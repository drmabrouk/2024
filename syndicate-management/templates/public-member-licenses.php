<?php if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$member = SM_DB::get_member_by_username(wp_get_current_user()->user_login);
if (!$member) {
    echo '<div class="sm-alert sm-alert-warning">عذراً، لم يتم العثور على بيانات العضوية المرتبطة بحسابك.</div>';
    return;
}

$requests = SM_DB::get_professional_requests(['member_id' => $member->id]);
$can_apply_facility = (in_array($member->professional_grade, ['specialist', 'consultant', 'expert']));
?>

<div class="sm-licenses-view" style="display: grid; gap: 30px;">
    <!-- Active Status Cards -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Practice Permit -->
        <div class="sm-card" style="background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                <div>
                    <h3 style="margin: 0; font-weight: 800; color: #1e293b;">تصريح مزاولة المهنة</h3>
                    <p style="margin: 5px 0 0 0; font-size: 13px; color: #64748b;">رخصة الممارسة المهنية الفردية</p>
                </div>
                <?php
                $lic_valid = ($member->license_expiration_date && $member->license_expiration_date >= date('Y-m-d'));
                $lic_status_label = empty($member->license_number) ? 'غير مقيد' : ($lic_valid ? 'ساري' : 'منتهي');
                $lic_color = empty($member->license_number) ? '#94a3b8' : ($lic_valid ? '#22c55e' : '#ef4444');
                ?>
                <span class="sm-badge" style="background: <?php echo $lic_color; ?>15; color: <?php echo $lic_color; ?>; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 12px; border: 1px solid <?php echo $lic_color; ?>40;">
                    <?php echo $lic_status_label; ?>
                </span>
            </div>

            <?php if (!empty($member->license_number)): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: #f8fafc; padding: 30px; border-radius: 8px; border: 1px solid #f1f5f9;">
                    <div><label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 3px;">رقم التصريح</label><strong style="font-size: 15px;"><?php echo esc_html($member->license_number); ?></strong></div>
                    <div><label style="font-size: 11px; color: #64748b; display: block; margin-bottom: 3px;">تاريخ الانتهاء</label><strong style="font-size: 15px; color: <?php echo $lic_color; ?>;"><?php echo esc_html($member->license_expiration_date ?: '---'); ?></strong></div>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #94a3b8; font-style: italic;">لا يوجد تصريح مزاولة مسجل حالياً</div>
            <?php endif; ?>

            <div style="margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button onclick="smSubmitProfRequest('permit_test', <?php echo $member->id; ?>)" class="sm-btn" style="height: 38px; font-size: 12px;">طلب دخول اختبار</button>
                <button onclick="smSubmitProfRequest('permit_renewal', <?php echo $member->id; ?>)" class="sm-btn sm-btn-outline" style="height: 38px; font-size: 12px;" <?php echo empty($member->license_number) ? 'disabled' : ''; ?>>طلب تجديد التصريح</button>
            </div>
        </div>

        <!-- Facility License -->
        <div class="sm-card" style="background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                <div>
                    <h3 style="margin: 0; font-weight: 800; color: #1e293b;">تراخيص المنشآت</h3>
                    <p style="margin: 5px 0 0 0; font-size: 13px; color: #64748b;">رخصة المنشأة أو الأكاديمية الرياضية</p>
                </div>
                <?php
                $fac_valid = ($member->facility_license_expiration_date && $member->facility_license_expiration_date >= date('Y-m-d'));
                $fac_status_label = empty($member->facility_number) ? 'لا توجد منشأة' : ($fac_valid ? 'ساري' : 'منتهي');
                $fac_color = empty($member->facility_number) ? '#94a3b8' : ($fac_valid ? '#0ea5e9' : '#ef4444');
                ?>
                <span class="sm-badge" style="background: <?php echo $fac_color; ?>15; color: <?php echo $fac_color; ?>; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 12px; border: 1px solid <?php echo $fac_color; ?>40;">
                    <?php echo $fac_status_label; ?>
                </span>
            </div>

            <?php if (!empty($member->facility_number)): ?>
                <div style="display: grid; gap: 10px; background: #f0f9ff; padding: 30px; border-radius: 8px; border: 1px solid #e0f2fe;">
                    <div><label style="font-size: 11px; color: #0369a1; display: block; margin-bottom: 3px;">اسم المنشأة</label><strong style="font-size: 15px;"><?php echo esc_html($member->facility_name); ?></strong></div>
                    <div style="display: flex; justify-content: space-between;">
                        <div><label style="font-size: 11px; color: #0369a1; display: block; margin-bottom: 3px;">رقم الترخيص</label><strong><?php echo esc_html($member->facility_number); ?></strong></div>
                        <div><label style="font-size: 11px; color: #0369a1; display: block; margin-bottom: 3px;">الفئة</label><span class="sm-badge sm-badge-low"><?php echo esc_html($member->facility_category); ?></span></div>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #94a3b8; font-style: italic;">لا توجد منشأة مسجلة باسمك</div>
            <?php endif; ?>

            <div style="margin-top: 20px;">
                <?php if ($can_apply_facility): ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <button onclick="smSubmitProfRequest('facility_new', <?php echo $member->id; ?>)" class="sm-btn" style="height: 38px; font-size: 12px; background: #0ea5e9;" <?php echo !empty($member->facility_number) ? 'disabled' : ''; ?>>تسجيل منشأة جديدة</button>
                        <button onclick="smSubmitProfRequest('facility_renewal', <?php echo $member->id; ?>)" class="sm-btn sm-btn-outline" style="height: 38px; font-size: 12px;" <?php echo empty($member->facility_number) ? 'disabled' : ''; ?>>طلب تجديد المنشأة</button>
                    </div>
                <?php else: ?>
                    <div style="background: #fffbeb; color: #92400e; padding: 25px; border-radius: 8px; font-size: 11px; border: 1px solid #fde68a; display: flex; gap: 8px; align-items: center;">
                        <span class="dashicons dashicons-info" style="font-size: 18px;"></span>
                        <span>يتطلب ترخيص المنشآت درجة "أخصائي" أو أعلى. الدرجة الحالية: <strong><?php echo SM_Settings::get_professional_grades()[$member->professional_grade] ?? $member->professional_grade; ?></strong></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Requests History -->
    <div style="background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0;">
        <h3 style="margin: 0 0 20px 0; font-weight: 800; display: flex; align-items: center; gap: 10px;">
            <span class="dashicons dashicons-list-view"></span> سجل طلبات التراخيص والمهنة
        </h3>

        <div class="sm-table-container" style="margin: 0;">
            <table class="sm-table">
                <thead>
                    <tr>
                        <th>نوع الطلب</th>
                        <th>تاريخ التقديم</th>
                        <th>الحالة</th>
                        <th>ملاحظات الإدارة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $type_map = [
                        'permit_test' => 'دخول اختبار مزاولة',
                        'permit_renewal' => 'تجديد تصريح مزاولة',
                        'facility_new' => 'ترخيص منشأة جديدة',
                        'facility_renewal' => 'تجديد ترخيص منشأة'
                    ];
                    $status_map = [
                        'pending' => ['قيد المراجعة', '#f59e0b'],
                        'approved' => ['تم الاعتماد', '#10b981'],
                        'rejected' => ['مرفوض', '#ef4444']
                    ];

                    if (empty($requests)): ?>
                        <tr><td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">لا توجد طلبات سابقة</td></tr>
                    <?php else: foreach ($requests as $r):
                        $s = $status_map[$r->status] ?? [$r->status, '#64748b'];
                    ?>
                        <tr>
                            <td><strong><?php echo $type_map[$r->request_type] ?? $r->request_type; ?></strong></td>
                            <td style="font-size: 12px; color: #64748b;"><?php echo date_i18n('Y/m/d - H:i', strtotime($r->created_at)); ?></td>
                            <td>
                                <span class="sm-badge" style="background: <?php echo $s[1]; ?>15; color: <?php echo $s[1]; ?>; border: 1px solid <?php echo $s[1]; ?>40;">
                                    <?php echo $s[0]; ?>
                                </span>
                            </td>
                            <td style="font-size: 12px; color: #475569; max-width: 250px;"><?php echo esc_html($r->admin_notes ?: '---'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
