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
 * AJAX endpoint to fetch paginated Kaltura Archive video cards for infinite scrolling.
 *
 * @package    local_mymedia
 * @copyright  Joel Dapiawen, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

require_login();

$page   = required_param('page', PARAM_INT);
$search = trim(optional_param('search', '', PARAM_TEXT));

global $DB, $CFG, $USER, $OUTPUT, $PAGE;

$PAGE->set_context(context_system::instance());

$perpage = 12;
$params  = ['ownerid' => $USER->username];
$where   = "owner_id = :ownerid";

if ($search !== '') {
    $searchparam = '%' . $DB->sql_like_escape($search) . '%';
    $namelike    = $DB->sql_like('entry_name', ':searchname', false, true);
    $tagslike    = $DB->sql_like('tags', ':searchtags', false, true);

    $where .= " AND ({$namelike} OR {$tagslike})";
    $params['searchname'] = $searchparam;
    $params['searchtags'] = $searchparam;
}

$countsql   = "SELECT COUNT(*) FROM {local_kaltura_archive} WHERE " . $where;
$totalcount = $DB->count_records_sql($countsql, $params);
$hasmore    = $totalcount > (($page + 1) * $perpage);

$sql = "SELECT *
          FROM {local_kaltura_archive}
         WHERE {$where}
      ORDER BY timemodified DESC";

$records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

$cards = [];

foreach ($records as $row) {
    $card = new stdClass();
    $card->id = $row->id;
    $card->entryid = $row->entry_id;
    $card->title = !empty($row->entry_name) ? $row->entry_name : get_string('untitled', 'local_mymedia');
    $card->timecreated = userdate($row->timecreated, get_string('strftimedateshort', 'langconfig'));
    $card->timemodified = userdate($row->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
    $card->filesize = !empty($row->filesize) ? display_size((int)$row->filesize) : '';

    $card->is_ready = ((int)$row->status === 1);
    $card->is_pending = ((int)$row->status === 0);
    $card->is_failed = ((int)$row->status === 2);

    //$thumbfile = $CFG->dataroot . '/zoommigration/thumbnails/' . $row->entry_id . '.jpg';
     $thumbfile = '/zoommigration/thumbnails/' . $row->entry_id . '.jpg';

    if ((int)$row->thumb_status === 1 || file_exists($thumbfile)) {
        $card->thumburl = new moodle_url('/local/mymedia/file.php', [
            'entryid' => $row->entry_id,
            'type'    => 'thumb'
        ]);
    } else {
        $card->thumburl = $OUTPUT->image_url('default_video_thumb', 'local_mymedia');
    }

    $card->has_captions = ((int)$row->caption_status === 1 && (int)$row->caption_count > 0);
    $card->caption_count = (int)$row->caption_count;

    if ($card->is_ready) {
        $card->videourl = new moodle_url('/local/mymedia/file.php', [
            'entryid' => $row->entry_id,
            'type'    => 'video'
        ]);

        if ($card->has_captions) {
            $card->captionurl = new moodle_url('/local/mymedia/file.php', [
                'entryid' => $row->entry_id,
                'type'    => 'caption'
            ]);
            $card->captiondownloadurl = new moodle_url('/local/mymedia/file.php', [
                'entryid'       => $row->entry_id,
                'type'          => 'caption',
                'forcedownload' => 1
            ]);
        }

        $card->downloadurl = new moodle_url('/local/mymedia/file.php', [
            'entryid'       => $row->entry_id,
            'type'          => 'video',
            'forcedownload' => 1
        ]);

        $card->thumbdownloadurl = new moodle_url('/local/mymedia/file.php', [
            'entryid'       => $row->entry_id,
            'type'          => 'thumb',
            'forcedownload' => 1
        ]);
    }

    $cards[] = $card;
}

$html = $OUTPUT->render_from_template('local_mymedia/kaltura_archive_cards', ['cards' => $cards]);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'html'    => $html,
    'hasmore' => $hasmore
]);
die();