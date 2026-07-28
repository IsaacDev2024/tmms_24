<?php
/**
 * Test View and Results - TMMS-24 Block
 *
 * @package    block_tmms_24
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

// Incluir la clase base de bloques para Moodle 4.1
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

// Ahora incluir nuestro archivo después de que Moodle esté cargado
require_once(dirname(__FILE__) . '/block_tmms_24.php');

if (!isloggedin()) {
    redirect($CFG->wwwroot . '/login/index.php');
}

$courseid = required_param('cid', PARAM_INT);
$scroll_to_finish = optional_param('scroll_to_finish', 0, PARAM_INT);

if ($courseid == SITEID || !$courseid) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = context_course::instance($courseid);
$PAGE->set_context($context);

require_login($course);

// Check if the block is added to the course
if (!$DB->record_exists('block_instances', array('blockname' => 'tmms_24', 'parentcontextid' => $context->id))) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

// Teachers/managers should not take the test; redirect them with a friendly message.
if (has_capability('block/tmms_24:viewstudentdata', $context)) {
    redirect(
        new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]),
        get_string('teachers_cannot_take_test', 'block_tmms_24'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Users without taketest capability should not access the test UI.
if (!has_capability('block/tmms_24:taketest', $context)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

$PAGE->set_url('/blocks/tmms_24/view.php', array('cid' => $courseid));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_tmms_24'));
$PAGE->set_heading($course->fullname);

$PAGE->requires->css('/blocks/tmms_24/styles.css');

// Verificar si ya existe información del usuario.
$entry = $DB->get_record('tmms_24', array('user' => $USER->id));

echo $OUTPUT->header();
echo "<div class='block_tmms_24_container'>";

if ($entry && (int)$entry->is_completed === 1) {
    // Mostrar resultados completos
    $responses = [
        $entry->item1, $entry->item2, $entry->item3, $entry->item4, $entry->item5, $entry->item6, $entry->item7, $entry->item8,
        $entry->item9, $entry->item10, $entry->item11, $entry->item12, $entry->item13, $entry->item14, $entry->item15, $entry->item16,
        $entry->item17, $entry->item18, $entry->item19, $entry->item20, $entry->item21, $entry->item22, $entry->item23, $entry->item24
    ];
    
    $scores = TMMS24Facade::calculate_scores($responses);
    $interpretations = TMMS24Facade::get_all_interpretations($scores, $entry->gender);
    $interpretations_long = TMMS24Facade::get_all_interpretations_long($scores, $entry->gender);
    
    echo "<div class='tmms-results-container'>";
    echo "<h2 style='color: #ff6600;'>" . get_string('results_title', 'block_tmms_24') . "</h2>";
    
    // Header with student name
    echo '<div class="mb-4">';
    echo '<h4 class="mb-0">' . get_string('results_for', 'block_tmms_24') . ' ' . fullname($USER) . '</h4>';
    echo '</div>';

    // Prepare completion info
    $gender_display = '';
    switch($entry->gender) {
        case 'M':
            $gender_display = get_string('gender_male', 'block_tmms_24');
            break;
        case 'F':
            $gender_display = get_string('female', 'block_tmms_24');
            break;
        case 'prefiero_no_decir':
            $gender_display = get_string('gender_other_genres', 'block_tmms_24');
            break;
        default:
            $gender_display = $entry->gender; // fallback
    }

    $completion_info = [
        'date' => userdate($entry->created_at, get_string('strftimedatetimeshort')),
        'age' => s($entry->age),
        'gender_display' => $gender_display
    ];

    echo TMMS24Facade::render_results_html($scores, $interpretations, $interpretations_long, $entry->gender, $entry, $completion_info);
    
    // Disclaimer
    echo "<div class='disclaimer'>";
    echo "<p><em>" . get_string('disclaimer', 'block_tmms_24') . "</em></p>";
    echo "<p><small>" . get_string('legal_notice', 'block_tmms_24') . "</small></p>";
    echo "</div>";

    // Botones de acción
    echo "<div class='results-actions mt-4'>";
    
    // Solo profesores/administradores pueden descargar resultados
    if (has_capability('block/tmms_24:viewstudentdata', context_course::instance($courseid))) {
        echo "<a href='" . new moodle_url('/blocks/tmms_24/export.php', array('cid' => $courseid, 'format' => 'csv')) . "' class='btn btn-success'>" . get_string('download_csv', 'block_tmms_24') . "</a> ";
        echo "<a href='" . new moodle_url('/blocks/tmms_24/export.php', array('cid' => $courseid, 'format' => 'json')) . "' class='btn btn-success'>" . get_string('download_json', 'block_tmms_24') . "</a> ";
    }
    
    echo "<a href='" . new moodle_url('/course/view.php', array('id' => $courseid)) . "' class='btn btn-secondary'>" . get_string('back_to_course', 'block_tmms_24') . "</a>";
    echo "</div>";
    echo "</div>";

} else {
    // PREPARE DATA FOR MUSTACHE TEMPLATE
    
    // 1. Icon URL
    $iconurl = new moodle_url('/blocks/tmms_24/pix/icon.svg');
    
    // 2. Gender Options
    $gendervalue = (!empty($entry->gender) ? $entry->gender : '');
    $gender_options = [
        [
            'value' => 'M', 
            'label' => get_string('gender_male', 'block_tmms_24'), 
            'selected' => ($gendervalue === 'M')
        ],
        [
            'value' => 'F', 
            'label' => get_string('female', 'block_tmms_24'), 
            'selected' => ($gendervalue === 'F')
        ],
        [
            'value' => 'prefiero_no_decir', 
            'label' => get_string('gender_prefer_not_say', 'block_tmms_24'), 
            'selected' => ($gendervalue === 'prefiero_no_decir')
        ]
    ];

    // 3. Scale Labels
    $scale_labels = [];
    for ($i = 1; $i <= 5; $i++) {
        $raw = get_string('scale_' . $i, 'block_tmms_24');
        $label = preg_replace('/^\s*\d+\s*[=:\-–—\.]+\s*/u', '', $raw);
        $scale_labels[$i] = trim($label);
    }

    // 4. Items (Questions)
    $items_data = [];
    $raw_items = TMMS24Facade::get_tmms24_items();
    
    foreach ($raw_items as $number => $text) {
        $itemfield = 'item' . $number;
        $saved_value = (isset($entry->$itemfield)) ? (int)$entry->$itemfield : null;
        
        $scale_options = [];
        for ($k = 1; $k <= 5; $k++) {
            $scale_options[] = [
                'value' => $k,
                'label' => $scale_labels[$k],
                'checked' => ($saved_value === $k)
            ];
        }

        $items_data[] = [
            'number' => $number,
            'text' => $text,
            'scale_options' => $scale_options
        ];
    }

    // 5. Build Context Data
    $data = [
        'icon_url' => $iconurl->out(false),
        'test_page_title' => get_string('test_page_title', 'block_tmms_24'),
        'instructions_title' => get_string('instructions_title', 'block_tmms_24'),
        'instructions_text' => get_string('instructions_text', 'block_tmms_24'),
        'instructions_text2' => get_string('instructions_text2', 'block_tmms_24'),
        'save_url' => $CFG->wwwroot . "/blocks/tmms_24/save.php",
        'courseid' => $courseid,
        'responseid' => ($entry && isset($entry->id) ? (int)$entry->id : 0),
        'sesskey' => sesskey(),
        
        'demographics_label' => get_string('demographics', 'block_tmms_24'),
        'age_label' => get_string('age', 'block_tmms_24'),
        'age_value' => (!empty($entry->age) ? (int)$entry->age : ''),
        'gender_label' => get_string('gender', 'block_tmms_24'),
        'select_option_label' => get_string('choose') . '...',
        'gender_options' => $gender_options,
        
        'questionnaire_label' => get_string('questionnaire', 'block_tmms_24'),
        'items' => $items_data,
        
        'back_url' => (new moodle_url('/course/view.php', array('id' => $courseid)))->out(false),
        'back_to_course_label' => get_string('back_to_course', 'block_tmms_24'),
        'submit_test_label' => get_string('submit_test', 'block_tmms_24')
    ];

    echo $OUTPUT->render_from_template('block_tmms_24/test_form', $data);
}

echo "</div>"; // Close block_tmms_24_container

echo $OUTPUT->footer();
?>
