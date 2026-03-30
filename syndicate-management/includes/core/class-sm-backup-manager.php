<?php
if (!defined('ABSPATH')) exit;

class SM_Backup_Manager {

    private static $backup_dir;

    public static function init() {
        $upload_dir = wp_upload_dir();
        self::$backup_dir = $upload_dir['basedir'] . '/sm-backups';
        if (!file_exists(self::$backup_dir)) {
            wp_mkdir_p(self::$backup_dir);
            file_put_contents(self::$backup_dir . '/index.php', '<?php // Silence is golden');
            file_put_contents(self::$backup_dir . '/.htaccess', 'deny from all');
        }
    }

    public static function get_plugin_tables() {
        global $wpdb;
        return [
            'members' => $wpdb->prefix . 'sm_members',
            'messages' => $wpdb->prefix . 'sm_messages',
            'logs' => $wpdb->prefix . 'sm_logs',
            'surveys' => $wpdb->prefix . 'sm_surveys',
            'test_questions' => $wpdb->prefix . 'sm_test_questions',
            'survey_responses' => $wpdb->prefix . 'sm_survey_responses',
            'test_assignments' => $wpdb->prefix . 'sm_test_assignments',
            'payments' => $wpdb->prefix . 'sm_payments',
            'update_requests' => $wpdb->prefix . 'sm_update_requests',
            'services' => $wpdb->prefix . 'sm_services',
            'service_requests' => $wpdb->prefix . 'sm_service_requests',
            'membership_requests' => $wpdb->prefix . 'sm_membership_requests',
            'notification_templates' => $wpdb->prefix . 'sm_notification_templates',
            'notification_logs' => $wpdb->prefix . 'sm_notification_logs',
            'documents' => $wpdb->prefix . 'sm_documents',
            'document_logs' => $wpdb->prefix . 'sm_document_logs',
            'pub_templates' => $wpdb->prefix . 'sm_pub_templates',
            'pub_documents' => $wpdb->prefix . 'sm_pub_documents',
            'tickets' => $wpdb->prefix . 'sm_tickets',
            'ticket_thread' => $wpdb->prefix . 'sm_ticket_thread',
            'alerts' => $wpdb->prefix . 'sm_alerts',
            'alert_views' => $wpdb->prefix . 'sm_alert_views',
            'professional_requests' => $wpdb->prefix . 'sm_professional_requests',
            'branches' => $wpdb->prefix . 'sm_branches_data'
        ];
    }

    public static function generate_backup($modules = 'all') {
        global $wpdb;
        self::init();

        $data = [
            'version' => SM_VERSION,
            'timestamp' => current_time('mysql'),
            'tables' => [],
            'settings' => [],
            'media' => []
        ];

        $tables = self::get_plugin_tables();
        if ($modules !== 'all') {
            $tables = array_intersect_key($tables, array_flip((array)$modules));
        }

        foreach ($tables as $key => $table_name) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
                $data['tables'][$key] = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
            }
        }

        // Include relevant settings
        $settings_keys = [
            'sm_appearance', 'sm_labels', 'sm_notification_settings', 'sm_syndicate_info',
            'sm_retention_settings', 'sm_professional_grades', 'sm_specializations',
            'sm_universities', 'sm_faculties', 'sm_departments', 'sm_finance_settings',
            'sm_verify_title', 'sm_verify_desc', 'sm_verify_show_membership',
            'sm_verify_show_practice', 'sm_verify_show_facility', 'sm_verify_help',
            'sm_verify_accent_color', 'sm_verify_success_msg', 'sm_support_email', 'sm_noreply_email',
            'sm_db_version', 'sm_plugin_version', 'sm_backup_frequency'
        ];

        foreach ($settings_keys as $s_key) {
            $data['settings'][$s_key] = get_option($s_key);
        }

        // Include Media if requested
        if ($modules === 'all' || in_array('media', (array)$modules)) {
            $data['media'] = self::collect_media_files();
        }

        $json = json_encode($data);
        $signature = hash_hmac('sha256', $json, wp_salt('auth'));

        $final_payload = [
            'data' => base64_encode($json),
            'signature' => $signature
        ];

        SM_Logger::log('نسخ احتياطي', "تم إنشاء نسخة احتياطية (النطاق: " . (is_string($modules) ? $modules : implode(',', $modules)) . ")");
        return json_encode($final_payload);
    }

    public static function restore_backup($json_payload, $selective_tables = 'all') {
        global $wpdb;
        $payload = json_decode($json_payload, true);

        if (!$payload || !isset($payload['data']) || !isset($payload['signature'])) {
            return new WP_Error('invalid_format', 'تنسيق ملف النسخة الاحتياطية غير صالح.');
        }

        $json = base64_decode($payload['data']);
        $expected_sig = hash_hmac('sha256', $json, wp_salt('auth'));

        if (!hash_equals($expected_sig, $payload['signature'])) {
            return new WP_Error('integrity_fail', 'فشل التحقق من سلامة البيانات. ربما تم التلاعب بالملف.');
        }

        $data = json_decode($json, true);
        if (!$data) return new WP_Error('json_fail', 'فشل في قراءة بيانات النسخة الاحتياطية.');

        // Start Restore
        $wpdb->query("SET foreign_key_checks = 0");

        if (!empty($data['tables'])) {
            $all_tables = self::get_plugin_tables();
            foreach ($data['tables'] as $key => $rows) {
                if ($selective_tables !== 'all' && !in_array($key, (array)$selective_tables)) continue;

                if (isset($all_tables[$key])) {
                    $table_name = $all_tables[$key];
                    $wpdb->query("TRUNCATE TABLE $table_name");
                    foreach ($rows as $row) {
                        $wpdb->insert($table_name, $row);
                    }
                }
            }
        }

        // Only restore settings if full restore
        if (!empty($data['settings']) && ($selective_tables === 'all')) {
            foreach ($data['settings'] as $s_key => $s_val) {
                update_option($s_key, $s_val);
            }
        }

        if (!empty($data['media']) && ($selective_tables === 'all' || in_array('media', (array)$selective_tables))) {
            self::restore_media_files($data['media']);
        }

        $wpdb->query("SET foreign_key_checks = 1");

        SM_Logger::log('استعادة نظام', "تمت استعادة نسخة احتياطية بتاريخ: " . $data['timestamp'] . " (النطاق: " . (is_string($selective_tables) ? $selective_tables : implode(',', $selective_tables)) . ")");
        return true;
    }

    public static function schedule_automated_backup() {
        $freq = get_option('sm_backup_frequency', 'weekly');
        if (!wp_next_scheduled('sm_scheduled_backup')) {
            wp_schedule_event(time(), $freq, 'sm_scheduled_backup');
        }
    }

    private static function collect_media_files() {
        global $wpdb;
        $doc_urls = $wpdb->get_col("SELECT file_url FROM {$wpdb->prefix}sm_documents WHERE file_url != ''");
        $member_photos = $wpdb->get_col("SELECT photo_url FROM {$wpdb->prefix}sm_members WHERE photo_url != ''");

        $urls = array_unique(array_merge($doc_urls, $member_photos));
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $base_dir = $upload_dir['basedir'];

        $files = [];
        foreach ($urls as $url) {
            if (strpos($url, $base_url) === 0) {
                $rel_path = str_replace($base_url, '', $url);
                $abs_path = $base_dir . $rel_path;
                if (file_exists($abs_path)) {
                    $files[$rel_path] = base64_encode(file_get_contents($abs_path));
                }
            }
        }
        return $files;
    }

    private static function restore_media_files($media_data) {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        foreach ($media_data as $rel_path => $encoded_content) {
            $abs_path = $base_dir . $rel_path;
            $dir = dirname($abs_path);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            file_put_contents($abs_path, base64_decode($encoded_content));
        }
    }

    public static function handle_scheduled_backup() {
        self::init();
        $payload = self::generate_backup('all');
        $filename = 'backup-' . date('Y-m-d-H-i-s') . '.smb';
        file_put_contents(self::$backup_dir . '/' . $filename, $payload);

        // Retention: keep only last 10 backups
        $files = glob(self::$backup_dir . '/*.smb');
        if (count($files) > 10) {
            array_multisort(array_map('filemtime', $files), SORT_ASC, $files);
            for ($i = 0; $i < count($files) - 10; $i++) {
                unlink($files[$i]);
            }
        }

        update_option('sm_last_auto_backup', current_time('mysql'));
    }
}
