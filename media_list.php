<?php
require_once('../../config.php');
require_once($CFG->dirroot . "/local/kaltura/API/KalturaClient.php"); 
require_once($CFG->dirroot . '/local/kaltura/locallib.php'); 

global $CFG, $USER, $DB;

$partnerId = local_kaltura_get_config()->partner_id;
$adminSecret = local_kaltura_get_config()->adminsecret;
$username = $USER->username; 

if (empty($username)) {
    die("No user session detected.");
}

$kconf = new KalturaConfiguration($partnerId);
$kconf->serviceUrl = "https://api.ca.kaltura.com";
$client = new KalturaClient($kconf);

try {
    $ks = $client->session->start($adminSecret, $username, KalturaSessionType::ADMIN, $partnerId);
    $client->setKS($ks);

    $mediaFilter = new KalturaMediaEntryFilter();
    $mediaFilter->userIdEqual = $username; 
    $mediaPager = new KalturaFilterPager();
    $mediaPager->pageSize = 50; 

    $mediaList = $client->media->listAction($mediaFilter, $mediaPager);

    echo "<h3>Found " . $mediaList->totalCount . " videos owned by: " . $username . "</h3>";

    foreach ($mediaList->objects as $entry) {
        echo "<strong>Video: " . $entry->name . "</strong> (" . $entry->id . ")<br>";

        $catEntryFilter = new KalturaCategoryEntryFilter();
        $catEntryFilter->entryIdEqual = $entry->id;
        $catEntries = $client->categoryEntry->listAction($catEntryFilter);

        if ($catEntries->totalCount == 0) {
            echo "-- Not published in any Moodle courses.<br>";
        } else {
            foreach ($catEntries->objects as $ce) {
                $category = $client->category->get($ce->categoryId);
                $courseTitle = "Unknown Course";
                $moodleId = null;

                // --- Extract ID from "Moodle>site>channels>XXXXX>InContext" ---
                if (preg_match('/channels>(\d+)/', $category->fullName, $matches)) {
                    $moodleId = $matches[1];
                } elseif (is_numeric($category->referenceId)) {
                    $moodleId = $category->referenceId;
                }

                if ($moodleId) {
                    $courseContext = $DB->get_record('context', array('id' => $moodleId));
                    if ($courseContext && $courseContext->contextlevel == CONTEXT_COURSE) {
                        $course = $DB->get_record('course', array('id' => $courseContext->instanceid));
                        $courseTitle = $course ? $course->fullname : "Course Deleted (ID: $moodleId)";
                    } else {
                        $courseTitle = "Non-Course Context (ID: $moodleId)";
                    }
                }

                echo "&nbsp;&nbsp; - Course: <strong>" . $courseTitle . "</strong> (Kaltura ID: " . $category->id . ")<br>";
            }
        }
        echo "<hr>";
    }

} catch (Exception $e) {
    echo 'Kaltura Error: ' . $e->getMessage();
}