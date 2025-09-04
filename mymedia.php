<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_user::instance($USER->id);
require_capability('local/mymedia:view', $context);

$systemcontext = context_system::instance();

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/local/mymedia/mymedia.php'));
$PAGE->set_pagetype('local-mymedia-index');
$PAGE->set_pagelayout('report');

$header = fullname($USER) . ': ' . get_string('heading_mymedia', 'local_mymedia');
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->requires->css('/local/mymedia/mymedia.css');

$pageclass = 'kaltura-mediagallery-body';
$PAGE->add_body_class($pageclass);

echo $OUTPUT->header();

// ------------------- Navigation -------------------
$navitems = [];

// Always available
$navitems[] = [
    'label' => get_string('troubleuploading', 'local_mymedia', 'Trouble uploading?'),
    'url'   => new moodle_url('/local/mymedia/simple_uploader.php')
];
$navitems[] = [
    'label' => get_string('urlsforh5p', 'local_mymedia', 'URLs for H5P'),
    'url'   => new moodle_url('/local/mymedia/get_h5p_link.php')
];

// for URcommunity 
if (!in_array($SITE->shortname, ['CCE Community', 'UR Community'])) {
    $navitems[] = [
        'label' => get_string('importzoomrecordings', 'local_mymedia', 'Import Zoom Recordings'),
        'url'   => new moodle_url('/local/mymedia/get_zoom_url.php')
    ];
}

// Admin only
if (has_capability('moodle/site:config', $systemcontext)) {
    $navitems[] = [
        'label' => get_string('logs', 'local_mymedia', 'Logs'),
        'url'   => new moodle_url('/local/mymedia/mymedia_logs.php')
    ];
    $navitems[] = [
        'label' => get_string('migratekaltura', 'local_mymedia', 'Migrate Kaltura Videos'),
        'url'   => new moodle_url('/local/mymedia/migrate_kaltura.php')
    ];
    $navitems[] = [
        'label' => get_string('kalturareport', 'local_mymedia', 'Kaltura Usage Report'),
        'url'   => new moodle_url('/local/mymedia/managekaltura.php')
    ];
}

// Render nav
echo html_writer::start_div('secondary-navigation d-print-none');
echo html_writer::start_tag('nav', ['class' => 'moremenu navigation observed']);
echo html_writer::start_tag('ul', [
    'role' => 'menubar',
    'id'   => 'moremenu',
    'class'=> 'nav more-nav nav-tabs'
]);

foreach ($navitems as $item) {
    echo html_writer::tag('li',
        html_writer::link(
            $item['url'],
            $item['label'],
            ['class' => 'nav-link nav_border_bottom', 'target' => 'contentframe']
        ),
        ['class' => 'nav-item']
    );
}

echo html_writer::end_tag('ul');
echo html_writer::end_tag('nav');
echo html_writer::end_div();

// ------------------- Iframe -------------------
$attr = [
    'id' => 'contentframe',
    'name' => 'contentframe',
    'height' => '600px',
    'width' => '100%',
    'allowfullscreen' => 'true',
    'src' => new moodle_url('/local/mymedia/lti_launch.php'),
    'allow' => 'autoplay *; fullscreen *; encrypted-media *; camera *; microphone *;'
];
echo html_writer::tag('iframe', '', $attr);


$params = [
    'bodyclass' => $pageclass,
    'lastheight' => null,
    'padding' => 15
];
$PAGE->requires->yui_module('moodle-local_kaltura-lticontainer', 'M.local_kaltura.init', [$params], null, true);
$PAGE->requires->js(new moodle_url('/local/kaltura/js/kea_resize.js'));


echo $OUTPUT->footer();
