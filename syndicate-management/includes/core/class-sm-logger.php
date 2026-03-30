<?php

class SM_Logger {
    public static function log($action, $details = '') {
        global $wpdb;
        $user_id = get_current_user_id();

        $wpdb->insert(
            "{$wpdb->prefix}sm_logs",
            array(
                'user_id' => $user_id,
                'action' => sanitize_text_field($action),
                'details' => sanitize_textarea_field($details),
                'created_at' => current_time('mysql')
            )
        );

        // Limit to 200 entries
        $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sm_logs");
        if ($count > 200) {
            $limit = $count - 200;
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sm_logs ORDER BY created_at ASC LIMIT %d", $limit));
        }
    }

    public static function get_logs($limit = 100, $offset = 0, $search = '') {
        global $wpdb;
        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where = "1=1";
        $params = array();

        if (!$has_full_access && $my_gov) {
            $where = "(
                EXISTS (SELECT 1 FROM {$wpdb->prefix}usermeta um WHERE um.user_id = l.user_id AND um.meta_key = 'sm_governorate' AND um.meta_value = %s)
                OR EXISTS (SELECT 1 FROM {$wpdb->prefix}sm_members m WHERE m.wp_user_id = l.user_id AND m.governorate = %s)
            )";
            $params[] = $my_gov;
            $params[] = $my_gov;
        }

        if (!empty($search)) {
            $s = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (l.action LIKE %s OR l.details LIKE %s OR u.display_name LIKE %s OR l.created_at LIKE %s)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $query = "SELECT l.*, u.display_name FROM {$wpdb->prefix}sm_logs l LEFT JOIN {$wpdb->base_prefix}users u ON l.user_id = u.ID WHERE $where ORDER BY l.created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($query, ...$params));
    }

    public static function get_total_logs($search = '') {
        global $wpdb;
        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where = "1=1";
        $params = array();

        if (!$has_full_access && $my_gov) {
            $where = "(
                EXISTS (SELECT 1 FROM {$wpdb->prefix}usermeta um WHERE um.user_id = l.user_id AND um.meta_key = 'sm_governorate' AND um.meta_value = %s)
                OR EXISTS (SELECT 1 FROM {$wpdb->prefix}sm_members m WHERE m.wp_user_id = l.user_id AND m.governorate = %s)
            )";
            $params[] = $my_gov;
            $params[] = $my_gov;
        }

        if (!empty($search)) {
            $s = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (l.action LIKE %s OR l.details LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->base_prefix}users u WHERE u.ID = l.user_id AND u.display_name LIKE %s) OR l.created_at LIKE %s)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}sm_logs l WHERE $where";

        if (!empty($params)) {
            return (int)$wpdb->get_var($wpdb->prepare($query, ...$params));
        }
        return (int)$wpdb->get_var($query);
    }
}
