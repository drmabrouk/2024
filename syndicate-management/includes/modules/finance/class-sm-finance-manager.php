<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Finance_Manager {
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

    public static function ajax_record_payment() {
        try {
            if (!current_user_can('sm_manage_finance') && !current_user_can('manage_options')) {
                 wp_send_json_error(['message' => 'Unauthorized access.']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_finance_action', 'nonce');
            } else {
                check_ajax_referer('sm_finance_action', '_wpnonce');
            }
            $mid = intval($_POST['member_id']);
            self::validate_member_access($mid);
            if (SM_Finance::record_payment($_POST)) {
            SM_Finance::invalidate_financial_caches();
            delete_transient('sm_stats_global');
            $member = SM_DB::get_member_by_id($mid);
            if ($member && $member->governorate) delete_transient('sm_stats_' . $member->governorate);

                wp_send_json_success(['message' => 'Payment recorded']);
            } else {
                wp_send_json_error(['message' => 'Failed to record payment']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error recording payment: ' . $e->getMessage()]);
        }
    }

    public static function ajax_delete_transaction() {
        try {
            if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
            $id = intval($_POST['transaction_id']);
        $pmt = SM_DB::get_payment_by_id($id);

        SM_DB::delete_payment($id);

        if ($pmt) {
            SM_Finance::invalidate_financial_caches();
            delete_transient('sm_stats_global');
            $member = SM_DB::get_member_by_id($pmt->member_id);
            if ($member && $member->governorate) delete_transient('sm_stats_' . $member->governorate);
        }

            SM_Logger::log('حذف عملية مالية', "تم حذف العملية رقم #$id");
            wp_send_json_success(['message' => 'Transaction deleted']);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error deleting transaction: ' . $e->getMessage()]);
        }
    }

    public static function ajax_get_member_finance_html() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            check_ajax_referer('sm_admin_action', 'nonce');
            $mid = intval($_GET['member_id']);
            self::validate_member_access($mid);

            $member = SM_DB::get_member_by_id($mid);
            $dues = SM_Finance::calculate_member_dues($member);
            $history = SM_Finance::get_payment_history($mid);
            ob_start();
            include SM_PLUGIN_DIR . 'templates/modal-finance-details.php';
            $html = ob_get_clean();
            wp_send_json_success(['html' => $html]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_export_finance_report() {
        if (!current_user_can('sm_manage_finance')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('sm_admin_action', 'nonce');
        $type = sanitize_text_field($_GET['type']);
        $members = SM_DB::get_members(['limit' => -1]);

        if (!empty($members)) {
            SM_Finance::prefetch_data(array_map(function($m) { return $m->id; }, $members));
        }

        $data = [];
        foreach ($members as $m) {
            $dues = SM_Finance::calculate_member_dues($m);
            if ($type === 'overdue_membership' && $dues['membership_balance'] > 0) {
                $data[] = [
                    'name' => $m->name,
                    'nid' => $m->national_id,
                    'amount' => $dues['membership_balance'],
                    'details' => 'متأخرات اشتراك'
                ];
            } elseif ($type === 'unpaid_fines' && $dues['penalty_balance'] > 0) {
                $data[] = [
                    'name' => $m->name,
                    'nid' => $m->national_id,
                    'amount' => $dues['penalty_balance'],
                    'details' => 'غرامات غير مسددة'
                ];
            } elseif ($type === 'full_liabilities' && $dues['balance'] > 0) {
                $data[] = [
                    'name' => $m->name,
                    'nid' => $m->national_id,
                    'amount' => $dues['balance'],
                    'details' => 'إجمالي المديونية'
                ];
            }
        }
        $titles = [
            'overdue_membership' => 'تقرير متأخرات اشتراكات العضوية',
            'unpaid_fines' => 'تقرير الغرامات المالية غير المسددة',
            'full_liabilities' => 'تقرير المديونيات المالية الشامل'
        ];
        $title = $titles[$type] ?? "تقرير مالي";
        include SM_PLUGIN_DIR . 'templates/print-finance-report.php';
        exit;
    }

    public static function ajax_print_invoice() {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }
        check_admin_referer('sm_admin_action', 'nonce');
        $pid = intval($_GET['payment_id'] ?? 0);
        $pmt = SM_DB::get_payment_by_id($pid);
        if (!$pmt || !SM_Member_Manager::can_access_member($pmt->member_id)) {
            wp_die('Unauthorized');
        }
        include SM_PLUGIN_DIR . 'templates/print-invoice.php';
        exit;
    }
}
