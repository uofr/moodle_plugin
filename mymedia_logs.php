<?php
require_once "bootstrap5.php";
require_once('../../config.php');
# Globals
$PAGE->set_context(context_system::instance());
$cmid = get_context_info_array($PAGE->context->id);
list($context, $course, $cm) = get_context_info_array($PAGE->context->id);

require_login($course, true, $cm);

if ( (!isloggedin()) ) {
    print_error("You need to be logged in to access this page.");
    exit;
}



function logVisit($action, $visitLogFile) {
    global $CFG, $USER, $DB, $PAGE, $visitLogFile;

    
    // Get the current site URL that was viewed
   // $siteUrl = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
   $siteUrl = "My Media custom logs page";
    // Get the current date and time
    $currentTimestamp = time(); // Get the current timestamp
    $formattedTime = date('Y-m-d h:i:s A', $currentTimestamp); // Format the timestamp as a string with 12-hour format
    
    
    // Create a log entry
    $logEntry = array(
        'time' => $formattedTime,
        'fullname' => $USER->firstname . ' ' . $USER->lastname,
        'ip' => $USER->lastip,
        'user' => $USER->username,
        'action' => $action, // Use the provided action parameter
        'site_url' => $siteUrl
    );
    
    // Read existing visit data from the file
    $visitData = file_exists($visitLogFile) ? file_get_contents($visitLogFile) : '[]';
    $visitDataArray = json_decode($visitData, true);
    
    // Append the new log entry to the visit data
    $visitDataArray[] = $logEntry;
    
    // Write the updated data back to the JSON file
   return file_put_contents($visitLogFile, json_encode($visitDataArray, JSON_PRETTY_PRINT));
}

$visitLogFile = 'visits.json';
    if (!file_exists($visitLogFile)) {
        file_put_contents($visitLogFile, '[]');
    }
// Check if the user clicked the upload button
$uploadClicked = isset($_POST['upload']);

// Determine the action based on the upload button click
$action = $uploadClicked ? " " : "Viewed the page";

// Call the function to log the visit
logVisit($action,$visitLogFile);

          // Read the JSON data from the file and decode it into an array
          $visitData = file_exists($visitLogFile) ? file_get_contents($visitLogFile) : '[]';
          $visitDataArray = json_decode($visitData, true);
          // Check if the user has the admin role or the required capability
          //$hasAdminRole = has_capability('moodle/site:config', context_system::instance()); 
          //$hasLogsCapability = has_capability('local/local_mymedia:viewlogs', context_system::instance()); 
          
         
            // Display the JSON data in a table
            echo"<div id='record-count' class='float-end m-3'>Log entries: <strong>". count($visitDataArray) ."</strong></div>";
            echo "<h2>Logs</h2>";
         
                echo"<div class='row g-3'>";
                echo"<div class='col-md-4'>";
                echo "<label for='username-filter'>Filter options:</label>";
                echo "<select class='p-2 ms-2' id='username-filter'>";
                echo "<option selected>All Users</option>"; // Option for displaying all users
                
                // Create an array to store unique usernames
                $uniqueUsernames = array();
                
                // Loop to populate the dropdown with usernames
                foreach ($visitDataArray as $logEntry) {
                    $username = $logEntry['user'];
                    
                    // Check if the username is not already in the unique list
                    if (!in_array($username, $uniqueUsernames)) {
                        // Add the username to the unique list and generate the <option> element
                        array_push($uniqueUsernames, $username);
                        echo "<option value='$username'>$username</option>";
                    }
                }
                
                echo "</select>";
                echo"</div>";

                echo "<div class='col-md-4'>";
                //echo "<label for='month-filter'>Filter by month:</label>";
                echo "<select id='month-filter' class='form-control mb-2'>";
                echo "<option value='ALL MONTHS'>All Months</option>"; // Add an option for showing all months
                echo "<option value='01'>January</option>";
                echo "<option value='02'>February</option>";
                echo "<option value='03'>March</option>";
                echo "<option value='04'>April</option>";
                echo "<option value='05'>May</option>";
                echo "<option value='06'>June</option>";
                echo "<option value='07'>July</option>";
                echo "<option value='08'>August</option>";
                echo "<option value='09'>September</option>";
                echo "<option value='10'>October</option>";
                echo "<option value='11'>November</option>";
                echo "<option value='12'>December</option>";
                
               
                echo "</select>";
                echo "</div>";

                
                echo"<div class='col-md-4'>";
                 // Add the download button
                echo "<button id='download-btn' class='btn btn-secondary mb-2'>Download CSV File</button>";
                //echo "<form action='delete_logs.php' method='post'>";
                echo "<button id='delete-button' type='submit' class='btn btn-secondary mb-2 ms-2' name='delete'>Clear All Logs</button>";
              
                //echo"</form>";
                echo"</div>";
                echo"</div>";
                echo"<div class=' d-flex align-items-center justify-content-center' id='result-message2'></div>";
             
                //    echo"</div>";
               
   
                echo "<table id='logs-table' class='table table-striped table-hover'>";
                echo "<tr><th>Time</th><th>User full name</th><th>IP</th><th>User</th><th>Action</th><th>Site name</th></tr>";
                
                $latestLogs1 = array_slice($visitDataArray, -500); 
                $latestLogs = array_reverse($latestLogs1); 
                
                foreach ($latestLogs as $logEntry) {
                    $timestamp = strtotime($logEntry['time']);
                    $month = date('m', $timestamp);
                
                    echo "<tr class='' data-month='$month'>";
                    echo "<td>{$logEntry['time']}</td>";
                    echo "<td>{$logEntry['fullname']}</td>";
                    echo "<td>{$logEntry['ip']}</td>";
                    echo "<td  class='username-column'>{$logEntry['user']}</td>";
                    echo "<td>{$logEntry['action']}</td>";
                    echo "<td>{$logEntry['site_url']}</td>";
                
                    // Add a hidden column for month
                    echo "<td class='month-column' style='display:none;'>$month</td>";
                
                    echo "</tr>";
                }
                echo"</table>";
                
                ?>
                <div id="result-message" class="alert alert-primary d-flex align-items-center" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16" role="img" aria-label="Warning:">
                        <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                    </svg>
                    <div>
                     status
                    </div>
                    </div>
                   <?php 
                      ?>
            <script>
           
document.addEventListener('DOMContentLoaded', function() {

    // Get the element by its ID
    var resultMessage = document.getElementById("result-message");
resultMessage.style.visibility = "hidden";
    const usernameFilter = document.getElementById('username-filter');
    const monthFilter = document.getElementById('month-filter');
    const logsTable = document.getElementById('logs-table').getElementsByTagName('tbody')[0];
    const downloadBtn = document.getElementById('download-btn');



// Add event listeners to the select dropdowns
document.getElementById("username-filter").addEventListener("change", filterTable);
document.getElementById("month-filter").addEventListener("change", filterTable);

function filterTable() {
  var usernameSelect, monthSelect, usernameFilter, monthFilter, table, tr, usernameTd, monthTd, i, usernameValue, monthValue;

  usernameSelect = document.getElementById("username-filter");
  monthSelect = document.getElementById("month-filter");
  usernameFilter = usernameSelect.value.toUpperCase();
  monthFilter = monthSelect.value.toUpperCase();
  table = document.getElementById("logs-table");
  tr = table.getElementsByTagName("tr");

  // Initialize count
  var totalCount = 0;

  // Loop through all table rows
  for (i = 0; i < tr.length; i++) {
    usernameTd = tr[i].querySelector(".username-column"); 
    monthTd = tr[i].querySelector(".month-column");
    if (usernameTd && monthTd) {
      usernameValue = usernameTd.textContent.trim();
      monthValue = monthTd.textContent.trim();
      var usernameMatch = usernameFilter === "ALL USERS" || usernameValue.toUpperCase().indexOf(usernameFilter) > -1;
      var monthMatch = monthFilter === "ALL MONTHS" || monthValue.toUpperCase().indexOf(monthFilter) > -1;
      
      if (usernameMatch && monthMatch) {
        tr[i].style.display = "";
        totalCount++; // Increment the count for matching rows
      } else {
        tr[i].style.display = "none";
      }
    }
  }

  // Update the count element
  document.getElementById("record-count").innerHTML = "Log entries: <strong>" + totalCount + "</strong>";

}


$(document).ready(function() {
  $("#delete-button").click(function() {
    // Display a confirmation dialog
    if (confirm("Are you sure you want to clear all the logs?")) {
      // User confirmed, proceed with the deletion
      $.ajax({
        url: "clear_logs.php", // The URL to your PHP script
        type: "POST",
        success: function(response) {
          // Display the response message in the result-message div
          $("#result-message").html(response);
         // resultMessage.style.visibility = "visible";
          setTimeout(function() {
            window.location.reload();
          }, 2000); // Reload after 2 seconds (adjust as needed)
        },
        error: function() {
          $("#result-message").html("An error occurred.");
          resultMessage.style.visibility = "visible";
        }
      });
    } else {
      // User canceled the deletion
      $("#result-message").html("<h4>User did not continue to clear the logs.</h4>");
      //resultMessage.style.visibility = "visible";
      setTimeout(function() {
            window.location.reload();
          }, 2000); // Reload after 2 seconds (adjust as needed)
    }
  });
});




document.getElementById("download-btn").addEventListener("click", downloadCSV);

function downloadCSV() {
  // Get the table and rows
  const table = document.getElementById("logs-table");
  const rows = table.querySelectorAll("tr");
  
  // Prepare the CSV content
  let csvContent = "Time,User full name,IP,User,Action,Site name\n"; // Add your column headers here

  for (let i = 1; i < rows.length; i++) { // Start from 1 to skip the header row
    const cells = rows[i].querySelectorAll("td");
    if (cells.length === 7) { 
      const time = cells[0].textContent.trim();
      const fullName = cells[1].textContent.trim();
      const ip = cells[2].textContent.trim();
      const user = cells[3].textContent.trim();
      const action = cells[4].textContent.trim();
      const siteName = cells[5].textContent.trim();
      csvContent += `${time},${fullName},${ip},${user},${action},${siteName}\n`;
    }
  }

  // Create a Blob containing the CSV data
  const blob = new Blob([csvContent], { type: "text/csv" });

  // Create a download link and trigger the download
  const link = document.createElement("a");
  link.href = window.URL.createObjectURL(blob);
  link.download = "Logs_data.csv";
  link.style.display = "none";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

});

        </script>
         
        
        