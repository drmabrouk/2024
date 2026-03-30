<?php
if (!defined('ABSPATH')) exit;

$user = wp_get_current_user();
global $wpdb;

// 1. Get the latest active assignment for the member
$assign = $wpdb->get_row($wpdb->prepare(
    "SELECT a.*, s.title, s.time_limit, s.pass_score, s.max_attempts, s.test_type, s.random_order, s.randomize_answers, s.lock_navigation, s.auto_grade
     FROM {$wpdb->prefix}sm_test_assignments a
     JOIN {$wpdb->prefix}sm_surveys s ON a.test_id = s.id
     WHERE a.user_id = %d AND (a.status = 'assigned' OR a.status = 'active')
     ORDER BY a.created_at DESC LIMIT 1",
    $user->ID
));

// 2. Redirect if no test file exists
if (!$assign) {
    echo "<script>window.location.href='" . home_url('/dashboard') . "';</script>";
    exit;
}

$member = SM_DB::get_member_by_wp_user_id($user->ID);
$questions = SM_DB::get_test_questions($assign->test_id);
$nonce = wp_create_nonce('sm_test_nonce');
$survey_nonce = wp_create_nonce('sm_survey_action');
?>

<div class="sm-test-portal" id="sm-test-hub" dir="rtl">

    <!-- Stage 1: Identity & Terms -->
    <div id="test-stage-1" class="sm-test-card active">
        <div style="text-align:center; margin-bottom:30px;">
            <div style="width:70px; height:70px; background:rgba(246, 48, 73, 0.1); border-radius:20px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:15px;">
                <span class="dashicons dashicons-welcome-learn-more" style="font-size:35px; width:35px; height:35px; color:var(--sm-primary-color);"></span>
            </div>
            <h2 style="font-weight:900; margin:0;"><?php echo esc_html($assign->title); ?></h2>
            <div style="font-size:13px; color:#64748b; margin-top:5px;">بوابة الاختبارات المهنية المعتمدة</div>
        </div>

        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:15px; padding:20px; margin-bottom:25px;">
            <div style="display:flex; align-items:center; gap:15px;">
                <div style="width:50px; height:50px; border-radius:50%; overflow:hidden; border:2px solid #fff; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                    <img src="<?php echo $member->photo_url ?: 'https://www.gravatar.com/avatar/000?d=mp'; ?>" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div>
                    <div style="font-weight:800; font-size:15px;"><?php echo $member->name; ?></div>
                    <div style="font-size:11px; color:#64748b;"><?php echo $member->national_id; ?> | <?php echo SM_Settings::get_branch_name($member->governorate); ?></div>
                </div>
            </div>
        </div>

        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:15px; padding:25px; margin-bottom:30px;">
            <h4 style="margin:0 0 15px 0; font-weight:800; color:var(--sm-dark-color); display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-shield-alt" style="color:var(--sm-primary-color);"></span> إقرار شروط الممارسة والامتحان
            </h4>
            <div style="font-size:14px; line-height:1.8; color:#4a5568;">
                <p>أتعهد بصفتي مقدم الطلب بالآتي:</p>
                <ul style="padding-right:20px;">
                    <li>أن كافة الإجابات المقدمة تعبر عن مجهودي الشخصي ولا يجوز الاستعانة بمصادر خارجية.</li>
                    <li><strong>المراقبة الرقمية:</strong> أقر بأن النظام يقوم بمراقبة التحركات البرمجية (الخروج من الشاشة، تبديل التبويبات).</li>
                    <li><strong>النزاهة:</strong> أي محاولة للغش أو التلاعب ستؤدي لإلغاء الاختبار فوراً واتخاذ الإجراءات النقابية اللازمة.</li>
                    <li><strong>الوقت:</strong> سيلتزم النظام بالوقت المحدد (<?php echo $assign->time_limit; ?> دقيقة) وسيتم الإرسال تلقائياً عند انتهائه.</li>
                </ul>
            </div>
            <div style="margin-top:20px; display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="agree-terms" style="width:20px; height:20px; accent-color:var(--sm-primary-color);">
                <label for="agree-terms" style="font-weight:800; font-size:14px; cursor:pointer;">أوافق على كافة الشروط وأقر بأنني مراقب إلكترونياً</label>
            </div>
        </div>

        <button onclick="smGoToStage(2)" class="sm-btn" style="width:100%; height:55px; font-weight:900; border-radius:12px;">المتابعة لبدء الاختبار</button>
    </div>

    <!-- Stage 2: Security Prep -->
    <div id="test-stage-2" class="sm-test-card" style="display:none; text-align:center;">
        <div style="max-width:500px; margin:0 auto;">
            <div style="font-size:50px; margin-bottom:20px;">🔒</div>
            <h3 style="font-weight:900;">تأمين بيئة الاختبار</h3>
            <p style="color:#64748b; font-size:15px; margin-bottom:35px;">لبدء الاختبار، يتطلب النظام الانتقال لوضع ملء الشاشة (Full-screen) لضمان الخصوصية ومنع التشتت. سيتم احتساب الوقت فور الدخول.</p>

            <button onclick="smStartExamFullScreen()" class="sm-btn" style="width:100%; height:60px; font-weight:900; background:var(--sm-dark-color); border-radius:15px;">الدخول لوضع الاختبار المعتمد</button>
        </div>
    </div>

    <!-- Stage 3: Testing Hub -->
    <div id="test-stage-3" class="sm-test-hub-view" style="display:none;">
        <div class="sm-test-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:40px; height:40px; background:#fff; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--sm-primary-color);">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div>
                    <div id="exam-timer" style="font-size:24px; font-weight:900; color:#fff; font-family:monospace;">00:00</div>
                    <div style="font-size:10px; color:rgba(255,255,255,0.7); font-weight:700;">الوقت المتبقي</div>
                </div>
            </div>

            <div style="text-align:center; flex:1;">
                <div style="font-size:11px; color:rgba(255,255,255,0.8); font-weight:700; margin-bottom:4px;">اسم الاختبار</div>
                <div style="font-weight:900; color:#fff; font-size:15px;"><?php echo esc_html($assign->title); ?></div>
            </div>

            <div style="text-align:left;">
                <button onclick="smSubmitExamFinal()" class="sm-btn" style="background:#fff; color:var(--sm-primary-color); height:40px; padding:0 25px; font-weight:900; border-radius:10px; font-size:13px;">إنهاء وتسليم</button>
            </div>
        </div>

        <div class="sm-test-body-container">
            <div class="sm-test-progress-bar">
                <div id="sm-progress-fill" class="fill"></div>
            </div>

            <div id="question-container" style="padding:40px;">
                <!-- Loaded via JS -->
            </div>
        </div>

        <div class="sm-test-footer">
             <div style="color:rgba(255,255,255,0.7); font-size:12px; font-weight:700;">سؤال <span id="curr-q-num">1</span> من <?php echo count($questions); ?></div>
             <div style="display:flex; gap:10px;">
                <button id="prev-q-btn" onclick="smNavigateQuestion(-1)" class="sm-btn-mini" style="background:rgba(255,255,255,0.1); display:none;">السابق</button>
                <button id="next-q-btn" onclick="smNavigateQuestion(1)" class="sm-btn-mini" style="background:#fff; color:var(--sm-dark-color);">التالي</button>
             </div>
        </div>
    </div>

    <!-- Stage 4: Result Summary -->
    <div id="test-stage-4" class="sm-test-card" style="display:none; text-align:center;">
        <div id="final-result-content">
            <!-- Loaded via JS -->
        </div>
    </div>

</div>

<style>
.sm-test-portal { max-width: 800px; margin: 40px auto; }
.sm-test-card { background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:40px; box-shadow:0 15px 35px rgba(0,0,0,0.05); }
.sm-test-hub-view { position:fixed; inset:0; background:#f1f5f9; z-index:999999; display:flex; flex-direction:column; }
.sm-test-header { background:var(--sm-dark-color); padding:20px 40px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
.sm-test-body-container { flex:1; display:flex; flex-direction:column; overflow-y:auto; background:#fff; margin:30px auto; width:90%; max-width:900px; border-radius:20px; box-shadow:0 10px 25px rgba(0,0,0,0.05); }
.sm-test-progress-bar { height:6px; background:#f1f5f9; position:relative; overflow:hidden; }
.sm-test-progress-bar .fill { height:100%; background:var(--sm-primary-color); width:0; transition:0.4s ease; }
.sm-test-footer { background:var(--sm-dark-color); padding:15px 40px; display:flex; justify-content:space-between; align-items:center; }
.sm-btn-mini { padding:8px 25px; border-radius:10px; border:none; color:#fff; font-weight:800; cursor:pointer; font-size:13px; transition:0.2s; }
.sm-btn-mini:hover { opacity:0.9; }

.sm-question-block h3 { font-size:1.4em; font-weight:900; line-height:1.6; margin-bottom:30px; color:var(--sm-dark-color); }
.sm-options-grid { display:grid; gap:12px; }
.sm-option-item { border:2px solid #f1f5f9; padding:18px 25px; border-radius:15px; cursor:pointer; transition:0.2s; font-weight:700; display:flex; align-items:center; gap:12px; }
.sm-option-item:hover { background:#f8fafc; border-color:var(--sm-primary-color); }
.sm-option-item.selected { background:rgba(246, 48, 73, 0.05); border-color:var(--sm-primary-color); color:var(--sm-primary-color); }
.sm-option-circle { width:22px; height:22px; border:2px solid #cbd5e0; border-radius:50%; position:relative; }
.sm-option-item.selected .sm-option-circle { border-color:var(--sm-primary-color); background:var(--sm-primary-color); }
.sm-option-item.selected .sm-option-circle::after { content:''; position:absolute; inset:5px; background:#fff; border-radius:50%; }

#sm-test-hub:fullscreen { background: #f1f5f9; }
</style>

<script>
(function($) {
    const AID = <?php echo $assign->id; ?>;
    const SID = <?php echo $assign->test_id; ?>;
    const NONCE = '<?php echo $nonce; ?>';
    const QDATA = <?php echo json_encode($questions); ?>;
    const TIME_LIMIT = <?php echo $assign->time_limit; ?>;

    let currentIdx = 0;
    let answers = {};
    let timer;
    let timeLeft = TIME_LIMIT * 60;
    let isTerminated = false;

    window.smGoToStage = (stage) => {
        if (stage === 2 && !$('#agree-terms').is(':checked')) {
            alert('يجب الموافقة على الشروط للمتابعة.');
            return;
        }
        $('.sm-test-card, .sm-test-hub-view').hide();
        $('#test-stage-' + stage).show();
    };

    window.smStartExamFullScreen = () => {
        const elem = document.getElementById('sm-test-hub');
        if (elem.requestFullscreen) elem.requestFullscreen();
        else if (elem.webkitRequestFullscreen) elem.webkitRequestFullscreen();
        else if (elem.msRequestFullscreen) elem.msRequestFullscreen();

        // Randomize questions if requested
        if(<?php echo $assign->random_order; ?> == 1) {
            QDATA.sort(() => Math.random() - 0.5);
        }

        // Initialize Session in DB
        const fd = new FormData();
        fd.append('action', 'sm_start_test_session');
        fd.append('assignment_id', AID);
        fd.append('nonce', NONCE);
        fetch(ajaxurl, { method: 'POST', body: fd });

        smGoToStage(3);
        renderQuestion();
        startTimer();
        setupSecurityMonitoring();
    };

    function startTimer() {
        timer = setInterval(() => {
            if (isTerminated) { clearInterval(timer); return; }
            timeLeft--;

            const mins = Math.floor(timeLeft / 60);
            const secs = timeLeft % 60;
            $('#exam-timer').text(`${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`);

            if (timeLeft === Math.floor(TIME_LIMIT * 30)) smNotifyAlert('نصف الوقت انقضى!');
            if (timeLeft === 600) smNotifyAlert('بقي 10 دقائق على نهاية الاختبار');
            if (timeLeft === 300) smNotifyAlert('تحذير: بقي 5 دقائق فقط!');
            if (timeLeft === 60) smNotifyAlert('دقيقة واحدة متبقية! يرجى إنهاء المراجعة.');

            if (timeLeft <= 10 && timeLeft > 0) {
                $('#exam-timer').css('color', '#e53e3e').fadeOut(100).fadeIn(100);
            }

            if (timeLeft <= 0) {
                clearInterval(timer);
                smSubmitExamFinal(true);
            }

            // Sync Heartbeat every 30s
            if (timeLeft % 30 === 0) smSyncProgress();

        }, 1000);
    }

    let qTimer;
    let qTimeLeft;

    function renderQuestion() {
        const q = QDATA[currentIdx];
        const prog = ((currentIdx + 1) / QDATA.length) * 100;
        $('#sm-progress-fill').css('width', prog + '%');
        $('#curr-q-num').text(currentIdx + 1);

        let html = `<div class="sm-question-block" style="animation: smSlideUp 0.4s ease;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div style="font-size:12px; color:var(--sm-primary-color); font-weight:800;">${q.topic || 'عام'} | ${q.points} نقاط</div>
                <div id="curr-q-timer" style="font-size:13px; color:#e53e3e; font-weight:800; display:none;">الوقت المتبقي: <span></span>ث</div>
            </div>
            <h3>${q.question_text}</h3>`;

        if(q.media_url) {
            html += `<div style="margin-bottom:20px; text-align:center;"><img src="${q.media_url}" style="max-width:100%; border-radius:12px; max-height:300px; border:1px solid #eee;"></div>`;
        }

        html += `<div class="sm-options-grid">`;

        if(q.question_type === 'mcq') {
            const opts = JSON.parse(q.options || '[]');
            if(<?php echo $assign->randomize_answers; ?> == 1) opts.sort(() => Math.random() - 0.5);
            opts.forEach((opt, i) => {
                const sel = answers[q.id] === opt ? 'selected' : '';
                html += `<div class="sm-option-item ${sel}" onclick="smSelectOption(${q.id}, '${opt}')">
                    <div class="sm-option-circle"></div>
                    <div>${opt}</div>
                </div>`;
            });
        } else if(q.question_type === 'true_false') {
            const opts = ['true', 'false'];
            opts.forEach(opt => {
                const label = opt === 'true' ? 'صح' : 'خطأ';
                const sel = answers[q.id] === opt ? 'selected' : '';
                html += `<div class="sm-option-item ${sel}" onclick="smSelectOption(${q.id}, '${opt}')">
                    <div class="sm-option-circle"></div>
                    <div>${label}</div>
                </div>`;
            });
        } else if(q.question_type === 'essay') {
            html += `<textarea class="sm-textarea" placeholder="اكتب إجابتك هنا..." rows="6" oninput="smSelectOption(${q.id}, this.value)">${answers[q.id] || ''}</textarea>`;
        } else if(q.question_type === 'ordering') {
            let items = JSON.parse(q.options || '[]');
            let currentOrder = answers[q.id] || [...items].sort(() => Math.random() - 0.5);
            html += `<div class="q-order-wrap" style="display:grid; gap:8px;">`;
            currentOrder.forEach(it => {
                html += `<div class="sm-option-item" draggable="true" ondragstart="smDragStart(event)" ondragover="smDragOver(event)" ondrop="smDrop(event)">
                    <span class="dashicons dashicons-menu"></span> ${it}
                </div>`;
            });
            html += `</div>`;
        } else if(q.question_type === 'matching') {
            let pairs = JSON.parse(q.options || '[]');
            let keys = pairs.map(p => p.key);
            let vals = pairs.map(p => p.val).sort(() => Math.random() - 0.5);
            html += `<div style="display:grid; gap:10px;">`;
            keys.forEach((k, kidx) => {
                const saved = (answers[q.id] && answers[q.id][k]) ? answers[q.id][k] : '';
                html += `<div style="display:flex; align-items:center; gap:15px;">
                    <div style="flex:1; padding:12px; background:#f8fafc; border-radius:10px; border:1px solid #eee; font-weight:700;">${k}</div>
                    <div style="color:var(--sm-primary-color); font-weight:900;">←</div>
                    <select class="sm-select" style="flex:1;" onchange="smSelectMatching(${q.id}, '${k}', this.value)">
                        <option value="">اختر المقابل...</option>
                        ${vals.map(v => `<option value="${v}" ${saved == v ? 'selected' : ''}>${v}</option>`).join('')}
                    </select>
                </div>`;
            });
            html += `</div>`;
        } else if(q.question_type === 'short_answer') {
             html += `<input type="text" class="sm-input" placeholder="اكتب إجابتك هنا..." oninput="smSelectOption(${q.id}, this.value)" value="${answers[q.id] || ''}">`;
        }

        html += `</div></div>`;
        $('#question-container').html(html);

        $('#prev-q-btn').toggle(currentIdx > 0 && <?php echo $assign->lock_navigation; ?> == 0);
        $('#next-q-btn').text(currentIdx === QDATA.length - 1 ? 'المراجعة النهائية' : 'التالي');

        // Individual question timer
        clearInterval(qTimer);
        if(q.time_limit > 0) {
            qTimeLeft = q.time_limit;
            $('#curr-q-timer').show().find('span').text(qTimeLeft);
            qTimer = setInterval(() => {
                qTimeLeft--;
                $('#curr-q-timer span').text(qTimeLeft);
                if(qTimeLeft <= 0) {
                    clearInterval(qTimer);
                    smNavigateQuestion(1);
                }
            }, 1000);
        }
    }

    window.smDragStart = (e) => { $(e.target).addClass('dragging'); };
    window.smDragOver = (e) => { e.preventDefault(); };
    window.smDrop = (e) => {
        e.preventDefault();
        const dragging = $('.dragging');
        const container = dragging.parent();
        const afterElement = getDragAfterElement(container[0], e.clientY);
        if(afterElement == null) container.append(dragging);
        else dragging.insertBefore(afterElement);
        dragging.removeClass('dragging');

        // Save new order
        const qid = QDATA[currentIdx].id;
        answers[qid] = container.find('.sm-option-item').map(function(){ return $(this).text().trim(); }).get();
    };

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.sm-option-item:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if(offset < 0 && offset > closest.offset) return { offset: offset, element: child };
            else return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    window.smSelectOption = (qid, val) => {
        answers[qid] = val;
        const q = QDATA[currentIdx];
        if(q.question_type === 'mcq' || q.question_type === 'true_false') {
            renderQuestion();
        }
    };

    window.smSelectMatching = (qid, key, val) => {
        if(!answers[qid]) answers[qid] = {};
        answers[qid][key] = val;
    };

    window.smNavigateQuestion = (dir) => {
        if(dir === 1 && currentIdx === QDATA.length - 1) {
            smSubmitExamFinal();
            return;
        }
        currentIdx += dir;
        if (currentIdx >= QDATA.length) currentIdx = QDATA.length - 1;
        if (currentIdx < 0) currentIdx = 0;
        renderQuestion();
    };

    function setupSecurityMonitoring() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                smLogAction('tab_switch', 'تبديل التبويب أو تصغير الشاشة');
                alert('تنبيه أمني: يمنع تبديل التبويبات أثناء الاختبار. تم تسجيل هذا الإجراء للإدارة.');
            }
        });

        $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', () => {
            if (!document.fullscreenElement && !document.webkitIsFullScreen && !document.mozFullScreen && !document.msFullscreenElement) {
                smLogAction('full_screen_exit', 'الخروج من وضع ملء الشاشة');
                alert('تحذير: يجب البقاء في وضع ملء الشاشة. تكرار ذلك قد يؤدي لإلغاء الاختبار.');
            }
        });

        // Disable Right Click, Inspect, and Copy/Paste
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('copy', e => e.preventDefault());
        document.addEventListener('cut', e => e.preventDefault());
        document.addEventListener('paste', e => e.preventDefault());

        document.onkeydown = e => {
            if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && (e.keyCode == 73 || e.keyCode == 74)) || (e.ctrlKey && e.keyCode == 85)) return false;
        };
    }

    function smLogAction(type, details) {
        const fd = new FormData();
        fd.append('action', 'sm_log_test_action');
        fd.append('assignment_id', AID);
        fd.append('type', type);
        fd.append('details', details);
        fd.append('nonce', NONCE);
        fetch(ajaxurl, { method: 'POST', body: fd });
    }

    function smSyncProgress() {
        const fd = new FormData();
        fd.append('action', 'sm_sync_test_progress');
        fd.append('assignment_id', AID);
        fd.append('progress', JSON.stringify(answers));
        fd.append('nonce', NONCE);
        fetch(ajaxurl, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
            if (res.data.status === 'terminated') {
                isTerminated = true;
                alert('تم إنهاء الاختبار من قبل الإدارة لمخالفة التعليمات.');
                location.reload();
            }
        });
    }

    window.smSubmitExamFinal = (isAuto = false) => {
        if (!isAuto && !confirm('هل أنت متأكد من تسليم الإجابات النهائية؟')) return;

        clearInterval(timer);
        $('#test-stage-3').fadeOut();
        $('#test-stage-4').show().html('<h3>جاري معالجة الإجابات ورصد النتيجة...</h3>');

        const fd = new FormData();
        fd.append('action', 'sm_submit_survey_response');
        fd.append('survey_id', SID);
        fd.append('assignment_id', AID);
        fd.append('responses', JSON.stringify(answers));
        fd.append('nonce', '<?php echo $nonce; ?>');

        fetch(ajaxurl, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if (res.success) {
                const d = res.data;
                const icon = d.passed ? '✅' : '❌';
                const color = d.passed ? '#38a169' : '#e53e3e';
                const status = d.passed ? 'ناجح' : 'لم تجتز';

                $('#final-result-content').html(`
                    <div style="font-size:60px; margin-bottom:20px;">${icon}</div>
                    <h2 style="font-weight:900; color:${color};">${status}</h2>
                    <div style="font-size:20px; font-weight:800; margin:20px 0;">الدرجة النهائية: ${Math.round(d.score)}%</div>
                    <p style="color:#64748b; max-width:500px; margin:0 auto 30px;">تم تسجيل نتيجتك رسمياً في ملفك النقابي. يمكنك مراجعة الإدارة المختصة لاستكمال إجراءات الترخيص.</p>
                    <button onclick="document.exitFullscreen(); location.href='<?php echo home_url('/my-account'); ?>';" class="sm-btn" style="width:200px;">العودة للملف الشخصي</button>
                `);
                smGoToStage(4);
            }
        });
    };

    function smNotifyAlert(msg) {
        if (typeof smShowNotification === 'function') smShowNotification(msg);
        else alert(msg);
    }

})(jQuery);
</script>
