<?php if (!defined('ABSPATH')) exit;

$status = $_GET['status'] ?? 'pending';
$type = $_GET['type'] ?? '';

$user = wp_get_current_user();
$is_admin = current_user_can('manage_options') || current_user_can('sm_full_access');
$my_gov = get_user_meta($user->ID, 'sm_governorate', true);

$args = ['status' => $status];
if ($type) $args['type'] = $type;
if (!$is_admin && $my_gov) $args['governorate'] = $my_gov;

$requests = SM_DB::get_professional_requests($args);
?>

<div class="sm-professional-requests-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h4 style="margin: 0; font-weight: 800; color: var(--sm-dark-color);">طلبات الترقية وتراخيص الممارسة المهنية</h4>
        <div style="display: flex; gap: 10px;">
            <select onchange="window.location.href='<?php echo add_query_arg('status', '', remove_query_arg('status')); ?>&status=' + this.value" class="sm-select" style="width: auto; height: 38px; font-size: 13px;">
                <option value="pending" <?php selected($status, 'pending'); ?>>قيد المراجعة</option>
                <option value="approved" <?php selected($status, 'approved'); ?>>تمت الموافقة</option>
                <option value="rejected" <?php selected($status, 'rejected'); ?>>مرفوضة</option>
            </select>
        </div>
    </div>

    <div class="sm-table-container" style="margin: 0;">
        <table class="sm-table">
            <thead>
                <tr>
                    <th>مقدم الطلب</th>
                    <th>نوع الطلب</th>
                    <th>الفرع</th>
                    <th>تاريخ الطلب</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $type_map = [
                    'permit_test' => 'دخول اختبار مزاولة',
                    'permit_renewal' => 'تجديد تصريح مزاولة',
                    'facility_new' => 'ترخيص منشأة جديدة',
                    'facility_renewal' => 'تجديد ترخيص منشأة',
                    'promotion_test_assistant_specialist' => 'طلب ترقية لأخصائي',
                    'promotion_test_specialist' => 'طلب ترقية لاستشاري',
                    'promotion_test_consultant' => 'طلب ترقية لخبير'
                ];
                if (empty($requests)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 25px; color: #94a3b8;">لا توجد طلبات حالياً تطابق الفلتر المختار</td></tr>
                <?php else: foreach ($requests as $r): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700;"><?php echo esc_html($r->member_name); ?></div>
                            <div style="font-size: 11px; color: #64748b;"><?php echo esc_html($r->national_id); ?></div>
                        </td>
                        <td><span class="sm-badge sm-badge-low" style="background: #f1f5f9; color: #475569;"><?php echo $type_map[$r->request_type] ?? $r->request_type; ?></span></td>
                        <td><?php echo esc_html(SM_Settings::get_branch_name($r->governorate)); ?></td>
                        <td style="font-size: 12px;"><?php echo date_i18n('Y/m/d - H:i', strtotime($r->created_at)); ?></td>
                        <td>
                            <span class="sm-badge <?php echo $r->status === 'pending' ? 'sm-badge-medium' : ($r->status === 'approved' ? 'sm-badge-high' : 'sm-badge-critical'); ?>">
                                <?php echo $r->status === 'pending' ? 'قيد المراجعة' : ($r->status === 'approved' ? 'معتمد' : 'مرفوض'); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <?php if ($r->status === 'pending'): ?>
                                    <button onclick="smProcessProfRequest(<?php echo $r->id; ?>, 'approved')" class="sm-btn" style="padding: 4px 12px; font-size: 11px; background: #10b981; width: auto;">موافقة</button>
                                    <button onclick="smProcessProfRequest(<?php echo $r->id; ?>, 'rejected')" class="sm-btn" style="padding: 4px 12px; font-size: 11px; background: #ef4444; width: auto;">رفض</button>
                                <?php endif; ?>
                                <a href="<?php echo add_query_arg(['sm_tab' => 'member-profile', 'member_id' => $r->member_id], remove_query_arg(['status', 'type'])); ?>" class="sm-btn sm-btn-outline" style="padding: 4px 12px; font-size: 11px; width: auto; text-decoration: none;">الملف</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
