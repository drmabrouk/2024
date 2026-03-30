<?php if (!defined('ABSPATH')) exit; ?>
<div class="sm-surveys-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin:0;">قسم امتحانات التراخيص</h3>
        <div style="display: flex; gap: 10px;">
            <button onclick="smOpenPrintCustomizer('surveys')" class="sm-btn" style="background: #4a5568; width: auto;"><span class="dashicons dashicons-printer"></span> طباعة مخصصة</button>
            <button class="sm-btn" onclick="smOpenNewSurveyModal()" style="width: auto;">+ إنشاء اختبار جديد</button>
        </div>
    </div>

    <div class="sm-tabs-wrapper" style="display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
        <button class="sm-tab-btn sm-active" onclick="smOpenInternalTab('tests-list', this)">الاختبارات المتاحة</button>
        <button class="sm-tab-btn" onclick="smOpenInternalTab('test-groups-tab', this)">مجموعات الطلاب</button>
        <button class="sm-tab-btn" onclick="smOpenInternalTab('active-sessions', this)">المراقبة المباشرة</button>
    </div>

    <?php
    global $wpdb;
    $user = wp_get_current_user();
    $roles = (array)$user->roles;
    $is_sys_admin = in_array('administrator', $roles) || current_user_can('manage_options');
    $is_general_officer = in_array('sm_general_officer', $roles);
    $is_branch_officer = in_array('sm_branch_officer', $roles);
    $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
    $test_type_map = ['practice' => 'مزاولة مهنة', 'promotion' => 'ترقية درجة', 'training' => 'دورة تدريبية'];
    $db_branches = SM_DB::get_branches_data();
    if (!is_array($db_branches)) $db_branches = [];
    $all_users = get_users(['role__in' => ['sm_member', 'sm_branch_officer', 'sm_general_officer']]);
    ?>

    <!-- TAB: Tests List -->
    <div id="tests-list" class="sm-internal-tab">
        <!-- Advanced Filter & Search Engine -->
        <div style="background: #f8fafc; padding: 25px; border-radius: 15px; margin-bottom: 30px; border: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
        <div style="flex: 2; min-width: 250px;">
            <label class="sm-label" style="font-size: 12px; margin-bottom: 8px; display: block; color: #64748b;">ابحث باسم الاختبار:</label>
            <div style="position: relative;">
                <input type="text" id="test_search_input" class="sm-input" placeholder="اكتب اسم الاختبار للبحث..." oninput="smApplyTestFilters()">
                <span class="dashicons dashicons-search" style="position: absolute; left: 10px; top: 12px; color: #94a3b8;"></span>
            </div>
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label class="sm-label" style="font-size: 12px; margin-bottom: 8px; display: block; color: #64748b;">تصفية بالنوع:</label>
            <select id="test_type_filter" class="sm-select" onchange="smApplyTestFilters()">
                <option value="all">كل الأنواع</option>
                <?php foreach($test_type_map as $k => $v) echo "<option value='$k'>$v</option>"; ?>
            </select>
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label class="sm-label" style="font-size: 12px; margin-bottom: 8px; display: block; color: #64748b;">تصفية بالفرع:</label>
            <select id="test_branch_filter" class="sm-select" onchange="smApplyTestFilters()">
                <option value="all">كل الفروع</option>
                <option value="all_branches">عام (لكل الفروع)</option>
                <?php
                foreach($db_branches as $b) echo "<option value='".esc_attr($b->slug)."'>".esc_html($b->name)."</option>";
                ?>
            </select>
        </div>
        <button class="sm-btn sm-btn-outline" onclick="smResetTestFilters()" style="height: 45px; width: auto; padding: 0 20px;">إعادة تعيين</button>
    </div>

        <div class="sm-table-container" style="border-radius: 15px; overflow-x: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
            <table class="sm-table" id="tests-admin-table">
                <thead>
                    <tr style="background: #f1f5f9;">
                        <th>بيانات الاختبار</th>
                        <th>الإعدادات والوقت</th>
                        <th>الفرع / التخصص</th>
                        <th>تاريخ البدء</th>
                        <th>الحالة</th>
                        <th>المشاركات</th>
                        <th style="text-align: left;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $surveys = SM_DB::get_surveys_admin();
                    if (!is_array($surveys)) $surveys = [];

                    $specs_labels = SM_Settings::get_specializations();
                    foreach ($surveys as $s):
                        $responses = SM_DB::get_survey_responses($s->id);
                        $responses_count = is_array($responses) ? count($responses) : 0;
                        $questions = SM_DB::get_test_questions($s->id);
                        $questions_count = is_array($questions) ? count($questions) : 0;
                        $branch_label = ($s->branch === 'all') ? 'كل الفروع' : (SM_Settings::get_branch_name($s->branch) ?: $s->branch);
                    ?>
                    <tr class="sm-test-row"
                        data-title="<?php echo esc_attr($s->title); ?>"
                        data-type="<?php echo esc_attr($s->test_type); ?>"
                        data-branch="<?php echo esc_attr($s->branch); ?>">
                        <td>
                            <div style="font-weight: 800; color:var(--sm-dark-color); font-size: 1.1em;"><?php echo esc_html($s->title); ?></div>
                            <div style="font-size: 11px; color:#64748b; margin-top:5px; display: flex; align-items: center; gap: 5px;">
                                <span class="dashicons dashicons-editor-help" style="font-size:14px; width:14px; height:14px; color: var(--sm-primary-color);"></span> <?php echo $questions_count; ?> سؤال مدرج
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 12px; margin-bottom: 3px;">⏰ <span style="font-weight: 700;"><?php echo $s->time_limit; ?></span> دقيقة</div>
                            <div style="font-size: 12px; color:#38a169; font-weight:700;">🎯 نجاح: <?php echo $s->pass_score; ?>%</div>
                        </td>
                        <td>
                            <div style="font-size: 12px; font-weight:800; color:var(--sm-primary-color);"><?php echo $branch_label; ?></div>
                            <div style="font-size: 11px; color:#64748b; margin-top: 3px;"><?php echo !empty($s->specialty) ? ($specs_labels[$s->specialty] ?? $s->specialty) : 'تخصص عام'; ?></div>
                        </td>
                        <td style="font-size: 12px; color: #4a5568;"><?php echo date('Y-m-d', strtotime($s->created_at)); ?></td>
                        <td>
                            <?php if ($s->status === 'active'): ?>
                                <span class="sm-badge sm-badge-high" style="font-size: 10px; padding: 4px 12px;">نشط</span>
                            <?php else: ?>
                                <span class="sm-badge sm-badge-urgent" style="font-size: 10px; padding: 4px 12px;">ملغى</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="sm-btn sm-btn-outline" onclick="smViewSurveyResults(<?php echo $s->id; ?>, '<?php echo esc_js($s->title); ?>')" style="padding: 4px 12px; font-size: 11px; font-weight: 700; border-radius: 8px;">
                                <?php echo $responses_count; ?> نتيجة
                            </button>
                        </td>
                        <td>
                            <div style="display:flex; gap:8px; justify-content: flex-end;">
                                <button class="sm-btn" style="padding:6px 12px; font-size:11px; background:var(--sm-dark-color); border-radius: 8px;" onclick='smOpenQuestionBank(<?php echo esc_attr(json_encode($s)); ?>)'>الأسئلة</button>
                                <button class="sm-btn sm-btn-outline" onclick="smOpenEditSurveyModal(<?php echo esc_attr(json_encode($s)); ?>)" style="padding: 6px 10px; font-size: 11px; border-radius: 8px;" title="تعديل"><span class="dashicons dashicons-edit"></span></button>
                                <?php if ($s->status === 'active'): ?>
                                    <button class="sm-btn" style="padding: 6px 15px; font-size: 11px; border-radius: 8px; background: #3182ce;" onclick="smOpenAssignModal(<?php echo $s->id; ?>, '<?php echo esc_js($s->title); ?>')">تعيين للعضو</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Test Groups -->
    <div id="test-groups-tab" class="sm-internal-tab" style="display:none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="margin:0;">إدارة مجموعات الطلاب والقاعات</h4>
            <button class="sm-btn" onclick="smOpenNewGroupModal()" style="width: auto;">+ إنشاء مجموعة جديدة</button>
        </div>
        <div class="sm-table-container">
            <table class="sm-table">
                <thead>
                    <tr>
                        <th>اسم المجموعة</th>
                        <th>الفرع</th>
                        <th>عدد الأعضاء</th>
                        <th>تاريخ الإنشاء</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody id="test-groups-body">
                    <?php
                    $groups = $wpdb->get_results("SELECT g.*, (SELECT COUNT(*) FROM {$wpdb->prefix}sm_test_group_members WHERE group_id = g.id) as members_count FROM {$wpdb->prefix}sm_test_groups g ORDER BY g.created_at DESC");
                    if(empty($groups)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:20px; color:#94a3b8;">لا توجد مجموعات مسجلة حالياً.</td></tr>
                    <?php else: foreach($groups as $g): ?>
                        <tr>
                            <td><strong><?php echo esc_html($g->name); ?></strong></td>
                            <td><?php echo ($g->branch === 'all') ? 'كل الفروع' : (SM_Settings::get_branch_name($g->branch) ?: $g->branch); ?></td>
                            <td><span class="sm-badge sm-badge-low"><?php echo $g->members_count; ?> عضو</span></td>
                            <td><?php echo date('Y-m-d', strtotime($g->created_at)); ?></td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <button class="sm-btn sm-btn-outline" onclick='smOpenEditGroupModal(<?php echo json_encode($g); ?>)' style="padding:4px 8px; font-size:11px;">تعديل</button>
                                    <button class="sm-btn" onclick="smManageGroupMembers(<?php echo $g->id; ?>, '<?php echo esc_js($g->name); ?>')" style="padding:4px 8px; font-size:11px; background:var(--sm-dark-color);">إدارة الأعضاء</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Live Monitoring -->
    <div id="active-sessions" class="sm-internal-tab" style="display:none;">
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; margin-bottom:30px;">
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; text-align:center;">
                <div style="font-size:12px; color:#64748b;">إجمالي الممتحنين الآن</div>
                <div id="active-count-badge" style="font-size:28px; font-weight:900; color:var(--sm-primary-color);">0</div>
            </div>
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; text-align:center;">
                <div style="font-size:12px; color:#64748b;">تنبيهات أمنية (Live)</div>
                <div id="security-alerts-count" style="font-size:28px; font-weight:900; color:#e53e3e;">0</div>
            </div>
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; text-align:center;">
                <div style="font-size:12px; color:#64748b;">نسبة التقدم الكلية</div>
                <div style="font-size:28px; font-weight:900; color:#38a169;">85%</div>
            </div>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:25px;">
            <div class="sm-table-container" style="flex: 1; min-width: 500px; overflow-x: auto;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h4 style="margin:0; font-weight:800; color:var(--sm-dark-color);">الجلسات النشطة حالياً</h4>
                    <button class="sm-btn sm-btn-outline" onclick="smRefreshLiveSessions()" style="width:auto; padding:4px 12px; font-size:11px;"><span class="dashicons dashicons-update"></span> تحديث فوري</button>
                </div>
                <table class="sm-table">
                    <thead>
                        <tr>
                            <th>المختبر</th>
                            <th>الاختبار</th>
                            <th>التقدم</th>
                            <th>آخر نبض</th>
                            <th>الحالة</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody id="live-sessions-body">
                        <?php
                        $active_sessions = $wpdb->get_results("
                            SELECT a.*, s.title, m.name as member_name
                            FROM {$wpdb->prefix}sm_test_assignments a
                            JOIN {$wpdb->prefix}sm_surveys s ON a.test_id = s.id
                            JOIN {$wpdb->prefix}sm_members m ON a.user_id = m.wp_user_id
                            WHERE a.status = 'active'
                            ORDER BY a.last_heartbeat DESC
                        ");
                        if(empty($active_sessions) || !is_array($active_sessions)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">لا توجد جلسات نشطة حالياً.</td></tr>
                        <?php else: foreach($active_sessions as $as):
                            $diff = time() - strtotime($as->last_heartbeat);
                            $pulse_color = ($diff < 60) ? '#38a169' : '#e53e3e';
                            $progress_data = json_decode($as->session_data, true);
                            $answered_count = is_array($progress_data) ? count(array_filter($progress_data)) : 0;
                        ?>
                            <tr data-aid="<?php echo $as->id; ?>">
                                <td>
                                    <div style="font-weight:800;"><?php echo $as->member_name; ?></div>
                                    <div style="font-size:9px; color:#64748b;">بدأ: <?php echo date('H:i:s', strtotime($as->started_at)); ?></div>
                                </td>
                                <td><?php echo $as->title; ?></td>
                                <td>
                                    <div style="font-size:11px; margin-bottom:4px;">تمت إجابة <?php echo $answered_count; ?> سؤال</div>
                                    <div style="width:100px; height:6px; background:#edf2f7; border-radius:10px; overflow:hidden;">
                                        <div style="width:<?php echo ($answered_count > 0) ? '60' : '0'; ?>%; height:100%; background:#3182ce;"></div>
                                    </div>
                                </td>
                                <td style="color:<?php echo $pulse_color; ?>; font-weight:700; font-size:11px;">متصل (<?php echo $diff; ?>ث)</td>
                                <td><span class="sm-badge sm-badge-high">مراقب</span></td>
                                <td><button onclick="smTerminateSession(<?php echo $as->id; ?>)" class="sm-btn sm-btn-outline" style="color:#e53e3e; border-color:#e53e3e; padding:4px 10px; font-size:10px;">إنهاء بقوة</button></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:15px; padding:20px; width: 350px; flex-shrink: 0; max-width: 100%;">
                <h4 style="margin:0 0 20px 0; font-weight:800; color:var(--sm-dark-color);">سجل التنبيهات الأمنية (Live)</h4>
                <div id="live-security-logs" style="max-height:500px; overflow-y:auto; display:grid; gap:10px;">
                    <?php
                    $logs = $wpdb->get_results("
                        SELECT l.*, m.name as member_name
                        FROM {$wpdb->prefix}sm_test_logs l
                        JOIN {$wpdb->prefix}sm_members m ON l.user_id = m.wp_user_id
                        ORDER BY l.created_at DESC LIMIT 20
                    ");
                    if (!is_array($logs)) $logs = [];
                    foreach($logs as $log):
                        $color = ($log->action_type === 'start' || $log->action_type === 'submit') ? '#38a169' : '#e53e3e';
                    ?>
                        <div style="background:#fff; border:1px solid #eee; border-right:4px solid <?php echo $color; ?>; padding:12px; border-radius:8px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                                <span style="font-weight:800; font-size:11px;"><?php echo $log->member_name; ?></span>
                                <span style="font-size:10px; color:#94a3b8;"><?php echo date('H:i:s', strtotime($log->created_at)); ?></span>
                            </div>
                            <div style="font-size:12px; font-weight:600;"><?php echo $log->details; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

<div id="new-survey-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 750px;">
        <div class="sm-modal-header">
            <h3 id="survey-modal-title">إعداد اختبار ممارسة مهنية جديد</h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body" style="padding: 30px;">
            <input type="hidden" id="survey_id">
            <div class="sm-form-group">
                <label class="sm-label">عنوان الاختبار / المسابقة:</label>
                <input type="text" id="survey_title" class="sm-input" placeholder="مثال: اختبار الحصول على درجة أخصائي" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0;">
                <div class="sm-form-group" style="margin-bottom:0;">
                    <label class="sm-label" style="font-size:11px;">مدة الاختبار (دقيقة):</label>
                    <input type="number" id="survey_time_limit" class="sm-input" value="30" style="padding:8px;">
                </div>
                <div class="sm-form-group" style="margin-bottom:0;">
                    <label class="sm-label" style="font-size:11px;">أقصى محاولات:</label>
                    <input type="number" id="survey_max_attempts" class="sm-input" value="1" style="padding:8px;">
                </div>
                <div class="sm-form-group" style="margin-bottom:0;">
                    <label class="sm-label" style="font-size:11px;">درجة النجاح (%):</label>
                    <input type="number" id="survey_pass_score" class="sm-input" value="50" style="padding:8px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #fff; padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #eee;">
                <div class="sm-form-group" style="margin-bottom:0;">
                    <label class="sm-label" style="font-size:11px;">تاريخ البدء (اختياري):</label>
                    <input type="datetime-local" id="survey_start_time" class="sm-input" style="padding:8px;">
                </div>
                <div class="sm-form-group" style="margin-bottom:0;">
                    <label class="sm-label" style="font-size:11px;">تاريخ الانتهاء (اختياري):</label>
                    <input type="datetime-local" id="survey_end_time" class="sm-input" style="padding:8px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; background: #fcfcfc; padding: 15px; border-radius: 10px; border: 1px solid #eee;">
                <label style="display:flex; align-items:center; gap:8px; font-size:11px; cursor:pointer;"><input type="checkbox" id="survey_show_results" checked> إظهار النتيجة فوراً</label>
                <label style="display:flex; align-items:center; gap:8px; font-size:11px; cursor:pointer;"><input type="checkbox" id="survey_random_order"> ترتيب عشوائي للأسئلة</label>
                <label style="display:flex; align-items:center; gap:8px; font-size:11px; cursor:pointer;"><input type="checkbox" id="survey_randomize_answers"> ترتيب عشوائي للإجابات</label>
                <label style="display:flex; align-items:center; gap:8px; font-size:11px; cursor:pointer;"><input type="checkbox" id="survey_lock_navigation"> قفل الرجوع للخلف</label>
                <label style="display:flex; align-items:center; gap:8px; font-size:11px; cursor:pointer;"><input type="checkbox" id="survey_auto_grade" checked> تصحيح تلقائي</label>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="sm-form-group">
                    <label class="sm-label">التخصص المرتبط:</label>
                    <select id="survey_specialty" class="sm-select">
                        <option value="">-- كافة التخصصات (عام) --</option>
                        <?php foreach (SM_Settings::get_specializations() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                    </select>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">نوع الاختبار:</label>
                    <select id="survey_test_type" class="sm-select">
                        <option value="practice">اختبار مزاولة مهنة</option>
                        <option value="promotion">اختبار ترقية درجة</option>
                        <option value="training">دورة تدريبية</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="sm-form-group">
                    <label class="sm-label">الفئة المستهدفة بالظهور التلقائي:</label>
                    <select id="survey_recipients" class="sm-select">
                        <option value="all">الجميع</option>
                        <option value="sm_member">أعضاء النقابة</option>
                        <option value="sm_general_officer">مسؤولو النقابة العامة</option>
                        <option value="sm_branch_officer">مسؤولو الفروع</option>
                    </select>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">متاح لفرع محدد:</label>
                    <select id="survey_branch" class="sm-select">
                        <option value="all">متاح لكافة الفروع</option>
                        <?php foreach($db_branches as $b) echo "<option value='".esc_attr($b->slug)."'>".esc_html($b->name)."</option>"; ?>
                    </select>
                </div>
            </div>

            <div style="margin-top: 30px; display:flex; gap:10px;">
                <button class="sm-btn" id="survey_submit_btn" onclick="smSaveSurvey()" style="flex:2; height:50px; font-weight:800;">حفظ ونشر الاختبار</button>
                <button class="sm-btn sm-btn-outline" onclick="this.closest('.sm-modal-overlay').style.display='none'" style="flex:1;">إلغاء</button>
            </div>
        </div>
    </div>
</div>

<!-- QUESTION BANK MODAL -->
<div id="question-bank-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 900px; width: 95%;">
        <div class="sm-modal-header">
            <h3>بنك أسئلة الاختبار: <span id="bank-test-title"></span></h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body" style="padding: 0;">
            <div style="display: flex; flex-wrap: wrap; min-height: 400px; max-height: 80vh;">
                <!-- Add Question Form -->
                <div style="background: #f8fafc; border-left: 1px solid #e2e8f0; padding: 25px; overflow-y: auto; width: 350px; flex-shrink: 0; max-width: 100%;">
                    <h4 style="margin-top:0;">إضافة سؤال جديد</h4>
                    <form id="add-question-form">
                        <input type="hidden" id="q_test_id">
                        <div class="sm-form-group">
                            <label class="sm-label">نص السؤال:</label>
                            <textarea id="q_text" class="sm-textarea" rows="3" required></textarea>
                        </div>
                        <div class="sm-form-group">
                            <label class="sm-label">نوع السؤال:</label>
                            <select id="q_type" class="sm-select" onchange="smToggleQuestionOptions(this.value)">
                                <option value="mcq">اختيار من متعدد (MCQ)</option>
                                <option value="true_false">صح أو خطأ</option>
                                <option value="short_answer">إجابة قصيرة</option>
                                <option value="essay">سؤال مقالي</option>
                                <option value="ordering">ترتيب العناصر</option>
                                <option value="matching">توصيل (Matching)</option>
                            </select>
                        </div>

                        <div class="sm-form-group">
                            <label class="sm-label">ملفات وسائط (اختياري):</label>
                            <div style="display:flex; gap:5px;">
                                <input type="text" id="q_media_url" class="sm-input" placeholder="رابط صورة أو فيديو...">
                                <button type="button" class="sm-btn sm-btn-outline" style="width:auto; padding:0 10px;" onclick="smOpenMediaUploader('q_media_url')"><span class="dashicons dashicons-admin-media"></span></button>
                            </div>
                        </div>

                        <div id="mcq-options-container">
                            <label class="sm-label">الخيارات المتاحة:</label>
                            <div style="display:grid; gap:8px; margin-bottom:15px;">
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="0" checked><input type="text" class="sm-input q-opt" placeholder="الخيار الأول"></div>
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="1"><input type="text" class="sm-input q-opt" placeholder="الخيار الثاني"></div>
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="2"><input type="text" class="sm-input q-opt" placeholder="الخيار الثالث"></div>
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="3"><input type="text" class="sm-input q-opt" placeholder="الخيار الرابع"></div>
                            </div>
                        </div>

                        <div id="tf-options-container" style="display:none;">
                            <label class="sm-label">الإجابة الصحيحة:</label>
                            <select id="q_correct_tf" class="sm-select">
                                <option value="true">صح</option>
                                <option value="false">خطأ</option>
                            </select>
                        </div>

                        <div id="short-options-container" style="display:none;">
                            <label class="sm-label">الإجابة النموذجية / الكلمات المفتاحية:</label>
                            <input type="text" id="q_correct_short" class="sm-input">
                            <p style="font-size:10px; color:#64748b; margin-top:5px;">في حال السؤال المقالي، أدخل كلمات مفتاحية للتصحيح التلقائي مفصولة بفاصلة.</p>
                        </div>

                        <div id="ordering-options-container" style="display:none;">
                            <label class="sm-label">رتب العناصر (بالترتيب الصحيح):</label>
                            <div style="display:grid; gap:8px; margin-bottom:15px;">
                                <input type="text" class="sm-input q-order-opt" placeholder="العنصر 1">
                                <input type="text" class="sm-input q-order-opt" placeholder="العنصر 2">
                                <input type="text" class="sm-input q-order-opt" placeholder="العنصر 3">
                                <input type="text" class="sm-input q-order-opt" placeholder="العنصر 4">
                            </div>
                        </div>

                        <div id="matching-options-container" style="display:none;">
                            <label class="sm-label">أزواج التوصيل (العنصر | مقابله):</label>
                            <div style="display:grid; gap:8px; margin-bottom:15px;">
                                <div style="display:flex; gap:5px;"><input type="text" class="sm-input q-match-key" placeholder="A1"><input type="text" class="sm-input q-match-val" placeholder="B1"></div>
                                <div style="display:flex; gap:5px;"><input type="text" class="sm-input q-match-key" placeholder="A2"><input type="text" class="sm-input q-match-val" placeholder="B2"></div>
                                <div style="display:flex; gap:5px;"><input type="text" class="sm-input q-match-key" placeholder="A3"><input type="text" class="sm-input q-match-val" placeholder="B3"></div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; margin-top:15px;">
                            <div class="sm-form-group"><label class="sm-label">النقاط:</label><input type="number" id="q_points" class="sm-input" value="1" style="padding:8px;"></div>
                            <div class="sm-form-group"><label class="sm-label">وقت السؤال(ث):</label><input type="number" id="q_time_limit" class="sm-input" value="0" style="padding:8px;" title="0 تعني استخدام وقت الاختبار الكلي"></div>
                            <div class="sm-form-group"><label class="sm-label">الصعوبة:</label><select id="q_difficulty" class="sm-select"><option value="easy">سهل</option><option value="medium" selected>متوسط</option><option value="hard">صعب</option></select></div>
                        </div>
                        <div class="sm-form-group"><label class="sm-label">الموضوع / التصنيف:</label><input type="text" id="q_topic" class="sm-input" placeholder="مثال: قوانين النقابة"></div>

                        <button type="submit" class="sm-btn" style="width:100%; margin-top:10px;">إضافة السؤال للبنك</button>
                    </form>
                </div>
                <!-- Questions List -->
                <div style="padding: 25px; overflow-y: auto; flex: 1; min-width: 300px;">
                    <div id="bank-questions-list">
                        <!-- Questions load here via JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TEST GROUP MODAL -->
<div id="test-group-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 500px;">
        <div class="sm-modal-header">
            <h3 id="group-modal-title">إنشاء مجموعة طلاب جديدة</h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body" style="padding: 25px;">
            <input type="hidden" id="group_id">
            <div class="sm-form-group">
                <label class="sm-label">اسم المجموعة / القاعة:</label>
                <input type="text" id="group_name" class="sm-input" placeholder="مثال: قاعة تدريب أ - يناير 2024" required>
            </div>
            <div class="sm-form-group">
                <label class="sm-label">الفرع المرتبط:</label>
                <select id="group_branch" class="sm-select">
                    <option value="all">كل الفروع</option>
                    <?php foreach($db_branches as $b) echo "<option value='".esc_attr($b->slug)."'>".esc_html($b->name)."</option>"; ?>
                </select>
            </div>
            <div class="sm-form-group">
                <label class="sm-label">الوصف:</label>
                <textarea id="group_description" class="sm-textarea" rows="3"></textarea>
            </div>
            <div style="margin-top: 20px; display:flex; gap:10px;">
                <button class="sm-btn" id="group_submit_btn" onclick="smSaveTestGroup()" style="flex:2; height:45px; font-weight:800;">حفظ المجموعة</button>
                <button class="sm-btn sm-btn-outline" onclick="this.closest('.sm-modal-overlay').style.display='none'" style="flex:1;">إلغاء</button>
            </div>
        </div>
    </div>
</div>

<!-- MANAGE GROUP MEMBERS MODAL -->
<div id="group-members-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 700px;">
        <div class="sm-modal-header">
            <h3>إدارة أعضاء المجموعة: <span id="m-group-name"></span></h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body" style="padding: 25px;">
            <input type="hidden" id="m_group_id">
            <div style="display:flex; gap:10px; margin-bottom:20px;">
                <select id="m_search_users" class="sm-select" multiple style="flex:1; height: 150px;">
                    <?php
                    foreach($all_users as $u) echo "<option value='{$u->ID}'>{$u->display_name} ({$u->user_login})</option>";
                    ?>
                </select>
                <button class="sm-btn" onclick="smAddMembersToGroup()" style="width:auto; height:45px; align-self:flex-end;">+ إضافة للمجموعة</button>
            </div>
            <h5 style="margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:5px;">قائمة الأعضاء الحاليين</h5>
            <div id="group-members-list" style="max-height:300px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc;">
                <!-- Members load here -->
            </div>
        </div>
    </div>
</div>

<!-- ASSIGN TEST MODAL -->
<div id="assign-test-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 500px;">
        <div class="sm-modal-header">
            <h3 id="assign-modal-title">تعيين الاختبار لمستخدمين</h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body" style="padding:25px;">
            <input type="hidden" id="assign_survey_id">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div class="sm-form-group">
                    <label class="sm-label">أفراد محددون:</label>
                    <select id="assign_user_ids" class="sm-select" multiple style="height: 200px;">
                        <?php
                        foreach($all_users as $u) {
                            echo "<option value='{$u->ID}'>{$u->display_name} ({$u->user_login})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">مجموعات الطلاب:</label>
                    <select id="assign_group_ids" class="sm-select" multiple style="height: 200px;">
                        <?php
                        $groups = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}sm_test_groups");
                        foreach($groups as $g) {
                            echo "<option value='{$g->id}'>{$g->name}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <button class="sm-btn" onclick="smSubmitAssignment()" style="width: 100%; margin-top: 20px;">تأكيد التعيين</button>
        </div>
    </div>
</div>

<!-- RESULTS MODAL -->
<div id="survey-results-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 800px;">
        <div class="sm-modal-header">
            <h3 id="res-modal-title">نتائج الاستطلاع</h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div id="survey-results-body" style="max-height: 600px; overflow-y: auto; padding: 25px;">
            <!-- Results will be loaded here -->
        </div>
        <div class="sm-modal-footer" style="padding:15px 25px; border-top:1px solid #eee; display:flex; justify-content:flex-end; gap:10px;">
            <button class="sm-btn" onclick="smExportResultsToCSV()" style="width:auto; background:#38a169; padding:0 25px; height:40px; font-size:12px;">تحميل كملف Excel (CSV)</button>
            <button class="sm-btn sm-btn-outline" onclick="document.getElementById('survey-results-modal').style.display='none'" style="width:auto; height:40px; font-size:12px;">إغلاق</button>
        </div>
    </div>
</div>

</div> <!-- End .sm-surveys-container -->

<script>
function smOpenNewSurveyModal() {
    document.getElementById('survey_id').value = '';
    document.getElementById('survey-modal-title').innerText = 'إعداد اختبار ممارسة مهنية جديد';
    document.getElementById('survey_submit_btn').innerText = 'حفظ ونشر الاختبار';
    document.getElementById('survey_title').value = '';
    document.getElementById('survey_time_limit').value = '30';
    document.getElementById('survey_max_attempts').value = '1';
    document.getElementById('survey_pass_score').value = '50';
    document.getElementById('new-survey-modal').style.display = 'flex';
}

function smOpenEditSurveyModal(s) {
    document.getElementById('survey_id').value = s.id;
    document.getElementById('survey-modal-title').innerText = 'تعديل إعدادات الاختبار: ' + s.title;
    document.getElementById('survey_submit_btn').innerText = 'تحديث إعدادات الاختبار';
    document.getElementById('survey_title').value = s.title;
    document.getElementById('survey_time_limit').value = s.time_limit;
    document.getElementById('survey_max_attempts').value = s.max_attempts;
    document.getElementById('survey_pass_score').value = s.pass_score;
    document.getElementById('survey_specialty').value = s.specialty;
    document.getElementById('survey_test_type').value = s.test_type;
    document.getElementById('survey_recipients').value = s.recipients;
    document.getElementById('survey_branch').value = s.branch || 'all';

    document.getElementById('survey_start_time').value = s.start_time ? s.start_time.replace(' ', 'T') : '';
    document.getElementById('survey_end_time').value = s.end_time ? s.end_time.replace(' ', 'T') : '';
    document.getElementById('survey_show_results').checked = s.show_results == 1;
    document.getElementById('survey_random_order').checked = s.random_order == 1;
    document.getElementById('survey_randomize_answers').checked = s.randomize_answers == 1;
    document.getElementById('survey_lock_navigation').checked = s.lock_navigation == 1;
    document.getElementById('survey_auto_grade').checked = s.auto_grade == 1;

    document.getElementById('new-survey-modal').style.display = 'flex';
}

function smSaveSurvey() {
    const id = document.getElementById('survey_id').value;
    const title = document.getElementById('survey_title').value;
    if (!title) {
        smShowNotification('يرجى إدخال عنوان الاختبار', true);
        return;
    }

    const fd = new FormData();
    fd.append('action', id ? 'sm_update_survey' : 'sm_add_survey');
    if (id) fd.append('id', id);
    fd.append('title', title);
    fd.append('time_limit', document.getElementById('survey_time_limit').value);
    fd.append('max_attempts', document.getElementById('survey_max_attempts').value);
    fd.append('pass_score', document.getElementById('survey_pass_score').value);
    fd.append('specialty', document.getElementById('survey_specialty').value);
    fd.append('test_type', document.getElementById('survey_test_type').value);
    fd.append('recipients', document.getElementById('survey_recipients').value);
    fd.append('branch', document.getElementById('survey_branch').value);
    fd.append('start_time', document.getElementById('survey_start_time').value);
    fd.append('end_time', document.getElementById('survey_end_time').value);
    fd.append('show_results', document.getElementById('survey_show_results').checked ? 1 : 0);
    fd.append('random_order', document.getElementById('survey_random_order').checked ? 1 : 0);
    fd.append('randomize_answers', document.getElementById('survey_randomize_answers').checked ? 1 : 0);
    fd.append('lock_navigation', document.getElementById('survey_lock_navigation').checked ? 1 : 0);
    fd.append('auto_grade', document.getElementById('survey_auto_grade').checked ? 1 : 0);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    const action = document.getElementById('survey_id').value ? 'sm_update_survey' : 'sm_add_survey';
    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        if (res.success) {
            smShowNotification('تم حفظ بيانات الاختبار');
            setTimeout(() => location.reload(), 1000);
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smToggleQuestionOptions(type) {
    document.getElementById('mcq-options-container').style.display = (type === 'mcq') ? 'block' : 'none';
    document.getElementById('tf-options-container').style.display = (type === 'true_false') ? 'block' : 'none';
    document.getElementById('short-options-container').style.display = (type === 'short_answer' || type === 'essay') ? 'block' : 'none';
}

window.smOpenQuestionBank = function(s) {
    document.getElementById('q_test_id').value = s.id;
    document.getElementById('bank-test-title').innerText = s.title;
    smLoadBankQuestions(s.id);
    document.getElementById('question-bank-modal').style.display = 'flex';
};

function smLoadBankQuestions(testId) {
    const list = document.getElementById('bank-questions-list');
    if (!list) return;
    list.innerHTML = '<p>جاري تحميل الأسئلة...</p>';

    fetch(ajaxurl + '?action=sm_get_test_questions&test_id=' + testId + '&nonce=<?php echo wp_create_nonce("sm_admin_action"); ?>')
    .then(r=>r.json()).then(res => {
        if (!res.success) {
            smHandleAjaxError(res);
            list.innerHTML = '';
            return;
        }
        if (!res.data || res.data.length === 0) {
            list.innerHTML = '<div style="text-align:center; padding:40px; color:#94a3b8;"><span class="dashicons dashicons-warning" style="font-size:40px; width:40px; height:40px;"></span><p>لا توجد أسئلة مضافة لهذا الاختبار بعد.</p></div>';
            return;
        }
        let html = '<div style="display:grid; gap:15px;">';
        res.data.forEach((q, idx) => {
            let displayAnswer = q.correct_answer;
            try {
                const parsed = JSON.parse(q.correct_answer);
                if(Array.isArray(parsed)) {
                    if(q.question_type === 'ordering') {
                        displayAnswer = parsed.join(' → ');
                    } else if(q.question_type === 'matching') {
                        displayAnswer = parsed.map(p => `${p.key}: ${p.val}`).join(' | ');
                    }
                }
            } catch(e) {}

            html += `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding: 25px; position:relative; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <div style="position:absolute; left:15px; top:15px; display:flex; gap:10px;">
                        <span class="sm-badge sm-badge-low" style="font-size:10px;">${q.difficulty}</span>
                        <button onclick="smDeleteQuestion(${q.id}, ${testId})" style="border:none; background:none; color:#e53e3e; cursor:pointer;"><span class="dashicons dashicons-trash"></span></button>
                    </div>
                    <div style="font-weight:800; color:var(--sm-dark-color); margin-bottom:10px;">س${idx+1}: ${q.question_text}</div>
                    <div style="font-size:11px; color:#64748b;">
                        النوع: <span style="color:var(--sm-primary-color);">${q.question_type}</span> |
                        النقاط: <strong>${q.points}</strong>
                        ${q.media_url ? ' | <span style="color:#3182ce;">📎 يحتوي وسائط</span>' : ''}
                    </div>
                    <div style="margin-top:10px; padding:10px; background:#f0fff4; border-radius:8px; border:1px solid #c6f6d5; font-size:12px; color:#22543d;">
                        <strong>الإجابة النموذجية:</strong> ${displayAnswer}
                    </div>
                </div>
            `;
        });
        html += '</div>';
        list.innerHTML = html;
    });
}

document.getElementById('add-question-form').onsubmit = function(e) {
    e.preventDefault();
    const testId = document.getElementById('q_test_id').value;
    const type = document.getElementById('q_type').value;
    const fd = new FormData();
    fd.append('action', 'sm_add_test_question');
    fd.append('test_id', testId);
    fd.append('question_text', document.getElementById('q_text').value);
    fd.append('question_type', type);
    fd.append('points', document.getElementById('q_points').value);
    fd.append('time_limit', document.getElementById('q_time_limit').value);
    fd.append('difficulty', document.getElementById('q_difficulty').value);
    fd.append('topic', document.getElementById('q_topic').value);
    fd.append('media_url', document.getElementById('q_media_url').value);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    if (type === 'mcq') {
        const opts = Array.from(document.querySelectorAll('.q-opt')).map(i => i.value);
        const correctIdx = document.querySelector('input[name="correct_mcq"]:checked').value;
        fd.append('options', JSON.stringify(opts));
        fd.append('correct_answer', opts[correctIdx]);
    } else if (type === 'true_false') {
        fd.append('correct_answer', document.getElementById('q_correct_tf').value);
    } else if (type === 'ordering') {
        const items = Array.from(document.querySelectorAll('.q-order-opt')).map(i => i.value).filter(v => v);
        fd.append('options', JSON.stringify(items));
        fd.append('correct_answer', JSON.stringify(items));
    } else if (type === 'matching') {
        const pairs = [];
        document.querySelectorAll('.q-match-key').forEach((el, idx) => {
            const val = document.querySelectorAll('.q-match-val')[idx].value;
            if(el.value && val) pairs.push({key: el.value, val: val});
        });
        fd.append('options', JSON.stringify(pairs));
        fd.append('correct_answer', JSON.stringify(pairs));
    } else {
        fd.append('correct_answer', document.getElementById('q_correct_short').value);
    }

    const action = 'sm_add_test_question';
    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        if (res.success) {
            smShowNotification('تم إضافة السؤال');
            this.reset();
            smLoadBankQuestions(testId);
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
};

function smDeleteQuestion(id, testId) {
    if (!confirm('حذف هذا السؤال نهائياً؟')) return;
    const action = 'sm_delete_test_question';
    const fd = new FormData();
    fd.append('action', action);
    fd.append('id', id);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl + '?action=' + action, {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.success) {
            smShowNotification('تم حذف السؤال');
            smLoadBankQuestions(testId);
        } else {
            smHandleAjaxError(res.data);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smOpenAssignModal(id, title) {
    document.getElementById('assign_survey_id').value = id;
    document.getElementById('assign-modal-title').innerText = 'تعيين الاختبار: ' + title;
    document.getElementById('assign-test-modal').style.display = 'flex';
}

function smOpenNewGroupModal() {
    document.getElementById('group_id').value = '';
    document.getElementById('group-modal-title').innerText = 'إنشاء مجموعة طلاب جديدة';
    document.getElementById('group_name').value = '';
    document.getElementById('group_description').value = '';
    document.getElementById('test-group-modal').style.display = 'flex';
}

function smOpenEditGroupModal(g) {
    document.getElementById('group_id').value = g.id;
    document.getElementById('group-modal-title').innerText = 'تعديل المجموعة: ' + g.name;
    document.getElementById('group_name').value = g.name;
    document.getElementById('group_branch').value = g.branch;
    document.getElementById('group_description').value = g.description;
    document.getElementById('test-group-modal').style.display = 'flex';
}

function smSaveTestGroup() {
    const id = document.getElementById('group_id').value;
    const name = document.getElementById('group_name').value;
    if(!name) return smShowNotification('يرجى إدخال اسم المجموعة', true);

    const fd = new FormData();
    fd.append('action', 'sm_save_test_group');
    if(id) fd.append('id', id);
    fd.append('name', name);
    fd.append('branch', document.getElementById('group_branch').value);
    fd.append('description', document.getElementById('group_description').value);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl + '?action=sm_save_test_group', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.success) {
            smShowNotification('تم حفظ المجموعة');
            setTimeout(() => location.reload(), 1000);
        } else smHandleAjaxError(res);
    });
}

function smManageGroupMembers(id, name) {
    document.getElementById('m_group_id').value = id;
    document.getElementById('m-group-name').innerText = name;
    smLoadGroupMembers(id);
    document.getElementById('group-members-modal').style.display = 'flex';
}

function smLoadGroupMembers(groupId) {
    const list = document.getElementById('group-members-list');
    list.innerHTML = '<p style="text-align:center; padding:20px;">جاري التحميل...</p>';
    fetch(ajaxurl + '?action=sm_get_group_members&group_id=' + groupId)
    .then(r=>r.json()).then(res => {
        if(res.success) {
            if(res.data.length === 0) {
                list.innerHTML = '<p style="text-align:center; padding:20px; color:#94a3b8;">لا يوجد أعضاء في هذه المجموعة.</p>';
                return;
            }
            list.innerHTML = res.data.map(m => `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 15px; border-bottom:1px solid #e2e8f0;">
                    <div><strong>${m.display_name}</strong> <span style="font-size:10px; color:#64748b;">(${m.user_login})</span></div>
                    <button onclick="smRemoveFromGroup(${groupId}, ${m.ID})" style="border:none; background:none; color:#e53e3e; cursor:pointer;"><span class="dashicons dashicons-dismiss" style="font-size:16px;"></span></button>
                </div>
            `).join('');
        }
    });
}

function smAddMembersToGroup() {
    const groupId = document.getElementById('m_group_id').value;
    const select = document.getElementById('m_search_users');
    const userIds = Array.from(select.selectedOptions).map(o => o.value);
    if(userIds.length === 0) return smShowNotification('يرجى اختيار عضو واحد على الأقل', true);

    const fd = new FormData();
    fd.append('action', 'sm_add_group_members');
    fd.append('group_id', groupId);
    userIds.forEach(id => fd.append('user_ids[]', id));
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl + '?action=sm_add_group_members', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.success) {
            smShowNotification('تم إضافة الأعضاء');
            smLoadGroupMembers(groupId);
        } else smHandleAjaxError(res);
    });
}

function smRemoveFromGroup(groupId, userId) {
    if(!confirm('حذف العضو من المجموعة؟')) return;
    const fd = new FormData();
    fd.append('action', 'sm_remove_group_member');
    fd.append('group_id', groupId);
    fd.append('user_id', userId);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl + '?action=sm_remove_group_member', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.success) {
            smShowNotification('تم حذف العضو من المجموعة');
            smLoadGroupMembers(groupId);
        } else smHandleAjaxError(res);
    });
}

function smSubmitAssignment() {
    const survey_id = document.getElementById('assign_survey_id').value;
    const user_ids = Array.from(document.getElementById('assign_user_ids').selectedOptions).map(o => o.value);
    const group_ids = Array.from(document.getElementById('assign_group_ids').selectedOptions).map(o => o.value);

    if (user_ids.length === 0 && group_ids.length === 0) {
        smShowNotification('يرجى اختيار مستخدم أو مجموعة واحدة على الأقل', true);
        return;
    }

    const fd = new FormData();
    fd.append('action', 'sm_assign_test');
    fd.append('survey_id', survey_id);
    user_ids.forEach(id => fd.append('user_ids[]', id));
    group_ids.forEach(id => fd.append('group_ids[]', id));
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    const action = 'sm_assign_test';
    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم تعيين الاختبار بنجاح');
            document.getElementById('assign-test-modal').style.display = 'none';
        } else {
            smHandleAjaxError(res);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smCancelSurvey(id) {
    if (!confirm('هل أنت متأكد من إلغاء هذا الاختبار؟ لن يتمكن أحد من التقديم عليه بعد الآن.')) return;

    const action = 'sm_cancel_survey';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('id', id);
    formData.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl + '?action=' + action, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم إلغاء الاستطلاع');
            setTimeout(() => location.reload(), 1000);
        } else {
            smHandleAjaxError(res.data);
        }
    }).catch(err => smHandleAjaxError(err));
}

function smTerminateSession(aid) {
    if(!confirm('هل أنت متأكد من إنهاء جلسة هذا المختبر؟ سيتم منعه من الاستمرار وإغلاق الاختبار عليه.')) return;
    const action = 'sm_terminate_test_admin';
    const fd = new FormData();
    fd.append('action', action);
    fd.append('assignment_id', aid);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl + '?action=' + action, {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.success) {
            smShowNotification('تم إنهاء الجلسة بنجاح');
            smRefreshLiveSessions();
        }
    });
}

function smRefreshLiveSessions() {
    const body = document.getElementById('live-sessions-body');
    const logBody = document.getElementById('live-security-logs');

    // Refresh Sessions
    fetch(ajaxurl + '?action=sm_get_live_sessions_ajax')
    .then(r=>r.json()).then(res => {
        if(res.success) {
            if(res.data.length === 0) {
                body.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">لا توجد جلسات نشطة حالياً.</td></tr>';
                document.getElementById('active-count-badge').innerText = '0';
            } else {
                document.getElementById('active-count-badge').innerText = res.data.length;
                body.innerHTML = res.data.map(as => {
                const diff = Math.floor((new Date() - new Date(as.last_heartbeat + ' UTC')) / 1000);
                const pulse_color = (diff < 60) ? '#38a169' : '#e53e3e';
                const progress_data = JSON.parse(as.session_data || '{}');
                const answered_count = Object.keys(progress_data).length;

                    return `
                        <tr>
                            <td>
                                <div style="font-weight:800;">${as.member_name}</div>
                                <div style="font-size:9px; color:#64748b;">بدأ: ${as.started_at.split(' ')[1]}</div>
                            </td>
                            <td>${as.title}</td>
                            <td>
                                <div style="font-size:11px; margin-bottom:4px;">تمت إجابة ${answered_count} سؤال</div>
                                <div style="width:100px; height:6px; background:#edf2f7; border-radius:10px; overflow:hidden;">
                                    <div style="width:50%; height:100%; background:#3182ce;"></div>
                                </div>
                            </td>
                            <td style="color:${pulse_color}; font-weight:700; font-size:11px;">متصل (${diff}ث)</td>
                            <td><span class="sm-badge sm-badge-high">مراقب</span></td>
                            <td><button onclick="smTerminateSession(${as.id})" class="sm-btn sm-btn-outline" style="color:#e53e3e; border-color:#e53e3e; padding:4px 10px; font-size:10px;">إنهاء بقوة</button></td>
                        </tr>
                    `;
                }).join('');
            }
        }
    });

    // Refresh Logs
    fetch(ajaxurl + '?action=sm_get_live_security_logs_ajax')
    .then(r=>r.json()).then(res => {
        if(res.success) {
            let alertsCount = 0;
            logBody.innerHTML = res.data.map(log => {
                const color = (log.action_type === 'start' || log.action_type === 'submit') ? '#38a169' : '#e53e3e';
                if(color === '#e53e3e') alertsCount++;
                return `
                    <div style="background:#fff; border:1px solid #eee; border-right:4px solid ${color}; padding:12px; border-radius:8px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <span style="font-weight:800; font-size:11px;">${log.member_name}</span>
                            <span style="font-size:10px; color:#94a3b8;">${log.created_at.split(' ')[1]}</span>
                        </div>
                        <div style="font-size:12px; font-weight:600;">${log.details}</div>
                    </div>
                `;
            }).join('');
            document.getElementById('security-alerts-count').innerText = alertsCount;
        }
    });
}

// Auto refresh every 10 seconds when tab is active
setInterval(() => {
    const tab = document.getElementById('active-sessions');
    if(tab && tab.style.display !== 'none') {
        smRefreshLiveSessions();
    }
}, 10000);

function smApplyTestFilters() {
    const search = document.getElementById('test_search_input').value.toLowerCase();
    const type = document.getElementById('test_type_filter').value;
    const branch = document.getElementById('test_branch_filter').value;

    document.querySelectorAll('.sm-test-row').forEach(row => {
        const matchesSearch = row.dataset.title.toLowerCase().includes(search);
        const matchesType = (type === 'all' || row.dataset.type === type);
        const matchesBranch = (branch === 'all' || row.dataset.branch === branch || (branch === 'all_branches' && row.dataset.branch === 'all'));

        if (matchesSearch && matchesType && matchesBranch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function smResetTestFilters() {
    document.getElementById('test_search_input').value = '';
    document.getElementById('test_type_filter').value = 'all';
    document.getElementById('test_branch_filter').value = 'all';
    smApplyTestFilters();
}

let currentViewingTestId = 0;
function smExportResultsToCSV() {
    if(!currentViewingTestId) return;
    const url = ajaxurl + '?action=sm_export_survey_results&id=' + currentViewingTestId + '&nonce=<?php echo wp_create_nonce("sm_admin_action"); ?>';
    window.location.href = url;
}

function smViewSurveyResults(id, title) {
    currentViewingTestId = id;
    document.getElementById('res-modal-title').innerText = 'نتائج: ' + title;
    const body = document.getElementById('survey-results-body');
    body.innerHTML = '<p style="text-align:center;">جاري تحميل النتائج...</p>';
    document.getElementById('survey-results-modal').style.display = 'flex';

    const action = 'sm_get_survey_results';
    fetch(ajaxurl + '?action=' + action + '&id=' + id)
    .then(r => r.json())
    .then(res => {
        if (res.success && res.data) {
            const d = res.data;
            let html = `
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:15px; margin-bottom: 30px;">
                    <div style="background:#fff; padding:15px; border-radius:10px; border:1px solid #e2e8f0; text-align:center;">
                        <div style="font-size:11px; color:#64748b;">إجمالي المشاركات</div>
                        <div style="font-size:24px; font-weight:900;">${d.stats.total_responses}</div>
                    </div>
                    <div style="background:#fff; padding:15px; border-radius:10px; border:1px solid #e2e8f0; text-align:center;">
                        <div style="font-size:11px; color:#64748b;">متوسط الدرجات</div>
                        <div style="font-size:24px; font-weight:900; color:var(--sm-primary-color);">${Math.round(d.stats.avg_score)}%</div>
                    </div>
                    <div style="background:#fff; padding:15px; border-radius:10px; border:1px solid #e2e8f0; text-align:center;">
                        <div style="font-size:11px; color:#64748b;">عدد الناجحين</div>
                        <div style="font-size:24px; font-weight:900; color:#38a169;">${d.stats.pass_count}</div>
                    </div>
                </div>
            `;

            html += `
                <div class="sm-tabs-wrapper" style="display:flex; gap:10px; margin-bottom:25px; border-bottom:2px solid #eee; padding-bottom:10px;">
                    <button class="sm-tab-btn sm-active" onclick="smToggleResultTab('res-stats', this)">الإحصائيات والأسئلة</button>
                    <button class="sm-tab-btn" onclick="smToggleResultTab('res-participants', this)">قائمة المشاركين (${d.participants.length})</button>
                </div>

                <div id="res-stats" class="res-tab-content">
            `;

            d.questions.forEach(item => {
                html += `<div style="margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <div style="font-weight: 800; margin-bottom: 15px; color: var(--sm-dark-color); font-size:14px;">${item.question}</div>
                    <div style="display: grid; gap: 8px;">`;

                for (const [ans, count] of Object.entries(item.answers)) {
                    const percent = Math.round((count / d.stats.total_responses) * 100);
                    html += `
                        <div style="background: white; border: 1px solid #edf2f7; border-radius: 8px; overflow:hidden;">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; position:relative; z-index:1;">
                                <span style="font-size:12px;">${ans}</span>
                                <span style="font-weight: 800; color: var(--sm-primary-color); font-size:12px;">${count} صوت (${percent}%)</span>
                            </div>
                            <div style="height:4px; background:rgba(246, 48, 73, 0.1); width:100%;">
                                <div style="height:100%; background:var(--sm-primary-color); width:${percent}%;"></div>
                            </div>
                        </div>`;
                }
                html += `</div></div>`;
            });
            html += `</div>`; // End stats

            html += `<div id="res-participants" class="res-tab-content" style="display:none;">
                <div class="sm-table-container" style="margin:0;">
                    <table class="sm-table sm-table-dense">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>رقم القيد</th>
                                <th>الدرجة</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${d.participants.map(p => `
                                <tr>
                                    <td><strong>${p.member_name}</strong><br><small style="color:#94a3b8;">${p.national_id}</small></td>
                                    <td>${p.membership_number}</td>
                                    <td><span style="font-weight:800; color:${p.score >= 50 ? '#38a169' : '#e53e3e'};">${Math.round(p.score)}%</span></td>
                                    <td><span class="sm-badge ${p.status === 'passed' ? 'sm-badge-high' : 'sm-badge-urgent'}">${p.status === 'passed' ? 'ناجح' : 'راسب'}</span></td>
                                    <td style="font-size:10px;">${p.created_at.split(' ')[0]}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>`;

            body.innerHTML = html;
        } else {
            smHandleAjaxError(res);
            body.innerHTML = '<p style="color:red;">فشل تحميل النتائج</p>';
        }
    }).catch(err => {
        smHandleAjaxError(err);
        body.innerHTML = '<p style="color:red;">حدث خطأ أثناء تحميل البيانات</p>';
    });
}
</script>
