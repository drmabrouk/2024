<?php
if (!defined('ABSPATH')) exit;

$accent_color = get_option('sm_verify_accent_color', '#F63049');
$show_membership = get_option('sm_verify_show_membership', 1);
$show_practice = get_option('sm_verify_show_practice', 1);
$show_facility = get_option('sm_verify_show_facility', 1);
?>
<div class="sm-verify-portal" dir="rtl" style="max-width: 950px; margin: 30px auto; padding: 0 15px; font-family: 'Rubik', sans-serif;">

    <!-- Minimal Professional Header -->
    <div style="text-align: center; margin-bottom: 30px;" class="sm-portal-header">
        <h2 style="margin: 0; font-weight: 900; font-size: 1.6em; color: var(--sm-dark-color); border: none; padding: 0; letter-spacing: -0.5px;"><?php echo esc_html(get_option('sm_verify_title', 'بوابة التحقق المهني الموحدة')); ?></h2>
        <div style="width: 30px; height: 3px; background: <?php echo esc_attr($accent_color); ?>; margin: 8px auto; border-radius: 2px;"></div>
        <p style="color: #64748b; font-size: 13px; margin-top: 5px; font-weight: 500; max-width: 500px; margin-left: auto; margin-right: auto; line-height: 1.4;"><?php echo esc_html(get_option('sm_verify_desc', 'استعلام فوري ومعتمد من السجلات الرسمية للنقابة لخدمة الأعضاء والمؤسسات.')); ?></p>
    </div>

    <!-- Compact Unified Search Control Center -->
    <div style="background: #fff; padding: 5px; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); margin-bottom: 20px; position: relative;" id="sm-search-container">
        <form id="sm-verify-form" style="margin: 0;">
            <div style="display: flex; gap: 5px; align-items: stretch;">
                <div style="width: 170px; flex-shrink: 0;">
                    <select id="sm-verify-type" class="sm-select" style="height: 42px; border-radius: 10px; border: 1px solid #f1f5f9; background: #f8fafc; font-weight: 700; width: 100%; font-size: 12.5px; padding: 0 10px; cursor: pointer; transition: 0.3s; color: var(--sm-dark-color); outline: none;">
                        <option value="auto">كشف تلقائي ذكي</option>
                        <option value="national_id">الرقم القومي (14 رقم)</option>
                        <option value="membership">رقم القيد النقابي</option>
                        <option value="practice">ترخيص مزاولة المهنة</option>
                        <option value="facility">ترخيص المنشأة</option>
                        <option value="tracking">كود تتبع الطلبات</option>
                    </select>
                </div>
                <div style="flex: 1; position: relative;">
                    <input type="text" id="sm-verify-value" class="sm-input" autocomplete="off"
                           placeholder="أدخل البيانات المراد التحقق منها..."
                           style="width: 100%; height: 42px; border-radius: 10px; border: 1px solid #f1f5f9; background: #f8fafc; padding: 0 15px; font-weight: 600; font-size: 13.5px; transition: 0.3s; color: var(--sm-dark-color); outline: none;">
                    <div id="sm-verify-suggestions" class="sm-suggestions-box" style="display: none; position: absolute; top: calc(100% + 5px); left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; z-index: 1000; box-shadow: 0 10px 20px -10px rgba(0,0,0,0.1); overflow: hidden;"></div>
                </div>
                <button type="submit" class="sm-btn" style="height: 42px; padding: 0 25px; font-weight: 800; font-size: 13.5px; border-radius: 10px; background: var(--sm-dark-color); color: #fff; border: none; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 6px;">
                    <span class="dashicons dashicons-search" style="font-size: 16px; width: 16px; height: 16px;"></span> استعلام
                </button>
            </div>
        </form>
    </div>

    <!-- Compact Validation Info -->
    <div id="sm-verify-help-area" style="text-align: center; margin-top: -12px; margin-bottom: 30px;">
        <span id="sm-validation-tip" style="display: inline-flex; align-items: center; gap: 5px; background: #fff; padding: 4px 12px; border-radius: 20px; font-size: 10.5px; color: #64748b; font-weight: 700; border: 1px solid #f1f5f9; transition: 0.3s;">
            <span class="dashicons dashicons-info" style="font-size: 13px; width: 13px; height: 13px; color: <?php echo esc_attr($accent_color); ?>;"></span>
            <span id="sm-tip-text">أدخل البيانات لبدء البحث الذكي في السجلات</span>
        </span>
    </div>

    <!-- Loading State -->
    <div id="sm-verify-loading" style="display: none; text-align: center; padding: 40px 0;">
        <div class="sm-loader-ring"></div>
        <p style="color: #64748b; font-size: 12px; font-weight: 700; margin-top: 10px;">جاري التدقيق الرقمي...</p>
    </div>

    <!-- Search Results Output -->
    <div id="sm-verify-results" style="display: flex; flex-direction: column; gap: 25px;"></div>

</div>

<style>
@keyframes sm-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes smSlideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

#sm-search-container:focus-within { border-color: <?php echo esc_attr($accent_color); ?>; }

.sm-loader-ring {
    width: 28px; height: 28px; margin: 0 auto;
    border: 2px solid #f1f5f9; border-top-color: <?php echo esc_attr($accent_color); ?>;
    border-radius: 50%; animation: sm-spin 0.8s linear infinite;
}

.sm-result-section { animation: smSlideUp 0.3s ease-out; }
.sm-section-header {
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 5px;
    margin-bottom: 12px;
}
.sm-section-title { font-weight: 800; font-size: 13px; color: #64748b; margin: 0; }

.sm-card-grid-2col {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.sm-verify-card {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    transition: 0.2s;
    display: flex;
    flex-direction: column;
}
.sm-verify-card:hover { border-color: #cbd5e1; }

.sm-verify-card-header {
    padding: 8px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f1f5f9;
    background: #fafafa;
}
.sm-verify-card-label { font-weight: 800; font-size: 10px; color: #64748b; }

.sm-verify-card-body { padding: 12px; flex: 1; }

.sm-result-item-compact {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    border-bottom: 1px dashed #f1f5f9;
}
.sm-result-item-compact:last-child { border-bottom: none; }
.sm-result-key { font-size: 9.5px; color: #94a3b8; font-weight: 700; }
.sm-result-val { font-weight: 700; color: var(--sm-dark-color); font-size: 11.5px; text-align: left; }

.sm-badge-status { padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 800; }
.sm-badge-success { background: #dcfce7; color: #15803d; }
.sm-badge-warning { background: #fef3c7; color: #b45309; }
.sm-badge-danger { background: #fee2e2; color: #b91c1c; }

.sm-verify-suggestion-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f8fafc; font-weight: 600; font-size: 12.5px; }
.sm-verify-suggestion-item:hover { background: #f8fafc; color: <?php echo esc_attr($accent_color); ?>; }

@media (max-width: 768px) {
    .sm-card-grid-2col { grid-template-columns: 1fr; }
    #sm-verify-form > div { flex-direction: column; gap: 5px; }
    #sm-verify-type { width: 100% !important; }
}

@media print {
    @page { size: A4 portrait; margin: 1cm; }
    body * { visibility: hidden; }
    .sm-verify-portal, .sm-verify-portal * { visibility: visible; }
    .sm-verify-portal { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; background: #fff; }
    .sm-portal-header, #sm-search-container, #sm-verify-help-area, button, .sm-btn-outline, #sm-verify-loading, .sm-suggestions-box { display: none !important; }

    .sm-result-section { margin-bottom: 25px; page-break-inside: avoid; }
    .sm-section-header { border-bottom: 2px solid #000; margin-bottom: 10px; }
    .sm-section-title { color: #000; font-size: 14px; }

    .sm-card-grid-2col { display: block !important; }
    .sm-verify-card {
        border: 1px solid #999 !important;
        margin-bottom: 10px !important;
        border-radius: 0;
        width: 100%;
        display: table !important;
        page-break-inside: avoid;
    }
    .sm-verify-card-header {
        display: table-caption;
        background: #f0f0f0 !important;
        border-bottom: 1px solid #999 !important;
        font-weight: bold;
        -webkit-print-color-adjust: exact;
    }
    .sm-verify-card-body { display: table-footer-group; width: 100%; }
    .sm-result-item-compact {
        display: table-row !important;
        width: 100%;
    }
    .sm-result-key {
        display: table-cell !important;
        padding: 5px 8px;
        border-bottom: 1px solid #eee;
        font-weight: bold;
        width: 35%;
        color: #000;
        font-size: 11px;
    }
    .sm-result-val {
        display: table-cell !important;
        padding: 5px 8px;
        border-bottom: 1px solid #eee;
        text-align: right;
        font-size: 11px;
        color: #000;
    }
    .sm-badge-status { border: 1px solid #000; background: transparent !important; color: #000 !important; }
}
</style>

<script>
(function($) {
    const form = $('#sm-verify-form'), input = $('#sm-verify-value'), typeSelect = $('#sm-verify-type'),
          resultsArea = $('#sm-verify-results'), loading = $('#sm-verify-loading'),
          suggestions = $('#sm-verify-suggestions'), tipText = $('#sm-tip-text');
    let typingTimer;

    const config = {
        show_membership: <?php echo (int)$show_membership; ?>,
        show_practice: <?php echo (int)$show_practice; ?>,
        show_facility: <?php echo (int)$show_facility; ?>,
        success_msg: "<?php echo esc_js(get_option('sm_verify_success_msg', 'سجل معتمد')); ?>"
    };

    typeSelect.on('change', function() { updateTip($(this).val()); input.trigger('input').focus(); });

    function updateTip(type) {
        let text = 'أدخل البيانات المطلوبة لبدء البحث الذكي';
        if (type === 'national_id') text = 'الرقم القومي (14 رقماً) من البطاقة الشخصية';
        else if (type === 'membership') text = 'رقم القيد النقابي المعتمد من الكارنيه';
        else if (type === 'tracking') text = 'كود تتبع الطلب (REG- أو SR-)';
        else if (type === 'auto') text = 'محرك البحث سيتعرف تلقائياً على نوع البيانات';
        tipText.text(text);
    }

    input.on('input', function() {
        clearTimeout(typingTimer);
        const val = $(this).val().trim();
        if (val.length < 3) { suggestions.hide(); return; }
        typingTimer = setTimeout(() => {
            fetch(`${ajaxurl}?action=sm_verify_suggest&query=${val}&type=${typeSelect.val()}`)
            .then(r => r.json()).then(res => {
                if (res.success && res.data && res.data.length > 0) {
                    suggestions.empty().show();
                    res.data.forEach(item => suggestions.append(`<div class="sm-verify-suggestion-item" onclick="smSelectSuggestion('${item}')">${item}</div>`));
                } else suggestions.hide();
            });
        }, 300);
    });

    window.smSelectSuggestion = val => { input.val(val); suggestions.hide(); form.submit(); };
    $(document).on('click', e => { if (!$(e.target).closest('#sm-verify-form').length) suggestions.hide(); });

    form.on('submit', function(e) {
        e.preventDefault();
        const val = input.val().trim(), type = typeSelect.val();
        if (!val) return;
        if (type === 'national_id' && !/^[0-9]{14}$/.test(val)) {
            if (typeof smShowNotification === 'function') smShowNotification('الرقم القومي يجب أن يتكون من 14 رقماً', true);
            return;
        }
        resultsArea.hide().empty(); loading.show(); suggestions.hide();
        const fd = new FormData(); fd.append('action', 'sm_verify_document'); fd.append('search_value', val); fd.append('search_type', type);
        fetch(ajaxurl + '?action=sm_verify_document', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            loading.hide();
            if (res.success && res.data) { renderResults(res.data); resultsArea.fadeIn(); }
            else { renderError(res.data || 'لا توجد سجلات مطابقة'); resultsArea.fadeIn(); }
        }).catch(err => { loading.hide(); renderError('خطأ تقني.'); resultsArea.fadeIn(); });
    });

    function renderResults(data) {
        const blocks = Array.isArray(data) ? data : [data];
        if (blocks.length === 0) { renderError('لم يتم العثور على سجلات'); return; }

        const groups = {
            profile: blocks.filter(b => b.type === 'profile'),
            membership: blocks.filter(b => b.type === 'membership'),
            practice: blocks.filter(b => b.type === 'practice'),
            facility: blocks.filter(b => b.type === 'facility'),
            tracking: blocks.filter(b => b.type === 'tracking')
        };

        if (groups.profile.length > 0) renderSection('الهوية المهنية', groups.profile.map(b => getProfileCard(b.owner)));
        if (groups.membership.length > 0 && config.show_membership) renderSection('السجل النقابي', groups.membership.map(b => getMembershipCard(b.membership)));
        if (groups.practice.length > 0 && config.show_practice) renderSection('تراخيص المزاولة', groups.practice.map(b => getPracticeCard(b.practice)));
        if (groups.facility.length > 0 && config.show_facility) renderSection('تراخيص المنشآت', groups.facility.map(b => getFacilityCard(b.facility)));
        if (groups.tracking.length > 0) renderSection('الطلبات الرقمية', groups.tracking.map(b => getTrackingCard(b.tracking)));

        resultsArea.append(`<div style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" class="sm-btn sm-btn-outline" style="width: auto; padding: 8px 30px; border-radius: 8px; font-weight: 700; font-size: 12.5px;">
                <span class="dashicons dashicons-printer" style="margin-left: 6px; vertical-align: middle;"></span> طباعة التقرير الرسمي
            </button>
        </div>`);
    }

    function renderSection(title, cardHtmlArray) {
        const section = $(`<div class="sm-result-section"><div class="sm-section-header"><h4 class="sm-section-title">${title}</h4></div><div class="sm-card-grid-2col"></div></div>`);
        cardHtmlArray.forEach(html => section.find('.sm-card-grid-2col').append(html));
        resultsArea.append(section);
    }

    function getProfileCard(o) {
        return `<div class="sm-verify-card">
            <div class="sm-verify-card-header"><div class="sm-verify-card-label">بيانات العضو</div><div class="sm-badge-status sm-badge-success">${config.success_msg}</div></div>
            <div class="sm-verify-card-body">
                <h3 style="margin: 0 0 8px; font-size: 1em; color: var(--sm-dark-color); font-weight: 900;">${o.name}</h3>
                <div class="sm-result-item-compact"><span class="sm-result-key">الرقم القومي</span><span class="sm-result-val">********${o.national_id.substr(-6)}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">الدرجة</span><span class="sm-result-val">${o.grade}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">التخصص</span><span class="sm-result-val">${o.specialization}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">الفرع</span><span class="sm-result-val">${o.branch}</span></div>
            </div>
        </div>`;
    }

    function getMembershipCard(m) {
        const v = !m.expiry || m.expiry === '---' || new Date(m.expiry) >= new Date();
        return `<div class="sm-verify-card">
            <div class="sm-verify-card-header"><div class="sm-verify-card-label">القيد النقابي</div><div class="sm-badge-status ${v ? 'sm-badge-success' : 'sm-badge-danger'}">${v ? 'سارية' : 'منتهية'}</div></div>
            <div class="sm-verify-card-body">
                <div class="sm-result-item-compact"><span class="sm-result-key">رقم القيد</span><span class="sm-result-val" style="color:var(--sm-primary-color);">${m.number}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">الانتهاء</span><span class="sm-result-val">${m.expiry || '---'}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">السداد</span><span class="sm-result-val">${m.status}</span></div>
            </div>
        </div>`;
    }

    function getPracticeCard(p) {
        const v = !p.expiry || p.expiry === '---' || new Date(p.expiry) >= new Date();
        return `<div class="sm-verify-card">
            <div class="sm-verify-card-header"><div class="sm-verify-card-label">ترخيص المزاولة</div><div class="sm-badge-status ${v ? 'sm-badge-success' : 'sm-badge-danger'}">${v ? 'سارٍ' : 'منتهٍ'}</div></div>
            <div class="sm-verify-card-body">
                <div class="sm-result-item-compact"><span class="sm-result-key">رقم الترخيص</span><span class="sm-result-val">${p.number}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">الإصدار</span><span class="sm-result-val">${p.issue_date || '---'}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">الصلاحية</span><span class="sm-result-val">${p.expiry || '---'}</span></div>
            </div>
        </div>`;
    }

    function getFacilityCard(f) {
        return `<div class="sm-verify-card">
            <div class="sm-verify-card-header"><div class="sm-verify-card-label">ترخيص المنشأة</div><div class="sm-badge-status sm-badge-success">مرخصة</div></div>
            <div class="sm-verify-card-body">
                <div class="sm-result-item-compact" style="border-bottom:1px solid #f1f5f9; margin-bottom:4px;"><span class="sm-result-key">المنشأة</span><span class="sm-result-val">${f.name}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">رقم الترخيص</span><span class="sm-result-val">${f.number}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">الفئة</span><span class="sm-result-val">(${f.category})</span></div>
                <div class="sm-result-item-compact" style="flex-direction:column; align-items:flex-start;"><span class="sm-result-key">العنوان</span><span class="sm-result-val" style="font-size:10px; margin-top:2px;">${f.address}</span></div>
            </div>
        </div>`;
    }

    function getTrackingCard(t) {
        const ok = t.status === 'تم القبول والتفعيل' || t.status === 'مكتمل / معتمد';
        return `<div class="sm-verify-card">
            <div class="sm-verify-card-header"><div class="sm-verify-card-label">حالة الطلب</div><div class="sm-badge-status ${ok ? 'sm-badge-success' : 'sm-badge-warning'}">${t.status}</div></div>
            <div class="sm-verify-card-body">
                <div class="sm-result-item-compact"><span class="sm-result-key">الخدمة</span><span class="sm-result-val">${t.service}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">الكود</span><span class="sm-result-val">${t.id}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">التاريخ</span><span class="sm-result-val">${t.date}</span></div>
                <div class="sm-result-item-compact"><span class="sm-result-key">الفرع</span><span class="sm-result-val">${t.branch}</span></div>
            </div>
        </div>`;
    }

    function renderError(msg) {
        resultsArea.append(`<div class="sm-verify-card" style="border-style: dashed; border-color: #feb2b2; text-align: center; padding: 15px;"><p style="color: #c53030; font-weight: 800; margin: 0; font-size:12px;">${msg}</p></div>`);
    }

})(jQuery);
</script>
