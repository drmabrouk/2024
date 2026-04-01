<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authentication Module
 * Handles login, registration, OTP, and account activation.
 */
class SM_Auth {
    public static function register_shortcodes() {
        add_shortcode('sm_login', array(__CLASS__, 'shortcode_login'));
        add_shortcode('login-page', array(__CLASS__, 'shortcode_login_page'));
    }

    public static function shortcode_login() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $is_member = in_array('sm_member', (array)$user->roles);
            wp_redirect(home_url($is_member ? '/my-account' : '/dashboard'));
            exit;
        }
        $syndicate = SM_Settings::get_syndicate_info();
        ob_start();
        ?>
        <div class="sm-login-container" style="display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 80px 20px; background: #f8fafc; border-radius: 20px; margin: 0;">
            <div class="sm-login-box" style="width: 100%; max-width: 420px; background: #ffffff; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); overflow: hidden; border: 1px solid #f1f5f9;" dir="rtl">
                <div style="background: #e2e8f0; padding: 30px 25px; text-align: center; color: var(--sm-dark-color); position: relative; border-bottom: 1px solid #cbd5e0;">
                    <?php if (!empty($syndicate['syndicate_logo'])): ?>
                        <img src="<?php echo esc_url($syndicate['syndicate_logo']); ?>" style="max-height: 60px; margin-bottom: 15px; display: inline-block; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
                    <?php endif; ?>
                    <h2 style="margin: 0; font-weight: 900; color: var(--sm-dark-color); font-size: 1.4em; letter-spacing: -0.5px;"><?php echo esc_html($syndicate['syndicate_name']); ?></h2>
                    <p style="margin: 5px 0 0 0; color: #64748b; font-size: 0.8em;">المنصة الرقمية للخدمات النقابية الموحدة</p>
                </div>
                <div style="padding: 30px 30px;">
                    <?php if (isset($_GET['login']) && $_GET['login'] == 'failed'): ?>
                        <div style="background: #fff5f5; color: #c53030; padding: 10px; border-radius: 8px; border: 1px solid #feb2b2; margin-bottom: 20px; font-size: 0.85em; text-align: center; font-weight: 600;">⚠️ بيانات الدخول غير صحيحة</div>
                    <?php endif; ?>
                    <style>
                        #sm_login_form p { margin-bottom: 15px; position: relative; }
                        #sm_login_form label { display: none; }
                        #sm_login_form input[type="text"], #sm_login_form input[type="password"] {
                            width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px;
                            background: #fcfcfc; font-size: 14px; transition: 0.3s; font-family: "Rubik", sans-serif;
                        }
                        #sm_login_form input:focus { border-color: var(--sm-primary-color); outline: none; background: #fff; }
                        #sm_login_form .login-remember { display: flex; align-items: center; gap: 8px; font-size: 0.8em; color: #64748b; margin-top: -5px; }
                        #sm_login_form input[type="submit"] {
                            width: 100%; padding: 14px; background: var(--sm-primary-color); color: #fff; border: none;
                            border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.3s;
                        }
                        #sm_login_form input[type="submit"]:hover { opacity: 0.9; transform: translateY(-1px); }
                        .sm-login-footer-links { margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
                        .sm-footer-btn { text-decoration: none !important; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 700; text-align: center; transition: 0.2s; border: 1px solid #e2e8f0; color: #4a5568; box-shadow: none !important; }
                        .sm-footer-btn:hover { background: #f8fafc; border-color: #cbd5e0; }
                        .sm-footer-btn-primary { background: #f1f5f9; color: var(--sm-dark-color) !important; border: 1px solid #e2e8f0; }
                        .sm-footer-btn-primary:hover { background: #e2e8f0; }
                        .sm-password-toggle { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; transition: 0.2s; z-index: 5; }
                        .sm-password-toggle:hover { color: var(--sm-primary-color); }
                    </style>
                    <?php
                    $args = array(
                        'echo' => false,
                        'redirect' => home_url('/dashboard'),
                        'form_id' => 'sm_login_form',
                        'label_remember' => 'تذكرني',
                        'label_log_in' => 'دخول النظام',
                        'remember' => true
                    );
                    $form = wp_login_form($args);
                    $form = str_replace('name="log"', 'name="log" placeholder="الرقم القومي أو اسم المستخدم"', $form);
                    $form = str_replace('name="pwd"', 'name="pwd" id="sm_login_pwd" placeholder="كلمة المرور"', $form);
                    // Targeted replacement using regex to avoid duplication and handle different tag endings
                    $form = preg_replace('/(<input[^>]+id="sm_login_pwd"[^>]*>)/', '$1<span class="dashicons dashicons-visibility sm-password-toggle" onclick="smTogglePass(\'sm_login_pwd\', this)"></span>', $form);
                    $form = preg_replace('/(<input[^>]+name="log"[^>]+>)\s*<span[^>]+><\/span>/', '$1', $form);
                    echo $form;
                    ?>
                    <div class="sm-login-footer-links">
                        <a href="javascript:void(0)" onclick="smToggleRegistration()" class="sm-footer-btn sm-footer-btn-primary"><b>عضوية جديدة</b></a>
                        <a href="javascript:void(0)" onclick="smToggleActivation()" class="sm-footer-btn"><b>تفعيل الحساب</b></a>
                        <a href="javascript:void(0)" onclick="smToggleRecovery()" style="grid-column: span 2; color: #64748b; font-size: 12px; text-decoration: none; text-align: center; margin-top: 10px;">نسيت كلمة المرور؟</a>
                    </div>
                </div>
            </div>
        </div>

        <?php
        include SM_PLUGIN_DIR . 'includes/modules/auth/login-modals.php';
        return ob_get_clean();
    }

    public static function shortcode_login_page() {
        return ''; // Integrated header removed per user request
    }

    public static function ajax_forgot_password_otp() {
        try {
            if (isset($_POST['_wpnonce'])) {
                check_ajax_referer('sm_registration_nonce', '_wpnonce');
            } else {
                check_ajax_referer('sm_registration_nonce', 'nonce');
            }
            $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member || !$member->wp_user_id) {
            wp_send_json_error(['message' => 'الرقم القومي غير مسجل في النظام']);
        }
        $user = get_userdata($member->wp_user_id);
        if (!$user) {
            wp_send_json_error(['message' => 'بيانات الحساب غير موجودة']);
        }
        $otp = sprintf("%06d", mt_rand(1, 999999));
        update_user_meta($user->ID, 'sm_recovery_otp', $otp);
        update_user_meta($user->ID, 'sm_recovery_otp_time', time());
        update_user_meta($user->ID, 'sm_recovery_otp_used', 0);
        $syndicate = SM_Settings::get_syndicate_info();
        $subject = "رمز استعادة كلمة المرور - " . $syndicate['syndicate_name'];
        $message = "عزيزي العضو " . $member->name . ",\n\n" . "رمز التحقق الخاص بك هو: " . $otp . "\n" . "هذا الرمز صالح لمدة 10 دقائق فقط ولمرة واحدة.\n\n" . "إذا لم تطلب هذا الرمز، يرجى تجاهل هذه الرسالة.\n";
            wp_mail($member->email, $subject, $message);
            wp_send_json_success('تم إرسال رمز التحقق إلى بريدك الإلكتروني المسجل');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error sending OTP: ' . $e->getMessage()]);
        }
    }

    public static function ajax_reset_password_otp() {
        try {
            if (isset($_POST['_wpnonce'])) {
                check_ajax_referer('sm_registration_nonce', '_wpnonce');
            } else {
                check_ajax_referer('sm_registration_nonce', 'nonce');
            }
            $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $otp = sanitize_text_field($_POST['otp'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';
        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member || !$member->wp_user_id) {
            wp_send_json_error(['message' => 'بيانات غير صحيحة']);
        }
        $user_id = $member->wp_user_id;
        $saved_otp = get_user_meta($user_id, 'sm_recovery_otp', true);
        $otp_time = get_user_meta($user_id, 'sm_recovery_otp_time', true);
        $otp_used = get_user_meta($user_id, 'sm_recovery_otp_used', true);
        if ($otp_used || $saved_otp !== $otp || (time() - $otp_time) > 600) {
            update_user_meta($user_id, 'sm_recovery_otp_used', 1);
            wp_send_json_error(['message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية']);
        }
        if (strlen($new_pass) < 10 || !preg_match('/^[a-zA-Z0-9]+$/', $new_pass)) {
            wp_send_json_error(['message' => 'كلمة المرور يجب أن تكون 10 أحرف على الأقل وتتكون من حروف وأرقام فقط بدون رموز']);
        }
            wp_set_password($new_pass, $user_id);
            update_user_meta($user_id, 'sm_recovery_otp_used', 1);
            wp_send_json_success('تمت إعادة تعيين كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error resetting password: ' . $e->getMessage()]);
        }
    }

    public static function ajax_activate_account_step1() {
        try {
            if (isset($_POST['_wpnonce'])) {
                check_ajax_referer('sm_registration_nonce', '_wpnonce');
            } else {
                check_ajax_referer('sm_registration_nonce', 'nonce');
            }
            $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $membership_number = sanitize_text_field($_POST['membership_number'] ?? '');
        $branch_slug = sanitize_text_field($_POST['branch'] ?? '');

        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member) {
            wp_send_json_error(['message' => 'الرقم القومي غير موجود في السجلات المهنية.']);
        }
        if ($member->membership_number !== $membership_number) {
            wp_send_json_error(['message' => 'بيانات التحقق غير صحيحة، يرجى مراجعة رقم القيد.']);
        }
        if ($member->governorate !== $branch_slug) {
            wp_send_json_error(['message' => 'العضو غير مسجل في الفرع المختار. يرجى اختيار الفرع الصحيح.']);
        }

            wp_send_json_success('تم التحقق بنجاح. يرجى إكمال بيانات التواصل');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error in activation step 1: ' . $e->getMessage()]);
        }
    }

    public static function ajax_activate_account_final() {
        try {
            if (isset($_POST['_wpnonce'])) {
                check_ajax_referer('sm_registration_nonce', '_wpnonce');
            } else {
                check_ajax_referer('sm_registration_nonce', 'nonce');
            }
            $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $membership_number = sanitize_text_field($_POST['membership_number'] ?? '');
        $new_email = sanitize_email($_POST['email'] ?? '');
        $new_phone = sanitize_text_field($_POST['phone'] ?? '');
        $new_pass = $_POST['password'] ?? '';
        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member || $member->membership_number !== $membership_number) {
            wp_send_json_error(['message' => 'فشل التحقق من الهوية']);
        }
        if (strlen($new_pass) < 10 || !preg_match('/^[a-zA-Z0-9]+$/', $new_pass)) {
            wp_send_json_error(['message' => 'كلمة المرور يجب أن تكون 10 أحرف على الأقل وتتكون من حروف وأرقام فقط']);
        }
        if (!is_email($new_email)) {
            wp_send_json_error(['message' => 'بريد إلكتروني غير صحيح']);
        }
        SM_DB::update_member($member->id, ['email' => $new_email, 'phone' => $new_phone]);
        if ($member->wp_user_id) {
            wp_update_user(['ID' => $member->wp_user_id, 'user_email' => $new_email, 'user_pass' => $new_pass]);
            update_user_meta($member->wp_user_id, 'sm_phone', $new_phone);
        }
            wp_send_json_success('تم تفعيل الحساب بنجاح. يمكنك الآن تسجيل الدخول');
            SM_Notifications::send_template_notification($member->id, 'welcome_activation');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error activating account: ' . $e->getMessage()]);
        }
    }

    public static function ajax_submit_membership_request() {
        try {
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_registration_nonce', 'nonce');
            } else {
                check_ajax_referer('sm_registration_nonce', '_wpnonce');
            }
        $nid = sanitize_text_field($_POST['national_id']);
        if (SM_DB::member_exists($nid)) {
            wp_send_json_error(['message' => 'عذراً، هذا الرقم القومي مسجل مسبقاً في النظام كعضو مفعل.']);
        }
        $exists_request = SM_DB::get_membership_request_by_national_id($nid);
        if ($exists_request) {
            wp_send_json_error(['message' => 'عذراً، يوجد طلب عضوية قيد المراجعة بهذا الرقم القومي.']);
        }

        $res = SM_DB::add_membership_request($_POST);
            if ($res) {
                $tracking_code = 'REG-' . date('Ymd') . $res;
                wp_send_json_success($tracking_code);
            } else {
                wp_send_json_error(['message' => 'فشل في إرسال الطلب']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error submitting request: ' . $e->getMessage()]);
        }
    }

    public static function ajax_clear_site_cache() {
        if (!current_user_can('manage_options') && !current_user_can('sm_manage_system')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        check_ajax_referer('sm_admin_action', 'nonce');

        // 1. Clear all WordPress Transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");

        // 2. Clear common caching plugins
        if (function_exists('wp_cache_flush')) { wp_cache_flush(); }
        if (function_exists('w3tc_flush_all')) { w3tc_flush_all(); }
        if (class_exists('WpFastestCache')) { $wpfc = new WpFastestCache(); $wpfc->deleteCache(); }
        if (function_exists('rocket_clean_domain')) { rocket_clean_domain(); }
        if (class_exists('AutoptimizeCache')) { AutoptimizeCache::clearall(); }
        if (function_exists('sg_cachepress_purge_cache')) { sg_cachepress_purge_cache(); }

        SM_Logger::log('مسح الكاش', "تم إجراء مسح شامل لكاش الموقع");
        wp_send_json_success('Site cache cleared successfully');
    }

    public static function ajax_acknowledge_alert_ajax() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'يجب تسجيل الدخول أولاً']);
            }
            check_ajax_referer('sm_profile_action', 'nonce');
            $user_id = get_current_user_id();
            $alert_id = intval($_POST['alert_id'] ?? 0);
            if (!$alert_id) wp_send_json_error(['message' => 'ID التنبيه غير صحيح']);

            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'sm_alert_views', [
                'alert_id' => $alert_id,
                'user_id' => $user_id,
                'acknowledged' => 1,
                'created_at' => current_time('mysql')
            ]);
            wp_send_json_success('تم تأكيد استلام التنبيه');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_update_profile() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'يجب تسجيل الدخول أولاً']);
            }
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_profile_action', 'nonce');
            } else {
                check_ajax_referer('sm_profile_action', '_wpnonce');
            }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $is_member = in_array('sm_member', (array)$user->roles);

        $data = ['ID' => $user_id];
        $email = sanitize_email($_POST['user_email'] ?? '');
        $pass = $_POST['user_pass'] ?? '';

        if (!empty($email)) {
            $data['user_email'] = $email;
        }

        if (!empty($pass)) {
            if (strlen($pass) < 10) {
                wp_send_json_error(['message' => 'كلمة المرور يجب أن تكون 10 أحرف على الأقل']);
            }
            $data['user_pass'] = $pass;
        }

        $res = wp_update_user($data);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }

        if (!empty($email)) {
            $member = SM_DB::get_member_by_wp_user_id($user_id);
            if ($member) {
                SM_DB::update_member($member->id, ['email' => $email]);
            }
        }

            SM_Logger::log('تحديث الملف الشخصي', "قام المستخدم بتحديث بياناته الشخصية");
            wp_send_json_success('تم تحديث البيانات بنجاح');
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error updating profile: ' . $e->getMessage()]);
        }
    }

    public static function ajax_track_membership_request() {
        try {
            if (isset($_POST['_wpnonce'])) {
                check_ajax_referer('sm_registration_nonce', '_wpnonce');
            } else {
                check_ajax_referer('sm_registration_nonce', 'nonce');
            }
            $req = SM_DB::get_membership_request_by_national_id(sanitize_text_field($_POST['national_id']));
            if (!$req) {
                wp_send_json_error(['message' => 'لم يتم العثور على طلب بهذا الرقم القومي']);
            }
            $map = [
                'Pending Payment Verification' => 'قيد مراجعة الدفع',
                'approved' => 'تم القبول',
                'rejected' => 'مرفوض',
                'pending' => 'قيد المراجعة'
            ];
            wp_send_json_success([
                'status' => $map[$req->status] ?? $req->status,
                'current_stage' => $req->current_stage,
                'rejection_reason' => $req->notes ?? ''
            ]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
