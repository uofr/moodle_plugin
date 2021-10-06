
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
}