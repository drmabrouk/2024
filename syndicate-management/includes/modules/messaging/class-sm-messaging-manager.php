<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Messaging_Manager {
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

    public static function ajax_send_message() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_message_action', 'nonce');
            } else {
                check_ajax_referer('sm_message_action', '_wpnonce');
            }

        $sid = get_current_user_id();
        $mid = intval($_POST['member_id'] ?? 0);

        if (!$mid) {
            $member_wp = SM_DB_Members::get_member_by_username(wp_get_current_user()->user_login);
            if ($member_wp) {
                $mid = $member_wp->id;
            }
        }

        self::validate_member_access($mid);

        $member = SM_DB::get_member_by_id($mid);
        if (!$member) {
            wp_send_json_error(['message' => 'Invalid member context']);
        }

        $msg = sanitize_textarea_field($_POST['message'] ?? '');
        $rid = intval($_POST['receiver_id'] ?? 0);

        $url = null;
        if (!empty($_FILES['message_file']['name'])) {
            if (!function_exists('media_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
            }
            $att_id = media_handle_upload('message_file', 0);
            if (!is_wp_error($att_id)) {
                $url = wp_get_attachment_url($att_id);
            }
        }

            SM_DB::send_message($sid, $rid, $msg, $mid, $url, $member->governorate);
            wp_send_json_success();
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error sending message: ' . $e->getMessage()]);
        }
    }

    public static function ajax_get_conversation() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_message_action', 'nonce');
            } else {
                check_ajax_referer('sm_message_action', '_wpnonce');
            }

        $mid = intval($_POST['member_id'] ?? 0);
        if (!$mid) {
            $member_wp = SM_DB_Members::get_member_by_username(wp_get_current_user()->user_login);
            if ($member_wp) {
                $mid = $member_wp->id;
            }
        }

        self::validate_member_access($mid);

            wp_send_json_success(SM_DB::get_ticket_messages($mid));
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
        }
    }

    public static function ajax_submit_contact_form() {
        check_ajax_referer('sm_contact_action', 'nonce');

        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $subj = sanitize_text_field($_POST['subject']);
        $msg = sanitize_textarea_field($_POST['message']);

        $member = SM_DB_Members::get_member_by_email($email);
        $mid = $member ? $member->id : 0;
        $prov = $member ? $member->governorate : 'HQ';

        $tid = SM_DB_Communications::create_ticket([
            'member_id' => $mid,
            'subject' => $subj,
            'category' => 'inquiry',
            'priority' => 'medium',
            'message' => "رسالة من نموذج التواصل:\n\nالاسم: $name\nالهاتف: $phone\nالبريد: $email\n\nالرسالة:\n$msg",
            'province' => $prov,
            'sender_id' => is_user_logged_in() ? get_current_user_id() : 0
        ]);

        if ($tid) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'فشل تقديم تذكرة الدعم']);
        }
    }

    public static function ajax_get_conversations() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_message_action', 'nonce');
            } else {
                check_ajax_referer('sm_message_action', '_wpnonce');
            }
        $user = wp_get_current_user();
        $gov = get_user_meta($user->ID, 'sm_governorate', true);
        $has_full = current_user_can('sm_full_access') || current_user_can('manage_options');

        if (!$gov && !$has_full) {
            wp_send_json_error(['message' => 'No governorate assigned']);
        }

        if (in_array('sm_member', (array)$user->roles)) {
            $offs = SM_DB::get_governorate_officials($gov);
            $data = [];
            foreach($offs as $o) {
                $data[] = [
                    'official' => [
                        'ID' => $o->ID,
                        'display_name' => $o->display_name,
                        'avatar' => get_avatar_url($o->ID)
                    ]
                ];
            }
            wp_send_json_success(['type' => 'member_view', 'officials' => $data]);
            } else {
                $t_gov = $has_full ? null : $gov;
                $convs = SM_DB::get_governorate_conversations($t_gov);
                foreach($convs as &$c) {
                    $c['member']->avatar = $c['member']->photo_url ?: get_avatar_url($c['member']->wp_user_id ?: 0);
                }
                wp_send_json_success(['type' => 'official_view', 'conversations' => $convs]);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error fetching conversations: ' . $e->getMessage()]);
        }
    }

    public static function ajax_mark_read() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        if (isset($_POST['nonce'])) {
            check_ajax_referer('sm_message_action', 'nonce');
        } else {
            check_ajax_referer('sm_message_action', '_wpnonce');
        }
        SM_DB::mark_messages_read(get_current_user_id(), intval($_POST['other_user_id']));
        wp_send_json_success();
    }

    public static function ajax_get_tickets() {
        if (!is_user_logged_in() || !current_user_can('read')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        if (isset($_REQUEST['nonce'])) {
            check_ajax_referer('sm_ticket_action', 'nonce');
        } else {
            check_ajax_referer('sm_ticket_action', '_wpnonce');
        }
        wp_send_json_success(SM_DB::get_tickets($_GET));
    }

    public static function ajax_create_ticket() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_ticket_action', 'nonce');
            } else {
                check_ajax_referer('sm_ticket_action', '_wpnonce');
            }
        $user = wp_get_current_user();
        $member = SM_DB_Members::get_member_by_username($user->user_login);
        if (!$member) {
            wp_send_json_error(['message' => 'Member profile not found']);
        }
        $url = null;
        $attachment_path = null;
        if (!empty($_FILES['attachment']['name'])) {
            if (!function_exists('media_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
            }
            $att_id = media_handle_upload('attachment', 0);
            if (!is_wp_error($att_id)) {
                $url = wp_get_attachment_url($att_id);
                $attachment_path = get_attached_file($att_id);
            }
        }
        $subject = sanitize_text_field($_POST['subject']);
        $message = sanitize_textarea_field($_POST['message']);
        $tid = SM_DB::create_ticket([
            'member_id' => $member->id,
            'subject' => $subject,
            'category' => sanitize_text_field($_POST['category']),
            'priority' => sanitize_text_field($_POST['priority'] ?? 'medium'),
            'message' => $message,
            'province' => $member->governorate,
            'file_url' => $url
        ]);
            if ($tid) {
                // Notification to official (Optional, but let's send confirmation to member)
                if ($member->email) {
                    $email_subject = "تأكيد استلام تذكرة دعم رقم #$tid: $subject";
                    $email_body = "عزيزي العضو {$member->name}،\n\nتم استلام تذكرتكم بنجاح وسيقوم الفريق المختص بمراجعتها والرد عليكم في أقرب وقت ممكن.\n\nالموضوع: $subject\nالتفاصيل: $message";
                    self::send_professional_html_email($member->email, $email_subject, $email_body, $attachment_path ? [$attachment_path] : [], $member);
                }
                wp_send_json_success($tid);
            } else {
                wp_send_json_error(['message' => 'Failed to create ticket']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error creating ticket: ' . $e->getMessage()]);
        }
    }

    public static function ajax_get_ticket_details() {
        if (!is_user_logged_in() || !current_user_can('read')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        if (isset($_REQUEST['nonce'])) {
            check_ajax_referer('sm_ticket_action', 'nonce');
        } else {
            check_ajax_referer('sm_ticket_action', '_wpnonce');
        }
        $id = intval($_GET['id']);
        $ticket = SM_DB::get_ticket($id);
        if (!$ticket) {
            wp_send_json_error(['message' => 'Ticket not found']);
        }
        $user = wp_get_current_user();
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            if (current_user_can('sm_branch_access')) {
                $gov = get_user_meta($user->ID, 'sm_governorate', true);
                if ($gov && $ticket->province !== $gov) {
                    wp_send_json_error(['message' => 'Access denied']);
                }
            } else {
                $member = SM_DB_Members::get_member_by_username($user->user_login);
                if (!$member || $ticket->member_id != $member->id) {
                    wp_send_json_error(['message' => 'Access denied']);
                }
            }
        }
        wp_send_json_success(array('ticket' => $ticket, 'thread' => SM_DB::get_ticket_thread($id)));
    }

    public static function ajax_add_ticket_reply() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_ticket_action', 'nonce');
            } else {
                check_ajax_referer('sm_ticket_action', '_wpnonce');
            }
        $tid = intval($_POST['ticket_id']);
        $ticket = SM_DB::get_ticket($tid);
        if (!$ticket) wp_send_json_error(['message' => 'Ticket not found']);

        $url = null;
        $attachment_path = null;
        if (!empty($_FILES['attachment']['name'])) {
            if (!function_exists('media_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
            }
            $att_id = media_handle_upload('attachment', 0);
            if (!is_wp_error($att_id)) {
                $url = wp_get_attachment_url($att_id);
                $attachment_path = get_attached_file($att_id);
            }
        }
        $msg = sanitize_textarea_field($_POST['message']);
        $rid = SM_DB::add_ticket_reply([
            'ticket_id' => $tid,
            'sender_id' => get_current_user_id(),
            'message' => $msg,
            'file_url' => $url
        ]);
            if ($rid) {
                $sender = wp_get_current_user();
                $is_official_reply = current_user_can('sm_branch_access') || current_user_can('sm_full_access');

                if ($is_official_reply) {
                    SM_DB::update_ticket_status($tid, 'in-progress');

                    // Send email to member
                    $member = SM_DB::get_member_by_id($ticket->member_id);
                    if ($member && $member->email) {
                        $email_subject = "رد جديد على تذكرة الدعم: " . $ticket->subject;
                        $email_body = "السيد الزميل/ {$member->name}\nتحية طيبة وبعد،،\n\nتم إضافة رد جديد من قبل إدارة النقابة على تذكرة الدعم الخاصة بكم:\n\n" . $msg . "\n\nيمكنكم متابعة التذكرة والرد عليها عبر حسابكم في المنصة الرقمية.";
                        self::send_professional_html_email($member->email, $email_subject, $email_body, $attachment_path ? [$attachment_path] : [], $member);
                    }
                }
                wp_send_json_success($rid);
            } else {
                wp_send_json_error(['message' => 'Failed to add reply']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error adding reply: ' . $e->getMessage()]);
        }
    }

    public static function ajax_close_ticket() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        if (isset($_POST['nonce'])) {
            check_ajax_referer('sm_ticket_action', 'nonce');
        } else {
            check_ajax_referer('sm_ticket_action', '_wpnonce');
        }
        if (SM_DB::update_ticket_status(intval($_POST['id']), 'closed')) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to close ticket']);
        }
    }

    public static function ajax_get_communication_templates() {
        self::check_capability('sm_manage_system');
        check_ajax_referer('sm_admin_action', 'nonce');
        wp_send_json_success(SM_DB::get_notification_templates());
    }

    public static function ajax_send_direct_message() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_message_action', 'nonce');
            } else {
                check_ajax_referer('sm_message_action', '_wpnonce');
            }

        $member_ids = isset($_POST['member_ids']) ? array_map('intval', $_POST['member_ids']) : [];
        if (empty($member_ids) && !empty($_POST['member_id'])) {
            $member_ids[] = intval($_POST['member_id']);
        }

        if (empty($member_ids)) wp_send_json_error(['message' => 'لم يتم اختيار أعضاء']);

        // Handle Attachments
        $attachment_paths = [];
        $attachment_urls = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            if (!function_exists('media_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
            }

            foreach ($_FILES['attachments']['name'] as $key => $value) {
                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $_FILES['single_attachment'] = [
                        'name'     => $_FILES['attachments']['name'][$key],
                        'type'     => $_FILES['attachments']['type'][$key],
                        'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
                        'error'    => $_FILES['attachments']['error'][$key],
                        'size'     => $_FILES['attachments']['size'][$key]
                    ];
                    $att_id = media_handle_upload('single_attachment', 0);
                    if (!is_wp_error($att_id)) {
                        $attachment_paths[] = get_attached_file($att_id);
                        $attachment_urls[] = wp_get_attachment_url($att_id);
                    }
                }
            }
        }

        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $raw_message = sanitize_textarea_field($_POST['message'] ?? '');
        $channels = $_POST['channels'] ?? [];
        $template_type = sanitize_text_field($_POST['template_type'] ?? 'direct');

        if (!empty($member_ids)) {
            SM_Finance::prefetch_data($member_ids);
        }

        $results = [];
        foreach ($member_ids as $mid) {
            $member = SM_DB::get_member_by_id($mid);
            if (!$member) continue;

            $finance = SM_Finance::calculate_member_dues($member);
            $message = str_replace(
                ['{member_name}', '{membership_number}', '{year}', '{amount}', '{balance}'],
                [$member->name, $member->membership_number ?: '---', date('Y'), number_format($finance['balance'], 2), number_format($finance['balance'], 2)],
                $raw_message
            );

            $results[$mid] = self::process_single_direct_comm($mid, $member, $channels, $subject, $message, $template_type, $attachment_paths, $attachment_urls);
        }

            wp_send_json_success($results);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error sending direct message: ' . $e->getMessage()]);
        }
    }

    private static function process_single_direct_comm($mid, $member, $channels, $subject, $message, $template_type, $attachment_paths = [], $attachment_urls = []) {
        $sid = get_current_user_id();
        $status_results = [];
        $primary_file_url = !empty($attachment_urls) ? $attachment_urls[0] : null;

        // 1. WhatsApp
        if (in_array('whatsapp', $channels)) {
            SM_DB_Communications::log_notification([
                'member_id' => $mid,
                'channel' => 'whatsapp',
                'type' => $template_type,
                'phone' => $member->phone,
                'message' => $message,
                'subject' => $subject
            ]);
            $status_results['whatsapp'] = 'logged';
        }

        // 2. Email
        if (in_array('email', $channels) && !empty($member->email)) {
            $sent = self::send_professional_html_email($member->email, $subject, $message, $attachment_paths, $member);
            if ($sent) {
                SM_DB_Communications::log_notification([
                    'member_id' => $mid,
                    'channel' => 'email',
                    'type' => $template_type,
                    'email' => $member->email,
                    'message' => $message,
                    'subject' => $subject
                ]);
            }
            $status_results['email'] = $sent ? 'sent' : 'failed';
        }

        // 3. Platform Ticket
        if (in_array('ticket', $channels)) {
            $tid = SM_DB::create_ticket([
                'member_id' => $mid,
                'subject' => $subject,
                'category' => 'other',
                'priority' => 'medium',
                'message' => $message,
                'province' => $member->governorate,
                'file_url' => $primary_file_url
            ]);
            if ($tid) {
                SM_DB_Communications::log_notification([
                    'member_id' => $mid,
                    'channel' => 'ticket',
                    'type' => $template_type,
                    'message' => $message,
                    'subject' => $subject
                ]);
            }
            $status_results['ticket'] = $tid ? 'created' : 'failed';
        }

        // ALWAYS SYNC to sm_messages for Top Bar / Correspondence Tab visibility
        if ($member->wp_user_id) {
            SM_DB::send_message($sid, $member->wp_user_id, $message, $mid, $primary_file_url, $member->governorate);
        }

        // Create targeted alert for Bell icon (Notifications tab)
        SM_DB::save_alert([
            'title' => $subject,
            'message' => $message,
            'severity' => 'info',
            'must_acknowledge' => 0,
            'status' => 'active',
            'target_users' => $member->national_id
        ]);

        return $status_results;
    }

    private static function send_professional_html_email($to, $subject, $message, $attachments, $member) {
        $appearance = SM_Settings::get_appearance();
        $syndicate = SM_Settings::get_syndicate_info();
        $primary_color = $appearance['primary_color'];
        $dark_color = $appearance['dark_color'];

        $branch_details = '';
        if ($member && !empty($member->governorate)) {
             $branch = SM_DB::get_branch_by_slug($member->governorate);
             if ($branch) {
                 $branch_details = "<div style='margin-top: 30px; padding-top: 20px; border-top: 2px solid #f1f5f9; font-size: 13px; color: #475569;'>";
                 $branch_details .= "<strong style='color: {$dark_color};'>للتواصل مع فرعك النقابي ({$branch->name}):</strong><br>";
                 if ($branch->phone) $branch_details .= "رقم الهاتف: {$branch->phone}<br>";
                 if ($branch->email) $branch_details .= "البريد الإلكتروني: {$branch->email}<br>";
                 if ($branch->address) $branch_details .= "العنوان: {$branch->address}";
                 $branch_details .= "</div>";
             }
        }

        $logo_html = !empty($syndicate['syndicate_logo']) ? "<img src='{$syndicate['syndicate_logo']}' style='max-height: 80px; margin-bottom: 25px;'>" : "";

        $html = "
        <div dir='rtl' style='font-family: \"Tahoma\", sans-serif; background-color: #f8fafc; padding: 40px 20px; line-height: 1.8; color: #1e293b;'>
            <div style='max-width: 650px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05);'>
                <div style='background-color: {$dark_color}; padding: 40px; text-align: center; color: #ffffff;'>
                    {$logo_html}
                    <h1 style='margin: 0; font-size: 22px; font-weight: 800;'>{$syndicate['syndicate_name']}</h1>
                </div>
                <div style='padding: 50px;'>
                    <h2 style='color: {$primary_color}; margin-top: 0; margin-bottom: 30px; font-size: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px;'>{$subject}</h2>
                    <div style='font-size: 16px; color: #334155;'>
                        " . nl2br($message) . "
                    </div>
                    {$branch_details}
                </div>
                <div style='background-color: #f1f5f9; padding: 30px; text-align: center; font-size: 12px; color: #64748b;'>
                    <p style='margin: 0;'>هذه رسالة رسمية صادرة عن المنصة الرقمية لنقابة الإصابات والتأهيل.</p>
                    <p style='margin: 5px 0;'>يرجى عدم الرد على هذا البريد الإلكتروني لأنه مخصص للإرسال فقط.</p>
                    <p style='margin: 15px 0 0 0; font-weight: 700;'>&copy; " . date('Y') . " {$syndicate['syndicate_name']} | جميع الحقوق محفوظة</p>
                </div>
            </div>
        </div>";

        $from_email = get_option('sm_noreply_email', 'noreply@irseg.org');
        $from_name = "Injuries and Rehabilitation Syndicate";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: $from_name <$from_email>",
            "Reply-To: $from_email"
        ];

        // We use global filters because wp_mail can be tricky with inline headers for 'From' in some setups
        $from_filter = function() use ($from_email) { return $from_email; };
        $name_filter = function() use ($from_name) { return $from_name; };
        $type_filter = function() { return 'text/html'; };

        add_filter('wp_mail_from', $from_filter);
        add_filter('wp_mail_from_name', $name_filter);
        add_filter('wp_mail_content_type', $type_filter);

        $sent = wp_mail($to, $subject, $html, $headers, $attachments);

        remove_filter('wp_mail_from', $from_filter);
        remove_filter('wp_mail_from_name', $name_filter);
        remove_filter('wp_mail_content_type', $type_filter);

        return $sent;
    }

    public static function ajax_get_member_comms_log() {
        self::check_capability('sm_manage_system');
        check_ajax_referer('sm_admin_action', 'nonce');
        $mid = intval($_GET['member_id'] ?? 0);
        wp_send_json_success(SM_DB_Communications::get_member_notification_logs($mid));
    }
}
