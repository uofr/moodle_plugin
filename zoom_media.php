<?php
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

global $USER, $DB, $PAGE, $OUTPUT;

require_login();
$context = context_system::instance(); // Or your specific context
require_capability('local/mymedia:view', $context);


$header = get_string('zoom_media_title', 'local_mymedia');
$PAGE->set_url('/local/mymedia/zoom_media.php');
$PAGE->set_context($context);
$PAGE->set_title($header);
$PAGE->set_heading($header);

echo $OUTPUT->header();


$is_localhost = ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['HTTP_HOST'] === 'localhost');

if ($is_localhost) {
    echo '<div style="background:#f4f4f4; height:600px; display:flex; align-items:center; justify-content:center; border:2px dashed #ccc;">';
    echo '<h3>Zoom LTI Placeholder (Localhost Mode)</h3>';
    echo '</div>';
} else {

   
$zoomurl = 'https://applications.zoom.us/lti/advantage';


$select = $DB->sql_compare_text('baseurl') . " = " . $DB->sql_compare_text(':url');


$zoom_tool = $DB->get_record_select('lti_types', $select, ['url' => $zoomurl], '*', IGNORE_MULTIPLE);

if ($zoom_tool) {

    $launchurl = new moodle_url('/mod/lti/launch.php', ['id' => $zoom_tool->id, 'container' => 'embed']);
    $attr = [
        'id' => 'contentframe',
        'name' => 'contentframe',
        'src' => $launchurl->out(false),
        'width' => '100%',
        'height' => '800px',
        'allow' => 'autoplay *; fullscreen *; encrypted-media *; camera *; microphone *;'
    ];
    echo html_writer::tag('iframe', '', $attr);
} else {
    echo $OUTPUT->notification("Zoom LTI Tool not found on this server.", 'error');
}
}

echo $OUTPUT->footer();