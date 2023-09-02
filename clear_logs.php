<?php
$jsonFile = 'visits.json';

// Check if the file exists
if (file_exists($jsonFile)) {
    // Truncate (empty) the file
    file_put_contents($jsonFile, '');
    echo 'All the Users custom logs has been removed!.';
} else {
    echo 'visits.json file not found.';
}
?>
