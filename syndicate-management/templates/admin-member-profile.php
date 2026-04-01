<?php if (!defined('ABSPATH')) exit;

/**
 * SYNDICATE MANAGEMENT - Member Profile Template (Refactored V6)
 * Handles both administrator view and member self-service portal.
 */

$member_id = intval($_GET['member_id'] ?? 0);
$member = SM_DB::get_member_by_id($member_id);

if (!$member) {
    echo '<div class="sm-alert sm-alert-danger" style="padding: 20px; background:#fff5f5; color:#c53030; border-radius:12px; border:1px solid #feb2b2; margin: 20px;"><h4>⚠️ خطأ في النظام</h4><p>العضو المطلوب غير موجود في السجلات.</p></div>';
    return;
}

$user = wp_get_current_user();
$is_admin = current_user_can('manage_options');
$is_sys_manager = current_user_can('sm_manage_system');
$is_syndicate_admin = current_user_can('sm_branch_access') && !current_user_can('sm_full_access');
$is_general_officer = current_user_can('sm_full_access') && !current_user_can('sm_manage_system');
$is_member = in_array('sm_member', (array)$user->roles);
$is_restricted = !current_user_can('sm_manage_members');

$db_branches = SM_DB::get_branches_data();

// --- SECURITY & ACCESS CONTROL ---

// 1. IDOR Enforcement: Members can only see their own profile
if ($is_restricted && $member->wp_user_id != $user->ID) {
    echo '<div style="padding: 40px; text-align:center; background:#fff; border-radius:16px; border:1px solid #eee; margin: 20px;">
            <div style="font-size: 50px; margin-bottom: 20px;">🚫</div>
            <h3 style="color:#c53030; font-weight:900;">وصول غير مصرح به</h3>
            <p style="color:#64748b;">عذراً، لا تملك صلاحية استعراض بيانات الأعضاء الآخرين. تم تسجيل هذه المحاولة.</p>
            <a href="' . home_url('/my-account') . '" class="sm-btn" style="width:auto; padding:0 30px;">العودة لملفي الشخصي</a>
          </div>';
    return;
}

// 2. Geographic Enforcement: Branch Officers can only see members in their governorate
if ($is_syndicate_admin) {
    $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
    if ($my_gov && $member->governorate !== $my_gov) {
        echo '<div style="padding: 40px; text-align:center; background:#fff; border-radius:16px; border:1px solid #eee; margin: 20px;">
                <div style="font-size: 50px; margin-bottom: 20px;">📍</div>
                <h3 style="color:#c53030; font-weight:900;">خارج النطاق الجغرافي</h3>
                <p style="color:#64748b;">هذا العضو يتبع لفرع نقابي آخر. لا يسمح لك باستعراض ملفات خارج نطاق إدارتك.</p>
              </div>';
        return;
    }
}

// --- DATA PREPARATION ---
$grades = SM_Settings::get_professional_grades();
$specs = SM_Settings::get_specializations();
$govs = SM_Settings::get_governorates();
$finance = SM_Finance::calculate_member_dues($member);
$acc_status = SM_Finance::get_member_status($member->id);

// NEW: Identify if the member being viewed has an administrative role
$target_user = new WP_User($member->wp_user_id);
$member_is_admin_role = !empty($target_user->roles) && array_intersect($target_user->roles, ['administrator', 'sm_general_officer', 'sm_branch_officer']);
?>

<div class="sm-member-profile-view <?php echo $is_restricted ? 'sm-portal-layout' : ''; ?>" dir="rtl">
    <script>
        const SM_CURRENT_MEMBER_ID = <?php echo $member->id; ?>;
        const SM_MEMBER_IS_ADMIN_ROLE = <?php echo $member_is_admin_role ? 'true' : 'false'; ?>;
    </script>
    <input type="file" id="member-photo-input" style="display:none;" accept="image/*" onchange="smUploadMemberPhoto(<?php echo $member->id; ?>)">


    <?php if ($is_restricted): ?>
    <div class="sm-portal-layout-container" style="display: flex; gap: 30px;">
        <!-- PORTAL NAVIGATION SIDEBAR -->
        <div class="sm-portal-sidebar" style="width: 320px; flex-shrink: 0;">
            <div style="background: #fff; border: 1px solid var(--sm-border-color); border-radius: 20px; padding: 20px; position: sticky; top: 20px; box-shadow: var(--sm-shadow);">
                <div style="padding: 10px 0 25px; border-bottom: 1px solid #f1f5f9; margin-bottom: 20px; text-align: center;">
                    <div style="position: relative; width: 90px; height: 90px; margin: 0 auto 15px; cursor: pointer;" onclick="smTriggerPhotoUpload()" class="sm-sidebar-photo-container">
                        <div style="width: 100%; height: 100%; background: #f8fafc; border-radius: 50%; border: 3px solid var(--sm-primary-color); padding: 4px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                            <img src="<?php echo esc_url($member->photo_url ?: get_avatar_url($user->ID)); ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                        </div>
                        <div class="sm-photo-overlay" style="position: absolute; inset: 0; background: rgba(0,0,0,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; color: white;">
                            <span class="dashicons dashicons-camera" style="font-size: 28px; width: 28px; height: 28px;"></span>
                        </div>
                    </div>
                    <h4 style="margin: 0; font-weight: 900; color: var(--sm-dark-color); font-size: 1.1em;"><?php echo esc_html($member->name); ?></h4>
                    <div style="display: flex; flex-direction: column; gap: 10px; align-items: center; margin-top: 10px;">
                        <div style="display: flex; gap: 5px; flex-wrap: wrap; justify-content: center;">
                            <?php if (!$member_is_admin_role): ?>
                                <span class="sm-badge sm-badge-low" style="font-size: 10px;"><?php echo $grades[$member->professional_grade] ?? $member->professional_grade; ?></span>
                            <?php endif; ?>
                            <span class="sm-badge" style="background: #edf2f7; color: #4a5568; font-size: 10px;"><?php echo esc_html(SM_Settings::get_branch_name($member->governorate)); ?></span>
                        </div>
                        <div style="font-size: 11px; color: #718096; font-weight: 600;">الرقم القومي: <span style="color:var(--sm-dark-color); font-family:monospace;"><?php echo esc_html($member->national_id); ?></span></div>
                    </div>
                </div>

                <nav class="sm-portal-nav" style="display: flex; flex-direction: column; gap: 6px;">
                    <button class="sm-portal-nav-btn sm-active" onclick="smOpenInternalTab('profile-info', this)"><span class="dashicons dashicons-admin-users"></span> <span><?php echo $member_is_admin_role ? 'بيانات الحساب الإداري' : 'بيانات العضوية'; ?></span></button>

                    <?php if (!$member_is_admin_role): ?>
                        <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('license-status-tab', this)"><span class="dashicons dashicons-id-alt"></span> <span>حالة التراخيص</span></button>
                        <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('finance-management', this)"><span class="dashicons dashicons-money-alt"></span> <span>المالية والاستحقاقات</span></button>
                        <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('document-vault', this); smLoadDocuments();"><span class="dashicons dashicons-portfolio"></span> <span>قسم الأرشيف الرقمي</span></button>
                        <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('digital-services-tab', this)"><span class="dashicons dashicons-cloud"></span> <span>إدارة الخدمات الرقمية</span></button>
                        <button class="sm-portal-nav-btn" onclick="smOpenInternalTab('exams-tab', this)"><span class="dashicons dashicons-welcome-learn-more"></span> <span>امتحانات تراخيص المزاولة</span></button>
                    <?php endif; ?>
                </nav>
            </div>
        </div>

        <!-- PORTAL MAIN CONTENT -->
        <div class="sm-portal-content" style="flex: 1; min-width: 0;">
    <?php else: ?>
        <!-- MANAGEMENT TABS -->
        <div class="sm-tabs-wrapper" style="display: flex; gap: 8px; margin-bottom: 30px; border-bottom: 2px solid #edf2f7; padding-bottom: 12px; overflow-x: auto; white-space: nowrap;">
            <button class="sm-tab-btn sm-active" onclick="smOpenInternalTab('profile-info', this)"><span class="dashicons dashicons-admin-users"></span> <?php echo $member_is_admin_role ? 'بيانات الحساب' : 'بيانات العضوية'; ?></button>

            <?php if (!$member_is_admin_role): ?>
                <button class="sm-tab-btn" onclick="smOpenInternalTab('professional-requests-tab', this)"><span class="dashicons dashicons-awards"></span> الطلبات المهنية</button>
                <button class="sm-tab-btn" onclick="smOpenInternalTab('license-status-tab', this)"><span class="dashicons dashicons-id-alt"></span> حالة التراخيص</button>
                <button class="sm-tab-btn" onclick="smOpenInternalTab('finance-management', this)"><span class="dashicons dashicons-money-alt"></span> المالية والاستحقاقات</button>
                <button class="sm-tab-btn" onclick="smOpenInternalTab('document-vault', this); smLoadDocuments();"><span class="dashicons dashicons-portfolio"></span> قسم الأرشيف الرقمي</button>
                <button class="sm-tab-btn" onclick="smOpenInternalTab('messaging-hub-tab', this)"><span class="dashicons dashicons-email"></span> المراسلات والشكاوى</button>
                <button class="sm-tab-btn" onclick="smOpenInternalTab('digital-services-tab', this)"><span class="dashicons dashicons-cloud"></span> إدارة الخدمات الرقمية</button>
                <button class="sm-tab-btn" onclick="smOpenInternalTab('exams-tab', this)"><span class="dashicons dashicons-welcome-learn-more"></span> امتحانات تراخيص المزاولة</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- TAB: Profile Info -->
    <div id="profile-info" class="sm-internal-tab">
        <div style="display: grid; grid-template-columns: <?php echo $is_restricted ? '1fr' : '2.2fr 1fr'; ?>; gap: 30px;">
            <div style="display: flex; flex-direction: column; gap: 30px;">

                <?php if ($member_is_admin_role): ?>
                    <!-- Essential Account and Branch Information (For Admins/Officers Only) -->
                    <div style="background: #fff; padding: 35px; border-radius: 20px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                        <h3 style="margin:0 0 25px 0; font-weight:900; color:var(--sm-dark-color); border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;"><span class="dashicons dashicons-admin-users" style="color:var(--sm-primary-color); margin-left:8px;"></span> بيانات الحساب والفرع الإدارية</h3>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px;">
                            <div><label class="sm-label">الاسم الكامل:</label> <div class="sm-value" style="font-weight:800;"><?php echo esc_html($member->name); ?></div></div>
                            <div><label class="sm-label">اسم المستخدم (Username):</label> <div class="sm-value" style="font-family:monospace;"><?php echo esc_html($target_user->user_login); ?></div></div>
                            <div><label class="sm-label">البريد الإلكتروني:</label> <div class="sm-value"><?php echo esc_html($member->email); ?></div></div>
                            <div><label class="sm-label">الفرع النقابي المعين عليه:</label> <div class="sm-value" style="font-weight:800; color:var(--sm-primary-color);"><?php echo esc_html(SM_Settings::get_branch_name($member->governorate)); ?></div></div>
                            <div>
                                <label class="sm-label">حالة الحساب:</label>
                                <div class="sm-value">
                                    <?php $acc_st = get_user_meta($member->wp_user_id, 'sm_account_status', true) ?: 'active'; ?>
                                    <span class="sm-badge <?php echo $acc_st === 'active' ? 'sm-badge-high' : 'sm-badge-low'; ?>">
                                        <?php echo $acc_st === 'active' ? 'نشط' : 'مقيد'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Group 1: Basic and Academic Information (Standard Members Only) -->
                    <div style="background: #fff; padding: 35px; border-radius: 20px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 25px;">
                            <h3 style="margin:0; font-weight:900; color:var(--sm-dark-color);"><span class="dashicons dashicons-welcome-learn-more" style="color:var(--sm-primary-color); margin-left:8px;"></span> المعلومات الأساسية والأكاديمية</h3>
                            <?php if ($is_restricted): ?>
                                <button onclick="smOpenUpdateMemberRequestModal()" class="sm-btn" style="background: #3182ce; width: auto; height: 34px; font-size: 11px; padding: 0 15px;"><span class="dashicons dashicons-edit" style="font-size: 16px; margin-top: 4px;"></span> طلب تحديث بياناتي</button>
                            <?php endif; ?>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px; margin-bottom: 30px;">
                            <div><label class="sm-label">الاسم الكامل:</label> <div class="sm-value" style="font-weight:800;"><?php echo esc_html($member->name); ?></div></div>
                            <div><label class="sm-label">الرقم القومي:</label> <div class="sm-value" style="font-family:monospace;"><?php echo esc_html($member->national_id); ?></div></div>
                            <div><label class="sm-label">البريد الإلكتروني:</label> <div class="sm-value"><?php echo esc_html($member->email); ?></div></div>
                        </div>

                        <?php
                        $univs = SM_Settings::get_universities();
                        $facs = SM_Settings::get_faculties();
                        $depts = SM_Settings::get_departments();
                        $degrees = SM_Settings::get_academic_degrees();
                        ?>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                            <div><label class="sm-label">الجامعة:</label> <div class="sm-value"><?php echo esc_html($univs[$member->university] ?? $member->university); ?></div></div>
                            <div><label class="sm-label">الكلية:</label> <div class="sm-value"><?php echo esc_html($facs[$member->faculty] ?? $member->faculty); ?></div></div>
                            <div><label class="sm-label">تاريخ التخرج:</label> <div class="sm-value"><?php echo esc_html($member->graduation_date); ?></div></div>
                        </div>
                    </div>

                    <!-- Group 2: Residence and Contact Information (Standard Members Only) -->
                    <div style="background: #fff; padding: 35px; border-radius: 20px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                        <h3 style="margin:0 0 25px 0; font-weight:900; color:var(--sm-dark-color); border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;"><span class="dashicons dashicons-location" style="color:var(--sm-primary-color); margin-left:8px;"></span> معلومات السكن والاتصال</h3>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px;">
                            <div><label class="sm-label">المحافظة (فرع السكن):</label> <div class="sm-value"><?php echo esc_html($govs[$member->residence_governorate] ?? $member->residence_governorate); ?></div></div>
                            <div><label class="sm-label">المدينة / المركز:</label> <div class="sm-value"><?php echo esc_html($member->residence_city); ?></div></div>
                            <div style="grid-column: span 2;"><label class="sm-label">العنوان التفصيلي:</label> <div class="sm-value"><?php echo esc_html($member->residence_street); ?></div></div>
                            <div><label class="sm-label">رقم الهاتف الجوال:</label> <div class="sm-value"><?php echo esc_html($member->phone); ?></div></div>
                            <div><label class="sm-label">الفرع النقابي التابع له:</label> <div class="sm-value"><?php echo esc_html(SM_Settings::get_branch_name($member->governorate)); ?></div></div>
                        </div>
                    </div>

                    <!-- Group 3: Personal and Professional Qualifications (Standard Members Only) -->
                    <div style="background: #fff; padding: 35px; border-radius: 20px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                        <h3 style="margin:0 0 25px 0; font-weight:900; color:var(--sm-dark-color); border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;"><span class="dashicons dashicons-awards" style="color:var(--sm-primary-color); margin-left:8px;"></span> المؤهلات الشخصية والمهنية</h3>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px;">
                            <div><label class="sm-label">الدرجة الوظيفية:</label> <div class="sm-value"><?php echo $grades[$member->professional_grade] ?? $member->professional_grade; ?></div></div>
                            <div><label class="sm-label">التخصص الدقيق:</label> <div class="sm-value"><?php echo esc_html($specs[$member->specialization] ?? $member->specialization); ?></div></div>
                            <div><label class="sm-label">الدرجة العلمية:</label> <div class="sm-value"><?php echo esc_html($degrees[$member->academic_degree] ?? $member->academic_degree); ?></div></div>
                            <div><label class="sm-label">القسم العلمي:</label> <div class="sm-value"><?php echo esc_html($depts[$member->department] ?? $member->department); ?></div></div>
                            <div><label class="sm-label">الجنس:</label> <div class="sm-value"><?php echo $member->gender === 'male' ? 'ذكر' : 'أنثى'; ?></div></div>
                            <div><label class="sm-label">تاريخ القيد بالنقابة:</label> <div class="sm-value"><?php echo esc_html($member->registration_date); ?></div></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$is_restricted): ?>
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <!-- Management Status Cards -->
                <div style="background: #fff; padding: 25px; border-radius: 20px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                    <h4 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 20px; font-weight:800;">دخول النظام</h4>
                    <div style="text-align: center; padding: 10px 0;">
                        <?php
                            $u = new WP_User($member->wp_user_id);
                            $has_pass = !empty($u->user_pass);
                        ?>
                        <div style="font-size: 0.85em; color: #718096; font-weight:700;">حالة تنشيط حساب العضو</div>
                        <div style="font-size: 1.4em; font-weight: 900; color: <?php echo $has_pass ? '#38a169' : '#e53e3e'; ?>; margin-top:5px;">
                            <?php echo $has_pass ? '✅ حساب نشط ومفعل' : '⚠️ بانتظار التنشيط'; ?>
                        </div>
                        <div style="font-size: 11px; color:#94a3b8; margin-top:10px;">ID الحساب: #<?php echo $member->wp_user_id; ?></div>
                    </div>
                </div>

                <div style="background: #fff; padding: 25px; border-radius: 20px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow);">
                    <h4 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 20px; font-weight:800;">التحصيل المالي</h4>
                    <div style="text-align: center; padding: 10px 0;">
                        <div style="font-size: 0.85em; color: #718096; font-weight:700;">إجمالي المديونية الحالية</div>
                        <div style="font-size: 2.2em; font-weight: 900; color: <?php echo $finance['balance'] > 0 ? '#e53e3e' : '#38a169'; ?>; margin:10px 0;">
                            <?php echo number_format($finance['balance'], 2); ?> <span style="font-size:0.4em;">ج.م</span>
                        </div>
                    </div>
                    <div style="margin-top: 15px; display: flex; flex-direction: column; gap: 8px; font-size: 13px;">
                        <div style="display: flex; justify-content: space-between; border-bottom:1px dashed #eee; padding-bottom:5px;"><span>المطلوب كلياً:</span> <strong style="font-family:monospace;"><?php echo number_format($finance['total_owed'], 2); ?></strong></div>
                        <div style="display: flex; justify-content: space-between;"><span>إجمالي المسدد:</span> <strong style="color:#38a169; font-family:monospace;"><?php echo number_format($finance['total_paid'], 2); ?></strong></div>
                    </div>

                    <button onclick="smOpenFinanceModal(<?php echo $member->id; ?>)" class="sm-btn" style="background: var(--sm-dark-color); margin-top: 25px; height: 45px; border-radius: 12px; font-weight: 800;">
                        <?php echo (current_user_can('sm_manage_finance')) ? 'إدارة التحصيل والفواتير' : 'كشف حساب مالي'; ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB PANELS -->
    <div id="professional-requests-tab" class="sm-internal-tab" style="display: none;">
        <?php $_GET['member_id'] = $member->id; include SM_PLUGIN_DIR . 'templates/admin-professional-requests.php'; ?>
    </div>

    <div id="license-status-tab" class="sm-internal-tab" style="display: none;">
        <?php include SM_PLUGIN_DIR . 'templates/public-member-licenses.php'; ?>
    </div>

    <div id="finance-management" class="sm-internal-tab" style="display: none;">
        <?php include SM_PLUGIN_DIR . 'templates/member-finance-tab.php'; ?>
    </div>

    <div id="document-vault" class="sm-internal-tab" style="display: none;">
        <?php include SM_PLUGIN_DIR . 'templates/member-document-vault.php'; ?>
    </div>

    <div id="messaging-hub-tab" class="sm-internal-tab" style="display: none;">
        <div style="min-height: 600px; border: 1px solid #edf2f7; border-radius: 20px; overflow: hidden; background: #fff; box-shadow: var(--sm-shadow);">
            <?php include SM_PLUGIN_DIR . 'templates/messaging-center.php'; ?>
        </div>
    </div>

    <div id="digital-services-tab" class="sm-internal-tab" style="display: none;">
        <?php include SM_PLUGIN_DIR . 'templates/admin-services.php'; ?>
    </div>

    <div id="exams-tab" class="sm-internal-tab" style="display: none;">
        <div style="background:#fff; padding: 35px; border-radius:20px; border:1px solid #e2e8f0; min-height:500px; box-shadow: var(--sm-shadow);">
            <h3 style="margin:0 0 10px 0; font-weight:900; color:var(--sm-dark-color);">امتحانات تراخيص المزاولة</h3>
            <p style="color:#64748b; margin-bottom: 30px; font-size:14px;">يجب اجتياز الامتحانات المقررة من النقابة العامة للحصول على أو تجديد تراخيص مزاولة المهنة والترقيات الوظيفية.</p>
            <?php include SM_PLUGIN_DIR . 'templates/public-dashboard-summary.php'; ?>
        </div>
    </div>

    <?php if ($is_restricted): ?>
        </div> <!-- End sm-portal-content -->
    </div> <!-- End sm-portal-layout-container -->
    <?php endif; ?>

    <!-- MODALS SECTION -->

    <!-- Modal: Edit Member -->
    <div id="edit-member-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 950px;">
            <div class="sm-modal-header"><h3>تحديث بيانات الملف الشخصي للعضو</h3><button class="sm-modal-close" onclick="document.getElementById('edit-member-modal').style.display='none'">&times;</button></div>
            <form id="edit-member-form">
                <?php wp_nonce_field('sm_add_member', 'sm_nonce'); ?>
                <input type="hidden" name="member_id" id="edit_member_id_hidden">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; padding: 25px;">
                    <div class="sm-form-group"><label class="sm-label">الاسم الكامل:</label><input name="name" id="edit_name" type="text" class="sm-input" required></div>

                    <div class="sm-admin-fields-group" style="display: none;">
                        <div class="sm-form-group"><label class="sm-label">اسم المستخدم (Username):</label><input type="text" id="edit_username" class="sm-input" readonly style="background:#f8fafc;"></div>
                    </div>

                    <div class="sm-form-group"><label class="sm-label">الرقم القومي:</label><input name="national_id" id="edit_national_id" type="text" class="sm-input" required maxlength="14"></div>

                    <div class="sm-form-group"><label class="sm-label">البريد الإلكتروني:</label><input name="email" id="edit_email" type="email" class="sm-input"></div>
                    <div class="sm-form-group"><label class="sm-label">الفرع النقابي:</label><select name="governorate" id="edit_gov" class="sm-select"><?php
                        if (!empty($db_branches)) {
                            foreach($db_branches as $db) echo "<option value='".esc_attr($db->slug)."'>".esc_html($db->name)."</option>";
                        } else {
                            foreach ($govs as $k => $v) echo "<option value='$k'>$v</option>";
                        }
                    ?></select></div>

                    <div class="sm-form-group"><label class="sm-label">رقم الهاتف:</label><input name="phone" id="edit_phone" type="text" class="sm-input"></div>

                    <div class="sm-admin-fields-group" style="display: none;">
                        <div class="sm-form-group">
                            <label class="sm-label">حالة الحساب:</label>
                            <select name="account_status" id="edit_account_status" class="sm-select">
                                <option value="active">نشط</option>
                                <option value="restricted">مقيد / موقوف</option>
                            </select>
                        </div>
                    </div>

                    <div class="sm-membership-fields-group" style="display: contents;">
                        <div class="sm-form-group"><label class="sm-label">الدرجة الوظيفية:</label><select name="professional_grade" id="edit_grade" class="sm-select"><?php foreach ($grades as $k => $v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">الجامعة:</label><select name="university" id="edit_university" class="sm-select edit-cascading"><?php foreach ($univs as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">الكلية:</label><select name="faculty" id="edit_faculty" class="sm-select edit-cascading"><?php foreach ($facs as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">القسم:</label><select name="department" id="edit_department" class="sm-select edit-cascading"><?php foreach ($depts as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">تاريخ التخرج:</label><input name="graduation_date" id="edit_grad_date" type="date" class="sm-input"></div>
                        <div class="sm-form-group"><label class="sm-label">الدرجة العلمية:</label><select name="academic_degree" id="edit_degree" class="sm-select"><?php foreach($degrees as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">التخصص:</label><select name="specialization" id="edit_spec" class="sm-select edit-cascading"><?php foreach ($specs as $k => $v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">فرع السكن:</label><select name="residence_governorate" id="edit_res_gov" class="sm-select"><?php foreach ($govs as $k => $v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">المدينة / المركز:</label><input name="residence_city" id="edit_res_city" type="text" class="sm-input"></div>
                        <div class="sm-form-group" style="grid-column: span 3;"><label class="sm-label">العنوان (الشارع / القرية):</label><input name="residence_street" id="edit_res_street" type="text" class="sm-input"></div>
                        <div class="sm-form-group" style="grid-column: span 3;"><label class="sm-label">ملاحظات الإدارة:</label><textarea name="notes" id="edit_notes" class="sm-textarea" rows="2"></textarea></div>
                    </div>
                </div>
                <div style="padding: 0 25px 25px; text-align:center;">
                    <button type="submit" class="sm-btn" style="width: auto; padding: 0 60px; height: 50px; font-weight: 800;">حفظ البيانات وتحديث السجل</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Profile Photo Rules -->
    <div id="sm-photo-rules-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 500px;">
            <div class="sm-modal-header">
                <h3>تعليمات الصورة الشخصية</h3>
                <button class="sm-modal-close" onclick="document.getElementById('sm-photo-rules-modal').style.display='none'">&times;</button>
            </div>
            <div style="padding: 30px;">
                <div style="text-align: center; margin-bottom: 25px;">
                    <div style="width: 80px; height: 80px; background: rgba(246, 48, 73, 0.1); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                        <span class="dashicons dashicons-camera" style="font-size: 40px; width: 40px; height: 40px; color: var(--sm-primary-color);"></span>
                    </div>
                    <h4 style="margin: 0; font-weight: 800;">شروط قبول الصورة</h4>
                </div>

                <ul style="list-style: none; padding: 0; margin: 0 0 30px 0; display: grid; gap: 15px;">
                    <li style="display: flex; align-items: center; gap: 12px; font-size: 14px; color: #4a5568;">
                        <span class="dashicons dashicons-yes" style="color: #38a169;"></span> يجب أن تكون الصورة حديثة وواضحة.
                    </li>
                    <li style="display: flex; align-items: center; gap: 12px; font-size: 14px; color: #4a5568;">
                        <span class="dashicons dashicons-yes" style="color: #38a169;"></span> يجب أن تكون الخلفية بيضاء سادة.
                    </li>
                    <li style="display: flex; align-items: center; gap: 12px; font-size: 14px; color: #e53e3e; font-weight: 700;">
                        <span class="dashicons dashicons-warning"></span> الصور غير المطابقة سيتم حذفها من قبل الإدارة.
                    </li>
                </ul>

                <button onclick="smProceedToPhotoUpload()" class="sm-btn" style="width: 100%; height: 50px; font-weight: 800;">أوافق، اختيار صورة من جهازي</button>
            </div>
        </div>
    </div>

    <!-- Modal: Member Update Request (Self Service Redesign) -->
    <div id="member-update-request-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 900px; overflow: hidden; border-radius: 24px;">
            <div class="sm-modal-header" style="background: var(--sm-dark-color); color: #fff; padding: 25px 40px;">
                <h3 style="margin: 0; color: #fff; font-weight: 900;">بوابة تحديث البيانات الرقمية</h3>
                <button class="sm-modal-close" style="color: #fff; opacity: 0.8;" onclick="document.getElementById('member-update-request-modal').style.display='none'">&times;</button>
            </div>

            <div style="display: grid; grid-template-columns: 280px 1fr; min-height: 500px;">
                <!-- Sidebar Info -->
                <div style="background: #f8fafc; padding: 40px 30px; border-left: 1px solid #e2e8f0;">
                    <div style="width: 50px; height: 50px; background: var(--sm-primary-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(246, 48, 73, 0.3);">
                        <span class="dashicons dashicons-shield-alt" style="color: #fff; font-size: 24px; width: 24px; height: 24px;"></span>
                    </div>
                    <h4 style="margin: 0 0 15px; font-weight: 800; color: var(--sm-dark-color);">دليل الخدمة</h4>
                    <p style="font-size: 13px; color: #64748b; line-height: 1.8; margin-bottom: 25px;">تتيح لك هذه الخدمة طلب تعديل بياناتك الشخصية أو الأكاديمية المسجلة في قاعدة بيانات النقابة.</p>

                    <ul style="list-style: none; padding: 0; margin: 0; display: grid; gap: 15px;">
                        <li style="display: flex; gap: 10px; font-size: 12px; color: #4a5568; font-weight: 600;">
                            <span style="color: var(--sm-primary-color); font-weight: 900;">١.</span> قم بتعديل الحقول المطلوبة في النموذج.
                        </li>
                        <li style="display: flex; gap: 10px; font-size: 12px; color: #4a5568; font-weight: 600;">
                            <span style="color: var(--sm-primary-color); font-weight: 900;">٢.</span> اذكر سبب التعديل في الملاحظات.
                        </li>
                        <li style="display: flex; gap: 10px; font-size: 12px; color: #4a5568; font-weight: 600;">
                            <span style="color: var(--sm-primary-color); font-weight: 900;">٣.</span> سيتم مراجعة الطلب من قبل الإدارة واعتماده خلال ٤٨ ساعة عمل.
                        </li>
                    </ul>

                    <div style="margin-top: 40px; padding: 15px; background: rgba(246, 48, 73, 0.05); border-radius: 12px; border: 1px solid rgba(246, 48, 73, 0.1);">
                        <div style="font-size: 11px; color: var(--sm-primary-color); font-weight: 800; margin-bottom: 5px;">⚠️ تنبيه قانوني</div>
                        <div style="font-size: 10px; color: #718096; line-height: 1.6;">إدخال بيانات غير صحيحة أو تزوير في المؤهلات يعرض العضو للمساءلة القانونية والتأديبية.</div>
                    </div>
                </div>

                <!-- Form Area -->
                <form id="member-update-request-form" style="padding: 40px;">
                    <input type="hidden" name="member_id" value="<?php echo $member->id; ?>">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                        <div class="sm-form-group"><label class="sm-label">الاسم الكامل:</label><input type="text" name="name" class="sm-input" value="<?php echo esc_attr($member->name); ?>" required></div>
                        <div class="sm-form-group"><label class="sm-label">الرقم القومي:</label><input type="text" name="national_id" class="sm-input" value="<?php echo esc_attr($member->national_id); ?>" required maxlength="14" style="font-family:monospace;"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                        <div class="sm-form-group"><label class="sm-label">الجامعة:</label><select name="university" class="sm-select academic-cascading"><?php foreach($univs as $k=>$v) echo "<option value='$k' ".selected($member->university, $k, false).">$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">الكلية:</label><select name="faculty" class="sm-select academic-cascading"><?php foreach($facs as $k=>$v) echo "<option value='$k' ".selected($member->faculty, $k, false).">$v</option>"; ?></select></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                        <div class="sm-form-group"><label class="sm-label">الدرجة العلمية:</label><select name="academic_degree" class="sm-select"><?php foreach($degrees as $k=>$v) echo "<option value='$k' ".selected($member->academic_degree, $k, false).">$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">التخصص الدقيق:</label><select name="specialization" class="sm-select academic-cascading"><?php foreach ($specs as $k => $v) echo "<option value='$k' ".selected($member->specialization, $k, false).">$v</option>"; ?></select></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                        <div class="sm-form-group"><label class="sm-label">رقم الهاتف:</label><input type="text" name="phone" class="sm-input" value="<?php echo esc_attr($member->phone); ?>"></div>
                        <div class="sm-form-group"><label class="sm-label">البريد الإلكتروني:</label><input type="email" name="email" class="sm-input" value="<?php echo esc_attr($member->email); ?>"></div>
                    </div>

                    <div class="sm-form-group" style="margin-bottom: 30px;">
                        <label class="sm-label">مبررات التحديث / ملاحظات إضافية:</label>
                        <textarea name="notes" class="sm-textarea" rows="3" placeholder="يرجى ذكر سبب التعديل أو أي بيانات إضافية ترغب في توضيحها..." style="background: #fdfdfd;"></textarea>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <button type="submit" class="sm-btn" style="flex: 1; height: 55px; font-weight: 800; font-size: 1.1em;">تقديم طلب التعديل الآن</button>
                        <button type="button" class="sm-btn sm-btn-outline" style="width: 150px; height: 55px;" onclick="document.getElementById('member-update-request-modal').style.display='none'">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * SYNDICATE MANAGEMENT - Profile UI Logic
 */

function smToggleFinanceDropdown() {
    const el = document.getElementById('sm-finance-dropdown');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function smTriggerPhotoUpload() {
    document.getElementById('sm-photo-rules-modal').style.display = 'flex';
}

function smProceedToPhotoUpload() {
    document.getElementById('sm-photo-rules-modal').style.display = 'none';
    document.getElementById('member-photo-input').click();
}

function smUploadMemberPhoto(memberId) {
    const file = document.getElementById('member-photo-input').files[0];
    if (!file) return;

    const action = 'sm_update_member_photo';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('member_id', memberId);
    formData.append('member_photo', file);
    formData.append('sm_photo_nonce', '<?php echo wp_create_nonce("sm_photo_action"); ?>');

    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.data && res.data.photo_url) {
            const containers = [document.getElementById('member-photo-container'), document.querySelector('.sm-sidebar-photo-container div')];
            containers.forEach(c => { if(c) c.innerHTML = `<img src="${res.data.photo_url}" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">`; });
            smShowNotification('تم تحديث الصورة الشخصية بنجاح');
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smOpenUpdateMemberRequestModal() {
    document.getElementById('member-update-request-modal').style.display = 'flex';
}

document.getElementById('member-update-request-form').onsubmit = function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerText = 'جاري الإرسال...';

    const formData = new FormData(this);
    formData.append('action', 'sm_submit_update_request_ajax');
    formData.append('nonce', '<?php echo wp_create_nonce("sm_update_request"); ?>');

    fetch(ajaxurl + '?action=sm_submit_update_request_ajax', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم إرسال طلب التحديث بنجاح. سنقوم بمراجعته قريباً.');
            document.getElementById('member-update-request-modal').style.display = 'none';
        } else {
            smHandleAjaxError(res);
            btn.disabled = false; btn.innerText = 'إرسال طلب التحديث للإدارة';
        }
    }).catch(err => smHandleAjaxError(err));
};

function deleteMember(id, name) {
    if (!confirm('هل أنت متأكد من حذف العضو: ' + name + ' نهائياً من النظام؟ لا يمكن التراجع عن هذا الإجراء.')) return;
    const formData = new FormData();
    formData.append('action', 'sm_delete_member_ajax');
    formData.append('member_id', id);
    formData.append('nonce', '<?php echo wp_create_nonce("sm_delete_member"); ?>');

    fetch(ajaxurl + '?action=sm_delete_member_ajax', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم حذف العضو بنجاح');
            setTimeout(() => { window.location.href = '<?php echo add_query_arg('sm_tab', 'members'); ?>'; }, 1000);
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}

window.editSmMember = function(s) {
    const f = document.getElementById('edit-member-form');
    document.getElementById('edit_member_id_hidden').value = s.id;
    f.name.value = s.name;
    f.national_id.value = s.national_id;
    f.professional_grade.value = s.professional_grade;
    f.university.value = s.university || '';
    f.faculty.value = s.faculty || '';
    f.department.value = s.department || '';
    f.graduation_date.value = s.graduation_date || '';
    f.academic_degree.value = s.academic_degree || '';
    f.specialization.value = s.specialization || '';
    f.residence_governorate.value = s.residence_governorate || '';
    f.residence_city.value = s.residence_city || '';
    f.residence_street.value = s.residence_street || '';
    f.governorate.value = s.governorate;
    f.phone.value = s.phone;
    f.email.value = s.email;
    f.notes.value = s.notes || '';

    // Cascade triggers
    const triggerCascade = (sel) => { if(sel.value) sel.disabled = false; };
    [f.faculty, f.department, f.specialization].forEach(triggerCascade);

    // Role-based visibility toggle in modal
    const membershipFields = document.querySelector('.sm-membership-fields-group');
    const adminFields = document.querySelectorAll('.sm-admin-fields-group');

    if (s.is_admin_role) {
        if (membershipFields) membershipFields.style.display = 'none';
        adminFields.forEach(el => el.style.display = 'contents');
        document.getElementById('edit_username').value = s.user_login || '';
        document.getElementById('edit_account_status').value = s.account_status || 'active';
    } else {
        if (membershipFields) membershipFields.style.display = 'contents';
        adminFields.forEach(el => el.style.display = 'none');
    }

    document.getElementById('edit-member-modal').style.display = 'flex';
};

const applyCascading = (selector) => {
    const elements = document.querySelectorAll(selector);
    elements.forEach((el, idx) => {
        el.addEventListener("change", function() {
            if (this.value && idx < elements.length - 1) {
                elements[idx + 1].disabled = false;
            } else if (!this.value) {
                for (let i = idx + 1; i < elements.length; i++) {
                    elements[i].value = "";
                    elements[i].disabled = true;
                }
            }
        });
    });
};
applyCascading("#edit-member-form .edit-cascading");
applyCascading("#member-update-request-form .academic-cascading");

document.getElementById('edit-member-form').onsubmit = function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerText = 'جاري التحديث...';

    const formData = new FormData(this);
    formData.append('action', 'sm_update_member_ajax');

    fetch(ajaxurl + '?action=sm_update_member_ajax', { method: 'POST', body: formData })
    .then(r => r.json()).then(res => {
        if(res.success) {
            smShowNotification('تم تحديث البيانات بنجاح');
            setTimeout(() => location.reload(), 1000);
        } else {
            smHandleAjaxError(res);
            btn.disabled = false; btn.innerText = 'حفظ البيانات وتحديث السجل';
        }
    }).catch(err => smHandleAjaxError(err));
};

document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('sm-finance-dropdown');
    const btn = document.querySelector('[onclick="smToggleFinanceDropdown()"]');
    if (dropdown && !dropdown.contains(e.target) && btn && !btn.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});

// Tab Routing from URL
window.addEventListener('load', function() {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('profile_tab');
    if (tab) {
        const tabMap = {
            'info': 'profile-info', 'requests': 'professional-requests-tab', 'licenses': 'license-status-tab',
            'finance': 'finance-management', 'archive': 'document-vault', 'correspondence': 'messaging-hub-tab',
            'services': 'digital-services-tab', 'exams': 'exams-tab'
        };
        const targetId = tabMap[tab];
        if (targetId) {
            const btn = document.querySelector(`[onclick*="'${targetId}'"]`);
            if (btn) btn.click();
        }
    }
});
</script>
