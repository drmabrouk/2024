<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Member_Manager {
    public static function ajax_get_member() {
        try {
            if (!current_user_can('sm_manage_members') && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
            $nid = sanitize_text_field($_POST['national_id'] ?? '');
        $member = SM_DB::get_member_by_national_id($nid);
            if ($member) {
                self::validate_member_access($member->id);
                wp_send_json_success($member);
            } else {
                wp_send_json_error(['message' => 'Member not found']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_search_members() {
        try {
            if (!current_user_can('sm_manage_members') && !current_user_can('sm_manage_system') && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_admin_action', 'nonce');
            $query = sanitize_text_field($_REQUEST['member_search'] ?? ($_REQUEST['query'] ?? ''));
        if (empty($query)) wp_send_json_success([]);

        $members = SM_DB::get_members(['search' => $query, 'limit' => 15]);

            // Enhance with labels for UI
            foreach ($members as &$m) {
                $m->branch_label = SM_Settings::get_branch_name($m->governorate);
            }

            wp_send_json_success($members);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_add_member() {
        try {
            if (!current_user_can('sm_manage_members') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access: You lack the "sm_manage_members" capability.']);
            }

            // Role-based Enforcement: Branch Officers can only add to their branch
            if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
                $my_gov = get_user_meta(get_current_user_id(), 'sm_governorate', true);
                if ($my_gov) {
                    $_POST['governorate'] = $my_gov;
                }
            }

            if (isset($_POST['sm_nonce'])) {
                check_ajax_referer('sm_add_member', 'sm_nonce');
            } else {
                check_ajax_referer('sm_add_member', 'nonce');
            }
            if (!function_exists('wp_insert_user')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
            }
            $res = SM_DB::add_member($_POST);
            if (is_wp_error($res)) {
                wp_send_json_error(['message' => $res->get_error_message()]);
            } elseif (!$res) {
                wp_send_json_error(['message' => 'فشل في إضافة العضو لقاعدة البيانات.']);
            } else {
                delete_transient('sm_stats_global');
                if (!empty($_POST['governorate'])) delete_transient('sm_stats_' . $_POST['governorate']);
                wp_send_json_success($res);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error adding member: ' . $e->getMessage()]);
        }
    }

    public static function ajax_update_member() {
        try {
            if (!current_user_can('sm_manage_members') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            if (isset($_POST['sm_nonce'])) {
                check_ajax_referer('sm_add_member', 'sm_nonce');
            } else {
                check_ajax_referer('sm_add_member', 'nonce');
            }
            if (!function_exists('wp_update_user')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
            }
            $id = intval($_POST['member_id']);
            self::validate_member_access($id);
            $old = SM_DB::get_member_by_id($id);
            SM_DB::update_member($id, $_POST);

            delete_transient('sm_stats_global');
            if ($old) delete_transient('sm_stats_' . $old->governorate);
            if (!empty($_POST['governorate'])) delete_transient('sm_stats_' . $_POST['governorate']);
            SM_Finance::invalidate_financial_caches($old ? $old->governorate : null);

            wp_send_json_success(['message' => 'Updated']);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error updating member: ' . $e->getMessage()]);
        }
    }

    public static function ajax_import_staffs_csv() {
        self::check_capability('sm_manage_users');
        check_ajax_referer('sm_admin_action', 'sm_admin_nonce');

        if (empty($_FILES['csv_file']['tmp_name'])) {
            wp_send_json_error(['message' => 'لم يتم رفع أي ملف.']);
        }

        if (!function_exists('wp_insert_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) {
            wp_send_json_error(['message' => 'فشل في فتح الملف.']);
        }

        $success = 0;
        $header = fgetcsv($handle); // Skip header

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[0]) || empty($row[1])) continue;

            $user_data = [
                'user_login' => sanitize_user($row[0]),
                'user_email' => sanitize_email($row[1]),
                'display_name' => sanitize_text_field($row[2]),
                'user_pass' => $row[6] ?? null,
                'role' => 'sm_member' // Default
            ];

            if (username_exists($user_data['user_login']) || email_exists($user_data['user_email'])) {
                continue;
            }

            $uid = wp_insert_user($user_data);
            if (!is_wp_error($uid)) {
                $officer_id = sanitize_text_field($row[3] ?? '');
                update_user_meta($uid, 'sm_syndicateMemberIdAttr', $officer_id);
                $phone = sanitize_text_field($row[5] ?? '');

                update_user_meta($uid, 'sm_phone', $phone);
                update_user_meta($uid, 'sm_account_status', 'active');

                // If the user being imported is also a member (indicated by National ID in row[3])
                if (preg_match('/^[0-9]{14}$/', $officer_id)) {
                    $member_data = array(
                        'national_id' => $officer_id,
                        'name' => $user_data['display_name'],
                        'email' => $user_data['user_email'],
                        'phone' => $phone,
                        'wp_user_id' => $uid,
                        'governorate' => $row[4] ?? ''
                    );
                    SM_DB::add_member($member_data);
                }

                $success++;
            }
        }
        fclose($handle);
        wp_redirect(add_query_arg(['sm_tab' => 'staff', 'import_success' => $success], wp_get_referer()));
        exit;
    }

    public static function ajax_import_members_json() {
        try {
            self::check_capability('sm_manage_members');
            check_ajax_referer('sm_admin_action', 'nonce');

            $data_json = $_POST['members_data'] ?? '';
            $rows = json_decode(stripslashes($data_json), true);

            if (empty($rows) || !is_array($rows)) {
                wp_send_json_error(['message' => 'لا توجد بيانات صالحة للمعالجة.']);
            }

            $results = ['total' => 0, 'success' => 0, 'updated' => 0, 'error' => 0];

            // Pre-load mappings
            $govs = array_flip(SM_Settings::get_governorates());
            $custom_branches = SM_DB::get_branches_data();
            if (!empty($custom_branches)) {
                foreach ($custom_branches as $cb) { $govs[$cb->name] = $cb->slug; }
            }
            $grades = array_flip(SM_Settings::get_professional_grades());
            $degrees = array_flip(SM_Settings::get_academic_degrees());
            $specs = array_flip(SM_Settings::get_specializations());
            $depts = array_flip(SM_Settings::get_departments());
            $categories = [
                'A' => 'A', 'B' => 'B', 'C' => 'C',
                'فئة A' => 'A', 'فئة B' => 'B', 'فئة C' => 'C',
                'فئة A (كبرى)' => 'A', 'فئة B (متوسطة)' => 'B', 'فئة C (صغرى)' => 'C',
                'كبرى' => 'A', 'متوسطة' => 'B', 'صغرى' => 'C'
            ];
            $site_domain = parse_url(home_url(), PHP_URL_HOST);

            $current_user_id = get_current_user_id();
            $is_admin = current_user_can('sm_full_access') || current_user_can('manage_options');
            $my_gov = get_user_meta($current_user_id, 'sm_governorate', true);

            foreach ($rows as $row) {
                $results['total']++;
                $nid = sanitize_text_field($row['C'] ?? '');
                if (empty($nid)) { $results['error']++; continue; }

                $gov_val = self::map_label_to_key($row['D'] ?? '', $govs);
                // Force Branch Officer's own branch if they are not a global admin
                if (!$is_admin && $my_gov) {
                    $gov_val = $my_gov;
                }

                $member_data = [
                    'name' => sanitize_text_field($row['A'] ?? ''),
                    'member_code' => sanitize_text_field($row['B'] ?? ''),
                    'national_id' => $nid,
                    'governorate' => $gov_val,
                    'academic_degree' => self::map_label_to_key($row['E'] ?? '', $degrees),
                    'department' => self::map_label_to_key($row['F'] ?? '', $depts),
                    'graduation_date' => self::format_excel_date($row['G'] ?? ''),
                    'membership_number' => sanitize_text_field($row['H'] ?? ''),
                    'membership_start_date' => self::format_excel_date($row['I'] ?? ''),
                    'professional_grade' => self::map_label_to_key($row['K'] ?? '', $grades),
                    'specialization' => self::map_label_to_key($row['L'] ?? '', $specs),
                    'faculty' => !empty($row['E']) ? self::map_label_to_key($row['E'], array_flip(SM_Settings::get_faculties())) : 'sports_science', // Default if empty
                    'license_number' => sanitize_text_field($row['M'] ?? ''),
                    'license_issue_date' => self::format_excel_date($row['N'] ?? ''),
                    'facility_name' => sanitize_text_field($row['P'] ?? ''),
                    'facility_number' => sanitize_text_field($row['Q'] ?? ''),
                    'facility_category' => self::map_label_to_key($row['R'] ?? 'C', $categories),
                    'facility_license_issue_date' => self::format_excel_date($row['S'] ?? ''),
                    'email' => !empty($row['U']) ? sanitize_email($row['U']) : "{$nid}@{$site_domain}",
                    'phone' => sanitize_text_field($row['V'] ?? ''),
                    'residence_governorate' => self::map_label_to_key($row['W'] ?? '', $govs),
                    'residence_city' => sanitize_text_field($row['X'] ?? ''),
                    'residence_street' => sanitize_text_field($row['Y'] ?? ''),
                    'notes' => sanitize_textarea_field($row['Z'] ?? '')
                ];

                // Handle Dues info in notes (Debt tracking)
                $dues_info = [];
                if (!empty($row['J'])) {
                    $val_j = preg_replace('/[^0-9.]/', '', (string)$row['J']);
                    if (floatval($val_j) > 0) $dues_info[] = "مديونية عضوية: " . floatval($val_j);
                }
                if (!empty($row['O'])) {
                    $val_o = preg_replace('/[^0-9.]/', '', (string)$row['O']);
                    if (floatval($val_o) > 0) $dues_info[] = "مديونية ترخيص: " . floatval($val_o);
                }
                if (!empty($row['T'])) {
                    $val_t = preg_replace('/[^0-9.]/', '', (string)$row['T']);
                    if (floatval($val_t) > 0) $dues_info[] = "مديونية منشأة: " . floatval($val_t);
                }

                if (!empty($dues_info)) {
                    $debt_note = "\n[بيانات مديونية مستوردة]: " . implode(' | ', $dues_info);
                    $member_data['notes'] .= $debt_note;
                }

                // Auto-calculate expiration dates (+1 year)
                if ($member_data['membership_start_date']) {
                    $member_data['membership_expiration_date'] = date('Y-m-d', strtotime($member_data['membership_start_date'] . ' +1 year'));
                }
                if ($member_data['license_issue_date']) {
                    $member_data['license_expiration_date'] = date('Y-m-d', strtotime($member_data['license_issue_date'] . ' +1 year'));
                }
                if ($member_data['facility_license_issue_date']) {
                    $member_data['facility_license_expiration_date'] = date('Y-m-d', strtotime($member_data['facility_license_issue_date'] . ' +1 year'));
                }

                // MERGE LOGIC
                $existing = SM_DB::get_member_by_national_id($nid);
                if ($existing) {
                    SM_DB::update_member($existing->id, $member_data);
                    $mid = $existing->id;
                    $results['updated']++;
                } else {
                    // NEW MEMBER RULES: Username = National ID
                    $mid = SM_DB::add_member($member_data);
                    if (!is_wp_error($mid)) {
                        $results['success']++;
                    } else {
                        $results['error']++;
                        continue;
                    }
                }

                // Password = National ID (Applied to both new and updated for strict rule compliance)
                $u = get_user_by('login', $nid);
                if ($u) {
                    wp_set_password($nid, $u->ID);
                }

            }

            wp_send_json_success($results);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

    private static function map_label_to_key($label, $mapping) {
        $label = trim($label);
        if (empty($label)) return '';
        return $mapping[$label] ?? $label;
    }

    private static function format_excel_date($val) {
        if (empty($val)) return null;
        if ($val instanceof DateTime) {
            return $val->format('Y-m-d');
        }
        if (is_numeric($val)) {
            // Excel serial date to YYYY-MM-DD
            return date('Y-m-d', ($val - 25569) * 86400);
        }
        $ts = strtotime($val);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    public static function ajax_delete_member() {
        try {
            self::check_capability('sm_manage_members');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_delete_member', 'nonce');
            } else {
                check_ajax_referer('sm_delete_member', 'sm_delete_nonce');
            }
            $id = intval($_POST['member_id']);
            self::validate_member_access($id);
            $old = SM_DB::get_member_by_id($id);

            if (SM_DB::delete_member($id)) {
                delete_transient('sm_stats_global');
                if ($old) delete_transient('sm_stats_' . $old->governorate);
                SM_Finance::invalidate_financial_caches($old ? $old->governorate : null);
                wp_send_json_success(['message' => 'تم نقل العضو إلى المحذوفات بنجاح']);
            } else {
                wp_send_json_error(['message' => 'فشل في نقل العضو للمحذوفات']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_permanent_delete_member() {
        try {
            // Final removal restricted to Site Manager (manage_options) only
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'عذراً، هذه الصلاحية لمدير النظام فقط.']);
            }
            check_ajax_referer('sm_delete_member', 'nonce');

            $id = intval($_POST['member_id']);
            // Global admin can delete any member, but let's keep consistency
            $old = SM_DB::get_member_by_id($id);

            if (SM_DB::permanent_delete_member($id)) {
                delete_transient('sm_stats_global');
                if ($old) delete_transient('sm_stats_' . $old->governorate);
                SM_Finance::invalidate_financial_caches($old ? $old->governorate : null);
                wp_send_json_success(['message' => 'تم حذف العضو نهائياً من النظام']);
            } else {
                wp_send_json_error(['message' => 'فشل الحذف النهائي']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_restore_member() {
        try {
            self::check_capability('sm_manage_members');
            check_ajax_referer('sm_admin_action', 'nonce');

            $id = intval($_POST['member_id']);
            self::validate_member_access($id);
            $old = SM_DB::get_member_by_id($id);

            if (SM_DB::restore_member($id)) {
                delete_transient('sm_stats_global');
                if ($old) delete_transient('sm_stats_' . $old->governorate);
                SM_Finance::invalidate_financial_caches($old ? $old->governorate : null);
                wp_send_json_success(['message' => 'تمت استعادة العضو بنجاح']);
            } else {
                wp_send_json_error(['message' => 'فشل في استعادة العضو']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_update_member_account() {
        try {
            if (!current_user_can('sm_manage_members') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
            if (!function_exists('wp_update_user')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
            }
        if (!function_exists('media_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
        $mid = intval($_POST['member_id']);
        $uid = intval($_POST['wp_user_id']);
        $email = sanitize_email($_POST['email']);
        $pass = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        self::validate_member_access($mid);

        $data = array('ID' => $uid, 'user_email' => $email);
        if (!empty($pass)) { $data['user_pass'] = $pass; }

        $res = wp_update_user($data);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }

        if (!empty($role) && (current_user_can('sm_full_access') || current_user_can('manage_options'))) {
            $u = new WP_User($uid);
            $u->set_role($role);

            if (isset($_POST['governorate'])) {
                update_user_meta($uid, 'sm_governorate', sanitize_text_field($_POST['governorate']));
            }
            if (isset($_POST['rank'])) {
                update_user_meta($uid, 'sm_rank', sanitize_text_field($_POST['rank']));
            }
        }

        SM_DB::update_member($mid, ['email' => $email]);

            SM_Logger::log('تحديث حساب عضو', "تم تحديث بيانات الحساب للعضو ID: $mid");
            wp_send_json_success(['message' => 'Account updated']);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error updating account: ' . $e->getMessage()]);
        }
    }

    private static function check_capability($cap) {
        if (!current_user_can($cap) && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
    }

    private static function validate_member_access($mid) {
        if (!self::can_access_member($mid)) {
            wp_send_json_error(['message' => 'Access denied']);
        }
    }

    public static function can_access_member($member_id) {
        if (current_user_can('sm_full_access') || current_user_can('manage_options')) {
            return true;
        }
        $member = SM_DB::get_member_by_id($member_id);
        if (!$member) {
            return false;
        }
        $user = wp_get_current_user();
        if ($member->wp_user_id == $user->ID) {
            return true;
        }
        if (current_user_can('sm_branch_access')) {
            $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
            if ($my_gov && $member->governorate !== $my_gov) {
                return false;
            }
            return true;
        }
        return false;
    }

    public static function ajax_update_member_photo() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_photo_action', 'sm_photo_nonce');
        $mid = intval($_POST['member_id']);
        self::validate_member_access($mid);
        if (!function_exists('media_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
        $att_id = media_handle_upload('member_photo', 0);
        if (is_wp_error($att_id)) {
            wp_send_json_error(['message' => $att_id->get_error_message()]);
        }
            $url = wp_get_attachment_url($att_id);
            SM_DB::update_member_photo($mid, $url);
            wp_send_json_success(array('photo_url' => $url));
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_add_staff() {
        try {
            if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            if (!function_exists('wp_insert_user')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
            }
            if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicateMemberAction')) {
                wp_send_json_error(['message' => 'Security check failed']);
            }

            $user_login = sanitize_user($_POST['user_login']);
            $email = sanitize_email($_POST['user_email']);
            $display_name = sanitize_text_field($_POST['display_name']);
            $role = sanitize_text_field($_POST['role']);

            // Security: Prevent unauthorized role assignment
            if ($role === 'administrator' && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Insufficient permissions to assign Administrator role']);
            }

            if (username_exists($user_login) || email_exists($email)) {
                wp_send_json_error(['message' => 'اسم المستخدم أو البريد الإلكتروني مسجل مسبقاً']);
            }

            $pass = !empty($_POST['user_pass']) ? $_POST['user_pass'] : null;
            $uid = wp_insert_user([
                'user_login' => $user_login,
                'user_email' => $email,
                'display_name' => $display_name,
                'user_pass' => $pass,
                'role' => $role
            ]);

            if (is_wp_error($uid)) {
                wp_send_json_error(['message' => $uid->get_error_message()]);
            }

            // Sync Member Profile if data provided
            $nid = sanitize_text_field($_POST['national_id'] ?? '');
            if ($nid) {
                $existing_member = SM_DB::get_member_by_national_id($nid);
                if ($existing_member) {
                    SM_DB::update_member($existing_member->id, array_merge($_POST, ['wp_user_id' => $uid, 'email' => $email]));
                } else {
                    $member_data = array_merge($_POST, ['wp_user_id' => $uid, 'email' => $email]);
                    SM_DB::add_member($member_data);
                }
            }

            update_user_meta($uid, 'sm_syndicateMemberIdAttr', sanitize_text_field($_POST['officer_id']));
            update_user_meta($uid, 'sm_phone', sanitize_text_field($_POST['phone']));
            update_user_meta($uid, 'sm_account_status', 'active');

            $gov = sanitize_text_field($_POST['governorate'] ?? '');
            if (current_user_can('sm_branch_access')) {
                $gov = get_user_meta(get_current_user_id(), 'sm_governorate', true);
            }
            update_user_meta($uid, 'sm_governorate', $gov);

            if (!empty($_POST['rank'])) {
                update_user_meta($uid, 'sm_rank', sanitize_text_field($_POST['rank']));
            }

            SM_Logger::log('إضافة مستخدم (إدارة شاملة)', "تم إنشاء الحساب: $display_name بالدور: $role");
            wp_send_json_success($uid);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error adding staff: ' . $e->getMessage()]);
        }
    }

    public static function ajax_update_staff() {
        try {
            if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            if (!function_exists('wp_update_user')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
            }
            if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicateMemberAction')) {
                wp_send_json_error(['message' => 'Security check failed']);
            }

            $uid = intval($_POST['edit_officer_id']);
            $mid = intval($_POST['member_id'] ?? 0);
            $role = sanitize_text_field($_POST['role']);

            // Security: Prevent unauthorized role assignment
            if ($role === 'administrator' && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Insufficient permissions to assign Administrator role']);
            }

            // 1. Update WordPress User Core
            $user_data = [
                'ID' => $uid,
                'display_name' => sanitize_text_field($_POST['display_name']),
                'user_email' => sanitize_email($_POST['user_email'])
            ];
            if (!empty($_POST['user_pass'])) {
                $user_data['user_pass'] = $_POST['user_pass'];
            }

            $update_res = wp_update_user($user_data);
            if (is_wp_error($update_res)) {
                wp_send_json_error(['message' => $update_res->get_error_message()]);
            }

            $u = new WP_User($uid);
            $u->set_role($role);

            // 2. Update User Meta (Unified Account/Member Meta)
            if (isset($_POST['officer_id'])) update_user_meta($uid, 'sm_syndicateMemberIdAttr', sanitize_text_field($_POST['officer_id']));
            if (isset($_POST['phone'])) update_user_meta($uid, 'sm_phone', sanitize_text_field($_POST['phone']));
            if (isset($_POST['governorate'])) update_user_meta($uid, 'sm_governorate', sanitize_text_field($_POST['governorate']));
            if (isset($_POST['account_status'])) update_user_meta($uid, 'sm_account_status', sanitize_text_field($_POST['account_status']));

            if (isset($_POST['rank'])) {
                update_user_meta($uid, 'sm_rank', sanitize_text_field($_POST['rank']));
            }

            // 3. Bi-directional Synchronization with sm_members
            if ($mid) {
                SM_DB::update_member($mid, $_POST);
            } else {
                // Check if member exists by national ID or username to link
                $nid = sanitize_text_field($_POST['national_id'] ?? '');
                if ($nid) {
                    $existing_member = SM_DB::get_member_by_national_id($nid);
                    if ($existing_member) {
                        SM_DB::update_member($existing_member->id, array_merge($_POST, ['wp_user_id' => $uid]));
                    }
                }
            }

            SM_Logger::log('تحديث مستخدم (إدارة شاملة)', "تم تحديث بيانات المستخدم: {$_POST['display_name']} ومزامنة ملف العضوية.");
            wp_send_json_success(['message' => 'User and member profile updated successfully']);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error updating staff: ' . $e->getMessage()]);
        }
    }

    public static function ajax_delete_staff() {
        try {
            self::check_capability('sm_manage_users');
            if (!function_exists('wp_delete_user')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
            }
            if (!wp_verify_nonce($_POST['nonce'], 'sm_syndicateMemberAction')) {
                wp_send_json_error(['message' => 'Security check failed']);
            }
            $uid = intval($_POST['user_id']);
            if ($uid === get_current_user_id()) {
                wp_send_json_error(['message' => 'Cannot delete yourself']);
            }
            wp_delete_user($uid);
            wp_send_json_success(['message' => 'Deleted']);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_export_users_csv() {
        try {
            if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }

            $args = array(
                'number' => -1,
                'role' => $_GET['role_filter'] ?? '',
                'meta_query' => array('relation' => 'AND')
            );

            if (!empty($_GET['gov_filter'])) {
                $args['meta_query'][] = array('key' => 'sm_governorate', 'value' => $_GET['gov_filter']);
            }
            if (!empty($_GET['status_filter'])) {
                $args['meta_query'][] = array('key' => 'sm_account_status', 'value' => $_GET['status_filter']);
            }

            $users = SM_DB::get_staff($args);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=system_users_' . date('Y-m-d') . '.csv');
            $output = fopen('php://output', 'w');

            // UTF-8 BOM for Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, array('اسم المستخدم', 'البريد الإلكتروني', 'الاسم الكامل', 'الرقم القومي', 'الدور', 'الفرع', 'رقم الهاتف', 'الحالة'));

            foreach ($users as $u) {
                $role = (array)$u->roles;
                $status = get_user_meta($u->ID, 'sm_account_status', true) ?: 'active';
                fputcsv($output, array(
                    $u->user_login,
                    $u->user_email,
                    $u->display_name,
                    get_user_meta($u->ID, 'sm_national_id', true) ?: get_user_meta($u->ID, 'sm_syndicateMemberIdAttr', true),
                    reset($role),
                    SM_Settings::get_branch_name(get_user_meta($u->ID, 'sm_governorate', true)),
                    get_user_meta($u->ID, 'sm_phone', true),
                    $status === 'active' ? 'نشط' : 'مقيد'
                ));
            }
            fclose($output);
            exit;
        } catch (Throwable $e) {
            wp_die($e->getMessage());
        }
    }

    public static function ajax_bulk_delete_users() {
        try {
            self::check_capability('sm_manage_users');
            if (!function_exists('wp_delete_user')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
            }
            if (!wp_verify_nonce($_POST['nonce'], 'sm_syndicateMemberAction')) {
                wp_send_json_error(['message' => 'Security check failed']);
            }
            $ids = explode(',', $_POST['user_ids']);
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id === get_current_user_id()) {
                    continue;
                }
                wp_delete_user($id);
            }
            wp_send_json_success(['message' => 'Bulk delete complete']);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_submit_update_request_ajax() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'يجب تسجيل الدخول']);
            }
            check_ajax_referer('sm_update_request', 'nonce');
        $mid = intval($_POST['member_id']);
        self::validate_member_access($mid);
            if (SM_DB::add_update_request($mid, $_POST)) {
                wp_send_json_success(['message' => 'Request submitted']);
            } else {
                wp_send_json_error(['message' => 'Failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_process_update_request_ajax() {
        try {
            if (!current_user_can('sm_manage_members') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            check_ajax_referer('sm_update_request', 'nonce');
            if (SM_DB::process_update_request(intval($_POST['request_id']), sanitize_text_field($_POST['status']))) {
                wp_send_json_success(['message' => 'Request processed']);
            } else {
                wp_send_json_error(['message' => 'Failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_submit_membership_request_stage3() {
        try {
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_registration_nonce', 'nonce');
            } else {
                check_ajax_referer('sm_registration_nonce', '_wpnonce');
            }
            $nid = sanitize_text_field($_POST['national_id']);
            if (!empty($_FILES)) {
                if (!function_exists('wp_handle_upload')) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                }
                $upd = ['status' => 'Awaiting Physical Documents', 'current_stage' => 3];
                $map = [
                    'doc_qualification' => 'doc_qualification_url',
                    'doc_id' => 'doc_id_url',
                    'doc_military' => 'doc_military_url',
                    'doc_criminal' => 'doc_criminal_url',
                    'doc_photo' => 'doc_photo_url'
                ];
                foreach ($map as $f => $c) {
                    if (!empty($_FILES[$f])) {
                        $u = wp_handle_upload($_FILES[$f], ['test_form' => false]);
                        if (isset($u['url'])) {
                            $upd[$c] = $u['url'];
                        }
                    }
                }
                SM_DB::update_membership_request($nid, $upd);
                wp_send_json_success(['message' => 'Stage 3 complete']);
            }
            wp_send_json_error(['message' => 'No files.']);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_process_membership_request() {
        try {
            if (!current_user_can('sm_manage_members') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            check_ajax_referer('sm_admin_action', 'nonce');
            if (!function_exists('wp_insert_user')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
            }

            $rid = intval($_POST['request_id']);
        $status = sanitize_text_field($_POST['status']);
        $reason = sanitize_text_field($_POST['reason'] ?? '');

        $req = SM_DB::get_membership_request($rid);
        if (!$req) {
            wp_send_json_error(['message' => 'Request not found']);
        }

        if ($status === 'approved') {
            $data = (array)$req;
            $data['membership_start_date'] = current_time('Y-m-d');
            $data['membership_expiration_date'] = date('Y-12-31');
            $data['membership_status'] = 'Active – New Member';

            $exclude = [
                'id', 'status', 'processed_by', 'created_at', 'current_stage',
                'payment_method', 'payment_reference', 'payment_screenshot_url',
                'doc_qualification_url', 'doc_id_url', 'doc_military_url',
                'doc_criminal_url', 'doc_photo_url', 'rejection_reason', 'notes'
            ];
            foreach ($exclude as $key) {
                unset($data[$key]);
            }

            $mid = SM_DB::add_member($data);
            if (is_wp_error($mid)) {
                wp_send_json_error(['message' => $mid->get_error_message()]);
            }

            if ($req->doc_photo_url) {
                SM_DB::update_member_photo($mid, $req->doc_photo_url);
            }

            $docs = [
                'doc_qualification_url' => 'شهادة المؤهل الدراسي',
                'doc_id_url' => 'بطاقة الرقم القومي',
                'doc_military_url' => 'شهادة الخدمة العسكرية',
                'doc_criminal_url' => 'صحيفة الحالة الجنائية',
                'payment_screenshot_url' => 'إيصال سداد رسوم العضوية'
            ];
            foreach ($docs as $f => $t) {
                if ($req->$f) {
                    SM_DB::add_document([
                        'member_id' => $mid,
                        'category' => 'other',
                        'title' => $t,
                        'file_url' => $req->$f,
                        'file_type' => 'application/pdf'
                    ]);
                }
            }

            $fin_settings = SM_Settings::get_finance_settings();
            SM_Finance::record_payment([
                'member_id' => $mid,
                'amount' => (float)($fin_settings['membership_new'] ?? 480),
                'payment_type' => 'membership',
                'payment_date' => current_time('mysql'),
                'target_year' => (int)date('Y'),
                'details_ar' => 'رسوم اشتراك عضوية جديدة - طلب رقم ' . $rid,
                'notes' => 'طريقة الدفع: ' . ($req->payment_method ?: 'manual')
            ]);
        }

        $upd = [
            'status' => $status,
            'processed_by' => get_current_user_id()
        ];
        if ($reason) {
            $upd['notes'] = $reason;
        }

        SM_DB::update_membership_request($rid, $upd);

            SM_Logger::log('معالجة طلب عضوية', "تم {$status} طلب العضوية للرقم القومي: {$req->national_id}");
            wp_send_json_success(['message' => "Request $status"]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error processing membership: ' . $e->getMessage()]);
        }
    }

    public static function ajax_upload_document() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_document_action', 'nonce');
        $mid = intval($_POST['member_id']);
        self::validate_member_access($mid);
        if (empty($_FILES['document_file']['name'])) {
            wp_send_json_error(['message' => 'No file']);
        }
        if (!function_exists('media_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
        $aid = media_handle_upload('document_file', 0);
        if (is_wp_error($aid)) {
            wp_send_json_error(['message' => $aid->get_error_message()]);
        }
        $did = SM_DB::add_document([
            'member_id' => $mid,
            'category' => sanitize_text_field($_POST['category']),
            'title' => sanitize_text_field($_POST['title']),
            'file_url' => wp_get_attachment_url($aid),
            'file_type' => get_post_mime_type($aid)
        ]);
            if ($did) {
                wp_send_json_success(['doc_id' => $did]);
            } else {
                wp_send_json_error(['message' => 'Failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_get_documents() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            $mid = intval($_GET['member_id']);
            self::validate_member_access($mid);
            wp_send_json_success(SM_DB::get_member_documents($mid, $_GET));
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_delete_document() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_document_action', 'nonce');
        $mid = SM_DB::get_document_member_id(intval($_POST['doc_id']));
        self::validate_member_access($mid);
            if (SM_DB::delete_document(intval($_POST['doc_id']))) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_get_document_logs() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_document_action', 'nonce');
            $mid = SM_DB::get_document_member_id(intval($_GET['doc_id']));
            self::validate_member_access($mid);
            wp_send_json_success(SM_DB::get_document_logs(intval($_GET['doc_id'])));
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_log_document_view() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_document_action', 'nonce');
            $mid = SM_DB::get_document_member_id(intval($_POST['doc_id']));
        self::validate_member_access($mid);
            SM_DB::log_document_action(intval($_POST['doc_id']), 'view');
            wp_send_json_success(['message' => 'Log updated']);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function handle_print() {
        if (!current_user_can('sm_print_reports')) {
            wp_die('Unauthorized');
        }
        $type = sanitize_text_field($_GET['type'] ?? ($_GET['print_type'] ?? ''));
        $mid = intval($_GET['member_id'] ?? 0);
        if ($mid && !self::can_access_member($mid)) {
            wp_die('Access denied');
        }
        switch($type) {
            case 'id_card':
                include SM_PLUGIN_DIR . 'templates/print-id-cards.php';
                break;
            case 'credentials':
                include SM_PLUGIN_DIR . 'templates/print-member-credentials.php';
                break;
            case 'membership_form':
                include SM_PLUGIN_DIR . 'templates/print-membership-form.php';
                break;
            default:
                wp_die('Invalid print type: ' . esc_html($type));
        }
        exit;
    }

    public static function ajax_submit_professional_request() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_professional_action', 'nonce');
        $mid = intval($_POST['member_id']);
        self::validate_member_access($mid);
            if (SM_DB::add_professional_request($mid, sanitize_text_field($_POST['request_type']))) {
                wp_send_json_success(['message' => 'Request submitted']);
            } else {
                wp_send_json_error(['message' => 'Failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_process_professional_request() {
        try {
            if (!current_user_can('sm_manage_members') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            check_ajax_referer('sm_admin_action', 'nonce');
            if (SM_DB::process_professional_request(intval($_POST['request_id']), sanitize_text_field($_POST['status']), sanitize_textarea_field($_POST['notes'] ?? ''))) {
                wp_send_json_success(['message' => 'Request processed']);
            } else {
                wp_send_json_error(['message' => 'Failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_track_membership_request() {
        try {
            // This is primarily called from public context
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_registration_nonce', 'nonce');
            } else {
                check_ajax_referer('sm_registration_nonce', '_wpnonce');
            }
            $req = SM_DB::get_membership_request_by_national_id(sanitize_text_field($_POST['national_id']));
            if (!$req) {
                wp_send_json_error(['message' => 'لم يتم العثور على الطلب']);
            }
            $map = [
                'Pending Payment Verification' => 'قيد مراجعة الدفع',
                'approved' => 'تم القبول',
                'rejected' => 'مرفوض',
                'pending' => 'قيد المراجعة'
            ];
            wp_send_json_success([
                'status' => $map[$req->status] ?? $req->status,
                'current_stage' => $req->current_stage,
                'rejection_reason' => $req->notes ?? ''
            ]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
