<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_System_Manager {
    private static function check_capability($cap) {
        if (!current_user_can($cap)) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }
    }

    public static function ajax_save_branch() {
        try {
            $can_manage_all = current_user_can('sm_full_access') || current_user_can('manage_options');
            $is_officer = current_user_can('sm_branch_access') && !$can_manage_all;

            if (!$can_manage_all && !$is_officer) {
                wp_send_json_error(['message' => 'Unauthorized: User lacks required management capabilities.']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

        if (empty($_POST['name'])) {
            wp_send_json_error(['message' => 'اسم الفرع مطلوب.']);
        }

        $data = $_POST;
        $id = !empty($data['id']) ? intval($data['id']) : null;

        // Granular Permissions: Branch officers can only edit their own branch
        if ($is_officer && !$can_manage_all) {
            $my_gov = get_user_meta(get_current_user_id(), 'sm_governorate', true);
            if (!$id) {
                wp_send_json_error(['message' => 'لا تملك صلاحية إضافة فروع جديدة.']);
            }
            $branch = SM_DB::get_branch_by_id($id);
            if (!$branch || $branch->slug !== $my_gov) {
                wp_send_json_error(['message' => 'لا تملك صلاحية تعديل هذا الفرع.']);
            }
            // Officers cannot change the slug (internal routing)
            unset($data['slug']);
        }

        if (isset($data['is_active'])) {
            $data['is_active'] = (int)$data['is_active'];
        }

        if (!empty($data['email']) && !is_email($data['email'])) {
             wp_send_json_error(['message' => 'البريد الإلكتروني غير صحيح.']);
        }

            $res = SM_DB::save_branch($data);
            if ($res !== false) {
                SM_Logger::log('حفظ بيانات فرع', "تم حفظ بيانات الفرع: " . sanitize_text_field($data['name'] ?? ''));
                wp_send_json_success('Branch saved');
            } else {
                wp_send_json_error(['message' => 'فشل في حفظ بيانات الفرع. تأكد من صحة الكود (Slug).']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error while saving branch: ' . $e->getMessage()]);
        }
    }

    public static function ajax_delete_branch() {
        try {
            if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized: عذراً، صلاحية حذف الفروع مقتصرة على الإدارة العامة ومدير النظام فقط.']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
            $id = intval($_POST['id']);
            if (SM_DB::delete_branch($id)) {
                SM_Logger::log('حذف فرع', "تم حذف الفرع رقم #$id");
                wp_send_json_success('Branch deleted');
            } else {
                wp_send_json_error(['message' => 'Failed to delete branch']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error deleting branch: ' . $e->getMessage()]);
        }
    }

    public static function ajax_delete_alert() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
            $id = intval($_POST['id']);
            if (SM_DB::delete_alert($id)) {
                wp_send_json_success('Alert deleted');
            } else {
                wp_send_json_error(['message' => 'Failed to delete alert']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_acknowledge_alert() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
            $aid = intval($_POST['alert_id']);
            $uid = get_current_user_id();
            if (SM_DB::acknowledge_alert($aid, $uid)) {
                wp_send_json_success('Alert acknowledged');
            } else {
                wp_send_json_error(['message' => 'Failed to acknowledge alert']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_save_alert() {
        try {
            if (!current_user_can('sm_manage_system') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $data = [
            'id' => !empty($_POST['id']) ? intval($_POST['id']) : null,
            'title' => sanitize_text_field($_POST['title']),
            'message' => wp_kses_post($_POST['message']),
            'severity' => sanitize_text_field($_POST['severity']),
            'must_acknowledge' => !empty($_POST['must_acknowledge']) ? 1 : 0,
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'target_roles' => $_POST['target_roles'] ?? [],
            'target_ranks' => $_POST['target_ranks'] ?? [],
            'target_users' => sanitize_text_field($_POST['target_users'] ?? '')
        ];

            if (SM_DB::save_alert($data)) {
                wp_send_json_success('Alert saved');
            } else {
                wp_send_json_error(['message' => 'Failed to save alert']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error saving alert: ' . $e->getMessage()]);
        }
    }

    public static function ajax_reset_system() {
        try {
            if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
        if (!function_exists('wp_delete_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }

        $pass = $_POST['admin_password'] ?? '';
        $user = wp_get_current_user();
        if (!wp_check_password($pass, $user->user_pass, $user->ID)) {
            wp_send_json_error(['message' => 'كلمة المرور غير صحيحة.']);
        }

        $tables = ['sm_members', 'sm_payments', 'sm_logs', 'sm_messages', 'sm_surveys', 'sm_survey_responses', 'sm_update_requests'];
        $uids = SM_DB::get_member_wp_user_ids();

        if (!empty($uids)) {
            foreach ($uids as $uid) {
                wp_delete_user($uid);
            }
        }

        SM_DB::truncate_tables($tables);
        delete_option('sm_invoice_sequence_' . date('Y'));

            SM_Logger::log('إعادة تهيئة النظام', "تم مسح كافة البيانات وتصفير النظام بالكامل");
            wp_send_json_success('System reset complete');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error resetting system: ' . $e->getMessage()]);
        }
    }

    public static function ajax_rollback_log() {
        try {
            if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

        $lid = intval($_POST['log_id']);
        $log = SM_DB::get_log($lid);

        if (!$log || strpos($log->details, 'ROLLBACK_DATA:') !== 0) {
            wp_send_json_error(['message' => 'لا توجد بيانات استعادة']);
        }

        $info = json_decode(str_replace('ROLLBACK_DATA:', '', $log->details), true);
        if (!$info || !isset($info['table'])) {
            wp_send_json_error(['message' => 'تنسيق غير صحيح']);
        }

        $table = $info['table'];
        $data = $info['data'];

        if ($table === 'members') {
            $uid = $data['wp_user_id'] ?? null;
            if (!empty($data['national_id']) && username_exists($data['national_id'])) {
                wp_send_json_error(['message' => 'اسم المستخدم موجود بالفعل']);
            }

            if ($uid && !get_userdata($uid)) {
                $digits = '';
                for ($i = 0; $i < 10; $i++) {
                    $digits .= mt_rand(0, 9);
                }
                $tp = null;
                $uid = wp_insert_user([
                    'user_login' => $data['national_id'],
                    'user_email' => $data['email'] ?: $data['national_id'] . '@irseg.org',
                    'display_name' => $data['name'],
                    'user_pass' => $tp,
                    'role' => 'sm_member'
                ]);
                if (is_wp_error($uid)) {
                    wp_send_json_error($uid->get_error_message());
                }
                if (!empty($data['governorate'])) {
                    update_user_meta($uid, 'sm_governorate', $data['governorate']);
                }
            }

            unset($data['id']);
            $data['wp_user_id'] = $uid;
            if (SM_DB::add_member($data)) {
                SM_Logger::log('استعادة بيانات', "تم استعادة العضو: " . $data['name']);
                wp_send_json_success('Member restored');
            } else {
                wp_send_json_error(['message' => 'فشل في إدراج البيانات']);
            }
        } elseif ($table === 'services') {
            unset($data['id']);
            if (SM_DB::add_service($data)) {
                SM_Logger::log('استعادة بيانات', "تم استعادة الخدمة: " . $data['name']);
                wp_send_json_success('Service restored');
            } else {
                wp_send_json_error(['message' => 'فشل في إدراج البيانات']);
            }
        }
            wp_send_json_error(['message' => 'نوع الاستعادة غير مدعوم']);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error during rollback: ' . $e->getMessage()]);
        }
    }

    public static function ajax_get_counts() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_admin_action', 'nonce');
            wp_send_json_success(['pending_reports' => SM_DB::get_pending_reports_count()]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_delete_gov_data() {
        try {
            if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
        if (!function_exists('wp_delete_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }
        $gov = sanitize_text_field($_POST['governorate']);
        if (!$gov) {
            wp_send_json_error(['message' => 'فرع غير محددة']);
        }
        $m_ids = SM_DB::get_member_ids_by_governorate($gov);
        if (empty($m_ids)) {
            wp_send_json_success('لا توجد بيانات');
        }
        $uids = SM_DB::get_member_wp_user_ids($gov);
        if (!empty($uids)) {
            foreach ($uids as $uid) wp_delete_user($uid);
        }
        SM_DB::delete_payments_by_member_ids($m_ids);
        SM_DB::delete_members_by_governorate($gov);
            SM_Logger::log('حذف بيانات فرع', "تم مسح كافة بيانات فرع: $gov");
            wp_send_json_success('Governorate data deleted');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error deleting governorate data: ' . $e->getMessage()]);
        }
    }

    public static function ajax_merge_gov_data() {
        try {
            if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
        if (!function_exists('wp_insert_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }
        $gov = sanitize_text_field($_POST['governorate']);
        if (empty($_FILES['backup_file']['tmp_name'])) {
            wp_send_json_error(['message' => 'الملف غير موجود']);
        }
        $data = json_decode(file_get_contents($_FILES['backup_file']['tmp_name']), true);
        if (!$data || !isset($data['members'])) {
            wp_send_json_error(['message' => 'تنسيق غير صحيح']);
        }
        $success = 0;
        foreach ($data['members'] as $row) {
            if ($row['governorate'] !== $gov || SM_DB::member_exists($row['national_id'])) {
                continue;
            }
            unset($row['id']);
            $tp = null;
            $uid = wp_insert_user([
                'user_login' => $row['national_id'],
                'user_email' => $row['email'] ?: $row['national_id'] . '@irseg.org',
                'display_name' => $row['name'],
                'user_pass' => $tp,
                'role' => 'sm_member'
            ]);
            if (!is_wp_error($uid)) {
                $row['wp_user_id'] = $uid;
                update_user_meta($uid, 'sm_governorate', $gov);
            }
            if (SM_DB::add_member($row)) {
                $success++;
            }
        }
            wp_send_json_success("تم دمج $success عضواً.");
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error merging governorate data: ' . $e->getMessage()]);
        }
    }

    public static function ajax_delete_log() {
        try {
            self::check_capability('manage_options');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
            SM_DB::delete_log(intval($_POST['log_id']));
            wp_send_json_success('Log deleted');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_clear_all_logs() {
        try {
            self::check_capability('manage_options');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
            SM_DB::truncate_logs();
            wp_send_json_success('Logs cleared');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_get_pub_template() {
        try {
            if (!current_user_can('sm_manage_system') && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_pub_action', 'nonce');
            $t = SM_DB::get_pub_template(intval($_GET['id']));
            if ($t) {
                wp_send_json_success($t);
            } else {
                wp_send_json_error(['message' => 'Not found']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_generate_pub_doc() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        if (isset($_POST['nonce'])) {
            check_ajax_referer('sm_pub_action', 'nonce');
        } else {
            check_ajax_referer('sm_pub_action', '_wpnonce');
        }
        $did = SM_DB::generate_pub_document([
            'title' => sanitize_text_field($_POST['title']),
            'content' => wp_kses_post($_POST['content']),
            'member_id' => intval($_POST['member_id'] ?? 0),
            'options' => [
                'doc_type' => sanitize_text_field($_POST['doc_type'] ?? 'report'),
                'fees' => floatval($_POST['fees'] ?? 0),
                'header' => isset($_POST['header']),
                'footer' => isset($_POST['footer']),
                'qr' => isset($_POST['qr']),
                'barcode' => isset($_POST['barcode'])
            ]
        ]);
        if ($did) {
            wp_send_json_success(['url' => admin_url('admin-ajax.php?action=sm_print_pub_doc&id=' . $did . '&format=' . sanitize_text_field($_POST['format'] ?? 'pdf'))]);
        } else {
            wp_send_json_error(['message' => 'Failed']);
        }
    }

    public static function ajax_save_pub_identity() {
        try {
            if (!current_user_can('sm_manage_system') && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_pub_action', 'nonce');
            } else {
                check_ajax_referer('sm_pub_action', '_wpnonce');
            }
        $info = SM_Settings::get_syndicate_info();
        $info['syndicate_name'] = sanitize_text_field($_POST['syndicate_name']);
        $info['authority_name'] = sanitize_text_field($_POST['authority_name']);
        $info['phone'] = sanitize_text_field($_POST['phone']);
        $info['email'] = sanitize_email($_POST['email']);
        $info['address'] = sanitize_text_field($_POST['address']);
        $info['syndicate_logo'] = esc_url_raw($_POST['syndicate_logo']);
        $info['authority_logo'] = esc_url_raw($_POST['authority_logo']);
            SM_Settings::save_syndicate_info($info);
            wp_send_json_success('Identity saved');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_save_pub_template() {
        try {
            if (!current_user_can('sm_manage_system') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_pub_action', 'nonce');
            } else {
                check_ajax_referer('sm_pub_action', '_wpnonce');
            }
            if (SM_DB::save_pub_template($_POST)) {
                wp_send_json_success('Template saved');
            } else {
                wp_send_json_error(['message' => 'Failed to save template']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error saving template: ' . $e->getMessage()]);
        }
    }

    public static function ajax_export_branches() {
        if (!current_user_can('sm_full_access')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        $bs = SM_DB::get_branches_data();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=branches.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Slug', 'Name', 'Phone', 'Email', 'Address']);
        foreach ($bs as $b) fputcsv($out, [$b->id, $b->slug, $b->name, $b->phone, $b->email, $b->address]);
        fclose($out);
        exit;
    }

    public static function ajax_print_pub_doc() {
        if (!current_user_can('sm_manage_system')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('sm_pub_action', 'nonce');
        $id = intval($_GET['id']);
        $doc = SM_DB::get_pub_document_by_id($id);
        if (!$doc) {
            wp_die('Document not found');
        }
        SM_DB::increment_pub_download($id, sanitize_text_field($_GET['format'] ?? 'pdf'));
        include SM_PLUGIN_DIR . 'templates/print-pub-document.php';
        exit;
    }

    public static function ajax_get_branch_details() {
        try {
            if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
            check_ajax_referer('sm_admin_action', 'nonce');
            $id = intval($_GET['id']);
        $branch = SM_DB::get_branch_by_id($id);
        if (!$branch) wp_send_json_error(['message' => 'Branch not found']);

        $stats = SM_DB_Finance::get_statistics(['governorate' => $branch->slug]);
            wp_send_json_success([
                'branch' => $branch,
                'stats' => $stats
            ]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_download_backup() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
        if (isset($_POST['nonce'])) {
            check_ajax_referer('sm_admin_action', 'nonce');
        } else {
            check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
        }

        $modules = $_POST['modules'] ?? 'all';
        $payload = SM_Backup_Manager::generate_backup($modules);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="syndicate-backup-' . date('Y-m-d') . '.smb"');
        echo $payload;
        exit;
    }

    public static function ajax_restore_backup() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
        if (isset($_POST['nonce'])) {
            check_ajax_referer('sm_admin_action', 'nonce');
        } else {
            check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
        }

        if (empty($_FILES['backup_file']['tmp_name'])) {
            wp_send_json_error(['message' => 'لم يتم رفع أي ملف.']);
        }

        $payload = file_get_contents($_FILES['backup_file']['tmp_name']);
        $selective = $_POST['selective_tables'] ?? 'all';

        $res = SM_Backup_Manager::restore_backup($payload, $selective);

        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        } else {
            wp_send_json_success('Backup restored');
        }
    }

    public static function ajax_get_backup_history() {
        try {
            if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) {
                 wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_admin_action', 'nonce');

            $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'] . '/sm-backups';
        $files = glob($dir . '/*.smb');
        $history = [];

        foreach ($files as $f) {
            $history[] = [
                'filename' => basename($f),
                'date' => date('Y-m-d H:i:s', filemtime($f)),
                'size' => size_format(filesize($f))
            ];
        }

            usort($history, function($a, $b) { return strcmp($b['date'], $a['date']); });
            wp_send_json_success($history);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_download_stored_backup() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
        check_admin_referer('sm_admin_action', 'nonce');

        $filename = sanitize_file_name($_GET['filename'] ?? '');
        if (empty($filename)) wp_send_json_error(['message' => 'Invalid file']);

        $upload_dir = wp_upload_dir();
        $path = $upload_dir['basedir'] . '/sm-backups/' . $filename;

        if (!file_exists($path)) wp_send_json_error(['message' => 'File not found']);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($path);
        exit;
    }

    public static function ajax_update_backup_freq() {
        try {
            if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) {
                 wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

        $freq = sanitize_text_field($_POST['frequency'] ?? 'weekly');
        update_option('sm_backup_frequency', $freq);

        wp_clear_scheduled_hook('sm_scheduled_backup');
        SM_Backup_Manager::schedule_automated_backup();

            wp_send_json_success('Backup frequency updated');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }
}
