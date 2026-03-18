<?php
/**
 * Poodll Recording Monitor and Rescue Dashboard with Recycle Bin & Date Tracking.
 * Integrated into local_mymedia.
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

global $USER, $DB, $PAGE, $OUTPUT, $CFG;

require_login();
require_capability('moodle/site:config', context_system::instance());

// --- 1. HANDLE ACTIONS ---
$action = optional_param('action', '', PARAM_ALPHANUM);
$taskid = optional_param('taskid', 0, PARAM_INT);
$showrecycled = optional_param('showrecycled', 0, PARAM_INT);

$baseurl = new moodle_url('/local/mymedia/poodll_monitor.php');

if ($action && $taskid) {
    require_sesskey();
    
    if ($action == 'retry') {
        $task = \core\task\manager::get_adhoc_task($taskid);
        if ($task) {
            try {
                if ($task->get_userid()) {
                    \core\cron::setup_user($DB->get_record('user', array('id' => $task->get_userid())));
                }
                ob_start();
                $task->execute();
                \core\task\manager::adhoc_task_complete($task);
                ob_end_clean();
                redirect($baseurl, "Success! Recording processed and moved to Moodle.", 2);
            } catch (\Exception $e) {
                if (ob_get_level()) { ob_end_clean(); }
                $DB->set_field('task_adhoc', 'nextruntime', time() + 60, ['id' => $taskid]);
                redirect($baseurl, "Execution failed: " . $e->getMessage(), 5);
            }
        }
    }
    
    // SOFT DELETE
    if ($action == 'clear') {
        $task = $DB->get_record('task_adhoc', ['id' => $taskid]);
        if ($task && strpos($task->classname, '_RECYCLED') === false) {
            $newclass = $task->classname . '_RECYCLED';
            $DB->set_field('task_adhoc', 'classname', $newclass, ['id' => $taskid]);
            redirect($baseurl, "Task moved to Recycle Bin.", 2);
        }
    }

    // RESTORE
    if ($action == 'restore') {
        $task = $DB->get_record('task_adhoc', ['id' => $taskid]);
        if ($task && strpos($task->classname, '_RECYCLED') !== false) {
            $newclass = str_replace('_RECYCLED', '', $task->classname);
            $DB->set_field('task_adhoc', 'classname', $newclass, ['id' => $taskid]);
            redirect($baseurl->params(['showrecycled' => 1]), "Task restored to active queue.", 2);
        }
    }
}

// --- 2. PAGE SETUP ---
$header = "Poodll Recording Monitor";
$PAGE->set_url($baseurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();

// --- 3. TABS ---
$active_url = new moodle_url($baseurl, ['showrecycled' => 0]);
$recycled_url = new moodle_url($baseurl, ['showrecycled' => 1]);

echo '<ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link '.($showrecycled ? '' : 'active').'" href="'.$active_url.'">Active Queue</a></li>
        <li class="nav-item"><a class="nav-link '.($showrecycled ? 'active' : '').'" href="'.$recycled_url.'">Recycle Bin</a></li>
      </ul>';

// --- 4. DATABASE QUERY ---
$classfilter = $showrecycled ? "LIKE '%_RECYCLED'" : "NOT LIKE '%_RECYCLED'";
$sql = "SELECT * FROM {task_adhoc} 
        WHERE classname LIKE '%filter_poodll%task%adhoc_s3_move%' 
        AND classname $classfilter
        ORDER BY timecreated DESC";

$tasks = $DB->get_records_sql($sql);

echo "<h3><i class='fa fa-microphone'></i> Poodll Recording Status</h3>";

if (!$tasks) {
    echo $OUTPUT->notification("No records found in this view.", 'info');
} else {
    echo '<table class="table table-striped table-hover mt-3">
            <thead>
                <tr>
                    <th>Student Info</th>
                    <th>Status / Timeline</th>
                    <th>Recording File</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($tasks as $task) {
        $data = json_decode($task->customdata);
        $student_id = $data->filerecord->userid ?? 0;
        $student_name = ($user = $DB->get_record('user', ['id' => $student_id])) ? fullname($user) : "ID: $student_id";
        
        // Status Labels
        if ($showrecycled) {
            $status_label = '<span class="badge badge-secondary">Recycled</span>';
            $time_info = '<small class="text-muted">Archived on discovery</small>';
        } else {
            $is_failing = ($task->faildelay > 0);
            $status_label = $is_failing ? 
                '<span class="badge badge-danger">Retrying (Failed)</span>' : 
                '<span class="badge badge-info">Queued</span>';
            $time_info = '<small>Next Run: '.userdate($task->nextruntime).'</small>';
        }

        echo "<tr>
                <td>
                    <strong>$student_name</strong><br>
                    <small class='text-muted'>Recorded: ".userdate($task->timecreated)."</small>
                </td>
                <td>
                    $status_label<br>
                    $time_info
                </td>
                <td>
                    <code style='font-size:0.85em;'>".($data->filename ?? 'Unknown')."</code>
                </td>
                <td>";
        
        if ($showrecycled) {
            $restore_url = new moodle_url($baseurl, ['action' => 'restore', 'taskid' => $task->id, 'sesskey' => sesskey()]);
            echo "<a href='$restore_url' class='btn btn-sm btn-info'>Restore Task</a>";
        } else {
            $retry_url = new moodle_url($baseurl, ['action' => 'retry', 'taskid' => $task->id, 'sesskey' => sesskey()]);
            $clear_url = new moodle_url($baseurl, ['action' => 'clear', 'taskid' => $task->id, 'sesskey' => sesskey()]);
            $warn = "Move to Recycle Bin? This prevents the recording from attaching to the quiz unless you restore it later.";
            
            echo "<div class='btn-group'>
                    <a href='$retry_url' class='btn btn-sm btn-success'>Run Now</a>
                    <a href='$clear_url' class='btn btn-sm btn-outline-danger' onclick='return confirm(\"$warn\")'>Clear</a>
                  </div>";
        }
        
        echo "</td></tr>";
    }
    echo '</tbody></table>';
}

echo $OUTPUT->footer();