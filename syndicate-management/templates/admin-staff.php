<?php if (!defined('ABSPATH')) exit; ?>
<div class="sm-content-wrapper" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: #fff; padding: 20px 25px; border-radius: 12px; border: 1px solid var(--sm-border-color);">
        <div>
            <h3 style="margin:0; border:none; padding:0; font-weight: 900; color: var(--sm-dark-color);">مركز التحكم بمستخدمي النظام</h3>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;">إدارة شاملة لكافة حسابات المسؤولين، الموظفين، وأعضاء النقابة</p>
        </div>
        <div style="display:flex; gap:10px;">
            <button onclick="smExportUsers()" class="sm-btn sm-btn-outline" style="width:auto;"><span class="dashicons dashicons-download" style="margin-top:4px;"></span> تصدير البيانات</button>
            <?php if (current_user_can('sm_manage_system')): ?>
                <button onclick="document.getElementById('staff-csv-import-form').style.display='block'" class="sm-btn" style="width:auto; background:var(--sm-secondary-color);"><span class="dashicons dashicons-upload" style="margin-top:4px;"></span> استيراد (CSV)</button>
                <button onclick="document.getElementById('add-staff-modal').style.display='flex'" class="sm-btn" style="width:auto;"><span class="dashicons dashicons-plus-alt" style="margin-top:4px;"></span> إضافة مستخدم</button>
                <button onclick="executeBulkDeleteUsers()" class="sm-btn" style="width:auto; background:#e53e3e; padding: 0 15px;"><span class="dashicons dashicons-trash" style="margin-top:4px;"></span> حذف محدد</button>
            <?php endif; ?>
        </div>
    </div>

    <div id="staff-csv-import-form" style="display:none; background: #f8fafc; padding: 30px; border: 2px dashed #cbd5e0; border-radius: 12px; margin-bottom: 20px;">
        <h3 style="margin-top:0; color:var(--sm-secondary-color);">دليل استيراد مستخدمي النظام (CSV)</h3>
        
        <div style="background:#fff; padding:15px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom: 20px;">
            <p style="font-size:13px; font-weight:700; margin-bottom:10px;">هيكل ملف المستخدمين الصحيح:</p>
            <table style="width:100%; border-collapse:collapse; text-align:center;">
                <thead>
                    <tr style="background:#edf2f7;">
                        <th style="border:1px solid #cbd5e0; padding:5px;">اسم المستخدم</th>
                        <th style="border:1px solid #cbd5e0; padding:5px;">البريد</th>
                        <th style="border:1px solid #cbd5e0; padding:5px;">الاسم الكامل</th>
                        <th style="border:1px solid #cbd5e0; padding:5px;">الرقم القومي / كود المستخدم</th>
                        <th style="border:1px solid #cbd5e0; padding:5px;">المسمى</th>
                        <th style="border:1px solid #cbd5e0; padding:5px;">رقم الجوال</th>
                        <th style="border:1px solid #cbd5e0; padding:5px;">كلمة المرور</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border:1px solid #cbd5e0; padding:5px;">staff_member</td>
                        <td style="border:1px solid #cbd5e0; padding:5px;">user@syndicate.com</td>
                        <td style="border:1px solid #cbd5e0; padding:5px;">الاسم الكامل</td>
                        <td style="border:1px solid #cbd5e0; padding:5px;">S101</td>
                        <td style="border:1px solid #cbd5e0; padding:5px;">عضو نقابة</td>
                        <td style="border:1px solid #cbd5e0; padding:5px;">050000000</td>
                        <td style="border:1px solid #cbd5e0; padding:5px;">123456</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('sm_admin_action', 'sm_admin_nonce'); ?>
            <div class="sm-form-group">
                <label class="sm-label">اختر ملف CSV للمستخدمين:</label>
                <input type="file" name="csv_file" accept=".csv" required>
            </div>
            <div style="display:flex; gap:10px; margin-top: 20px;">
                <button type="submit" name="sm_import_staffs_csv" class="sm-btn" style="width:auto; background:#27ae60;">استيراد القائمة الآن</button>
                <button type="button" onclick="this.parentElement.parentElement.parentElement.style.display='none'" class="sm-btn" style="width:auto; background:var(--sm-text-gray);">إلغاء</button>
            </div>
        </form>
    </div>

    <?php
    $current_user = wp_get_current_user();
    $is_sys_manager = current_user_can('sm_manage_system');
    $is_syndicate_admin = current_user_can('sm_full_access') && !$is_sys_manager;
    $my_gov = get_user_meta($current_user->ID, 'sm_governorate', true);
    $db_branches = SM_DB::get_branches_data();
    ?>

    <div class="sm-tabs-wrapper" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee;">
        <a href="<?php echo remove_query_arg('role_filter'); ?>" class="sm-tab-btn <?php echo empty($_GET['role_filter']) ? 'sm-active' : ''; ?>" style="text-decoration:none;">الكل</a>
        <a href="<?php echo add_query_arg('role_filter', 'administrator'); ?>" class="sm-tab-btn <?php echo ($_GET['role_filter'] ?? '') == 'administrator' ? 'sm-active' : ''; ?>" style="text-decoration:none;">مدير النظام</a>
        <a href="<?php echo add_query_arg('role_filter', 'sm_general_officer'); ?>" class="sm-tab-btn <?php echo ($_GET['role_filter'] ?? '') == 'sm_general_officer' ? 'sm-active' : ''; ?>" style="text-decoration:none;">مسؤول النقابة العامة</a>
        <a href="<?php echo add_query_arg('role_filter', 'sm_branch_officer'); ?>" class="sm-tab-btn <?php echo ($_GET['role_filter'] ?? '') == 'sm_branch_officer' ? 'sm-active' : ''; ?>" style="text-decoration:none;">مسؤول نقابة</a>
        <a href="<?php echo add_query_arg('role_filter', 'sm_member'); ?>" class="sm-tab-btn <?php echo ($_GET['role_filter'] ?? '') == 'sm_member' ? 'sm-active' : ''; ?>" style="text-decoration:none;">عضو النقابة</a>
    </div>

    <div style="background: white; padding: 25px; border: 1px solid var(--sm-border-color); border-radius: var(--sm-radius); margin-bottom: 25px; box-shadow: var(--sm-shadow);">
        <form method="get" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
            <input type="hidden" name="page" value="sm-dashboard">
            <input type="hidden" name="sm_tab" value="staff">

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label" style="font-size: 11px; font-weight: 800;">البحث المتقدم (اسم، بريد، كود، هوية):</label>
                <input type="text" name="staff_search" class="sm-input" value="<?php echo esc_attr(isset($_GET['staff_search']) ? $_GET['staff_search'] : ''); ?>" placeholder="كلمات البحث...">
            </div>

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label" style="font-size: 11px; font-weight: 800;">الدور الوظيفي:</label>
                <select name="role_filter" class="sm-select">
                    <option value="">كافة الأدوار</option>
                    <?php
                    $roles_obj = wp_roles();
                    foreach($roles_obj->roles as $rk => $rd): ?>
                        <option value="<?php echo esc_attr($rk); ?>" <?php selected($_GET['role_filter'] ?? '', $rk); ?>><?php echo esc_html(function_exists('translate_user_role') ? translate_user_role($rd['name']) : $rd['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label" style="font-size: 11px; font-weight: 800;">الفرع / اللجنة:</label>
                <select name="gov_filter" class="sm-select">
                    <option value="">كافة الفروع</option>
                    <?php
                        foreach($db_branches as $db) echo "<option value='".esc_attr($db->slug)."' ".selected($_GET['gov_filter'] ?? '', $db->slug, false).">".esc_html($db->name)."</option>";
                    ?>
                </select>
            </div>

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label" style="font-size: 11px; font-weight: 800;">حالة الحساب:</label>
                <select name="status_filter" class="sm-select">
                    <option value="">الكل</option>
                    <option value="active" <?php selected($_GET['status_filter'] ?? '', 'active'); ?>>نشط</option>
                    <option value="restricted" <?php selected($_GET['status_filter'] ?? '', 'restricted'); ?>>مقيد / معطل</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="sm-btn" style="width: auto; padding: 0 20px;">فلترة</button>
                <a href="<?php echo add_query_arg(array('sm_tab'=>'staff'), remove_query_arg(array('staff_search', 'role_filter', 'gov_filter', 'status_filter'))); ?>" class="sm-btn sm-btn-outline" style="text-decoration:none; width: auto; padding: 0 15px;">تصفير</a>
            </div>
        </form>
    </div>

    <div class="sm-table-container">
        <table class="sm-table">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" onclick="toggleAllUsers(this)"></th>
                    <th>الرقم القومي / كود المستخدم</th>
                    <th>الاسم الكامل</th>
                    <th>الدور / الرتبة</th>
                    <th>الفرع</th>
                    <th>رقم التواصل</th>
                    <th>البريد الإلكتروني</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $role_labels = array(
                    'administrator' => 'مدير النظام',
                    'sm_general_officer' => 'مسؤول النقابة العامة',
                    'sm_branch_officer' => 'مسؤول نقابة',
                    'sm_member' => 'عضو النقابة'
                );

                $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $limit = 20;
                $offset = ($current_page - 1) * $limit;

                $args = array(
                    'number' => $limit,
                    'offset' => $offset,
                    'role' => $_GET['role_filter'] ?? '',
                    'governorate' => $_GET['gov_filter'] ?? '',
                    'account_status' => $_GET['status_filter'] ?? ''
                );

                if (!empty($_GET['staff_search'])) {
                    $args['search'] = '*' . esc_attr($_GET['staff_search']) . '*';
                    $args['search_columns'] = array('user_login', 'display_name', 'user_email');
                }

                $users = SM_DB::get_staff($args);
                if (empty($users)): ?>
                    <tr><td colspan="6" style="padding: 30px; text-align: center;">لا يوجد مستخدمون يطابقون البحث.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u):
                        $role = (array)$u->roles;
                        $role_slug = reset($role);
                        if ($u->ID === get_current_user_id()) continue; // Skip current user
                        $member = SM_DB::get_member_by_wp_user_id($u->ID);
                        $user_data_attr = array(
                            "id" => $u->ID,
                            "name" => $u->display_name,
                            "email" => $u->user_email,
                            "login" => $u->user_login,
                            "role" => $role_slug,
                            "rank" => get_user_meta($u->ID, "sm_rank", true),
                            "officer_id" => get_user_meta($u->ID, "sm_syndicateMemberIdAttr", true),
                            "phone" => get_user_meta($u->ID, "sm_phone", true),
                            "governorate" => get_user_meta($u->ID, "sm_governorate", true),
                            "status" => get_user_meta($u->ID, "sm_account_status", true) ?: "active",
                            // Full Member Fields
                            "member_id" => $member ? $member->id : '',
                            "national_id" => $member ? $member->national_id : get_user_meta($u->ID, "sm_national_id", true),
                            "professional_grade" => $member ? $member->professional_grade : '',
                            "specialization" => $member ? $member->specialization : '',
                            "academic_degree" => $member ? $member->academic_degree : '',
                            "university" => $member ? $member->university : '',
                            "faculty" => $member ? $member->faculty : '',
                            "department" => $member ? $member->department : '',
                            "graduation_date" => $member ? $member->graduation_date : '',
                            "residence_governorate" => $member ? $member->residence_governorate : '',
                            "residence_city" => $member ? $member->residence_city : '',
                            "residence_street" => $member ? $member->residence_street : '',
                            "membership_number" => $member ? $member->membership_number : '',
                            "membership_start_date" => $member ? $member->membership_start_date : '',
                            "membership_expiration_date" => $member ? $member->membership_expiration_date : '',
                            "notes" => $member ? $member->notes : ''
                        );
                    ?>
                        <tr class="user-row" data-user-id="<?php echo $u->ID; ?>">
                            <td><input type="checkbox" class="user-cb" value="<?php echo $u->ID; ?>"></td>
                            <td style="font-family: 'Rubik', sans-serif; font-weight: 700; color: var(--sm-primary-color);"><?php echo esc_html($user_data_attr['officer_id'] ?: $u->user_login); ?></td>
                            <td style="font-weight: 800; color: var(--sm-dark-color);"><?php echo esc_html($u->display_name); ?></td>
                            <td><span class="sm-badge sm-badge-low"><?php echo $role_labels[$role_slug] ?? $role_slug; ?></span></td>
                            <td><?php echo esc_html(SM_Settings::get_branch_name($user_data_attr['governorate'])); ?></td>
                            <td dir="ltr" style="text-align: right;"><?php echo esc_html($user_data_attr['phone']); ?></td>
                            <td><?php echo esc_html($u->user_email); ?></td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button onclick='toggleEditPanel(<?php echo $u->ID; ?>)' class="sm-btn sm-btn-outline" style="padding: 4px 12px; font-size: 11px; width: auto; height: 28px; display: flex; align-items: center; gap: 5px;">
                                        <span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px;"></span> تعديل
                                    </button>
                                    <button onclick="deleteSmUser(<?php echo $u->ID; ?>, '<?php echo esc_js($u->display_name); ?>')" class="sm-btn" style="background: #e53e3e; padding: 4px 12px; font-size: 11px; width: auto; height: 28px; display: flex; align-items: center; gap: 5px;">
                                        <span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span> حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr id="edit-panel-<?php echo $u->ID; ?>" class="edit-panel-row" style="display:none; background: #fdfdfd; border-right: 5px solid var(--sm-primary-color);">
                            <td colspan="8" style="padding: 35px;">
                                <form onsubmit="saveInlineEdit(event, <?php echo $u->ID; ?>)" class="inline-edit-form">
                                    <?php wp_nonce_field('sm_syndicateMemberAction', 'sm_nonce'); ?>
                                    <input type="hidden" name="edit_officer_id" value="<?php echo $u->ID; ?>">
                                    <input type="hidden" name="member_id" value="<?php echo esc_attr($user_data_attr['member_id']); ?>">

                                    <!-- Core Account Information -->
                                    <h4 style="margin: 0 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; color: var(--sm-dark-color);"><span class="dashicons dashicons-admin-network" style="margin-top:4px;"></span> إدارة حساب المستخدم والصلاحيات</h4>
                                    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom: 30px; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                                        <div class="sm-form-group">
                                            <label class="sm-label">الاسم المعروض (Display Name):</label>
                                            <input type="text" name="display_name" value="<?php echo esc_attr($u->display_name); ?>" class="sm-input" required <?php echo !current_user_can('sm_manage_system') ? 'disabled' : ''; ?>>
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">الدور الوظيفي / الصلاحيات:</label>
                                            <select name="role" class="sm-select" onchange="smToggleRankField(this, 'rank-group-<?php echo $u->ID; ?>')" <?php echo !current_user_can('sm_manage_system') ? 'disabled' : ''; ?>>
                                                <?php
                                                foreach($role_labels as $rk => $rl): ?>
                                                    <option value="<?php echo esc_attr($rk); ?>" <?php selected($role_slug, $rk); ?>><?php echo esc_html($rl); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">الفرع الإداري المباشر:</label>
                                            <select name="governorate" class="sm-select">
                                                <option value="">-- بلا فرع محدد --</option>
                                                <?php
                                                    foreach($db_branches as $db) echo "<option value='".esc_attr($db->slug)."' ".selected($user_data_attr['governorate'], $db->slug, false).">".esc_html($db->name)."</option>";
                                                ?>
                                            </select>
                                        </div>
                                        <div class="sm-form-group" id="rank-group-<?php echo $u->ID; ?>" style="<?php echo ($role_slug === 'sm_member') ? 'display:none;' : 'display:block;'; ?>">
                                            <label class="sm-label">الرتبة / المسمى النقابي:</label>
                                            <select name="rank" class="sm-select">
                                                <option value="">-- اختر الرتبة --</option>
                                                <?php
                                                foreach(SM_Settings::get_professional_grades() as $rk => $rv) {
                                                    echo "<option value='".esc_attr($rk)."' ".selected($user_data_attr['rank'], $rk, false).">".esc_html($rv)."</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">حالة الحساب الإلكتروني:</label>
                                            <select name="account_status" class="sm-select">
                                                <option value="active" <?php selected($user_data_attr['status'], 'active'); ?>>✅ نشط / مفعل</option>
                                                <option value="restricted" <?php selected($user_data_attr['status'], 'restricted'); ?>>🚫 مقيد / معطل</option>
                                            </select>
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">تحديث كلمة المرور (اختياري):</label>
                                            <input type="password" name="user_pass" class="sm-input" placeholder="********">
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">البريد الإلكتروني للحساب:</label>
                                            <input type="email" name="user_email" value="<?php echo esc_attr($u->user_email); ?>" class="sm-input" required>
                                        </div>
                                    </div>

                                    <!-- Member Integrated Profile -->
                                    <h4 style="margin: 0 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; color: var(--sm-dark-color);"><span class="dashicons dashicons-id-alt" style="margin-top:4px;"></span> البيانات المهنية والأكاديمية المرتبطة</h4>
                                    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom: 30px;">
                                        <div class="sm-form-group">
                                            <label class="sm-label">الاسم الرباعي (بالهوية):</label>
                                            <input type="text" name="name" value="<?php echo esc_attr($user_data_attr['name']); ?>" class="sm-input">
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">الرقم القومي (14 رقم):</label>
                                            <input type="text" name="national_id" value="<?php echo esc_attr($user_data_attr['national_id']); ?>" class="sm-input" maxlength="14">
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">كود الضابط / التعريفي:</label>
                                            <input type="text" name="officer_id" value="<?php echo esc_attr($user_data_attr['officer_id']); ?>" class="sm-input">
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">رقم القيد / العضوية:</label>
                                            <input type="text" name="membership_number" value="<?php echo esc_attr($user_data_attr['membership_number']); ?>" class="sm-input">
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">الدرجة الوظيفية:</label>
                                            <select name="professional_grade" class="sm-select">
                                                <option value="">-- اختر الدرجة --</option>
                                                <?php foreach (SM_Settings::get_professional_grades() as $k => $v) echo "<option value='$k' ".selected($user_data_attr['professional_grade'], $k, false).">$v</option>"; ?>
                                            </select>
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">التخصص الدقيق:</label>
                                            <select name="specialization" class="sm-select">
                                                <option value="">-- اختر التخصص --</option>
                                                <?php foreach (SM_Settings::get_specializations() as $k => $v) echo "<option value='$k' ".selected($user_data_attr['specialization'], $k, false).">$v</option>"; ?>
                                            </select>
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">الجامعة:</label>
                                            <select name="university" class="sm-select">
                                                <option value="">-- اختر الجامعة --</option>
                                                <?php foreach (SM_Settings::get_universities() as $k => $v) echo "<option value='$k' ".selected($user_data_attr['university'], $k, false).">$v</option>"; ?>
                                            </select>
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">الكلية:</label>
                                            <select name="faculty" class="sm-select">
                                                <option value="">-- اختر الكلية --</option>
                                                <?php foreach (SM_Settings::get_faculties() as $k => $v) echo "<option value='$k' ".selected($user_data_attr['faculty'], $k, false).">$v</option>"; ?>
                                            </select>
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">رقم الهاتف الجوال:</label>
                                            <input type="text" name="phone" value="<?php echo esc_attr($user_data_attr['phone']); ?>" class="sm-input">
                                        </div>
                                    </div>

                                    <h4 style="margin: 0 0 20px 0; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; color: var(--sm-dark-color);"><span class="dashicons dashicons-location" style="margin-top:4px;"></span> بيانات السكن وتواريخ العضوية</h4>
                                    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom: 20px;">
                                        <div class="sm-form-group">
                                            <label class="sm-label">محافظة السكن:</label>
                                            <select name="residence_governorate" class="sm-select">
                                                <option value="">-- اختر المحافظة --</option>
                                                <?php foreach (SM_Settings::get_governorates() as $k => $v) echo "<option value='$k' ".selected($user_data_attr['residence_governorate'], $k, false).">$v</option>"; ?>
                                            </select>
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">المدينة / المركز:</label>
                                            <input type="text" name="residence_city" value="<?php echo esc_attr($user_data_attr['residence_city']); ?>" class="sm-input">
                                        </div>
                                        <div class="sm-form-group" style="grid-column: span 2;">
                                            <label class="sm-label">العنوان بالتفصيل:</label>
                                            <input type="text" name="residence_street" value="<?php echo esc_attr($user_data_attr['residence_street']); ?>" class="sm-input">
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">تاريخ بدء القيد:</label>
                                            <input type="date" name="membership_start_date" value="<?php echo esc_attr($user_data_attr['membership_start_date']); ?>" class="sm-input">
                                        </div>
                                        <div class="sm-form-group">
                                            <label class="sm-label">تاريخ انتهاء العضوية:</label>
                                            <input type="date" name="membership_expiration_date" value="<?php echo esc_attr($user_data_attr['membership_expiration_date']); ?>" class="sm-input">
                                        </div>
                                        <div class="sm-form-group" style="grid-column: span 2;">
                                            <label class="sm-label">ملاحظات إدارية:</label>
                                            <textarea name="notes" class="sm-textarea" rows="1"><?php echo esc_textarea($user_data_attr['notes']); ?></textarea>
                                        </div>
                                    </div>

                                    <div style="margin-top: 30px; display: flex; gap: 10px; justify-content: flex-end;">
                                        <button type="submit" class="sm-btn" style="width: 200px; height: 45px; font-weight: 800;">تحديث البيانات بالكامل</button>
                                        <button type="button" onclick="document.getElementById('edit-panel-<?php echo $u->ID; ?>').style.display='none'" class="sm-btn sm-btn-outline" style="width: auto; padding: 0 30px;">إلغاء التعديل</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php
    $total_users = count(SM_DB::get_staff(array_merge($args, ['number' => -1, 'offset' => 0])));
    $total_pages = ceil($total_users / $limit);
    if ($total_pages > 1):
    ?>
    <div class="sm-pagination" style="margin-top: 20px; display: flex; gap: 5px; justify-content: center;">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="<?php echo add_query_arg('paged', $i); ?>" class="sm-btn <?php echo $i == $current_page ? '' : 'sm-btn-outline'; ?>" style="padding: 5px 12px; min-width: 40px; text-align: center;"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>



    <div id="add-staff-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 900px;">
            <div class="sm-modal-header">
                <h3>إضافة حساب مستخدم ونظامي جديد</h3>
                <button class="sm-modal-close" onclick="document.getElementById('add-staff-modal').style.display='none'">&times;</button>
            </div>
            <form id="add-staff-form" style="padding: 25px;">
                <?php wp_nonce_field('sm_syndicateMemberAction', 'sm_nonce'); ?>

                <h4 style="margin: 0 0 15px 0; color: var(--sm-primary-color); border-bottom: 1px solid #eee; padding-bottom: 8px;">بيانات الحساب الأساسية</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px; margin-bottom: 25px;">
                    <div class="sm-form-group"><label class="sm-label">اسم الدخول (Username):</label><input type="text" name="user_login" class="sm-input" required></div>
                    <div class="sm-form-group"><label class="sm-label">البريد الإلكتروني:</label><input type="email" name="user_email" class="sm-input" required></div>
                    <div class="sm-form-group"><label class="sm-label">كلمة المرور (اختياري):</label><input type="password" name="user_pass" class="sm-input" placeholder="توليد تلقائي"></div>
                    <div class="sm-form-group">
                        <label class="sm-label">الدور الوظيفي:</label>
                        <select name="role" class="sm-select">
                            <?php
                            foreach($role_labels as $rk => $rl): ?>
                                <option value="<?php echo esc_attr($rk); ?>" <?php selected($rk, 'sm_member'); ?>><?php echo esc_html($rl); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">الفرع الملحق به:</label>
                        <select name="governorate" class="sm-select">
                            <option value="">-- اختر الفرع --</option>
                            <?php foreach($db_branches as $db) echo "<option value='".esc_attr($db->slug)."'>".esc_html($db->name)."</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group" id="add-rank-group" style="display:none;">
                        <label class="sm-label">الرتبة / المسمى النقابي:</label>
                        <select name="rank" class="sm-select">
                            <option value="">-- اختر الرتبة --</option>
                            <?php foreach(SM_Settings::get_professional_grades() as $rk => $rv) echo "<option value='".esc_attr($rk)."'>".esc_html($rv)."</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group"><label class="sm-label">رقم التواصل:</label><input type="text" name="phone" class="sm-input"></div>
                </div>

                <h4 style="margin: 0 0 15px 0; color: var(--sm-primary-color); border-bottom: 1px solid #eee; padding-bottom: 8px;">بيانات العضوية (للمزامنة)</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px;">
                    <div class="sm-form-group"><label class="sm-label">الاسم الكامل (رباعي):</label><input type="text" name="name" class="sm-input" required></div>
                    <div class="sm-form-group"><label class="sm-label">الرقم القومي (14 رقم):</label><input type="text" name="national_id" class="sm-input" maxlength="14" required></div>
                    <div class="sm-form-group"><label class="sm-label">كود الضابط / التعريفي:</label><input type="text" name="officer_id" class="sm-input"></div>
                    <div class="sm-form-group">
                        <label class="sm-label">الدرجة الوظيفية:</label>
                        <select name="professional_grade" class="sm-select">
                            <option value="">-- اختر الدرجة --</option>
                            <?php foreach (SM_Settings::get_professional_grades() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">التخصص:</label>
                        <select name="specialization" class="sm-select">
                            <option value="">-- اختر التخصص --</option>
                            <?php foreach (SM_Settings::get_specializations() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group"><label class="sm-label">رقم القيد:</label><input type="text" name="membership_number" class="sm-input"></div>
                </div>

                <div style="margin-top: 25px; text-align: left; border-top: 1px solid #eee; padding-top: 20px;">
                    <button type="submit" class="sm-btn" style="width: 250px; height: 45px; font-weight: 800;">إضافة المستخدم وتفعيل الملف</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    window.smExportUsers = function() {
        const role = document.querySelector('select[name="role_filter"]').value;
        const gov = document.querySelector('select[name="gov_filter"]').value;
        const status = document.querySelector('select[name="status_filter"]').value;
        let url = ajaxurl + '?action=sm_export_users_csv';
        if (role) url += '&role_filter=' + role;
        if (gov) url += '&gov_filter=' + gov;
        if (status) url += '&status_filter=' + status;
        window.location.href = url;
    };

    window.smToggleRankField = function(roleSelect, groupId) {
        const group = document.getElementById(groupId);
        if (!group) return;
        // Rank is for Officers (General and Branch), hide for members
        if (roleSelect.value === 'sm_member') {
            group.style.display = 'none';
            const select = group.querySelector('select');
            if (select) select.value = '';
        } else {
            group.style.display = 'block';
        }
    };

    // Initialize rank toggle for Add form
    document.querySelector('#add-staff-modal select[name="role"]')?.addEventListener('change', function() {
        const group = document.getElementById('add-rank-group');
        if (this.value === 'sm_member') {
            group.style.display = 'none';
            group.querySelector('select').value = '';
        } else {
            group.style.display = 'block';
        }
    });

    function toggleAllUsers(master) {
        document.querySelectorAll('.user-cb').forEach(cb => cb.checked = master.checked);
    }

    window.deleteSmUser = function(id, name) {
        if (!confirm('هل أنت متأكد من حذف حساب: ' + name + '؟')) return;
        const action = 'sm_delete_staff_ajax';
        const formData = new FormData();
        formData.append('action', action);
        formData.append('user_id', id);
        formData.append('nonce', '<?php echo wp_create_nonce("sm_syndicateMemberAction"); ?>');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تم حذف المستخدم بنجاح');
                setTimeout(() => location.reload(), 1000);
            } else {
                smHandleAjaxError(res);
            }
        }).catch(err => smHandleAjaxError(err));
    };

    function executeBulkDeleteUsers() {
        const ids = Array.from(document.querySelectorAll('.user-cb:checked')).map(cb => cb.value);
        if (ids.length === 0) {
            smShowNotification('يرجى تحديد مستخدمين أولاً', true);
            return;
        }
        if (!confirm('هل أنت متأكد من حذف ' + ids.length + ' مستخدم؟')) return;

        const action = 'sm_bulk_delete_users_ajax';
        const formData = new FormData();
        formData.append('action', action);
        formData.append('user_ids', ids.join(','));
        formData.append('nonce', '<?php echo wp_create_nonce("sm_syndicateMemberAction"); ?>');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                smShowNotification('تم حذف المستخدمين بنجاح');
                setTimeout(() => location.reload(), 1000);
            } else {
                smHandleAjaxError(res);
            }
        }).catch(err => smHandleAjaxError(err));
    }

    (function() {
        window.toggleEditPanel = function(userId) {
            const panel = document.getElementById('edit-panel-' + userId);
            const isVisible = panel.style.display !== 'none';
            document.querySelectorAll('.edit-panel-row').forEach(p => p.style.display = 'none');
            if (!isVisible) {
                panel.style.display = 'table-row';

                // Fetch latest data to ensure rank and gov are correct
                const action = 'sm_get_user_role';
                fetch(ajaxurl + '?action=' + action + '&user_id=' + userId + '&nonce=<?php echo wp_create_nonce("sm_admin_action"); ?>')
                .then(r => r.json()).then(res => {
                    if (res.success && res.data) {
                        const form = panel.querySelector('form');
                        if (form) {
                            form.role.value = res.data.role || '';
                            form.governorate.value = res.data.governorate || '';
                            if (form.rank) {
                                form.rank.value = res.data.rank || '';
                                smToggleRankField(form.role, 'rank-group-' + userId);
                            }
                        }
                    }
                });
            }
        };

        window.saveInlineEdit = function(e, userId) {
            e.preventDefault();
            const action = 'sm_update_staff_ajax';
            const form = e.target;
            const formData = new FormData(form);
            if (!formData.has('action')) formData.append('action', action);

            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerText = 'جاري الحفظ...';

            fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    smShowNotification('تم تحديث بيانات المستخدم بنجاح');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    smHandleAjaxError(res);
                    btn.disabled = false;
                    btn.innerText = 'حفظ التعديلات';
                }
            }).catch(err => {
                smHandleAjaxError(err);
                btn.disabled = false;
                btn.innerText = 'حفظ التعديلات';
            });
        };

        const addForm = document.getElementById('add-staff-form');
        if (addForm) {
            addForm.onsubmit = function(e) {
                e.preventDefault();
                const action = 'sm_add_staff_ajax';
                const formData = new FormData(this);
                if (!formData.has('action')) formData.append('action', action);
                fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        smShowNotification('تمت إضافة المستخدم بنجاح');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        smHandleAjaxError(res);
                    }
                }).catch(err => smHandleAjaxError(err));
            };
        }
    })();
    </script>
</div>
