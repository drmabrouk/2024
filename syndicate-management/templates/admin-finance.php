<?php if (!defined('ABSPATH')) exit;

$stats = SM_Finance::get_financial_stats();
$search = isset($_GET['member_search']) ? sanitize_text_field($_GET['member_search']) : '';
$gov_filter = isset($_GET['gov_filter']) ? sanitize_text_field($_GET['gov_filter']) : '';
$members = SM_DB::get_members(['search' => $search, 'governorate' => $gov_filter]);

if (!empty($members)) {
    SM_Finance::prefetch_data(array_map(function($m) { return $m->id; }, $members));
}

$members_with_balance = [];
foreach ($members as $m) {
    $dues = SM_Finance::calculate_member_dues($m);
    if ($dues['balance'] > 0 || !empty($search)) {
        $m->finance = $dues;
        $members_with_balance[] = $m;
    }
}
?>

<div class="sm-finance-registry" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin:0; font-weight:800; color:var(--sm-dark-color);">إدارة الموارد والاستحقاقات المالية</h2>
        <div style="display:flex; gap:10px;">
             <button onclick="smOpenPrintCustomizer('finance')" class="sm-btn" style="background: #4a5568; width: auto; height: 42px; padding: 0 20px;"><span class="dashicons dashicons-printer"></span> طباعة مخصصة</button>
             <div class="sm-actions-dropdown" style="position:relative; display:inline-block;">
                <button class="sm-btn" style="background: #2c3e50; width: auto;"><span class="dashicons dashicons-media-spreadsheet"></span> تقارير الاستحقاقات <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 10px;"></span></button>
                <div class="sm-actions-content" style="left:0; right:auto;">
                    <a href="<?php echo admin_url('admin-ajax.php?action=sm_export_finance_report&type=overdue_membership'); ?>" target="_blank" class="sm-action-item">
                        <span class="dashicons dashicons-id"></span> متأخرات العضوية
                    </a>
                    <a href="<?php echo admin_url('admin-ajax.php?action=sm_export_finance_report&type=unpaid_fines'); ?>" target="_blank" class="sm-action-item">
                        <span class="dashicons dashicons-warning"></span> الغرامات غير المسددة
                    </a>
                    <a href="<?php echo admin_url('admin-ajax.php?action=sm_export_finance_report&type=full_liabilities'); ?>" target="_blank" class="sm-action-item">
                        <span class="dashicons dashicons-calculator"></span> تقرير المديونيات الشامل
                    </a>
                </div>
             </div>
             <button onclick="location.reload()" class="sm-btn sm-btn-outline" style="width:auto;"><span class="dashicons dashicons-update"></span> تحديث البيانات</button>
        </div>
    </div>

    <div class="sm-tabs-wrapper" style="display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 2px solid #eee; overflow-x: auto; white-space: nowrap; padding-bottom: 0;">
        <a href="<?php echo add_query_arg('sm_tab', 'finance'); ?>" class="sm-tab-btn <?php echo $_GET['sm_tab'] == 'finance' ? 'sm-active' : ''; ?>" style="text-decoration:none;">سجل المستحقات والمديونيات</a>
        <a href="<?php echo add_query_arg('sm_tab', 'financial-logs'); ?>" class="sm-tab-btn <?php echo $_GET['sm_tab'] == 'financial-logs' ? 'sm-active' : ''; ?>" style="text-decoration:none;">سجل العمليات المالية الشامل</a>
    </div>

    <?php if ($_GET['sm_tab'] === 'finance'): ?>
    <!-- Overall Metrics -->
    <div class="sm-card-grid" style="margin-bottom: 30px;">
        <?php
        // Total Collected Amount
        $icon = 'dashicons-money-alt'; $label = 'إجمالي المبالغ المحصلة'; $value = number_format($stats['total_paid'], 2); $color = '#38a169'; $suffix = 'ج.م';
        include SM_PLUGIN_DIR . 'templates/component-stat-card.php';

        // Total Overdue Receivables
        $icon = 'dashicons-warning'; $label = 'إجمالي المستحقات المتأخرة'; $value = number_format($stats['total_balance'], 2); $color = '#dd6b20'; $suffix = 'ج.م';
        include SM_PLUGIN_DIR . 'templates/component-stat-card.php';

        // Total Assigned Penalties
        $icon = 'dashicons-hammer'; $label = 'إجمالي الغرامات المقررة'; $value = number_format($stats['total_penalty'], 2); $color = '#e53e3e'; $suffix = 'ج.م';
        include SM_PLUGIN_DIR . 'templates/component-stat-card.php';

        // Total Claims Value
        $icon = 'dashicons-calculator'; $label = 'القيمة الإجمالية للمطالبات'; $value = number_format($stats['total_owed'], 2); $color = '#111F35'; $suffix = 'ج.م';
        include SM_PLUGIN_DIR . 'templates/component-stat-card.php';
        ?>
    </div>

    <!-- Search & Filter -->
    <div style="background: #f8fafc; padding: 25px; border-radius: 15px; border: 1px solid #e2e8f0; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
        <form method="get" style="display: grid; grid-template-columns: 1.5fr 1fr 1fr auto; gap: 12px; align-items: flex-end;">
            <input type="hidden" name="sm_tab" value="finance">
            <div class="sm-form-group" style="margin:0;">
                <label class="sm-label" style="font-size:12px; font-weight:700; color:#64748b;">البحث عن عضو (الاسم/الرقم القومي):</label>
                <input type="text" name="member_search" class="sm-input" value="<?php echo esc_attr($search); ?>" placeholder="أدخل بيانات العضو...">
            </div>
            <div class="sm-form-group" style="margin:0;">
                <label class="sm-label" style="font-size:12px; font-weight:700; color:#64748b;">تصفية حسب الفرع:</label>
                <select name="gov_filter" class="sm-select">
                    <option value="">كل الفروع</option>
                    <?php
                    $db_branches = SM_DB::get_branches_data();
                    foreach($db_branches as $db) echo "<option value='".esc_attr($db->slug)."' ".selected($_GET['gov_filter'] ?? '', $db->slug, false).">".esc_html($db->name)."</option>";
                    ?>
                </select>
            </div>
            <div class="sm-form-group" style="margin:0;">
                <label class="sm-label" style="font-size:12px; font-weight:700; color:#64748b;">الحالة المالية:</label>
                <select name="status_filter" class="sm-select">
                    <option value="">كافة السجلات</option>
                    <option value="debt" <?php selected($_GET['status_filter'] ?? '', 'debt'); ?>>مدين فقط</option>
                    <option value="cleared" <?php selected($_GET['status_filter'] ?? '', 'cleared'); ?>>خالص فقط</option>
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="sm-btn" style="width: auto; height: 42px; padding: 0 20px; font-weight:800;">تطبيق البحث</button>
                <?php if ($search || !empty($_GET['gov_filter']) || !empty($_GET['status_filter'])): ?>
                    <a href="<?php echo remove_query_arg(['member_search', 'gov_filter', 'status_filter']); ?>" class="sm-btn sm-btn-outline" style="width: auto; height: 42px; text-decoration:none; display:flex; align-items:center; padding: 0 15px; font-size:11px;">تصفير</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Members Balance Table -->
    <div class="sm-table-container">
        <table class="sm-table sm-table-dense">
            <thead>
                <tr>
                    <th style="width:40px;"><input type="checkbox" onclick="document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = this.checked)"></th>
                    <th>العضو</th>
                    <th>الرقم القومي</th>
                    <th>المستحق</th>
                    <th>المسدد</th>
                    <th>المتبقي</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members_with_balance)): ?>
                    <tr><td colspan="7" style="text-align:center; padding: 30px; color: #718096;">لا توجد مديونيات قائمة بناءً على معايير البحث.</td></tr>
                <?php else: ?>
                    <?php foreach ($members_with_balance as $m): ?>
                        <tr>
                            <td><input type="checkbox" class="member-checkbox" value="<?php echo $m->id; ?>"></td>
                            <td>
                                <div style="font-weight: 700; color: var(--sm-dark-color);"><?php echo esc_html($m->name); ?></div>
                                <div style="font-size: 11px; color: #718096;"><?php echo esc_html($m->membership_number); ?></div>
                            </td>
                            <td style="font-family: monospace;"><?php echo esc_html($m->national_id); ?></td>
                            <td style="font-weight: 600;"><?php echo number_format($m->finance['total_owed'], 2); ?></td>
                            <td style="color: #38a169; font-weight: 600;"><?php echo number_format($m->finance['total_paid'], 2); ?></td>
                            <td style="color: #e53e3e; font-weight: 800;"><?php echo number_format($m->finance['balance'], 2); ?></td>
                            <td>
                                <?php if ($m->finance['balance'] <= 0): ?>
                                    <span class="sm-badge sm-badge-low" style="background:#def7ec; color:#03543f;">خالص</span>
                                <?php else: ?>
                                    <span class="sm-badge sm-badge-high">مدين</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button onclick="smOpenFinanceModal(<?php echo $m->id; ?>)" class="sm-btn" style="height: 28px; font-size: 10px; width: auto; background: #111F35;">تفاصيل / سداد</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?php include SM_PLUGIN_DIR . 'templates/admin-financial-logs.php'; ?>
    <?php endif; ?>
</div>

