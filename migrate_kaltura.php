<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Migrate Kaltura Videos for a Specific Course
 *
 * This script allows Moodle administrators to manage and migrate Kaltura video entries
 * associated with a specific course. It provides a form to input a course ID and
 * migrate the Kaltura videos by updating their entry IDs.
 *
 * @package    local_mymedia
 * @author     Joel Dapiawen December 11, 2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once "bootstrap5.php";
require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');

// Ensure the user has permission to access this page
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Get course ID from the form submission or query parameter
$courseid = optional_param('courseid', null, PARAM_INT);
$newentryid = optional_param('newentryid', null, PARAM_TEXT);
$kalvidresid = optional_param('kalvidresid', null, PARAM_INT);

// Page setup
$url = new moodle_url('/local/mymedia/migrate_kaltura.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Migrate Kaltura Videos');
$PAGE->set_heading('Migrate Kaltura Videos');
global $OUTPUT;

class courseid_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        
     
     // Fix label wrapping with custom styles
     $mform->addElement('html', '<style>
     label[for="id_courseid"] {
         white-space: nowrap;
         display: inline-block;
     }
    </style>');

        // Add the Course ID field
        $mform->addElement('html', '<div class="d-flex align-items-center mb-2 mt-2">');
            
        $mform->addElement('text', 'courseid', 'Course ID', ['class' => 'me-2']);
        $mform->setType('courseid', PARAM_TEXT);

        // Add the Submit button
        $mform->addElement('submit', 'submitbutton', 'Search');
        $mform->addElement('html', '</div>'); 
        
    }
   
}


$form = new courseid_form();


if ($form->is_submitted() && $form->is_validated()) {
    $data = $form->get_data();
    $courseid = $data->courseid;
   

}


if ($kalvidresid && $newentryid) {
    // Update the entryid in mdl_kalvidres
    $record = $DB->get_record('kalvidres', ['id' => $kalvidresid]);
    if ($record) {
        $record->entry_id = $newentryid; // Set the new entry_id
        $DB->update_record('kalvidres', $record); // Update the record
       // echo $OUTPUT->notification('Video migrated successfully.', 'notifysuccess');
    } else {
        echo $OUTPUT->notification('Error: Video not found.', 'notifyerror');
    }
    // Redirect to avoid resubmission on refresh
   // redirect($url, 'Migration successful', 3);
}

$form->display();

require_once "mapping_data.php";
$old_entryids = [];
$new_entryids = [];

// Parse the mapping data to extract old and new IDs
$mapping_pairs = explode(';', $mapping_data);
foreach ($mapping_pairs as $pair) {
    if (strpos($pair, ',') !== false) {
        list($new_id, $old_id) = explode(',', $pair); // Left is new, right is old
        $old_entryids[] = trim($old_id);
        $new_entryids[] = trim($new_id);
    }
}

if ($courseid == null) {
    error_log($courseid);
    return;
} elseif (!is_numeric($courseid)) {
    
    echo '<div class="alert alert-warning" role="alert">';
    echo 'Please provide a valid numeric Course ID.';
    echo '</div>';
    return;
} else {
 
try {
    // Attempt to fetch the course
    $course = get_course($courseid);

       // Check if the course exists
       if ($course) {
        echo html_writer::tag('h4', "Kaltura Videos for Course: " . $course->fullname, ['class' => 'mt-4']);
    } else {
        // Course not found
        error_log('Error: Course ID ' . $courseid . ' not found.');
        echo '<div class="alert alert-danger" role="alert">';
        echo 'Error: The course with the provided ID does not exist. Please check the course ID and try again.';
        echo '</div>';
        return; 
    }

    
} catch (Exception $e) {
 
        error_log('Error: Course ID ' . $courseid . ' not found.');
        echo '<div class="alert alert-danger" role="alert">';
        echo 'The course with the specified ID could not be found. Please verify the ID and try again.';
        echo '</div>';

    // log the actual error message for debugging
    debugging('Error occurred: ' . $e->getMessage(), DEBUG_DEVELOPER);
}

// Fetch videos from the database
$videos = $DB->get_records_sql("
    SELECT kr.id, kr.name, kr.entry_id
    FROM {kalvidres} kr
    JOIN {course_modules} cm ON cm.instance = kr.id
    JOIN {modules} m ON m.id = cm.module
    WHERE m.name = 'kalvidres' AND cm.course = ?
", [$courseid]);

if (!$videos) {
    echo '<div class="alert alert-info" role="alert">';
    echo 'No Kaltura videos found in this course.';
    echo '</div>';
    return;
}

// Generate the video table
echo html_writer::start_tag('table', ['class' => 'table table-striped table-bordered table-hover mt-2']);
echo html_writer::start_tag('thead');
$headers = ['Video Name', 'New Entry ID', 'Current Entry ID', 'Status', 'Action'];
foreach ($headers as $header) {
    echo html_writer::tag('th', $header);
}
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($videos as $video) {
    $is_old = in_array($video->entry_id, $old_entryids);
    $is_new = in_array($video->entry_id, $new_entryids);
    $button_disabled = !$is_old;
    $new_id = $is_old ? $new_entryids[array_search($video->entry_id, $old_entryids)] : '';

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $video->name);
    echo html_writer::tag('td', $new_id ?: 'N/A', ['style' => $new_id ? 'color: green;' : 'color: gray;']);
    echo html_writer::tag('td', $video->entry_id);
    echo html_writer::tag('td', $is_old ? 'Legacy ID' : ($is_new ? 'Migrated' : 'Not included for migration'), [
        'class' => $is_old ? 'legacy-id' : ($is_new ? 'mapped-id' : 'unmapped-id'),
        'style' => $is_old ? 'color: red;' : ($is_new ? 'color: green;' : 'color: gray;'),
    ]);
    echo html_writer::start_tag('td');
    echo html_writer::start_tag('form', [
        'id' => "form_$video->id",
        'method' => 'post',
        'action' => $url,
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'kalvidresid',
        'value' => $video->id,
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'courseid',
        'value' => $courseid,
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'newentryid',
        'value' => $new_id,
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => 'Migrate',
        'disabled' => $button_disabled ? 'disabled' : null,
        'class' => 'btn btn-success',
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('td');
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');


}