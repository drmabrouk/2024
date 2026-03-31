<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_Certificates {
    public static function add_certificate($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sm_certificates';

        $res = $wpdb->insert($table, [
            'member_id' => intval($data['member_id'] ?? 0),
            'member_name' => sanitize_text_field($data['member_name'] ?? ''),
            'member_national_id' => sanitize_text_field($data['member_national_id'] ?? ''),
            'governorate' => sanitize_text_field($data['governorate'] ?? ''),
            'serial_number' => sanitize_text_field($data['serial_number']),
            'barcode_data' => sanitize_text_field($data['barcode_data'] ?? ''),
            'cert_type' => sanitize_text_field($data['cert_type']),
            'category' => sanitize_text_field($data['category']),
            'specialization' => sanitize_text_field($data['specialization']),
            'title' => sanitize_text_field($data['title']),
            'issue_date' => sanitize_text_field($data['issue_date'] ?: current_time('Y-m-d')),
            'expiry_date' => sanitize_text_field($data['expiry_date'] ?? ''),
            'grade' => sanitize_text_field($data['grade'] ?? ''),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ]);

        return $res ? $wpdb->insert_id : false;
    }

    public static function get_certificates($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'sm_certificates';
        $members_table = $wpdb->prefix . 'sm_members';

        $where = "1=1";
        $params = [];

        if (!empty($args['search'])) {
            $s = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= " AND (c.title LIKE %s OR c.serial_number LIKE %s OR m.name LIKE %s OR m.national_id LIKE %s)";
            $params = array_merge($params, [$s, $s, $s, $s]);
        }

        if (!empty($args['member_id'])) {
            $where .= " AND c.member_id = %d";
            $params[] = intval($args['member_id']);
        }

        if (!empty($args['cert_type'])) {
            $where .= " AND c.cert_type = %s";
            $params[] = $args['cert_type'];
        }

        $query = "
            SELECT c.*, m.name as member_name, m.national_id as member_nid, m.governorate as member_gov
            FROM $table c
            LEFT JOIN $members_table m ON c.member_id = m.id
            WHERE $where
            ORDER BY c.created_at DESC
        ";

        if (isset($args['limit'])) {
            $query .= " LIMIT %d";
            $params[] = intval($args['limit']);
        }

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, ...$params));
        }
        return $wpdb->get_results($query);
    }

    public static function get_certificate_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_certificates WHERE id = %d", intval($id)));
    }

    public static function get_certificate_by_serial($serial) {
        global $wpdb;
        $table = $wpdb->prefix . 'sm_certificates';
        $members_table = $wpdb->prefix . 'sm_members';

        return $wpdb->get_row($wpdb->prepare("
            SELECT c.*,
                   IFNULL(m.name, c.member_name) as member_name,
                   IFNULL(m.national_id, c.member_national_id) as member_nid,
                   IFNULL(m.governorate, c.governorate) as governorate
            FROM $table c
            LEFT JOIN $members_table m ON c.member_id = m.id
            WHERE c.serial_number = %s
        ", $serial));
    }

    public static function delete_certificate($id) {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}sm_certificates", ['id' => intval($id)]);
    }
}
