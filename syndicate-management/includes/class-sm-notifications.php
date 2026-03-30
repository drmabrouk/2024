<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Notifications {
    public static function get_template($type) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_notification_templates WHERE template_type = %s", $type));
    }

    public static function ajax_get_template_ajax() {
        if (!current_user_can('sm_manage_system') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        $type = sanitize_text_field($_REQUEST['type'] ?? ($_REQUEST['template_type'] ?? ''));
        $template = self::get_template($type);
        if ($template) {
            wp_send_json_success($template);
        } else {
            wp_send_json_error('Template not found');
        }
    }

    public static function save_template($data) {
        global $wpdb;
        return $wpdb->replace("{$wpdb->prefix}sm_notification_templates", [
            'template_type' => sanitize_text_field($data['template_type']),
            'subject' => sanitize_text_field($data['subject']),
            'body' => sanitize_textarea_field($data['body']),
            'days_before' => intval($data['days_before']),
            'is_enabled' => isset($data['is_enabled']) ? 1 : 0
        ]);
    }

    public static function send_template_notification($mid, $type, $extra = []) {
        $t = self::get_template($type);
        if (!$t || !$t->is_enabled) {
            return false;
        }

        $m = SM_DB::get_member_by_id($mid);
        if (!$m || empty($m->email)) {
            return false;
        }

        $subj = $t->subject;
        $body = $t->body;
        $pls = array_merge([
            '{member_name}' => $m->name,
            '{national_id}' => $m->national_id,
            '{membership_number}' => $m->membership_number,
            '{governorate}' => SM_Settings::get_governorates()[$m->governorate] ?? $m->governorate,
            '{year}' => date('Y')
        ], $extra);

        foreach ($pls as $s => $r) {
            $subj = str_replace($s, $r, $subj);
            $body = str_replace($s, $r, $body);
        }

        $dsgn = get_option('sm_email_design_settings', [
            'header_bg' => '#111F35',
            'header_text' => '#ffffff',
            'footer_text' => '#64748b',
            'accent_color' => '#F63049'
        ]);

        $synd = SM_Settings::get_syndicate_info();

        // Append Branch Details
        $branch_contact = '';
        if (!empty($m->governorate)) {
            global $wpdb;
            $branch = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_branches_data WHERE slug = %s", $m->governorate));
            if ($branch) {
                $branch_contact = "\n\n---\nللتواصل مع فرعك النقابي ({$branch->name}):\n";
                if ($branch->phone) $branch_contact .= "الهاتف: {$branch->phone}\n";
                if ($branch->email) $branch_contact .= "البريد: {$branch->email}\n";
                if ($branch->address) $branch_contact .= "العنوان: {$branch->address}\n";
            }
        }
        $body .= $branch_contact;

        $html = self::wrap_in_template($subj, $body, $dsgn, $synd);
        $from = get_option('sm_noreply_email', 'noreply@irseg.org');
        $from_name = "Injuries and Rehabilitation Syndicate";

        $from_filter = function() use ($from) { return $from; };
        $name_filter = function() use ($from_name) { return $from_name; };
        $type_filter = function() { return 'text/html'; };

        add_filter('wp_mail_from', $from_filter);
        add_filter('wp_mail_from_name', $name_filter);
        add_filter('wp_mail_content_type', $type_filter);

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($m->email, $subj, $html, $headers);

        remove_filter('wp_mail_from', $from_filter);
        remove_filter('wp_mail_from_name', $name_filter);
        remove_filter('wp_mail_content_type', $type_filter);
        self::log_notification($mid, $type, $m->email, $subj, $sent ? 'success' : 'failed');

        return $sent;
    }

    private static function wrap_in_template($subj, $body, $d, $s) {
        $logo = !empty($s['syndicate_logo']) ? '<img src="'.esc_url($s['syndicate_logo']).'" style="max-height:80px; margin-bottom:25px;">' : '';
        $primary_color = $d['accent_color'] ?: '#F63049';
        $header_bg = $d['header_bg'] ?: '#111F35';
        $header_text = $d['header_text'] ?: '#ffffff';
        $footer_text = $d['footer_text'] ?: '#64748b';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: 'Tahoma', sans-serif; background-color: #f8fafc; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }
                .wrapper { width: 100%; table-layout: fixed; background-color: #f8fafc; padding: 40px 0; }
                .container { width: 100%; max-width: 650px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
                .header { background-color: <?php echo $header_bg; ?>; padding: 40px; text-align: center; color: <?php echo $header_text; ?>; }
                .header h1 { margin: 0; font-size: 22px; font-weight: 800; }
                .content { padding: 50px; text-align: right; line-height: 1.8; color: #1e293b; }
                .content h2 { color: <?php echo $primary_color; ?>; margin-top: 0; margin-bottom: 30px; font-size: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; }
                .footer { background-color: #f1f5f9; padding: 30px; text-align: center; font-size: 12px; color: <?php echo $footer_text; ?>; }
                .footer p { margin: 0; }
                .footer .copyright { margin: 15px 0 0 0; font-weight: 700; color: #475569; }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="container">
                    <div class="header">
                        <?php echo $logo; ?>
                        <h1><?php echo esc_html($s['syndicate_name']); ?></h1>
                    </div>
                    <div class="content">
                        <h2><?php echo esc_html($subj); ?></h2>
                        <div style="white-space: pre-wrap; font-size: 16px;"><?php echo esc_html($body); ?></div>
                    </div>
                    <div class="footer">
                        <p>هذه رسالة رسمية صادرة عن المنصة الرقمية لنقابة الإصابات والتأهيل.</p>
                        <p>يرجى عدم الرد على هذا البريد الإلكتروني لأنه مخصص للإرسال فقط.</p>
                        <p class="copyright">&copy; <?php echo date('Y'); ?> <?php echo esc_html($s['syndicate_name']); ?> | جميع الحقوق محفوظة</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private static function log_notification($mid, $type, $email, $subj, $status) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}sm_notification_logs", [
            'member_id' => $mid,
            'notification_type' => $type,
            'recipient_email' => $email,
            'subject' => $subj,
            'status' => $status,
            'sent_at' => current_time('mysql')
        ]);
    }

    /**
     * run_daily_checks
     * Main entry point for cron maintenance
     */
    public static function run_daily_checks() {
        self::check_membership_renewals();
        self::check_license_expirations();
        self::check_payment_dues();

        // Data Integrity Monitoring
        $health = SM_Health_Check::run_all_checks();
        $issues = 0;
        foreach($health as $check) {
            if ($check['status'] !== 'success') {
                $issues += $check['count'];
            }
        }

        if ($issues > 0) {
            SM_DB::save_alert([
                'title' => 'تنبيه: اكتشاف فجوات في سلامة بيانات النظام',
                'message' => "تم اكتشاف عدد ($issues) مشكلة محتملة في قاعدة البيانات خلال الفحص التلقائي اليومي. يرجى مراجعة صفحة 'صحة النظام' في الإعدادات المتقدمة.",
                'severity' => 'warning',
                'status' => 'active',
                'target_roles' => ['administrator'],
                'must_acknowledge' => 1
            ]);
        }
    }

    private static function check_membership_renewals() {
        $t = self::get_template('membership_renewal');
        if (!$t || !$t->is_enabled) {
            return;
        }
        global $wpdb;
        $cy = date('Y');
        $ms = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE last_paid_membership_year < %d", $cy));
        foreach ($ms as $m) {
            if (!self::already_notified($m->id, 'membership_renewal', 25)) {
                self::send_template_notification($m->id, 'membership_renewal', ['{year}' => $cy]);
            }
        }
    }

    private static function check_license_expirations() {
        $types = ['license_practice', 'license_facility'];
        global $wpdb;
        foreach ($types as $type) {
            $t = self::get_template($type);
            if (!$t || !$t->is_enabled) {
                continue;
            }
            $tar = date('Y-m-d', strtotime("+{$t->days_before} days"));
            $f = ($type === 'license_practice') ? 'license_expiration_date' : 'facility_license_expiration_date';
            $ms = $wpdb->get_results($wpdb->prepare("SELECT id, $f as exp, facility_name FROM {$wpdb->prefix}sm_members WHERE $f = %s", $tar));
            foreach ($ms as $m) {
                if (!self::already_notified($m->id, $type, 5)) {
                    self::send_template_notification($m->id, $type, ['{expiry_date}' => $m->exp, '{facility_name}' => $m->facility_name ?? '']);
                }
            }
        }
    }

    private static function check_payment_dues() {
        $t = self::get_template('payment_reminder');
        if (!$t || !$t->is_enabled) {
            return;
        }
        $ms = SM_DB::get_members(['limit' => -1]);

        if (!empty($ms)) {
            SM_Finance::prefetch_data(array_map(function($m) { return $m->id; }, $ms));
        }

        foreach ($ms as $m) {
            $dues = SM_Finance::calculate_member_dues($m);
            if ($dues['balance'] > 500 && !self::already_notified($m->id, 'payment_reminder', 30)) {
                self::send_template_notification($m->id, 'payment_reminder', ['{balance}' => $dues['balance']]);
            }
        }
    }

    private static function already_notified($mid, $type, $limit) {
        global $wpdb;
        $last = $wpdb->get_var($wpdb->prepare("SELECT sent_at FROM {$wpdb->prefix}sm_notification_logs WHERE member_id = %d AND notification_type = %s ORDER BY sent_at DESC LIMIT 1", $mid, $type));
        if (!$last) {
            return false;
        }
        return (strtotime($last) > strtotime("-$limit days"));
    }

    public static function get_logs($limit = 100, $offset = 0) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT l.*, m.name as member_name FROM {$wpdb->prefix}sm_notification_logs l LEFT JOIN {$wpdb->prefix}sm_members m ON l.member_id = m.id ORDER BY l.sent_at DESC LIMIT %d OFFSET %d", $limit, $offset));
    }
}
