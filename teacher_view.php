<?php
require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_once('block_tmms_24.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('block/tmms_24:viewallresults', $context);

$PAGE->set_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('all_results_title', 'block_tmms_24'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('pluginname', 'block_tmms_24'));
$PAGE->navbar->add(get_string('all_results_title', 'block_tmms_24'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tmms_24_dashboard', 'block_tmms_24'));

// Add description
echo $OUTPUT->box(
    get_string('admin_dashboard_description', 'block_tmms_24'),
    'generalbox'
);

// Get enrolled students in this course (filter by student role)
$enrolled_users = get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);

// Filtrar solo estudiantes (rol 5)
$student_ids = array();
foreach ($enrolled_users as $user) {
    $roles = get_user_roles($context, $user->id);
    foreach ($roles as $role) {
        if ($role->roleid == 5) { // 5 = student
            $student_ids[] = $user->id;
            break;
        }
    }
}

// Get results only for enrolled students (both completed and in progress)
$all_results = array();
$results_completed = array();
$results_in_progress = array();
$all_filtered_results = array();
if (!empty($student_ids)) {
    list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED);
    $all_results = $DB->get_records_select('tmms_24', "user $insql", $params);
    
    // Separate completed from in-progress (only count in-progress if has at least 1 answer)
    foreach ($all_results as $result) {
        if ($result->is_completed == 1) {
            $results_completed[] = $result;
        } else {
            // Count answered questions to determine if really in progress
            $answered_count = 0;
            for ($i = 1; $i <= 24; $i++) {
                $item = 'item' . $i;
                if (isset($result->$item) && $result->$item !== null) {
                    $answered_count++;
                }
            }
            // Only add to in_progress if at least 1 question answered
            if ($answered_count > 0) {
                $results_in_progress[] = $result;
            }
        }
    }
    
    // Merge both arrays and sort by last action date DESC (most recent first)
    $all_filtered_results = array_merge($results_completed, $results_in_progress);
    usort($all_filtered_results, function($a, $b) {
        // For completed tests, use created_at; for in-progress, use updated_at (or created_at if not set)
        $a_time = ($a->is_completed == 1) ? $a->created_at : (isset($a->updated_at) && $a->updated_at ? $a->updated_at : $a->created_at);
        $b_time = ($b->is_completed == 1) ? $b->created_at : (isset($b->updated_at) && $b->updated_at ? $b->updated_at : $b->created_at);
        return $b_time - $a_time;
    });
    
    // Update the arrays to use the sorted merged list
    $results_completed = array();
    $results_in_progress = array();
    foreach ($all_filtered_results as $result) {
        if ($result->is_completed == 1) {
            $results_completed[] = $result;
        } else {
            $results_in_progress[] = $result;
        }
    }
}

// Calculate statistics
$total_enrolled = count($student_ids);
$total_completed = count($results_completed);
$total_in_progress = count($results_in_progress);
$completion_rate = $total_enrolled > 0 ? ($total_completed / $total_enrolled) * 100 : 0;

// Statistics cards
echo '<div class="row mb-4">';
echo '<div class="col-12">';
echo '<h4>' . get_string('statistics', 'block_tmms_24') . '</h4>';
echo '</div>';
echo '</div>';

echo '<div class="row mb-4">';

// Total enrolled
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card border-primary">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-users text-primary" style="font-size: 2em;"></i>';
echo '<h3 class="mt-2 mb-1">' . $total_enrolled . '</h3>';
echo '<p class="text-muted mb-0">' . get_string('enrolled_students', 'block_tmms_24') . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

// Completed tests
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card border-success">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-check-circle text-success" style="font-size: 2em;"></i>';
echo '<h3 class="mt-2 mb-1">' . $total_completed . '</h3>';
echo '<p class="text-muted mb-0">' . get_string('total_completed', 'block_tmms_24') . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

// In progress tests
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card border-warning">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-hourglass-half text-warning" style="font-size: 2em;"></i>';
echo '<h3 class="mt-2 mb-1">' . $total_in_progress . '</h3>';
echo '<p class="text-muted mb-0">' . get_string('in_progress', 'block_tmms_24') . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

// Completion rate
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card border-info">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-percent text-info" style="font-size: 2em;"></i>';
echo '<h3 class="mt-2 mb-1">' . number_format($completion_rate, 1) . '%</h3>';
echo '<p class="text-muted mb-0">' . get_string('completion_rate', 'block_tmms_24') . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>';

if (!empty($results_completed)) {
    // Average scores statistics (only for completed tests)
    $avg_scores = ['percepcion' => 0, 'comprension' => 0, 'regulacion' => 0];
    $score_distributions = [
        'percepcion' => ['difficulty' => 0, 'adequate' => 0, 'excellent_excessive' => 0],
        'comprension' => ['difficulty' => 0, 'adequate' => 0, 'excellent' => 0],
        'regulacion' => ['difficulty' => 0, 'adequate' => 0, 'excellent' => 0]
    ];
    
    foreach ($results_completed as $result) {
        // Calculate scores from individual item responses
        $responses = [];
        for ($i = 1; $i <= 24; $i++) {
            $item = 'item' . $i;
            $responses[] = $result->$item;
        }
        $scores = TMMS24Facade::calculate_scores($responses);
        $interpretations = TMMS24Facade::get_all_interpretations($scores, $result->gender);
        
        $avg_scores['percepcion'] += $scores['percepcion'];
        $avg_scores['comprension'] += $scores['comprension'];
        $avg_scores['regulacion'] += $scores['regulacion'];
        
        // Count interpretations for distribution
        foreach (['percepcion', 'comprension', 'regulacion'] as $dimension) {
            // Get interpretation directly from the array
            $interp = $interpretations[$dimension];
            
            // Categorizar según las nuevas interpretaciones
            if (strpos($interp, get_string('perception_difficulty_feeling', 'block_tmms_24')) !== false ||
                strpos($interp, get_string('comprehension_difficulty_understanding', 'block_tmms_24')) !== false ||
                strpos($interp, get_string('regulation_difficulty_managing', 'block_tmms_24')) !== false) {
                $score_distributions[$dimension]['difficulty']++;
            } elseif (strpos($interp, get_string('perception_excessive_attention', 'block_tmms_24')) !== false) {
                $score_distributions[$dimension]['excellent_excessive']++; // Para percepción, excesiva atención
            } elseif (strpos($interp, get_string('comprehension_great_clarity', 'block_tmms_24')) !== false ||
                     strpos($interp, get_string('regulation_great_capacity', 'block_tmms_24')) !== false) {
                $score_distributions[$dimension]['excellent']++;
            } else {
                // Capacidad adecuada (todas las dimensiones)
                $score_distributions[$dimension]['adequate']++;
            }
        }
    }
    
    $avg_scores['percepcion'] /= $total_completed;
    $avg_scores['comprension'] /= $total_completed;
    $avg_scores['regulacion'] /= $total_completed;

    // Dimension statistics
    echo '<div class="row mb-4">';
    echo '<div class="col-12">';
    echo '<h5>' . get_string('dimension_statistics', 'block_tmms_24') . '</h5>';
    echo '<div class="card">';
    echo '<div class="card-body">';
    
    echo '<div class="row">';
    $dimensions = [
        'percepcion' => get_string('perception', 'block_tmms_24'),
        'comprension' => get_string('comprehension', 'block_tmms_24'),
        'regulacion' => get_string('regulation', 'block_tmms_24')
    ];
    
    foreach ($dimensions as $dim_key => $dim_name) {
        $avg_score = $avg_scores[$dim_key];
        $distribution = $score_distributions[$dim_key];
        
        echo '<div class="col-md-4 mb-3">';
        echo '<div class="border rounded p-3">';
        echo '<h6 class="mb-2">' . $dim_name . '</h6>';
        echo '<div class="mb-2">';
        echo '<small class="text-muted">' . get_string('average_score', 'block_tmms_24') . ':</small> ';
        echo '<strong>' . number_format($avg_score, 1) . '/40</strong>';
        echo '</div>';
        echo '<div class="progress mb-1" style="height: 8px;">';
        $progress_width = ($avg_score / 40) * 100;
        echo '<div class="progress-bar bg-primary" style="width: ' . $progress_width . '%"></div>';
        echo '</div>';
        echo '<small class="text-muted">';
        if ($dim_key === 'percepcion') {
            // Para percepción: dificultad, adecuado, excesivo
            echo get_string('difficulty_category', 'block_tmms_24') . ': ' . $distribution['difficulty'] . ' | ';
            echo get_string('adequate_category', 'block_tmms_24') . ': ' . $distribution['adequate'] . ' | ';
            echo get_string('excessive_category', 'block_tmms_24') . ': ' . $distribution['excellent_excessive'];
        } else {
            // Para comprensión y regulación: dificultad, adecuado, excelente
            echo get_string('difficulty_category', 'block_tmms_24') . ': ' . $distribution['difficulty'] . ' | ';
            echo get_string('adequate_category', 'block_tmms_24') . ': ' . $distribution['adequate'] . ' | ';
            echo get_string('excellent_category', 'block_tmms_24') . ': ' . $distribution['excellent'];
        }
        echo '</small>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Export buttons
    echo '<div class="row mb-3">';
    echo '<div class="col-12">';
    echo '<div class="btn-group" role="group">';
    $download_csv_url = new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'format' => 'csv']);
    $download_json_url = new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'format' => 'json']);
    echo '<a href="' . $download_csv_url . '" class="btn btn-success">';
    echo '<i class="fa fa-download"></i> ' . get_string('download_csv', 'block_tmms_24');
    echo '</a>';
    echo '<a href="' . $download_json_url . '" class="btn btn-info">';
    echo '<i class="fa fa-download"></i> ' . get_string('download_json', 'block_tmms_24');
    echo '</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Students table section
    echo '<div class="row">';
    echo '<div class="col-12">';
    echo '<h5>' . get_string('student_responses', 'block_tmms_24') . '</h5>';
    echo '</div>';
    echo '</div>';

    // Results table with management capabilities (include both completed and in progress)
    $table = new flexible_table('block_tmms_24_report');
    $table->define_columns(array('user', 'status', 'perception', 'comprehension', 'regulation', 'last_action', 'viewresults', 'download', 'actions'));
    $table->define_headers(array(
        get_string('student', 'block_tmms_24'),
        get_string('status', 'block_tmms_24'),
        get_string('perception', 'block_tmms_24'),
        get_string('comprehension', 'block_tmms_24'),
        get_string('regulation', 'block_tmms_24'),
        get_string('date_last_action', 'block_tmms_24'),
        get_string('view_results', 'block_tmms_24'),
        get_string('download', 'block_tmms_24'),
        get_string('actions', 'block_tmms_24')
    ));
    $table->set_attribute('class', 'admintable');
    $table->define_baseurl($PAGE->url);
    $table->setup();

    // Combine completed and in-progress results for the table
    $all_table_results = !empty($all_filtered_results)
        ? $all_filtered_results
        : array_merge($results_completed, $results_in_progress);
    
    foreach ($all_table_results as $result) {
        $user = $DB->get_record('user', ['id' => $result->user], 'id, firstname, lastname, picture, imagealt, firstnamephonetic, lastnamephonetic, middlename, alternatename, email');
        if (!$user) {
            continue;
        }
        
        $is_completed = ($result->is_completed == 1);
        
        // Count answered questions
        $answered_count = 0;
        for ($i = 1; $i <= 24; $i++) {
            $item = 'item' . $i;
            if (isset($result->$item) && $result->$item !== null) {
                $answered_count++;
            }
        }
        
        // Status badge with progress counter (only in-progress items reach here)
        if ($is_completed) {
            $status_badge = '<span class="badge badge-success"><i class="fa fa-check"></i> ' . get_string('completed', 'block_tmms_24') . '</span>';
        } else {
            $status_badge = '<span class="badge badge-warning"><i class="fa fa-hourglass-half"></i> ' . 
                           get_string('in_progress', 'block_tmms_24') . ' (' . $answered_count . '/24)</span>';
        }
        
        // Calculate scores (only if completed)
        $perception_score = '-';
        $comprehension_score = '-';
        $regulation_score = '-';
        
        if ($is_completed) {
            $responses = [];
            for ($i = 1; $i <= 24; $i++) {
                $item = 'item' . $i;
                $responses[] = $result->$item;
            }
            $scores = TMMS24Facade::calculate_scores($responses);
            $perception_score = $scores['percepcion'];
            $comprehension_score = $scores['comprension'];
            $regulation_score = $scores['regulacion'];
        }
        
        $usercell = $OUTPUT->user_picture($user, array('size' => 35, 'courseid' => $courseid)) . ' ' . fullname($user);
        
        // View results button (enable for both completed and in-progress)
        $viewresultsurl = new moodle_url('/blocks/tmms_24/student_results.php', array(
            'courseid' => $courseid,
            'userid' => $result->user
        ));
        
        $viewresultsbutton = html_writer::link(
            $viewresultsurl, 
            $OUTPUT->pix_icon('i/report', get_string('view_results', 'block_tmms_24')),
            array(
                'class' => 'btn btn-sm btn-outline-primary tmms-view-results',
                'title' => get_string('view_results', 'block_tmms_24')
            )
        );

        // Delete button (if user has permission)
        $deletebutton = '';
        if (has_capability('block/tmms_24:viewallresults', $context) && has_capability('moodle/course:manageactivities', $context)) {
            $deleteurl = new moodle_url('/blocks/tmms_24/delete_response.php', array(
                'id' => $result->id,
                'courseid' => $courseid,
                'sesskey' => sesskey()
            ));
            
            $deletebutton = html_writer::link(
                $deleteurl, 
                $OUTPUT->pix_icon('t/delete', get_string('delete_response', 'block_tmms_24')),
                array(
                    'class' => 'btn btn-sm btn-outline-danger tmms-delete-btn',
                    'title' => get_string('delete_response', 'block_tmms_24'),
                    'onclick' => 'return confirm("' . get_string('delete_response_confirm', 'block_tmms_24', fullname($user)) . '");'
                )
            );
        }

        // Download buttons for individual student (disabled if not completed)
        if ($is_completed) {
            $downloadcsvurl = new moodle_url('/blocks/tmms_24/export.php', array(
                'cid' => $courseid,
                'userid' => $result->user,
                'format' => 'csv'
            ));
            
            $downloadjsonurl = new moodle_url('/blocks/tmms_24/export.php', array(
                'cid' => $courseid,
                'userid' => $result->user,
                'format' => 'json'
            ));
            
            $downloadbuttons = html_writer::link(
                $downloadcsvurl, 
                '<i class="fa fa-file-excel-o"></i> CSV',
                array(
                    'class' => 'btn btn-sm btn-success me-1',
                    'title' => 'Download CSV'
                )
            );
            
            $downloadbuttons .= html_writer::link(
                $downloadjsonurl, 
                '<i class="fa fa-file-code-o"></i> JSON',
                array(
                    'class' => 'btn btn-sm btn-info',
                    'title' => 'Download JSON'
                )
            );
        } else {
            $downloadbuttons = '<button class="btn btn-sm btn-secondary me-1" disabled title="' . get_string('test_not_completed_yet', 'block_tmms_24') . '">' .
                              '<i class="fa fa-file-excel-o"></i> CSV</button>' .
                              '<button class="btn btn-sm btn-secondary" disabled title="' . get_string('test_not_completed_yet', 'block_tmms_24') . '">' .
                              '<i class="fa fa-file-code-o"></i> JSON</button>';
        }

        // Date cell - show created_at if completed, updated_at if in progress
        if ($is_completed) {
            $date_cell = userdate($result->created_at, get_string('strftimedatetimeshort'));
        } else {
            // For in-progress, show updated_at (last modification date)
            $date_cell = isset($result->updated_at) && $result->updated_at ? 
                        userdate($result->updated_at, get_string('strftimedatetimeshort')) : '-';
        }
        
        $row = array(
            $usercell,
            $status_badge,
            $perception_score,
            $comprehension_score,
            $regulation_score,
            $date_cell,
            $viewresultsbutton,
            $downloadbuttons,
            $deletebutton
        );
        $table->add_data($row);
    }
    
    $table->print_html();
} else {
    echo '<div class="alert alert-info">';
    echo get_string('no_results_yet', 'block_tmms_24');
    echo '</div>';
}

// Add custom CSS for button colors (Cognitio theme compatibility)
echo '<style>
.tmms-view-results {
    color: #e91e63 !important;
    border-color: #e91e63 !important;
}
.tmms-view-results:hover {
    background-color: #e91e63 !important;
    color: white !important;
    border-color: #e91e63 !important;
}
.tmms-delete-btn {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}
.tmms-delete-btn:hover {
    background-color: #dc3545 !important;
    color: white !important;
    border-color: #dc3545 !important;
}
</style>';

echo $OUTPUT->footer();
