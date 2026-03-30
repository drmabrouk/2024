<?php if (!defined('ABSPATH')) exit; ?>
<?php
$can_manage_members = current_user_can('sm_manage_members');
$is_admin_user = current_user_can('manage_options');
$active_tab = $_GET['sm_tab'] ?? 'members';
$is_deleted_view = ($active_tab === 'deleted-members');

$import_results = get_transient('sm_import_results_' . get_current_user_id());
if ($import_results) {
    delete_transient('sm_import_results_' . get_current_user_id());
}
?>
<div class="sm-content-wrapper" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h2 style="margin:0; font-weight: 800; color: var(--sm-dark-color);">إدارة الأعضاء وطلبات القيد</h2>
            <p style="margin:5px 0 0 0; color:#64748b; font-size:13px;">إدارة بيانات الأعضاء المسجلين، طباعة البطاقات، وعمليات الاستيراد الجماعي.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if (!$is_deleted_view && SM_Settings::can_role_access(reset(wp_get_current_user()->roles), 'add_member')): ?>
                <button onclick="document.getElementById('add-single-member-modal').style.display='flex'" class="sm-btn" style="width: 160px; height: 42px; padding: 0; display: flex; align-items: center; justify-content: center; font-weight: 700;">+ إضافة عضو جديد</button>
                <button onclick="document.getElementById('csv-import-form').style.display='block'" class="sm-btn sm-btn-secondary" style="width: 160px; height: 42px; padding: 0; display: flex; align-items: center; justify-content: center; font-weight: 700;">استيراد أعضاء (Excel)</button>
            <?php endif; ?>

            <?php if (!$is_deleted_view && SM_Settings::can_role_access(reset(wp_get_current_user()->roles), 'print_reports')): ?>
                <button onclick="smOpenPrintCustomizer('members')" class="sm-btn" style="background: #4a5568; width: 160px; height: 42px; padding: 0; display: flex; align-items: center; justify-content: center; font-weight: 700;"><span class="dashicons dashicons-printer" style="font-size: 16px; margin-left: 8px;"></span> طباعة مخصصة</button>
                <a href="<?php echo admin_url('admin-ajax.php?action=sm_print&print_type=id_card'); ?>" target="_blank" class="sm-btn sm-btn-accent" style="background: #27ae60; text-decoration:none; width: 160px; height: 42px; padding: 0; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">طباعة كافة البطاقات</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($import_results): ?>
        <div style="background: #fff; border-radius: 12px; border: 1px solid var(--sm-border-color); margin-bottom: 8px; overflow: hidden; box-shadow: var(--sm-shadow);">
            <div style="background: var(--sm-bg-light); padding: 20px 15px; border-bottom: 1px solid var(--sm-border-color); display: flex; justify-content: space-between; align-items: center;">
                <h4 style="margin:0; color: var(--sm-dark-color); font-weight: 800;">تقرير استيراد الأعضاء الأخير</h4>
                <span style="font-size: 12px; color: #718096;">إجمالي السجلات المعالجة: <?php echo $import_results['total']; ?></span>
            </div>
            <div style="padding: 15px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 8px;">
                    <div style="background: #f0fff4; padding: 15px; border-radius: 8px; border: 1px solid #c6f6d5; text-align: center;">
                        <div style="font-size: 20px; font-weight: 800; color: #2f855a;"><?php echo $import_results['success']; ?></div>
                        <div style="font-size: 12px; color: #38a169;">تم الاستيراد بنجاح</div>
                    </div>
                    <div style="background: #fffaf0; padding: 15px; border-radius: 8px; border: 1px solid #feebc8; text-align: center;">
                        <div style="font-size: 20px; font-weight: 800; color: #c05621;"><?php echo $import_results['warning']; ?></div>
                        <div style="font-size: 12px; color: #dd6b20;">تنبيهات (بيانات ناقصة)</div>
                    </div>
                    <div style="background: #fff5f5; padding: 15px; border-radius: 8px; border: 1px solid #fed7d7; text-align: center;">
                        <div style="font-size: 20px; font-weight: 800; color: #c53030;"><?php echo $import_results['error']; ?></div>
                        <div style="font-size: 12px; color: #e53e3e;">أخطاء (فشل الاستيراد)</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div style="background: white; padding: 15px; border: 1px solid var(--sm-border-color); border-radius: var(--sm-radius); margin-bottom: 8px; box-shadow: var(--sm-shadow);">
        <form method="get" style="display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
            <input type="hidden" name="sm_tab" value="<?php echo esc_attr($active_tab); ?>">

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label">بحث:</label>
                <input type="text" name="member_search" class="sm-input" value="<?php echo esc_attr(isset($_GET['member_search']) ? $_GET['member_search'] : ''); ?>" placeholder="الاسم، الرقم القومي، رقم العضوية...">
            </div>

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label">الدرجة الوظيفية:</label>
                <select name="grade_filter" class="sm-select">
                    <option value="">كل الدرجات</option>
                    <?php foreach (SM_Settings::get_professional_grades() as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected(isset($_GET['grade_filter']) && $_GET['grade_filter'] == $k); ?>><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label">التخصص:</label>
                <select name="spec_filter" class="sm-select">
                    <option value="">كل التخصصات</option>
                    <?php foreach (SM_Settings::get_specializations() as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected(isset($_GET['spec_filter']) && $_GET['spec_filter'] == $k); ?>><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm-form-group" style="margin-bottom:0;">
                <label class="sm-label">الفرع:</label>
                <select name="gov_filter" class="sm-select">
                    <option value="">كل الفروع</option>
                    <?php
                    $db_branches = SM_DB::get_branches_data();
                    if (!empty($db_branches)) {
                        foreach($db_branches as $db) echo "<option value='".esc_attr($db->slug)."' ".selected($_GET['gov_filter'] ?? '', $db->slug, false).">".esc_html($db->name)."</option>";
                    } else {
                        foreach (SM_Settings::get_governorates() as $k => $v) echo "<option value='$k' ".selected($_GET['gov_filter'] ?? '', $k, false).">$v</option>";
                    }
                    ?>
                </select>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="sm-btn">بحث</button>
                <a href="<?php echo add_query_arg('sm_tab', $active_tab, remove_query_arg(['member_search', 'grade_filter', 'spec_filter', 'gov_filter', 'paged'])); ?>" class="sm-btn sm-btn-outline" style="text-decoration:none;">إعادة ضبط</a>
            </div>
        </form>
    </div>

    <?php if (SM_Settings::can_role_access(reset(wp_get_current_user()->roles), 'add_member')): ?>
    <!-- Excel Import Interface -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <div id="csv-import-form" style="display:none; background: #f8fafc; padding: 25px; border: 2px dashed #cbd5e0; border-radius: 15px; margin-bottom: 20px; box-shadow: var(--sm-shadow);">
        <h3 style="margin-top:0; color:var(--sm-primary-color); font-weight: 800;">استيراد الأعضاء الذكي (Excel / XLSX / CSV)</h3>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 20px; line-height: 1.6;">
            يرجى اختيار ملف Excel يحتوي على بيانات الأعضاء. يدعم النظام الاستيراد المباشر مع دمج البيانات والتحقق التلقائي من الخيارات.<br>
            <strong style="color:var(--sm-dark-color);">ترتيب الأعمدة المطلوب (A-Z):</strong> الاسم، رقم القيد، الرقم القومي، فرع القيد، الدرجة العلمية، القسم، تاريخ المؤهل، رقم العضوية، تاريخ البدء، مديونية العضوية، الرتبة، التخصص، رقم الترخيص، تاريخ الترخيص، مديونية الترخيص، اسم المنشأة، رقم المنشأة، فئة المنشأة، تاريخ منشأة، مديونية منشأة، البريد، الهاتف، محافظة الإقامة، المدينة، العنوان، ملاحظات.
        </p>

        <div style="display: flex; gap: 15px; align-items: center; background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0;">
            <input type="file" id="sm_excel_import_file" accept=".xlsx, .xls, .csv" style="flex: 1; padding: 10px;">
            <button type="button" id="sm_start_excel_import" class="sm-btn" style="width: auto; background: #27ae60; padding: 0 30px; height: 45px;">بدء المعالجة والاستيراد</button>
            <button type="button" onclick="document.getElementById('csv-import-form').style.display='none'" class="sm-btn sm-btn-outline" style="width: auto; height: 45px;">إغلاق</button>
        </div>

        <!-- Progress Overlay -->
        <div id="import-progress-wrap" style="display:none; margin-top: 25px; background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0;">
            <div style="display:flex; justify-content:space-between; margin-bottom: 10px;">
                <span id="import-status-text" style="font-weight:700; font-size:13px; color:var(--sm-dark-color);">جاري تحليل الملف...</span>
                <span id="import-percent-text" style="font-weight:800; color:var(--sm-primary-color);">0%</span>
            </div>
            <div style="height:10px; background:#f1f5f9; border-radius:10px; overflow:hidden;">
                <div id="import-progress-bar" style="width:0%; height:100%; background:var(--sm-primary-color); transition: 0.3s;"></div>
            </div>
            <div id="import-log" style="margin-top:15px; max-height:150px; overflow-y:auto; font-size:11px; color:#64748b; line-height:1.5;"></div>
        </div>

        <div style="margin-top: 20px; font-size: 11px; color: #e53e3e; font-weight: 700;">* سيتم استخدام الرقم القومي كاسم مستخدم وكلمة مرور افتراضية. في حال عدم وجود بريد إلكتروني، سيقوم النظام بإنشائه تلقائياً.</div>
    </div>
    <?php endif; ?>

    <div class="sm-table-container">
        <table class="sm-table sm-table-dense">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="select-all-members" onclick="toggleAllMembers(this)"></th>
                    <th>الرقم القومي</th>
                    <th>الاسم</th>
                    <th>الدرجة الوظيفية</th>
                    <th>التخصص</th>
                    <th>الفرع</th>
                    <th>رقم العضوية</th>
                    <th>المبلغ المستحق</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $limit = 20;
                $offset = ($current_page - 1) * $limit;
                $members = SM_DB::get_members(array(
                    'search' => $_GET['member_search'] ?? '',
                    'professional_grade' => $_GET['grade_filter'] ?? '',
                    'specialization' => $_GET['spec_filter'] ?? '',
                    'governorate' => $_GET['gov_filter'] ?? '',
                    'is_deleted' => $is_deleted_view ? 1 : 0,
                    'limit' => $limit,
                    'offset' => $offset
                ));

                if (!empty($members)) {
                    SM_Finance::prefetch_data(array_map(function($m) { return $m->id; }, $members));
                }

                if (empty($members)): ?>
                    <tr><td colspan="9" style="padding: 15px; text-align: center;">لا يوجد أعضاء يطابقون البحث.</td></tr>
                <?php else:
                    $grades = SM_Settings::get_professional_grades();
                    $specs = SM_Settings::get_specializations();
                    $statuses = SM_Settings::get_membership_statuses();
                    foreach ($members as $member):
                        $finance = SM_Finance::calculate_member_dues($member);
                        $target_user = $member->wp_user_id ? get_userdata($member->wp_user_id) : false;
                        $member_is_admin_role = $target_user && !empty($target_user->roles) && array_intersect($target_user->roles, ['administrator', 'sm_general_officer', 'sm_branch_officer']);

                        $member_data = (array)$member;
                        $member_data['is_admin_role'] = $member_is_admin_role;
                        if ($target_user) {
                            $member_data['user_login'] = $target_user->user_login;
                            $member_data['account_status'] = get_user_meta($member->wp_user_id, "sm_account_status", true) ?: "active";
                        }
                    ?>
                        <tr id="member-row-<?php echo $member->id; ?>">
                            <td><input type="checkbox" class="member-checkbox" value="<?php echo $member->id; ?>"></td>
                            <td style="font-weight: 700; color: var(--sm-primary-color);"><?php echo esc_html($member->national_id); ?></td>
                            <td style="font-weight: 800;"><?php echo esc_html($member->name); ?></td>
                            <td><?php echo $member_is_admin_role ? '<span class="sm-badge sm-badge-high">حساب إداري</span>' : esc_html($grades[$member->professional_grade] ?? $member->professional_grade); ?></td>
                            <td><?php echo $member_is_admin_role ? '---' : esc_html($specs[$member->specialization] ?? $member->specialization); ?></td>
                            <td><?php echo esc_html(SM_Settings::get_branch_name($member->governorate)); ?></td>
                            <td><?php echo $member_is_admin_role ? '---' : esc_html($member->membership_number); ?></td>
                            <td style="font-weight:700; color:<?php echo $finance['balance'] > 0 ? '#e53e3e' : '#38a169'; ?>;"><?php echo number_format($finance['balance'], 2); ?></td>
                            <td>
                                <div style="display: flex; gap: 5px; justify-content: flex-end;">
                                    <?php if (!$is_deleted_view): ?>
                                        <a href="<?php echo add_query_arg('sm_tab', 'member-profile'); ?>&member_id=<?php echo $member->id; ?>" class="sm-btn sm-btn-outline" style="padding: 4px 10px; font-size: 11px; height: 28px; text-decoration:none; display:flex; align-items:center;">عرض</a>
                                        <?php if (SM_Settings::can_role_access(reset(wp_get_current_user()->roles), 'edit_member')): ?>
                                            <button onclick='editSmMember(<?php echo esc_attr(json_encode($member_data)); ?>)' class="sm-btn sm-btn-outline" style="padding: 4px 10px; font-size: 11px; height: 28px; color: #2c3e50; border-color: #2c3e50;">تعديل</button>
                                        <?php endif; ?>
                                        <?php if ($is_admin_user): ?>
                                            <button onclick='smOpenMemberAccountModal(<?php echo esc_attr(json_encode(["id" => $member->id, "wp_user_id" => $member->wp_user_id, "name" => $member->name, "email" => $member->email])); ?>)' class="sm-btn" style="padding: 4px 10px; font-size: 11px; height: 28px; background: #2c3e50;">الحساب</button>
                                        <?php endif; ?>
                                        <button onclick="smArchiveMember(<?php echo $member->id; ?>, '<?php echo esc_js($member->name); ?>')" class="sm-btn" style="padding: 4px 10px; font-size: 11px; height: 28px; background: #e53e3e;">حذف</button>
                                    <?php else: ?>
                                        <button onclick="smRestoreMember(<?php echo $member->id; ?>, '<?php echo esc_js($member->name); ?>')" class="sm-btn" style="padding: 4px 10px; font-size: 11px; height: 28px; background: #38a169;">استعادة</button>
                                        <?php if ($is_admin_user): ?>
                                            <button onclick="smPermanentDeleteMember(<?php echo $member->id; ?>, '<?php echo esc_js($member->name); ?>')" class="sm-btn" style="padding: 4px 10px; font-size: 11px; height: 28px; background: #c53030;">حذف نهائي</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php
    $total_members = SM_DB::count_members([
        'search' => $_GET['member_search'] ?? '',
        'governorate' => $_GET['gov_filter'] ?? '',
        'is_deleted' => $is_deleted_view ? 1 : 0
    ]);
    $limit = 20;
    $total_pages = ceil($total_members / $limit);
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    if ($total_pages > 1):
    ?>
    <div class="sm-pagination" style="margin-top: 20px; display: flex; gap: 5px; justify-content: center;">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="<?php echo add_query_arg('paged', $i); ?>" class="sm-btn <?php echo $i == $current_page ? '' : 'sm-btn-outline'; ?>" style="padding: 5px 12px; min-width: 40px; text-align: center;"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php if (SM_Settings::can_role_access(reset(wp_get_current_user()->roles), 'add_member')): ?>
    <div id="add-single-member-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 900px;">
            <div class="sm-modal-header"><h3>تسجيل عضو جديد</h3><button class="sm-modal-close" onclick="document.getElementById('add-single-member-modal').style.display='none'">&times;</button></div>
            <form id="add-member-form">
                <?php wp_nonce_field('sm_add_member', 'sm_nonce'); ?>
                <div style="padding: 15px;">
                    <div style="display: grid; grid-template-columns: 2fr 1.2fr; gap: 15px; margin-bottom: 8px;">
                        <div class="sm-form-group"><input name="name" type="text" class="sm-input" required placeholder="الاسم كما في الهوية الوطنية"></div>
                        <div class="sm-form-group"><input name="national_id" type="text" class="sm-input" required maxlength="14" placeholder="الرقم القومي (14 رقم)"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 8px;">
                        <div class="sm-form-group"><input name="email" type="email" class="sm-input" placeholder="البريد الإلكتروني"></div>
                        <div class="sm-form-group"><input name="phone" type="text" class="sm-input" placeholder="رقم الهاتف الجوال"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 8px;">
                        <div class="sm-form-group">
                            <select name="residence_governorate" class="sm-select" required>
                                <option value="">-- محافظة الإقامة --</option>
                                <?php foreach(SM_Settings::get_governorates() as $k=>$v) echo "<option value='$k'>$v</option>"; ?>
                            </select>
                        </div>
                        <div class="sm-form-group"><input name="residence_city" type="text" class="sm-input" required placeholder="مدينة الإقامة"></div>
                    </div>

                    <div class="sm-form-group" style="margin-bottom: 8px;">
                        <input name="residence_street" type="text" class="sm-input" required placeholder="العنوان بالتفصيل">
                    </div>

                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                        <div class="sm-form-group">
                            <select name="professional_grade" class="sm-select">
                                <option value="">-- الدرجة الوظيفية --</option>
                                <?php foreach (SM_Settings::get_professional_grades() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                            </select>
                        </div>
                        <div class="sm-form-group">
                            <select name="academic_degree" class="sm-select">
                                <option value="">-- الدرجة العلمية --</option>
                                <?php foreach (SM_Settings::get_academic_degrees() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                            </select>
                        </div>
                        <div class="sm-form-group">
                            <select name="governorate" class="sm-select" required>
                                <option value="">-- فرع القيد --</option>
                                <?php
                                $db_branches = SM_DB::get_branches_data();
                                $current_user_gov = get_user_meta(get_current_user_id(), 'sm_governorate', true);
                                $has_full = current_user_can('sm_full_access') || current_user_can('manage_options');

                                if (!empty($db_branches)) {
                                    foreach($db_branches as $db) {
                                        $selected = (!$has_full && $current_user_gov === $db->slug) ? 'selected' : '';
                                        $disabled = (!$has_full && $current_user_gov !== $db->slug) ? 'disabled' : '';
                                        echo "<option value='".esc_attr($db->slug)."' $selected $disabled>".esc_html($db->name)."</option>";
                                    }
                                } else {
                                    foreach (SM_Settings::get_governorates() as $k => $v) {
                                        $selected = (!$has_full && $current_user_gov === $k) ? 'selected' : '';
                                        $disabled = (!$has_full && $current_user_gov !== $k) ? 'disabled' : '';
                                        echo "<option value='$k' $selected $disabled>$v</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="sm-form-group"><select name="university" class="sm-select add-cascading" required><option value="">-- اختر الجامعة --</option><?php foreach(SM_Settings::get_universities() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><select name="faculty" class="sm-select add-cascading" required disabled><option value="">-- اختر الكلية --</option><?php foreach(SM_Settings::get_faculties() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><select name="department" class="sm-select add-cascading" required disabled><option value="">-- اختر القسم --</option><?php foreach(SM_Settings::get_departments() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><select name="specialization" class="sm-select add-cascading" required disabled><option value="">-- اختر التخصص --</option><?php foreach (SM_Settings::get_specializations() as $k => $v) echo "<option value='$k'>$v</option>"; ?></select></div>

                        <div class="sm-form-group"><input name="membership_number" type="text" class="sm-input" placeholder="رقم القيد / العضوية"></div>
                        <div class="sm-form-group"><input name="membership_start_date" id="add_mem_start" type="date" class="sm-input" onchange="smCalculateDateExpiry('add_mem_start', 'add_mem_expiry')" title="تاريخ بدء العضوية"></div>
                        <div class="sm-form-group"><input name="membership_expiration_date" id="add_mem_expiry" type="date" class="sm-input" title="تاريخ انتهاء العضوية"></div>
                    </div>
                </div>
                <button type="submit" class="sm-btn">إضافة العضو</button>
            </form>
        </div>
    </div>

    <div id="edit-member-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 900px;">
            <div class="sm-modal-header"><h3>تعديل بيانات العضو</h3><button class="sm-modal-close" onclick="document.getElementById('edit-member-modal').style.display='none'">&times;</button></div>
            <form id="edit-member-form">
                <?php wp_nonce_field('sm_add_member', 'sm_nonce'); ?>
                <input type="hidden" name="member_id" id="edit_member_id_hidden">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; padding: 15px;">
                    <div class="sm-form-group"><label class="sm-label">الاسم الكامل:</label><input name="name" id="edit_name" type="text" class="sm-input" required></div>

                    <div class="sm-admin-fields-group" style="display: none;">
                        <div class="sm-form-group"><label class="sm-label">اسم المستخدم (Username):</label><input type="text" id="edit_username" class="sm-input" readonly style="background:#f8fafc;"></div>
                    </div>

                    <div class="sm-form-group"><label class="sm-label">البريد الإلكتروني:</label><input name="email" id="edit_email" type="email" class="sm-input"></div>
                    <div class="sm-form-group"><label class="sm-label">رقم الهاتف:</label><input name="phone" id="edit_phone_input" type="text" class="sm-input"></div>

                    <div class="sm-admin-fields-group" style="display: none;">
                        <div class="sm-form-group">
                            <label class="sm-label">حالة الحساب:</label>
                            <select name="account_status" id="edit_account_status" class="sm-select">
                                <option value="active">نشط</option>
                                <option value="restricted">مقيد / موقوف</option>
                            </select>
                        </div>
                    </div>

                    <div class="sm-form-group"><label class="sm-label">الفرع:</label><select name="governorate" id="edit_gov" class="sm-select"><?php
                        $db_branches = SM_DB::get_branches_data();
                        if (!empty($db_branches)) {
                            foreach($db_branches as $db) echo "<option value='".esc_attr($db->slug)."'>".esc_html($db->name)."</option>";
                        } else {
                            foreach (SM_Settings::get_governorates() as $k => $v) echo "<option value='$k'>$v</option>";
                        }
                    ?></select></div>

                    <div class="sm-membership-fields-group" style="display: contents;">
                        <div class="sm-form-group"><label class="sm-label">الدرجة الوظيفية:</label><select name="professional_grade" id="edit_grade" class="sm-select"><?php foreach (SM_Settings::get_professional_grades() as $k => $v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">الجامعة:</label><select name="university" id="edit_university" class="sm-select edit-cascading"><?php foreach(SM_Settings::get_universities() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">الكلية:</label><select name="faculty" id="edit_faculty" class="sm-select edit-cascading"><?php foreach(SM_Settings::get_faculties() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">القسم:</label><select name="department" id="edit_department" class="sm-select edit-cascading"><?php foreach(SM_Settings::get_departments() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">التخصص:</label><select name="specialization" id="edit_spec" class="sm-select edit-cascading"><?php foreach (SM_Settings::get_specializations() as $k => $v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">فرع الميلاد:</label><input name="province_of_birth" id="edit_birth_prov" type="text" class="sm-input"></div>
                        <div class="sm-form-group"><label class="sm-label">الدرجة العلمية:</label><select name="academic_degree" id="edit_degree" class="sm-select"><?php foreach (SM_Settings::get_academic_degrees() as $k => $v) echo "<option value='$k'>$v</option>"; ?></select></div>
                        <div class="sm-form-group"><label class="sm-label">تاريخ بدء العضوية:</label><input name="membership_start_date" id="edit_mem_start_input" type="date" class="sm-input" onchange="smCalculateDateExpiry('edit_mem_start_input', 'edit_mem_expiry_input')"></div>
                        <div class="sm-form-group"><label class="sm-label">تاريخ انتهاء العضوية:</label><input name="membership_expiration_date" id="edit_mem_expiry_input" type="date" class="sm-input"></div>
                    </div>
                </div>
                <button type="submit" class="sm-btn">تحديث البيانات</button>
            </form>
        </div>
    </div>
    <div id="member-account-modal" class="sm-modal-overlay">
        <div class="sm-modal-content" style="max-width: 500px;">
            <div class="sm-modal-header">
                <h3>إعدادات حساب المستخدم: <span id="acc_member_name"></span></h3>
                <button class="sm-modal-close" onclick="document.getElementById('member-account-modal').style.display='none'">&times;</button>
            </div>
            <form id="member-account-form">
                <?php wp_nonce_field('sm_admin_action', 'nonce'); ?>
                <input type="hidden" name="member_id" id="acc_member_id">
                <input type="hidden" name="wp_user_id" id="acc_wp_user_id">
                <div style="padding: 15px;">
                    <div class="sm-form-group">
                        <label class="sm-label">البريد الإلكتروني:</label>
                        <input name="email" id="acc_email" type="email" class="sm-input" required>
                    </div>
                    <div class="sm-form-group">
                        <label class="sm-label">كلمة مرور جديدة (اتركها فارغة إذا لم ترد التغيير):</label>
                        <input name="password" type="password" class="sm-input">
                    </div>
                    <?php if (current_user_can('manage_options')): ?>
                    <div class="sm-form-group">
                        <label class="sm-label">الدور / الصلاحيات:</label>
                        <select name="role" id="acc_role" class="sm-select" onchange="smToggleAccountRank(this)">
                            <option value="sm_member">عضو نقابة (افتراضي)</option>
                            <option value="sm_branch_officer">مسؤول نقابة</option>
                            <option value="sm_general_officer">مسؤول النقابة العامة</option>
                            <option value="administrator">مدير نظام</option>
                        </select>
                    </div>
                    <div class="sm-form-group" id="acc_gov_group" style="display:none;">
                        <label class="sm-label">الفرع الملحق به:</label>
                        <select name="governorate" id="acc_governorate" class="sm-select">
                            <option value="">-- اختر الفرع --</option>
                            <?php foreach(SM_DB::get_branches_data() as $db) echo "<option value='".esc_attr($db->slug)."'>".esc_html($db->name)."</option>"; ?>
                        </select>
                    </div>
                    <div class="sm-form-group" id="acc_rank_group" style="display:none;">
                        <label class="sm-label">الرتبة / المسمى النقابي:</label>
                        <select name="rank" id="acc_rank" class="sm-select">
                            <option value="">-- اختر الرتبة --</option>
                            <?php foreach(SM_Settings::get_professional_grades() as $rk => $rv) echo "<option value='".esc_attr($rk)."'>".esc_html($rv)."</option>"; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button type="submit" class="sm-btn" style="flex: 1;">حفظ التغييرات</button>
                        <button type="button" class="sm-btn sm-btn-outline" style="flex: 1;" onclick="document.getElementById('member-account-modal').style.display='none'">إلغاء</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    (function() {
        // EXCEL IMPORT LOGIC
        document.getElementById('sm_start_excel_import')?.addEventListener('click', function() {
            const fileInput = document.getElementById('sm_excel_import_file');
            if (!fileInput.files.length) {
                smShowNotification('يرجى اختيار ملف أولاً', true);
                return;
            }

            const file = fileInput.files[0];
            const reader = new FileReader();
            const btn = this;
            const progressWrap = document.getElementById('import-progress-wrap');
            const progressBar = document.getElementById('import-progress-bar');
            const statusText = document.getElementById('import-status-text');
            const percentText = document.getElementById('import-percent-text');
            const importLog = document.getElementById('import-log');

            btn.disabled = true;
            progressWrap.style.display = 'block';
            importLog.innerHTML = '<div>بدء قراءة الملف...</div>';

            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array', cellDates: true });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: "A", defval: "" });

                    // Skip header row if it looks like one (assuming Row 1 is header)
                    const rowsToProcess = jsonData.slice(1);
                    if (rowsToProcess.length === 0) {
                        throw new Error('الملف المختار فارغ أو لا يحتوي على بيانات صحيحة.');
                    }

                    statusText.innerText = 'تم تحليل الملف. جاري إرسال البيانات للمعالجة...';
                    importLog.innerHTML += `<div>تم العثور على ${rowsToProcess.length} سجل. يتم الآن الرفع للملقم...</div>`;

                    // Process in batches of 50 to avoid timeout
                    const batchSize = 50;
                    let processed = 0;

                    async function sendBatch(startIndex) {
                        const batch = rowsToProcess.slice(startIndex, startIndex + batchSize);
                        const fd = new FormData();
                        fd.append('action', 'sm_import_members_json');
                        fd.append('members_data', JSON.stringify(batch));
                        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

                        const response = await fetch(ajaxurl, { method: 'POST', body: fd });
                        const res = await response.json();

                        if (res.success) {
                            processed += batch.length;
                            const percent = Math.round((processed / rowsToProcess.length) * 100);
                            progressBar.style.width = percent + '%';
                            percentText.innerText = percent + '%';
                            statusText.innerText = `جاري معالجة السجلات (${processed} من ${rowsToProcess.length})`;
                            importLog.innerHTML += `<div style="color:#38a169;">Batch OK: +${batch.length} records</div>`;

                            if (processed < rowsToProcess.length) {
                                sendBatch(processed);
                            } else {
                                statusText.innerText = 'اكتمل الاستيراد بنجاح!';
                                smShowNotification('اكتملت عملية الاستيراد بنجاح');
                                setTimeout(() => location.reload(), 1500);
                            }
                        } else {
                            throw new Error(res.data?.message || 'فشل معالجة الدفعة.');
                        }
                    }

                    sendBatch(0).catch(err => {
                        console.error(err);
                        smHandleAjaxError(err);
                        btn.disabled = false;
                        statusText.innerText = 'حدث خطأ أثناء الاستيراد';
                    });

                } catch (err) {
                    smShowNotification(err.message, true);
                    btn.disabled = false;
                }
            };
            reader.readAsArrayBuffer(file);
        });

        window.smArchiveMember = function(id, name) {
            if (!confirm('هل أنت متأكد من نقل العضو "' + name + '" إلى الأرشيف (المحذوفات)؟')) return;

            const fd = new FormData();
            fd.append('action', 'sm_delete_member_ajax');
            fd.append('member_id', id);
            fd.append('nonce', '<?php echo wp_create_nonce("sm_delete_member"); ?>');

            fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    smShowNotification(res.data.message);
                    location.reload();
                } else {
                    smHandleAjaxError(res);
                }
            }).catch(err => smHandleAjaxError(err));
        };

        window.smRestoreMember = function(id, name) {
            if (!confirm('هل أنت متأكد من استعادة العضو "' + name + '" إلى القائمة النشطة؟')) return;

            const fd = new FormData();
            fd.append('action', 'sm_restore_member_ajax');
            fd.append('member_id', id);
            fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

            fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    smShowNotification(res.data.message);
                    location.reload();
                } else {
                    smHandleAjaxError(res);
                }
            }).catch(err => smHandleAjaxError(err));
        };

        window.smPermanentDeleteMember = function(id, name) {
            if (!confirm('تحذير نهائي: هل أنت متأكد من حذف العضو "' + name + '" نهائياً من النظام؟ لا يمكن التراجع عن هذا الإجراء وسيتم حذف حساب المستخدم المرتبط به أيضاً.')) return;

            const fd = new FormData();
            fd.append('action', 'sm_permanent_delete_member_ajax');
            fd.append('member_id', id);
            fd.append('nonce', '<?php echo wp_create_nonce("sm_delete_member"); ?>');

            fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    smShowNotification(res.data.message);
                    location.reload();
                } else {
                    smHandleAjaxError(res);
                }
            }).catch(err => smHandleAjaxError(err));
        };

        window.smCalculateDateExpiry = function(startId, endId) {
            const startEl = document.getElementById(startId);
            const endEl = document.getElementById(endId);
            if (startEl && endEl && startEl.value) {
                const date = new Date(startEl.value);
                date.setFullYear(date.getFullYear() + 1);
                endEl.value = date.toISOString().split('T')[0];
            }
        };

        window.editSmMember = function(s) {
            document.getElementById('edit_member_id_hidden').value = s.id;
            document.getElementById('edit_name').value = s.name;
            document.getElementById('edit_grade').value = s.professional_grade;
            document.getElementById('edit_university').value = s.university || "";
            document.getElementById('edit_faculty').value = s.faculty || "";
            document.getElementById('edit_department').value = s.department || "";
            document.getElementById('edit_spec').value = s.specialization || "";
            document.getElementById('edit_degree').value = s.academic_degree || "";
            document.getElementById('edit_birth_prov').value = s.province_of_birth || "";
            document.getElementById('edit_gov').value = s.governorate;
            document.getElementById('edit_email').value = s.email || "";
            document.getElementById('edit_phone_input').value = s.phone || "";
            document.getElementById('edit_mem_start_input').value = s.membership_start_date;
            document.getElementById('edit_mem_expiry_input').value = s.membership_expiration_date;

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

            // Enable cascading fields if values exist
            const fac = document.getElementById('edit_faculty');
            const dept = document.getElementById('edit_department');
            const spec = document.getElementById('edit_spec');
            if (s.university) fac.disabled = false;
            if (s.faculty) dept.disabled = false;
            if (s.department) spec.disabled = false;

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
        applyCascading("#add-member-form .add-cascading");
        applyCascading("#edit-member-form .edit-cascading");

        window.toggleAllMembers = function(master) {
            document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = master.checked);
        };

        window.smToggleAccountRank = function(roleSelect) {
            const rankGroup = document.getElementById('acc_rank_group');
            const govGroup = document.getElementById('acc_gov_group');
            if (roleSelect.value === 'sm_member') {
                rankGroup.style.display = 'none';
                govGroup.style.display = 'none';
            } else {
                rankGroup.style.display = 'block';
                govGroup.style.display = 'block';
            }
        };

        window.smOpenMemberAccountModal = function(data) {
            const mid = document.getElementById('acc_member_id');
            const uid = document.getElementById('acc_wp_user_id');
            const name = document.getElementById('acc_member_name');
            const email = document.getElementById('acc_email');
            const roleSelect = document.getElementById('acc_role');
            const govSelect = document.getElementById('acc_governorate');
            const rankSelect = document.getElementById('acc_rank');

            if (mid) mid.value = data.id || '';
            if (uid) uid.value = data.wp_user_id || '';
            if (name) name.innerText = data.name || '';
            if (email) email.value = data.email || '';

            if (roleSelect && data.wp_user_id) {
                const action = 'sm_get_user_role';
                fetch(ajaxurl + '?action=' + action + '&user_id=' + data.wp_user_id + '&nonce=<?php echo wp_create_nonce("sm_admin_action"); ?>')
                .then(r => r.json()).then(res => {
                    if (res.success && res.data) {
                        roleSelect.value = res.data.role || 'sm_member';
                        if (govSelect) govSelect.value = res.data.governorate || '';
                        if (rankSelect) rankSelect.value = res.data.rank || '';
                        smToggleAccountRank(roleSelect);
                    } else if (!res.success) {
                        smHandleAjaxError(res);
                    }
                }).catch(err => smHandleAjaxError(err));
            }

            const modal = document.getElementById('member-account-modal');
            if (modal) modal.style.display = 'flex';
        };

        const addMemberForm = document.getElementById('add-member-form');
        if (addMemberForm) {
            addMemberForm.onsubmit = function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                if (btn) { btn.disabled = true; btn.innerText = 'جاري الإضافة...'; }

                const action = 'sm_add_member_ajax';
                const formData = new FormData(this);
                if (!formData.has('action')) formData.append('action', action);

                fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
                .then(r => {
                    if (!r.ok) throw r;
                    return r.json();
                })
                .then(res => {
                    if(res.success) {
                        smShowNotification('تم إضافة العضو بنجاح');
                        setTimeout(() => {
                            location.href = location.pathname + '?page=' + (new URLSearchParams(window.location.search).get('page') || '') + '&sm_tab=members';
                        }, 800);
                    } else {
                        smHandleAjaxError(res);
                        if (btn) { btn.disabled = false; btn.innerText = 'إضافة العضو'; }
                    }
                }).catch(err => {
                    smHandleAjaxError(err);
                    if (btn) { btn.disabled = false; btn.innerText = 'إضافة العضو'; }
                });
            };
        }

        const accMemberForm = document.getElementById('member-account-form');
        if (accMemberForm) {
            accMemberForm.onsubmit = function(e) {
                e.preventDefault();
                const action = 'sm_update_member_account_ajax';
                const formData = new FormData(this);
                if (!formData.has('action')) formData.append('action', action);
                fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
                .then(r => {
                    if (!r.ok) throw r;
                    return r.json();
                })
                .then(res => {
                    if(res.success) {
                        smShowNotification('تم تحديث بيانات الحساب بنجاح');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        smHandleAjaxError(res);
                    }
                }).catch(err => smHandleAjaxError(err));
            };
        }

        const editMemberForm = document.getElementById('edit-member-form');
        if (editMemberForm) {
            editMemberForm.onsubmit = function(e) {
                e.preventDefault();
                const action = 'sm_update_member_ajax';
                const formData = new FormData(this);
                if (!formData.has('action')) formData.append('action', action);
                fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
                .then(r => {
                    if (!r.ok) throw r;
                    return r.json();
                })
                .then(res => {
                    if(res.success) location.reload();
                    else smHandleAjaxError(res);
                }).catch(err => smHandleAjaxError(err));
            };
        }
    })();
    </script>
</div>
