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



global $DB;

echo "Moodle Kaltura Discovery Tool (Deep Search Edition)\n";
echo str_repeat("=", 75) . "\n";
echo sprintf("%-20s | %-10s | %-12s | %-20s\n", "Table", "Course ID", "Record ID", "Kaltura ID");
echo str_repeat("-", 75) . "\n";

$found_count = 0;

// 1. Standard Tables with 'course' column
$standard_targets = [
    'label'           => 'intro',
    'page'            => 'content',
    'assign'          => 'intro',
    'resource'        => 'intro',
    'quiz'            => 'intro',
    'course_sections' => 'summary', // THE MAIN COURSE PAGE TOPICS
    'feedback'        => 'page_after_submit'
];

foreach ($standard_targets as $table => $field) {
    // We check if table exists first to avoid errors on different Moodle versions
    if (!$DB->get_manager()->table_exists($table)) continue;

    $sql = "SELECT id, course, $field FROM {".$table."} 
            WHERE $field LIKE '%entryid%' OR $field LIKE '%entry_id%' OR $field LIKE '%kaltura%'";
    
    $records = $DB->get_recordset_sql($sql);
    foreach ($records as $record) {
        process_content($table, $record->course, $record->id, $record->$field);
    }
    $records->close();
}

// 2. Special Case: Book Chapters (JOIN to get Course ID)
if ($DB->get_manager()->table_exists('book_chapters')) {
    $book_sql = "SELECT bc.id, b.course, bc.content 
                 FROM {book_chapters} bc 
                 JOIN {book} b ON bc.bookid = b.id 
                 WHERE bc.content LIKE '%entryid%' OR bc.content LIKE '%kaltura%'";
    $records = $DB->get_recordset_sql($book_sql);
    foreach ($records as $record) {
        process_content('book_chapters', $record->course, $record->id, $record->content);
    }
    $records->close();
}

// 3. Special Case: Quiz Questions (No direct course ID, usually linked via Category)
if ($DB->get_manager()->table_exists('question')) {
    $q_sql = "SELECT id, questiontext FROM {question} WHERE questiontext LIKE '%kaltura%'";
    $records = $DB->get_recordset_sql($q_sql);
    foreach ($records as $record) {
        process_content('question', 0, $record->id, $record->questiontext); // 0 = Global/Question Bank
    }
    $records->close();
}

function process_content($table, $courseid, $recordid, $content) {
    global $found_count;
    // Regex catches the LTI format and standard kaltura formats
    $pattern = '/(?:entryid%2F|entry_id[=\/]|entryid[=\/])([0-9a-z_]+)/i';

    if (preg_match_all($pattern, $content, $matches)) {
        foreach ($matches[1] as $kId) {
            echo sprintf("%-20s | %-10d | %-12d | %-20s\n", $table, $courseid, $recordid, $kId);
            $found_count++;
        }
    }
}

echo str_repeat("=", 75) . "\n";
echo "Deep Search Complete. Total Instances Found: $found_count\n";