<?php if (!defined('ABSPATH')) exit;

$search = sanitize_text_field($_GET['cert_search'] ?? '');
$certs = SM_DB::get_certificates(['search' => $search]);
$members = SM_DB::get_members(['limit' => -1]);
?>

<div class="sm-certificates-admin" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h3 style="margin:0;">إدارة الشهادات والدورات التدريبية</h3>
        <div style="display:flex; gap:10px;">
            <button onclick="document.getElementById('sm-import-certs-modal').style.display='flex'" class="sm-btn sm-btn-outline" style="width:auto;">📥 استيراد من Excel</button>
            <button onclick="smOpenAddCertModal()" class="sm-btn" style="width:auto;">+ إضافة شهادة جديدة</button>
        </div>
    </div>

    <div style="background: #f8fafc; padding: 25px; border-radius: 15px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
        <form method="get" style="display: flex; gap: 12px; align-items: flex-end;">
            <input type="hidden" name="sm_tab" value="certificates">
            <div style="flex: 1;">
                <label class="sm-label">البحث في السجلات:</label>
                <input type="text" name="cert_search" class="sm-input" value="<?php echo esc_attr($search); ?>" placeholder="عنوان الشهادة، الرقم التسلسلي، أو بيانات العضو...">
            </div>
            <button type="submit" class="sm-btn" style="width: auto;">بحث</button>
        </form>
    </div>

    <div class="sm-table-container">
        <table class="sm-table">
            <thead>
                <tr>
                    <th>الرقم التسلسلي</th>
                    <th>عنوان الشهادة</th>
                    <th>العضو المستفيد</th>
                    <th>النوع / التصنيف</th>
                    <th>تاريخ الإصدار</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($certs)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">لا توجد شهادات مسجلة حالياً.</td></tr>
                <?php else: foreach ($certs as $c): ?>
                    <tr>
                        <td style="font-weight: 800; color: var(--sm-primary-color); font-family: monospace;"><?php echo esc_html($c->serial_number); ?></td>
                        <td><strong><?php echo esc_html($c->title); ?></strong></td>
                        <td>
                            <div><?php echo esc_html($c->member_name); ?></div>
                            <div style="font-size:10px; color:#94a3b8;"><?php echo esc_html($c->member_nid); ?></div>
                        </td>
                        <td>
                            <span class="sm-badge sm-badge-low"><?php echo esc_html($c->cert_type); ?></span>
                            <div style="font-size:10px; color:#718096; margin-top:4px;"><?php echo esc_html($c->category); ?> | <?php echo esc_html($c->specialization); ?></div>
                        </td>
                        <td><?php echo esc_html($c->issue_date); ?></td>
                        <td>
                            <div style="display:flex; gap:8px;">
                                <a href="<?php echo admin_url('admin-ajax.php?action=sm_print_certificate&id=' . $c->id); ?>" target="_blank" class="sm-btn" style="height:28px; font-size:11px; width:auto; background:#111F35; padding:0 12px; display:flex; align-items:center;">طباعة</a>
                                <button onclick="smDeleteCert(<?php echo $c->id; ?>)" class="sm-btn" style="height:28px; font-size:11px; width:auto; background:#e53e3e; padding:0 12px;">حذف</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Cert Modal -->
<div id="sm-add-cert-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 650px;">
        <div class="sm-modal-header">
            <h3>إصدار شهادة جديدة</h3>
            <button class="sm-modal-close" onclick="document.getElementById('sm-add-cert-modal').style.display='none'">&times;</button>
        </div>
        <form id="sm-add-cert-form" style="padding: 25px;">
            <div class="sm-form-group">
                <label class="sm-label">اختر العضو (اختياري):</label>
                <select name="member_id" class="sm-select">
                    <option value="0">-- غير مسجل أو خارجي --</option>
                    <?php foreach ($members as $m) echo "<option value='{$m->id}'>{$m->name} ({$m->national_id})</option>"; ?>
                </select>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <div class="sm-form-group">
                    <label class="sm-label">اسم المتدرب / المستفيد:</label>
                    <input type="text" name="member_name" class="sm-input" placeholder="في حال عدم اختيار عضو مسجل">
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">الرقم القومي:</label>
                    <input type="text" name="member_national_id" class="sm-input" placeholder="14 رقماً">
                </div>
            </div>
            <div class="sm-form-group" style="margin-top:20px;">
                <label class="sm-label">عنوان الشهادة / الدورة:</label>
                <input type="text" name="title" class="sm-input" required placeholder="مثال: دورة الإصابات الرياضية المتقدمة">
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <div class="sm-form-group">
                    <label class="sm-label">نوع الشهادة:</label>
                    <select name="cert_type" class="sm-select">
                        <option value="دورة تدريبية">دورة تدريبية</option>
                        <option value="ورشة عمل">ورشة عمل</option>
                        <option value="دبلوم مهني">دبلوم مهني</option>
                        <option value="شهادة شكر">شهادة شكر وتقدير</option>
                        <option value="أداة قياس">أداة قياس / ترخيص أداة</option>
                    </select>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">التصنيف:</label>
                    <input type="text" name="category" class="sm-input" placeholder="مثال: علمي، إداري، طبي">
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">مجال التخصص:</label>
                    <input type="text" name="specialization" class="sm-input" placeholder="مثال: تأهيل حركي">
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">تاريخ الإصدار:</label>
                    <input type="date" name="issue_date" class="sm-input" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">تاريخ الانتهاء (اختياري):</label>
                    <input type="date" name="expiry_date" class="sm-input">
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">التقدير / النتيجة:</label>
                    <input type="text" name="grade" class="sm-input" placeholder="مثال: امتياز، ناجح">
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">الفرع المصدر:</label>
                    <select name="governorate" class="sm-select">
                        <option value="all">النقابة العامة</option>
                        <?php foreach (SM_Settings::get_governorates() as $slug => $name) echo "<option value='{$slug}'>{$name}</option>"; ?>
                    </select>
                </div>
            </div>
            <div style="background:#f0f9ff; border:1px solid #bae6fd; padding:15px; border-radius:10px; margin-top:25px; font-size:12px; color:#0369a1;">
                <span class="dashicons dashicons-info"></span> سيقوم النظام تلقائياً بتوليد رقم تسلسلي فريد وكود باركود (Barcode) للشهادة فور الحفظ.
            </div>
            <button type="submit" class="sm-btn" style="margin-top: 25px; height:50px; font-weight:800;">إصدار وحفظ الشهادة الآن</button>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="sm-import-certs-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 550px;">
        <div class="sm-modal-header">
            <h3>استيراد شهادات من ملف Excel</h3>
            <button class="sm-modal-close" onclick="document.getElementById('sm-import-certs-modal').style.display='none'">&times;</button>
        </div>
        <div style="padding: 25px;">
            <p style="font-size: 11px; color: #64748b; margin-bottom: 20px;">يرجى رفع ملف Excel يحتوي على الأعمدة بالترتيب:<br>
            A: العنوان | B: الرقم القومي | C: الاسم | D: النوع | E: التصنيف | F: التخصص | G: تاريخ الإصدار | H: كود الفرع | I: تاريخ الانتهاء | J: التقدير</p>
            <div style="background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 15px; padding: 30px; text-align: center;">
                <input type="file" id="sm-cert-excel-file" accept=".xlsx, .xls" style="display: none;" onchange="smProcessCertExcel(this)">
                <label for="sm-cert-excel-file" style="cursor: pointer;">
                    <div style="font-size: 40px; color: var(--sm-primary-color); margin-bottom: 10px;">📊</div>
                    <div style="font-weight: 800; color: var(--sm-dark-color);">اضغط هنا لاختيار ملف الـ Excel</div>
                    <div style="font-size: 11px; color: #94a3b8; margin-top: 5px;">يدعم تنسيقات .xlsx و .xls</div>
                </label>
            </div>
            <div id="cert-import-progress" style="display: none; margin-top: 20px;">
                <div style="height: 8px; background: #eee; border-radius: 10px; overflow: hidden;"><div id="cert-progress-bar" style="width: 0%; height: 100%; background: var(--sm-primary-color); transition: 0.3s;"></div></div>
                <div id="cert-progress-text" style="font-size: 11px; text-align: center; margin-top: 8px; font-weight: 700;">جاري المعالجة...</div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
function smOpenAddCertModal() {
    document.getElementById('sm-add-cert-form').reset();
    document.getElementById('sm-add-cert-modal').style.display = 'flex';
}

document.getElementById('sm-add-cert-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerText = 'جاري الإصدار...';

    const fd = new FormData(this);
    fd.append('action', 'sm_add_certificate');
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl + '?action=sm_add_certificate', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم إصدار الشهادة بنجاح. الرقم التسلسلي: ' + res.data.serial);
            setTimeout(() => location.reload(), 800);
        } else {
            smHandleAjaxError(res.data);
            btn.disabled = false; btn.innerText = 'إصدار وحفظ الشهادة الآن';
        }
    });
});

function smDeleteCert(id) {
    if (!confirm('هل أنت متأكد من حذف هذه الشهادة نهائياً؟')) return;
    const fd = new FormData();
    fd.append('action', 'sm_delete_certificate');
    fd.append('id', id);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl + '?action=sm_delete_certificate', { method: 'POST', body: fd })
    .then(r => r.json()).then(res => {
        if (res.success) location.reload();
        else smHandleAjaxError(res.data);
    });
}

function smProcessCertExcel(input) {
    if (!input.files.length) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json(sheet, { header: 'A' });
        if (rows.length < 2) { alert('الملف فارغ أو غير صحيح'); return; }

        const certs = rows.slice(1); // Skip header
        const modal = document.getElementById('sm-import-certs-modal');
        const progress = document.getElementById('cert-import-progress');
        progress.style.display = 'block';

        const batchSize = 50;
        let processed = 0;

        const sendBatch = (startIndex) => {
            const batch = certs.slice(startIndex, startIndex + batchSize);
            const fd = new FormData();
            fd.append('action', 'sm_import_certificates_json');
            fd.append('certs_data', JSON.stringify(batch));
            fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

            fetch(ajaxurl + '?action=sm_import_certificates_json', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                processed += batch.length;
                const pct = Math.round((processed / certs.length) * 100);
                document.getElementById('cert-progress-bar').style.width = pct + '%';
                document.getElementById('cert-progress-text').innerText = `تمت معالجة ${processed} من أصل ${certs.length}...`;

                if (startIndex + batchSize < certs.length) {
                    sendBatch(startIndex + batchSize);
                } else {
                    smShowNotification(`اكتمل الاستيراد بنجاح! تم استيراد ${processed} شهادة.`);
                    setTimeout(() => location.reload(), 1000);
                }
            });
        };
        sendBatch(0);
    };
    reader.readAsArrayBuffer(input.files[0]);
}
</script>
