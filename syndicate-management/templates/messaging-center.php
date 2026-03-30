<?php if (!defined('ABSPATH')) exit; ?>
<?php
$my_id = get_current_user_id();
$is_admin = current_user_can('manage_options');
$is_official = current_user_can('sm_branch_access') || current_user_can('sm_full_access');
$is_member = !current_user_can('sm_branch_access') && !current_user_can('sm_full_access');

$categories = array(
    'inquiry' => array('label' => 'استفسار عام', 'color' => '#EBF8FF', 'text' => '#3182CE'),
    'finance' => array('label' => 'مشكلة مالية', 'color' => '#FEF3C7', 'text' => '#B45309'),
    'technical' => array('label' => 'دعم فني', 'color' => '#F0FDF4', 'text' => '#15803D'),
    'membership' => array('label' => 'تجديد عضوية', 'color' => '#F5F3FF', 'text' => '#6D28D9'),
    'other' => array('label' => 'أخرى', 'color' => '#F1F5F9', 'text' => '#475569')
);

$statuses = array(
    'open' => array('label' => 'مفتوح', 'class' => 'sm-badge-high'),
    'in-progress' => array('label' => 'قيد التنفيذ', 'class' => 'sm-badge-mid'),
    'closed' => array('label' => 'مغلق', 'class' => 'sm-badge-low')
);
?>

<div class="sm-messaging-center" dir="rtl" style="font-family: 'Rubik', sans-serif;">

    <!-- Header with Tabs -->
    <div style="background: #fff; border-radius: 12px; border: 1px solid var(--sm-border-color); padding: 15px; margin-bottom: 30px; box-shadow: var(--sm-shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="width: 45px; height: 45px; background: var(--sm-primary-color); color: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <span class="dashicons dashicons-email-alt2"></span>
                </div>
                <div>
                    <h2 style="margin: 0; font-weight: 900; font-size: 1.3em; color: var(--sm-dark-color); border:none; padding:0;">مركز المراسلات والدعم</h2>
                    <p style="margin: 2px 0 0 0; font-size: 11px; color: #64748b; font-weight: 500;">بوابة التواصل المركزية بين الإدارة والأعضاء</p>
                </div>
            </div>

            <div class="sm-tabs-wrapper" style="margin: 0; border: none; padding: 0;">
                <button class="sm-tab-btn sm-active" onclick="smSwitchMessagingTab('tickets', this)">تذاكر الدعم</button>
                <?php if ($is_official): ?>
                    <button class="sm-tab-btn" onclick="smSwitchMessagingTab('direct-comm', this)">التواصل المباشر مع الأعضاء</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TICKET SYSTEM CONTENT -->
    <div id="messaging-tab-tickets" class="messaging-tab-content">
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 15px;">
            <!-- Sidebar: Ticket Filters & Stats -->
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="background: #fff; border-radius: 12px; border: 1px solid var(--sm-border-color); padding: 15px; box-shadow: var(--sm-shadow);">
                    <h4 style="margin: 0 0 12px 0; font-size: 13px; font-weight: 800; color: var(--sm-dark-color); border-bottom: 1px solid #eee; padding-bottom: 8px;">تصفية التذاكر</h4>

                    <div class="sm-form-group" style="margin-bottom: 20px;">
                        <input type="text" id="ticket-search" class="sm-input" placeholder="بحث بالموضوع أو الرقم..." style="font-size: 12px; height: 36px;" oninput="smLoadTickets()">
                    </div>

                    <div class="sm-form-group" style="margin-bottom: 20px;">
                        <select id="ticket-filter-status" class="sm-select" style="font-size: 12px; height: 36px;" onchange="smLoadTickets()">
                            <option value="">كل الحالات</option>
                            <?php foreach($statuses as $k => $v) echo "<option value='$k'>{$v['label']}</option>"; ?>
                        </select>
                    </div>

                    <div class="sm-form-group" style="margin-bottom: 30px;">
                        <select id="ticket-filter-category" class="sm-select" style="font-size: 12px; height: 36px;" onchange="smLoadTickets()">
                            <option value="">كل الأقسام</option>
                            <?php foreach($categories as $k => $v) echo "<option value='$k'>{$v['label']}</option>"; ?>
                        </select>
                    </div>

                    <?php if ($is_member): ?>
                        <button onclick="smOpenCreateTicketModal()" class="sm-btn" style="width: 100%; height: 38px; font-weight: 700;">+ فتح تذكرة جديدة</button>
                    <?php endif; ?>
                </div>

                <div style="background: #fff; border-radius: 12px; border: 1px solid var(--sm-border-color); padding: 15px; box-shadow: var(--sm-shadow);">
                    <h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 800; color: var(--sm-dark-color);">إحصائيات الدعم</h4>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12px;"><span>تذاكر مفتوحة:</span><strong id="stat-open-count">-</strong></div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px;"><span>قيد التنفيذ:</span><strong id="stat-progress-count">-</strong></div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px;"><span>تم إغلاقها:</span><strong id="stat-closed-count">-</strong></div>
                    </div>
                </div>
            </div>

            <!-- Main Ticket Area -->
            <div style="min-height: 600px;">
                <div id="tickets-list-view">
                    <div id="sm-tickets-grid" style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                        <!-- Loaded via JS -->
                        <div style="text-align: center; padding: 100px; color: #94a3b8;">جاري تحميل التذاكر...</div>
                    </div>
                </div>

                <div id="ticket-details-view" style="display: none;">
                    <!-- Loaded via JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- DIRECT COMMUNICATION HUB CONTENT -->
    <?php if ($is_official): ?>
    <div id="messaging-tab-direct-comm" class="messaging-tab-content" style="display: none;">
        <div style="display: grid; grid-template-columns: 350px 1fr; gap: 20px;">
            <!-- Member Selection Sidebar -->
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="background: #fff; border-radius: 12px; border: 1px solid var(--sm-border-color); padding: 15px; box-shadow: var(--sm-shadow);">
                    <h4 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 800; color: var(--sm-dark-color); display: flex; justify-content: space-between; align-items: center;">
                        <span>اختيار الأعضاء</span>
                        <span id="comm-selected-count" class="sm-badge sm-badge-low" style="font-size: 10px;">0 مختار</span>
                    </h4>

                    <div style="display: flex; gap: 5px; margin-bottom: 15px;">
                        <button onclick="smBulkActionComm('all')" class="sm-btn sm-btn-outline" style="flex: 1; font-size: 10px; padding: 5px; height: auto;">تحديد الكل</button>
                        <button onclick="smBulkActionComm('none')" class="sm-btn sm-btn-outline" style="flex: 1; font-size: 10px; padding: 5px; height: auto;">إلغاء الكل</button>
                    </div>

                    <div style="position: relative; margin-bottom: 20px;">
                        <input type="text" id="member-search-comm" class="sm-input" placeholder="بحث بالاسم، الهوية، أو الكود..." style="padding-left: 35px; height: 38px; font-size: 13px;" oninput="smSearchMembersForComm()">
                        <span class="dashicons dashicons-search" style="position: absolute; left: 10px; top: 11px; color: #94a3b8;"></span>
                    </div>

                    <div id="member-comm-results" style="max-height: 500px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
                        <div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 12px;">ابدأ البحث لاختيار عضو...</div>
                    </div>
                </div>
            </div>

            <!-- Communication Form Hub -->
            <div id="direct-comm-form-area" style="display: none;">
                <div style="background: #fff; border-radius: 12px; border: 1px solid var(--sm-border-color); box-shadow: var(--sm-shadow); overflow: hidden;">
                    <div style="background: #f8fafc; padding: 15px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div id="comm-target-photos" style="display: flex; -webkit-margin-start: 10px;">
                                <!-- Stacked photos -->
                            </div>
                            <div>
                                <h3 id="comm-target-name" style="margin: 0; font-size: 15px; font-weight: 900; color: var(--sm-dark-color);"></h3>
                                <div id="comm-target-meta" style="font-size: 11px; color: #64748b; margin-top: 2px;"></div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <a id="comm-call-btn" href="" class="sm-btn sm-btn-outline" style="width: 36px; height: 36px; border-radius: 50%; padding: 0;" title="اتصال هاتفي">
                                <span class="dashicons dashicons-phone"></span>
                            </a>
                            <button id="comm-history-btn" onclick="smShowCommHistory()" class="sm-btn sm-btn-outline" style="height: 36px; padding: 0 15px; font-size: 12px; font-weight: 700;">سجل المراسلات</button>
                        </div>
                    </div>

                    <div style="padding: 25px;">
                        <form id="direct-comm-form">
                            <div id="comm-member-ids-hidden"></div>

                            <!-- Template Selection -->
                            <div class="sm-form-group" style="margin-bottom: 20px;">
                                <label class="sm-label" style="font-size: 13px;">قوالب الرسائل الجاهزة:</label>
                                <select id="comm-template-select" class="sm-select" onchange="smApplyCommTemplate()">
                                    <option value="">-- رسالة حرة (بدون قالب) --</option>
                                </select>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                                <div class="sm-form-group">
                                    <label class="sm-label" style="font-size: 13px;">عنوان الرسالة / الموضوع:</label>
                                    <input type="text" name="subject" id="comm-subject" class="sm-input" required>
                                </div>
                                <div class="sm-form-group">
                                    <label class="sm-label" style="font-size: 13px;">نص الرسالة:</label>
                                    <textarea name="message" id="comm-message" class="sm-textarea" rows="6" required></textarea>
                                    <p style="font-size: 11px; color: #718096; margin-top: 5px;">يمكنك استخدام الوسوم: {member_name}, {membership_number}, {year}, {balance}</p>
                                </div>
                                <div class="sm-form-group">
                                    <label class="sm-label" style="font-size: 13px;">إرفاق ملفات (اختياري):</label>
                                    <input type="file" name="attachments[]" class="sm-input" multiple style="padding: 8px;">
                                    <p style="font-size: 11px; color: #64748b; margin-top: 5px;">سيتم إرفاق هذه الملفات في رسائل البريد الإلكتروني ونظام التذاكر.</p>
                                </div>
                            </div>

                            <!-- Channel Selection -->
                            <div style="background: #f1f5f9; padding: 15px; border-radius: 10px; margin-top: 20px;">
                                <label class="sm-label" style="font-size: 13px; margin-bottom: 25px;">قنوات الإرسال (يمكن اختيار أكثر من قناة):</label>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <input type="checkbox" name="channels[]" value="whatsapp" checked>
                                        <span style="color: #25D366; font-size: 18px;" class="dashicons dashicons-whatsapp"></span>
                                        <span style="font-size: 13px; font-weight: 700;">WhatsApp</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <input type="checkbox" name="channels[]" value="email" checked>
                                        <span style="color: #3182ce; font-size: 18px;" class="dashicons dashicons-email"></span>
                                        <span style="font-size: 13px; font-weight: 700;">Email</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <input type="checkbox" name="channels[]" value="ticket" checked>
                                        <span style="color: #e53e3e; font-size: 18px;" class="dashicons dashicons-megaphone"></span>
                                        <span style="font-size: 13px; font-weight: 700;">نظام التذاكر</span>
                                    </label>
                                </div>
                            </div>

                            <div style="margin-top: 25px; text-align: left;">
                                <button type="submit" class="sm-btn" style="width: 250px; height: 48px; font-weight: 900; font-size: 16px;">
                                    <span class="dashicons dashicons-share-alt" style="margin-top: 4px;"></span> إرسال المراسلات الآن
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Empty State for Direct Comm -->
            <div id="direct-comm-empty" style="background: #fff; border-radius: 12px; border: 1px dashed #cbd5e0; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 100px; color: #94a3b8;">
                <span class="dashicons dashicons-search" style="font-size: 50px; width: 50px; height: 50px; margin-bottom: 30px;"></span>
                <p style="font-size: 16px; font-weight: 700;">يرجى اختيار عضو من القائمة الجانبية للبدء بالتواصل المباشر</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: Create Ticket -->
<div id="create-ticket-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 600px;">
        <div class="sm-modal-header">
            <h3>فتح تذكرة دعم جديدة</h3>
            <button class="sm-modal-close" onclick="document.getElementById('create-ticket-modal').style.display='none'">&times;</button>
        </div>
        <form id="create-ticket-form" style="padding: 15px;">
            <div class="sm-form-group">
                <label class="sm-label">موضوع التذكرة:</label>
                <input type="text" name="subject" class="sm-input" required placeholder="مثال: مشكلة في تحديث البيانات">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="sm-form-group">
                    <label class="sm-label">القسم:</label>
                    <select name="category" class="sm-select" required>
                        <?php foreach($categories as $k => $v) echo "<option value='$k'>{$v['label']}</option>"; ?>
                    </select>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">الأولوية:</label>
                    <select name="priority" class="sm-select">
                        <option value="low">منخفضة</option>
                        <option value="medium" selected>متوسطة</option>
                        <option value="high">عالية / عاجل</option>
                    </select>
                </div>
            </div>
            <div class="sm-form-group">
                <label class="sm-label">تفاصيل المشكلة / الطلب:</label>
                <textarea name="message" class="sm-textarea" rows="5" required placeholder="يرجى شرح طلبك بالتفصيل..."></textarea>
            </div>
            <div class="sm-form-group">
                <label class="sm-label">مرفقات (اختياري):</label>
                <input type="file" name="attachment" class="sm-input">
                <p style="font-size: 11px; color: #64748b; margin-top: 5px;">يسمح بملفات الصور و PDF (بحد أقصى 5 ميجابايت)</p>
            </div>
            <button type="submit" class="sm-btn" style="width: 100%; height: 45px; font-weight: 700; margin-top: 20px;">إرسال التذكرة</button>
        </form>
    </div>
</div>

<!-- Comm History Modal -->
<div id="comm-history-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 800px;">
        <div class="sm-modal-header">
            <h3>سجل مراسلات العضو</h3>
            <button class="sm-modal-close" onclick="document.getElementById('comm-history-modal').style.display='none'">&times;</button>
        </div>
        <div id="comm-history-body" style="padding: 15px; max-height: 500px; overflow-y: auto;">
            <!-- Loaded via JS -->
        </div>
    </div>
</div>

<script>
(function($) {
    let currentActiveTicketId = null;
    let autoRefreshInterval = null;
    let commTemplates = [];
    let selectedMembers = new Map();
    let lastSearchResults = [];

    const categories = <?php echo wp_json_encode($categories); ?>;
    const statuses = <?php echo wp_json_encode($statuses); ?>;
    const isOfficial = <?php echo $is_official ? 'true' : 'false'; ?>;
    const currentUserId = <?php echo $my_id; ?>;

    window.smSwitchMessagingTab = function(tab, btn) {
        $('.messaging-tab-content').hide();
        $(`#messaging-tab-${tab}`).show();
        $('.sm-tab-btn').removeClass('sm-active');
        $(btn).addClass('sm-active');

        if (tab === 'direct-comm' && commTemplates.length === 0) {
            smLoadCommTemplates();
        }
    };

    window.smOpenCreateTicketModal = function() {
        $('#create-ticket-form')[0].reset();
        $('#create-ticket-modal').fadeIn().css('display', 'flex');
    };

    // TICKET SYSTEM LOGIC
    window.smLoadTickets = function(showLoader = true) {
        const grid = $('#sm-tickets-grid');
        if (showLoader) grid.css('opacity', '0.5');

        const status = $('#ticket-filter-status').val();
        const category = $('#ticket-filter-category').val();
        const search = $('#ticket-search').val();
        const nonce = '<?php echo wp_create_nonce("sm_ticket_action"); ?>';

        const action = 'sm_get_tickets';
        fetch(ajaxurl + `?action=${action}&status=${status}&category=${category}&search=${search}&nonce=${nonce}`)
        .then(r => r.json())
        .then(res => {
            grid.css('opacity', '1').empty();
            let counts = { open: 0, 'in-progress': 0, closed: 0 };

            if (res.success && res.data && res.data.length > 0) {
                res.data.forEach(t => {
                    counts[t.status]++;
                    const cat = categories[t.category] || categories['other'];
                    const stat = statuses[t.status];

                    const card = $(`
                        <div class="sm-ticket-list-item" onclick="smViewTicket(${t.id})" style="background: #fff; border: 1px solid var(--sm-border-color); border-radius: 10px; padding: 25px 20px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: space-between; border-right: 4px solid ${t.priority === 'high' ? '#e53e3e' : '#e2e8f0'}; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                                <div style="width: 40px; height: 40px; border-radius: 8px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; border: 1px solid #eee;">
                                    ${t.member_photo ? `<img src="${t.member_photo}" style="width: 100%; height: 100%; object-fit: cover;">` : `<span class="dashicons dashicons-admin-users" style="color: #94a3b8;"></span>`}
                                </div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 10px; font-weight: 800; color: #94a3b8;">#${t.id}</span>
                                        <h4 style="margin: 0; font-size: 14px; font-weight: 800; color: var(--sm-dark-color);">${t.subject}</h4>
                                    </div>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                        <strong>${t.member_name}</strong> • ${t.updated_at} • <span style="color: ${cat.text}">${cat.label}</span>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left;">
                                <span class="sm-badge ${stat.class}">${stat.label}</span>
                            </div>
                        </div>
                    `);
                    grid.append(card);
                });
            } else {
                if (!res.success) smHandleAjaxError(res);
                grid.html('<div style="text-align: center; padding: 50px; background: #fff; border-radius: 10px; border: 1px dashed #cbd5e0; color: #94a3b8;">لا توجد تذاكر حالياً.</div>');
            }

            $('#stat-open-count').text(counts.open);
            $('#stat-progress-count').text(counts['in-progress']);
            $('#stat-closed-count').text(counts.closed);
        }).catch(err => {
            grid.css('opacity', '1');
            smHandleAjaxError(err);
        });
    };

    window.smViewTicket = function(id, silent = false) {
        currentActiveTicketId = id;
        if (!silent) {
            $('#tickets-list-view').hide();
            $('#ticket-details-view').show().html('<div style="text-align: center; padding: 100px;"><div class="sm-loader-mini"></div></div>');
        }
        const nonce = '<?php echo wp_create_nonce("sm_ticket_action"); ?>';

        const action = 'sm_get_ticket_details';
        fetch(ajaxurl + `?action=${action}&id=${id}&nonce=${nonce}`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                const t = res.data.ticket;
                const thread = res.data.thread;

                if (silent) {
                    const threadHtml = renderThreadHtml(thread);
                    if (threadHtml.trim() !== $('#ticket-thread-body').html().trim()) {
                        $('#ticket-thread-body').html(threadHtml);
                        const threadBody = $('#ticket-thread-body');
                        threadBody.scrollTop(threadBody[0].scrollHeight);
                    }
                    return;
                }

                const cat = categories[t.category] || categories['other'];
                const stat = statuses[t.status];

                $('#ticket-details-view').html(`
                    <div style="background: #fff; border-radius: 12px; border: 1px solid var(--sm-border-color); overflow: hidden; box-shadow: var(--sm-shadow);">
                        <!-- Ticket Header -->
                        <div style="padding: 15px 25px; border-bottom: 1px solid #f1f5f9; background: #fafafa; display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; gap: 15px; align-items: center;">
                                <button onclick="smBackToList()" class="sm-btn sm-btn-outline" style="width: auto; padding: 5px 12px; height: 32px;"><span class="dashicons dashicons-arrow-right-alt2"></span> العودة</button>
                                <div>
                                    <h3 style="margin:0; font-weight: 900; font-size: 15px;">${t.subject}</h3>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                                        تذكرة #${t.id} • ${cat.label} • مقدمة من: <strong>${t.member_name}</strong>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <span class="sm-badge ${stat.class}">${stat.label}</span>
                                ${isOfficial && t.status !== 'closed' ? `<button onclick="smCloseTicket(${t.id})" class="sm-btn" style="background:#e53e3e; height: 32px; width:auto; font-size: 11px;">إغلاق التذكرة</button>` : ''}
                            </div>
                        </div>

                        <!-- Ticket Thread -->
                        <div id="ticket-thread-body" style="padding: 20px; background: #f8fafc; height: 450px; overflow-y: auto;">
                            ${renderThreadHtml(thread)}
                        </div>

                        <!-- Reply Form -->
                        ${t.status !== 'closed' ? `
                            <div style="padding: 20px; border-top: 1px solid #f1f5f9;">
                                <form id="ticket-reply-form">
                                    <input type="hidden" name="ticket_id" value="${t.id}">
                                    <textarea name="message" class="sm-textarea" rows="3" required placeholder="اكتب ردك هنا..." style="margin-bottom: 20px;"></textarea>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <input type="file" name="attachment" style="font-size: 11px;">
                                        <button type="submit" class="sm-btn" style="width: 150px; height: 40px; font-weight: 800;">إرسال الرد</button>
                                    </div>
                                </form>
                            </div>
                        ` : '<div style="padding: 15px; text-align: center; background: #fff5f5; color: #c53030; font-weight: 700; font-size: 13px;">هذه التذكرة مغلقة.</div>'}
                    </div>
                `);

                const threadBody = $('#ticket-thread-body');
                threadBody.scrollTop(threadBody[0].scrollHeight);

                $('#ticket-reply-form').on('submit', function(e) {
                    e.preventDefault();
                    const btn = $(this).find('button[type="submit"]');
                    btn.prop('disabled', true).text('جاري الإرسال...');

                    const fd = new FormData(this);
                    fd.append('action', 'sm_add_ticket_reply');
                    fd.append('nonce', '<?php echo wp_create_nonce("sm_ticket_action"); ?>');

        const action = 'sm_add_ticket_reply';
        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) smViewTicket(t.id);
                        else { smHandleAjaxError(res); btn.prop('disabled', false).text('إرسال الرد'); }
                    }).catch(err => { smHandleAjaxError(err); btn.prop('disabled', false).text('إرسال الرد'); });
                });
            } else {
                smHandleAjaxError(res);
                smBackToList();
            }
        }).catch(err => {
            smHandleAjaxError(err);
            smBackToList();
        });
    };

    function renderThreadHtml(thread) {
        let html = '';
        thread.forEach(m => {
            const isMe = m.sender_id == currentUserId;
            let fileHtml = '';
            if (m.file_url) {
                fileHtml = `<a href="${m.file_url}" target="_blank" style="display: block; margin-top: 8px; font-size: 11px; color: inherit; text-decoration: underline;"><span class="dashicons dashicons-paperclip"></span> مرفق</a>`;
            }

            html += `
                <div style="display: flex; flex-direction: column; align-items: ${isMe ? 'flex-end' : 'flex-start'}; margin-bottom: 25px;">
                    <div style="background: ${isMe ? 'var(--sm-primary-color)' : '#fff'}; color: ${isMe ? '#fff' : '#1e293b'}; padding: 25px 18px; border-radius: 12px; border-bottom-${isMe ? 'left' : 'right'}-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); max-width: 85%; border: ${isMe ? 'none' : '1px solid #e2e8f0'};">
                        <div style="font-weight: 800; font-size: 10px; margin-bottom: 4px; opacity: 0.8;">${m.sender_name} • ${m.created_at}</div>
                        <div style="font-size: 13px; line-height: 1.6; white-space: pre-wrap;">${m.message}</div>
                        ${fileHtml}
                    </div>
                </div>
            `;
        });
        return html;
    }

    window.smBackToList = function() {
        currentActiveTicketId = null;
        $('#ticket-details-view').hide();
        $('#tickets-list-view').show();
        smLoadTickets();
    };

    window.smCloseTicket = function(id) {
        if (!confirm('هل أنت متأكد من إغلاق هذه التذكرة؟')) return;
        const action = 'sm_close_ticket';
        const fd = new FormData();
        fd.append('action', action);
        fd.append('id', id);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_ticket_action"); ?>');
        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if(res.success) {
                smShowNotification('تم إغلاق التذكرة');
                smViewTicket(id);
            } else {
                smHandleAjaxError(res);
            }
        }).catch(err => smHandleAjaxError(err));
    };

    // DIRECT COMMUNICATION LOGIC
    window.smLoadCommTemplates = function() {
        const action = 'sm_get_comm_templates';
        fetch(ajaxurl + '?action=' + action)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                commTemplates = res.data;
                const select = $('#comm-template-select');
                res.data.forEach(t => {
                    select.append(`<option value="${t.template_type}">${t.subject}</option>`);
                });
            } else {
                smHandleAjaxError(res);
            }
        }).catch(err => smHandleAjaxError(err));
    };

    window.smSearchMembersForComm = function() {
        const q = $('#member-search-comm').val();
        if (q.length < 3) return;

        const action = 'sm_search_members';
        fetch(ajaxurl + `?action=${action}&member_search=${q}`)
        .then(r => r.json())
        .then(res => {
            const results = $('#member-comm-results').empty();
            if (res.success && res.data && res.data.length > 0) {
                lastSearchResults = res.data;
                res.data.forEach(m => {
                    const isSelected = selectedMembers.has(m.id);
                    results.append(`
                        <div onclick='smSelectCommMember(${JSON.stringify(m)})' class="sm-comm-member-item" data-id="${m.id}" style="padding: 12px 15px; background: ${isSelected ? 'var(--sm-pastel-red)' : '#fff'}; border: 1px solid ${isSelected ? 'var(--sm-primary-color)' : '#eee'}; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: 0.2s;">
                            <div style="width: 20px; height: 20px; border: 2px solid #cbd5e0; border-radius: 4px; display: flex; align-items: center; justify-content: center; background: ${isSelected ? 'var(--sm-primary-color)' : '#fff'}">
                                ${isSelected ? '<span class="dashicons dashicons-yes" style="color:#fff; font-size:16px; width:16px; height:16px;"></span>' : ''}
                            </div>
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; overflow: hidden; flex-shrink: 0; border: 1px solid #eee;">
                                <img src="${m.photo_url || ''}" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 12px; font-weight: 800; color: var(--sm-dark-color); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${m.name}</div>
                                <div style="font-size: 10px; color: #64748b;">${m.national_id} | ${m.branch_label || m.governorate}</div>
                            </div>
                        </div>
                    `);
                });
            } else {
                if (!res.success) smHandleAjaxError(res);
                lastSearchResults = [];
                results.html('<div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 12px;">لم يتم العثور على نتائج.</div>');
            }
        }).catch(err => {
            smHandleAjaxError(err);
        });
    };

    window.smBulkActionComm = function(type) {
        if (type === 'all') {
            lastSearchResults.forEach(m => selectedMembers.set(m.id, m));
        } else {
            selectedMembers.clear();
        }
        smUpdateCommUI();
        smSearchMembersForComm();
    };

    window.smSelectCommMember = function(m) {
        if (selectedMembers.has(m.id)) {
            selectedMembers.delete(m.id);
        } else {
            selectedMembers.set(m.id, m);
        }
        smUpdateCommUI();

        const item = $(`.sm-comm-member-item[data-id="${m.id}"]`);
        const isSelected = selectedMembers.has(m.id);
        item.css('border-color', isSelected ? 'var(--sm-primary-color)' : '#eee')
            .css('background', isSelected ? 'var(--sm-pastel-red)' : '#fff');
        item.find('div:first').css('background', isSelected ? 'var(--sm-primary-color)' : '#fff')
            .html(isSelected ? '<span class="dashicons dashicons-yes" style="color:#fff; font-size:16px; width:16px; height:16px;"></span>' : '');
    };

    window.smUpdateCommUI = function() {
        const count = selectedMembers.size;
        $('#comm-selected-count').text(`${count} مختار`);

        if (count > 0) {
            $('#direct-comm-empty').hide();
            $('#direct-comm-form-area').show();

            const photos = $('#comm-target-photos').empty();
            const names = [];
            const idsHidden = $('#comm-member-ids-hidden').empty();

            let i = 0;
            selectedMembers.forEach(m => {
                idsHidden.append(`<input type="hidden" name="member_ids[]" value="${m.id}">`);
                if (i < 3) {
                    photos.append(`<div style="width: 36px; height: 36px; border-radius: 50%; overflow: hidden; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-inline-start: ${i === 0 ? '0' : '-15px'}; z-index: ${10-i};"><img src="${m.photo_url || ''}" style="width:100%; height:100%; object-fit:cover;"></div>`);
                }
                names.push(m.name);
                i++;
            });

            if (count === 1) {
                const m = Array.from(selectedMembers.values())[0];
                $('#comm-target-name').text(m.name);
                $('#comm-target-meta').text(`${m.membership_number || 'بدون رقم قيد'} • ${m.branch_label || m.governorate} • ${m.phone}`);
                $('#comm-call-btn').show().attr('href', `tel:${m.phone}`);
                $('#comm-history-btn').show();
            } else {
                $('#comm-target-name').text(`${count} أعضاء مختارين`);
                $('#comm-target-meta').text(names.slice(0, 3).join(', ') + (count > 3 ? '...' : ''));
                $('#comm-call-btn').hide();
                $('#comm-history-btn').hide();
            }
        } else {
            $('#direct-comm-empty').show();
            $('#direct-comm-form-area').hide();
        }
    };

    window.smApplyCommTemplate = function() {
        const type = $('#comm-template-select').val();
        if (!type || selectedMembers.size === 0) return;

        const t = commTemplates.find(x => x.template_type === type);
        if (t) {
            let body = t.body;
            if (selectedMembers.size === 1) {
                const m = Array.from(selectedMembers.values())[0];
                body = body
                    .replace(/{member_name}/g, m.name)
                    .replace(/{membership_number}/g, m.membership_number || '---')
                    .replace(/{year}/g, new Date().getFullYear())
                    .replace(/{amount}/g, '0.00');
            }

            $('#comm-subject').val(t.subject);
            $('#comm-message').val(body);
        }
    };

    $('#direct-comm-form').on('submit', function(e) {
        e.preventDefault();
        const channels = $("input[name='channels[]']:checked").map(function(){return $(this).val();}).get();
        if (channels.length === 0) {
            smShowNotification('يرجى اختيار قناة إرسال واحدة على الأقل', true);
            return;
        }

        const action = 'sm_send_direct_message';
        const formData = new FormData(this);
        formData.append('action', action);
        formData.append('nonce', '<?php echo wp_create_nonce("sm_message_action"); ?>');
        formData.append('template_type', $('#comm-template-select').val() || 'direct');

        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('جاري المعالجة...');

        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            btn.prop('disabled', false).html('<span class="dashicons dashicons-share-alt" style="margin-top: 4px;"></span> إرسال المراسلات الآن');
            if (res.success) {
                if (channels.includes('whatsapp') && selectedMembers.size === 1) {
                    const m = Array.from(selectedMembers.values())[0];
                    const msg = encodeURIComponent($('#comm-message').val());
                    const waUrl = `https://api.whatsapp.com/send?phone=${m.phone.replace(/^0/, '+20')}&text=${msg}`;
                    window.open(waUrl, '_blank');
                }
                smShowNotification('تم إرسال المراسلات بنجاح');
                if (selectedMembers.size === 1) smShowCommHistory();
            } else {
                smHandleAjaxError(res);
            }
        }).catch(err => {
            smHandleAjaxError(err);
            btn.prop('disabled', false).html('<span class="dashicons dashicons-share-alt" style="margin-top: 4px;"></span> إرسال المراسلات الآن');
        });
    });

    window.smShowCommHistory = function() {
        if (selectedMembers.size !== 1) return;
        const m = Array.from(selectedMembers.values())[0];
        const body = $('#comm-history-body').html('<div style="text-align: center; padding: 50px;"><div class="sm-loader-mini"></div></div>');
        $('#comm-history-modal').fadeIn().css('display', 'flex');

        const action = 'sm_get_member_comms_log';
        fetch(ajaxurl + `?action=${action}&member_id=${m.id}`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data && res.data.length > 0) {
                body.empty();
                res.data.forEach(l => {
                    const channelIcons = { whatsapp: 'whatsapp', email: 'email', ticket: 'megaphone' };
                    const channelColors = { whatsapp: '#25D366', email: '#3182ce', ticket: '#e53e3e' };

                    body.append(`
                        <div style="padding: 15px; border-bottom: 1px solid #eee; display: flex; gap: 15px;">
                            <div style="color: ${channelColors[l.channel]};"><span class="dashicons dashicons-${channelIcons[l.channel]}"></span></div>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <strong style="font-size: 13px;">${l.subject || 'بدون موضوع'}</strong>
                                    <span style="font-size: 11px; color: #94a3b8;">${l.sent_at}</span>
                                </div>
                                <div style="font-size: 12px; color: #64748b; line-height: 1.6; white-space: pre-wrap;">${l.message_body}</div>
                                <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">بواسطة: ${l.sender_name}</div>
                            </div>
                        </div>
                    `);
                });
            } else {
                if (!res.success) smHandleAjaxError(res);
                body.html('<div style="text-align: center; padding: 50px; color: #94a3b8;">لا يوجد سجل مراسلات لهذا العضو.</div>');
            }
        }).catch(err => smHandleAjaxError(err));
    };

    $('#create-ticket-form').on('submit', function(e) {
        e.preventDefault();
        const action = 'sm_create_ticket';
        const fd = new FormData(this);
        fd.append('action', action);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_ticket_action"); ?>');
        fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
            if(res.success) {
                smShowNotification('تم فتح التذكرة بنجاح');
                $('#create-ticket-modal').fadeOut();
                smLoadTickets();
                smViewTicket(res.data);
            } else {
                smHandleAjaxError(res);
            }
        }).catch(err => smHandleAjaxError(err));
    });

    // Initialize
    smLoadTickets();

    autoRefreshInterval = setInterval(() => {
        if (currentActiveTicketId) smViewTicket(currentActiveTicketId, true);
        else if ($('#tickets-list-view').is(':visible')) smLoadTickets(false);
    }, 10000);

})(jQuery);
</script>

<style>
.sm-ticket-list-item:hover { background: #f8fafc !important; transform: translateX(-5px); }
.sm-comm-member-item:hover { background: #f8fafc !important; }
.sm-loader-mini { border: 3px solid #f3f3f3; border-top: 3px solid var(--sm-primary-color); border-radius: 50%; width: 24px; height: 24px; animation: sm-spin 1s linear infinite; display: inline-block; }
@keyframes sm-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>
