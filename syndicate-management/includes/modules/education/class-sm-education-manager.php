<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Education_Manager {
    private static function check_capability($cap) {
        if (!current_user_can($cap)) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }
    }

    public static function ajax_add_survey() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $id = SM_DB::add_survey($_POST);
            if ($id) {
                wp_send_json_success($id);
            } else {
                wp_send_json_error(['message' => 'Failed to create test']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error adding survey: ' . $e->getMessage()]);
        }
    }

    public static function ajax_update_survey() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $id = intval($_POST['id']);
            if (SM_DB::update_survey_data($id, $_POST)) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed to update test']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error updating survey: ' . $e->getMessage()]);
        }
    }

    public static function ajax_add_test_question() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $id = SM_DB::add_test_question($_POST);
            if ($id) {
                wp_send_json_success($id);
            } else {
                wp_send_json_error(['message' => 'Failed to add question']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error adding question: ' . $e->getMessage()]);
        }
    }

    public static function ajax_delete_test_question() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $id = intval($_POST['id']);
            if (SM_DB::delete_test_question($id)) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed to delete question']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error deleting question: ' . $e->getMessage()]);
        }
    }

    public static function ajax_save_test_group() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');
            $id = intval($_POST['id'] ?? 0);
            $data = [
                'name' => sanitize_text_field($_POST['name']),
                'branch' => sanitize_text_field($_POST['branch']),
                'description' => sanitize_textarea_field($_POST['description']),
                'created_by' => get_current_user_id()
            ];
            global $wpdb;
            if($id) {
                $wpdb->update("{$wpdb->prefix}sm_test_groups", $data, ['id' => $id]);
            } else {
                $wpdb->insert("{$wpdb->prefix}sm_test_groups", $data);
            }
            wp_send_json_success();
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_get_group_members() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');
            $gid = intval($_REQUEST['group_id'] ?? 0);
            if (!$gid) wp_send_json_error(['message' => 'Group ID required']);
            global $wpdb;
            $members = $wpdb->get_results($wpdb->prepare("
                SELECT u.ID, u.display_name, u.user_login
                FROM {$wpdb->prefix}users u
                JOIN {$wpdb->prefix}sm_test_group_members gm ON u.ID = gm.user_id
                WHERE gm.group_id = %d
            ", $gid));
            wp_send_json_success($members);
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_add_group_members() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');
            $gid = intval($_POST['group_id']);
            $uids = array_map('intval', (array)$_POST['user_ids']);
            global $wpdb;
            foreach($uids as $uid) {
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_test_group_members WHERE group_id = %d AND user_id = %d", $gid, $uid));
                if(!$exists) {
                    $wpdb->insert("{$wpdb->prefix}sm_test_group_members", ['group_id' => $gid, 'user_id' => $uid]);
                }
            }
            wp_send_json_success();
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_remove_group_member() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');
            $gid = intval($_POST['group_id']);
            $uid = intval($_POST['user_id']);
            global $wpdb;
            $wpdb->delete("{$wpdb->prefix}sm_test_group_members", ['group_id' => $gid, 'user_id' => $uid]);
            wp_send_json_success();
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_assign_test() {
        try {
            self::check_capability('sm_manage_system');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }

            $sid = intval($_POST['survey_id']);
            $uids = array_map('intval', (array)$_POST['user_ids'] ?? []);
            $gids = array_map('intval', (array)$_POST['group_ids'] ?? []);

            if (empty($uids) && empty($gids)) {
                wp_send_json_error(['message' => 'يرجى اختيار مستخدم أو مجموعة واحدة على الأقل']);
            }

            global $wpdb;
            foreach ($uids as $uid) {
                SM_DB::assign_test($sid, $uid);
            }
            foreach ($gids as $gid) {
                $wpdb->insert("{$wpdb->prefix}sm_test_group_assignments", ['test_id' => $sid, 'group_id' => $gid, 'assigned_by' => get_current_user_id()]);
                $members = $wpdb->get_col($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}sm_test_group_members WHERE group_id = %d", $gid));
                foreach($members as $mid) {
                    SM_DB::assign_test($sid, $mid);
                }
            }
            wp_send_json_success();
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_start_test_session() {
        try {
            if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
            check_ajax_referer('sm_test_nonce', 'nonce');

            $aid = intval($_POST['assignment_id'] ?? 0);
            if (!$aid) wp_send_json_error(['message' => 'Assignment ID required']);
            global $wpdb;
            $assign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_test_assignments WHERE id = %d AND user_id = %d", $aid, get_current_user_id()));
            if (!$assign) wp_send_json_error(['message' => 'Invalid assignment']);

            $wpdb->update("{$wpdb->prefix}sm_test_assignments", [
                'started_at' => current_time('mysql'),
                'last_heartbeat' => current_time('mysql'),
                'status' => 'active'
            ], ['id' => $aid]);

            self::log_test_action($aid, 'start', 'بدء الاختبار المهني');
            wp_send_json_success();
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_log_test_action() {
        try {
            if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
            check_ajax_referer('sm_test_nonce', 'nonce');
            $aid = intval($_POST['assignment_id'] ?? 0);
            if (!$aid) wp_send_json_error(['message' => 'Assignment ID required']);
            $type = sanitize_text_field($_POST['type']);
            $details = sanitize_textarea_field($_POST['details']);
            self::log_test_action($aid, $type, $details);
            wp_send_json_success();
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    private static function log_test_action($aid, $type, $details) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}sm_test_logs", [
            'assignment_id' => $aid,
            'user_id' => get_current_user_id(),
            'action_type' => $type,
            'details' => $details,
            'created_at' => current_time('mysql')
        ]);
    }

    public static function ajax_sync_test_progress() {
        try {
            if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
            check_ajax_referer('sm_test_nonce', 'nonce');

            $aid = intval($_POST['assignment_id'] ?? 0);
            if (!$aid) wp_send_json_error(['message' => 'Assignment ID required']);
            $data = $_POST['progress']; // JSON string
            global $wpdb;

            $wpdb->update("{$wpdb->prefix}sm_test_assignments", [
                'session_data' => $data,
                'last_heartbeat' => current_time('mysql')
            ], ['id' => $aid, 'user_id' => get_current_user_id()]);

            // Check if terminated by admin
            $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}sm_test_assignments WHERE id = %d", $aid));
            wp_send_json_success(['status' => $status]);
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_terminate_test_admin() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');
            $aid = intval($_POST['assignment_id'] ?? 0);
            if (!$aid) wp_send_json_error(['message' => 'Assignment ID required']);
            global $wpdb;
            $wpdb->update("{$wpdb->prefix}sm_test_assignments", ['status' => 'terminated'], ['id' => $aid]);
            wp_send_json_success();
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_submit_survey_response() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }

            // Unified nonce check for testing system
            if (isset($_REQUEST['nonce'])) {
                check_ajax_referer('sm_test_nonce', 'nonce');
            } else {
                check_ajax_referer('sm_test_nonce', '_wpnonce');
            }

        $sid = intval($_POST['survey_id']);
        $aid = intval($_POST['assignment_id'] ?? 0);
        $user_id = get_current_user_id();
        $responses = json_decode(stripslashes($_POST['responses'] ?? '[]'), true);
        $questions = SM_DB::get_test_questions($sid);
        $survey = SM_DB::get_survey($sid);

        if (!$survey) {
            wp_send_json_error(['message' => 'Test not found']);
        }

        // Security: Check attempt limits (skip if it's an auto-submit from active session)
        if (!$aid) {
            $attempts_made = SM_DB::get_user_attempts_count($sid, $user_id);
            if ($attempts_made >= $survey->max_attempts) {
                wp_send_json_error(['message' => 'لقد استنفدت كافة المحاولات المتاحة لهذا الاختبار.']);
            }
        }

        $score = 0;
        $total_points = 0;

        if (!empty($questions)) {
            foreach ($questions as $q) {
                $total_points += $q->points;
                $user_ans = $responses[$q->id] ?? '';

                $is_correct = false;

                switch ($q->question_type) {
                    case 'mcq':
                    case 'true_false':
                    case 'short_answer':
                        if (trim((string)$user_ans) === trim((string)$q->correct_answer)) {
                            $is_correct = true;
                        }
                        break;

                    case 'essay':
                        // Basic keyword matching if auto-grade is on
                        if ($survey->auto_grade && !empty($q->correct_answer)) {
                            $keywords = explode(',', $q->correct_answer);
                            $matches = 0;
                            foreach ($keywords as $kw) {
                                if (mb_stripos((string)$user_ans, trim($kw)) !== false) $matches++;
                            }
                            // If user mentions at least 50% of keywords, give points (simplistic)
                            if ($matches >= (count($keywords) / 2)) $is_correct = true;
                        }
                        break;

                    case 'ordering':
                        // User ans should be array of strings, correct_answer is JSON array
                        $correct = json_decode($q->correct_answer, true);
                        if (is_array($user_ans) && is_array($correct)) {
                            if (json_encode($user_ans) === json_encode($correct)) $is_correct = true;
                        }
                        break;

                    case 'matching':
                        // User ans should be object/assoc array {key: val}, correct_answer is JSON array of {key, val}
                        $correct_raw = json_decode($q->correct_answer, true);
                        if (is_array($user_ans) && is_array($correct_raw)) {
                            $matched_all = true;
                            foreach($correct_raw as $pair) {
                                if (($user_ans[$pair['key']] ?? '') !== $pair['val']) {
                                    $matched_all = false;
                                    break;
                                }
                            }
                            if ($matched_all) $is_correct = true;
                        }
                        break;
                }

                if ($is_correct) {
                    $score += $q->points;
                }
            }
        }

        $percent = $total_points > 0 ? ($score / $total_points) * 100 : 0;
        $passed = ($percent >= $survey->pass_score);

        SM_DB::save_test_response([
            'survey_id' => $sid,
            'user_id' => get_current_user_id(),
            'responses' => $responses,
            'score' => $percent,
            'status' => $passed ? 'passed' : 'failed'
        ]);

        if ($aid) {
            global $wpdb;
            $wpdb->update("{$wpdb->prefix}sm_test_assignments", ['status' => 'completed'], ['id' => $aid]);
            self::log_test_action($aid, 'submit', 'تم تسليم الاختبار بنجاح');
        }

        // Notify member of result
        $user = wp_get_current_user();
        $msg = "لقد أكملت اختبار: {$survey->title}\nالنتيجة: " . round($percent) . "%\nالحالة: " . ($passed ? 'ناجح ✅' : 'لم تجتز ❌');

        SM_DB::send_message(
            0, // System
            $user->ID,
            $msg,
            null,
            null,
            get_user_meta($user->ID, 'sm_governorate', true)
        );

            wp_send_json_success([
                'score' => $percent,
                'passed' => $passed
            ]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Critical Error submitting test results: ' . $e->getMessage()]);
        }
    }

    public static function ajax_cancel_survey() {
        try {
            self::check_capability('manage_options');
            if (isset($_POST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } else {
                check_ajax_referer('sm_admin_action', 'sm_admin_nonce');
            }
            if (SM_DB::update_survey_data(intval($_POST['id']), ['status' => 'cancelled'])) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed']);
            }
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_get_survey_results() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');

            $survey_id = intval($_GET['id'] ?? 0);
            if (!$survey_id) wp_send_json_error(['message' => 'Survey ID required']);
            $results = SM_DB::get_survey_results($survey_id);

            // Add detailed participant list
            global $wpdb;
            $results['participants'] = $wpdb->get_results($wpdb->prepare("
                SELECT r.*, m.name as member_name, m.national_id, m.membership_number
                FROM {$wpdb->prefix}sm_survey_responses r
                JOIN {$wpdb->prefix}sm_members m ON r.user_id = m.wp_user_id
                WHERE r.survey_id = %d
                ORDER BY r.score DESC
            ", $survey_id));

            wp_send_json_success($results);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_export_survey_results() {
        if (!current_user_can('manage_options') && !current_user_can('sm_manage_system')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('sm_admin_action', 'nonce');

        $id = intval($_GET['id']);
        $survey = SM_DB::get_survey($id);

        global $wpdb;
        $participants = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, m.name as member_name, m.national_id, m.membership_number, m.governorate
            FROM {$wpdb->prefix}sm_survey_responses r
            JOIN {$wpdb->prefix}sm_members m ON r.user_id = m.wp_user_id
            WHERE r.survey_id = %d
            ORDER BY r.score DESC
        ", $id));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="test-results-'.$id.'.csv"');

        // Add UTF-8 BOM for Excel Arabic support
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, ['الاسم', 'الرقم القومي', 'رقم القيد', 'الفرع', 'الدرجة %', 'الحالة', 'تاريخ المشاركة']);

        foreach ($participants as $p) {
            fputcsv($out, [
                $p->member_name,
                $p->national_id,
                $p->membership_number,
                SM_Settings::get_branch_name($p->governorate),
                round($p->score, 2),
                ($p->status === 'passed' ? 'ناجح' : 'راسب'),
                $p->created_at
            ]);
        }

        fclose($out);
        exit;
    }

    public static function ajax_get_live_security_logs_ajax() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');
            global $wpdb;
            $logs = $wpdb->get_results("
                SELECT l.*, m.name as member_name
                FROM {$wpdb->prefix}sm_test_logs l
                JOIN {$wpdb->prefix}sm_members m ON l.user_id = m.wp_user_id
                ORDER BY l.created_at DESC LIMIT 20
            ");
            wp_send_json_success($logs);
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_get_live_sessions_ajax() {
        try {
            self::check_capability('sm_manage_system');
            check_ajax_referer('sm_admin_action', 'nonce');
            global $wpdb;
            $sessions = $wpdb->get_results("
                SELECT a.*, s.title, m.name as member_name
                FROM {$wpdb->prefix}sm_test_assignments a
                JOIN {$wpdb->prefix}sm_surveys s ON a.test_id = s.id
                JOIN {$wpdb->prefix}sm_members m ON a.user_id = m.wp_user_id
                WHERE a.status = 'active'
                ORDER BY a.last_heartbeat DESC
            ");
            wp_send_json_success($sessions);
        } catch (Throwable $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public static function ajax_get_test_questions() {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            // Nonce check based on context (public test session vs admin view)
            if (isset($_REQUEST['nonce'])) {
                check_ajax_referer('sm_admin_action', 'nonce');
            } elseif (isset($_REQUEST['sm_test_nonce'])) {
                check_ajax_referer('sm_test_nonce', 'sm_test_nonce');
            }

            $test_id = intval($_GET['test_id'] ?? 0);
            if (!$test_id) wp_send_json_error(['message' => 'Test ID required']);
        // Capability check: admins or the user assigned to the test
        $can_view = current_user_can('sm_manage_system');
        if (!$can_view) {
            $assignments = SM_DB::get_test_assignments($test_id);
            foreach ($assignments as $a) {
                if ($a->user_id == get_current_user_id()) {
                    $can_view = true;
                    break;
                }
            }
        }

        if (!$can_view) {
            wp_send_json_error(['message' => 'Access denied']);
        }

            wp_send_json_success(SM_DB::get_test_questions($test_id));
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
