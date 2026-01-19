<?php
/**
 * Export - TMMS-24 Block
 *
 * @package    block_tmms_24
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/block_tmms_24.php');

$courseid = required_param('cid', PARAM_INT);
$format = required_param('format', PARAM_ALPHA);

if ($courseid == SITEID || !$courseid) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);

if (!has_capability('block/tmms_24:viewallresults', $context)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

$userid = optional_param('userid', 0, PARAM_INT);

// Get enrolled students in this course
$enrolled_users = get_enrolled_users($context, 'block/tmms_24:taketest', 0, 'u.id', null, 0, 0, true);
$enrolled_ids = array();
foreach ($enrolled_users as $user) {
    $candidateid = (int)$user->id;
    // Defensive: exclude teachers/managers/siteadmins from exports.
    if (has_capability('block/tmms_24:viewallresults', $context, $candidateid)) {
        continue;
    }
    $enrolled_ids[] = $candidateid;
}

// Fetch entries for enrolled students
$all_entries = array();
if ($userid > 0) {
    if (empty($enrolled_ids) || !in_array($userid, $enrolled_ids, true)) {
        redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
    }
    // Export for specific user
    $all_entries = $DB->get_records('tmms_24', ['user' => $userid], 'created_at DESC');
} else if (!empty($enrolled_ids)) {
    // Export for all enrolled students
    list($insql, $params) = $DB->get_in_or_equal($enrolled_ids, SQL_PARAMS_NAMED);
    $all_entries = $DB->get_records_select('tmms_24', "user $insql AND is_completed = 1", $params, 'created_at DESC');
}

if (empty($all_entries)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]), get_string('no_results_yet', 'block_tmms_24'), null, 'error');
}

$facade = new TMMS24Facade();

// Build CSV/JSON rows
$export_rows = [];

// Prefetch users to avoid N+1 when exporting large cohorts.
$userids = array();
foreach ($all_entries as $entry) {
    $userids[] = (int)$entry->user;
}
$userids = array_values(array_unique($userids));
$users_by_id = !empty($userids)
    ? $DB->get_records_list('user', 'id', $userids, '', 'id, firstname, lastname, email, idnumber')
    : array();

foreach ($all_entries as $entry) {
    $user = isset($users_by_id[$entry->user]) ? $users_by_id[$entry->user] : null;
    if (!$user) {
        continue;
    }

    $responses = [];
    for ($i = 1; $i <= 24; $i++) {
        $field = 'item' . $i;
        $responses[] = isset($entry->$field) ? $entry->$field : '';
    }

    $scores = $facade->calculate_scores($responses);
    $interpretations = $facade->get_all_interpretations($scores, $entry->gender);

    $row = [
        'student_id' => $user->idnumber,
        'name' => $user->firstname . ' ' . $user->lastname,
        'email' => $user->email,
        'age' => $entry->age,
        'gender' => $entry->gender
    ];

    // Responses
    for ($i = 1; $i <= 24; $i++) {
        $row['item_' . $i] = $responses[$i - 1];
    }

    // Scores (support both English and Spanish keys from facade)
    $row['perception_score'] = isset($scores['perception']) ? $scores['perception'] : (isset($scores['percepcion']) ? $scores['percepcion'] : '');
    $row['comprehension_score'] = isset($scores['comprehension']) ? $scores['comprehension'] : (isset($scores['comprension']) ? $scores['comprension'] : '');
    $row['regulation_score'] = isset($scores['regulation']) ? $scores['regulation'] : (isset($scores['regulacion']) ? $scores['regulacion'] : '');

    // Interpretations - support both key variants
    $row['perception_interpretation'] = isset($interpretations['perception']) ? $interpretations['perception'] : (isset($interpretations['percepcion']) ? $interpretations['percepcion'] : '');
    $row['comprehension_interpretation'] = isset($interpretations['comprehension']) ? $interpretations['comprehension'] : (isset($interpretations['comprension']) ? $interpretations['comprension'] : '');
    $row['regulation_interpretation'] = isset($interpretations['regulation']) ? $interpretations['regulation'] : (isset($interpretations['regulacion']) ? $interpretations['regulacion'] : '');

    // Append human-readable last-action date as the last column.
    if (isset($entry->updated_at) && $entry->updated_at) {
        $lastaction = $entry->updated_at;
    } else if (isset($entry->last_action) && $entry->last_action) {
        $lastaction = $entry->last_action;
    } else {
        $lastaction = $entry->created_at;
    }
    $row['last_action_date'] = date('Y-m-d H:i:s', $lastaction);

    $export_rows[] = $row;
}

// Generar nombre elegante del archivo usando string de idioma
$course_name = preg_replace('/[^a-z0-9]/i', '_', strtolower($course->shortname));
$date_str = date('Y-m-d');
if ($userid > 0) {
    $user = $DB->get_record('user', ['id' => $userid], 'firstname, lastname');
    $user_name = preg_replace('/[^a-z0-9]/i', '_', strtolower(fullname($user)));
    $filename = get_string('export_filename', 'block_tmms_24') . '_' . $user_name . '_' . $date_str . '.' . $format;
} else {
    $filename = get_string('export_filename', 'block_tmms_24') . '_' . $course_name . '_' . $date_str . '.' . $format;
}

// Mapping of internal keys to language keys in block_tmms_24
$labelmap = [
    'student_id' => 'export_student_id',
    'name' => 'export_name',
    'email' => 'export_email',
    'age' => 'export_age',
    'gender' => 'export_gender',
    'perception_score' => 'export_perception_score',
    'comprehension_score' => 'export_comprehension_score',
    'regulation_score' => 'export_regulation_score',
    'perception_interpretation' => 'export_perception_interpretation',
    'comprehension_interpretation' => 'export_comprehension_interpretation',
    'regulation_interpretation' => 'export_regulation_interpretation',
    'last_actionlast_action_date_date' => 'export_last_action_date'
];

$humanize = function($k) {
    // turn item_1 -> Item 1, student_id -> Student Id
    $k = str_replace('_', ' ', $k);
    $k = preg_replace('/\s+/', ' ', $k);
    $k = trim($k);
    $k = mb_convert_case($k, MB_CASE_TITLE, "UTF-8");
    return $k;
};

$get_label = function($k) use ($labelmap, $humanize) {
    if (preg_match('/^item_(\d+)$/', $k, $m)) {
        $i = (int)$m[1];
        $label = get_string('export_item', 'block_tmms_24', $i);
        if ($label === 'export_item') {
            $label = $humanize($k);
        }
    } else if (isset($labelmap[$k])) {
        $label = get_string($labelmap[$k], 'block_tmms_24');
        if ($label === $labelmap[$k]) {
            $label = $humanize($k);
        }
    } else {
        // Default: try to use a generic export string key, else humanize
        $trykey = 'export_' . $k;
        $label = get_string($trykey, 'block_tmms_24');
        if ($label === $trykey) {
            $label = $humanize($k);
        }
    }
    return $label;
};

if ($format === 'csv') {
    // Send headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');
    // BOM for UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Build header row from keys of first row, but present friendly names
    $keys = array_keys($export_rows[0]);
    $headers = array();

    foreach ($keys as $k) {
        $headers[] = $get_label($k);
    }

    fputcsv($output, $headers);

    // Write rows (use original keys order in $keys)
    foreach ($export_rows as $r) {
        $line = [];
        foreach ($keys as $key) {
            $line[] = isset($r[$key]) ? $r[$key] : '';
        }
        fputcsv($output, $line);
    }

    fclose($output);
    exit;

} elseif ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Localize JSON keys to the current language (fallback to humanized keys).
    $keys = array_keys($export_rows[0]);

    $keylabels = [];
    foreach ($keys as $k) {
        $keylabels[$k] = $get_label($k);
    }

    $localized = [];
    foreach ($export_rows as $r) {
        $lr = [];
        foreach ($keys as $k) {
            $value = isset($r[$k]) ? $r[$k] : null;

            // For item_N and age, output numeric types (int/float) or null.
            if ($value === '' || $value === null) {
                $out = null;
            } else if ($k === 'age' || preg_match('/^item_\d+$/', $k)) {
                if (is_numeric($value)) {
                    $s = (string)$value;
                    $out = (strpos($s, '.') !== false) ? (float)$value : (int)$value;
                } else {
                    // Non-numeric fallback: keep original value
                    $out = $value;
                }
            } else {
                $out = $value;
            }

            $lr[$keylabels[$k]] = $out;
        }
        $localized[] = $lr;
    }

    echo json_encode($localized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;

} else {
    redirect(new moodle_url('/blocks/tmms_24/view.php', ['cid' => $courseid, 'view_results' => 1]), get_string('invalid_export_format', 'block_tmms_24'), null, 'error');
}

?>
