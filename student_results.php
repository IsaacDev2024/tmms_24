<?php
require_once('../../config.php');
require_once('block_tmms_24.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('block/tmms_24:viewallresults', $context);

$PAGE->set_url('/blocks/tmms_24/student_results.php', ['courseid' => $courseid, 'userid' => $userid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('student_results', 'block_tmms_24'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('pluginname', 'block_tmms_24'));
$PAGE->navbar->add(get_string('all_results_title', 'block_tmms_24'), new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]));
$PAGE->navbar->add(get_string('student_results', 'block_tmms_24'));

echo $OUTPUT->header();

// Back link
$back_url = new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]);
echo '<div class="mb-3">';
echo '<a href="' . $back_url . '" class="btn btn-secondary">';
echo '<i class="fa fa-arrow-left"></i> ' . get_string('back_to_teacher_view', 'block_tmms_24');
echo '</a>';
echo '</div>';

// Student info header
echo '<div class="card mb-4">';
echo '<div class="card-header">';
echo '<h4 class="mb-0">' . get_string('results_for', 'block_tmms_24') . ' ' . fullname($user) . '</h4>';
echo '</div>';
echo '<div class="card-body">';

// Get student's test result (in any course) - both completed and in-progress
$result = $DB->get_record('tmms_24', ['user' => $userid]);

if (!$result) {
    echo '<div class="alert alert-info">';
    echo get_string('student_not_completed', 'block_tmms_24');
    echo '</div>';
} else if ($result->is_completed == 0) {
    // Test is in progress - show progress similar to CHASIDE
    
    // Calculate progress
    $answered = 0;
    for ($i = 1; $i <= 24; $i++) {
        $field = "item{$i}";
        if (isset($result->$field) && $result->$field !== null) {
            $answered++;
        }
    }
    $progress_percentage = round(($answered / 24) * 100, 1);
    
    echo '<div class="alert alert-warning" role="alert">';
    echo '<h4 class="alert-heading"><i class="fa fa-clock-o"></i> ' . get_string('test_in_progress', 'block_tmms_24') . '</h4>';
    echo '<p>' . get_string('test_in_progress_message', 'block_tmms_24', fullname($user)) . '</p>';
    echo '<hr>';
    echo '<p class="mb-1"><strong>' . get_string('progress_label', 'block_tmms_24') . ':</strong></p>';
    echo '<div class="progress mb-2" style="height: 30px;">';
    echo '<div class="progress-bar bg-warning" role="progressbar" style="width: ' . $progress_percentage . '%" aria-valuenow="' . $progress_percentage . '" aria-valuemin="0" aria-valuemax="100">';
    echo '<strong>' . $progress_percentage . '%</strong>';
    echo '</div>';
    echo '</div>';
    echo '<p><strong>' . get_string('has_answered', 'block_tmms_24') . ':</strong> ' . $answered . '/24 ' . get_string('questions', 'block_tmms_24') . '</p>';
    
    // Special message if all questions answered but not submitted
    if ($answered == 24) {
        echo '<div class="alert alert-info mt-2" role="alert">';
        echo '<i class="fa fa-info-circle"></i> ';
        echo '<strong>' . get_string('remind_submit_test', 'block_tmms_24') . '</strong>';
        echo '</div>';
    }
    
    echo '<p class="mb-0"><em>' . get_string('results_available_when_complete', 'block_tmms_24', fullname($user)) . '</em></p>';
    echo '</div>';
    
} else {
    // Calculate scores from individual item responses
    $responses = [];
    for ($i = 1; $i <= 24; $i++) {
        $item = 'item' . $i;
        $responses[] = $result->$item;
    }
    $scores = TMMS24Facade::calculate_scores($responses);
    $interpretations = TMMS24Facade::get_all_interpretations($scores, $result->gender);

    // Display results summary
    echo '<div class="row mb-4">';
    echo '<div class="col-md-4">';
    echo '<div class="card border-primary">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('perception', 'block_tmms_24') . '</h5>';
    echo '<h3 class="text-primary">' . $scores['percepcion'] . '/40</h3>';
    // Get interpretations directly from the array
    $percepcion_interp = $interpretations['percepcion'];
    $comprension_interp = $interpretations['comprension'];
    $regulacion_interp = $interpretations['regulacion'];
    
    echo '<p class="mb-0">' . $percepcion_interp . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-4">';
    echo '<div class="card border-info">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('comprehension', 'block_tmms_24') . '</h5>';
    echo '<h3 class="text-info">' . $scores['comprension'] . '/40</h3>';
    echo '<p class="mb-0">' . $comprension_interp . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-4">';
    echo '<div class="card border-success">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('regulation', 'block_tmms_24') . '</h5>';
    echo '<h3 class="text-success">' . $scores['regulacion'] . '/40</h3>';
    echo '<p class="mb-0">' . $regulacion_interp . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Test completion info
    echo '<div class="row mb-4">';
    echo '<div class="col-md-4">';
    echo '<strong>' . get_string('date_completed', 'block_tmms_24') . ':</strong> ';
    echo userdate($result->created_at, get_string('strftimedatetimeshort'));
    echo '</div>';
    echo '<div class="col-md-4">';
    echo '<strong>' . get_string('age', 'block_tmms_24') . ':</strong> ';
    echo $result->age ? $result->age : '-';
    echo '</div>';
    echo '<div class="col-md-4">';
    echo '<strong>' . get_string('gender', 'block_tmms_24') . ':</strong> ';
    
    // Convert gender code to display string
    $gender_display = '';
    switch($result->gender) {
        case 'M':
            $gender_display = get_string('gender_male', 'block_tmms_24');
            break;
        case 'F':
            $gender_display = get_string('gender_female', 'block_tmms_24');
            break;
        case 'prefiero_no_decir':
            $gender_display = get_string('gender_prefer_not_say', 'block_tmms_24');
            break;
        default:
            $gender_display = $result->gender; // fallback
    }
    echo $gender_display;
    echo '</div>';
    echo '</div>';

    // Detailed responses
    echo '<h5>' . get_string('detailed_responses', 'block_tmms_24') . '</h5>';
    
    $dimensions = [
        'perception' => range(1, 8),
        'comprehension' => range(9, 16), 
        'regulation' => range(17, 24)
    ];

    foreach ($dimensions as $dimension => $items) {
        echo '<div class="card mb-3">';
        echo '<div class="card-header">';
        echo '<h6 class="mb-0">' . get_string($dimension, 'block_tmms_24') . '</h6>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm">';
        
        foreach ($items as $item_num) {
            $item_key = 'item' . $item_num;
            $response_value = $result->$item_key;
            
            echo '<tr>';
            echo '<td style="width: 60px;"><strong>' . $item_num . '.</strong></td>';
            echo '<td>' . get_string('item' . $item_num, 'block_tmms_24') . '</td>';
            echo '<td style="width: 100px;" class="text-center">';
            echo '<span class="badge badge-' . ($response_value >= 4 ? 'success' : ($response_value >= 3 ? 'warning' : 'danger')) . '">';
            echo $response_value . '/5';
            echo '</span>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Overall interpretation summary
    echo '<div class="card mt-4">';
    echo '<div class="card-header">';
    echo '<h5 class="mb-0">' . get_string('results_interpretation', 'block_tmms_24') . '</h5>';
    echo '</div>';
    echo '<div class="card-body">';
    
    // Display interpretations directly
    echo '<div class="row">';
    echo '<div class="col-md-4"><strong>' . get_string('perception', 'block_tmms_24') . ':</strong><br>' . $interpretations['percepcion'] . '</div>';
    echo '<div class="col-md-4"><strong>' . get_string('comprehension', 'block_tmms_24') . ':</strong><br>' . $interpretations['comprension'] . '</div>';
    echo '<div class="col-md-4"><strong>' . get_string('regulation', 'block_tmms_24') . ':</strong><br>' . $interpretations['regulacion'] . '</div>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
}

echo '</div>';  // End card-body
echo '</div>';  // End card

echo $OUTPUT->footer();
