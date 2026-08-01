<?php
require_once('../../config.php');

// 1. Force Moodle to show errors so you don't get a blank screen
//@error_reporting(E_ALL | E_STRICT);
//@ini_set('display_errors', '1');

// Security: Admins only
require_login();
require_capability('moodle/site:config', context_system::instance());

$id = optional_param('id', 0, PARAM_INT);

echo $OUTPUT->header();
echo "<h1>Media Transfer Status Checker</h1>";

if ($id === 0) {
    echo "<div class='alert alert-warning'>No ID provided. Please visit: <code>debug_status.php?id=178</code></div>";
    echo $OUTPUT->footer();
    exit();
}

$record = $DB->get_record('zoomvideo_transfers', ['id' => $id]);

if (!$record) {
    echo "<div class='alert alert-danger'>Record #$id not found.</div>";
    echo $OUTPUT->footer();
    exit();
}

echo "<h3>Checking status for: <strong>{$record->filename}</strong></h3>";
echo "<p>Clip ID: {$record->clip_id}</p>";

// 2. Safety Check: Does the class actually exist?
if (!class_exists('\mod_zoomvideo\api')) {
    echo "<div class='alert alert-danger'>FATAL ERROR: The class <code>\mod_zoomvideo\api</code> could not be found. Check your mod/zoomvideo/classes/api.php file namespace!</div>";
} else {
    try {
        $api = new \mod_zoomvideo\api();
        
        // 3. Debug: Print available methods if you aren't sure of the name
        // print_r(get_class_methods($api)); 
        
        $result = $api->get_single_video_status($record->clip_id);

        echo "<div style='background:#f4f4f4; padding:15px; border:1px solid #ccc;'>";
        echo "<h3>API Raw Response:</h3>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        echo "</div>";

    } catch (\Exception $e) {
        echo "<div class='alert alert-danger'>Error contacting Zoom: " . $e->getMessage() . "</div>";
    }
}

echo "<br><a href='debug_status.php?id=$id' class='btn btn-primary'>Check Again</a>";
echo $OUTPUT->footer();