<?php
if (!defined('ABSPATH')) exit;
$is_officer = current_user_can('sm_manage_members') || current_user_can('manage_options');

// Check for active surveys for current user role
$user_role = !empty(wp_get_current_user()->roles) ? wp_get_current_user()->roles[0] : '';
$member_specialty = '';
if (in_array('sm_member', (array)wp_get_current_user()->roles)) {
    $current_mem = SM_DB_Members::get_member_by_wp_user_id(get_current_user_id());
    if ($current_mem) $member_specialty = $current_mem->specialization;
}
$active_surveys = SM_DB::get_surveys(get_current_user_id(), $user_role, $member_specialty);

if (empty($active_surveys) && in_array('sm_member', (array)wp_get_current_user()->roles)): ?>
    <div style="text-align: center; padding: 60px 20px; background: #f8fafc; border: 1px dashed #cbd5e0; border-radius: 20px; margin-bottom: 30px;">
        <div style="width: 80px; height: 80px; background: #fff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <span class="dashicons dashicons-welcome-learn-more" style="font-size: 40px; width: 40px; height: 40px; color: #94a3b8;"></span>
        </div>
        <h3 style="margin: 0; color: #4a5568; font-weight: 800;">لا توجد اختبارات مقررة حالياً</h3>
        <p style="color: #94a3b8; margin: 10px 0 25px;">لم يتم تعيين أي اختبارات مهنية لحسابك في الوقت الحالي من قبل الإدارة.</p>

        <?php
        $current_mem = SM_DB_Members::get_member_by_wp_user_id(get_current_user_id());
        if ($current_mem):
            $grade = $current_mem->professional_grade;
            $next_grade = '';
            if ($grade === 'assistant_specialist') $next_grade = 'Specialist (أخصائي)';
            elseif ($grade === 'specialist') $next_grade = 'Consultant (استشاري)';
            elseif ($grade === 'consultant') $next_grade = 'Expert (خبير)';

            if ($next_grade): ?>
                <div style="background: #fff; padding: 25px; border-radius: 15px; border: 1px solid #e2e8f0; max-width: 400px; margin: 0 auto; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">
                    <h4 style="margin: 0 0 15px; font-weight: 800; color: var(--sm-primary-color);">طلب اختبار ترقية مهنية</h4>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">درجتك الحالية: <strong><?php echo SM_Settings::get_professional_grades()[$grade] ?? $grade; ?></strong>. يمكنك طلب دخول اختبار الترقية لدرجة <strong><?php echo $next_grade; ?></strong>.</p>
                    <button onclick="smRequestPromotionTest('<?php echo esc_js($grade); ?>')" class="sm-btn" style="width: 100%; height: 45px; font-weight: 800;">إرسال طلب دخول الاختبار</button>
                </div>
            <?php endif;
        endif; ?>
    </div>
<?php endif;

foreach ($active_surveys as $survey):
    // Check if already responded
    $responded = SM_DB_Education::get_user_survey_response_id($survey->id, get_current_user_id());
    if ($responded) continue;

    $is_test = $survey->test_type !== 'survey';
    $attempts_made = SM_DB_Education::get_user_attempts_count($survey->id, get_current_user_id());
    $attempts_left = $survey->max_attempts - $attempts_made;
    $best_score = SM_DB_Education::get_user_best_score($survey->id, get_current_user_id());
    $passed = ($best_score !== null && $best_score >= $survey->pass_score);
?>
<div class="sm-survey-card" style="background: <?php echo $is_test ? '#f0f7ff' : '#fffdf2'; ?>; border: 2px solid <?php echo $is_test ? '#bee3f8' : '#fef3c7'; ?>; border-radius: 12px; padding: 30px; margin-bottom: 20px; position: relative; overflow: hidden;">
    <div style="position: absolute; top: 0; right: 0; background: <?php echo $is_test ? '#3182ce' : '#fbbf24'; ?>; color: #fff; font-size: 10px; font-weight: 800; padding: 4px 15px; border-radius: 0 0 0 12px;">
        <?php echo $is_test ? 'اختبار مهني مقرر' : 'استطلاع رأي هام'; ?>
    </div>
    <h3 style="margin: 0 0 10px 0; color: <?php echo $is_test ? '#2c5282' : '#92400e'; ?>;"><?php echo esc_html($survey->title); ?></h3>
    <div style="display:flex; gap:20px; margin-bottom: 20px; font-size:12px; color:#64748b;">
        <?php if($is_test): ?>
            <span>⏰ المدة: <?php echo $survey->time_limit; ?> دقيقة</span>
            <span>🎯 درجة النجاح: <?php echo $survey->pass_score; ?>%</span>
            <span>🔄 المحاولات المتبقية: <?php echo $attempts_left; ?></span>
        <?php endif; ?>
    </div>

    <?php if ($passed): ?>
        <div style="background:#f0fff4; color:#22543d; padding:12px 20px; border-radius:10px; display:inline-flex; align-items:center; gap:10px; font-weight:700;">
            <span class="dashicons dashicons-yes-alt"></span> لقد اجتزت هذا الاختبار بنجاح بنسبة <?php echo round($best_score); ?>%
        </div>
    <?php elseif ($attempts_left <= 0): ?>
        <div style="background:#fff5f5; color:#c53030; padding:12px 20px; border-radius:10px; display:inline-flex; align-items:center; gap:10px; font-weight:700;">
            <span class="dashicons dashicons-no-alt"></span> لقد استنفدت كافة المحاولات المتاحة لهذا الاختبار.
        </div>
    <?php else: ?>
        <button class="sm-btn" style="background: <?php echo $is_test ? '#2b6cb0' : '#d97706'; ?>; width: auto;" onclick='smStartProfessionalTest(<?php echo esc_attr(json_encode($survey)); ?>)'>
            <?php echo $is_test ? 'بدء الاختبار الآن' : 'المشاركة الآن'; ?>
        </button>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- GLOBAL TEST OVERLAY -->
<div id="sm-test-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#fff; z-index:999999; flex-direction:column; overflow-y:auto;">
    <div style="background:var(--sm-dark-color); color:#fff; padding:15px 40px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:10;">
        <div>
            <h2 id="overlay-test-title" style="margin:0; font-size:1.2em; color:#fff;">اسم الاختبار</h2>
            <div id="test-timer" style="font-size:24px; font-weight:900; color:var(--sm-primary-color); margin-top:5px;">00:00</div>
        </div>
        <button onclick="smExitTest()" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#fff; padding:8px 20px; border-radius:8px; cursor:pointer;">انسحاب وإغلاق</button>
    </div>
    <div id="test-questions-area" style="max-width:800px; margin:40px auto; padding:0 20px; width:100%;">
        <!-- Questions injected here -->
    </div>
    <div style="max-width:800px; margin:0 auto 60px; padding:0 20px; width:100%;">
        <button id="submit-test-btn" class="sm-btn" style="height:55px; font-weight:800; font-size:1.1em;" onclick="smFinishTest()">إرسال الإجابات النهائية وتصحيح الاختبار</button>
    </div>
</div>

<script>
let currentTestTimer = null;
let testQuestions = [];
let activeTestId = 0;
let currentQuestionIndex = 0;
let testSettings = {};

function smStartProfessionalTest(s) {
    if(!confirm('هل أنت مستعد لبدء الاختبار؟ سيتم بدء المحتسب الزمني فوراً.')) return;

    activeTestId = s.id;
    testSettings = s;
    currentQuestionIndex = 0;
    window.sm_temp_responses = {};
    window.activeAssignmentId = s.assignment_id || 0;

    // Basic Anti-Cheating
    document.addEventListener('visibilitychange', smHandleVisibilityChange);
    window.addEventListener('blur', smHandleBlur);

    document.getElementById('overlay-test-title').innerText = s.title;
    document.getElementById('sm-test-overlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Fetch questions
    fetch(ajaxurl + '?action=sm_get_test_questions&test_id=' + s.id + '&nonce=<?php echo wp_create_nonce("sm_admin_action"); ?>')
    .then(r=>r.json()).then(res => {
        if(res.success) {
            testQuestions = res.data;

            // Apply Randomization if set in survey
            if(s.random_order == 1) {
                testQuestions.sort(() => Math.random() - 0.5);
            }

            smRenderTestQuestions();
            smStartTimer(s.time_limit);

            // Notify system of start
            const fd = new FormData();
            fd.append('action', (window.activeAssignmentId > 0) ? 'sm_start_test_session' : 'sm_log_test_action');
            if(window.activeAssignmentId > 0) fd.append('assignment_id', window.activeAssignmentId);
            fd.append('type', 'start');
            fd.append('details', 'بدء الاختبار من الواجهة');
            fd.append('nonce', '<?php echo wp_create_nonce("sm_test_nonce"); ?>');
            fetch(ajaxurl + '?action=' + fd.get('action'), {method:'POST', body:fd});
        } else {
            smHandleAjaxError(res);
            smExitTest();
        }
    }).catch(err => {
        smHandleAjaxError(err);
        smExitTest();
    });
}

function smRenderTestQuestions() {
    const area = document.getElementById('test-questions-area');
    if (!area || testQuestions.length === 0) return;

    const q = testQuestions[currentQuestionIndex];
    let html = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; color:#64748b; font-size:13px;">
            <span>سؤال <strong>${currentQuestionIndex + 1}</strong> من <strong>${testQuestions.length}</strong></span>
            <div id="q-timer-display" style="color:#e53e3e; font-weight:800; display:${q.time_limit > 0 ? 'block' : 'none'};">الوقت المتبقي للسؤال: <span id="q-timer-sec">${q.time_limit}</span>ث</div>
        </div>
        <div class="test-q-block" id="q-block-${q.id}" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:15px; padding: 30px; margin-bottom: 30px;">
            <div style="font-weight:900; font-size:1.25em; color:var(--sm-dark-color); line-height:1.6; margin-bottom:20px;">
                ${q.question_text}
            </div>
    `;

    if(q.media_url) {
        html += `<div style="margin-bottom:20px; text-align:center;"><img src="${q.media_url}" style="max-width:100%; border-radius:12px; border:1px solid #eee; max-height:400px; box-shadow:0 4px 10px rgba(0,0,0,0.05);"></div>`;
    }

    if(q.question_type === 'mcq') {
        let opts = [];
        try { opts = JSON.parse(q.options); } catch(e) {}
        if(testSettings.randomize_answers == 1) opts.sort(() => Math.random() - 0.5);
        html += '<div style="display:grid; gap:12px;">';
        opts.forEach((opt) => {
            html += `
                <label style="display:flex; align-items:center; gap:12px; background:#fff; padding:15px; border-radius:12px; border:1px solid #edf2f7; cursor:pointer; transition:0.2s;">
                    <input type="radio" name="q_${q.id}" value="${opt}" style="width:20px; height:20px;" ${window.sm_temp_responses && window.sm_temp_responses[q.id] == opt ? 'checked' : ''}>
                    <span style="font-weight:600;">${opt}</span>
                </label>
            `;
        });
        html += '</div>';
    } else if(q.question_type === 'true_false') {
        html += `
            <div style="display:flex; gap:15px;">
                <label style="flex:1; display:flex; align-items:center; gap:12px; background:#fff; padding:20px; border-radius:12px; border:1px solid #edf2f7; cursor:pointer;">
                    <input type="radio" name="q_${q.id}" value="true" ${window.sm_temp_responses && window.sm_temp_responses[q.id] == 'true' ? 'checked' : ''}> <strong>صح</strong>
                </label>
                <label style="flex:1; display:flex; align-items:center; gap:12px; background:#fff; padding:20px; border-radius:12px; border:1px solid #edf2f7; cursor:pointer;">
                    <input type="radio" name="q_${q.id}" value="false" ${window.sm_temp_responses && window.sm_temp_responses[q.id] == 'false' ? 'checked' : ''}> <strong>خطأ</strong>
                </label>
            </div>
        `;
    } else if(q.question_type === 'essay') {
        html += `<textarea class="sm-textarea" name="q_${q.id}" placeholder="اكتب إجابتك المقالية هنا..." rows="8" style="background:#fff;">${window.sm_temp_responses ? (window.sm_temp_responses[q.id] || '') : ''}</textarea>`;
    } else if(q.question_type === 'ordering') {
        let items = [];
        try { items = JSON.parse(q.options); } catch(e) {}
        let displayItems = window.sm_temp_responses && window.sm_temp_responses[q.id] ? window.sm_temp_responses[q.id] : [...items].sort(() => Math.random() - 0.5);
        html += `<div class="q-order-wrap" data-qid="${q.id}" style="display:grid; gap:8px;">`;
        displayItems.forEach(it => {
            html += `<div class="q-order-item" style="padding:15px; background:#fff; border:1px solid #ddd; border-radius:10px; cursor:move; display:flex; align-items:center; gap:10px;" draggable="true" ondragstart="smDragStart(event)" ondragover="smDragOver(event)" ondrop="smDrop(event)">
                <span class="dashicons dashicons-menu" style="color:#94a3b8;"></span> ${it}
            </div>`;
        });
        html += `</div>`;
    } else if(q.question_type === 'matching') {
        let pairs = [];
        try { pairs = JSON.parse(q.options); } catch(e) {}
        let keys = pairs.map(p => p.key);
        let vals = pairs.map(p => p.val).sort(() => Math.random() - 0.5);
        html += `<div style="display:grid; gap:10px;">`;
        keys.forEach((k, kidx) => {
            const savedVal = (window.sm_temp_responses && window.sm_temp_responses[q.id]) ? window.sm_temp_responses[q.id][k] : '';
            html += `<div style="display:flex; align-items:center; gap:15px;">
                <div style="flex:1; padding:12px; background:#edf2f7; border-radius:10px; font-weight:700;">${k}</div>
                <div style="color:var(--sm-primary-color); font-weight:900;">←</div>
                <select name="q_match_${q.id}_${kidx}" data-key="${k}" class="sm-select q-match-select" style="flex:1; background:#fff;">
                    <option value="">اختر المقابل...</option>
                    ${vals.map(v => `<option value="${v}" ${savedVal == v ? 'selected' : ''}>${v}</option>`).join('')}
                </select>
            </div>`;
        });
        html += `</div>`;
    } else {
        html += `<input type="text" class="sm-input" name="q_${q.id}" placeholder="اكتب إجابتك هنا..." style="background:#fff;" value="${window.sm_temp_responses ? (window.sm_temp_responses[q.id] || '') : ''}">`;
    }

    html += '</div>';

    // Navigation Buttons
    html += `
        <div style="display:flex; justify-content:space-between; gap:20px;">
            ${(testSettings.lock_navigation == 0 && currentQuestionIndex > 0) ? `<button class="sm-btn sm-btn-outline" onclick="smPrevQuestion()" style="flex:1;">السابق</button>` : '<div style="flex:1;"></div>'}
            ${currentQuestionIndex < testQuestions.length - 1 ? `<button class="sm-btn" onclick="smNextQuestion()" style="flex:1; font-weight:800;">السؤال التالي</button>` : ''}
        </div>
    `;

    area.innerHTML = html;

    // Set individual question timer
    if(q.time_limit > 0) {
        window.qTimerSec = q.time_limit;
    } else {
        window.qTimerSec = null;
    }
}

window.smPrevQuestion = function() {
    smSaveCurrentAnswer();
    currentQuestionIndex--;
    smRenderTestQuestions();
};

window.smNextQuestion = function() {
    smSaveCurrentAnswer();
    currentQuestionIndex++;
    smRenderTestQuestions();
};

function smSaveCurrentAnswer() {
    const q = testQuestions[currentQuestionIndex];
    if(!window.sm_temp_responses) window.sm_temp_responses = {};

    if(q.question_type === 'ordering') {
        const wrap = document.querySelector(`.q-order-wrap[data-qid="${q.id}"]`);
        window.sm_temp_responses[q.id] = wrap ? Array.from(wrap.querySelectorAll('.q-order-item')).map(it => it.innerText.trim()) : [];
    } else if(q.question_type === 'matching') {
        const res = {};
        document.querySelectorAll(`.q-match-select[name^="q_match_${q.id}"]`).forEach(sel => {
            res[sel.dataset.key] = sel.value;
        });
        window.sm_temp_responses[q.id] = res;
    } else {
        const el = document.querySelector(`[name="q_${q.id}"]:checked`) ||
                   document.querySelector(`textarea[name="q_${q.id}"]`) ||
                   document.querySelector(`input[name="q_${q.id}"]`);
        window.sm_temp_responses[q.id] = el ? el.value : '';
    }
}

window.smDragStart = (e) => { e.dataTransfer.setData('text/plain', e.target.innerText); e.target.classList.add('dragging'); };
window.smDragOver = (e) => { e.preventDefault(); };
window.smDrop = (e) => {
    e.preventDefault();
    const dragging = document.querySelector('.dragging');
    if(!dragging) return;
    const container = e.target.closest('.q-order-wrap');
    const afterElement = getDragAfterElement(container, e.clientY);
    if(afterElement == null) container.appendChild(dragging);
    else container.insertBefore(dragging, afterElement);
    dragging.classList.remove('dragging');
};

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.q-order-item:not(.dragging)')];
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if(offset < 0 && offset > closest.offset) return { offset: offset, element: child };
        else return closest;
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function smStartTimer(mins) {
    let sec = mins * 60;
    const el = document.getElementById('test-timer');

    let lastSync = 0;

    currentTestTimer = setInterval(() => {
        let m = Math.floor(sec / 60);
        let s = sec % 60;
        el.innerText = `${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;

        // Handle current question timer
        if(window.qTimerSec !== null) {
            window.qTimerSec--;
            const qTimerEl = document.getElementById('q-timer-sec');
            if(qTimerEl) qTimerEl.innerText = window.qTimerSec;

            if(window.qTimerSec <= 0) {
                smShowNotification('انتهى وقت هذا السؤال!', true);
                if(currentQuestionIndex < testQuestions.length - 1) {
                    smNextQuestion();
                } else {
                    smFinishTest();
                }
            }
        }

        if(sec <= 0) {
            clearInterval(currentTestTimer);
            smShowNotification('انتهى الوقت المحدد للاختبار!', true);
            smFinishTest();
        }

        if(lastSync >= 30) {
            smSyncProgress();
            lastSync = 0;
        }

        sec--;
        lastSync++;
    }, 1000);
}

function smSyncProgress() {
    const responses = smGetCurrentResponses();
    const fd = new FormData();
    fd.append('action', 'sm_sync_test_progress');
    if(window.activeAssignmentId) fd.append('assignment_id', window.activeAssignmentId);
    fd.append('survey_id', activeTestId);
    fd.append('progress', JSON.stringify(responses));
    fd.append('nonce', '<?php echo wp_create_nonce("sm_test_nonce"); ?>');
    fetch(ajaxurl + '?action=sm_sync_test_progress', {method:'POST', body:fd})
    .then(r=>r.json()).then(res => {
        if(res.success && res.data.status === 'terminated') {
            alert('تم إنهاء جلستك من قبل المشرف.');
            location.reload();
        }
    });
}

function smGetCurrentResponses() {
    return window.sm_temp_responses || {};
}

function smFinishTest() {
    smSaveCurrentAnswer();
    const responses = smGetCurrentResponses();
    const action = 'sm_submit_survey_response';
    const fd = new FormData();
    fd.append('action', action);
    fd.append('survey_id', activeTestId);
    if(window.activeAssignmentId) fd.append('assignment_id', window.activeAssignmentId);
    fd.append('responses', JSON.stringify(responses));
    fd.append('nonce', '<?php echo wp_create_nonce("sm_test_nonce"); ?>');

    document.getElementById('submit-test-btn').disabled = true;
    document.getElementById('submit-test-btn').innerText = 'جاري التصحيح وحفظ النتائج...';

    fetch(ajaxurl + '?action=' + action, {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        clearInterval(currentTestTimer);
        if(res.success) {
            const data = res.data;
            const emoji = data.passed ? '🎉' : '😕';
            const title = data.passed ? 'تهانينا، لقد اجتزت الاختبار!' : 'عذراً، لم تجتز الاختبار هذه المرة';
            const color = data.passed ? '#38a169' : '#e53e3e';

            const area = document.getElementById('test-questions-area');
            if (area) {
                area.innerHTML = `
                    <div style="text-align:center; padding:50px;">
                        <div style="font-size:80px; margin-bottom: 20px;">${emoji}</div>
                        <h2 style="font-weight:900; color:${color};">${title}</h2>
                        <div style="font-size:2.5em; font-weight:900; margin:20px 0;">${Math.round(data.score)}%</div>
                        <p style="font-size:1.2em; color:#64748b; margin-bottom: 30px;">تم حفظ النتيجة وإخطار الإدارة بنجاح.</p>
                        <button class="sm-btn" onclick="location.reload()" style="width:auto; padding:0 50px;">العودة للوحة التحكم</button>
                    </div>
                `;
            }
            const submitBtn = document.getElementById('submit-test-btn');
            if (submitBtn) submitBtn.style.display = 'none';
            const timerEl = document.getElementById('test-timer');
            if (timerEl) timerEl.style.display = 'none';
        } else {
            smHandleAjaxError(res);
            document.getElementById('submit-test-btn').disabled = false;
            document.getElementById('submit-test-btn').innerText = 'إرسال الإجابات النهائية وتصحيح الاختبار';
        }
    }).catch(err => {
        smHandleAjaxError(err);
        document.getElementById('submit-test-btn').disabled = false;
        document.getElementById('submit-test-btn').innerText = 'إرسال الإجابات النهائية وتصحيح الاختبار';
    });
}

function smHandleVisibilityChange() {
    if (document.hidden) {
        smLogSecurityAction('تبديل النافذة / خروج من الاختبار', 'warning');
    }
}

function smHandleBlur() {
    smLogSecurityAction('فقدان التركيز عن نافذة الاختبار', 'warning');
}

function smLogSecurityAction(msg, type) {
    const fd = new FormData();
    fd.append('action', 'sm_log_test_action');
    if(window.activeAssignmentId) fd.append('assignment_id', window.activeAssignmentId);
    fd.append('type', type);
    fd.append('details', msg);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_test_nonce"); ?>');
    fetch(ajaxurl + '?action=sm_log_test_action', {method:'POST', body:fd});
}

function smRequestPromotionTest(currentGrade) {
    if(!confirm('هل أنت متأكد من رغبتك في طلب اختبار ترقية مهنية؟')) return;

    const fd = new FormData();
    fd.append('action', 'sm_submit_professional_request');
    fd.append('request_type', 'promotion_test_' + currentGrade);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_professional_action"); ?>');

    // Automatically detect member ID if in profile context, otherwise backend will try to match user
    if (typeof SM_CURRENT_MEMBER_ID !== 'undefined') {
        fd.append('member_id', SM_CURRENT_MEMBER_ID);
    }

    fetch(ajaxurl + '?action=sm_submit_professional_request', {method:'POST', body:fd})
    .then(r=>r.json()).then(res => {
        if(res.success) {
            smShowNotification('تم إرسال طلب الاختبار بنجاح للإدارة المختصة.');
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smExitTest() {
    if(confirm('هل أنت متأكد من الانسحاب؟ لن يتم حفظ إجاباتك وستخسر محاولة.')) {
        document.removeEventListener('visibilitychange', smHandleVisibilityChange);
        window.removeEventListener('blur', smHandleBlur);
        location.reload();
    }
}
</script>

<?php if ($is_officer): ?>
<div class="sm-card-grid" style="margin-bottom: 30px;">
    <?php
    // Stat Box 1: Members
    $icon = 'dashicons-groups'; $label = 'إجمالي الأعضاء'; $value = number_format($stats['total_members'] ?? 0); $color = '#3182ce'; $url = add_query_arg('sm_tab', 'members');
    include SM_PLUGIN_DIR . 'templates/component-stat-card.php';

    // Stat Box 2: Practice Licenses
    $icon = 'dashicons-id-alt'; $label = 'تصاريح تراخيص المزاولة'; $value = number_format($stats['total_practice_licenses'] ?? 0); $color = '#dd6b20'; $url = add_query_arg('sm_tab', 'practice-licenses');
    include SM_PLUGIN_DIR . 'templates/component-stat-card.php';

    // Stat Box 3: Facility Licenses
    $icon = 'dashicons-building'; $label = 'تراخيص المنشآت'; $value = number_format($stats['total_facility_licenses'] ?? 0); $color = '#805ad5'; $url = add_query_arg('sm_tab', 'facility-licenses');
    include SM_PLUGIN_DIR . 'templates/component-stat-card.php';

    // Stat Box 4: Revenue
    $icon = 'dashicons-money-alt'; $label = 'إجمالي الإيرادات'; $value = number_format($stats['total_revenue'] ?? 0, 2); $color = '#38a169'; $suffix = 'ج.م'; $url = add_query_arg('sm_tab', 'finance');
    include SM_PLUGIN_DIR . 'templates/component-stat-card.php';
    ?>
</div>


<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 40px;">
    <!-- Financial Collection Trends -->
    <div style="background: #fff; padding: 30px; border: 1px solid var(--sm-border-color); border-radius: 12px; box-shadow: var(--sm-shadow);">
        <h3 style="margin-top:0; font-size: 1.1em; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">تحصيل الإيرادات (آخر 30 يوم)</h3>
        <div style="height: 300px; position: relative;">
            <canvas id="financialTrendsChart"></canvas>
        </div>
    </div>

    <!-- Specialization Distribution -->
    <div style="background: #fff; padding: 30px; border: 1px solid var(--sm-border-color); border-radius: 12px; box-shadow: var(--sm-shadow);">
        <h3 style="margin-top:0; font-size: 1.1em; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">توزيع التخصصات المهنية</h3>
        <div style="height: 300px; position: relative;">
            <canvas id="specializationDistChart"></canvas>
        </div>
    </div>
</div>

<?php endif; ?>





<script>
function smDownloadChart(chartId, fileName) {
    const canvas = document.getElementById(chartId);
    if (!canvas) return;
    const link = document.createElement('a');
    link.download = fileName + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

(function() {
    <?php if (!$is_officer): ?>
    return;
    <?php endif; ?>
    window.smCharts = window.smCharts || {};

    const initSummaryCharts = function() {
        if (typeof Chart === 'undefined') {
            setTimeout(initSummaryCharts, 200);
            return;
        }

        const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };

        // Data for Financial Trends
        const financialData = <?php echo json_encode($stats['financial_trends']); ?>;
        const trendLabels = financialData.map(d => d.date);
        const trendValues = financialData.map(d => d.total);

        new Chart(document.getElementById('financialTrendsChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'إجمالي التحصيل اليومي',
                    data: trendValues,
                    borderColor: '#38a169',
                    backgroundColor: 'rgba(56, 161, 105, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: chartOptions
        });

        // Data for Specializations
        const specData = <?php
            $specs_labels = SM_Settings::get_specializations();
            $mapped_specs = [];
            foreach($stats['specializations'] as $s) {
                $mapped_specs[] = [
                    'label' => $specs_labels[$s->specialization] ?? $s->specialization,
                    'count' => $s->count
                ];
            }
            echo json_encode($mapped_specs);
        ?>;

        new Chart(document.getElementById('specializationDistChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: specData.map(d => d.label),
                datasets: [{
                    data: specData.map(d => d.count),
                    backgroundColor: ['#3182ce', '#e53e3e', '#d69e2e', '#38a169', '#805ad5', '#d53f8c']
                }]
            },
            options: chartOptions
        });

        const createOrUpdateChart = (id, config) => {
            if (window.smCharts[id]) {
                window.smCharts[id].destroy();
            }
            const el = document.getElementById(id);
            if (el) {
                window.smCharts[id] = new Chart(el.getContext('2d'), config);
            }
        };


    };

    if (document.readyState === 'complete') initSummaryCharts();
    else window.addEventListener('load', initSummaryCharts);
})();
</script>
