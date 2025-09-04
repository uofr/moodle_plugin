<?php
require_once "bootstrap5.php";
require_once('../../config.php'); 
require_once($CFG->dirroot . '/local/kaltura/locallib.php'); 
require_once($CFG->libdir . '/formslib.php');

require_login();
global $USER, $PAGE, $OUTPUT;

// Page setup
$PAGE->set_url(new moodle_url('/local/mymedia/user_videos.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title("My Uploaded Videos");
$PAGE->set_heading("My Uploaded Videos");

// ---- Moodle form class ----
class select_user_form extends moodleform {
    function definition() {
        $mform = $this->_form;

        // Username text field
        $mform->addElement('text', 'username', get_string('username'));
        $mform->setType('username', PARAM_USERNAME);
        $mform->setDefault('username', $this->_customdata['defaultuser']);

        $this->add_action_buttons(false, get_string('showvideos', 'local_mymedia'));
    }
}

// ---- Show header and form ----
//echo $OUTPUT->header();
//echo $OUTPUT->heading("Select User Videos");

$mform = new select_user_form(null, ['defaultuser' => $USER->username]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/my'));
} else if ($data = $mform->get_data()) {
    $selecteduser = trim($data->username);

    // ---- Loading spinner ----
    echo '<div id="loading-message" style="padding:40px; text-align:center;">
            <div class="spinner-border text-success" role="status" style="width:3rem; height:3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div style="margin-top:15px; font-weight:bold;">Loading videos for ' . s($selecteduser) . '...</div>
            <div id="progress-message" style="margin-top:10px; font-style:italic;"></div>
          </div>
          <div id="video-container" style="display:none;"></div>';

    @ob_flush();
    @flush();

    // ---- Init Kaltura client ----
    $partnerId   = local_kaltura_get_config()->partner_id;
    $adminSecret = local_kaltura_get_config()->adminsecret;
    $serviceUrl  = "https://api.ca.kaltura.com";
    require_once "../kaltura/API/KalturaClient.php";

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

    // ---- Fetch entries ----
    $filter = new KalturaMediaEntryFilter();
// Include all relevant statuses
$filter->statusIn = implode(',', [
    -1, // ERROR
    0,  // QUEUED
    2,  // READY
   // 3,  // DELETED
   // 7,  // IMPORTING
   // 9   // EXPORTING
]);

// Optional: only videos
$filter->typeEqual = KalturaMediaType::VIDEO;
    $filter->userIdEqual = $selecteduser;

    $pager = new KalturaFilterPager();
    $pager->pageSize  = 1000;
    $pager->pageIndex = 1;

    $allEntries = [];
    do {
        $result = $client->media->listAction($filter, $pager);
        if (!empty($result->objects)) {
            $allEntries = array_merge($allEntries, $result->objects);
        }
        $pager->pageIndex++;
    } while (!empty($result->objects));

    $total = count($allEntries);
    $counter = 0;

    // ---- Build table (with delete + AJAX) ---- <th>Size (MB)</th>
    $tableHtml = '<form id="video-form">';
    $tableHtml .= '<input type="hidden" id="sesskey" value="'.sesskey().'">';
    $tableHtml .= '<div class="sticky-top d-flex gap-2 mb-2">
    <button type="button" class="btn btn-danger" onclick="bulkDelete()">Delete Selected</button>
    <button type="button" class="btn btn-success" onclick="exportCSV()">Export CSV</button>
</div>';

    $tableHtml .= '<table class="table table-bordered">';
    $tableHtml .= '<tr>
        <th><input type="checkbox" onclick="toggleAll(this)"></th>
        <th>Title</th><th>Entry ID</th>
        <th>Duration (mins)</th><th>Created At</th>
        <th>Plays</th><th>Views</th><th>Last Played</th><th>Action</th>
    </tr>';

    foreach ($allEntries as $entry) {
        $counter++;

        $created = userdate($entry->createdAt);
        $durationMins = isset($entry->duration) ? round($entry->duration / 60, 2) : 'N/A';

        $sizeMB = 'N/A';
        try {
            $flavors = $client->flavorAsset->getByEntryId($entry->id);
            $totalSize = 0;
            foreach ($flavors as $flavor) {
                if (!empty($flavor->size)) {
                    $totalSize += $flavor->size;
                }
            }
           // if ($totalSize > 0) {
           //     $sizeMB = round($totalSize / 1024 / 1024, 2) . ' MB';
         //   }
        } catch (Exception $e) {
            $sizeMB = 'N/A';
        }

        $plays = $entry->plays ?? 0;
        $views = $entry->views ?? 0;
        $lastPlayed = (!empty($entry->lastPlayedAt) && $entry->lastPlayedAt > 0) ? userdate($entry->lastPlayedAt) : 'Never played';

        $tableHtml .= '<tr id="row-'.s($entry->id).'">'
            . '<td><input type="checkbox" class="video-check" value="'.s($entry->id).'"></td>'
            . '<td>' . format_string($entry->name) . '</td>'
            . '<td>' . s($entry->id) . '</td>'
           // . '<td>' . $sizeMB . '</td>'
            . '<td>' . $durationMins . '</td>'
            . '<td>' . $created . '</td>'
            . '<td>' . $plays . '</td>'
            . '<td>' . $views . '</td>'
            . '<td>' . $lastPlayed . '</td>'
            . '<td><button type="button" class="btn btn-danger btn-sm" onclick="deleteVideos([\''.s($entry->id).'\'])">Delete</button></td>'
            . '</tr>';

        // Update progress message
        echo '<script>
            document.getElementById("progress-message").innerText = "Processing video ' . $counter . ' of ' . $total . '...";
        </script>';
        @ob_flush();
        @flush();
    }

    $tableHtml .= '</table>';
    $tableHtml .= '</form>';

    // Inject table
    echo '<script>
        document.getElementById("loading-message").style.display = "none";
        var container = document.getElementById("video-container");
        container.style.display = "block";
        container.innerHTML = ' . json_encode($tableHtml) . ';
    </script>';

    // JS (deleteVideos, bulkDelete, toggleAll)
    echo '<script>
    function toggleAll(master) {
        document.querySelectorAll(".video-check").forEach(cb => cb.checked = master.checked);
    }
    function deleteVideos(ids) {
        if (!confirm("Delete selected video(s)?")) return;
        fetch("ajax_delete.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "sesskey=" + encodeURIComponent(document.getElementById("sesskey").value) + 
                  "&" + ids.map(id => "ids[]=" + encodeURIComponent(id)).join("&")
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                data.deleted.forEach(id => {
                    let row = document.getElementById("row-" + id);
                    if (row) {
                        row.style.transition = "opacity 0.5s";
                        row.style.opacity = "0";
                        setTimeout(() => row.remove(), 500);
                    }
                });
                if (data.failed && Object.keys(data.failed).length > 0) {
                    alert("Some videos could not be deleted: " + JSON.stringify(data.failed));
                }
            } else {
                alert("Delete failed: " + data.message);
            }
        })
        .catch(err => alert("Error: " + err));
    }
    function bulkDelete2() {
        let ids = [];
        document.querySelectorAll(".video-check:checked").forEach(cb => ids.push(cb.value));
        if (ids.length === 0) {
            alert("Please select at least one video to delete.");
            return;
        }
        deleteVideos(ids);
    }
  function bulkDelete() {
    let selected = Array.from(document.querySelectorAll(".video-check:checked")).map(cb => cb.value);
    if (selected.length === 0) {
        alert("Please select at least one video.");
        return;
    }

    var modalEl = document.getElementById("deleteProgressModal");
    var modal = new bootstrap.Modal(modalEl);
    modal.show();

    let total = selected.length;
    let count = 0;

    function deleteNext() {
        if (selected.length === 0) {
            document.getElementById("delete-progress-text").innerText = "All videos deleted!";
            document.getElementById("delete-progress-bar").style.width = "100%";
            document.getElementById("delete-progress-bar").innerText = "100%";
            setTimeout(() => modal.hide(), 1500);
            return;
        }

        let entryId = selected.shift();
        fetch("ajax_delete.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "sesskey=" + encodeURIComponent(document.getElementById("sesskey").value) +
                  "&" + "ids[]=" + encodeURIComponent(entryId)
        })
        .then(res => res.json())
        .then(data => {
            count++;
            let percent = Math.round((count / total) * 100);
            document.getElementById("delete-progress-bar").style.width = percent + "%";
            document.getElementById("delete-progress-bar").innerText = percent + "%";
            document.getElementById("delete-progress-text").innerText =
                "Deleted " + count + " of " + total + " videos...";

            // Remove the row from table
            let row = document.getElementById("row-" + entryId);
            if (row) {
                row.style.transition = "opacity 0.5s";
                row.style.opacity = 0;
                setTimeout(() => row.remove(), 500);
            }

            deleteNext();
        })
        .catch(err => {
            console.error("Delete failed for " + entryId, err);
            count++;
            deleteNext();
        });
    }

    deleteNext();
}

function exportCSV() {
    var table = document.querySelector("#video-form table");
    if (!table) { alert("No table to export."); return; }

    var dq = String.fromCharCode(34); // = "
    var rows = [];
    var trs = table.querySelectorAll("tr");

    trs.forEach(function (tr, rowIndex) {
        // Use TH for header row if present
        var cells = tr.querySelectorAll(rowIndex === 0 ? "th, td" : "td");
        var out = [];

        cells.forEach(function (td, i) {
            // Skip first checkbox column and last Action column
            if (i === 0 || i === cells.length - 1) return;

            var text = (td.innerText || "").trim();

           
            text = text.split(dq).join(dq + dq);

            // Wrap field in quotes
            out.push(dq + text + dq);
        });

        rows.push(out.join(","));
    });

    var csv = rows.join("\n");

    // Add BOM so Excel opens UTF-8 correctly
    var blob = new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8;" });
    var url = URL.createObjectURL(blob);

    var a = document.createElement("a");
    a.href = url;
    a.download = "user_videos.csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}



    </script>';
} else {
    // First load: just show form
    $mform->display();
}
// ✅ After echoing the table + before footer
echo '
<!-- Progress modal -->
<div class="modal fade" id="deleteProgressModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3 text-center">
      <h5>Deleting Videos...</h5>
      <div class="progress mt-3">
        <div id="delete-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
             role="progressbar" style="width: 0%">0%</div>
      </div>
      <div id="delete-progress-text" class="mt-2">Starting...</div>
    </div>
  </div>
</div>';

//echo $OUTPUT->footer();
