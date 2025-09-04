<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/kaltura/locallib.php');
require_login();
require_sesskey();

header('Content-Type: application/json');

if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
    echo json_encode(['success' => false, 'message' => 'No video IDs provided']);
    exit;
}

$partnerId   = local_kaltura_get_config()->partner_id;
$adminSecret = local_kaltura_get_config()->adminsecret;
$serviceUrl  = "https://api.ca.kaltura.com";
require_once "../kaltura/API/KalturaClient.php";

// Init client
$kconf = new KalturaConfiguration($partnerId);
$kconf->serviceUrl = $serviceUrl;
$client = new KalturaClient($kconf);

$ks = $client->generateSessionV2(
    $adminSecret,
    $USER->username,
    KalturaSessionType::ADMIN,
    $partnerId,
    86400,
    ''
);
$client->setKs($ks);

$deleted = [];
$failed = [];

foreach ($_POST['ids'] as $entryId) {
    try {
        $client->media->delete($entryId);
        $deleted[] = $entryId;
    } catch (Exception $e) {
        $failed[$entryId] = $e->getMessage();
    }
}

echo json_encode([
    'success' => true,
    'deleted' => $deleted,
    'failed'  => $failed
]);
exit;
