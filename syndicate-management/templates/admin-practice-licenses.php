<?php if (!defined('ABSPATH')) exit;

$members = SM_DB::get_members(['limit' => -1]);

$stats = [
    'total' => 0,
    'expired' => 0,
    'expiring_soon' => 0,
    'active' => 0
];

$current_date = date('Y-m-d');
$soon_date = date('Y-m-d', strtotime('+30 days'));

foreach ($members as $m) {
    if (!empty($m->license_number) && !$m->license_is_deleted) {
        $stats['total']++;
        if ($m->license_expiration_date < $current_date) {
            $stats['expired']++;
        } elseif ($m->license_expiration_date <= $soon_date) {
            $stats['expiring_soon']++;
        } else {
            $stats['active']++;
        }
    }
}

$search = isset($_GET['license_search']) ? sanitize_text_field($_GET['license_search']) : '';
$registry = SM_DB::get_members([
    'search' => $search,
    'search_licenses' => true,
    'only_with_license' => true,
    'license_is_deleted' => 0,
    'orderby' => 'license_expiration_date ASC',
    'limit' => -1
]);

$deleted_registry = SM_DB::get_members([
    'search' => $search,
    'search_licenses' => true,
    'license_is_deleted' => 1,
    'orderby' => 'license_deleted_at DESC',
    'limit' => -1
]);

$can_delete = current_user_can('manage_options') || current_user_can('sm_full_access') || current_user_can('sm_branch_access');
$can_permanent_delete = current_user_can('manage_options') || current_user_can('sm_full_access');
?>

<div class="sm-practice-licenses" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h3 style="margin:0;">إدارة تصاريح مزاولة المهنة</h3>
        <div style="display:flex; gap:10px;">
            <button onclick="smOpenPrintCustomizer('practice_licenses')" class="sm-btn" style="background: #4a5568; width: auto;"><span class="dashicons dashicons-printer"></span> طباعة السجل</button>
            <button onclick="smOpenLicenseIssuanceModal()" class="sm-btn" style="width:auto;">+ إصدار / تجديد تصريح</button>
        </div>
    </div>

    <div class="sm-tabs-wrapper" style="display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
        <button class="sm-tab-btn sm-active" onclick="smOpenInternalTab('license-registry', this)">سجل التراخيص</button>
        <button class="sm-tab-btn" onclick="smOpenInternalTab('permit-requests', this)">طلبات التصاريح والامتحانات</button>
        <button class="sm-tab-btn" onclick="smOpenInternalTab('license-deleted', this)">التراخيص المحذوفة</button>
    </div>

    <div id="license-deleted" class="sm-internal-tab" style="display: none;">
        <div style="background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #c53030; font-size: 14px;">
            <span class="dashicons dashicons-warning"></span> تنبيه: التراخيص المحذوفة سيتم إزالتها نهائياً من النظام بعد مرور 3 أشهر من تاريخ الحذف.
        </div>

        <div class="sm-table-container">
            <table class="sm-table sm-table-dense">
                <thead>
                    <tr>
                        <th>العضو</th>
                        <th>رقم الترخيص</th>
                        <th>تاريخ الحذف</th>
                        <th>الوقت المتبقي</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deleted_registry)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 20px; color: #94a3b8;">لا توجد تراخيص محذوفة حالياً.</td></tr>
                    <?php else: foreach ($deleted_registry as $m):
                        $deleted_at = strtotime($m->license_deleted_at);
                        $expiry_at = strtotime('+3 months', $deleted_at);
                        $remaining = $expiry_at - time();
                        $days_left = ceil($remaining / (60 * 60 * 24));
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700;"><?php echo esc_html($m->name); ?></div>
                            <div style="font-size: 11px; color: #718096;"><?php echo esc_html($m->national_id); ?></div>
                        </td>
                        <td style="font-weight: 800; color: var(--sm-primary-color);"><?php echo esc_html($m->license_number); ?></td>
                        <td><?php echo date('Y-m-d', $deleted_at); ?></td>
                        <td>
                            <span class="sm-badge" style="background: #fffaf0; color: #975a16; border: 1px solid #fbd38d;">
                                متبقي <?php echo $days_left; ?> يوم
                            </span>
                        </td>
                        <td>
                            <div style="display:flex; gap:8px;">
                                <button onclick="smRestoreLicense(<?php echo $m->id; ?>)" class="sm-btn" style="height:28px; font-size:11px; width:auto; background:#38a169; padding: 0 10px;">استعادة</button>
                                <?php if ($can_permanent_delete): ?>
                                    <button onclick="smPermanentDeleteLicense(<?php echo $m->id; ?>)" class="sm-btn" style="height:28px; font-size:11px; width:auto; background:#e53e3e; padding: 0 10px;">حذف نهائي</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="permit-requests" class="sm-internal-tab" style="display: none;">
        <?php
            $_GET['type'] = 'permit_test';
            include SM_PLUGIN_DIR . 'templates/admin-professional-requests.php';
        ?>
    </div>

    <div id="license-registry" class="sm-internal-tab">

    <div class="sm-card-grid" style="margin-bottom: 30px;">
        <?php
        // Total Licenses
        $icon = 'dashicons-id-alt'; $label = 'إجمالي التراخيص'; $value = $stats['total']; $color = '#111F35';
        include SM_PLUGIN_DIR . 'templates/component-stat-card.php';

        // Active Licenses
        $icon = 'dashicons-yes-alt'; $label = 'تراخيص سارية'; $value = $stats['active']; $color = '#38a169';
        include SM_PLUGIN_DIR . 'templates/component-stat-card.php';

        // Expiring Soon
        $icon = 'dashicons-clock'; $label = 'تنتهي قريباً (30 يوم)'; $value = $stats['expiring_soon']; $color = '#dd6b20';
        include SM_PLUGIN_DIR . 'templates/component-stat-card.php';

        // Expired
        $icon = 'dashicons-no-alt'; $label = 'تراخيص منتهية'; $value = $stats['expired']; $color = '#e53e3e';
        include SM_PLUGIN_DIR . 'templates/component-stat-card.php';
        ?>
    </div>

    <div style="background: #f8fafc; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
        <form method="get" style="display: flex; gap: 12px; align-items: flex-end;">
            <input type="hidden" name="sm_tab" value="practice-licenses">
            <div style="flex: 1;">
                <label class="sm-label">بحث في سجل التراخيص:</label>
                <input type="text" name="license_search" class="sm-input" value="<?php echo esc_attr($search); ?>" placeholder="الاسم، الرقم القومي، أو رقم الترخيص...">
            </div>
            <button type="submit" class="sm-btn" style="width: auto;">بحث</button>
        </form>
    </div>

    <div class="sm-table-container">
        <table class="sm-table sm-table-dense">
            <thead>
                <tr>
                    <th style="width:40px;"><input type="checkbox" onclick="document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = this.checked)"></th>
                    <th>العضو</th>
                    <th>رقم الترخيص</th>
                    <th>تاريخ الإصدار</th>
                    <th>تاريخ الانتهاء</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registry as $m):
                    $is_expired = $m->license_expiration_date < $current_date;
                    $is_soon = !$is_expired && $m->license_expiration_date <= $soon_date;
                ?>
                <tr>
                    <td><input type="checkbox" class="member-checkbox" value="<?php echo $m->id; ?>"></td>
                    <td>
                        <div style="font-weight: 700;"><?php echo esc_html($m->name); ?></div>
                        <div style="font-size: 11px; color: #718096;"><?php echo esc_html($m->national_id); ?></div>
                    </td>
                    <td style="font-weight: 800; color: var(--sm-primary-color);"><?php echo esc_html($m->license_number); ?></td>
                    <td><?php echo esc_html($m->license_issue_date); ?></td>
                    <td><?php echo esc_html($m->license_expiration_date); ?></td>
                    <td>
                        <?php if ($is_expired): ?>
                            <span class="sm-badge sm-badge-high">منتهي</span>
                        <?php elseif ($is_soon): ?>
                            <span class="sm-badge sm-badge-medium">ينتهي قريباً</span>
                        <?php else: ?>
                            <span class="sm-badge sm-badge-low" style="background:#def7ec; color:#03543f;">ساري</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button onclick="smEditLicense(<?php echo $m->id; ?>)" class="sm-btn sm-btn-outline" style="height:28px; font-size:11px; width:auto; padding: 0 10px;">تعديل</button>
                            <a href="<?php echo admin_url('admin-ajax.php?action=sm_print_license&member_id='.$m->id); ?>" target="_blank" class="sm-btn" style="height:28px; font-size:11px; width:auto; background:#111F35; padding: 0 10px; display:flex; align-items:center;">طباعة</a>
                            <?php if ($can_delete): ?>
                                <button onclick="smDeleteLicense(<?php echo $m->id; ?>)" class="sm-btn" style="height:28px; font-size:11px; width:auto; background:#e53e3e; padding: 0 10px;">حذف</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- License Issuance Modal -->
<div id="sm-license-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 600px;">
        <div class="sm-modal-header">
            <h3>إصدار / تجديد تصريح مزاولة المهنة</h3>
            <button class="sm-modal-close" onclick="document.getElementById('sm-license-modal').style.display='none'">&times;</button>
        </div>
        <form id="sm-license-form" style="padding: 30px;">
            <div class="sm-form-group">
                <label class="sm-label">اختر العضو:</label>
                <select name="member_id" class="sm-select" id="license_member_select" required>
                    <option value="">-- ابحث واختر العضو --</option>
                    <?php foreach ($members as $m) echo "<option value='{$m->id}' data-license='{$m->license_number}' data-issue='{$m->license_issue_date}' data-expiry='{$m->license_expiration_date}'>{$m->name} ({$m->national_id})</option>"; ?>
                </select>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
                <div class="sm-form-group">
                    <label class="sm-label">رقم الترخيص:</label>
                    <input type="text" name="license_number" id="lic_num" class="sm-input" required>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">تاريخ الإصدار:</label>
                    <input type="date" name="license_issue_date" id="lic_issue" class="sm-input" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">تاريخ الانتهاء:</label>
                    <input type="date" name="license_expiration_date" id="lic_expiry" class="sm-input" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" required>
                </div>
            </div>
            <button type="submit" class="sm-btn" style="margin-top: 25px;">حفظ بيانات الترخيص</button>
        </form>
    </div>
</div>

<script>
function smOpenLicenseIssuanceModal() {
    document.getElementById('sm-license-form').reset();
    document.getElementById('sm-license-modal').style.display = 'flex';
    document.getElementById('lic_issue').value = '<?php echo date('Y-m-d'); ?>';
    smCalculateExpiry('lic_issue', 'lic_expiry');
}

function smCalculateExpiry(startId, endId) {
    const startDate = document.getElementById(startId).value;
    if (startDate) {
        const date = new Date(startDate);
        date.setFullYear(date.getFullYear() + 1);
        document.getElementById(endId).value = date.toISOString().split('T')[0];
    }
}

document.getElementById('lic_issue').addEventListener('change', function() {
    smCalculateExpiry('lic_issue', 'lic_expiry');
});

document.getElementById('license_member_select').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (opt.value) {
        document.getElementById('lic_num').value = opt.dataset.license || '';
        document.getElementById('lic_issue').value = opt.dataset.issue || '<?php echo date('Y-m-d'); ?>';

        if (opt.dataset.expiry) {
            document.getElementById('lic_expiry').value = opt.dataset.expiry;
        } else {
            smCalculateExpiry('lic_issue', 'lic_expiry');
        }
    }
});

window.addEventListener('load', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'new') {
        smOpenLicenseIssuanceModal();
    }
});

function smEditLicense(memberId) {
    const select = document.getElementById('license_member_select');
    select.value = memberId;
    select.dispatchEvent(new Event('change'));
    document.getElementById('sm-license-modal').style.display = 'flex';
}

document.getElementById('sm-license-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'sm_update_license_ajax');
    formData.append('nonce', '<?php echo wp_create_nonce("sm_add_member"); ?>');

    const action = 'sm_update_license_ajax';
    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم حفظ بيانات الترخيص بنجاح');
            setTimeout(() => location.reload(), 500);
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
});

function smDeleteLicense(id) {
    if (!confirm('هل أنت متأكد من حذف هذا الترخيص؟ سيتم نقله إلى التراخيص المحذوفة.')) return;
    const fd = new FormData();
    fd.append('action', 'sm_soft_delete_license');
    fd.append('id', id);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl + '?action=sm_soft_delete_license', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم حذف الترخيص بنجاح');
            setTimeout(() => location.reload(), 500);
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smRestoreLicense(id) {
    if (!confirm('هل أنت متأكد من استعادة هذا الترخيص؟')) return;
    const fd = new FormData();
    fd.append('action', 'sm_restore_license');
    fd.append('id', id);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl + '?action=sm_restore_license', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم استعادة الترخيص بنجاح');
            setTimeout(() => location.reload(), 500);
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smPermanentDeleteLicense(id) {
    if (!confirm('تحذير: سيتم حذف بيانات هذا الترخيص نهائياً من النظام. هل أنت متأكد؟')) return;
    const fd = new FormData();
    fd.append('action', 'sm_permanent_delete_license');
    fd.append('id', id);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl + '?action=sm_permanent_delete_license', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم حذف الترخيص نهائياً');
            setTimeout(() => location.reload(), 500);
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}
</script>
