<?php
/**
 * Student Activity Report - cleaned, fixed module filtering & corrected time calculations
 *
 * Shows per-student course summary rows plus per-module activity rows,
 * and includes grouped course-level events (EventsInCourse).
 *  - Module-level logs only assigned when log context is CONTEXT_MODULE.
 *  - Course-level logs grouped under cmid = 0.
 *  - Active/idle time uses capped-gap algorithm (gaps > threshold treated as threshold active + remainder idle).
 *  - EventsInCourse built from event counts.
 *
 * Usage: local/mymedia/track_student_report.php?courseid=XX
 * Download CSV: local/mymedia/track_student_report.php?courseid=XX&download=csv
 */
require_once "bootstrap5.php";

require_once(__DIR__ . '/../../config.php');
require_login();

// Admin-only access
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/mymedia/track_student_report.php'));
$PAGE->set_pagelayout('report');
$PAGE->set_title('Student Activity Report');
//$PAGE->set_heading('Student Activity Report');

//echo $OUTPUT->header();


/**
 * Format event name (strip namespace/backslashes).
 * @param string $eventname full event name
 * @return string basename-like event name
 */
function format_event_name(string $eventname): string {
    return basename(str_replace('\\', '/', $eventname));
}

/**
 * Safe get course module name from course_modules id (returns empty if not found).
 * @param int $cmid
 * @global moodle_database $DB
 * @return string
 */
function get_modulename_by_cmid(int $cmid): string {
    global $DB;
    if ($cmid <= 0) {
        return '';
    }
    $cmrec = $DB->get_record('course_modules', ['id' => $cmid], '*', IGNORE_MISSING);
    if (!$cmrec) {
        return '';
    }
    $modrec = $DB->get_record('modules', ['id' => $cmrec->module], '*', IGNORE_MISSING);
    return $modrec ? $modrec->name : '';
}

/**
 * Given a sorted array of unix timestamps, compute active and idle seconds using capped gap algorithm.
 *
 * Algorithm:
 *   - For each gap between consecutive timestamps:
 *       if gap <= threshold:
 *           active += gap
 *       else:
 *           active += threshold
 *           idle += gap - threshold
 *
 * @param int[] $times unsorted or sorted list of timestamps (ints)
 * @param int $threshold seconds to consider as maximum continuous active time for a gap (default 1800s = 30min)
 * @return array [$active_seconds, $idle_seconds, $first_ts_or_null, $last_ts_or_null]
 */
function compute_active_idle_from_times(array $times, int $threshold = 1800): array {
    if (empty($times)) {
        return [0, 0, null, null];
    }
    sort($times, SORT_NUMERIC);
    $previous = null;
    $active_seconds = 0;
    $idle_seconds = 0;
    foreach ($times as $time) {
        if ($previous !== null) {
            $gap = $time - $previous;
            if ($gap <= $threshold) {
                $active_seconds += $gap;
            } else {
                // count threshold as active, remainder as idle
                $active_seconds += $threshold;
                $idle_seconds += ($gap - $threshold);
            }
        }
        $previous = $time;
    }
    $first = reset($times);
    $last = end($times);
    return [$active_seconds, $idle_seconds, $first, $last];
}


$selectedcourseid = optional_param('courseid', 0, PARAM_INT);
$courses = $DB->get_records('course', [], 'fullname ASC');

$options = ['' => 'Choose a course...'];
foreach ($courses as $c) {
    $options[$c->id] = format_string($c->fullname);
}

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url]);
echo html_writer::label('Select a course:', 'courseid', false);
echo html_writer::select($options, 'courseid', $selectedcourseid, [], ['class' => 'custom-select mb-2']);
echo html_writer::empty_tag('br');
echo html_writer::tag('button', 'View Report', ['type' => 'submit', 'class' => 'btn btn-primary mb-3']);
echo html_writer::end_tag('form');

if (!$selectedcourseid) {
  //  echo $OUTPUT->footer();
    exit;
}

//Fetch course, context, students

$course = get_course($selectedcourseid);
$context = context_course::instance($selectedcourseid);

// You might want to restrict which roles you fetch here; keeping original behaviour:
$students = get_enrolled_users($context);

$records = [];          
$student_summaries = []; 

//Iterate students and build data

require_once($CFG->libdir . '/completionlib.php');
$completion = new completion_info($course);

// threshold for cap in seconds (30 minutes)
$ACTIVE_GAP_THRESHOLD = 1800;

foreach ($students as $student) {
    // Fetch logs for that user/course
    $logs = $DB->get_records('logstore_standard_log', [
        'userid' => $student->id,
        'courseid' => $selectedcourseid
    ], 'timecreated ASC');

    if (!$logs) {
        // No logs for this student; skip
        continue;
    }

    //
    // Build module-level grouping and collect all times for active/idle calcs
    //
    $activities = []; 
    $all_times = [];    
    $event_counts = []; 

    //LOG PROCESSING BLOCK 
    foreach ($logs as $log) {

        // Determine context of the log (may be course, module, system, etc.)
        $ctx = context::instance_by_id($log->contextid, IGNORE_MISSING);
        if (!$ctx) {
            continue;
        }

        // Default: course-level event (cmid = 0)
        $cmid = 0;

        // Only when context is a module do we assign a module id
        if ($ctx->contextlevel == CONTEXT_MODULE) {
            $cmid = $ctx->instanceid;
        }

        // Initialize structure for this cmid
        if (!isset($activities[$cmid])) {
            $activities[$cmid] = [
                'times' => [],
                'events' => [],
                'eventnames' => []
            ];
        }

        // Add timestamp and event
        $ts = (int)$log->timecreated;
        $activities[$cmid]['times'][] = $ts;
        $activities[$cmid]['events'][] = $log->eventname;

        // Short event name for counts
        $short = format_event_name($log->eventname);
        $activities[$cmid]['eventnames'][$short] =
            ($activities[$cmid]['eventnames'][$short] ?? 0) + 1;

        // Track overall course timestamps and event counts
        $all_times[] = $ts;
        $event_counts[$short] = ($event_counts[$short] ?? 0) + 1;
    }

    // Compute activity/idle for the full logs of this student using the capped-gap algorithm
    [$activity_seconds_total, $idle_seconds_total, $first_all, $last_all] =
        compute_active_idle_from_times($all_times, $ACTIVE_GAP_THRESHOLD);

    // Fallback if first/last unexpectedly null (shouldn't be)
    if ($first_all === null || $last_all === null) {
        // nothing meaningful for this student
        continue;
    }

    $total_hours = round($activity_seconds_total / 3600, 2);
    $idle_hours = round($idle_seconds_total / 3600, 2);
    $total_span_hours = round(($last_all - $first_all) / 3600, 2);

    //
    // Build per-module activity stats using same capped-gap algorithm
    //
    $activity_stats = []; // keyed by cmid
    foreach ($activities as $cmid => $a) {
        $times = $a['times'];
        if (empty($times)) {
            continue;
        }
        [$active_sec_mod, $idle_sec_mod, $first_mod, $last_mod] =
            compute_active_idle_from_times($times, $ACTIVE_GAP_THRESHOLD);

        $event_for_module = end($a['events']) ?: '';
        $activity_stats[$cmid] = [
            'first' => $first_mod,
            'last' => $last_mod,
            'active_seconds' => $active_sec_mod,
            'idle_seconds' => $idle_sec_mod,
            'event' => $event_for_module
        ];
    }

    //
    // Course completion info for this student
    //
    try {
        $cstatus = $completion->get_data($student, true);
    } catch (Exception $e) {
        $cstatus = null;
    }

    $course_completed_at = ($cstatus && $cstatus->completionstate == COMPLETION_COMPLETE)
                           ? date('Y-m-d H:i:s', $cstatus->timemodified)
                           : 'Not completed';

    // Map of completion items keyed by coursemodule id for quicker lookup when building rows
    $cm_items = [];
    if (!empty($cstatus->items) && is_array($cstatus->items)) {
        foreach ($cstatus->items as $item) {
            $cm_items[intval($item->coursemodule)] = $item;
        }
    }

    //
    // Add per-module rows to $records using correct active_seconds
    //
    foreach ($activity_stats as $cmid => $stat) {
        $module_name = ($cmid == 0) ? 'COURSE' : get_modulename_by_cmid($cmid);

       // $activity_completion = '';
      //  $activity_completed_at = '';
     //   if (isset($cm_items[$cmid])) {
      //      $item = $cm_items[$cmid];
        //    $activity_completion = ($item->completionstate == COMPLETION_COMPLETE) ? 'Completed' : 'Not completed';
        //    $activity_completed_at = $item->timemodified ? date('Y-m-d H:i:s', $item->timemodified) : '';
     //   }

        $records[] = [
            'userid' => $student->id,
            'fullname' => $student->firstname . ' ' . $student->lastname,
            'moduleid' => $cmid ?: '',
            'modulename' => $module_name,
            // use event string from activity_stats (last event for that module)
            'event' => $stat['event'],
            'firstaccess' => date('Y-m-d H:i:s', $stat['first']),
            'lastaccess' => date('Y-m-d H:i:s', $stat['last']),
            // minutes spent = active seconds / 60
            'minutes_spent' => round($stat['active_seconds'] / 60, 2),
            'total_hours' => round($stat['active_seconds'] / 3600, 2),
            'idle_hours' => round($stat['idle_seconds'] / 3600, 2)
        ];
    }

    //
    // Build student course summary row using the totals
    //
    $total_minutes_spent = round($activity_seconds_total / 60, 2);
    $completed_activities = 0;
    // count completed items from completion items map (more accurate than searching $records)
    foreach ($cm_items as $cmid => $item) {
        if ($item->completionstate == COMPLETION_COMPLETE) {
            $completed_activities++;
        }
    }

    // Build grouped events string like "course_viewed (5), quiz_attempt_started (2)"
    $grouped_events_display = [];
    foreach ($event_counts as $ename => $count) {
        $grouped_events_display[] = "{$ename} ({$count})";
    }
    $student_event_summary = implode(', ', $grouped_events_display);

    $student_summaries[$student->id] = [
        'userid' => $student->id,
        'fullname' => $student->firstname . ' ' . $student->lastname,
        'firstaccess' => date('Y-m-d H:i:s', $first_all),
        'lastaccess' => date('Y-m-d H:i:s', $last_all),
        'minutes_spent' => $total_minutes_spent,
        'total_span_hours' => $total_span_hours,
         'total_hours' => round($activity_seconds_total / 3600, 2),
        'idle_hours' => round($idle_seconds_total / 3600, 2),
        'events' => $student_event_summary
    ];
}

//If CSV requested - output and exit


$download = optional_param('download', '', PARAM_RAW);
if ($download === 'csv') {

    $filename = clean_filename("student_activity_report_course_{$selectedcourseid}_" . date('Ymd_His') . '.csv');

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    $out = fopen('php://output', 'w');

    $headers = [
        'UserID','Full Name','ModuleID','ModuleName','Event','EventsInCourse',
        'FirstAccess','LastAccess','MinutesSpent','TotalSpanHours',
        'TotalHours','IdleHours'
    ];
    fputcsv($out, $headers);

    foreach ($student_summaries as $s) {

        fputcsv($out, [
            $s['userid'], $s['fullname'], '', 'COURSE SUMMARY', '', $s['events'],
            $s['firstaccess'], $s['lastaccess'], $s['minutes_spent'], $s['total_span_hours'],
            $s['total_hours'], $s['idle_hours']
        ]);

        foreach (array_filter($records, fn($r) => $r['userid'] == $s['userid']) as $r) {
            fputcsv($out, [
                $r['userid'], $r['fullname'], $r['moduleid'], $r['modulename'], $r['event'], '',
                $r['firstaccess'], $r['lastaccess'], $r['minutes_spent'], round($r['minutes_spent']/60,2),
                $r['total_hours'], $r['idle_hours']
            ]);
        }
    }

    fclose($out);
    exit;
}

//Render HTML table

echo html_writer::link(
    new moodle_url($PAGE->url, ['courseid'=>$selectedcourseid,'download'=>'csv']),
    'Download CSV',
    ['class'=>'btn btn-success mb-3']
);

echo html_writer::tag('style', '.summary-row{font-weight:bold;background:#e0e0e0;}');

$table = new html_table();
$table->head = [
    'UserID','Full Name','ModuleID','ModuleName','Event','EventsInCourse',
    'FirstAccess','LastAccess','MinutesSpent','TotalSpanHours','TotalHours','IdleHours'
];
$table->data = [];
$table->attributes['class'] = 'table table-striped';
foreach ($student_summaries as $s) {
    $row = [
        $s['userid'], $s['fullname'], '', 'COURSE SUMMARY', '', $s['events'],
        $s['firstaccess'], $s['lastaccess'], $s['minutes_spent'], $s['total_span_hours'],
        $s['total_hours'], $s['idle_hours']
    ];
    $tr = new html_table_row($row);
    $tr->attributes['class'] = 'summary-row';
    $table->data[] = $tr;

    foreach (array_filter($records, fn($r) => $r['userid'] == $s['userid']) as $r) {
        $table->data[] = new html_table_row([
            $r['userid'], $r['fullname'], $r['moduleid'], $r['modulename'], $r['event'], '',
            $r['firstaccess'], $r['lastaccess'], $r['minutes_spent'], round($r['minutes_spent']/60,2),
            $r['total_hours'], $r['idle_hours']
        ]);
    }
}

echo html_writer::table($table);

//echo $OUTPUT->footer();
