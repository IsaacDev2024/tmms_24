<?php
/**
 * Save - TMMS-24 Block
 *
 * @package    block_tmms_24
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once(dirname(__FILE__) . '/block_tmms_24.php');

if (!isloggedin()) {
    if (optional_param('ajax', 0, PARAM_INT)) {
        echo json_encode(['success' => false, 'error' => 'not_logged_in']);
        die();
    }
    redirect($CFG->wwwroot . '/login/index.php');
}

require_sesskey();

$courseid = required_param('cid', PARAM_INT);
$responseid = required_param('responseid', PARAM_INT);
$is_ajax = optional_param('ajax', 0, PARAM_INT);
$auto_save = optional_param('auto_save', 0, PARAM_INT);

if ($courseid == SITEID || !$courseid) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'error' => 'invalid_course']);
        die();
    }
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);

// Teachers/managers should not submit TMMS-24.
if (has_capability('block/tmms_24:viewstudentdata', $context)) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'error' => 'no_permission']);
        die();
    }

    redirect(
        new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]),
        get_string('teachers_cannot_take_test', 'block_tmms_24'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Only students (taketest) can save responses.
if (!has_capability('block/tmms_24:taketest', $context)) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'error' => 'no_permission']);
        die();
    }

    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

// Get existing entry from tmms_24 table
$entry = null;
if ($responseid > 0) {
    $entry = $DB->get_record('tmms_24', array('id' => $responseid, 'user' => $USER->id));
} else {
    // Try to find existing entry by user (TMMS-24 is one per user, not per course)
    $entry = $DB->get_record('tmms_24', array('user' => $USER->id));
}

// Handle auto-save (partial save)
if ($auto_save) {
    // Collect demographic fields
    $age = optional_param('age', 0, PARAM_INT);
    $gender = optional_param('gender', '', PARAM_TEXT);
    
    // Collect question responses
    $has_any_question = false;
    $questions_data = array();
    for ($i = 1; $i <= 24; $i++) {
        $item_value = optional_param('item' . $i, null, PARAM_INT);
        if ($item_value !== null && $item_value >= 1 && $item_value <= 5) {
            $questions_data['item' . $i] = $item_value;
            $has_any_question = true;
        }
    }
    
    // Check if we have any meaningful data
    $has_demographics = ($age > 0 || !empty($gender));
    $has_any_data = $has_demographics || $has_any_question;
    
    // If no data at all, just return success without creating a record
    if (!$has_any_data && !$entry) {
        if ($is_ajax) {
            echo json_encode(['success' => true, 'message' => 'No data to save']);
            die();
        }
        redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)));
    }
    
    // If we have data but no existing entry, create it
    if ($has_any_data && !$entry) {
        $entry = new stdClass();
        $entry->user = $USER->id;
        $entry->is_completed = 0;
        $entry->created_at = time();
        $entry->updated_at = time();
        
        // Set demographics if provided
        if ($age > 0) {
            $entry->age = $age;
        }
        if (!empty($gender)) {
            $entry->gender = $gender;
        }
        
        // Set question responses
        foreach ($questions_data as $field => $value) {
            $entry->$field = $value;
        }
        
        $entry->id = $DB->insert_record('tmms_24', $entry);
        
        if ($is_ajax) {
            echo json_encode(['success' => true, 'responseid' => $entry->id]);
            die();
        }
        redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)));
    }
    
    // If entry exists, update it
    if ($entry) {
        // Update demographic fields
        if ($age > 0) {
            $entry->age = $age;
        }
        if (!empty($gender)) {
            $entry->gender = $gender;
        }
        
        // Update question responses
        foreach ($questions_data as $field => $value) {
            $entry->$field = $value;
        }
        
        $entry->updated_at = time();
        $DB->update_record('tmms_24', $entry);
        
        if ($is_ajax) {
            echo json_encode(['success' => true]);
            die();
        }
        redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)));
    }
    
    // Should not reach here
    if ($is_ajax) {
        echo json_encode(['success' => false, 'error' => 'unexpected_state']);
        die();
    }
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)));
}

// Handle final submission - entry must exist at this point
if (!$entry) {
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)), 
             get_string('invalidaccess'), null, 'error');
}

// Handle final submission
$age = required_param('age', PARAM_INT);
$gender = required_param('gender', PARAM_TEXT);

// Validate demographics
if ($age < 10 || $age > 100) {
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)), 
             'Edad inválida', null, 'error');
}

if (!in_array($gender, ['M', 'F', 'prefiero_no_decir'])) {
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)), 
             'Género inválido', null, 'error');
}

// Collect and validate all responses
$responses = [];
$missing_items = [];

for ($i = 1; $i <= 24; $i++) {
    $item_value = optional_param('item' . $i, 0, PARAM_INT);
    if ($item_value < 1 || $item_value > 5) {
        $missing_items[] = $i;
    } else {
        $responses[$i] = $item_value;
    }
}

// If missing items, redirect with error
if (!empty($missing_items)) {
    $error_message = 'Faltan responder ítems: ' . implode(', ', $missing_items);
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)), 
             $error_message, null, 'error');
}

// Calculate scores
$scores = TMMS24Facade::calculate_scores(array_values($responses));

// Update the existing entry with final data
$entry->age = $age;
$entry->gender = $gender;

// Assign individual responses
for ($i = 1; $i <= 24; $i++) {
    $field_name = 'item' . $i;
    $entry->$field_name = $responses[$i];
}

// Assign calculated scores
$entry->percepcion_score = $scores['percepcion'];
$entry->comprension_score = $scores['comprension'];
$entry->regulacion_score = $scores['regulacion'];

// Mark as completed
$entry->is_completed = 1;
$entry->updated_at = time();

try {
    // Update the tmms_24 record
    $DB->update_record('tmms_24', $entry);
    
    $message = get_string('test_saved_successfully', 'block_tmms_24');
    
    // Trigger event
    $event = \core\event\user_updated::create(array(
        'objectid' => $USER->id,
        'context' => $context,
        'relateduserid' => $USER->id,
        'other' => array('action' => 'tmms24_completed')
    ));
    $event->trigger();
    
    // Redirect to results
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid, 'view_results' => 1)), 
             $message, null, 'success');
             
} catch (Exception $e) {
    error_log('Error saving TMMS-24 test: ' . $e->getMessage());
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)), 
             get_string('error_saving_test', 'block_tmms_24'), null, 'error');
}
?>
