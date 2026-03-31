<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_Finance {
    public static function delete_payments_by_member_ids($member_ids) {
        global $wpdb;
        $ids_str = implode(',', array_map('intval', $member_ids));
        return $wpdb->query("DELETE FROM {$wpdb->prefix}sm_payments WHERE member_id IN ($ids_str)");
    }

    public static function get_payment_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_payments WHERE id = %d", intval($id)));
    }

    public static function delete_payment($id) {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}sm_payments", ['id' => intval($id)]);
    }

    public static function get_payments($args = []) {
        global $wpdb;
        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where = "1=1";
        $params = [];

        if (!$has_full_access && $my_gov) {
            $where .= " AND m.governorate = %s";
            $params[] = $my_gov;
        }

        if (!empty($args['day'])) { $where .= " AND DAY(p.payment_date) = %d"; $params[] = intval($args['day']); }
        if (!empty($args['month'])) { $where .= " AND MONTH(p.payment_date) = %d"; $params[] = intval($args['month']); }
        if (!empty($args['year'])) { $where .= " AND YEAR(p.payment_date) = %d"; $params[] = intval($args['year']); }

        if (!empty($args['search'])) {
            $where .= " AND (m.name LIKE %s OR m.national_id LIKE %s)";
            $s = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $s; $params[] = $s;
        }

        if (isset($args['include']) && !empty($args['include'])) {
            $include_ids = array_map('intval', (array)$args['include']);
            if (!empty($include_ids)) {
                $where .= " AND p.id IN (" . implode(',', $include_ids) . ")";
            }
        }

        $limit = isset($args['limit']) ? intval($args['limit']) : 500;
        $query = "SELECT p.*, m.name as member_name, m.governorate as member_gov, u.display_name as staff_name
                  FROM {$wpdb->prefix}sm_payments p
                  JOIN {$wpdb->prefix}sm_members m ON p.member_id = m.id
                  LEFT JOIN {$wpdb->base_prefix}users u ON p.created_by = u.ID
                  WHERE $where ORDER BY p.created_at DESC";

        if ($limit != -1) {
            $query .= $wpdb->prepare(" LIMIT %d", $limit);
        }

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, ...$params));
        }
        return $wpdb->get_results($query);
    }

    public static function get_statistics($filters = array()) {
        global $wpdb;

        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
        $target_gov = $filters['governorate'] ?? null;

        // Caching Logic
        $cache_key = 'sm_stats_' . ($target_gov ?: ($has_full_access ? 'global' : $my_gov));
        $cached = get_transient($cache_key);
        if ($cached !== false && empty($filters['no_cache'])) return $cached;

        $stats = array();
        $params = [];

        $where_member = "is_deleted = 0";
        if ($target_gov) {
            $where_member .= " AND governorate = %s";
            $params[] = $target_gov;
        } elseif (!$has_full_access) {
            if ($my_gov) {
                $where_member .= " AND governorate = %s";
                $params[] = $my_gov;
            } else {
                $where_member = "1=0";
            }
        }

        $query_members = "SELECT COUNT(*) FROM {$wpdb->prefix}sm_members WHERE $where_member";
        if (!empty($params)) {
            $stats['total_members'] = $wpdb->get_var($wpdb->prepare($query_members, ...$params));
        } else {
            $stats['total_members'] = $wpdb->get_var($query_members);
        }
        $stats['total_officers'] = count(SM_DB_Members::get_staff(['number' => -1]));

        // Total Board Members
        $stats['total_board'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}users u
             JOIN {$wpdb->prefix}usermeta um1 ON u.ID = um1.user_id AND um1.meta_key = '{$wpdb->prefix}capabilities'
             JOIN {$wpdb->prefix}usermeta um2 ON u.ID = um2.user_id AND um2.meta_key = 'sm_rank'
             WHERE (um1.meta_value LIKE %s OR um1.meta_value LIKE %s) AND um2.meta_value != ''",
            '%"sm_branch_officer"%',
            '%"sm_general_officer"%'
        ));

        // Total Revenue (Exclude payments from deleted members)
        $join_member_rev = "JOIN {$wpdb->prefix}sm_members m ON p.member_id = m.id";
        $where_rev = "m.is_deleted = 0";
        $rev_params = [];
        if (!$has_full_access) {
            if ($my_gov) {
                $where_rev .= " AND m.governorate = %s";
                $rev_params[] = $my_gov;
            } else {
                $where_rev = "1=0";
            }
        }
        $query_rev = "SELECT SUM(amount) FROM {$wpdb->prefix}sm_payments p $join_member_rev WHERE $where_rev";
        if (!empty($rev_params)) {
            $stats['total_revenue'] = $wpdb->get_var($wpdb->prepare($query_rev, ...$rev_params)) ?: 0;
        } else {
            $stats['total_revenue'] = $wpdb->get_var($query_rev) ?: 0;
        }

        // Financial Trends (Last 30 Days, Exclude deleted members)
        $join_member = "JOIN {$wpdb->prefix}sm_members m ON p.member_id = m.id";
        $where_finance = "m.is_deleted = 0 AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $trend_params = [];
        if (!$has_full_access) {
            if ($my_gov) {
                $where_finance .= " AND m.governorate = %s";
                $trend_params[] = $my_gov;
            } else {
                $where_finance .= " AND 1=0";
            }
        }

        $query_trends = "
            SELECT DATE(payment_date) as date, SUM(amount) as total
            FROM {$wpdb->prefix}sm_payments p
            $join_member
            WHERE $where_finance
            GROUP BY DATE(payment_date)
            ORDER BY date ASC
        ";
        if (!empty($trend_params)) {
            $stats['financial_trends'] = $wpdb->get_results($wpdb->prepare($query_trends, ...$trend_params));
        } else {
            $stats['financial_trends'] = $wpdb->get_results($query_trends);
        }

        // Specialization Distribution
        $query_specs = "
            SELECT specialization, COUNT(*) as count
            FROM {$wpdb->prefix}sm_members
            WHERE specialization != '' AND $where_member
            GROUP BY specialization
        ";
        if (!empty($params)) {
            $stats['specializations'] = $wpdb->get_results($wpdb->prepare($query_specs, ...$params));
        } else {
            $stats['specializations'] = $wpdb->get_results($query_specs);
        }

        // Advanced Stats
        $query_service_reqs = "
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_service_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE $where_member
        ";
        $query_executed_reqs = "
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_service_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE r.status = 'approved' AND $where_member
        ";
        $query_update_reqs = "
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_update_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE $where_member
        ";
        $query_membership_reqs = "
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_membership_requests
            WHERE $where_member
        ";

        if (!empty($params)) {
            $stats['total_service_requests'] = $wpdb->get_var($wpdb->prepare($query_service_reqs, ...$params)) ?: 0;
            $stats['total_executed_requests'] = $wpdb->get_var($wpdb->prepare($query_executed_reqs, ...$params)) ?: 0;
            $stats['total_update_requests'] = $wpdb->get_var($wpdb->prepare($query_update_reqs, ...$params)) ?: 0;
            $stats['total_membership_requests'] = $wpdb->get_var($wpdb->prepare($query_membership_reqs, ...$params)) ?: 0;
        } else {
            $stats['total_service_requests'] = $wpdb->get_var($query_service_reqs) ?: 0;
            $stats['total_executed_requests'] = $wpdb->get_var($query_executed_reqs) ?: 0;
            $stats['total_update_requests'] = $wpdb->get_var($query_update_reqs) ?: 0;
            $stats['total_membership_requests'] = $wpdb->get_var($query_membership_reqs) ?: 0;
        }

        $stats['total_requests'] = intval($stats['total_service_requests']) + intval($stats['total_update_requests']) + intval($stats['total_membership_requests']);

        $query_practice = "
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_members
            WHERE license_number != '' AND $where_member
        ";
        $query_facility = "
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_members
            WHERE facility_number != '' AND $where_member
        ";

        if (!empty($params)) {
            $stats['total_practice_licenses'] = $wpdb->get_var($wpdb->prepare($query_practice, ...$params)) ?: 0;
            $stats['total_facility_licenses'] = $wpdb->get_var($wpdb->prepare($query_facility, ...$params)) ?: 0;
        } else {
            $stats['total_practice_licenses'] = $wpdb->get_var($query_practice) ?: 0;
            $stats['total_facility_licenses'] = $wpdb->get_var($query_facility) ?: 0;
        }

        // Work permits (assumed same as practice licenses in this context)
        $stats['total_work_permits'] = $stats['total_practice_licenses'];

        set_transient($cache_key, $stats, HOUR_IN_SECONDS);
        return $stats;
    }
}
