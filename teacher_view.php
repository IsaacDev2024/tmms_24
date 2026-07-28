<?php
/**
 * Teacher View - TMMS-24 Block
 *
 * @package    block_tmms_24
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_once('block_tmms_24.php');

$courseid = required_param('courseid', PARAM_INT);
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

$action = optional_param('action', '', PARAM_ALPHA);
$entryid = optional_param('id', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$search = optional_param('search', '', PARAM_NOTAGS);
$gender_filter = optional_param('gender_filter', 'all', PARAM_ALPHA);

$PAGE->set_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]);
$PAGE->set_pagelayout('incourse');
$title = get_string('tmms_24_dashboard', 'block_tmms_24');
$PAGE->set_title($title . " : " . $course->fullname);
$PAGE->set_heading($title . " : " . $course->fullname);

$PAGE->requires->css('/blocks/tmms_24/styles.css');

$icon_url = new moodle_url('/blocks/tmms_24/pix/icon.svg');
$icon_html = '<img src="' . $icon_url . '" alt="TMMS-24 Icon" style="width: 50px; height: 50px; vertical-align: middle; margin-right: 15px;" />';

// Data for template
$data = [
    'title' => $title,
    'description' => format_text(get_string('admin_dashboard_description', 'block_tmms_24'), FORMAT_HTML),
    'icon_html' => $icon_html,
    'courseid' => $courseid,
    'gender_filter' => $gender_filter,
    'search_term' => $search,
    'page_url' => (new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]))->out(false),
    'back_url' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
];

// Get enrolled students
$enrolled_users = get_enrolled_users($context, 'block/tmms_24:taketest', 0, 'u.id', null, 0, 0, true);
$student_ids = array();
foreach ($enrolled_users as $user) {
    $candidateid = (int)$user->id;
    if (has_capability('block/tmms_24:viewstudentdata', $context, $candidateid)) continue;
    $student_ids[] = $candidateid;
}

// -------------------------------------------------------------------------
// DELETE ACTION
// -------------------------------------------------------------------------
if ($action === 'delete' && $entryid) {
    if (!has_capability('block/tmms_24:deletestudentdata', $context)) {
        redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
    }

    $entry = $DB->get_record('tmms_24', array('id' => $entryid), '*', MUST_EXIST);
    if (!is_enrolled($context, $entry->user, 'block/tmms_24:taketest', true)) {
        redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
    }

    if (confirm_sesskey() && optional_param('confirm', 0, PARAM_INT)) {
        $DB->delete_records('tmms_24', array('id' => $entryid));
        $deleteduser = $DB->get_record('user', array('id' => $entry->user));
        redirect(
            new moodle_url('/blocks/tmms_24/teacher_view.php', array('courseid' => $courseid)),
            get_string('response_deleted_success', 'block_tmms_24', fullname($deleteduser))
        );
        exit;
    }

    // Confirmation Screen
    $targetuser = $DB->get_record('user', array('id' => $entry->user));
    $data['delete_confirmation'] = true;
    $data['confirm_message'] = get_string('delete_response_confirm', 'block_tmms_24', fullname($targetuser));
    $data['confirm_url'] = (new moodle_url('/blocks/tmms_24/teacher_view.php', [
        'courseid' => $courseid, 'action' => 'delete', 'id' => $entryid, 'confirm' => 1, 'sesskey' => sesskey()
    ]))->out(false);
    $data['cancel_url'] = (new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]))->out(false);

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('block_tmms_24/teacher_view', $data);
    echo $OUTPUT->footer();
    exit;
}

// -------------------------------------------------------------------------
// MAIN VIEW
// -------------------------------------------------------------------------

// Get results
$all_results = array();
$results_completed = array();
$results_in_progress = array();
$all_filtered_results = array();

if (!empty($student_ids)) {
    list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED);
    $all_results = $DB->get_records_select('tmms_24', "user $insql", $params);
    
    foreach ($all_results as $result) {
        if ($result->is_completed == 1) {
            $results_completed[] = $result;
        } else {
            $answered_count = 0;
            for ($i = 1; $i <= 24; $i++) {
                $item = 'item' . $i;
                if (isset($result->$item) && $result->$item !== null) $answered_count++;
            }
            if ($answered_count > 0) $results_in_progress[] = $result;
        }
    }
    
    $all_filtered_results = array_merge($results_completed, $results_in_progress);
    usort($all_filtered_results, function($a, $b) {
        $a_time = ($a->is_completed == 1) ? $a->created_at : (isset($a->updated_at) && $a->updated_at ? $a->updated_at : $a->created_at);
        $b_time = ($b->is_completed == 1) ? $b->created_at : (isset($b->updated_at) && $b->updated_at ? $b->updated_at : $b->created_at);
        return $b_time - $a_time;
    });

    // Re-segment after sort
    $results_completed = array_filter($all_filtered_results, function($r) { return $r->is_completed == 1; });
    $results_in_progress = array_filter($all_filtered_results, function($r) { return $r->is_completed != 1; });
}

$total_enrolled = count($student_ids);
$total_completed = count($results_completed);
$total_in_progress = count($results_in_progress);
$completion_rate = $total_enrolled > 0 ? ($total_completed / $total_enrolled) * 100 : 0;

$data['total_enrolled'] = $total_enrolled;
$data['total_completed'] = $total_completed;
$data['total_in_progress'] = $total_in_progress;
$data['completion_rate'] = number_format($completion_rate, 1);
$data['has_completed'] = (!empty($results_completed));

// -------------------------------------------------------------------------
// DIMENSION STATISTICS (If completed exists)
// -------------------------------------------------------------------------
if (!empty($results_completed)) {
    // Filter Links Styles
    $active_style = 'background-color: #ff6600; border-color: #ff6600; color: white;';
    $inactive_style = 'color: #ff6600; border-color: #ff6600; background-color: white;';
    
    $data['url_all'] = (new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid, 'gender_filter' => 'all']))->out(false);
    $data['url_m'] = (new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid, 'gender_filter' => 'M']))->out(false);
    $data['url_f'] = (new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid, 'gender_filter' => 'F']))->out(false);
    
    $data['style_all'] = ($gender_filter === 'all') ? $active_style : $inactive_style;
    $data['style_m'] = ($gender_filter === 'M') ? $active_style : $inactive_style;
    $data['style_f'] = ($gender_filter === 'F') ? $active_style : $inactive_style;
    
    $data['is_filter_all'] = ($gender_filter === 'all');
    $data['is_filter_active'] = ($gender_filter !== 'all');

    // Calculations
    $avg_scores = ['percepcion' => 0, 'comprension' => 0, 'regulacion' => 0];
    $score_distributions = [
        'percepcion' => ['difficulty' => 0, 'adequate' => 0, 'excellent_excessive' => 0],
        'comprension' => ['difficulty' => 0, 'adequate' => 0, 'excellent' => 0],
        'regulacion' => ['difficulty' => 0, 'adequate' => 0, 'excellent' => 0]
    ];
    
    $count_filtered = 0;
    foreach ($results_completed as $result) {
        if ($gender_filter !== 'all' && $result->gender !== $gender_filter) continue;
        
        $count_filtered++;
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
        
        foreach (['percepcion', 'comprension', 'regulacion'] as $dim) {
            $interp = $interpretations[$dim];
            if (strpos($interp, get_string('perception_difficulty_feeling', 'block_tmms_24')) !== false ||
                strpos($interp, get_string('comprehension_difficulty_understanding', 'block_tmms_24')) !== false ||
                strpos($interp, get_string('regulation_difficulty_managing', 'block_tmms_24')) !== false) {
                $score_distributions[$dim]['difficulty']++;
            } elseif (strpos($interp, get_string('perception_excessive_attention', 'block_tmms_24')) !== false) {
                $score_distributions[$dim]['excellent_excessive']++;
            } elseif (strpos($interp, get_string('comprehension_great_clarity', 'block_tmms_24')) !== false ||
                    strpos($interp, get_string('regulation_great_capacity', 'block_tmms_24')) !== false) {
                $score_distributions[$dim]['excellent']++;
            } else {
                $score_distributions[$dim]['adequate']++;
            }
        }
    }
    
    if ($count_filtered > 0) {
        foreach ($avg_scores as $k => $v) $avg_scores[$k] /= $count_filtered;
    }

    // Build Dimension Stats Data for Mustache
    $dimensions_info = [
        'percepcion' => get_string('perception', 'block_tmms_24'),
        'comprension' => get_string('comprehension', 'block_tmms_24'),
        'regulacion' => get_string('regulation', 'block_tmms_24')
    ];
    
    $dim_stats_data = [];
    foreach ($dimensions_info as $dim_key => $dim_name) {
        $avg_score = $avg_scores[$dim_key];
        $distribution = $score_distributions[$dim_key];
        $progress_width = ($avg_score / 40) * 100;
        
        // Gradient Logic
        $bar_gradient_css = "";
        if ($gender_filter !== 'all') {
            $c_low = '#ff8e8e'; $c_mid = '#4cd137'; $c_high = '#48dbfb'; $c_excess = '#ff6b6b';
            $bg_size = ($progress_width > 0) ? (100 / $progress_width * 100) : 100;
            
            if ($dim_key === 'percepcion') {
                if ($gender_filter === 'M') { $p_start_green = (22/40)*100; $p_optimal = (27/40)*100; $p_end_green = (32/40)*100; } 
                else { $p_start_green = (25/40)*100; $p_optimal = (30/40)*100; $p_end_green = (35/40)*100; }
                
                $bar_gradient_css = "background-image: linear-gradient(to right, $c_low 0%, $c_low " . ($p_start_green - 10) . "%, $c_mid " . $p_optimal . "%, $c_excess " . ($p_end_green + 10) . "%, $c_excess 100%) !important; background-size: {$bg_size}% 100% !important;";
            } else {
                if ($dim_key === 'comprension') {
                     if ($gender_filter === 'M') { $t1 = 26; $t2 = 35; } else { $t1 = 24; $t2 = 34; }
                } else {
                     if ($gender_filter === 'M') { $t1 = 23; $t2 = 35; } else { $t1 = 23; $t2 = 34; }
                }
                $p_t1 = ($t1/40)*100;
                $p_t2 = ($t2/40)*100;
                $bar_gradient_css = "background-image: linear-gradient(to right, $c_low 0%, $c_low " . ($p_t1 - 5) . "%, $c_mid " . ($p_t1 + 5) . "%, $c_mid " . $p_t2 . "%, $c_high 100%) !important; background-size: {$bg_size}% 100% !important;";
            }
        }
        
        // Distribution Icons
        $distributions_data = [];
        if ($dim_key === 'percepcion') {
            $distributions_data[] = ['count' => $distribution['difficulty'], 'label' => get_string('difficulty_category', 'block_tmms_24'), 'icon' => 'fa-exclamation-circle', 'color' => '#ffc107'];
            $distributions_data[] = ['count' => $distribution['adequate'], 'label' => get_string('adequate_category', 'block_tmms_24'), 'icon' => 'fa-check-circle', 'color' => '#28a745'];
            $distributions_data[] = ['count' => $distribution['excellent_excessive'], 'label' => get_string('excessive_category', 'block_tmms_24'), 'icon' => 'fa-exclamation-triangle', 'color' => '#dc3545'];
        } else {
            $distributions_data[] = ['count' => $distribution['difficulty'], 'label' => get_string('difficulty_category', 'block_tmms_24'), 'icon' => 'fa-exclamation-circle', 'color' => '#ffc107'];
            $distributions_data[] = ['count' => $distribution['adequate'], 'label' => get_string('adequate_category', 'block_tmms_24'), 'icon' => 'fa-check-circle', 'color' => '#28a745'];
            $distributions_data[] = ['count' => $distribution['excellent'], 'label' => get_string('excellent_category', 'block_tmms_24'), 'icon' => 'fa-star', 'color' => '#007bff'];
        }

        $dim_stats_data[] = [
            'dim_key' => $dim_key,
            'dim_name' => $dim_name,
            'avg_score' => number_format($avg_score, 1),
            'progress_width' => $progress_width,
            'bar_gradient_css' => $bar_gradient_css,
            'is_filter_active' => ($gender_filter !== 'all'),
            'distributions' => $distributions_data
        ];
    }
    $data['dimension_stats'] = $dim_stats_data;
    
    // Ranges JSON Data
    $ranges_data = [
        'percepcion' => [
            'title' => get_string('perception', 'block_tmms_24'),
            'headers' => [get_string('range_low_perception', 'block_tmms_24'), get_string('range_adequate', 'block_tmms_24'), get_string('range_high_perception', 'block_tmms_24')],
            'rows' => ['M' => ['< 22', '22 - 32', '> 32'], 'F' => ['< 25', '25 - 35', '> 35']]
        ],
        'comprension' => [
            'title' => get_string('comprehension', 'block_tmms_24'),
            'headers' => [get_string('range_low_linear', 'block_tmms_24'), get_string('range_adequate', 'block_tmms_24'), get_string('range_high_linear', 'block_tmms_24')],
            'rows' => ['M' => ['< 26', '26 - 35', '> 35'], 'F' => ['< 24', '24 - 34', '> 34']]
        ],
        'regulacion' => [
            'title' => get_string('regulation', 'block_tmms_24'),
            'headers' => [get_string('range_low_linear', 'block_tmms_24'), get_string('range_adequate', 'block_tmms_24'), get_string('range_high_linear', 'block_tmms_24')],
            'rows' => ['M' => ['< 23', '23 - 35', '> 35'], 'F' => ['< 23', '23 - 34', '> 34']]
        ]
    ];
    $data['ranges_json'] = json_encode($ranges_data);
} else {
    $data['ranges_json'] = '{}';
}

// -------------------------------------------------------------------------
// PARTICIPANTS TABLE
// -------------------------------------------------------------------------
// Flatten results for table
$all_table_results = !empty($all_filtered_results) ? $all_filtered_results : array_merge($results_completed, $results_in_progress);

// Filter by search
if (!empty($search)) {
    $search = core_text::strtolower($search);
    $userids = [];
    foreach ($all_table_results as $r) $userids[] = $r->user;
    if (!empty($userids)) {
         $users = $DB->get_records_list('user', 'id', array_unique($userids));
         $all_table_results = array_filter($all_table_results, function($r) use ($users, $search) {
             if (!isset($users[$r->user])) return false;
             $u = $users[$r->user];
             return (strpos(core_text::strtolower(fullname($u)), $search) !== false) || 
                    (strpos(core_text::strtolower($u->email), $search) !== false);
         });
    }
}

$total_participants_after_search = count($all_table_results);
$total_participants_all = count($results_completed) + count($results_in_progress);

// show_table is TRUE if there are students enrolled AND (either no search is active OR search has results)
// If search is active but no results, we still show the SEARCH BAR, so we need show_table = true, 
// but we hide the table rows and show "no matches".
// Wait, typically: 
// - No students at all => show "no participants" alert.
// - Students exist but search has 0 matches => show search bar + "No matches found".

$data['show_table'] = ($total_participants_all > 0); 
$data['no_results_search'] = (!empty($search) && $total_participants_after_search === 0);
$data['has_participants'] = ($total_participants_after_search > 0); // Logic for showing the HTML table

// Config URLs for buttons
$data['download_csv_url'] = (new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'format' => 'csv']))->out(false);
$data['download_json_url'] = (new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'format' => 'json']))->out(false);

// Pagination slice
$paged_results = array_slice($all_table_results, $page * $perpage, $perpage);

// Preload users for simple rendering
$userids = [];
foreach ($paged_results as $r) $userids[] = $r->user;
$users_by_id = !empty($userids) ? $DB->get_records_list('user', 'id', array_unique($userids)) : [];

$participants_data = [];
foreach ($paged_results as $result) {
    if (!isset($users_by_id[$result->user])) continue;
    $user = $users_by_id[$result->user];
    $is_completed = ($result->is_completed == 1);
    
    // Scores
    $p_cell = '<span class="text-muted">-</span>';
    $c_cell = '<span class="text-muted">-</span>';
    $r_cell = '<span class="text-muted">-</span>';
    
    if ($is_completed) {
        $responses = [];
        for ($i = 1; $i <= 24; $i++) { $item = 'item' . $i; $responses[] = $result->$item; }
        $scores = TMMS24Facade::calculate_scores($responses);
        $p_cell = '<strong>' . $scores['percepcion'] . '</strong>';
        $c_cell = '<strong>' . $scores['comprension'] . '</strong>';
        $r_cell = '<strong>' . $scores['regulacion'] . '</strong>';
    }
    
    // Date
    $date_cell = '-';
    if ($is_completed) $date_cell = userdate($result->created_at, get_string('strftimedatetimeshort'));
    elseif (!empty($result->updated_at)) $date_cell = userdate($result->updated_at, get_string('strftimedatetimeshort'));
    
    // Answered count
    $answered = 0;
    if (!$is_completed) {
        for ($i = 1; $i <= 24; $i++) { $item = 'item' . $i; if (isset($result->$item) && $result->$item !== null) $answered++; }
    }
    
    $participants_data[] = [
        'fullname' => fullname($user),
        'email' => $user->email,
        'user_picture' => $OUTPUT->user_picture($user, ['size' => 35, 'courseid' => $courseid]),
        'is_completed' => $is_completed,
        'answered_count' => $answered,
        'perception_cell' => $p_cell,
        'comprehension_cell' => $c_cell,
        'regulation_cell' => $r_cell,
        'date_cell' => $date_cell,
        'view_results_url' => (new moodle_url('/blocks/tmms_24/student_results.php', ['courseid' => $courseid, 'userid' => $result->user]))->out(false),
        'download_csv_url' => (new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'userid' => $result->user, 'format' => 'csv']))->out(false),
        'download_json_url' => (new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'userid' => $result->user, 'format' => 'json']))->out(false),
        'can_delete' => has_capability('block/tmms_24:deletestudentdata', $context),
        'delete_url' => (new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid, 'action' => 'delete', 'id' => $result->id, 'sesskey' => sesskey()]))->out(false)
    ];
}
$data['participants'] = $participants_data;

// Pagination Bar
$baseurl = new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]);
if ($search) $baseurl->param('search', $search);
$data['pagination'] = $OUTPUT->render(new paging_bar($total_participants_after_search, $page, $perpage, $baseurl, 'page'));

// Render
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_tmms_24/teacher_view', $data);
echo $OUTPUT->footer();
