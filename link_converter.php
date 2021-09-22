<?php

/*If we had a php script that worked through the results somehow, 
by loading the content of the first result into a text area that could be updated, 
we could write out the replacement url as well, 
so it’s easy to copy and paste, and then provide an option to save to the db. 
When reloaded it would just bring up the next entry maybe?
*/

# Moodle Includes
require_once('../../config.php');
require_once('locallib.php');
require_once('link_converter_form.php');

# Globals
global $CFG, $USER, $DB, $PAGE;

$PAGE->set_url('/local/kaltura/link_converter');
$PAGE->set_context(context_system::instance());

# Check security - special privileges are required to use this script
$currentcontext = context_system::instance();
$userID = $USER->id;
$site = get_site();

list($context, $course, $cm) = get_context_info_array($PAGE->context->id);

require_login($course, true, $cm);

if ( (!isloggedin()) ) {
    print_error("You need to be logged in to access this page."); 
    exit;
}

$admins = get_admins();
$isadmin = false;
foreach($admins as $admin) {
    if ($USER->id != $admin->id) {
        print_error("You need to be an admin to access this page."); 
        exit;
    }
}

$PAGE->set_title("Kaltura CC Link Converter");
$PAGE->set_pagelayout('base');
$PAGE->set_heading($site->fullname);
$PAGE->navbar->ignore_active();


echo $OUTPUT->header();

// Get URL parameters.
$offset = optional_param('offset', '', PARAM_INT);

if(empty($offset)){
    $offset =0;
}

//first get count of all records
//set count as total
$countarray = count_records();

$total = $countarray["total"];

//check url for offset & do little math thing to see where you are at
$pagesize = 1;
if ($offset == -1) {
    if ($total > $pagesize) {
        $offset = $offset+1;
        //$offset = floor($offsetcount / $pagesize);
    } else {
        $offset = 0;
    }
}
if ($offset * $pagesize >= $total && $total > 0) {
    $offset = floor(($total-1) / $pagesize);
}

//calculate offset for certain catergory
$categoryoffset=0;
$type = "";
if($countarray["book"] > $offset){
    $categoryoffset = $offset;
    $type = "book";
}else if($countarray["book"] <= $offset && $countarray["book"]+$countarray["label"] > $offset ){
    $categoryoffset = $offset - $countarray["book"];
    $type = "label";
}else if($countarray["book"]+$countarray["label"] <= $offset && $countarray["book"]+$countarray["label"]+$countarray["hvp"] > $offset){
    $categoryoffset = $offset - ($countarray["book"]+$countarray["label"]);
    $type = "hvp";
}else if($countarray["book"]+$countarray["label"]+$countarray["hvp"] <= $offset && $countarray["book"]+$countarray["label"]+$countarray["hvp"]+$countarray["url"] >= $offset){
    $categoryoffset = $offset - ($countarray["book"]+$countarray["label"]+$countarray["hvp"]);
    $type = "url";
}

$actionurl = new moodle_url('/local/kaltura/link_converter.php', array('offset'=>$offset));

if($categoryoffset !=0){
    $categoryoffset--;
}

//init form & pass offset to form
$mform = new link_converter_form(null, array('offset'=>$categoryoffset, 'type'=>$type ));

//do is cancelled and submit checks
if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
    $returnurl = new moodle_url('/my');
    redirect($returnurl);
   
} else if ($fromform = $mform->get_data()) {
  //In this case you process validated data. $mform->get_data() returns data posted in form.

        $type = [
            "book",
            "label",
            "hvp",
            "url"
        ];

        $location=[
            "content",
            "intro",
            "json_content",
            "externalurl"
        ];

        $table=[
            "book_chapters",
            "label",
            "hvp",
            "url"
        ];

        for($i=0; $i<count($type); $i++){
            if($type[$i]== $fromform->activity){

                $sql = "UPDATE mdl_".$table[$i]."
                SET ".$location[$i]." = '".$fromform->converter["text"]."'
                WHERE id = ".$fromform->modid;

                $completed = $DB->update_record($table[$i], array("id"=>$fromform->modid, "$location[$i]"=> "$fromform->converter['text']" ));
            }
        }

        if($completed){
            echo '<div class="alert alert-success" role="alert">
                Link update successfull.
            </div>';
        }else{
            echo '<div class="alert alert-danger" role="alert">
                Oops! Something went wrong with the link update.
            </div>';
        }
        $mform->display();

} else{
    $mform->display();
}

//output pager with correct values
if ($total > $pagesize) {
    echo $OUTPUT->paging_bar($total, $offset, $pagesize, $actionurl, 'offset');
}

echo $OUTPUT->footer();


function count_records(){
    global $DB;
    $type = [
        "book",
        "label",
        "hvp",
        "url"
    ];

    $location=[
        "content",
        "intro",
        "json_content",
        "externalurl"
    ];

    $id=[
        "book_chapters.id",
        "label.id",
        "hvp.id",
        "url.id"
    ];

    $count=0;
    $countarray=[
        "book"=>0,
        "label"=>0,
        "hvp"=>0,
        "url"=>0,
        "total"=>0
    ];
    for($i=0; $i<count($type); $i++){

        $sql = "
            SELECT COUNT(id)";

        if($type[$i]=="book"){
            $sql .= " FROM mdl_book_chapters ";
        }else{
            $sql .= "FROM mdl_".$type[$i]." ";
        }

        $sql .= "    
            WHERE (".$location[$i]." LIKE '%urcourses-video%' OR ".$location[$i]." LIKE '%kaltura.cc.uregina%')
            ";

        $results = $DB->count_records_sql($sql);

        if(!empty($results)){
            $count += $results;
            $countarray[$type[$i]] = $results;
        }
    }

    $countarray["total"] = $count;
    return $countarray;
}