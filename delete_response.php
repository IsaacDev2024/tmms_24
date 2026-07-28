<?php
/**
 * Delete Response - TMMS-24 Block
 *
 * @package    block_tmms_24
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
if (!has_capability('block/tmms_24:deletestudentdata', $context)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

if (!confirm_sesskey($sesskey)) {
    print_error('invalidsesskey');
}

$response = $DB->get_record('tmms_24', array('id' => $id), '*', MUST_EXIST);

// Safety: this table is global (one row per user). Ensure the user belongs to this course context.
if (!is_enrolled($context, $response->user, 'block/tmms_24:taketest', true)) {
    redirect(
        new moodle_url('/blocks/tmms_24/teacher_view.php', array('courseid' => $courseid)),
        get_string('invalidaccess'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

if ($DB->delete_records('tmms_24', array('id' => $id))) {
    // Get user info for notification
    $user = $DB->get_record('user', array('id' => $response->user));
    $message = get_string('response_deleted_success', 'block_tmms_24', fullname($user));
    redirect(
        new moodle_url('/blocks/tmms_24/teacher_view.php', array('courseid' => $courseid)),
        $message,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    redirect(
        new moodle_url('/blocks/tmms_24/teacher_view.php', array('courseid' => $courseid)),
        get_string('error_deleting_response', 'block_tmms_24'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
