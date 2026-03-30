<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_License_Manager {
    private static function check_capability($cap) {
        if (!current_user_can($cap)) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }
    }

    private static function validate_member_access($member_id) {
        if (!SM_Member_Manager::can_access_member($member_id)) {
            wp_send_json_error(['message' => 'Access denied to this member data.']);
        }
    }

    public static function ajax_update_license() {
        try {
            if (!current_user_can('sm_manage_licenses') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_add_member', 'nonce');
            } else {
                check_ajax_referer('sm_add_member', 'sm_nonce');
            }
            $mid = intval($_POST['member_id']);
        self::validate_member_access($mid);

        $res = SM_DB::update_member($mid, [
            'license_number' => sanitize_text_field($_POST['license_number']),
            'license_issue_date' => sanitize_text_field($_POST['license_issue_date']),
            'license_expiration_date' => sanitize_text_field($_POST['license_expiration_date'])
        ]);

        if ($res === false) {
            wp_send_json_error(['message' => 'فشل في تحديث بيانات الترخيص في قاعدة البيانات.']);
        }

        SM_DB::add_document([
            'member_id' => $mid,
            'category' => 'licenses',
            'title' => "تصريح مزاولة مهنة رقم " . $_POST['license_number'],
            'file_url' => admin_url('admin-ajax.php?action=sm_print_license&member_id=' . $mid),
            'file_type' => 'application/pdf'
        ]);
            SM_Logger::log('تحديث ترخيص مزاولة', "العضو ID: $mid");
            wp_send_json_success();
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error updating license: ' . $e->getMessage()]);
        }
    }

    public static function ajax_update_facility() {
        try {
            if (!current_user_can('sm_manage_licenses') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_add_member', 'nonce');
            } else {
                check_ajax_referer('sm_add_member', 'sm_nonce');
            }
            $mid = intval($_POST['member_id']);
        self::validate_member_access($mid);

        $res = SM_DB::update_member($mid, [
            'facility_name' => sanitize_text_field($_POST['facility_name']),
            'facility_number' => sanitize_text_field($_POST['facility_number']),
            'facility_category' => sanitize_text_field($_POST['facility_category']),
            'facility_license_issue_date' => sanitize_text_field($_POST['facility_license_issue_date']),
            'facility_license_expiration_date' => sanitize_text_field($_POST['facility_license_expiration_date']),
            'facility_address' => sanitize_textarea_field($_POST['facility_address'])
        ]);

        if ($res === false) {
            wp_send_json_error(['message' => 'فشل في تحديث بيانات المنشأة في قاعدة البيانات.']);
        }

        SM_DB::add_document([
            'member_id' => $mid,
            'category' => 'licenses',
            'title' => "ترخيص منشأة: " . $_POST['facility_name'],
            'file_url' => admin_url('admin-ajax.php?action=sm_print_facility&member_id=' . $mid),
            'file_type' => 'application/pdf'
        ]);
            SM_Logger::log('تحديث منشأة', "العضو ID: $mid");
            wp_send_json_success();
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error updating facility: ' . $e->getMessage()]);
        }
    }

    public static function ajax_soft_delete_facility() {
        self::check_capability('sm_manage_licenses');
        check_ajax_referer('sm_admin_action', 'nonce');
        $id = intval($_POST['id']);
        self::validate_member_access($id);
        if (SM_DB::soft_delete_facility($id)) {
            SM_Logger::log('حذف مؤقت للمنشأة', "العضو ID: $id");
            wp_send_json_success();
        }
        wp_send_json_error(['message' => 'فشل في حذف المنشأة']);
    }

    public static function ajax_restore_facility() {
        self::check_capability('sm_manage_licenses');
        check_ajax_referer('sm_admin_action', 'nonce');
        $id = intval($_POST['id']);
        self::validate_member_access($id);
        if (SM_DB::restore_facility($id)) {
            SM_Logger::log('استعادة المنشأة', "العضو ID: $id");
            wp_send_json_success();
        }
        wp_send_json_error(['message' => 'فشل في استعادة المنشأة']);
    }

    public static function ajax_permanent_delete_facility() {
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        $id = intval($_POST['id']);
        self::validate_member_access($id);
        if (SM_DB::permanent_delete_facility($id)) {
            SM_Logger::log('حذف نهائي للمنشأة', "العضو ID: $id");
            wp_send_json_success();
        }
        wp_send_json_error(['message' => 'فشل في الحذف النهائي للمنشأة']);
    }

    public static function ajax_soft_delete_license() {
        self::check_capability('sm_manage_licenses');
        check_ajax_referer('sm_admin_action', 'nonce');
        $id = intval($_POST['id']);
        self::validate_member_access($id);
        if (SM_DB::soft_delete_license($id)) {
            SM_Logger::log('حذف مؤقت للترخيص', "العضو ID: $id");
            wp_send_json_success();
        }
        wp_send_json_error(['message' => 'فشل في حذف الترخيص']);
    }

    public static function ajax_restore_license() {
        self::check_capability('sm_manage_licenses');
        check_ajax_referer('sm_admin_action', 'nonce');
        $id = intval($_POST['id']);
        self::validate_member_access($id);
        if (SM_DB::restore_license($id)) {
            SM_Logger::log('استعادة الترخيص', "العضو ID: $id");
            wp_send_json_success();
        }
        wp_send_json_error(['message' => 'فشل في استعادة الترخيص']);
    }

    public static function ajax_permanent_delete_license() {
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        $id = intval($_POST['id']);
        self::validate_member_access($id);
        if (SM_DB::permanent_delete_license($id)) {
            SM_Logger::log('حذف نهائي للترخيص', "العضو ID: $id");
            wp_send_json_success();
        }
        wp_send_json_error(['message' => 'فشل في الحذف النهائي للترخيص']);
    }

    public static function ajax_verify_document() {
        try {
            // This is a public search, but we should still have a nonce if called from our forms
            if (isset($_REQUEST['nonce'])) {
                check_ajax_referer('sm_contact_action', 'nonce');
            }
            $val = trim(sanitize_text_field($_POST['search_value'] ?? ''));
            $type = sanitize_text_field($_POST['search_type'] ?? 'auto');

            if (empty($val)) {
                wp_send_json_error(['message' => 'يرجى إدخال قيمة للبحث']);
            }

            $user = wp_get_current_user();
            $is_admin = current_user_can('sm_full_access') || current_user_can('manage_options');
            $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

            $blocks = [];
            $grades = SM_Settings::get_professional_grades();
            $specs = SM_Settings::get_specializations();

            // Intelligent Detection
            if ($type === 'auto') {
                if (preg_match('/^[0-9]{14}$/', $val)) $type = 'national_id';
                elseif (strpos($val, 'REG-') === 0 || strpos($val, 'SR-') === 0 || (strlen($val) > 8 && is_numeric($val))) $type = 'tracking';
                elseif (is_numeric($val)) $type = 'numeric_short';
            }

            // 1. Search by National ID -> Full Aggregated Report
            if ($type === 'national_id') {
                $member = SM_DB::get_member_by_national_id($val);
                if ($member) {
                    // Security Check
                    if (!$is_admin && $my_gov && $member->governorate !== $my_gov) {
                        wp_send_json_error(['message' => 'عذراً، لا تملك صلاحية الوصول لبيانات هذا العضو في فرع آخر.']);
                    }
                    $blocks[] = [ 'type' => 'profile', 'owner' => self::format_owner_data($member, $grades, $specs) ];
                    if ($member->membership_number) $blocks[] = [ 'type' => 'membership', 'membership' => [ 'number' => $member->membership_number, 'status' => $member->membership_status ?: 'Active', 'expiry' => $member->membership_expiration_date ?: '---' ] ];
                    if ($member->license_number) $blocks[] = [ 'type' => 'practice', 'practice' => [ 'number' => $member->license_number, 'issue_date' => $member->license_issue_date ?: '---', 'expiry' => $member->license_expiration_date ?: '---' ] ];
                    if ($member->facility_number) $blocks[] = [ 'type' => 'facility', 'facility' => [ 'name' => $member->facility_name, 'number' => $member->facility_number, 'category' => $member->facility_category, 'address' => $member->facility_address ?: '---', 'expiry' => $member->facility_license_expiration_date ?: '---' ] ];
                }
                $reqs = self::find_tracking_by_national_id($val);
                foreach ($reqs as $r) { $blocks[] = ['type' => 'tracking', 'tracking' => $r]; }
                if (!empty($blocks)) wp_send_json_success($blocks);
            }

            // 2. Exact Match Searches (Return Full Aggregated Report for Identification)
            $found_member = null;
            if ($type === 'membership' || ($type === 'numeric_short' && empty($blocks))) {
                $found_member = SM_DB::get_member_by_membership_number($val);
            }
            if (!$found_member && ($type === 'practice' || ($type === 'numeric_short' && empty($blocks)))) {
                $found_member = SM_DB::get_member_by_license_number($val);
            }
            if (!$found_member && ($type === 'facility' || ($type === 'numeric_short' && empty($blocks)))) {
                $found_member = SM_DB::get_member_by_facility_number($val);
            }

            if ($found_member) {
                if (!$is_admin && $my_gov && $found_member->governorate !== $my_gov) {
                    wp_send_json_error(['message' => 'عذراً، لا تملك صلاحية الوصول لبيانات هذا السجل في فرع آخر.']);
                }
                $blocks[] = [ 'type' => 'profile', 'owner' => self::format_owner_data($found_member, $grades, $specs) ];
                if ($found_member->membership_number) $blocks[] = [ 'type' => 'membership', 'membership' => [ 'number' => $found_member->membership_number, 'status' => $found_member->membership_status ?: 'Active', 'expiry' => $found_member->membership_expiration_date ?: '---' ] ];
                if ($found_member->license_number) $blocks[] = [ 'type' => 'practice', 'practice' => [ 'number' => $found_member->license_number, 'issue_date' => $found_member->license_issue_date ?: '---', 'expiry' => $found_member->license_expiration_date ?: '---' ] ];
                if ($found_member->facility_number) $blocks[] = [ 'type' => 'facility', 'facility' => [ 'name' => $found_member->facility_name, 'number' => $found_member->facility_number, 'category' => $found_member->facility_category, 'address' => $found_member->facility_address ?: '---', 'expiry' => $found_member->facility_license_expiration_date ?: '---' ] ];

                $reqs = self::find_tracking_by_national_id($found_member->national_id);
                foreach ($reqs as $r) { $blocks[] = ['type' => 'tracking', 'tracking' => $r]; }
                wp_send_json_success($blocks);
            }

            if ($type === 'tracking') {
                $track = self::find_tracking_by_code($val);
                if ($track) {
                    if (!$is_admin && $my_gov && $track['branch_slug'] !== $my_gov) {
                        wp_send_json_error(['message' => 'طلب التتبع يخص فرعاً آخر.']);
                    }
                    wp_send_json_success([[ 'type' => 'tracking', 'tracking' => $track ]]);
                }
            }

            // Fallback: Partial Name or Facility Name search
            if ($type === 'auto' && strlen($val) >= 3 && !is_numeric($val)) {
                // Try searching members (including facility names)
                $members = SM_DB::get_members(['search' => $val, 'limit' => 5]);
                if (!empty($members)) {
                    $all_blocks = [];
                    foreach ($members as $m) {
                        if (!$is_admin && $my_gov && $m->governorate !== $my_gov) continue;

                        $all_blocks[] = [ 'type' => 'profile', 'owner' => self::format_owner_data($m, $grades, $specs) ];
                        if ($m->membership_number) $all_blocks[] = [ 'type' => 'membership', 'membership' => [ 'number' => $m->membership_number, 'status' => $m->membership_status ?: 'Active', 'expiry' => $m->membership_expiration_date ?: '---' ] ];
                        if ($m->license_number) $all_blocks[] = [ 'type' => 'practice', 'practice' => [ 'number' => $m->license_number, 'issue_date' => $m->license_issue_date ?: '---', 'expiry' => $m->license_expiration_date ?: '---' ] ];
                        if ($m->facility_number) $all_blocks[] = [ 'type' => 'facility', 'facility' => [ 'name' => $m->facility_name, 'number' => $m->facility_number, 'category' => $m->facility_category, 'address' => $m->facility_address ?: '---', 'expiry' => $m->facility_license_expiration_date ?: '---' ] ];
                    }
                    if (!empty($all_blocks)) wp_send_json_success($all_blocks);
                }
            }

            wp_send_json_error(['message' => 'عذراً، لم يتم العثور على أية بيانات مطابقة لقيمة البحث المدخلة.']);
        } catch (Throwable $e) {
            SM_Logger::log('Verification Error', $e->getMessage());
            wp_send_json_error(['message' => 'خطأ تقني في معالجة البحث.']);
        }
    }

    private static function format_owner_data($member, $grades, $specs) {
        return [
            'name' => $member->name ?? '---',
            'national_id' => $member->national_id ?? '---',
            'email' => $member->email ?? '---',
            'phone' => $member->phone ?? '---',
            'branch' => SM_Settings::get_branch_name($member->governorate ?? ''),
            'grade' => $grades[$member->professional_grade ?? ''] ?? ($member->professional_grade ?? '---'),
            'specialization' => $specs[$member->specialization ?? ''] ?? ($member->specialization ?? '---'),
            'role_label' => 'عضو نقابة معتمد',
        ];
    }

    private static function find_tracking_by_national_id($nid) {
        $found = [];
        $user = wp_get_current_user();
        $is_admin = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $req = SM_DB::get_membership_request_by_national_id($nid);
        if ($req) {
            if ($is_admin || !$my_gov || $req->governorate === $my_gov) {
                $found[] = self::map_membership_request($req);
            }
        }

        $member = SM_DB::get_member_by_national_id($nid);
        if ($member) {
            $s_reqs = SM_DB_Services::get_service_requests(['member_id' => $member->id]);
            foreach ($s_reqs as $sr) { $found[] = self::map_service_request($sr); }
        }
        return $found;
    }

    private static function find_tracking_by_code($code) {
        if (strpos($code, 'REG-') === 0 || (is_numeric($code) && strlen($code) < 9)) {
            $id = str_replace('REG-', '', $code);
            $req = SM_DB::get_membership_request((int)$id);
            if ($req) return self::map_membership_request($req);
        }
        $id = 0;
        if (strpos($code, 'SR-') === 0) $id = str_replace('SR-', '', $code);
        elseif (strlen($code) > 8 && is_numeric($code)) $id = substr($code, 8);
        elseif (is_numeric($code)) $id = $code;
        if ($id) {
            $req = SM_DB_Services::get_service_request_by_id((int)$id);
            if ($req) return self::map_service_request($req);
        }
        return null;
    }

    private static function map_membership_request($req) {
        $map = [
            'Pending Payment' => 'بانتظار السداد',
            'Pending Payment Verification' => 'قيد مراجعة الدفع',
            'Awaiting Physical Documents' => 'بانتظار الملف الورقي',
            'Under Review' => 'قيد المراجعة والتدقيق',
            'approved' => 'تم القبول والتفعيل',
            'rejected' => 'مرفوض'
        ];
        return [
            'id' => 'REG-' . $req->id,
            'service' => 'طلب قيد عضوية جديدة',
            'status' => $map[$req->status] ?? $req->status,
            'notes' => $req->notes ?? ($req->rejection_reason ?? ''),
            'date' => date('Y-m-d', strtotime($req->created_at)),
            'member' => $req->name ?? '---',
            'branch' => SM_Settings::get_branch_name($req->governorate ?? ''),
            'branch_slug' => $req->governorate ?? ''
        ];
    }

    private static function map_service_request($req) {
        $statuses = [
            'pending' => 'قيد الانتظار',
            'processing' => 'جاري التنفيذ',
            'approved' => 'مكتمل / معتمد',
            'rejected' => 'مرفوض'
        ];
        return [
            'id' => date('Ymd', strtotime($req->created_at)) . $req->id,
            'service' => $req->service_name ?? 'خدمة رقمية',
            'status' => $statuses[$req->status] ?? $req->status,
            'notes' => $req->admin_notes ?? '',
            'date' => date('Y-m-d', strtotime($req->created_at)),
            'member' => $req->member_name ?: ($req->name ?? '---'),
            'branch' => SM_Settings::get_branch_name($req->governorate ?? ''),
            'branch_slug' => $req->governorate ?? ''
        ];
    }

    public static function ajax_print_license() {
        if (!current_user_can('sm_print_reports')) { wp_die('Unauthorized'); }
        check_admin_referer('sm_admin_action', 'nonce');
        $mid = intval($_GET['member_id'] ?? 0);
        if (!$mid || !SM_Member_Manager::can_access_member($mid)) { wp_die('Access denied'); }
        include SM_PLUGIN_DIR . 'templates/print-practice-license.php';
        exit;
    }

    public static function ajax_print_facility() {
        if (!current_user_can('sm_print_reports')) { wp_die('Unauthorized'); }
        check_admin_referer('sm_admin_action', 'nonce');
        $mid = intval($_GET['member_id'] ?? 0);
        if (!$mid || !SM_Member_Manager::can_access_member($mid)) { wp_die('Access denied'); }
        include SM_PLUGIN_DIR . 'templates/print-facility-license.php';
        exit;
    }

    public static function ajax_verify_suggest() {
        try {
            if (isset($_REQUEST['nonce'])) {
                check_ajax_referer('sm_contact_action', 'nonce');
            }
            $q = sanitize_text_field($_GET['query'] ?? '');
            $type = sanitize_text_field($_GET['type'] ?? 'auto');
            if (strlen($q) < 3) wp_send_json_success([]);

            $res = SM_DB::get_member_suggestions($q, 8);
            $sug = [];
            foreach ($res as $r) {
                if ($type === 'national_id' || $type === 'auto') $sug[] = $r->national_id;
                if ($type === 'name' || $type === 'auto') $sug[] = $r->name;
                if ($type === 'membership' || $type === 'auto') if($r->membership_number) $sug[] = $r->membership_number;
                if ($type === 'practice' || $type === 'auto') if($r->license_number) $sug[] = $r->license_number;
            }
            wp_send_json_success(array_values(array_unique(array_filter($sug))));
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
