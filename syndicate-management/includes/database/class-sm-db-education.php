<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_Education {
    public static function add_survey($data) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}sm_surveys", array(
            'title' => sanitize_text_field($data['title']),
            'questions' => json_encode($data['questions'] ?? []),
            'recipients' => sanitize_text_field($data['recipients'] ?? 'all'),
            'specialty' => sanitize_text_field($data['specialty'] ?? ''),
            'test_type' => sanitize_text_field($data['test_type'] ?? 'practice'),
            'time_limit' => intval($data['time_limit'] ?? 30),
            'max_attempts' => intval($data['max_attempts'] ?? 1),
            'pass_score' => intval($data['pass_score'] ?? 50),
            'branch' => sanitize_text_field($data['branch'] ?? 'all'),
            'start_time' => !empty($data['start_time']) ? sanitize_text_field($data['start_time']) : null,
            'end_time' => !empty($data['end_time']) ? sanitize_text_field($data['end_time']) : null,
            'show_results' => intval($data['show_results'] ?? 1),
            'random_order' => intval($data['random_order'] ?? 0),
            'randomize_answers' => intval($data['randomize_answers'] ?? 0),
            'lock_navigation' => intval($data['lock_navigation'] ?? 0),
            'auto_grade' => intval($data['auto_grade'] ?? 1),
            'status' => 'active',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ));
        return $wpdb->insert_id;
    }

    public static function update_survey($id, $data) {
        global $wpdb;
        $update = array(
            'title' => sanitize_text_field($data['title']),
            'recipients' => sanitize_text_field($data['recipients']),
            'specialty' => sanitize_text_field($data['specialty']),
            'test_type' => sanitize_text_field($data['test_type']),
            'time_limit' => intval($data['time_limit']),
            'max_attempts' => intval($data['max_attempts']),
            'pass_score' => intval($data['pass_score']),
            'branch' => sanitize_text_field($data['branch'] ?? 'all'),
            'status' => sanitize_text_field($data['status'] ?? 'active')
        );

        if(isset($data['start_time'])) $update['start_time'] = !empty($data['start_time']) ? sanitize_text_field($data['start_time']) : null;
        if(isset($data['end_time'])) $update['end_time'] = !empty($data['end_time']) ? sanitize_text_field($data['end_time']) : null;
        if(isset($data['show_results'])) $update['show_results'] = intval($data['show_results']);
        if(isset($data['random_order'])) $update['random_order'] = intval($data['random_order']);
        if(isset($data['randomize_answers'])) $update['randomize_answers'] = intval($data['randomize_answers']);
        if(isset($data['lock_navigation'])) $update['lock_navigation'] = intval($data['lock_navigation']);
        if(isset($data['auto_grade'])) $update['auto_grade'] = intval($data['auto_grade']);

        return $wpdb->update("{$wpdb->prefix}sm_surveys", $update, ['id' => intval($id)]);
    }

    public static function add_question($data) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_test_questions", array(
            'test_id' => intval($data['test_id']),
            'question_text' => sanitize_textarea_field($data['question_text']),
            'question_type' => sanitize_text_field($data['question_type'] ?? 'mcq'),
            'options' => is_array($data['options']) ? json_encode($data['options']) : $data['options'],
            'correct_answer' => sanitize_text_field($data['correct_answer']),
            'points' => intval($data['points'] ?? 1),
            'time_limit' => intval($data['time_limit'] ?? 0),
            'media_url' => esc_url_raw($data['media_url'] ?? ''),
            'media_type' => sanitize_text_field($data['media_type'] ?? ''),
            'extra_data' => !empty($data['extra_data']) ? (is_array($data['extra_data']) ? json_encode($data['extra_data']) : $data['extra_data']) : null,
            'topic' => sanitize_text_field($data['topic'] ?? ''),
            'difficulty' => sanitize_text_field($data['difficulty'] ?? 'medium'),
            'sort_order' => intval($data['sort_order'] ?? 0)
        ));
    }

    public static function get_test_questions($test_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_test_questions WHERE test_id = %d ORDER BY sort_order ASC", $test_id));
    }

    public static function delete_question($id) {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}sm_test_questions", ['id' => intval($id)]);
    }

    public static function get_surveys($user_id, $role, $specialty = '') {
        global $wpdb;
        $roles = [$role, 'all'];

        $placeholders = implode(',', array_fill(0, count($roles), '%s'));

        // Get surveys targeted by role/specialty AND branch OR specifically assigned to this user
        $my_gov = get_user_meta($user_id, 'sm_governorate', true);

        $query = "SELECT s.*, a.id as assignment_id, a.status as assignment_status FROM {$wpdb->prefix}sm_surveys s
                  LEFT JOIN {$wpdb->prefix}sm_test_assignments a ON s.id = a.test_id AND a.user_id = %d
                  WHERE s.status = 'active'
                  AND (
                      (s.recipients IN ($placeholders) AND (s.branch = 'all' OR s.branch = %s))
                      OR a.id IS NOT NULL
                  )";

        $params = array_merge([$user_id], $roles, [$my_gov ?: '']);

        if (!empty($specialty)) {
            $query .= " AND (s.specialty = %s OR s.specialty = '' OR a.id IS NOT NULL)";
            $params[] = $specialty;
        }

        $query .= " ORDER BY s.created_at DESC";
        return $wpdb->get_results($wpdb->prepare($query, ...$params));
    }

    public static function save_test_response($data) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_survey_responses", array(
            'survey_id' => intval($data['survey_id']),
            'user_id' => intval($data['user_id']),
            'responses' => json_encode($data['responses']),
            'score' => floatval($data['score'] ?? 0),
            'status' => sanitize_text_field($data['status'] ?? 'pending'),
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_survey($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_surveys WHERE id = %d", $id));
    }

    public static function get_survey_results($survey_id) {
        global $wpdb;
        $survey = self::get_survey($survey_id);
        if (!$survey) return array();

        $questions = self::get_test_questions($survey_id);
        $responses = self::get_survey_responses($survey_id);

        $results = [
            'stats' => [
                'total_responses' => count($responses),
                'avg_score' => count($responses) > 0 ? array_sum(array_column($responses, 'score')) / count($responses) : 0,
                'pass_count' => 0
            ],
            'questions' => []
        ];

        foreach ($responses as $r) {
            if ($r->score >= $survey->pass_score) $results['stats']['pass_count']++;
        }

        foreach ($questions as $q) {
            $q_res = ['question' => $q->question_text, 'type' => $q->question_type, 'answers' => []];
            foreach ($responses as $r) {
                $res_data = json_decode($r->responses, true);
                $ans = $res_data[$q->id] ?? 'No Answer';
                $q_res['answers'][$ans] = ($q_res['answers'][$ans] ?? 0) + 1;
            }
            $results['questions'][] = $q_res;
        }
        return $results;
    }

    public static function get_survey_responses($survey_id) {
        global $wpdb;
        $user = wp_get_current_user();
        $is_sys_admin = current_user_can('sm_manage_system');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where = $wpdb->prepare("r.survey_id = %d", $survey_id);
        if (!$is_sys_admin && $my_gov) {
            $where .= $wpdb->prepare(" AND (
                EXISTS (SELECT 1 FROM {$wpdb->prefix}usermeta um WHERE um.user_id = r.user_id AND um.meta_key = 'sm_governorate' AND um.meta_value = %s)
                OR EXISTS (SELECT 1 FROM {$wpdb->prefix}sm_members m WHERE m.wp_user_id = r.user_id AND m.governorate = %s)
            )", $my_gov, $my_gov);
        }

        return $wpdb->get_results("SELECT r.* FROM {$wpdb->prefix}sm_survey_responses r WHERE $where");
    }

    public static function assign_test($test_id, $user_id) {
        global $wpdb;
        $assigned_by = get_current_user_id();
        return $wpdb->insert("{$wpdb->prefix}sm_test_assignments", [
            'test_id' => intval($test_id),
            'user_id' => intval($user_id),
            'assigned_by' => intval($assigned_by),
            'status' => 'assigned',
            'created_at' => current_time('mysql')
        ]);
    }

    public static function get_test_assignments($test_id = null) {
        global $wpdb;
        $query = "SELECT a.*, u.display_name as user_name, u2.display_name as assigner_name
                  FROM {$wpdb->prefix}sm_test_assignments a
                  JOIN {$wpdb->prefix}users u ON a.user_id = u.ID
                  LEFT JOIN {$wpdb->prefix}users u2 ON a.assigned_by = u2.ID";
        if ($test_id) {
            return $wpdb->get_results($wpdb->prepare($query . " WHERE a.test_id = %d", $test_id));
        }
        return $wpdb->get_results($query);
    }

    public static function get_user_attempts_count($test_id, $user_id) {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sm_survey_responses WHERE survey_id = %d AND user_id = %d", $test_id, $user_id));
    }

    public static function get_user_best_score($test_id, $user_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT MAX(score) FROM {$wpdb->prefix}sm_survey_responses WHERE survey_id = %d AND user_id = %d", $test_id, $user_id));
    }

    public static function get_user_survey_response_id($survey_id, $user_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_survey_responses WHERE survey_id = %d AND user_id = %d", intval($survey_id), intval($user_id)));
    }

    public static function get_surveys_admin($args = []) {
        global $wpdb;
        $user = wp_get_current_user();
        $is_sys_admin = current_user_can('sm_manage_system');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where = "1=1";
        $params = [];

        if (!$is_sys_admin && $my_gov) {
            $where .= " AND (branch = %s OR branch = 'all')";
            $params[] = $my_gov;
        }

        $query = "SELECT * FROM {$wpdb->prefix}sm_surveys WHERE $where ORDER BY created_at DESC";
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, ...$params));
        }
        return $wpdb->get_results($query);
    }
}
