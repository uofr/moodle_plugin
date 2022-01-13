<?php
// using boostrap class
require_once('../../config.php');
require_once($CFG->dirroot . '/local/kaltura/locallib.php'); 

# Globals
global $CFG, $USER, $DB, $PAGE;

$PAGE->set_url('/local/mymedia/get_h5p_link');
$PAGE->set_context(context_system::instance());

# Check security - special privileges are required to use this script
$currentcontext = context_system::instance();
$username = $USER->username;
$site = get_site();

list($context, $course, $cm) = get_context_info_array($PAGE->context->id);

require_login($course, true, $cm);

if ( (!isloggedin()) ) {
    print_error("You need to be logged in to access this page.");
    exit;
}

// Your Kaltura partner credentials
$partnerId = local_kaltura_get_config()->partner_id;
$adminSecret = local_kaltura_get_config()->adminsecret;

require_once "../kaltura/API/KalturaClient.php";

$kconf = new KalturaConfiguration($partnerId);

$kconf->serviceUrl = "https://api.ca.kaltura.com";
$kclient = new KalturaClient($kconf);

$kafuri = local_kaltura_get_config()->partner_id;
//error_log('Kaltura Configuration Data: ' . print_r($kafuri, true));
$ksession = $kclient->session->start($adminSecret, $username, KalturaSessionType::ADMIN, $partnerId, null, 'disableentitlement');
if (!$ksession) {
    error_log("Failed to establish Kaltura session.");
    die("Error establishing Kaltura session.");
}

?>

<div class="container">
<form method="post" action="get_h5p_link.php">
<div class="mt-2 mb-2 input-group">
<label for="entryidlabel" class="col-form-label p-2">Entry ID</label>
<input type="text" class="form-control" id="entryidlabel" name="entryId" placeholder="entryId" value="<?php isset($_POST['entryId']) ? $_POST['entryId'] : '' ?>">
<input name="set" class="btn btn-secondary" type="submit">
</div>
</div>
</form>
</div>
<?php
if (isset($_POST['entryId'])) {
    $eid = $_POST['entryId']; //pass entry id coming from Input entryId

}

  $kclient->setKs($ksession);
  
  $filter = new KalturaAssetFilter();
  $pager = new KalturaFilterPager();
  $pager->pageSize = 500;
  $pager->pageIndex = 1;

  $filter->entryIdEqual = $eid; //entryId being pass to kaltura api call
  $result = $kclient->flavorAsset->listAction($filter, $pager);

     ?>
     <div class="container">
     <table id ="" class="table table-striped"><tr><th></th><th>Flavor_URL</th><th>Format</th><th>Dimension</th><th>Size(kb)</th></tr>
     </div>
     <?php

     $ta = 0; // var id for the copy flavor url button
   foreach ($result->objects as $entry) { //iterate on the flavor assets objects
          $ta += 1;
          $dimension = $entry->width."X".$entry->height;

          //create the flavor url link
          $flavorlink="https://vodcdn.ca.kaltura.com/p/103/sp/10300/serveFlavor/entryId/$eid/v/2/ev/3/flavorId/$entry->id/forceproxy/true/name/a.mp4";

           if ($entry->isOriginal == 1 and $entry->flavorParamsId == 0) {
                 $flavorname = "Source";
                 //$flavorurl = "https://api.ca.kaltura.com/p/103/sp/10300/playManifest/entryId/$eid/format/url/flavorParamIds/0";
                // $flavorlink="https://vodcdn.ca.kaltura.com/p/103/sp/10300/serveFlavor/entryId/$eid/v/2/ev/3/flavorId/$entry->id/forceproxy/true/name/a.mp4";
             }elseif ($entry->status > 2) {
                 // disable entries if status is not equal 2(ready)
                  $flavorlink ="";
                  $entry->fileExt ="";
                  $dimension="";
                  $entry->size="";
                  $stat ="disabled";
             }elseif ($entry->status == 1) {
                  $flavorlink = "Flavor Stuck in convertion Error, Please Notify IT Support";
             }
// display the table
   echo "<tr>
        <td><input data-bs-toggle=\"tooltip\" data-bs-placement=\"left\" title=\"Copy Flavor Url\"
        class=\"btn btn-secondary\" type=\"button\" value=\"Copy Flavor URL\" onclick=\"selectElementContents( document.getElementById('$ta') );\" $stat></td>
        <td id='$ta'> ".$flavorlink."</td>
        <td> ".$entry->fileExt."</td>
        <td>".$dimension."</td>
        <td>".$entry->size."</td>
        </tr>";
?>

<script type="text/javascript">
// copy the flavor url from the table
function selectElementContents(el) {
       var body = document.body, range, sel;
       if (document.createRange && window.getSelection) {
           range = document.createRange();
           sel = window.getSelection();
           sel.removeAllRanges();
           try {
               range.selectNodeContents(el);
               sel.addRange(range);
           } catch (e) {
               range.selectNode(el);
               sel.addRange(range);
           }
           document.execCommand("copy");
           //alert("copied Flavor Url");
       } else if (body.createTextRange) {
           range = body.createTextRange();
           range.moveToElementText(el);
           range.select();
           range.execCommand("Copy");
       }

       }

</script>

<?php

   }

 //echo "</table>";

?>
