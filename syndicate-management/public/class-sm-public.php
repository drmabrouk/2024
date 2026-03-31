<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Public {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function hide_admin_bar_for_non_admins($show) {
        return current_user_can('manage_options') ? $show : false;
    }

    public function restrict_admin_access() {
        if (is_user_logged_in()) {
            if (get_user_meta(get_current_user_id(), 'sm_account_status', true) === 'restricted') {
                wp_logout();
                wp_redirect(home_url('/sm-login?login=failed'));
                exit;
            }
        }
        // Site Manager (administrator) is the only role allowed in WP Core Admin
        if (is_admin() && !defined('DOING_AJAX') && !current_user_can('manage_options')) {
            $user = wp_get_current_user();
            $roles = (array)$user->roles;
            $is_member = in_array('sm_member', $roles);
            wp_redirect(home_url($is_member ? '/my-account' : '/dashboard'));
            exit;
        }
    }

    public function handle_frontend_redirection() {
        if (!is_user_logged_in() || is_admin()) return;

        $user = wp_get_current_user();
        $roles = (array)$user->roles;
        $is_officer = in_array('sm_general_officer', $roles) || in_array('sm_branch_officer', $roles) || in_array('administrator', $roles);
        $is_member = in_array('sm_member', $roles);

        global $wp;
        $current_path = trim($wp->request, '/');

        if ($is_officer) {
            if ($current_path === 'my-account' || $current_path === 'sm-admin') {
                wp_redirect(add_query_arg($_GET, home_url('/dashboard')));
                exit;
            }
        } elseif ($is_member) {
            if ($current_path === 'dashboard' || $current_path === 'sm-admin') {
                wp_redirect(add_query_arg($_GET, home_url('/my-account')));
                exit;
            }
        }
    }

    public function custom_login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('administrator', $user->roles) || in_array('sm_general_officer', $user->roles) || in_array('sm_branch_officer', $user->roles)) {
                return home_url('/dashboard');
            } else {
                return home_url('/my-account');
            }
        }
        return $redirect_to;
    }

    public function enqueue_styles() {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";', 'before');
        wp_enqueue_style('dashicons');
        wp_enqueue_style('google-font-rubik', 'https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;700;800;900&display=swap', array(), null);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
        wp_enqueue_style($this->plugin_name, SM_PLUGIN_URL . 'assets/css/sm-public.css', array('dashicons'), $this->version, 'all');

        $appearance = SM_Settings::get_appearance();
        $custom_css = "
            :root {
                --sm-primary-color: {$appearance['primary_color']};
                --sm-secondary-color: {$appearance['secondary_color']};
                --sm-accent-color: {$appearance['accent_color']};
                --sm-dark-color: {$appearance['dark_color']};
                --sm-radius: {$appearance['border_radius']};
            }
            .sm-content-wrapper, .sm-admin-dashboard, .sm-container,
            .sm-content-wrapper *:not(.dashicons), .sm-admin-dashboard *:not(.dashicons), .sm-container *:not(.dashicons) {
                font-family: 'Rubik', sans-serif !important;
            }
            .sm-admin-dashboard { font-size: {$appearance['font_size']}; }
        ";
        wp_add_inline_style($this->plugin_name, $custom_css);
    }

    public function register_shortcodes() {
        SM_Auth::register_shortcodes();
        SM_Service_Manager::register_shortcodes();

        add_shortcode('sm_admin', array($this, 'shortcode_admin_dashboard'));
        add_shortcode('test', array($this, 'shortcode_test_system'));
        add_shortcode('verify', array($this, 'shortcode_verify'));
        add_shortcode('sm_branches', array($this, 'shortcode_branches'));
        add_shortcode('contact', array($this, 'shortcode_contact'));
        add_shortcode('sm_cover', array($this, 'shortcode_cover_box'));
        add_shortcode('sm_cover_2', array($this, 'shortcode_cover_2'));

        add_filter('authenticate', array($this, 'custom_authenticate'), 20, 3);
        add_filter('auth_cookie_expiration', array($this, 'custom_auth_cookie_expiration'), 10, 3);
    }

    public function custom_auth_cookie_expiration($exp, $uid, $rem) {
        return $rem ? 30 * DAY_IN_SECONDS : $exp;
    }

    public function custom_authenticate($user, $username, $password) {
        if (empty($username) || empty($password)) {
            return $user;
        }
        if ($user instanceof WP_User) {
            return $user;
        }

        $code_query = new WP_User_Query([
            'meta_query' => [['key' => 'sm_syndicateMemberIdAttr', 'value' => $username]],
            'number' => 1
        ]);
        $found = $code_query->get_results();
        if (!empty($found)) {
            $u = $found[0];
            if (wp_check_password($password, $u->user_pass, $u->ID)) {
                return $u;
            }
        }

        global $wpdb;
        $member_wp_id = $wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM {$wpdb->prefix}sm_members WHERE national_id = %s", $username));
        if ($member_wp_id) {
            $u = get_userdata($member_wp_id);
            if ($u && wp_check_password($password, $u->user_pass, $u->ID)) {
                return $u;
            }
        }
        return $user;
    }

    public function shortcode_verify() {
        ob_start();
        include SM_PLUGIN_DIR . 'templates/public-verification.php';
        return ob_get_clean();
    }

    public function shortcode_test_system() {
        if (!is_user_logged_in()) return SM_Auth::shortcode_login();
        ob_start();
        include SM_PLUGIN_DIR . 'templates/public-test-system.php';
        return ob_get_clean();
    }

    public function shortcode_branches() {
        $branches = SM_DB::get_branches_data();
        ob_start();
        ?>
        <div class="sm-public-page" dir="rtl">
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:30px; padding:50px 40px; margin-bottom:50px; text-align:center; box-shadow:0 10px 15px -3px rgba(0,0,0,0.05);">
                <div style="display:inline-flex; align-items:center; justify-content:center; width:60px; height:60px; background:rgba(246, 48, 73, 0.1); border-radius:20px; margin-bottom:20px;">
                    <span class="dashicons dashicons-networking" style="font-size:30px; width:30px; height:30px; color:var(--sm-primary-color);"></span>
                </div>
                <h2 style="margin:0; font-weight:900; font-size:2.5em; color:var(--sm-dark-color);">الفروع واللجان النقابية</h2>
                <p style="color:#64748b; margin-top:10px;">استكشف فروع النقابة وتواصل مع الإدارة المختصة في منطقتك</p>
                <div style="max-width:500px; margin:30px auto 0; position:relative;">
                    <input type="text" id="sm_branch_search" placeholder="ابحث عن فرع محدد..." style="width:100%; padding:15px 45px 15px 20px; border-radius:15px; border:1px solid #e2e8f0; font-family:'Rubik',sans-serif; outline:none;" oninput="smFilterBranchesPublic(this.value)">
                    <span class="dashicons dashicons-search" style="position:absolute; right:15px; top:15px; color:#94a3b8;"></span>
                </div>
            </div>
            <div id="sm-branches-grid-public" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(350px, 1fr)); gap:30px;">
                <?php if(empty($branches)): ?>
                    <div style="grid-column:1/-1; text-align:center; padding:50px; background:#fff; border-radius:20px; border:1px dashed #cbd5e0;">
                        <p style="color:#718096; margin:0;">لا توجد فروع مسجلة حالياً.</p>
                    </div>
                <?php else: foreach($branches as $b): ?>
                    <div class="sm-branch-card-public" data-name="<?php echo esc_attr($b->name); ?>" style="background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:30px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); transition:0.3s; position:relative; display:flex; flex-direction:column;">
                        <div style="display:flex; align-items:center; gap:20px; margin-bottom:20px;">
                            <div style="width:50px; height:50px; background:var(--sm-primary-color); border-radius:15px; display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0;">
                                <span class="dashicons dashicons-location"></span>
                            </div>
                            <div style="flex:1;">
                                <h3 style="margin:0; font-weight:800; color:var(--sm-dark-color); font-size:1.4em;"><?php echo esc_html($b->name); ?></h3>
                                <div style="font-size:12px; color:#94a3b8; margin-top:4px;">كود الفرع: <?php echo esc_html($b->slug); ?></div>
                            </div>
                        </div>

                        <div style="margin-bottom:20px; font-size:14px; color:#64748b; line-height:1.6; min-height:45px;">
                            <?php echo esc_html(mb_strimwidth($b->address, 0, 100, "...")); ?>
                        </div>

                        <button onclick="smToggleBranchDetails(this)" class="sm-btn sm-btn-outline" style="width:100%; border-radius:12px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px;">
                            عرض التفاصيل <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>

                        <div class="sm-branch-details-expanded" style="display:none; margin-top:25px; padding-top:25px; border-top:1px solid #f1f5f9; animation: smFadeIn 0.3s ease;">
                            <div style="display:grid; gap:15px;">
                                <div style="background:#f8fafc; padding:15px; border-radius:12px;">
                                    <div style="font-size:11px; color:#94a3b8; margin-bottom:4px;">مدير الفرع</div>
                                    <div style="font-weight:700; color:var(--sm-dark-color);"><?php echo esc_html($b->manager ?: 'غير محدد'); ?></div>
                                </div>
                                <div style="display:flex; align-items:center; gap:12px; font-size:13px; color:#4a5568;">
                                    <span class="dashicons dashicons-phone" style="color:var(--sm-primary-color); font-size:18px;"></span>
                                    <strong>الهاتف:</strong> <?php echo esc_html($b->phone ?: '---'); ?>
                                </div>
                                <div style="display:flex; align-items:center; gap:12px; font-size:13px; color:#4a5568;">
                                    <span class="dashicons dashicons-email" style="color:var(--sm-primary-color); font-size:18px;"></span>
                                    <strong>البريد:</strong> <?php echo esc_html($b->email ?: '---'); ?>
                                </div>
                                <div style="display:flex; align-items:start; gap:12px; font-size:13px; color:#4a5568;">
                                    <span class="dashicons dashicons-admin-home" style="color:var(--sm-primary-color); font-size:18px;"></span>
                                    <div><strong>العنوان بالكامل:</strong><br><span style="display:inline-block; margin-top:4px;"><?php echo esc_html($b->address); ?></span></div>
                                </div>
                                <?php if($b->description): ?>
                                    <div style="font-size:12px; color:#718096; font-style:italic; margin-top:10px; border-top:1px dashed #e2e8f0; padding-top:10px;">
                                        <?php echo nl2br(esc_html($b->description)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <script>
        function smFilterBranchesPublic(val) {
            const cards = document.querySelectorAll('.sm-branch-card-public');
            const search = val.trim().toLowerCase();
            cards.forEach(c => {
                const name = c.dataset.name.toLowerCase();
                c.style.display = name.includes(search) ? 'flex' : 'none';
            });
        }
        function smToggleBranchDetails(btn) {
            const card = btn.closest('.sm-branch-card-public');
            const details = card.querySelector('.sm-branch-details-expanded');
            const isVisible = details.style.display === 'block';

            // Close all others optional? Let's keep it simple first

            details.style.display = isVisible ? 'none' : 'block';
            btn.innerHTML = isVisible ?
                'عرض التفاصيل <span class="dashicons dashicons-arrow-down-alt2"></span>' :
                'إخفاء التفاصيل <span class="dashicons dashicons-arrow-up-alt2"></span>';

            if (!isVisible) {
                btn.style.background = 'var(--sm-dark-color)';
                btn.style.color = '#fff';
            } else {
                btn.style.background = 'transparent';
                btn.style.color = 'inherit';
            }
        }
        </script>
        <?php
        return ob_get_clean();
    }

    public function append_cover_v2_to_homepage($content) {
        if (is_front_page() && is_main_query()) {
            $content .= do_shortcode('[sm_cover_2]');
        }
        return $content;
    }

    public function shortcode_cover_2() {
        ob_start();
        ?>
        <div class="sm-cover-box sm-cover-v2" dir="rtl" style="position:relative; width:100%; height:400px; border-radius:25px; overflow:hidden; margin:40px 0; background: linear-gradient(135deg, var(--sm-primary-color) 0%, var(--sm-dark-color) 100%); display:flex; align-items:center; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
            <div style="position:absolute; top:-50px; right:-50px; width:200px; height:200px; background:rgba(255,255,255,0.05); border-radius:50%;"></div>
            <div style="position:absolute; bottom:-80px; left:10%; width:300px; height:300px; background:rgba(255,255,255,0.03); border-radius:50%;"></div>

            <div class="sm-cover-content" style="position:relative; z-index:10; padding:0 60px; color:#fff; width:100%;">
                <div style="display:inline-flex; align-items:center; justify-content:center; width:60px; height:60px; background:rgba(255,255,255,0.1); border-radius:20px; margin-bottom:25px; backdrop-filter:blur(10px);">
                    <span class="dashicons dashicons-shield-check" style="font-size:30px; width:30px; height:30px; color:#fff;"></span>
                </div>
                <h2 style="font-size:2.4em; font-weight:900; margin:0; color:#fff; line-height:1.2;">بوابة التحقق الرقمية الموحدة</h2>
                <p style="font-size:18px; font-weight:400; margin:15px 0 35px 0; color:rgba(255,255,255,0.8); max-width:700px; line-height:1.6;">تأكد من صحة بيانات العضوية، التراخيص المهنية، وتصاريح المنشآت بشكل فوري عبر البوابة الرسمية للنقابة لضمان الشفافية والموثوقية.</p>
                <div style="display:flex; gap:15px;">
                    <a href="<?php echo home_url('/verify'); ?>" class="sm-btn-cover" style="height:55px; padding:0 40px; font-weight:800; border-radius:15px; font-size:16px; display:flex; align-items:center; background:#fff; color:var(--sm-primary-color) !important; text-decoration:none !important; transition:0.3s transform;">
                        الدخول لبوابة التحقق
                        <span class="dashicons dashicons-arrow-left-alt2" style="margin-right:10px;"></span>
                    </a>
                </div>
            </div>

            <style>
                .sm-cover-v2 .sm-btn-cover:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
                @media (max-width: 768px) {
                    .sm-cover-v2 { height: auto !important; padding: 60px 0 !important; border-radius: 20px !important; }
                    .sm-cover-v2 .sm-cover-content { padding: 0 30px !important; text-align: center !important; }
                    .sm-cover-v2 .sm-cover-content div { justify-content: center !important; margin-left:auto; margin-right:auto; }
                    .sm-cover-v2 h2 { font-size: 1.8em !important; }
                    .sm-cover-v2 p { font-size: 15px !important; }
                    .sm-cover-v2 .sm-btn-cover { width:100%; justify-content:center; height:50px !important; }
                }
            </style>
        </div>
        <?php
        return ob_get_clean();
    }

    public function shortcode_admin_dashboard() {
        if (!is_user_logged_in()) {
            return SM_Auth::shortcode_login();
        }
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        $active_tab = isset($_GET['sm_tab']) ? sanitize_text_field($_GET['sm_tab']) : 'summary';
        $stats = SM_DB::get_statistics();

        ob_start();
        include SM_PLUGIN_DIR . 'templates/public-admin-panel.php';
        return ob_get_clean();
    }

    public function shortcode_contact() {
        ob_start();
        ?>
        <div class="sm-contact-wrapper" dir="rtl" style="max-width: 800px; margin: 60px auto; background: #fff; padding: 40px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">
            <div style="text-align: center; margin-bottom: 35px;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; background: rgba(246, 48, 73, 0.1); border-radius: 20px; margin-bottom: 20px;">
                    <span class="dashicons dashicons-email-alt" style="font-size: 30px; width: 30px; height: 30px; color: var(--sm-primary-color);"></span>
                </div>
                <h2 style="margin: 0; font-weight: 900; font-size: 2em;">تواصل مع الإدارة</h2>
            </div>
            <form id="sm-public-contact-form">
                <?php wp_nonce_field('sm_contact_action', 'nonce'); ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="sm-form-group"><input type="text" name="name" class="sm-input" required placeholder="الاسم الكامل"></div>
                    <div class="sm-form-group"><input type="text" name="phone" class="sm-input" required placeholder="رقم الهاتف"></div>
                </div>
                <div class="sm-form-group" style="margin-bottom: 20px;">
                    <input type="email" name="email" class="sm-input" required placeholder="البريد الإلكتروني">
                </div>
                <div class="sm-form-group" style="margin-bottom: 20px;">
                    <input type="text" name="subject" class="sm-input" required placeholder="عنوان الرسالة">
                </div>
                <div class="sm-form-group" style="margin-bottom: 30px;">
                    <textarea name="message" class="sm-textarea" rows="6" required placeholder="كيف يمكننا مساعدتك؟"></textarea>
                </div>
                <button type="submit" class="sm-btn" style="width: 100%; height: 55px; font-weight: 800;">إرسال الرسالة الآن</button>
            </form>
            <div id="sm-contact-success" style="display: none; text-align: center; padding: 40px 0;">
                <div style="font-size: 60px; margin-bottom: 20px;">✅</div>
                <h3 style="font-weight: 900;">تم إرسال رسالتك بنجاح!</h3>
                <button onclick="location.reload()" class="sm-btn" style="margin-top: 30px; width: auto; padding: 0 50px;">إرسال رسالة أخرى</button>
            </div>
        </div>
        <script>
        document.getElementById('sm-public-contact-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const btn = form.querySelector('button[type="submit"]');
            const action = 'sm_submit_contact_form';
            const fd = new FormData(form);
            fd.append('action', action);
            btn.disabled = true;
            btn.innerText = 'جاري الإرسال...';
            fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    form.style.display = 'none';
                    document.getElementById('sm-contact-success').style.display = 'block';
                } else {
                    if (typeof smHandleAjaxError === 'function') {
                        smHandleAjaxError(res.data, 'فشل إرسال الرسالة');
                    } else if (typeof smShowNotification === 'function') {
                        smShowNotification('خطأ: ' + (res.data.message || res.data), true);
                    } else {
                        if (typeof smShowNotification === 'function') smShowNotification('خطأ: ' + (res.data.message || res.data), true);
                        else alert('خطأ: ' + (res.data.message || res.data));
                    }
                    btn.disabled = false;
                    btn.innerText = 'إرسال الرسالة الآن';
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function login_failed($username) {
        $ref = wp_get_referer();
        if ($ref && !strstr($ref, 'wp-login') && !strstr($ref, 'wp-admin')) {
            wp_redirect(add_query_arg('login', 'failed', $ref));
            exit;
        }
    }

    public function log_successful_login($user_login, $user) {
        SM_Logger::log('تسجيل دخول', "المستخدم: $user_login");
    }

    public function inject_global_alerts() {
        if (!is_user_logged_in()) {
            return;
        }
        $alerts = SM_DB::get_active_alerts_for_user(get_current_user_id());
        if (empty($alerts)) {
            return;
        }
        foreach ($alerts as $a) {
            $bg = $a->severity === 'critical' ? '#fff5f5' : ($a->severity === 'warning' ? '#fffaf0' : '#fff');
            $border = $a->severity === 'critical' ? '#feb2b2' : ($a->severity === 'warning' ? '#f6ad55' : '#e2e8f0');
            ?>
            <div id="sm-global-alert-<?php echo $a->id; ?>" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; display:flex; align-items:center; justify-content:center;">
                <div style="background:<?php echo $bg; ?>; border:2px solid <?php echo $border; ?>; border-radius:15px; width:90%; max-width:500px; padding:30px; text-align:center; direction:rtl;">
                    <h2 style="margin:0 0 15px 0; font-weight:800;"><?php echo esc_html($a->title); ?></h2>
                    <div style="margin-bottom:25px;"><?php echo wp_kses_post($a->message); ?></div>
                    <button onclick="smAcknowledgeAlert(<?php echo $a->id; ?>)" class="sm-btn" style="width:100%;"><?php echo $a->must_acknowledge ? 'إقرار واستمرار' : 'إغلاق'; ?></button>
                </div>
            </div>
            <?php
        }
        ?>
        <script>
        function smAcknowledgeAlert(aid) {
            const action = 'sm_acknowledge_alert';
            const fd = new FormData();
            fd.append('action', action);
            fd.append('alert_id', aid);
            fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
            fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                document.getElementById('sm-global-alert-' + aid).remove();
            });
        }
        </script>
        <?php
    }

    public function handle_form_submission() {
        if (isset($_POST['sm_save_appearance'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            $data = SM_Settings::get_appearance();
            foreach ($data as $k => $v) {
                if (isset($_POST[$k])) {
                    $data[$k] = sanitize_text_field($_POST[$k]);
                }
            }
            SM_Settings::save_appearance($data);
            wp_redirect(add_query_arg('sm_tab', 'global-settings', wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_settings_unified'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            $info = SM_Settings::get_syndicate_info();
            $fields = [
                'syndicate_name' => 'syndicate_name',
                'syndicate_officer_name' => 'syndicate_officer_name',
                'syndicate_phone' => 'phone',
                'syndicate_email' => 'email',
                'syndicate_postal_code' => 'postal_code',
                'syndicate_logo' => 'syndicate_logo',
                'syndicate_address' => 'address',
                'syndicate_map_link' => 'map_link',
                'syndicate_extra_details' => 'extra_details'
            ];

            foreach($fields as $post_key => $info_key) {
                if(isset($_POST[$post_key])) {
                    $info[$info_key] = sanitize_text_field($_POST[$post_key]);
                }
            }
            SM_Settings::save_syndicate_info($info);

            // Save Appearance (Colors & Design)
            SM_Settings::save_appearance([
                'primary_color' => sanitize_hex_color($_POST['primary_color']),
                'secondary_color' => sanitize_hex_color($_POST['secondary_color']),
                'accent_color' => sanitize_hex_color($_POST['accent_color']),
                'dark_color' => sanitize_hex_color($_POST['dark_color']),
                'bg_color' => sanitize_hex_color($_POST['bg_color']),
                'sidebar_bg_color' => sanitize_hex_color($_POST['sidebar_bg_color']),
                'font_color' => sanitize_hex_color($_POST['font_color']),
                'border_color' => sanitize_hex_color($_POST['border_color']),
                'font_size' => sanitize_text_field($_POST['font_size']),
                'font_weight' => sanitize_text_field($_POST['font_weight']),
                'line_spacing' => sanitize_text_field($_POST['line_spacing'])
            ]);

            $labels = SM_Settings::get_labels();
            foreach($labels as $key => $val) {
                if (isset($_POST[$key])) {
                    $labels[$key] = sanitize_text_field($_POST[$key]);
                }
            }
            SM_Settings::save_labels($labels);

            wp_redirect(add_query_arg(['sm_tab' => 'global-settings', 'sub' => 'init', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_verify_settings'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            update_option('sm_verify_title', sanitize_text_field($_POST['sm_verify_title']));
            update_option('sm_verify_desc', sanitize_text_field($_POST['sm_verify_desc']));
            update_option('sm_verify_help', sanitize_text_field($_POST['sm_verify_help']));
            update_option('sm_verify_show_membership', intval($_POST['sm_verify_show_membership']));
            update_option('sm_verify_show_practice', intval($_POST['sm_verify_show_practice']));
            update_option('sm_verify_show_facility', intval($_POST['sm_verify_show_facility']));
            update_option('sm_verify_accent_color', sanitize_hex_color($_POST['sm_verify_accent_color']));
            update_option('sm_verify_success_msg', sanitize_text_field($_POST['sm_verify_success_msg']));

            wp_redirect(add_query_arg(['sm_tab' => 'advanced-settings', 'sub' => 'verification', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_role_permissions'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            if (!current_user_can('sm_manage_system')) wp_die('Unauthorized');

            $perms = SM_Settings::get_role_permissions();
            foreach (['sm_general_officer', 'sm_branch_officer', 'sm_member'] as $role) {
                $perms[$role]['modules'] = isset($_POST['perms'][$role]['modules']) ? array_map('sanitize_text_field', $_POST['perms'][$role]['modules']) : [];
                $perms[$role]['actions'] = isset($_POST['perms'][$role]['actions']) ? array_map('sanitize_text_field', $_POST['perms'][$role]['actions']) : [];
            }
            SM_Settings::save_role_permissions($perms);
            wp_redirect(add_query_arg(['sm_tab' => 'advanced-settings', 'sub' => 'permissions', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_cover_settings'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            $data = SM_Settings::get_cover_settings();
            $data['welcome_msg'] = sanitize_text_field($_POST['welcome_msg']);
            $data['welcome_sub_msg'] = sanitize_text_field($_POST['welcome_sub_msg']);
            $data['login_btn_label'] = sanitize_text_field($_POST['login_btn_label']);
            $data['services_btn_label'] = sanitize_text_field($_POST['services_btn_label']);
            $data['filter_intensity'] = sanitize_text_field($_POST['filter_intensity']);
            $data['filter_color'] = sanitize_text_field($_POST['filter_color']);
            $data['slider_interval'] = sanitize_text_field($_POST['slider_interval']);

            // Handle multi-image list
            $images = isset($_POST['cover_images']) ? array_map('esc_url_raw', $_POST['cover_images']) : [];
            $data['images'] = array_values(array_filter($images));

            SM_Settings::save_cover_settings($data);
            wp_redirect(add_query_arg(['sm_tab' => 'global-settings', 'sub' => 'cover', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }
    }

    public static function ajax_refresh_dashboard() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        wp_send_json_success(array('stats' => SM_DB::get_statistics()));
    }

    public function shortcode_cover_box() {
        $settings = SM_Settings::get_cover_settings();
        $images = $settings['images'] ?: [SM_PLUGIN_URL . 'assets/images/default-cover.jpg'];
        $is_slider = count($images) > 1;

        ob_start();
        ?>
        <div class="sm-cover-box" dir="rtl" style="position:relative; width:100%; height:400px; border-radius:15px; overflow:hidden; margin:0; box-shadow:none;">
            <div class="sm-cover-slider" style="width:100%; height:100%; position:relative;">
                <?php foreach($images as $idx => $img): ?>
                    <div class="sm-cover-slide <?php echo $idx === 0 ? 'active' : ''; ?>" style="position:absolute; top:0; left:0; width:100%; height:100%; background:url('<?php echo esc_url($img); ?>') center/cover no-repeat; opacity:<?php echo $idx === 0 ? '1' : '0'; ?>; transition: opacity 1s ease-in-out; image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges;">
                        <div class="sm-cover-overlay" style="position:absolute; top:0; left:0; width:100%; height:100%; background:<?php echo esc_attr($settings['filter_color']); ?>; backdrop-filter: blur(<?php echo intval($settings['filter_intensity']); ?>px);"></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="sm-cover-content" style="position:absolute; inset:0; display:flex; flex-direction:column; justify-content:center; padding:0 30px; z-index:10; color:#fff;">
                <h1 class="sm-cover-title" style="font-size:1.6em; font-weight:800; margin:0; color:#fff; text-shadow:none;"><?php echo esc_html($settings['welcome_msg']); ?></h1>
                <?php if($settings['welcome_sub_msg']): ?>
                    <p class="sm-cover-desc" style="font-size:14px; font-weight:400; margin:10px 0 20px 0; color:rgba(255,255,255,0.9); text-shadow:none; max-width:600px; line-height:1.5;"><?php echo esc_html($settings['welcome_sub_msg']); ?></p>
                <?php else: ?>
                    <div style="height:15px;"></div>
                <?php endif; ?>
                <div style="display:flex; gap:10px;">
                    <a href="<?php echo is_user_logged_in() ? home_url('/dashboard') : home_url('/sm-login'); ?>" class="sm-btn-cover" style="height:36px; padding:0 20px; font-weight:700; border-radius:8px; font-size:13px; display:flex; align-items:center; background:#fff; color:var(--sm-primary-color) !important; text-decoration:none !important; border:none; box-shadow:none;">
                        <?php echo esc_html($settings['login_btn_label']); ?>
                    </a>
                    <a href="<?php echo home_url('/services'); ?>" class="sm-btn-cover" style="height:36px; padding:0 20px; font-weight:700; border-radius:8px; font-size:13px; display:flex; align-items:center; border:1px solid #fff; color:#fff !important; background:rgba(255,255,255,0.15); text-decoration:none !important; backdrop-filter:blur(5px); box-shadow:none;">
                        <?php echo esc_html($settings['services_btn_label']); ?>
                    </a>
                </div>
            </div>

            <style>
                .sm-btn-cover:hover { opacity: 0.9; }
                @media (max-width: 768px) {
                    .sm-cover-box { height: 300px !important; border-radius: 10px !important; }
                    .sm-cover-content { padding: 0 20px !important; align-items: center !important; text-align: center !important; }
                    .sm-cover-title { font-size: 1.4em !important; }
                    .sm-cover-desc { font-size: 13px !important; margin: 10px 0 18px 0 !important; }
                    .sm-btn-cover { height: 36px !important; padding: 0 18px !important; font-size: 13px !important; }
                }
            </style>

            <?php if($is_slider): ?>
            <script>
                (function() {
                    let current = 0;
                    const slides = document.querySelectorAll('.sm-cover-slide');
                    setInterval(() => {
                        slides[current].style.opacity = '0';
                        current = (current + 1) % slides.length;
                        slides[current].style.opacity = '1';
                    }, <?php echo intval($settings['slider_interval'] ?: 5000); ?>);
                })();
            </script>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function ajax_get_user_role() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        $u = get_userdata(intval($_GET['user_id']));
        if ($u) {
            wp_send_json_success([
                'role' => !empty($u->roles) ? $u->roles[0] : '',
                'rank' => get_user_meta($u->ID, 'sm_rank', true),
                'governorate' => get_user_meta($u->ID, 'sm_governorate', true)
            ]);
        } else {
            wp_send_json_error('User not found');
        }
    }


}
