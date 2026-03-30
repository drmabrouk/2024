<?php
if (!defined('ABSPATH')) exit;

class SM_Health_Check {
    public static function run_all_checks() {
        return [
            'schema_integrity' => self::check_schema_integrity(),
            'members_vs_users' => self::check_members_vs_users(),
            'orphaned_payments' => self::check_orphaned_payments(),
            'governorate_consistency' => self::check_governorate_consistency(),
            'broken_documents' => self::check_broken_documents(),
            'performance_metrics' => self::get_performance_metrics()
        ];
    }

    private static function check_schema_integrity() {
        global $wpdb;
        $tables = [
            'sm_members', 'sm_payments', 'sm_logs', 'sm_branches_data',
            'sm_notification_templates', 'sm_notification_logs',
            'sm_service_requests', 'sm_services', 'sm_membership_requests'
        ];
        $missing = [];
        foreach ($tables as $t) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}$t'") != $wpdb->prefix . $t) {
                $missing[] = $t;
            }
        }
        return [
            'label' => 'سلامة هيكل قاعدة البيانات',
            'count' => count($missing),
            'items' => $missing,
            'status' => count($missing) === 0 ? 'success' : 'danger'
        ];
    }

    private static function get_performance_metrics() {
        $slow_queries = get_option('sm_slow_queries', []);
        return [
            'label' => 'مؤشرات أداء الاستعلامات',
            'count' => count($slow_queries),
            'items' => $slow_queries,
            'status' => count($slow_queries) < 5 ? 'success' : 'warning'
        ];
    }

    private static function check_members_vs_users() {
        global $wpdb;
        $missing = $wpdb->get_results("
            SELECT m.id, m.name, m.national_id
            FROM {$wpdb->prefix}sm_members m
            LEFT JOIN {$wpdb->prefix}users u ON m.wp_user_id = u.ID
            WHERE u.ID IS NULL OR m.wp_user_id = 0
        ");
        return [
            'label' => 'الأعضاء بدون حسابات مستخدمين',
            'count' => count($missing),
            'items' => $missing,
            'status' => count($missing) === 0 ? 'success' : 'warning'
        ];
    }

    private static function check_orphaned_payments() {
        global $wpdb;
        $orphaned = $wpdb->get_results("
            SELECT p.id, p.amount, p.payment_date, p.member_id
            FROM {$wpdb->prefix}sm_payments p
            LEFT JOIN {$wpdb->prefix}sm_members m ON p.member_id = m.id
            WHERE m.id IS NULL
        ");
        return [
            'label' => 'عمليات مالية بدون أعضاء مرتبطة',
            'count' => count($orphaned),
            'items' => $orphaned,
            'status' => count($orphaned) === 0 ? 'success' : 'danger'
        ];
    }

    private static function check_governorate_consistency() {
        global $wpdb;
        $inconsistent = $wpdb->get_results("
            SELECT m.id, m.name, m.governorate as member_gov, um.meta_value as user_gov
            FROM {$wpdb->prefix}sm_members m
            JOIN {$wpdb->prefix}usermeta um ON m.wp_user_id = um.user_id AND um.meta_key = 'sm_governorate'
            WHERE m.governorate != um.meta_value
        ");
        return [
            'label' => 'عدم تطابق الفروع بين العضو والمستخدم',
            'count' => count($inconsistent),
            'items' => $inconsistent,
            'status' => count($inconsistent) === 0 ? 'success' : 'warning'
        ];
    }

    private static function check_broken_documents() {
        global $wpdb;
        $docs = $wpdb->get_results("SELECT id, title, file_url FROM {$wpdb->prefix}sm_documents");
        $broken = [];
        foreach ($docs as $doc) {
            if (empty($doc->file_url)) {
                $broken[] = $doc;
            }
        }
        return [
            'label' => 'وثائق بروابط ملفات مكسورة أو فارغة',
            'count' => count($broken),
            'items' => $broken,
            'status' => count($broken) === 0 ? 'success' : 'warning'
        ];
    }

    public static function ajax_run_health_check() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        wp_send_json_success(self::run_all_checks());
    }
}
