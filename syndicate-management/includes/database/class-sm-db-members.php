<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_Members {

    public static function get_staff($args = array()) {
        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $default_args = array(
            'number' => 20,
            'offset' => 0,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'meta_query' => array('relation' => 'AND')
        );

        // Advanced Filtering from Args
        if (!empty($args['governorate'])) {
            $default_args['meta_query'][] = array(
                'key' => 'sm_governorate',
                'value' => sanitize_text_field($args['governorate']),
                'compare' => '='
            );
        }

        if (!empty($args['account_status'])) {
            $default_args['meta_query'][] = array(
                'key' => 'sm_account_status',
                'value' => sanitize_text_field($args['account_status']),
                'compare' => '='
            );
        }

        // Security: If not a full admin, restricted to their own branch
        if (!$has_full_access) {
            if ($my_gov) {
                $default_args['meta_query'][] = array(
                    'key' => 'sm_governorate',
                    'value' => $my_gov,
                    'compare' => '='
                );
            } else {
                // Non-admin with no governorate can only see themselves
                $default_args['include'] = array($user->ID);
            }
        }

        $args = wp_parse_args($args, $default_args);

        // Handle search for meta fields if needed, but get_users handles basic search well
        return get_users($args);
    }

    public static function get_members($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';
        $is_deleted = isset($args['is_deleted']) ? intval($args['is_deleted']) : 0;

        if ($is_deleted === 0) {
            $where = "($table_name.is_deleted = 0 OR $table_name.is_deleted IS NULL)";
        } else {
            $where = "$table_name.is_deleted = 1";
        }

        $params = array();

        $limit = isset($args['limit']) ? intval($args['limit']) : 20;
        $offset = isset($args['offset']) ? intval($args['offset']) : 0;
        if ($limit < -1) $limit = 20;

        // Role-based filtering (Governorate)
        $user = wp_get_current_user();
        $has_full_access = current_user_can('manage_options') || current_user_can('sm_full_access');
        if (!$has_full_access) {
            $gov = get_user_meta($user->ID, 'sm_governorate', true);
            if ($gov) {
                $where .= " AND governorate = %s";
                $params[] = $gov;
            } else {
                $where .= " AND 1=0";
            }
        }

        if (!empty($args['professional_grade'])) {
            $where .= " AND professional_grade = %s";
            $params[] = $args['professional_grade'];
        }

        if (!empty($args['specialization'])) {
            $where .= " AND specialization = %s";
            $params[] = $args['specialization'];
        }

        if (!empty($args['membership_status'])) {
            $where .= " AND membership_status = %s";
            $params[] = $args['membership_status'];
        }

        if (!empty($args['governorate'])) {
            $where .= " AND governorate = %s";
            $params[] = $args['governorate'];
        }

        if (!empty($args['include'])) {
            $include_ids = array_map('intval', (array)$args['include']);
            if (!empty($include_ids)) {
                $where .= " AND id IN (" . implode(',', $include_ids) . ")";
            }
        }

        if (!empty($args['only_with_license'])) {
            $where .= " AND license_number IS NOT NULL AND license_number != ''";
        }

        if (!empty($args['only_with_facility'])) {
            $where .= " AND facility_number IS NOT NULL AND facility_number != ''";
        }

        if (isset($args['facility_is_deleted'])) {
            $where .= $wpdb->prepare(" AND facility_is_deleted = %d", intval($args['facility_is_deleted']));
        }

        if (isset($args['license_is_deleted'])) {
            $where .= $wpdb->prepare(" AND license_is_deleted = %d", intval($args['license_is_deleted']));
        }

        if (!empty($args['search'])) {
            $s = '%' . $wpdb->esc_like($args['search']) . '%';
            $search_conds = ["name LIKE %s", "national_id LIKE %s", "membership_number LIKE %s"];
            $search_params = [$s, $s, $s];

            if (!empty($args['search_licenses'])) {
                $search_conds[] = "license_number LIKE %s";
                $search_params[] = $s;
            }
            if (!empty($args['search_facilities'])) {
                $search_conds[] = "facility_name LIKE %s";
                $search_conds[] = "facility_number LIKE %s";
                $search_params[] = $s;
                $search_params[] = $s;
            }

            $where .= " AND (" . implode(" OR ", $search_conds) . ")";
            $params = array_merge($params, $search_params);
        }

        $allowed_orderby = ['id', 'name', 'national_id', 'membership_number', 'sort_order', 'registration_date', 'membership_expiration_date', 'license_expiration_date'];
        $orderby = 'sort_order ASC, name ASC';
        if (!empty($args['orderby']) && in_array($args['orderby'], $allowed_orderby)) {
            $order = (!empty($args['order']) && strtoupper($args['order']) === 'DESC') ? 'DESC' : 'ASC';
            $orderby = "`" . $args['orderby'] . "` " . $order;
        }

        $query = "SELECT * FROM $table_name WHERE $where ORDER BY $orderby";

        if ($limit != -1) {
            $query .= " LIMIT %d OFFSET %d";
            $params[] = $limit;
            $params[] = $offset;
        }

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, ...$params));
        }
        return $wpdb->get_results($query);
    }

    public static function get_member_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE id = %d", $id));
    }

    public static function get_member_by_national_id($national_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE national_id = %s", $national_id));
    }

    public static function get_member_by_membership_number($membership_number) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE membership_number = %s", $membership_number));
    }

    public static function get_member_by_license_number($license_number) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE license_number = %s", $license_number));
    }

    public static function get_member_by_facility_number($facility_number) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE facility_number = %s", $facility_number));
    }

    public static function get_member_by_email($email) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE email = %s", $email));
    }

    public static function get_member_by_username($username) {
        $user = get_user_by('login', $username);
        if (!$user) {
            return null;
        }
        return self::get_member_by_wp_user_id($user->ID);
    }

    public static function get_member_by_wp_user_id($wp_user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", intval($wp_user_id)));
    }

    public static function count_members($args = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';
        $user = wp_get_current_user();
        $has_full_access = current_user_can('manage_options') || current_user_can('sm_full_access');

        $is_deleted = isset($args['is_deleted']) ? intval($args['is_deleted']) : 0;

        if ($is_deleted === 0) {
            $where = "($table_name.is_deleted = 0 OR $table_name.is_deleted IS NULL)";
        } else {
            $where = "$table_name.is_deleted = 1";
        }

        $params = [];

        if (!empty($args['professional_grade'])) {
            $where .= " AND professional_grade = %s";
            $params[] = $args['professional_grade'];
        }

        if (!empty($args['specialization'])) {
            $where .= " AND specialization = %s";
            $params[] = $args['specialization'];
        }

        if (!$has_full_access) {
            $gov = get_user_meta($user->ID, 'sm_governorate', true);
            if ($gov) {
                $where .= " AND governorate = %s";
                $params[] = $gov;
            } else {
                return 0;
            }
        }

        if (!empty($args['search'])) {
            $s = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= " AND (name LIKE %s OR national_id LIKE %s OR membership_number LIKE %s)";
            $params[] = $s; $params[] = $s; $params[] = $s;
        }

        if (!empty($args['governorate'])) {
            $where .= " AND governorate = %s";
            $params[] = $args['governorate'];
        }

        if (isset($args['facility_is_deleted'])) {
            $where .= $wpdb->prepare(" AND facility_is_deleted = %d", intval($args['facility_is_deleted']));
        }

        if (isset($args['license_is_deleted'])) {
            $where .= $wpdb->prepare(" AND license_is_deleted = %d", intval($args['license_is_deleted']));
        }

        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}sm_members WHERE $where";
        if (!empty($params)) {
            return (int)$wpdb->get_var($wpdb->prepare($query, ...$params));
        }
        return (int)$wpdb->get_var($query);
    }

    public static function add_member($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';

        $national_id = sanitize_text_field($data['national_id'] ?? '');
        if (!preg_match('/^[0-9]{14}$/', $national_id)) {
            return new WP_Error('invalid_national_id', 'الرقم القومي يجب أن يتكون من 14 رقم بالضبط وبدون حروف.');
        }

        // Check if national_id already exists
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE national_id = %s", $national_id));
        if ($exists) {
            return new WP_Error('duplicate_national_id', 'الرقم القومي مسجل مسبقاً.');
        }

        $name = sanitize_text_field($data['name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');

        // Auto-create WordPress User for the Member
        $wp_user_id = null;

        if (!function_exists('wp_insert_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }

        // Check if WP user already exists by login
        if (username_exists($national_id)) {
            $existing_user = get_user_by('login', $national_id);
            $wp_user_id = $existing_user->ID;
        } else {
            $wp_user_id = wp_insert_user(array(
                'user_login' => $national_id,
                'user_email' => $email ?: $national_id . '@irseg.org',
                'display_name' => $name,
                'user_pass' => '', // Account needs activation/password set
                'role' => 'sm_member'
            ));
        }

        if (!is_wp_error($wp_user_id)) {
            if (!empty($data['governorate'])) {
                update_user_meta($wp_user_id, 'sm_governorate', sanitize_text_field($data['governorate']));
            }
        } else {
            return $wp_user_id; // Return WP_Error
        }

        $insert_data = array(
            'national_id' => $national_id,
            'member_code' => sanitize_text_field($data['member_code'] ?? ''),
            'name' => $name,
            'gender' => sanitize_text_field($data['gender'] ?? 'male'),
            'professional_grade' => sanitize_text_field($data['professional_grade'] ?? ''),
            'specialization' => sanitize_text_field($data['specialization'] ?? ''),
            'academic_degree' => sanitize_text_field($data['academic_degree'] ?? ''),
            'university' => sanitize_text_field($data['university'] ?? ''),
            'faculty' => sanitize_text_field($data['faculty'] ?? 'sports_science'),
            'department' => sanitize_text_field($data['department'] ?? ''),
            'graduation_date' => (!empty($data['graduation_date']) && $data['graduation_date'] !== '0000-00-00') ? sanitize_text_field($data['graduation_date']) : null,
            'residence_street' => sanitize_textarea_field($data['residence_street'] ?? ''),
            'residence_city' => sanitize_text_field($data['residence_city'] ?? ''),
            'residence_governorate' => sanitize_text_field($data['residence_governorate'] ?? ''),
            'governorate' => sanitize_text_field($data['governorate'] ?? ''),
            'membership_number' => sanitize_text_field($data['membership_number'] ?? ''),
            'membership_start_date' => (!empty($data['membership_start_date']) && $data['membership_start_date'] !== '0000-00-00') ? sanitize_text_field($data['membership_start_date']) : null,
            'membership_expiration_date' => (!empty($data['membership_expiration_date']) && $data['membership_expiration_date'] !== '0000-00-00') ? sanitize_text_field($data['membership_expiration_date']) : null,
            'membership_status' => sanitize_text_field($data['membership_status'] ?? ''),
            'license_number' => sanitize_text_field($data['license_number'] ?? ''),
            'license_issue_date' => (!empty($data['license_issue_date']) && $data['license_issue_date'] !== '0000-00-00') ? sanitize_text_field($data['license_issue_date']) : null,
            'license_expiration_date' => (!empty($data['license_expiration_date']) && $data['license_expiration_date'] !== '0000-00-00') ? sanitize_text_field($data['license_expiration_date']) : null,
            'facility_number' => sanitize_text_field($data['facility_number'] ?? ''),
            'facility_name' => sanitize_text_field($data['facility_name'] ?? ''),
            'facility_license_issue_date' => (!empty($data['facility_license_issue_date']) && $data['facility_license_issue_date'] !== '0000-00-00') ? sanitize_text_field($data['facility_license_issue_date']) : null,
            'facility_license_expiration_date' => (!empty($data['facility_license_expiration_date']) && $data['facility_license_expiration_date'] !== '0000-00-00') ? sanitize_text_field($data['facility_license_expiration_date']) : null,
            'facility_address' => sanitize_textarea_field($data['facility_address'] ?? ''),
            'sub_syndicate' => sanitize_text_field($data['sub_syndicate'] ?? ''),
            'facility_category' => sanitize_text_field($data['facility_category'] ?? 'C'),
            'last_paid_membership_year' => intval($data['last_paid_membership_year'] ?? 0),
            'last_paid_license_year' => intval($data['last_paid_license_year'] ?? 0),
            'email' => $email ?: $national_id . '@irseg.org',
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'alt_phone' => sanitize_text_field($data['alt_phone'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'province_of_birth' => sanitize_text_field($data['province_of_birth'] ?? ''),
            'wp_user_id' => $wp_user_id,
            'registration_date' => current_time('Y-m-d'),
            'sort_order' => self::get_next_sort_order()
        );

        $wpdb->insert($table_name, $insert_data);
        $id = $wpdb->insert_id;

        if ($id) {
            SM_Logger::log('إضافة عضو جديد', "تمت إضافة العضو: $name بنجاح (الرقم القومي: $national_id)");
            SM_Finance::invalidate_financial_caches($insert_data['governorate'] ?? null);
        }

        return $id;
    }

    public static function update_member($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';

        if (!function_exists('wp_update_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }

        $update_data = array();
        $fields = [
            'national_id', 'member_code', 'name', 'gender', 'professional_grade', 'specialization',
            'academic_degree', 'university', 'faculty', 'department', 'graduation_date',
            'residence_street', 'residence_city', 'residence_governorate',
            'governorate', 'membership_number', 'membership_start_date',
            'membership_expiration_date', 'membership_status', 'license_number',
            'license_issue_date', 'license_expiration_date', 'facility_number',
            'facility_name', 'facility_license_issue_date', 'facility_license_expiration_date',
            'facility_address', 'sub_syndicate', 'facility_category', 'last_paid_membership_year',
            'last_paid_license_year', 'email', 'phone', 'alt_phone', 'notes', 'province_of_birth'
        ];

        $date_fields = [
            'graduation_date', 'membership_start_date', 'membership_expiration_date',
            'license_issue_date', 'license_expiration_date',
            'facility_license_issue_date', 'facility_license_expiration_date'
        ];

        foreach ($fields as $f) {
            if (isset($data[$f])) {
                if (in_array($f, $date_fields)) {
                    $update_data[$f] = (!empty($data[$f]) && $data[$f] !== '0000-00-00') ? sanitize_text_field($data[$f]) : null;
                } elseif (in_array($f, ['facility_address', 'notes', 'residence_street'])) {
                    $update_data[$f] = sanitize_textarea_field($data[$f]);
                } elseif ($f === 'email') {
                    $update_data[$f] = sanitize_email($data[$f]);
                } else {
                    $update_data[$f] = sanitize_text_field($data[$f]);
                }
            }
        }

        if (isset($data['wp_user_id'])) $update_data['wp_user_id'] = intval($data['wp_user_id']);
        if (isset($data['registration_date'])) $update_data['registration_date'] = sanitize_text_field($data['registration_date']);
        if (isset($data['sort_order'])) $update_data['sort_order'] = intval($data['sort_order']);

        $res = $wpdb->update($table_name, $update_data, array('id' => $id));

        // Sync to WP User
        $member = self::get_member_by_id($id);

        if ($res !== false) {
            SM_Finance::invalidate_financial_caches($member->governorate ?? null);
        }
        if ($member && $member->wp_user_id) {
            $user_data = ['ID' => $member->wp_user_id];
            if (isset($data['name'])) $user_data['display_name'] = $data['name'];
            if (isset($data['email'])) $user_data['user_email'] = $data['email'];
            if (count($user_data) > 1) {
                wp_update_user($user_data);
            }
            if (isset($data['governorate'])) {
                update_user_meta($member->wp_user_id, 'sm_governorate', sanitize_text_field($data['governorate']));
            }
            if (isset($data['account_status'])) {
                update_user_meta($member->wp_user_id, 'sm_account_status', sanitize_text_field($data['account_status']));
            }
        }

        return $res;
    }

    public static function update_member_photo($id, $photo_url) {
        global $wpdb;
        return $wpdb->update($wpdb->prefix . 'sm_members', array('photo_url' => $photo_url), array('id' => $id));
    }

    public static function delete_member($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';

        $member = self::get_member_by_id($id);
        if ($member) {
            $user = wp_get_current_user();
            $role_names = [
                'administrator' => 'مدير النظام',
                'sm_general_officer' => 'مسؤول النقابة العامة',
                'sm_branch_officer' => 'مسؤول نقابة فرعي'
            ];
            $my_role = reset($user->roles);
            $role_label = $role_names[$my_role] ?? $my_role;

            SM_Logger::log('أرشفة عضو (حذف مؤقت)', "قام $role_label ({$user->display_name}) بنقل العضو {$member->name} إلى سلة المحذوفات.");

            // Soft delete
            $res = $wpdb->update($table_name, ['is_deleted' => 1], ['id' => $id]);
            SM_Finance::invalidate_financial_caches($member->governorate);
            return $res;
        }

        return false;
    }

    public static function permanent_delete_member($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';

        $member = self::get_member_by_id($id);
        if ($member) {
            $user = wp_get_current_user();
            SM_Logger::log('حذف عضو نهائي', "قام مدير النظام ({$user->display_name}) بحذف سجل العضو {$member->name} نهائياً من النظام.");

            if ($member->wp_user_id) {
                if (!function_exists('wp_delete_user')) {
                    require_once(ABSPATH . 'wp-admin/includes/user.php');
                }
                wp_delete_user($member->wp_user_id);
            }
            $res = $wpdb->delete($table_name, array('id' => $id));
            SM_Finance::invalidate_financial_caches($member->governorate ?? null);
            return $res;
        }

        return false;
    }

    public static function restore_member($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';

        $member = self::get_member_by_id($id);
        if ($member) {
            $user = wp_get_current_user();
            SM_Logger::log('استعادة عضو', "تمت استعادة العضو {$member->name} من سلة المحذوفات بواسطة {$user->display_name}.");
            $res = $wpdb->update($table_name, ['is_deleted' => 0], ['id' => $id]);
            SM_Finance::invalidate_financial_caches($member->governorate);
            return $res;
        }

        return false;
    }

    public static function member_exists($national_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sm_members WHERE national_id = %s",
            $national_id
        ));
    }

    public static function get_next_sort_order() {
        global $wpdb;
        $max = $wpdb->get_var("SELECT MAX(sort_order) FROM {$wpdb->prefix}sm_members");
        return ($max ? intval($max) : 0) + 1;
    }

    public static function get_member_suggestions($query, $limit = 5) {
        global $wpdb;
        $s = '%' . $wpdb->esc_like($query) . '%';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT name, national_id FROM {$wpdb->prefix}sm_members WHERE name LIKE %s OR national_id LIKE %s LIMIT %d",
            $s, $s, $limit
        ));
    }

    public static function get_member_wp_user_ids($governorate = null) {
        global $wpdb;
        $query = "SELECT wp_user_id FROM {$wpdb->prefix}sm_members WHERE wp_user_id IS NOT NULL";
        if ($governorate) {
            return $wpdb->get_col($wpdb->prepare($query . " AND governorate = %s", $governorate));
        }
        return $wpdb->get_col($query);
    }

    public static function get_member_ids_by_governorate($governorate) {
        global $wpdb;
        return $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sm_members WHERE governorate = %s",
            $governorate
        ));
    }

    public static function delete_members_by_governorate($governorate) {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sm_members WHERE governorate = %s",
            $governorate
        ));
    }

    public static function add_membership_request($data) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_membership_requests", array(
            'national_id' => sanitize_text_field($data['national_id']),
            'name' => sanitize_text_field($data['name']),
            'gender' => sanitize_text_field($data['gender'] ?? 'male'),
            'professional_grade' => sanitize_text_field($data['professional_grade'] ?? ''),
            'specialization' => sanitize_text_field($data['specialization'] ?? ''),
            'academic_degree' => sanitize_text_field($data['academic_degree'] ?? ''),
            'university' => sanitize_text_field($data['university'] ?? ''),
            'faculty' => sanitize_text_field($data['faculty'] ?? ''),
            'department' => sanitize_text_field($data['department'] ?? ''),
            'graduation_date' => sanitize_text_field($data['graduation_date'] ?? null),
            'residence_street' => sanitize_textarea_field($data['residence_street'] ?? ''),
            'residence_city' => sanitize_text_field($data['residence_city'] ?? ''),
            'residence_governorate' => sanitize_text_field($data['residence_governorate'] ?? ''),
            'governorate' => sanitize_text_field($data['governorate'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'current_stage' => 1,
            'status' => 'Pending Payment',
            'created_at' => current_time('mysql')
        ));
    }

    public static function update_membership_request($id, $data) {
        global $wpdb;
        $update_data = array();
        $fields = [
            'name', 'gender', 'professional_grade', 'specialization', 'academic_degree',
            'university', 'faculty', 'department', 'graduation_date',
            'residence_street', 'residence_city', 'residence_governorate', 'governorate',
            'phone', 'email', 'notes', 'payment_method', 'payment_reference', 'payment_screenshot_url',
            'doc_qualification_url', 'doc_id_url', 'doc_military_url', 'doc_criminal_url', 'doc_photo_url',
            'current_stage', 'status', 'rejection_reason', 'processed_by'
        ];

        foreach ($fields as $f) {
            if (isset($data[$f])) {
                if (in_array($f, ['notes', 'residence_street', 'rejection_reason'])) {
                    $update_data[$f] = sanitize_textarea_field($data[$f]);
                } elseif (strpos($f, '_url') !== false) {
                    $update_data[$f] = esc_url_raw($data[$f]);
                } elseif ($f === 'email') {
                    $update_data[$f] = sanitize_email($data[$f]);
                } elseif ($f === 'current_stage') {
                    $update_data[$f] = intval($data[$f]);
                } else {
                    $update_data[$f] = sanitize_text_field($data[$f]);
                }
            }
        }

        if (is_numeric($id) && strlen((string)$id) < 10) {
            return $wpdb->update("{$wpdb->prefix}sm_membership_requests", $update_data, array('id' => intval($id)));
        } else {
            // Assume $id is national_id
            return $wpdb->update("{$wpdb->prefix}sm_membership_requests", $update_data, array('national_id' => sanitize_text_field($id)));
        }
    }

    public static function get_membership_request($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_membership_requests WHERE id = %d", $id));
    }

    public static function get_membership_request_by_national_id($national_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_membership_requests WHERE national_id = %s", $national_id));
    }

    public static function get_membership_requests($args = []) {
        global $wpdb;
        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where = "1=1";
        $params = [];

        if (!$has_full_access && $my_gov) {
            $where .= " AND governorate = %s";
            $params[] = $my_gov;
        }

        if (!empty($args['status'])) {
            $where .= " AND status = %s";
            $params[] = $args['status'];
        } elseif (!empty($args['exclude_final'])) {
            $where .= " AND status NOT IN ('approved', 'rejected')";
        }

        if (!empty($args['branch'])) {
            $where .= " AND governorate = %s";
            $params[] = $args['branch'];
        }

        if (!empty($args['search'])) {
            $s = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= " AND (name LIKE %s OR national_id LIKE %s)";
            $params[] = $s; $params[] = $s;
        }

        $query = "SELECT * FROM {$wpdb->prefix}sm_membership_requests WHERE $where ORDER BY created_at DESC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        return $wpdb->get_results($query);
    }

    public static function add_update_request($member_id, $data) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_update_requests", array(
            'member_id' => $member_id,
            'requested_data' => json_encode($data),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_update_requests($status = 'pending') {
        global $wpdb;
        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where = $wpdb->prepare("r.status = %s", $status);
        if (!$has_full_access && $my_gov) {
            $where .= $wpdb->prepare(" AND m.governorate = %s", $my_gov);
        }

        return $wpdb->get_results("
            SELECT r.*, m.name as member_name, m.national_id
            FROM {$wpdb->prefix}sm_update_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE $where
            ORDER BY r.created_at DESC
        ");
    }

    public static function count_pending_update_requests() {
        global $wpdb;
        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where = "r.status = 'pending'";
        if (!$has_full_access && $my_gov) {
            $where .= $wpdb->prepare(" AND m.governorate = %s", $my_gov);
        }

        return (int)$wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}sm_update_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE $where
        ");
    }

    public static function process_update_request($request_id, $status) {
        global $wpdb;
        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_update_requests WHERE id = %d", $request_id));
        if (!$request) return false;

        if ($status === 'approved') {
            $data = json_decode($request->requested_data, true);
            self::update_member($request->member_id, $data);
            SM_Logger::log('اعتماد طلب تحديث بيانات', "تم تحديث بيانات العضو ID: {$request->member_id}");
        }

        return $wpdb->update(
            "{$wpdb->prefix}sm_update_requests",
            array(
                'status' => $status,
                'processed_at' => current_time('mysql'),
                'processed_by' => get_current_user_id()
            ),
            array('id' => $request_id)
        );
    }

    public static function soft_delete_facility($id) {
        global $wpdb;
        $res = $wpdb->update(
            $wpdb->prefix . 'sm_members',
            ['facility_is_deleted' => 1, 'facility_deleted_at' => current_time('mysql')],
            ['id' => $id]
        );
        $m = self::get_member_by_id($id);
        SM_Finance::invalidate_financial_caches($m ? $m->governorate : null);
        return $res;
    }

    public static function restore_facility($id) {
        global $wpdb;
        $res = $wpdb->update(
            $wpdb->prefix . 'sm_members',
            ['facility_is_deleted' => 0, 'facility_deleted_at' => null],
            ['id' => $id]
        );
        $m = self::get_member_by_id($id);
        SM_Finance::invalidate_financial_caches($m ? $m->governorate : null);
        return $res;
    }

    public static function permanent_delete_facility($id) {
        global $wpdb;
        $res = $wpdb->update(
            $wpdb->prefix . 'sm_members',
            [
                'facility_number' => null,
                'facility_name' => null,
                'facility_license_issue_date' => null,
                'facility_license_expiration_date' => null,
                'facility_address' => null,
                'facility_category' => 'C',
                'facility_is_deleted' => 0,
                'facility_deleted_at' => null
            ],
            ['id' => $id]
        );
        $m = self::get_member_by_id($id);
        SM_Finance::invalidate_financial_caches($m ? $m->governorate : null);
        return $res;
    }

    public static function soft_delete_license($id) {
        global $wpdb;
        $res = $wpdb->update(
            $wpdb->prefix . 'sm_members',
            ['license_is_deleted' => 1, 'license_deleted_at' => current_time('mysql')],
            ['id' => $id]
        );
        $m = self::get_member_by_id($id);
        SM_Finance::invalidate_financial_caches($m ? $m->governorate : null);
        return $res;
    }

    public static function restore_license($id) {
        global $wpdb;
        $res = $wpdb->update(
            $wpdb->prefix . 'sm_members',
            ['license_is_deleted' => 0, 'license_deleted_at' => null],
            ['id' => $id]
        );
        $m = self::get_member_by_id($id);
        SM_Finance::invalidate_financial_caches($m ? $m->governorate : null);
        return $res;
    }

    public static function permanent_delete_license($id) {
        global $wpdb;
        $res = $wpdb->update(
            $wpdb->prefix . 'sm_members',
            [
                'license_number' => null,
                'license_issue_date' => null,
                'license_expiration_date' => null,
                'license_is_deleted' => 0,
                'license_deleted_at' => null
            ],
            ['id' => $id]
        );
        $m = self::get_member_by_id($id);
        SM_Finance::invalidate_financial_caches($m ? $m->governorate : null);
        return $res;
    }

    public static function cleanup_deleted_licenses_and_facilities() {
        global $wpdb;
        $table = $wpdb->prefix . 'sm_members';
        $three_months_ago = date('Y-m-d H:i:s', strtotime('-3 months'));

        // Permanent delete facilities
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET
                facility_number = NULL,
                facility_name = NULL,
                facility_license_issue_date = NULL,
                facility_license_expiration_date = NULL,
                facility_address = NULL,
                facility_category = 'C',
                facility_is_deleted = 0,
                facility_deleted_at = NULL
            WHERE facility_is_deleted = 1 AND facility_deleted_at <= %s",
            $three_months_ago
        ));

        // Permanent delete licenses
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET
                license_number = NULL,
                license_issue_date = NULL,
                license_expiration_date = NULL,
                license_is_deleted = 0,
                license_deleted_at = NULL
            WHERE license_is_deleted = 1 AND license_deleted_at <= %s",
            $three_months_ago
        ));
    }
}
