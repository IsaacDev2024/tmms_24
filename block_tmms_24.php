<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

// Fachada para la lógica de negocio del test TMMS-24
class TMMS24Facade {
    
    // Obtiene la interpretación de una puntuación según el baremo actualizado
    public static function get_interpretation($dimension, $score, $gender) {
        switch ($dimension) {
            case 'percepcion':
                if ($gender === 'M') {
                    if ($score <= 21) return get_string('perception_difficulty_feeling', 'block_tmms_24');
                    if ($score >= 22 && $score <= 32) return get_string('perception_adequate_feeling', 'block_tmms_24');
                    if ($score >= 33) return get_string('perception_excessive_attention', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 24) return get_string('perception_difficulty_feeling', 'block_tmms_24');
                    if ($score >= 25 && $score <= 35) return get_string('perception_adequate_feeling', 'block_tmms_24');
                    if ($score >= 36) return get_string('perception_excessive_attention', 'block_tmms_24');
                }
                break;
                
            case 'comprension':
                if ($gender === 'M') {
                    if ($score <= 25) return get_string('comprehension_difficulty_understanding', 'block_tmms_24');
                    if ($score >= 26 && $score <= 35) return get_string('comprehension_adequate_with_difficulties', 'block_tmms_24');
                    if ($score >= 36) return get_string('comprehension_great_clarity', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 23) return get_string('comprehension_difficulty_understanding', 'block_tmms_24');
                    if ($score >= 24 && $score <= 34) return get_string('comprehension_adequate_with_difficulties', 'block_tmms_24');
                    if ($score >= 35) return get_string('comprehension_great_clarity', 'block_tmms_24');
                }
                break;
                
            case 'regulacion':
                if ($gender === 'M') {
                    if ($score <= 23) return get_string('regulation_difficulty_managing', 'block_tmms_24');
                    if ($score >= 24 && $score <= 35) return get_string('regulation_adequate_balance', 'block_tmms_24');
                    if ($score >= 36) return get_string('regulation_great_capacity', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 23) return get_string('regulation_difficulty_managing', 'block_tmms_24');
                    if ($score >= 24 && $score <= 34) return get_string('regulation_adequate_balance', 'block_tmms_24');
                    if ($score >= 35) return get_string('regulation_great_capacity', 'block_tmms_24');
                }
                break;
        }
        
        return get_string('not_determined', 'block_tmms_24');
    }
    
    public static function calculate_scores($responses) {
        $percepcion = array_sum(array_slice($responses, 0, 8));
        $comprension = array_sum(array_slice($responses, 8, 8));
        $regulacion = array_sum(array_slice($responses, 16, 8));
        
        return [
            'percepcion' => $percepcion,
            'comprension' => $comprension,
            'regulacion' => $regulacion
        ];
    }
    
    public static function get_all_interpretations($scores, $gender) {
        return [
            'percepcion' => self::get_interpretation('percepcion', $scores['percepcion'], $gender),
            'comprension' => self::get_interpretation('comprension', $scores['comprension'], $gender),
            'regulacion' => self::get_interpretation('regulacion', $scores['regulacion'], $gender)
        ];
    }
    
    public static function get_gender_label($gender) {
        if (!isset($gender) || empty($gender)) {
            return get_string('not_determined', 'block_tmms_24');
        }
        
        switch ($gender) {
            case 'M':
                return get_string('gender_male', 'block_tmms_24');
            case 'F':
                return get_string('gender_female', 'block_tmms_24');
            default:
                return get_string('gender_prefer_not_say', 'block_tmms_24');
        }
    }
    
    public static function get_tmms24_items() {
        $items = [];
        for ($i = 1; $i <= 24; $i++) {
            $item_key = 'item' . $i;
            // Verificar que el item_key no esté vacío y que exista la cadena
            if (!empty($item_key) && get_string_manager()->string_exists($item_key, 'block_tmms_24')) {
                $items[$i] = get_string($item_key, 'block_tmms_24');
            } else {
                // Fallback en caso de que no exista la cadena
                $items[$i] = get_string('item_not_found', 'block_tmms_24', $i);
            }
        }
        return $items;
    }
}

class block_tmms_24 extends block_base {
    
    function init() {
        $this->title = get_string('pluginname', 'block_tmms_24');
    }
    
    function get_content() {
        global $USER, $DB, $COURSE;
        
        if ($this->content !== null) {
            return $this->content;
        }
        
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';
        
        if (!isloggedin()) {
            $this->content->text = get_string('not_logged_in', 'block_tmms_24');
            return $this->content;
        }
        
        $context = context_course::instance($COURSE->id);
        
        if (has_capability('block/tmms_24:viewallresults', $context)) {
            $this->content->text = $this->get_management_summary();
        } else {
            // Check if completed (is_completed = 1)
            $entry = $DB->get_record('tmms_24', ['user' => $USER->id]);
            
            if ($entry && $entry->is_completed == 1) {
                // Show enhanced results directly in the block
                $this->content->text = '<div id="tmms-results-container">' . 
                                      $this->get_student_results($entry) . 
                                      '</div>';
            } else {
                // Show enhanced test invitation (checks is_completed inside)
                $this->content->text = '<div id="tmms-invitation-container">' . 
                                      $this->get_test_invitation() . 
                                      '</div>';
            }
        }
        
        return $this->content;
    }
    
    function has_config() {
        return false;
    }
    
    private function get_student_results($entry) {
        global $COURSE, $USER;
        
        $output = '';
        
        // Build responses array from individual item fields in the database
        $responsesArray = [];
        for ($i = 1; $i <= 24; $i++) {
            $field_name = 'item' . $i;
            if (isset($entry->{$field_name}) && $entry->{$field_name} > 0) {
                $responsesArray[] = (int)$entry->{$field_name};
            }
        }
        
        // If we don't have all 24 responses, show error
        if (count($responsesArray) < 24) {
            return '<div class="alert alert-warning">' . get_string('incomplete_data', 'block_tmms_24') . '</div>';
        }
        
        $scores = TMMS24Facade::calculate_scores($responsesArray);
        
        if (!isset($entry->gender) || empty($entry->gender)) {
            $entry->gender = 'M'; // Default value
        }
        
        $interpretations = TMMS24Facade::get_all_interpretations($scores, $entry->gender);
        
        $output .= '<div class="tmms-results-block">';
        
        // Header with success icon
        $output .= '<div class="tmms-header text-center mb-3">';
        $output .= '<i class="fa fa-check-circle text-success" style="font-size: 1.5em;"></i>';
        $output .= '<h6 class="mt-2 mb-1">' . get_string('test_completed', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('emotional_intelligence_results', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
        // Test description
        $output .= '<div class="tmms-description mb-3" style="background: #f8f9fa; padding: 10px 12px; border-radius: 5px; border-left: 3px solid #28a745;">';
        $output .= '<small class="text-muted" style="line-height: 1.5;">';
        $output .= '<i class="fa fa-info-circle" style="color: #28a745;"></i> ';
        $output .= get_string('test_description_short', 'block_tmms_24');
        $output .= '</small>';
        $output .= '</div>';
        
        // Your emotional intelligence
        $output .= '<div class="tmms-top-section mb-3">';
        $output .= '<h6 class="mb-2">' . get_string('your_emotional_intelligence', 'block_tmms_24') . '</h6>';
        
        // Dimension names mapping
        $dimension_names = [
            'percepcion' => get_string('perception', 'block_tmms_24'),
            'comprension' => get_string('comprehension', 'block_tmms_24'),
            'regulacion' => get_string('regulation', 'block_tmms_24')
        ];
        
        // Find top dimension
        $max_dimension = array_search(max($scores), $scores);
        $max_score = $scores[$max_dimension];
        
        // Top dimension card
        $output .= '<div class="card border-primary mb-3" style="border-left: 4px solid #007bff !important;">';
        $output .= '<div class="card-body p-3">';
        $output .= '<div class="d-flex justify-content-between align-items-center mb-2">';
        $output .= '<div>';
        $output .= '<strong><i class="fa fa-star text-warning"></i> ' . $dimension_names[$max_dimension] . '</strong><br>';
        $output .= '<small class="text-muted">' . $max_score . '/40 ' . get_string('points', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        $output .= '</div>';
        // Add interpretation for top dimension
        $max_interpretation = isset($interpretations[$max_dimension]) ? $interpretations[$max_dimension] : get_string('not_determined', 'block_tmms_24');
        $output .= '<div class="small text-muted mt-2" style="font-style: italic; line-height: 1.3;">' . $max_interpretation . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Other dimensions summary
        $output .= '<div class="tmms-other-dimensions mb-3">';
        $output .= '<h6 class="mb-2">' . get_string('other_dimensions', 'block_tmms_24') . '</h6>';
        foreach ($scores as $dimension => $score) {
            if ($dimension !== $max_dimension) {
                $interpretation = isset($interpretations[$dimension]) ? $interpretations[$dimension] : get_string('not_determined', 'block_tmms_24');
                
                $output .= '<div class="card border-secondary mb-2">';
                $output .= '<div class="card-body p-2">';
                $output .= '<div class="d-flex justify-content-between align-items-center mb-1">';
                $output .= '<strong class="small">' . $dimension_names[$dimension] . '</strong>';
                $output .= '<span class="small text-muted">' . $score . '/40</span>';
                $output .= '</div>';
                $output .= '<div class="small text-muted" style="line-height: 1.2;">' . $interpretation . '</div>';
                $output .= '</div>';
                $output .= '</div>';
            }
        }
        $output .= '</div>';
        
        // View detailed results button
        $url = new moodle_url('/blocks/tmms_24/view.php', ['cid' => $COURSE->id, 'view_results' => 1]);
        $output .= '<div class="tmms-actions text-center mt-3">';
        $output .= '<a href="' . $url . '" class="btn btn-primary btn-sm btn-block">';
        $output .= '<i class="fa fa-chart-bar"></i> ' . get_string('view_detailed_results', 'block_tmms_24');
        $output .= '</a>';
        $output .= '</div>';

        $output .= '</div>';
        
        // Add custom CSS in the style of chaside
        $output .= '<style>
        .tmms-results-block {
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .tmms-header i {
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .tmms-results-block .card {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .tmms-results-block .card:hover {
            transform: translateY(-2px);
        }
        .tmms-other-dimensions {
            background: white;
            padding: 12px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .tmms-actions .btn {
            box-shadow: 0 2px 4px rgba(0,123,255,0.2);
            font-weight: 500;
        }
        .tmms-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }
        </style>';        return $output;
    }
    
    private function get_test_invitation() {
        global $COURSE, $USER, $DB;
        
        $output = '';
        
        // Check for response in tmms_24 table (user only takes test once)
        $response = $DB->get_record('tmms_24', array('user' => $USER->id));
        
        $output .= '<div class="tmms-invitation-block">';
        
        // Header with heart icon (emotional intelligence)
        $output .= '<div class="tmms-header text-center mb-3">';
        $output .= '<i class="fa fa-smile-o" style="font-size: 2em; color: #e91e63;"></i>';
        $output .= '<h6 class="mt-2 mb-1 font-weight-bold">' . get_string('emotional_intelligence_test', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('discover_your_emotional_skills', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
        // Initialize variables
        $answered_count = 0;
        $button_text = '';
        $button_icon = '';
        $button_class = '';
        $scroll_param = null;
        
        if ($response && !$response->is_completed) {
            // Test in progress - calculate progress
            for ($i = 1; $i <= 24; $i++) {
                $item_field = 'item' . $i;
                if (isset($response->$item_field) && $response->$item_field !== null) {
                    $answered_count++;
                }
            }
            
            // Only show progress if at least 1 question has been answered
            if ($answered_count > 0) {
                $progress_percentage = ($answered_count / 24) * 100;
                $all_answered = ($answered_count == 24);
                
                // Description section
                $output .= '<div class="tmms-description mb-3" style="background: white; padding: 10px 12px; border-radius: 5px; border-left: 3px solid #e91e63;">';
                $output .= '<small class="text-muted" style="line-height: 1.5;">';
                $output .= '<i class="fa fa-info-circle" style="color: #e91e63;"></i> ';
                $output .= get_string('test_description_short', 'block_tmms_24');
                $output .= '</small>';
                $output .= '</div>';
            
                if ($all_answered) {
                    // Show special message when all answered but not finished
                    $output .= '<div class="alert alert-warning mb-3" style="padding: 12px 15px; margin-bottom: 15px; border-left: 4px solid #ffc107; background-color: #fff3cd; border-radius: 4px;">';
                    $output .= '<div style="display: flex; align-items: start;">';
                    $output .= '<i class="fa fa-exclamation-triangle" style="color: #856404; margin-right: 10px; margin-top: 2px; font-size: 1.2em;"></i>';
                    $output .= '<div>';
                    $output .= '<strong style="color: #856404;">' . get_string('all_answered_title', 'block_tmms_24') . '</strong><br>';
                    $output .= '<small style="color: #856404;">' . get_string('all_answered_message', 'block_tmms_24') . '</small>';
                    $output .= '</div>';
                    $output .= '</div>';
                    $output .= '</div>';
                    
                    $button_text = get_string('finish_test', 'block_tmms_24');
                    $button_icon = 'fa-flag-checkered';
                    $button_class = 'btn-success';
                    $scroll_param = 'finish';
                } else {
                    $button_text = get_string('continue_test', 'block_tmms_24');
                    $button_icon = 'fa-play';
                    $button_class = 'btn-primary';
                    
                    // Find first unanswered
                    $scroll_param = null;
                    for ($i = 1; $i <= 24; $i++) {
                        $item_field = 'item' . $i;
                        if (!isset($response->$item_field) || $response->$item_field === null) {
                            $scroll_param = $i;
                            break;
                        }
                    }
                }
                
                // Show progress bar with TMMS colors
                $output .= '<div class="tmms-progress mb-3">';
                $output .= '<div class="d-flex justify-content-between align-items-center mb-2">';
                $output .= '<span class="small font-weight-bold">' . get_string('your_progress', 'block_tmms_24') . '</span>';
                $output .= '<span class="small text-muted">' . $answered_count . '/24</span>';
                $output .= '</div>';
                $output .= '<div class="progress mb-2" style="height: 8px; background-color: #fce4ec;">';
                $output .= '<div class="progress-bar" style="width: ' . $progress_percentage . '%; background: linear-gradient(90deg, #f48fb1 0%, #e91e63 100%);"></div>';
                $output .= '</div>';
                $output .= '<small class="text-muted">' . number_format($progress_percentage, 1) . '% ' . get_string('completed_status', 'block_tmms_24') . '</small>';
                $output .= '</div>';
            }
        }
        
        // Show test description for new test (when no response or no answers yet)
        if (!$response || ($response && !$response->is_completed && $answered_count == 0)) {
            // Show test description for new test
            $output .= '<div class="tmms-description mb-3">';
            $output .= '<div class="card border-info">';
            $output .= '<div class="card-body p-3">';
            $output .= '<h6 class="card-title font-weight-bold">';
            $output .= '<i class="fa fa-info-circle text-info"></i> ';
            $output .= get_string('what_is_tmms24', 'block_tmms_24');
            $output .= '</h6>';
            $output .= '<p class="card-text small mb-2">' . get_string('test_description_short', 'block_tmms_24') . '</p>';
            $output .= '<ul class="list-unstyled small mb-0">';
            $output .= '<li><i class="fa fa-check text-success"></i> ' . get_string('feature_24_questions', 'block_tmms_24') . '</li>';
            $output .= '<li><i class="fa fa-check text-success"></i> ' . get_string('feature_3_dimensions', 'block_tmms_24') . '</li>';
            $output .= '<li><i class="fa fa-check text-success"></i> ' . get_string('feature_instant_results', 'block_tmms_24') . '</li>';
            $output .= '</ul>';
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</div>';
            
            $button_text = get_string('start_test', 'block_tmms_24');
            $button_icon = 'fa-rocket';
            $button_class = 'btn-primary';
            $scroll_param = null;
        }
        
        // Call to action
        $output .= '<div class="tmms-actions text-center">';
        
        $url_params = array('cid' => $COURSE->id);
        if ($scroll_param !== null) {
            $url_params['scroll'] = $scroll_param;
        }
        $url = new moodle_url('/blocks/tmms_24/view.php', $url_params);
        
        // Button styling based on state
        $button_style = '';
        if ($button_class == 'btn-primary') {
            $button_style = 'background: linear-gradient(135deg, #e91e63 0%, #d81b60 100%); border-color: #e91e63;';
        } else if ($button_class == 'btn-success') {
            $button_style = 'background: linear-gradient(135deg, #28a745 0%, #218838 100%); border-color: #28a745;';
        }
        
        $output .= '<a href="' . $url . '" class="btn ' . $button_class . ' btn-block" style="' . $button_style . '">';
        $output .= '<i class="fa ' . $button_icon . '"></i> ' . $button_text;
        $output .= '</a>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        // Add custom CSS for invitation
        $output .= '<style>
        .block_tmms_24 .tmms-invitation-block {
            padding: 15px !important;
            background: linear-gradient(135deg, #fce4ec 0%, #f8f9fa 100%) !important;
            border-radius: 8px !important;
            border: 1px solid #f8bbd0 !important;
        }
        .block_tmms_24 .tmms-header i {
            text-shadow: 0 1px 2px rgba(0,0,0,0.1) !important;
        }
        .block_tmms_24 .tmms-progress {
            background: white !important;
            padding: 12px !important;
            border-radius: 5px !important;
            border: 1px solid #e9ecef !important;
        }
        .block_tmms_24 .tmms-description .card {
            box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
        }
        /* Eliminar solo el pin del título interior (h6.card-title) */
        .block_tmms_24 .tmms-description .card-title::before,
        .block_tmms_24 .tmms-description .card-title::after {
            content: none !important;
            display: none !important;
        }
        .block_tmms_24 .tmms-description .card-title {
            padding-left: 0 !important;
            margin-left: 0 !important;
            background: transparent !important;
            background-color: transparent !important;
            border-bottom: none !important;
            font-weight: bold !important;
        }
        .block_tmms_24 .tmms-actions .btn {
            box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
        }
        .block_tmms_24 .tmms-actions .btn-primary {
            background-color: #e91e63 !important;
            border-color: #e91e63 !important;
        }
        .block_tmms_24 .tmms-actions .btn-primary:hover {
            background-color: #d81b60 !important;
            border-color: #c2185b !important;
        }
        .block_tmms_24 .tmms-actions .btn:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
        }
        </style>';
        
        return $output;
    }
    
    private function get_management_summary() {
        global $DB, $COURSE;
        
        $output = '';
        $output .= '<div class="tmms-management-block">';
        
        // Header with icon
        $output .= '<div class="tmms-header text-center mb-3">';
        $output .= '<i class="fa fa-chart-line text-success" style="font-size: 1.5em;"></i>';
        $output .= '<h6 class="mt-2 mb-1 font-weight-bold">' . get_string('management_title', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('course_overview', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
        // Get course statistics
        $context = context_course::instance($COURSE->id);
        $enrolled_students = get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);
        
        // Filtrar solo estudiantes (rol 5)
        $student_ids = array();
        foreach ($enrolled_students as $user) {
            $roles = get_user_roles($context, $user->id);
            foreach ($roles as $role) {
                if ($role->roleid == 5) { // 5 = student
                    $student_ids[] = $user->id;
                    break;
                }
            }
        }
        
        $total_enrolled = count($student_ids);
        
        // Obtener respuestas solo de estudiantes inscritos
        $total_completed = 0;
        $total_in_progress = 0;
        if (!empty($student_ids)) {
            list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED, 'user');
            $all_responses = $DB->get_records_select('tmms_24', "user $insql", $params);
            
            // Separar completados de en progreso
            foreach ($all_responses as $response) {
                if ($response->is_completed == 1) {
                    $total_completed++;
                } else {
                    // Solo contar como en progreso si tiene al menos 1 respuesta
                    $has_answers = false;
                    for ($i = 1; $i <= 24; $i++) {
                        $item = 'item' . $i;
                        if (isset($response->$item) && $response->$item !== null) {
                            $has_answers = true;
                            break;
                        }
                    }
                    if ($has_answers) {
                        $total_in_progress++;
                    }
                }
            }
        }
        
        $completion_rate = $total_enrolled > 0 ? ($total_completed / $total_enrolled) * 100 : 0;
        
        // Quick stats with chaside style
        $output .= '<div class="tmms-stats mb-3">';
        $output .= '<div class="row text-center">';
        
        // Completion rate
        $output .= '<div class="col-4">';
        $output .= '<div class="stat-card">';
        $output .= '<div class="stat-number text-success">' . number_format($completion_rate, 1) . '%</div>';
        $output .= '<div class="stat-label">' . get_string('completion_rate', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Completed tests
        $output .= '<div class="col-4">';
        $output .= '<div class="stat-card">';
        $output .= '<div class="stat-number text-primary">' . $total_completed . '</div>';
        $output .= '<div class="stat-label">' . get_string('completed', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // In Progress
        $output .= '<div class="col-4">';
        $output .= '<div class="stat-card">';
        $output .= '<div class="stat-number text-warning">' . $total_in_progress . '</div>';
        $output .= '<div class="stat-label">' . get_string('in_progress', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '</div>';
        $output .= '</div>';
        
        // Progress bar overview
        $output .= '<div class="tmms-progress-overview mb-3">';
        $output .= '<div class="progress" style="height: 10px;">';
        $output .= '<div class="progress-bar bg-success" style="width: ' . ($completion_rate) . '%"></div>';
        $output .= '</div>';
        $output .= '<small class="text-muted">' . $total_completed . ' ' . get_string('of', 'block_tmms_24') . ' ' . $total_enrolled . ' ' . get_string('students_completed', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
        // Recent completions (only completed tests)
        $recent_completions = array();
        if (!empty($student_ids)) {
            list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED, 'user');
            $sql = "SELECT u.firstname, u.lastname, tr.created_at 
                    FROM {tmms_24} tr 
                    JOIN {user} u ON tr.user = u.id 
                    WHERE tr.user $insql AND tr.is_completed = 1
                    ORDER BY tr.created_at DESC";
            $recent_completions = $DB->get_records_sql($sql, $params, 0, 3);
        }
        
        if ($recent_completions) {
            $output .= '<div class="recent-completions mt-3">';
            $output .= '<h6 class="mb-2 font-weight-bold">' . get_string('recent_completions', 'block_tmms_24') . '</h6>';
            foreach ($recent_completions as $completion) {
                $completion_date = $completion->created_at ? $completion->created_at : time();
                $output .= '<div class="d-flex justify-content-between align-items-center mb-1">';
                $output .= '<span class="small">' . $completion->firstname . ' ' . $completion->lastname . '</span>';
                $output .= '<span class="badge badge-success small">' . userdate($completion_date, get_string('strftimedatefullshort')) . '</span>';
                $output .= '</div>';
            }
            $output .= '</div>';
        }
        
        // Management actions
        $url = new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $COURSE->id]);
        $output .= '<div class="tmms-actions text-center mt-3">';
        $output .= '<a href="' . $url . '" class="btn btn-primary btn-sm btn-block">';
        $output .= '<i class="fa fa-chart-bar"></i> ' . get_string('view_all_results', 'block_tmms_24');
        $output .= '</a>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        // Add custom CSS for management view (with !important for Cognitio theme compatibility)
        $output .= '<style>
        .block_tmms_24 .tmms-management-block {
            padding: 15px !important;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
            border-radius: 8px !important;
            border: 1px solid #dee2e6 !important;
        }
        .block_tmms_24 .stat-card {
            padding: 8px !important;
            background: white !important;
            border-radius: 5px !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
        }
        .block_tmms_24 .stat-number {
            font-size: 1.2em !important;
            font-weight: bold !important;
        }
        .block_tmms_24 .stat-label {
            font-size: 0.75em !important;
            color: #6c757d !important;
        }
        .block_tmms_24 .tmms-progress-overview {
            background: white !important;
            padding: 10px !important;
            border-radius: 5px !important;
            border: 1px solid #e9ecef !important;
        }
        .block_tmms_24 .recent-completions {
            background: white !important;
            padding: 10px !important;
            border-radius: 5px !important;
            border: 1px solid #e9ecef !important;
        }
        .block_tmms_24 .tmms-management-block .tmms-actions .btn {
            box-shadow: 0 2px 4px rgba(0,123,255,0.2) !important;
        }
        </style>';
        
        return $output;
    }
    
    private function get_score_badge_class($interpretation) {
        // Asignar clases CSS según el tipo de interpretación
        if (strpos($interpretation, get_string('regulation_great_capacity', 'block_tmms_24')) !== false ||
            strpos($interpretation, get_string('comprehension_great_clarity', 'block_tmms_24')) !== false) {
            return 'badge-success'; // Verde para resultados excelentes
        } elseif (strpos($interpretation, get_string('perception_adequate_feeling', 'block_tmms_24')) !== false ||
                  strpos($interpretation, get_string('comprehension_adequate_with_difficulties', 'block_tmms_24')) !== false ||
                  strpos($interpretation, get_string('regulation_adequate_balance', 'block_tmms_24')) !== false) {
            return 'badge-primary'; // Azul para resultados adecuados
        } elseif (strpos($interpretation, get_string('perception_excessive_attention', 'block_tmms_24')) !== false) {
            return 'badge-warning'; // Amarillo para atención excesiva
        } else {
            return 'badge-danger'; // Rojo para dificultades
        }
    }
}
