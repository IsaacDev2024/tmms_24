<?php
/**
 * TMMS-24 Block
 *
 * @package    block_tmms_24
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

// Fachada para la lógica de negocio del test TMMS-24
class TMMS24Facade {
    
    // Helper to resolve the base string key for interpretation
    private static function resolve_interpretation_key($dimension, $score, $gender) {
        // Standardize gender check: M or masculino are considered Male, everything else falls to Female/Default logic
        $is_male = ($gender === 'M' || strtolower($gender) === 'masculino');

        switch ($dimension) {
            case 'percepcion':
                if ($is_male) {
                    if ($score <= 21) return 'perception_difficulty_feeling';
                    if ($score >= 22 && $score <= 32) return 'perception_adequate_feeling';
                    if ($score >= 33) return 'perception_excessive_attention';
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 24) return 'perception_difficulty_feeling';
                    if ($score >= 25 && $score <= 35) return 'perception_adequate_feeling';
                    if ($score >= 36) return 'perception_excessive_attention';
                }
                break;
                
            case 'comprension':
                if ($is_male) {
                    if ($score <= 25) return 'comprehension_difficulty_understanding';
                    if ($score >= 26 && $score <= 35) return 'comprehension_adequate_with_difficulties';
                    if ($score >= 36) return 'comprehension_great_clarity';
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 23) return 'comprehension_difficulty_understanding';
                    if ($score >= 24 && $score <= 34) return 'comprehension_adequate_with_difficulties';
                    if ($score >= 35) return 'comprehension_great_clarity';
                }
                break;
                
            case 'regulacion':
                if ($is_male) {
                    if ($score <= 23) return 'regulation_difficulty_managing';
                    if ($score >= 24 && $score <= 35) return 'regulation_adequate_balance';
                    if ($score >= 36) return 'regulation_great_capacity';
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 23) return 'regulation_difficulty_managing';
                    if ($score >= 24 && $score <= 34) return 'regulation_adequate_balance';
                    if ($score >= 35) return 'regulation_great_capacity';
                }
                break;
        }
        return null;
    }

    // Obtiene la interpretación de una puntuación según el baremo actualizado
    public static function get_interpretation($dimension, $score, $gender, $long = false, $teacher_mode = false) {
        $key = self::resolve_interpretation_key($dimension, $score, $gender);
        if (!$key) {
            return get_string('not_determined', 'block_tmms_24');
        }
        
        if ($long && $teacher_mode) {
             $teacher_key = $key . '_long_teacher';
             // Optimization: Try to use the teacher string directly.
             // If string_exists check was failing due to cache or context, this will expose the localized string or [[...]]
             return get_string($teacher_key, 'block_tmms_24'); 
        }
        
        return get_string($long ? $key . '_long' : $key, 'block_tmms_24');
    }
    
    public static function calculate_scores($responses) {
        if (!is_array($responses)) {
            return [
                'percepcion' => 0,
                'comprension' => 0,
                'regulacion' => 0
            ];
        }

        $ordered = [];

        // Prefer associative arrays keyed by item1..item24.
        $foundItemKeys = false;
        for ($i = 1; $i <= 24; $i++) {
            $key = 'item' . $i;
            if (array_key_exists($key, $responses)) {
                $ordered[] = (int)$responses[$key];
                $foundItemKeys = true;
            }
        }

        // Fallback: numeric keys 1..24.
        if (!$foundItemKeys) {
            $foundNumericKeys = true;
            for ($i = 1; $i <= 24; $i++) {
                if (!array_key_exists($i, $responses)) {
                    $foundNumericKeys = false;
                    break;
                }
            }

            if ($foundNumericKeys) {
                for ($i = 1; $i <= 24; $i++) {
                    $ordered[] = (int)$responses[$i];
                }
            } else {
                // Final fallback: trust the given order.
                $ordered = array_values($responses);
            }
        }

        // Ensure exactly 24 values.
        $ordered = array_slice($ordered, 0, 24);
        if (count($ordered) < 24) {
            $ordered = array_pad($ordered, 24, 0);
        }

        $percepcion = array_sum(array_slice($ordered, 0, 8));
        $comprension = array_sum(array_slice($ordered, 8, 8));
        $regulacion = array_sum(array_slice($ordered, 16, 8));

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

    public static function get_all_interpretations_long($scores, $gender, $teacher_mode = false) {
        return [
            'percepcion' => self::get_interpretation('percepcion', $scores['percepcion'], $gender, true, $teacher_mode),
            'comprension' => self::get_interpretation('comprension', $scores['comprension'], $gender, true, $teacher_mode),
            'regulacion' => self::get_interpretation('regulacion', $scores['regulacion'], $gender, true, $teacher_mode)
        ];
    }

    public static function prepare_results_data($entry, $courseid, $is_teacher_view = false, $back_url = '') {
        $responses = [];
        for ($i = 1; $i <= 24; $i++) { $item = 'item' . $i; $responses[] = $entry->$item; }
        $scores = self::calculate_scores($responses);
        
        $interpretations = self::get_all_interpretations($scores, $entry->gender);
        // Use teacher mode for long interpretations if requested
        $interpretations_long = self::get_all_interpretations_long($scores, $entry->gender, $is_teacher_view);
        
        // Build Dimensions Array
        $dim_configs = [
            'percepcion' => ['title' => get_string('perception', 'block_tmms_24'), 'color' => '#ff6600', 'bg' => '#ffcc99', 'icon' => 'fa-eye'],
            'comprension' => ['title' => get_string('comprehension', 'block_tmms_24'), 'color' => '#ff8533', 'bg' => '#ffd699', 'icon' => 'fa-lightbulb-o'],
            'regulacion' => ['title' => get_string('regulation', 'block_tmms_24'), 'color' => '#ffaa66', 'bg' => '#ffe0b3', 'icon' => 'fa-sliders']
        ];
        
        $dimensions = [];
        foreach (['percepcion', 'comprension', 'regulacion'] as $key) {
            $score = $scores[$key];
            $conf = $dim_configs[$key];
            $dimensions[] = [
                'key' => $key,
                'title' => $conf['title'],
                'score' => $score,
                'color' => $conf['color'],
                'bg_color' => $conf['bg'],
                'icon' => $conf['icon'],
                'interpretation_short' => $interpretations[$key],
                'interpretation_long' => $interpretations_long[$key],
                'progress_width' => ($score / 40) * 100,
                'goal_text' => self::get_goal_text($key, $entry->gender)
            ];
        }

        $user = \core_user::get_user($entry->user);
        
        $gender_display = self::get_gender_label($entry->gender);
        $gender_icon = 'fa-genderless';
        $gender_color_style = 'color: #6c757d;'; // Default neutral (grey)
        
        $gender = isset($entry->gender) ? $entry->gender : '';
        
        // Handle both code ('M', 'F') and full text ('masculino', 'femenino') cases as requested
        if ($gender === 'M' || strtolower($gender) === 'masculino') {
            $gender_icon = 'fa-mars';
            $gender_color_style = 'color: #007bff;'; // Bootstrap Primary Blue
        } elseif ($gender === 'F' || strtolower($gender) === 'femenino') {
            $gender_icon = 'fa-venus';
            $gender_color_style = 'color: #e83e8c;'; // Bootstrap Pink / standard pink
        } elseif ($gender === 'prefiero_no_decir' || strtolower($gender) === 'prefiero no decir') {
            $gender_icon = 'fa-user-secret';
            $gender_color_style = 'color: #6c757d;'; // Secondary Grey
        }

        $data = [
            'is_completed' => true,
            'student_name' => $is_teacher_view ? fullname($user) : null, // Only show name header if teacher view
            'firstname' => $user->firstname, // Some templates might use this
            'date' => userdate($entry->created_at, get_string('strftimedatetimeshort')),
            'date_label' => get_string('date_completed', 'block_tmms_24'),
            'age' => $entry->age ? $entry->age : '-',
            'age_label' => get_string('age', 'block_tmms_24'),
            'gender_display' => $gender_display,
            'gender_label' => get_string('gender', 'block_tmms_24'),
            'gender_icon' => $gender_icon,
            'gender_color_style' => $gender_color_style,
            'dimensions' => $dimensions,
            'download_csv_url' => (new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'userid' => $entry->user, 'format' => 'csv']))->out(false),
            'download_json_url' => (new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'userid' => $entry->user, 'format' => 'json']))->out(false),
        ];

        if ($back_url) {
            if ($is_teacher_view) {
                $data['back_teacher_url'] = $back_url;
                $data['show_actions'] = true; 
            } else {
                $data['back_course_url'] = $back_url;
                $data['show_actions'] = false;
            }
        }

        // Add questionnaire responses data
        // Reuse logic similar to test_form but read-only
        $scale_options = [];
        for ($i = 1; $i <= 5; $i++) {
            $raw = get_string('scale_' . $i, 'block_tmms_24');
            $label = preg_replace('/^\s*\d+\s*[=:\-–—\.]+\s*/u', '', $raw);
            $scale_options[] = ['value' => $i, 'label' => trim($label)];
        }

        $items_data = [];
        $raw_items = self::get_tmms24_items();
        foreach ($raw_items as $number => $text) {
            $itemfield = 'item' . $number;
            $saved_value = (isset($entry->$itemfield)) ? (int)$entry->$itemfield : null;
            
            // For read-only display, we just need the selected label or something similar
            // But let's build a structure that allows "simulating" the form in disabled mode or just a list
            $selected_label = '-';
            foreach ($scale_options as $opt) {
                if ($opt['value'] === $saved_value) {
                    $selected_label = $opt['label'];
                    break;
                }
            }

            $percentage = ($saved_value / 5) * 100;

            $items_data[] = [
                'number' => $number,
                'text' => $text,
                'response_value' => $saved_value,
                'response_label' => $selected_label,
                'percentage' => $percentage,
                'color' => '#ff6600', // Official block color for neutrality
                'selected_1' => ($saved_value === 1),
                'selected_2' => ($saved_value === 2),
                'selected_3' => ($saved_value === 3),
                'selected_4' => ($saved_value === 4),
                'selected_5' => ($saved_value === 5),
            ];
        }
        $data['questionnaire_results'] = $items_data;
        $data['questionnaire_label'] = get_string('questionnaire', 'block_tmms_24');
        
        return $data;
    }

    public static function get_goal_text($dim, $gender) {
        $is_male = ($gender === 'M' || strtolower($gender) === 'masculino');
        
        if ($dim === 'percepcion') {
            $range = $is_male ? '22-32' : '25-35';
            $optimal = $is_male ? '27' : '30';
            $a = new stdClass();
            $a->range = $range;
            $a->optimal = $optimal;
            return get_string('goal_perception', 'block_tmms_24', $a);
        } else {
            // Comprension / Regulacion
            $min = $is_male ? 36 : 35;
            $a = new stdClass();
            $a->range = $min . '-40';
            return get_string('goal_linear', 'block_tmms_24', $a);
        }
    }
    
    public static function get_gender_label($gender) {
        if (!isset($gender) || empty($gender)) {
            return get_string('not_determined', 'block_tmms_24');
        }
        
        $g = strtolower($gender);
        if ($g === 'm' || $g === 'masculino') {
            return get_string('gender_male', 'block_tmms_24');
        } elseif ($g === 'f' || $g === 'femenino') {
            return get_string('female', 'block_tmms_24');
        } else {
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

    public static function render_results_html($scores, $interpretations, $interpretations_long, $gender, $entry, $completion_info = null) {
        global $PAGE;

        // Use the unified modern template for results.
        // We ignore the passed pre-calculated scores/interpretations to ensure consistency with the centralized logic in prepare_results_data.
        // We pass empty back_url because view.php renders its own buttons.
        $courseid = $PAGE->course->id;
        $data = self::prepare_results_data($entry, $courseid, false, '');

        return $PAGE->get_renderer('core')->render_from_template('block_tmms_24/results_details', $data);
    }

    /**
     * Calculates a normalized score (0-100) for fair comparison between dimensions.
     * 
     * Logic:
     * - Perception: Parabolic. Optimal (27M/30F) -> 100. Adequate Range -> 80-100. Outside -> 0-59.
     * - Comprehension/Regulation: Linear. Great Range -> 80-100. Adequate Range -> 60-79. Bad Range -> 0-59.
     */
    public static function get_normalized_score($dimension, $score, $gender) {
        $score = (int)$score;
        
        if ($dimension === 'percepcion') {
            // Perception Logic
            if ($gender === 'M') {
                $optimal = 27;
                $adequate_min = 22;
                $adequate_max = 32;
            } else {
                $optimal = 30;
                $adequate_min = 25;
                $adequate_max = 35;
            }
            
            // Check if in Adequate Range (Tier 1 - Best State for Perception)
            if ($score >= $adequate_min && $score <= $adequate_max) {
                // Map to [80, 100] based on distance to optimal
                // Optimal (27) -> 100
                // Edges (22, 32) -> 80
                
                if ($score <= $optimal) {
                    $side_range = $optimal - $adequate_min;
                    $dist = $optimal - $score;
                } else {
                    $side_range = $adequate_max - $optimal;
                    $dist = $score - $optimal;
                }
                
                if ($side_range > 0) {
                    return 100 - ($dist / $side_range) * 20;
                } else {
                    return 100;
                }
            } else {
                // Outside Adequate Range (Tier 3 - Bad) -> [0, 59]
                if ($score < $adequate_min) {
                    // Lower side: 0 to adequate_min-1
                    // Map 0 -> 0, adequate_min-1 -> 59
                    if ($adequate_min > 0) {
                        return ($score / $adequate_min) * 59;
                    }
                    return 0;
                } else {
                    // Upper side (Excessive): adequate_max+1 to 40
                    // Map adequate_max+1 -> 59, 40 -> 29 (Excessive is bad)
                    $range = 40 - $adequate_max;
                    $dist_from_edge = $score - $adequate_max;
                    if ($range > 0) {
                        return 59 - (($dist_from_edge / $range) * 30);
                    }
                    return 59;
                }
            }
        } else {
            // Comprehension & Regulation Logic (Linear)
            // Define thresholds
            if ($dimension === 'comprension') {
                if ($gender === 'M') {
                    $great_min = 36;
                    $adequate_min = 26;
                    $adequate_max = 35;
                } else {
                    $great_min = 35;
                    $adequate_min = 24;
                    $adequate_max = 34;
                }
            } else { // regulacion
                if ($gender === 'M') {
                    $great_min = 36;
                    $adequate_min = 24;
                    $adequate_max = 35;
                } else {
                    $great_min = 35;
                    $adequate_min = 24;
                    $adequate_max = 34;
                }
            }
            
            if ($score >= $great_min) {
                // Tier 1 (Great): [80, 100]
                $range = 40 - $great_min;
                $dist = $score - $great_min;
                if ($range > 0) {
                    return 80 + ($dist / $range) * 20;
                }
                return 100;
            } elseif ($score >= $adequate_min) {
                // Tier 2 (Adequate): [60, 79]
                $range = $adequate_max - $adequate_min;
                $dist = $score - $adequate_min;
                if ($range > 0) {
                    return 60 + ($dist / $range) * 19;
                }
                return 79;
            } else {
                // Tier 3 (Bad): [0, 59]
                // Map 0 -> 0, adequate_min-1 -> 59
                if ($adequate_min > 0) {
                    return ($score / $adequate_min) * 59;
                }
                return 0;
            }
        }
    }
}

class block_tmms_24 extends block_base {
    
    function init() {
        $this->title = get_string('pluginname', 'block_tmms_24');
    }
    
    function get_content() {
        global $USER, $DB, $COURSE, $OUTPUT;
        
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
        } else if (has_capability('block/tmms_24:taketest', $context)) {
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
        } else {
            // Users without either capability (e.g. guests/roles without taketest) see no TMMS content.
            $this->content->text = '';
        }
        
        return $this->content;
    }
    
    function has_config() {
        return true;
    }
    
    private function get_student_results($entry) {
        global $COURSE, $OUTPUT;
        
        // Build responses keyed by item1..item24 to preserve ordering.
        $responsesArray = [];
        for ($i = 1; $i <= 24; $i++) {
            $field_name = 'item' . $i;
            if (!isset($entry->{$field_name}) || (int)$entry->{$field_name} <= 0) {
                return '<div class="alert alert-warning">' . get_string('incomplete_data', 'block_tmms_24') . '</div>';
            }
            $responsesArray[$field_name] = (int)$entry->{$field_name};
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
        
        $dimension_names = [
            'percepcion' => get_string('perception', 'block_tmms_24'),
            'comprension' => get_string('comprehension', 'block_tmms_24'),
            'regulacion' => get_string('regulation', 'block_tmms_24')
        ];
        
        // Calculate normalized scores for comparison
        $normalized_scores = [];
        $gender = isset($entry->gender) ? $entry->gender : 'M';
        
        foreach ($scores as $dim => $score) {
            $normalized_scores[$dim] = TMMS24Facade::get_normalized_score($dim, $score, $gender);
        }
        
        // Check for "All Bad" case (All scores < 60)
        $all_bad = true;
        foreach ($normalized_scores as $n_score) {
            if ($n_score >= 60) {
                $all_bad = false;
                break;
            }
        }
        
        // Datos comunes para pasar al template
        $data = [
            'icon' => $this->get_tmms_24_icon('4em', 'display: block;', false),
            'show_descriptions' => !empty($this->config->showdescriptions),
            'title' => get_string('test_completed', 'block_tmms_24'),
            'subtitle' => get_string('emotional_intelligence_results', 'block_tmms_24'),
            'description' => get_string('test_description_short', 'block_tmms_24'),
            'all_bad' => $all_bad,
            'detailed_link' => (new moodle_url('/blocks/tmms_24/view.php', ['cid' => $COURSE->id]))->out(false),
            'view_detailed_str' => get_string('view_detailed_results', 'block_tmms_24')
        ];
        
        if ($all_bad) {
            // Case: All dimensions need improvement
            $data['your_dimensions_title'] = get_string('your_dimensions', 'block_tmms_24');
            $data['dimensions'] = [];
            
            foreach ($scores as $dimension => $score) {
                $interpretation = isset($interpretations[$dimension]) ? $interpretations[$dimension] : get_string('not_determined', 'block_tmms_24');
                $goal_text = TMMS24Facade::get_goal_text($dimension, $gender);
                
                $data['dimensions'][] = [
                    'name' => $dimension_names[$dimension],
                    'score' => $score,
                    'interpretation' => $interpretation,
                    'goal_text' => $goal_text
                ];
            }
        } else {
            // Case: At least one good dimension
            // Find max score
            $max_n_score = max($normalized_scores);
            
            // Find all dimensions with max score (allowing small float error)
            $star_dimensions = [];
            foreach ($normalized_scores as $dim => $n_score) {
                if (abs($n_score - $max_n_score) < 0.1) {
                    $star_dimensions[] = $dim;
                }
            }
            
            $data['star_dimensions_title'] = count($star_dimensions) > 1 ? 
                get_string('your_star_dimensions', 'block_tmms_24') : 
                get_string('your_star_dimension', 'block_tmms_24');
            
            $data['star_dimensions'] = [];
            $data['has_star_dimensions'] = true;

            foreach ($star_dimensions as $dim) {
                $score = $scores[$dim];
                $interpretation = isset($interpretations[$dim]) ? $interpretations[$dim] : get_string('not_determined', 'block_tmms_24');
                $goal_text = TMMS24Facade::get_goal_text($dim, $gender);
                
                $reason_icon = '';
                $reason_text = '';
                
                 if ($dim === 'percepcion') {
                     $optimal = ($gender === 'M') ? 27 : 30;
                     $reason_icon = '<i class="fa fa-balance-scale text-primary mr-1"></i>';
                     if ((int)$score === $optimal) {
                         $reason_text = get_string('star_dimension_reason_perception_exact', 'block_tmms_24');
                     } else {
                         $reason_text = get_string('star_dimension_reason_perception_close', 'block_tmms_24');
                     }
                } else {
                     $reason_icon = '<i class="fa fa-arrow-up text-success mr-1"></i>';
                     $reason_text = get_string('star_dimension_reason_other', 'block_tmms_24');
                }
                
                $data['star_dimensions'][] = [
                    'name' => $dimension_names[$dim],
                    'score' => $score,
                    'points_str' => get_string('points', 'block_tmms_24'),
                    'interpretation' => $interpretation,
                    'goal_text' => $goal_text,
                    'reason_icon' => $reason_icon,
                    'reason_text' => $reason_text
                ];
            }
            
            // Render Other Dimensions
            $other_dimensions = array_diff(array_keys($scores), $star_dimensions);
            
            // Sort other dimensions by normalized score descending
            usort($other_dimensions, function($a, $b) use ($normalized_scores) {
                return $normalized_scores[$b] <=> $normalized_scores[$a];
            });

            $data['has_other_dimensions'] = !empty($other_dimensions);
            if ($data['has_other_dimensions']) {
                $data['other_dimensions_title'] = get_string('other_dimensions', 'block_tmms_24');
                $data['other_dimensions'] = [];
                
                foreach ($other_dimensions as $dim) {
                    $score = $scores[$dim];
                    $interpretation = isset($interpretations[$dim]) ? $interpretations[$dim] : get_string('not_determined', 'block_tmms_24');
                    $goal_text = TMMS24Facade::get_goal_text($dim, $gender);
                    
                    $data['other_dimensions'][] = [
                        'name' => $dimension_names[$dim],
                        'score' => $score,
                        'interpretation' => $interpretation,
                        'goal_text' => $goal_text
                    ];
                }
            }
        }
        
        return $OUTPUT->render_from_template('block_tmms_24/results_summary', $data);
    }
    
    /**
     * Helper method to generate tmms_24 icon HTML (SVG)
     * @param string $size Icon size (default: 1.8em)
     * @param string $additional_style Additional inline styles
     * @param bool $centered Whether to center the icon
     * @return string HTML img tag with the SVG icon
     */
    private function get_tmms_24_icon($size = '1.8em', $additional_style = '', $centered = false) {
        $iconurl = new moodle_url('/blocks/tmms_24/pix/icon.svg');
        $style = 'width: ' . $size . '; height: ' . $size . '; vertical-align: middle; float: none !important;';
        if ($centered) {
            $style .= ' display: block; margin: 0 auto;';
        }
        if (!empty($additional_style)) {
            $style .= ' ' . $additional_style;
        }
        return '<img class="tmms-24-icon" src="' . $iconurl . '" alt="TMMS-24 Icon" style="' . $style . '" />';
    }
    
    private function get_test_invitation() {
        global $COURSE, $USER, $DB, $OUTPUT;
        
        // Check for response in tmms_24 table (user only takes test once)
        $response = $DB->get_record('tmms_24', array('user' => $USER->id));
        
        $data = [
            'icon' => $this->get_tmms_24_icon('4em', '', true),
            'show_descriptions' => !empty($this->config->showdescriptions),
            'title' => get_string('emotional_intelligence_test', 'block_tmms_24'),
            'subtitle' => get_string('discover_your_emotional_skills', 'block_tmms_24'),
        ];
        
        // Initialize variables
        $answered_count = 0;
        
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
                
                $data['in_progress'] = true;
                $data['description_short'] = get_string('test_description_short', 'block_tmms_24');
                $data['progress_title'] = get_string('your_progress', 'block_tmms_24');
                $data['answered_count'] = $answered_count;
                $data['progress_percentage'] = $progress_percentage;
                $data['progress_percentage_formatted'] = number_format($progress_percentage, 1);
                $data['completed_status'] = get_string('completed_status', 'block_tmms_24');
            
                if ($all_answered) {
                    $data['all_answered'] = true;
                    $data['all_answered_title'] = get_string('all_answered_title', 'block_tmms_24');
                    $data['all_answered_message'] = get_string('all_answered_message', 'block_tmms_24');
                    
                    $data['button_text'] = get_string('finish_test', 'block_tmms_24');
                    $data['button_icon'] = 'fa-flag-checkered';
                    $data['button_class'] = 'btn-success';
                } else {
                    $data['button_text'] = get_string('continue_test', 'block_tmms_24');
                    $data['button_icon'] = 'fa-play';
                    $data['button_class'] = 'btn-primary';
                }
            }
        }
        
        // Show test description for new test (when no response or no answers yet)
        if (!$response || ($response && !$response->is_completed && $answered_count == 0)) {
             $data['in_progress'] = false;
             $data['what_is_title'] = get_string('what_is_tmms24', 'block_tmms_24');
             $data['description_short'] = get_string('test_description_short', 'block_tmms_24');
             $data['feature_24'] = get_string('feature_24_questions', 'block_tmms_24');
             $data['feature_3'] = get_string('feature_3_dimensions', 'block_tmms_24');
             $data['feature_results'] = get_string('feature_instant_results', 'block_tmms_24');
             
             $data['button_text'] = get_string('start_test', 'block_tmms_24');
             $data['button_icon'] = 'fa-rocket';
             $data['button_class'] = 'btn-primary';
        }
        
        $url_params = array('cid' => $COURSE->id);
        if (isset($all_answered) && $all_answered) {
            $url_params['scroll_to_finish'] = 1;
        }
        $data['link_url'] = (new moodle_url('/blocks/tmms_24/view.php', $url_params))->out(false);
        
        if ($data['button_class'] != 'btn-success') {
             $data['button_style'] = 'background-color: #ff6600; border-color: #ff6600; color: white;';
        }

        return $OUTPUT->render_from_template('block_tmms_24/test_invitation', $data);
    }
    
    private function get_management_summary() {
        global $DB, $COURSE, $OUTPUT;
        
        $context = context_course::instance($COURSE->id);
        
        // 1. Get enrolled users SQL (users who CAN take the test)
        // Optimized: only fetching IDs to properly filter the tmms_24 table
        [$enrolledsql, $enrolledparams] = get_enrolled_sql($context, 'block/tmms_24:taketest', 0, true);
        
        // 2. Count total enrolled students
        $total_enrolled = count_enrolled_users($context, 'block/tmms_24:taketest', 0, true);
        
        if ($total_enrolled > 0) {
            // 3. Count completed (is_completed = 1)
            $sql_completed = "SELECT COUNT(tr.id) 
                              FROM {tmms_24} tr
                              JOIN ($enrolledsql) eu ON tr.user = eu.id
                              WHERE tr.is_completed = 1";
            $total_completed = $DB->count_records_sql($sql_completed, $enrolledparams);
            
            // 4. Count in progress (exists but not completed)
            $sql_inprogress = "SELECT COUNT(tr.id) 
                               FROM {tmms_24} tr
                               JOIN ($enrolledsql) eu ON tr.user = eu.id
                               WHERE tr.is_completed = 0";
            $total_in_progress = $DB->count_records_sql($sql_inprogress, $enrolledparams);
        } else {
            $total_completed = 0;
            $total_in_progress = 0;
        }
        
        $completion_rate = $total_enrolled > 0 ? ($total_completed / $total_enrolled) * 100 : 0;
        
        // Recent completions (fetch minimal data)
        $recent_completions_data = [];
        if ($total_completed > 0) {
            $sql = "SELECT tr.id, u.firstname, u.lastname, tr.created_at 
                    FROM {tmms_24} tr 
                    JOIN {user} u ON tr.user = u.id 
                    JOIN ($enrolledsql) eu ON tr.user = eu.id
                    WHERE tr.is_completed = 1
                    ORDER BY tr.created_at DESC";
            // Limit to 3 for performance
            $recent_recs = $DB->get_records_sql($sql, $enrolledparams, 0, 3);
            
            foreach ($recent_recs as $rec) {
                $completion_date = $rec->created_at ? $rec->created_at : time();
                $recent_completions_data[] = [
                    'firstname' => $rec->firstname,
                    'lastname' => $rec->lastname,
                    'date' => userdate($completion_date, get_string('strftimedatefullshort'))
                ];
            }
        }
        
        $data = [
            'icon' => $this->get_tmms_24_icon('4em', '', true),
            'title' => get_string('management_title', 'block_tmms_24'),
            'subtitle' => get_string('course_overview', 'block_tmms_24'),
            'completion_rate' => number_format($completion_rate, 1),
            'completion_rate_label' => get_string('completion_rate', 'block_tmms_24'),
            'total_completed' => $total_completed,
            'completed_label' => get_string('completed', 'block_tmms_24'),
            'total_in_progress' => $total_in_progress,
            'in_progress_label' => get_string('in_progress', 'block_tmms_24'),
            'of_str' => get_string('of', 'block_tmms_24'),
            'total_enrolled' => $total_enrolled,
            'students_completed_str' => get_string('students_completed', 'block_tmms_24'),
            'has_recent' => !empty($recent_completions_data),
            'recent_title' => get_string('recent_completions', 'block_tmms_24'),
            'recent_completions' => $recent_completions_data,
            'link_url' => (new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $COURSE->id]))->out(false),
            'view_all_str' => get_string('view_all_results', 'block_tmms_24')
        ];
        
        return $OUTPUT->render_from_template('block_tmms_24/management_summary', $data);
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
