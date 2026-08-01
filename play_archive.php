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
 * Video player page for archived Kaltura media entries.
 *
 * @package    local_mymedia
 * @copyright  Joel Dapiawen, 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/mymedia:view', $context);

$id = required_param('id', PARAM_INT);

global $DB, $USER, $CFG, $PAGE, $OUTPUT;

$record = $DB->get_record('local_kaltura_archive', ['id' => $id], '*', MUST_EXIST);

if ($record->owner_id !== $USER->username && $record->owner_id !== $USER->email && !has_capability('moodle/site:config', $context)) {
    throw new moodle_exception('nopermissiontoviewpage');
}

$title = !empty($record->entry_name) ? $record->entry_name : get_string('untitled', 'local_mymedia');
$url   = new moodle_url('/local/mymedia/play_archive.php', ['id' => $id]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$PAGE->navbar->add(get_string('zoom_media', 'local_mymedia'), new moodle_url('/local/mymedia/zoommedia.php', ['tab' => 'kaltura']));
$PAGE->navbar->add($title);

echo $OUTPUT->header();

$videourl = new moodle_url('/local/mymedia/file.php', [
    'entryid' => $record->entry_id,
    'type'    => 'video'
]);

$thumburl = '';
if ((int)$record->thumb_status === 1) {
    $thumburl = new moodle_url('/local/mymedia/file.php', [
        'entryid' => $record->entry_id,
        'type'    => 'thumb'
    ]);
}

$hascaptions = ((int)$record->caption_status === 1 && (int)$record->caption_count > 0);
$captionurl  = '';
if ($hascaptions) {
    $captionurl = new moodle_url('/local/mymedia/file.php', [
        'entryid' => $record->entry_id,
        'type'    => 'caption'
    ]);
}
?>
<div class="container-fluid my-3">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            
            <div class="card shadow-sm border-0 bg-dark mb-4">
                <div class="card-body p-0">
                    <video id="kaltura-archive-player" 
                           class="w-100" 
                           controls 
                           preload="metadata"
                           <?php if (!empty($thumburl)) { echo 'poster="' . $thumburl->out() . '"'; } ?>
                           style="max-height: 70vh; background: #000;">
                        <source src="<?php echo $videourl->out(); ?>" type="video/mp4">
                        <?php if ($hascaptions): ?>
                            <track label="English" kind="subtitles" srclang="en" src="<?php echo $captionurl->out(); ?>" default>
                        <?php endif; ?>
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div>
                            <h3 class="card-title font-weight-bold mb-1"><?php echo s($title); ?></h3>
                            <p class="text-muted mb-2 font-monospace small">
                                Entry ID: <?php echo s($record->entry_id); ?>
                            </p>
                        </div>
                        <div class="mt-2 mt-sm-0">
                            <a href="<?php echo new moodle_url('/local/mymedia/file.php', ['entryid' => $record->entry_id, 'type' => 'video', 'forcedownload' => 1]); ?>" 
                               class="btn btn-outline-dark">
                                <i class="fa fa-download mr-1"></i> Download MP4
                            </a>
                        </div>
                    </div>

                    <hr>

                    <div class="row text-muted small">
                        <div class="col-sm-4 mb-2">
                            <strong>Created:</strong><br>
                            <?php echo userdate($record->timecreated, get_string('strftimedatetimeshort', 'langconfig')); ?>
                        </div>
                        <div class="col-sm-4 mb-2">
                            <strong>Last Modified:</strong><br>
                            <?php echo userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig')); ?>
                        </div>
                        <div class="col-sm-4 mb-2">
                            <strong>File Size:</strong><br>
                            <?php echo !empty($record->filesize) ? display_size((int)$record->filesize) : 'Unknown'; ?>
                        </div>
                    </div>

                    <?php if (!empty($record->tags)): ?>
                        <div class="mt-3">
                            <strong>Tags:</strong> 
                            <?php
                            $tags = explode(',', $record->tags);
                            foreach ($tags as $tag) {
                                $cleantag = trim($tag);
                                if ($cleantag !== '') {
                                    echo \html_writer::span(s($cleantag), 'badge badge-light border mr-1');
                                }
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>
<?php

echo $OUTPUT->footer();