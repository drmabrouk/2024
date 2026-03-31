<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Certificate_Manager {
    public static function ajax_add_certificate() {
        try {
            if (!current_user_can('sm_manage_system') && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_admin_action', 'nonce');

            $data = $_POST;
            $data['serial_number'] = self::generate_unique_serial();
            $data['barcode_data'] = self::generate_barcode($data['serial_number']);

            $cert_id = SM_DB::add_certificate($data);
            if ($cert_id) {
                SM_Logger::log('إضافة شهادة', "تم إصدار شهادة رقم: {$data['serial_number']} للعضو ID: {$data['member_id']}");
                wp_send_json_success(['id' => $cert_id, 'serial' => $data['serial_number']]);
            } else {
                wp_send_json_error(['message' => 'Failed to save certificate']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_import_certificates_json() {
        try {
            if (!current_user_can('sm_manage_system') && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_admin_action', 'nonce');

            $rows = json_decode(stripslashes($_POST['certs_data']), true);
            if (empty($rows)) wp_send_json_error(['message' => 'No data provided']);

            $success = 0; $errors = 0;
            foreach ($rows as $row) {
                $nid = sanitize_text_field($row['B'] ?? ''); // Assume B is National ID
                $member = SM_DB::get_member_by_national_id($nid);

                $serial = self::generate_unique_serial();
                $cert_data = [
                    'member_id' => $member ? $member->id : 0,
                    'member_name' => sanitize_text_field($row['C'] ?? ''), // C is Name if member not found
                    'member_national_id' => $nid,
                    'governorate' => sanitize_text_field($row['H'] ?? ''), // H is Governorate
                    'serial_number' => $serial,
                    'barcode_data' => self::generate_barcode($serial),
                    'cert_type' => sanitize_text_field($row['D'] ?? 'دورة تدريبية'), // D is Type
                    'category' => sanitize_text_field($row['E'] ?? 'عام'), // E is Category
                    'specialization' => sanitize_text_field($row['F'] ?? 'تخصص عام'), // F is Specialty
                    'title' => sanitize_text_field($row['A'] ?? 'شهادة إتمام'), // A is Title
                    'issue_date' => sanitize_text_field($row['G'] ?? current_time('Y-m-d')), // G is Issue Date
                    'expiry_date' => sanitize_text_field($row['I'] ?? ''), // I is Expiry Date
                    'grade' => sanitize_text_field($row['J'] ?? '') // J is Grade
                ];

                if (SM_DB::add_certificate($cert_data)) {
                    $success++;
                } else {
                    $errors++;
                }
            }

            wp_send_json_success(['total' => count($rows), 'success' => $success, 'error' => $errors]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_delete_certificate() {
        try {
            if (!current_user_can('sm_manage_system') && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_admin_action', 'nonce');

            $id = intval($_POST['id']);
            if (SM_DB::delete_certificate($id)) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Delete failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function generate_unique_serial() {
        global $wpdb;
        $prefix = 'CERT-' . date('Y') . '-';
        $is_unique = false;
        $serial = '';

        while (!$is_unique) {
            $random = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $serial = $prefix . $random;
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_certificates WHERE serial_number = %s", $serial));
            if (!$exists) $is_unique = true;
        }
        return $serial;
    }

    public static function generate_barcode($data) {
        // Placeholder for barcode logic. In a real environment, we'd use a library.
        // For now, we return a structured data string that can be rendered via a barcode font or JS.
        return $data;
    }

    public static function ajax_print_certificate() {
        if (!current_user_can('sm_print_reports')) wp_die('Unauthorized');
        $id = intval($_GET['id']);
        $cert = SM_DB::get_certificate_by_id($id);
        if (!$cert) wp_die('Certificate not found');

        $member = SM_DB::get_member_by_id($cert->member_id);
        include SM_PLUGIN_DIR . 'templates/print-certificate.php';
        exit;
    }
}
