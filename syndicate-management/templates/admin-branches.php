<?php if (!defined('ABSPATH')) exit;
$mgmt_stats = SM_DB::get_branch_management_stats();
$can_manage_all = current_user_can('sm_full_access') || current_user_can('manage_options');
$current_user_gov = get_user_meta(get_current_user_id(), 'sm_governorate', true);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="sm-content-wrapper">
    <!-- Header & Action -->
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h2 style="margin:0; font-weight:800; color:var(--sm-dark-color);">إدارة فروع ولجان النقابة</h2>
            <p style="margin:5px 0 0 0; color:#64748b; font-size:13px;">إدارة التواجد الجغرافي، اللجان، والرسوم المالية الخاصة بالفروع.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="smOpenPrintCustomizer('branches')" class="sm-btn" style="background: #4a5568; width: auto; height: 42px; padding: 0 20px;"><span class="dashicons dashicons-printer"></span> طباعة مخصصة</button>
            <?php if ($can_manage_all): ?>
                <button onclick="smOpenBranchModal()" class="sm-btn" style="width:auto; padding:0 25px; height:42px;">+ إضافة فرع جديد</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Stats (Exact Dashboard Layout) -->
    <div class="sm-card-grid" style="margin-bottom: 30px;">
        <?php
        $stat_items = [
            ['label' => 'إجمالي الفروع', 'value' => $mgmt_stats['total_branches'], 'icon' => 'dashicons-location', 'color' => '#3182ce'],
            ['label' => 'الأعضاء (كافة الفروع)', 'value' => $mgmt_stats['total_members'], 'icon' => 'dashicons-admin-users', 'color' => '#38a169'],
            ['label' => 'تراخيص المزاولة', 'value' => $mgmt_stats['total_practice_licenses'], 'icon' => 'dashicons-id-alt', 'color' => '#e67e22'],
            ['label' => 'تراخيص المنشآت', 'value' => $mgmt_stats['total_facility_licenses'], 'icon' => 'dashicons-building', 'color' => '#e53e3e'],
        ];
        foreach ($stat_items as $s):
            $icon = $s['icon']; $label = $s['label']; $value = $s['value']; $color = $s['color'];
            include SM_PLUGIN_DIR . 'templates/component-stat-card.php';
        endforeach; ?>
    </div>

    <!-- Advanced Search Engine -->
    <div style="background:#fff; padding:25px; border-radius:15px; border:1px solid #e2e8f0; margin-bottom:30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; align-items: flex-end;">
            <div class="sm-form-group">
                <label class="sm-label">البحث بالاسم أو المدير:</label>
                <input type="text" id="sm-branch-search-q" class="sm-input" placeholder="اكتب للبحث..." oninput="smHandleBranchSearch()">
            </div>
            <div class="sm-form-group">
                <label class="sm-label">الموقع / العنوان:</label>
                <input type="text" id="sm-branch-search-loc" class="sm-input" placeholder="محافظة أو مدينة..." oninput="smHandleBranchSearch()">
            </div>
            <div class="sm-form-group">
                <label class="sm-label">الحالة:</label>
                <select id="sm-branch-search-status" class="sm-select" onchange="smHandleBranchSearch()">
                    <option value="">كافة الحالات</option>
                    <option value="active">نشط / معلن</option>
                    <option value="hidden">مخفي من القوائم</option>
                </select>
            </div>
            <button onclick="smHandleBranchSearch()" class="sm-btn sm-btn-outline" style="height:42px;"><span class="dashicons dashicons-filter" style="font-size:16px; margin-top:4px;"></span> تصفية النتائج</button>
        </div>
    </div>

    <div id="sm-branches-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:25px;">
        <?php
        $branches = SM_DB::get_branches_data();
        if (empty($branches)): ?>
            <div style="grid-column: 1/-1; text-align:center; padding:50px; background:#fff; border-radius:15px; border:1px dashed #cbd5e0;">
                <p style="color:#718096;">لا توجد فروع مطابقة للبحث.</p>
            </div>
        <?php else: foreach($branches as $b):
            $is_hidden = !($b->is_active ?? 1);
            $can_edit = $can_manage_all || ($current_user_gov === $b->slug);
            $b_stats = SM_DB_Finance::get_statistics(['governorate' => $b->slug]);
        ?>
            <div class="sm-branch-card-complex"
                 data-name="<?php echo esc_attr($b->name); ?>"
                 data-manager="<?php echo esc_attr($b->manager); ?>"
                 data-address="<?php echo esc_attr($b->address); ?>"
                 data-committees="<?php echo esc_attr($b->committees); ?>"
                 data-status="<?php echo $is_hidden ? 'hidden' : 'active'; ?>"
                 style="background:<?php echo $is_hidden ? '#f8fafc' : '#fff'; ?>; border:1px solid #e2e8f0; border-radius:20px; overflow:hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); transition:0.3s; opacity:<?php echo $is_hidden ? '0.75' : '1'; ?>;">

                <div style="padding: 25px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px;">
                        <div style="width:50px; height:50px; background:#fff; border:1px solid #edf2f7; border-radius:12px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                            <?php if (!empty($b->logo_url)): ?>
                                <img src="<?php echo esc_url($b->logo_url); ?>" style="width:100%; height:100%; object-fit:contain;">
                            <?php else: ?>
                                <span class="dashicons dashicons-location" style="color:var(--sm-primary-color);"></span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; gap:5px;">
                            <button onclick="smViewBranchDetailedPage(<?php echo $b->id; ?>)" class="sm-btn sm-btn-outline" style="padding:6px 12px; font-size:11px; width:auto; height:auto; color:var(--sm-dark-color) !important;">عرض التفاصيل</button>
                            <?php if ($can_edit): ?>
                                <button onclick='smEditBranch(<?php echo esc_attr(json_encode($b)); ?>)' class="sm-btn" style="padding:6px 12px; font-size:11px; width:auto; height:auto;">تعديل</button>
                            <?php endif; ?>
                            <?php if ($can_manage_all): ?>
                                <button onclick="smDeleteBranch(<?php echo $b->id; ?>, '<?php echo esc_js($b->name); ?>')" class="sm-btn" style="padding:6px 12px; font-size:11px; width:auto; height:auto; background:#e53e3e;">حذف</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h3 style="margin:0 0 5px 0; font-weight:800; color:var(--sm-dark-color);"><?php echo esc_html($b->name); ?></h3>
                    <div style="font-size:12px; color:#64748b; margin-bottom:15px; display:flex; align-items:center; gap:5px;">
                        <span class="dashicons dashicons-admin-site" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php echo esc_html($b->slug); ?>
                        <?php if ($is_hidden): ?>
                            <span style="background:#edf2f7; color:#4a5568; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; margin-right:10px;">مخفي</span>
                        <?php endif; ?>
                    </div>

                    <div style="background: #f8fafc; border-radius: 12px; padding: 15px; margin-bottom: 20px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; border: 1px solid #edf2f7;">
                        <div style="text-align: center;">
                            <div style="font-size: 9px; color: #94a3b8; font-weight: 700; margin-bottom: 2px;">أعضاء</div>
                            <div style="font-size: 13px; font-weight: 800; color: var(--sm-dark-color);"><?php echo number_format($b_stats['total_members']); ?></div>
                        </div>
                        <div style="text-align: center; border-right: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0;">
                            <div style="font-size: 9px; color: #94a3b8; font-weight: 700; margin-bottom: 2px;">تراخيص</div>
                            <div style="font-size: 13px; font-weight: 800; color: var(--sm-dark-color);"><?php echo number_format($b_stats['total_practice_licenses']); ?></div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 9px; color: #94a3b8; font-weight: 700; margin-bottom: 2px;">منشآت</div>
                            <div style="font-size: 13px; font-weight: 800; color: var(--sm-dark-color);"><?php echo number_format($b_stats['total_facility_licenses']); ?></div>
                        </div>
                    </div>
                </div>

                <div style="background:#f1f5f9; padding:15px 25px; border-top:1px solid #e2e8f0; display:grid; gap:8px;">
                    <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:#4a5568;">
                        <span class="dashicons dashicons-admin-users" style="font-size:16px; width:16px; height:16px; color:var(--sm-primary-color);"></span>
                        <strong>المدير:</strong> <?php echo esc_html($b->manager ?: 'غير محدد'); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Detailed View Modal (Dynamic) -->
<div id="sm-branch-detail-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 1000px;">
        <div class="sm-modal-header">
            <h3>تفاصيل الفرع الشاملة</h3>
            <button class="sm-modal-close" onclick="document.getElementById('sm-branch-detail-modal').style.display='none'">&times;</button>
        </div>
        <div id="sm-branch-detail-body" style="padding:30px;"></div>
    </div>
</div>

<!-- Professional Edit Modal -->
<div id="sm-branch-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 900px; padding:0;">
        <div class="sm-modal-header" style="padding:25px 30px; margin:0;">
            <h3 id="sm-branch-modal-title">إدارة بيانات الفرع</h3>
            <button class="sm-modal-close" onclick="document.getElementById('sm-branch-modal').style.display='none'">&times;</button>
        </div>

        <div style="display:flex; background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:0 20px;">
            <button class="sm-branch-tab-btn active" onclick="smSwitchBranchTab('basic', this)">البيانات الأساسية</button>
            <button class="sm-branch-tab-btn" onclick="smSwitchBranchTab('banking', this)">البيانات البنكية</button>
            <button class="sm-branch-tab-btn" onclick="smSwitchBranchTab('visibility', this)">الخصوصية والظهور</button>
            <button class="sm-branch-tab-btn" onclick="smSwitchBranchTab('finance', this)">الرسوم والخدمات</button>
            <button class="sm-branch-tab-btn" onclick="smSwitchBranchTab('location', this); smInitMap();">الموقع الجغرافي</button>
        </div>

        <form id="sm-branch-form">
            <input type="hidden" name="id" id="sm_branch_id">
            <input type="hidden" name="logo_url" id="sm_branch_logo_url">
            <input type="hidden" name="latitude" id="sm_branch_lat">
            <input type="hidden" name="longitude" id="sm_branch_lng">

            <div style="max-height:60vh; overflow-y:auto; padding:30px;">
                <!-- Basic -->
                <div id="sm-branch-tab-basic" class="sm-branch-tab-content active">
                    <div style="display:flex; gap:30px; margin-bottom:25px; align-items:center;">
                        <div style="width:120px; height:120px; background:#f1f5f9; border-radius:15px; display:flex; align-items:center; justify-content:center; border:2px dashed #cbd5e0; overflow:hidden; position:relative; flex-shrink:0;">
                            <img id="sm_branch_logo_preview" src="" style="width:100%; height:100%; object-fit:contain; display:none;">
                            <div id="sm_branch_logo_placeholder" style="text-align:center; color:#94a3b8;">
                                <span class="dashicons dashicons-camera" style="font-size:30px; width:30px; height:30px;"></span>
                                <div style="font-size:10px; font-weight:700;">شعار الفرع</div>
                            </div>
                            <button type="button" onclick="smUploadBranchLogo()" style="position:absolute; inset:0; opacity:0; cursor:pointer; width:100%;"></button>
                        </div>
                        <div style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div class="sm-form-group"><label class="sm-label">اسم الفرع / اللجنة:</label><input type="text" name="name" class="sm-input" required></div>
                            <div class="sm-form-group"><label class="sm-label">الكود (Slug):</label><input type="text" name="slug" id="sm_branch_slug_edit" class="sm-input" required <?php echo !$can_manage_all ? 'readonly' : ''; ?>></div>
                            <div class="sm-form-group"><label class="sm-label">اسم المدير:</label><input type="text" name="manager" class="sm-input"></div>
                            <div class="sm-form-group"><label class="sm-label">رقم التواصل:</label><input type="text" name="phone" class="sm-input"></div>
                        </div>
                    </div>
                    <div class="sm-form-group"><label class="sm-label">البريد الإلكتروني:</label><input type="email" name="email" class="sm-input"></div>
                    <div class="sm-form-group"><label class="sm-label">العنوان التفصيلي:</label><input type="text" name="address" class="sm-input"></div>
                    <div class="sm-form-group"><label class="sm-label">اللجان المنبثقة (فصل بفاصلة):</label><input type="text" name="committees" class="sm-input"></div>
                </div>

                <!-- Banking -->
                <div id="sm-branch-tab-banking" class="sm-branch-tab-content" style="display:none;">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <div class="sm-form-group"><label class="sm-label">اسم البنك:</label><input type="text" name="bank_name" class="sm-input"></div>
                        <div class="sm-form-group"><label class="sm-label">رقم الآيبان (IBAN):</label><input type="text" name="bank_iban" class="sm-input"></div>
                        <div class="sm-form-group"><label class="sm-label">انستا باي (Instapay):</label><input type="text" name="instapay_id" class="sm-input"></div>
                        <div class="sm-form-group"><label class="sm-label">المحفظة الإلكترونية:</label><input type="text" name="digital_wallet" class="sm-input"></div>
                    </div>
                    <h5 style="margin:20px 0 15px 0; font-weight:800;">طرق الدفع المفعلة:</h5>
                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px;">
                        <label class="sm-check-label"><input type="checkbox" name="payment_methods[]" value="cash"> نقدى / يدوي</label>
                        <label class="sm-check-label"><input type="checkbox" name="payment_methods[]" value="transfer"> تحويل بنكي</label>
                        <label class="sm-check-label"><input type="checkbox" name="payment_methods[]" value="wallet"> محفظة إلكترونية</label>
                    </div>
                </div>

                <!-- Visibility -->
                <div id="sm-branch-tab-visibility" class="sm-branch-tab-content" style="display:none;">
                    <div style="background:#fffaf0; border:1px solid #feebc8; padding:20px; border-radius:12px; margin-bottom:20px;">
                        <h5 style="margin:0 0 10px 0; color:#c05621; font-weight:800;">إعدادات الظهور</h5>
                        <p style="margin:0; font-size:13px; color:#7b341e;">تحكم في مدى ظهور الفرع في استمارات التسجيل العامة وقوائم النظام.</p>
                    </div>
                    <label class="sm-check-label" style="background:#f8fafc; padding:20px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:20px; display:flex;">
                        <input type="checkbox" name="is_active" id="sm_branch_active_check" value="1" style="width:22px; height:22px;">
                        تفعيل الفرع (ظهور في قوائم التسجيل والنظام)
                    </label>
                    <h5 style="margin:20px 0 10px 0; font-weight:800;">قواعد الوصول للبيانات:</h5>
                    <div class="sm-form-group">
                        <select name="privacy_settings[access_rule]" class="sm-select">
                            <option value="standard">افتراضي: المسؤول يرى أعضاء فرعه فقط</option>
                            <option value="restricted">مقيد: يرى بيانات محدودة للأعضاء</option>
                            <option value="open">مفتوح: المسؤول يرى كافة الأعضاء (للفروع الإشرافية)</option>
                        </select>
                    </div>
                </div>

                <!-- Fees -->
                <div id="sm-branch-tab-finance" class="sm-branch-tab-content" style="display:none;">
                    <div style="background:#ebf8ff; border:1px solid #bee3f8; padding:20px; border-radius:12px; margin-bottom:25px;">
                        <h5 style="margin:0 0 10px 0; color:#2b6cb0; font-weight:800;">تخصيص الرسوم المالية للفرع</h5>
                        <p style="margin:0; font-size:13px; color:#2c5282;">اترك الحقل فارغاً لاستخدام السعر الموحد. القيم هنا ستطبق فقط على أعضاء هذا الفرع.</p>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                        <?php
                        $fees_labels = ['membership_new' => 'انضمام عضوية', 'membership_renewal' => 'تجديد سنوي', 'license_new' => 'ترخيص مزاولة', 'license_renewal' => 'تجديد ترخيص', 'license_penalty' => 'غرامة تأخير', 'test_entry_fee' => 'رسوم اختبارات'];
                        foreach ($fees_labels as $fk => $fl): ?>
                            <div class="sm-form-group">
                                <label class="sm-label"><?php echo $fl; ?>:</label>
                                <input type="number" name="fees[<?php echo $fk; ?>]" class="sm-input sm-fee-input" placeholder="السعر الموحد">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Geolocation -->
                <div id="sm-branch-tab-location" class="sm-branch-tab-content" style="display:none;">
                    <p style="font-size:12px; color:#64748b; margin-bottom:15px;">انقر على الخريطة لتحديد موقع الفرع بدقة، أو ابحث عن العنوان.</p>
                    <div id="sm-branch-map-picker" style="height:350px; border-radius:12px; border:1px solid #e2e8f0; background:#f8fafc;"></div>
                </div>
            </div>

            <div style="padding:25px 30px; border-top:1px solid #e2e8f0; text-align:center;">
                <button type="submit" id="sm-save-branch-btn" class="sm-btn" style="width:auto; padding:0 60px; height:50px; font-size:16px;">حفظ وتطبيق التغييرات</button>
            </div>
        </form>
    </div>
</div>

<style>
.sm-branch-tab-btn { padding: 18px 25px; border:none; background:none; cursor:pointer; font-weight:800; font-size:13px; color:#64748b; border-bottom: 3px solid transparent; transition: 0.3s; }
.sm-branch-tab-btn.active { color:var(--sm-primary-color); border-bottom-color:var(--sm-primary-color); background:#fff; }
.sm-branch-card-complex:hover { transform: translateY(-5px); }
.sm-check-label { display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:700; font-size:13px; color:var(--sm-dark-color); }
</style>

<script>
let branchPickerMap, branchPickerMarker;

window.smInitMap = function() {
    if (branchPickerMap) return;

    const lat = parseFloat(document.getElementById('sm_branch_lat').value) || 30.0444;
    const lng = parseFloat(document.getElementById('sm_branch_lng').value) || 31.2357;

    branchPickerMap = L.map('sm-branch-map-picker').setView([lat, lng], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(branchPickerMap);

    branchPickerMarker = L.marker([lat, lng], {draggable: true}).addTo(branchPickerMap);

    branchPickerMap.on('click', function(e) {
        branchPickerMarker.setLatLng(e.latlng);
        smUpdateCoords(e.latlng);
    });

    branchPickerMarker.on('dragend', function(e) {
        smUpdateCoords(e.target.getLatLng());
    });
};

function smUpdateCoords(latlng) {
    document.getElementById('sm_branch_lat').value = latlng.lat;
    document.getElementById('sm_branch_lng').value = latlng.lng;
}

window.smOpenBranchModal = function() {
    document.getElementById('sm-branch-form').reset();
    document.getElementById('sm_branch_id').value = '';
    document.getElementById('sm_branch_slug_edit').readOnly = false;
    document.getElementById('sm_branch_logo_preview').style.display = 'none';
    document.getElementById('sm_branch_logo_placeholder').style.display = 'block';
    document.getElementById('sm_branch_lat').value = '';
    document.getElementById('sm_branch_lng').value = '';
    smSwitchBranchTab('basic', document.querySelector('.sm-branch-tab-btn'));
    document.getElementById('sm-branch-modal').style.display = 'flex';
};

window.smEditBranch = function(b) {
    const f = document.getElementById('sm-branch-form');
    document.getElementById('sm_branch_id').value = b.id;
    f.name.value = b.name;
    f.slug.value = b.slug;
    f.manager.value = b.manager || '';
    f.phone.value = b.phone || '';
    f.email.value = b.email || '';
    f.address.value = b.address || '';
    f.committees.value = b.committees || '';
    f.bank_name.value = b.bank_name || '';
    f.bank_iban.value = b.bank_iban || '';
    f.instapay_id.value = b.instapay_id || '';
    f.digital_wallet.value = b.digital_wallet || '';
    f.is_active.checked = (b.is_active != 0);
    f.logo_url.value = b.logo_url || '';
    f.latitude.value = b.latitude || '';
    f.longitude.value = b.longitude || '';

    if (b.logo_url) {
        document.getElementById('sm_branch_logo_preview').src = b.logo_url;
        document.getElementById('sm_branch_logo_preview').style.display = 'block';
        document.getElementById('sm_branch_logo_placeholder').style.display = 'none';
    }

    // Payment Methods
    if (b.payment_methods) {
        const methods = JSON.parse(b.payment_methods);
        f.querySelectorAll('input[name="payment_methods[]"]').forEach(cb => cb.checked = methods.includes(cb.value));
    }

    // Fees
    document.querySelectorAll('.sm-fee-input').forEach(input => input.value = '');
    if (b.fees) {
        const fees = JSON.parse(b.fees);
        for (const [k, v] of Object.entries(fees)) {
            const input = f.querySelector(`[name="fees[${k}]"]`);
            if (input) input.value = v;
        }
    }

    document.getElementById('sm-branch-modal-title').innerText = 'تعديل بيانات: ' + b.name;
    smSwitchBranchTab('basic', document.querySelector('.sm-branch-tab-btn'));
    document.getElementById('sm-branch-modal').style.display = 'flex';
};

window.smSwitchBranchTab = function(tab, btn) {
    document.querySelectorAll('.sm-branch-tab-content').forEach(c => c.style.display = 'none');
    document.querySelectorAll('.sm-branch-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('sm-branch-tab-' + tab).style.display = 'block';
    btn.classList.add('active');
    if (tab === 'location' && branchPickerMap) {
        setTimeout(() => branchPickerMap.invalidateSize(), 200);
    }
};

window.smUploadBranchLogo = function() {
    const frame = wp.media({ title: 'اختر شعار الفرع', button: { text: 'استخدام كشعار للفرع' }, multiple: false });
    frame.on('select', function() {
        const att = frame.state().get('selection').first().toJSON();
        document.getElementById('sm_branch_logo_url').value = att.url;
        document.getElementById('sm_branch_logo_preview').src = att.url;
        document.getElementById('sm_branch_logo_preview').style.display = 'block';
        document.getElementById('sm_branch_logo_placeholder').style.display = 'none';
    });
    frame.open();
};

window.smViewBranchDetailedPage = function(id) {
    const modal = document.getElementById('sm-branch-detail-modal');
    const body = document.getElementById('sm-branch-detail-body');
    modal.style.display = 'flex';
    body.innerHTML = '<div style="text-align:center; padding:50px;"><div class="sm-loader-mini"></div><p>جاري تحميل البيانات التفصيلية...</p></div>';

    const action = 'sm_get_branch_details';
    fetch(ajaxurl + '?action=' + action + '&id=' + id)
    .then(r => r.json())
    .then(res => {
        if (res.success && res.data && res.data.branch) {
            const b = res.data.branch;
            const s = res.data.stats || { total_members: 0, total_revenue: 0 };
            let html = `
                <div style="display:grid; grid-template-columns: 280px 1fr; gap:40px;">
                    <div style="text-align:center;">
                        <img src="${b.logo_url || ''}" style="width:100%; border-radius:20px; border:1px solid #eee; margin-bottom:20px; padding:20px; background:#f9fafb;">
                        <h2 style="margin:0; font-weight:900;">${b.name || 'بدون اسم'}</h2>
                        <span class="sm-badge sm-badge-high" style="margin-top:10px;">فرع رسمي معتمد</span>
                    </div>
                    <div>
                        <div class="sm-card-grid" style="grid-template-columns: repeat(2, 1fr); margin-bottom:30px;">
                            <div class="sm-stat-card-modern"><div class="sm-stat-card-info"><div class="sm-stat-card-label">الأعضاء المسجلين</div><div class="sm-stat-card-value">${s.total_members || 0}</div></div></div>
                            <div class="sm-stat-card-modern"><div class="sm-stat-card-info"><div class="sm-stat-card-label">إيرادات المحافظة</div><div class="sm-stat-card-value">${s.total_revenue || 0} ج.م</div></div></div>
                        </div>
                        <h4 style="border-bottom:2px solid #f1f5f9; padding-bottom:10px; margin-bottom:20px;">معلومات التواصل والمقر</h4>
                        <div style="display:grid; gap:12px; font-size:14px;">
                            <div><strong>مدير الفرع:</strong> ${b.manager || '---'}</div>
                            <div><strong>رقم الهاتف:</strong> ${b.phone || '---'}</div>
                            <div><strong>البريد:</strong> ${b.email || '---'}</div>
                            <div><strong>العنوان:</strong> ${b.address || '---'}</div>
                        </div>
                        ${b.latitude ? `<div id="branch-static-map" style="height:200px; margin-top:20px; border-radius:12px; border:1px solid #eee;"></div>` : ''}
                    </div>
                </div>
            `;
            body.innerHTML = html;
            if (b.latitude && b.longitude) {
                setTimeout(() => {
                    const mapContainer = document.getElementById('branch-static-map');
                    if (mapContainer) {
                        try {
                            const map = L.map('branch-static-map', {zoomControl: false, dragging: false}).setView([b.latitude, b.longitude], 14);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                            L.marker([b.latitude, b.longitude]).addTo(map);
                        } catch(e) { console.error('Leaflet Error:', e); }
                    }
                }, 100);
            }
        } else {
            smHandleAjaxError(res);
            body.innerHTML = '<p style="text-align:center; color:red;">فشل تحميل بيانات الفرع.</p>';
        }
    }).catch(err => {
        smHandleAjaxError(err);
        body.innerHTML = '<p style="text-align:center; color:red;">حدث خطأ في الاتصال.</p>';
    });
};

window.smHandleBranchSearch = function() {
    const q = document.getElementById('sm-branch-search-q').value.toLowerCase();
    const loc = document.getElementById('sm-branch-search-loc').value.toLowerCase();
    const st = document.getElementById('sm-branch-search-status').value;

    document.querySelectorAll('.sm-branch-card-complex').forEach(card => {
        const name = card.getAttribute('data-name').toLowerCase();
        const manager = card.getAttribute('data-manager').toLowerCase();
        const address = card.getAttribute('data-address').toLowerCase();
        const status = card.getAttribute('data-status');

        const matchQ = !q || name.includes(q) || manager.includes(q);
        const matchLoc = !loc || address.includes(loc);
        const matchSt = !st || status === st;

        card.style.display = (matchQ && matchLoc && matchSt) ? 'block' : 'none';
    });
};

document.getElementById('sm-branch-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const action = 'sm_save_branch';
    const fd = new FormData(this);
    fd.append('action', action);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    const btn = document.getElementById('sm-save-branch-btn');
    btn.disabled = true; btn.innerText = 'جاري الحفظ...';

    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
    .then(r => {
        if (!r.ok) throw r;
        return r.json();
    })
    .then(res => {
        if (res.success) {
            smShowNotification('تم حفظ بيانات الفرع بنجاح');
            setTimeout(() => location.reload(), 1000);
        } else {
            smHandleAjaxError(res);
            btn.disabled = false;
            btn.innerText = 'حفظ وتطبيق التغييرات';
        }
    }).catch(err => {
        smHandleAjaxError(err);
        btn.disabled = false;
        btn.innerText = 'حفظ وتطبيق التغييرات';
    });
});

window.smDeleteBranch = function(id, name) {
    if (!confirm('هل أنت متأكد من حذف فرع "' + name + '" نهائياً؟ لا يمكن التراجع عن هذا الإجراء.')) return;

    const fd = new FormData();
    fd.append('action', 'sm_delete_branch');
    fd.append('id', id);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl + '?action=sm_delete_branch', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم حذف الفرع بنجاح');
            location.reload();
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
};
</script>
