
<?php

require_once($CFG->libdir.'/formslib.php');

class link_converter_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;
       
        $mform = $this->_form; // Don't forget the underscore! 

        $results = $this->get_prefilldata();
        if(empty($results)){

            $mform->addElement('html', '<div class="alert alert-primary" role="alert">
            No CC Kaltura urls found in the book, label, hvp and url resources.
          </div>');
        }

        //should only be one
        foreach($results as $result){
            $mform->addElement('html', '<div class="alert alert-primary" role="alert">
            <a href="'.new moodle_url('/course/view.php', array('id' => $result->cid)) .'" target="_blank">
            '.$result->shortname.': '.$result->activity.' '.$result->name. 
          '</a></div>');

            //suggested link styling
            $suggestion = $this->generate_suggestion($result);
            $mform->addElement('html', '<div style = "word-wrap: break-word;" class="alert alert-primary" role="alert"> <b> Suggest replacements: </b> <br>'.$suggestion.'</div>');

          if($this->_customdata['type']=="url"){
            $mform->addElement('textarea', 'converter', "Kaltura CC Link",'wrap="virtual" rows="20" cols="50"');
            $mform->setDefault('converter', $result->url);
          }else{
            $converter =  $mform->addElement('editor','converter', "Kaltura CC Link" );
            $converter->setValue(array('text' => ""));
            $converter->setValue(array('text' => $result->url));
            $mform->setType('converter', PARAM_RAW);   
          }
        
            $mform->addElement('hidden', 'activity', $result->activity);
            $mform->addElement('hidden', 'cid', $result->cid);
            $mform->addElement('hidden', 'shortname', $result->shortname);
            $mform->addElement('hidden', 'name', $result->name);
            $mform->addElement('hidden', 'modid', $result->id);
            $mform->addElement('hidden', 'pageoffset', $this->_customdata['pageoffset']);
        }
          //normally you use add_action_buttons instead of this code
          $buttonarray=array();
          $buttonarray[] = $mform->createElement('submit', 'submitbutton', "Save to DB");
          $buttonarray[] = $mform->createElement('cancel');
          $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);      
          
    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }

    function get_prefilldata(){

        global $DB;
        $type = [
            "book",
            "label",
            "hvp",
            "url"
        ];
    
        $location=[
            "book_chapters.content",
            "label.intro",
            "hvp.json_content",
            "url.externalurl"
        ];

        $id=[
            "book_chapters.id",
            "label.id",
            "hvp.id",
            "url.id"
        ];
    
        for($i=0; $i<count($type); $i++){   
            if($type[$i] == $this->_customdata['type'] ){

                $sql = "
                    SELECT
                        mdl_course.id as cid,
                        mdl_course.shortname,
                        mdl_modules.name as activity,
                        mdl_".$type[$i].".name, 
        
                    mdl_".$location[$i]." as url,
                    mdl_".$id[$i]." as id
        
                    FROM mdl_course 
                    LEFT JOIN mdl_".$type[$i]." ON (mdl_".$type[$i].".course = mdl_course.id) ";
        
                if($type[$i]=="book"){
                    $sql .= " LEFT JOIN mdl_book_chapters ON (mdl_book.id = mdl_book_chapters.bookid) ";
                }
        
                $sql .= "    
                    JOIN mdl_modules ON mdl_modules.name = '".$type[$i]."'
                    JOIN mdl_course_modules ON (mdl_course_modules.module = mdl_modules.id ) 
                        AND (mdl_course_modules.instance = mdl_".$type[$i].".id) AND (mdl_course_modules.course = mdl_course.id)
                    WHERE (mdl_".$location[$i]." LIKE '%urcourses-video%' OR mdl_".$location[$i]." LIKE '%kaltura.cc.uregina%')
                    ";

                $sql  .= " LIMIT 1 OFFSET ".$this->_customdata['offset'];

                $results = $DB->get_records_sql($sql);
                if(!empty($results)){
                    break;
                }
            }
        }
        return $results;
    }

    function create_session(){
        require_once "API/KalturaClient.php";
        // Your Kaltura partner credentials
        define("PARTNER_ID", "");
        define("ADMIN_SECRET", "");
        define("USER_SECRET",  "");
        $user = ""; 
        $kconf = new KalturaConfiguration(PARTNER_ID);
        $kconf->serviceUrl = "https://api.ca.kaltura.com";
        $kclient = new KalturaClient($kconf);
        $ksession = $kclient->session->start(ADMIN_SECRET, $user, KalturaSessionType::ADMIN, PARTNER_ID);
       
        if (!isset($ksession)) {
            die("Could not establish Kaltura session. Please verify that you are using valid Kaltura partner credentials.");
        }
       
        $kclient->setKs($ksession);       
        $kconf->format = KalturaClientBase::KALTURA_SERVICE_FORMAT_PHP;
        return $kclient;
    }

    function generate_suggestion($result){
        global $CFG, $lulist, $lulist2;

        require_once($CFG->dirroot.'/local/kaltura/phatphile.php');
        require_once($CFG->dirroot.'/local/kaltura/oldphatphile.php');

        $kclient = $this->create_session();
        $id_map = array();
        $oldid_map = array();
        $text ="";

         //send up rootcategory and course id
         $kafuri = get_config(KALTURA_PLUGIN_NAME, 'kaf_uri');
          
         if(empty($kafuri)||!$kafuri ){
             return '<div class="alert alert-warning" role="alert">
             Context could not be set, video may not be accessible.
             </div>';
         }else{

             if (strpos($kafuri, 'dev')) {
                 $source = "http://regina-moodle-dev.kaf.ca.kaltura.com";
             }else if(strpos($kafuri, 'cce')){
                 $source = "http://regina-moodle-cce.kaf.ca.kaltura.com";
             }else{
                 $source = "http://kaf.urcourses.uregina.ca";
             }
         }

        //$pattern1 = "/\/entryid\/\s*[^\n\r]*/";
        //$pattern2 = "/\/entry_id\/\s*[^\n\r]*/"; 
        $pattern1 = "/entry_id\/?\s*.{10}/i";
        $pattern2 = "/entryid\/?\s*.{10}/i";
        preg_match_all($pattern1, $result->url, $entryidsholder1 );
        preg_match_all($pattern2, $result->url, $entryidsholder2 );

        error_log(print_r("ENTRY IDS ",TRUE));
        error_log(print_r($entryidsholder1,TRUE));
        error_log(print_r($entryidsholder2,TRUE));

        $entryids=[];

        foreach($entryidsholder1 as $entryid){
            foreach($entryid as $eid){
                $split = explode("/",$eid);
                $entryids[] = $split[1];
            }
        }

        foreach($entryidsholder2 as $entryid){
            foreach($entryid as $eid){
                $split = explode("/",$eid);
                $entryids[] = $split[1];
            }
        }
      
        $entryrefs = explode(';',$lulist);
        foreach ($entryrefs as $entryref) {
            $elms = explode(',',$entryref);
            $id_map[$elms[1]] = $elms[0];
        }

        foreach($entryids as $entryid){
            
            if (array_key_exists($entryid, $id_map)) {
                // re-mapping entry if match exists
                $newentryid = $id_map[$entryid];
                $result = $kclient->media->get($newentryid, -1);

                $text .= '&lt;a href="'.$source.'/browseandembed/index/media/entryid/'.$newentryid.
                '/showDescription/false/showTitle/false/showTags/false/showDuration/false/showOwner/false/showUploadDate/false/playerSize/608x373/playerSkin/23449221/">';
                $text .= "tinymce-kalturamedia-embed||".$result->name." ".gmdate("H:i:s", $result->duration)."||608||373 &lt;/a&gt;<br><br>";
            }else{


                //check if old id

                $oldentryrefs = explode(';',$lulist2);
                foreach ($oldentryrefs as $entryref) {
                    $elms = explode(',',$entryref);
                    $oldid_map[$elms[1]] = $elms[0];
                }

                if (array_key_exists($entryid, $oldid_map)) {

                    error_log(print_r("MADE IT ",TRUE));
                    // re-mapping entry if match exists
                    $midentryid = $oldid_map[$entryid];
                    error_log(print_r($midentryid,TRUE));
                    if (array_key_exists($midentryid, $id_map)) {

                        error_log(print_r("MADE IT2 ",TRUE));

                        // re-mapping entry if match exists
                        $newentryid = $id_map[$midentryid];

                        error_log(print_r($newentryid,TRUE));
                        $result = $kclient->media->get($newentryid, -1);

                        $text .= '&lt;a href="'.$source.'/browseandembed/index/media/entryid/'.$newentryid.
                        '/showDescription/false/showTitle/false/showTags/false/showDuration/false/showOwner/false/showUploadDate/false/playerSize/608x373/playerSkin/23449221/">';
                        $text .= "tinymce-kalturamedia-embed||".$result->name." ".gmdate("H:i:s", $result->duration)."||608||373 &lt;/a&gt;<br><br>";
                    }else{
                        $text.= "Old entry id: ".$entryid." could not be mapped to new id <br>";
                    }
                }else{
                    $text.= "Old entry id: ".$entryid." could not be mapped to new id <br>";
                }

                
            }
        }   
        return $text;
    }
}


