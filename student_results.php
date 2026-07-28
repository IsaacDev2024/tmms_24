<?php
/**
 * Student Results - TMMS-24 Block
 *
 * @package    block_tmms_24
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('block_tmms_24.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);

// Check if the block is added to the course
if (!$DB->record_exists('block_instances', array('blockname' => 'tmms_24', 'parentcontextid' => $context->id))) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

if (!has_capability('block/tmms_24:viewstudentdata', $context)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}
// Prevent looking up users outside this course.
if (!is_enrolled($context, $userid, 'block/tmms_24:taketest', true)) {
    redirect(new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]));
}

$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

$PAGE->set_url('/blocks/tmms_24/student_results.php', ['courseid' => $courseid, 'userid' => $userid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('individual_results', 'block_tmms_24'));
$PAGE->set_heading(get_string('individual_results', 'block_tmms_24'));
$PAGE->requires->css('/blocks/tmms_24/styles.css');

$result = $DB->get_record('tmms_24', ['user' => $userid]);

echo $OUTPUT->header();

if (!$result) {
    // Not started
    $data = [
        'student_name' => fullname($user),
        'back_teacher_url' => (new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]))->out(false),
        'show_actions' => true,
        'not_started' => true
    ];
    echo $OUTPUT->render_from_template('block_tmms_24/results_details', $data);

} else if ($result->is_completed == 0) {
    // In progress
    $answered = 0;
    for ($i = 1; $i <= 24; $i++) {
        $field = "item{$i}";
        if (isset($result->$field) && $result->$field !== null) $answered++;
    }
    $progress_percentage = round(($answered / 24) * 100, 1);

    $data = [
        'student_name' => fullname($user),
        'back_teacher_url' => (new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]))->out(false),
        'show_actions' => true,
        'is_completed' => false,
        'test_in_progress_message' => get_string('test_in_progress_message', 'block_tmms_24', fullname($user)),
        'progress_percentage' => $progress_percentage,
        'answered' => $answered,
        'total_questions' => 24,
        'show_submit_reminder' => ($answered == 24),
        'results_available_message' => get_string('results_available_when_complete', 'block_tmms_24', fullname($user))
    ];
    echo $OUTPUT->render_from_template('block_tmms_24/results_details', $data);

} else {
    // Completed - Use Centralized logic
    $back_url = (new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]))->out(false);
    
    // Pass true for $is_teacher_view
    $data = TMMS24Facade::prepare_results_data($result, $courseid, true, $back_url);
    
    echo $OUTPUT->render_from_template('block_tmms_24/results_details', $data);
}

echo $OUTPUT->footer();
