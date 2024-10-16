<?php
require_once('../../config.php');
global $CFG, $USER, $DB, $PAGE;


require_login();

// Ensure user is logged in
if (!isloggedin()) {
    echo "You need to be logged in to access this page.";
    exit;
}

if (isset($_POST['meeting_id'])) {
  $meeting_id = required_param('meeting_id', PARAM_TEXT);

  $get_recordMeeting_service = new \mod_zoom\webservice();
  $response = $get_recordMeeting_service->grant_access_to_recording($meeting_id);

  // Get the updated meeting recordings 
  $updated_recordings = $get_recordMeeting_service->get_user_recording($meeting_id);

  $accordion_body = ''; // Initialize accordion content

// Check if there are recording files
if (!empty($updated_recordings->recording_files)) {
  $allowedrecordingtypes = [
    'MP4' => 'video',
    'M4A' => 'audio',
    //'audio_transcript' => 'transcript',  
    'active_speaker' => 'video',           
    'audio_only' => 'audio', 
    'shared_screen_with_speaker_view' => 'video', 
       'shared_screen' => 'video',    
   // 'timeline' => 'timeline',              
];
    // Loop through each recording file and populate the accordion content
    foreach ($updated_recordings->recording_files as $recfiles) {
     
        // var_dump($recfiles);
           // Check if the recording type is in the allowed types
           if (array_key_exists($recfiles->recording_type, $allowedrecordingtypes)) {
            $accordion_body .= html_writer::tag('div', 
                html_writer::tag('fieldset', 
                    html_writer::start_tag('form', array('action' => '', 'method' => 'post', 'id' => 'uploadfrm')) .
                    html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'mid[]', 'value' => $updated_recordings->uuid, 'class' => 'meetingid')) .
                    html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'rectype[]', 'value' => $recfiles->recording_type, 'class' => 'recordType')) .
                    html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'zoomdate[]', 'value' => date('Y-M-d h:i:s', strtotime($updated_recordings->start_time)), 'class' => 'date_created')) .
        
                    // Create a row for the video/audio and the form
                    html_writer::tag('div', 
                        // Video/Audio container
                        html_writer::tag('div', 
                            (($recfiles->recording_type == "audio_only") ?
                                html_writer::tag('audio', 
                                    html_writer::empty_tag('source', array('src' => $recfiles->download_url)), 
                                    array('controls' => true, 'class' => 'img-fluid'))
                            :
                                html_writer::tag('video', 
                                    html_writer::empty_tag('source', array('src' => $recfiles->download_url)), 
                                    array('controls' => true, 'class' => 'img-fluid', 'width' => '300', 'height' => '220'))
                            ), 
                            array('class' => 'col-4') // column for video/audio
                        ) .
        
                        // Form fields container
                        html_writer::tag('div', 
                            // Title input field
                            html_writer::tag('div', 
                                html_writer::empty_tag('input', array('type' => 'text', 'name' => 'title[]', 'class' => 'form-control tit', 'id' => 'floatingInput', 'placeholder' => 'Media Title')) .
                                html_writer::tag('label', 'Enter your media title here..', array('for' => 'floatingInput')),
                            array('class' => 'form-floating mb-3 tit1')
                            ) .
        
                            // Checkbox for download option
                            html_writer::tag('div', 
                                html_writer::empty_tag('input', array('class' => 'form-check-input', 'name' => 'chooser[]', 'type' => 'checkbox', 'value' => $recfiles->download_url, 'id' => 'flexCheckDefault')) .
                                html_writer::tag('label', 
                                    $recfiles->download_url, 
                                    array('class' => 'form-check-label', 'for' => 'flexCheckDefault')
                                ),
                            array('class' => 'form-check m-2')
                            ) .
        
                            // Display recording type
                            html_writer::tag('p', '<strong>Recording type:</strong> ' . $recfiles->recording_type, array('class' => 'bg-light'))
                        , array('class' => 'col-8') // column for the form
                        )
                    , array('class' => 'row accordionBdr p-2 rounded') // row for layout
                    ) .
        
                    html_writer::end_tag('form'),
                array('class' => 'accordion-body accordion-border')
                )
            );
        }
        



        
    }
} else {
    $accordion_body .= '<p>No recordings available for this meeting.</p>';
}

// Output the accordion content
echo $accordion_body;

}

function build_recording_accordion_content($recording_details, $meeting_id) {
  $accordion_body = '';

  if (!empty($recording_details->recording_files)) {
      foreach ($recording_details->recording_files as $recfiles) {
          $accordion_body .= html_writer::tag('div', 
              html_writer::tag('fieldset', 
                  html_writer::start_tag('form', array('action' => '', 'method' => 'post', 'id' => 'uploadfrm')) .
                  html_writer::tag('div', 
                      html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'mid[]', 'value' => $meeting_id, 'class' => 'meetingid')) .
                      html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'rectype[]', 'value' => $recfiles->recording_type, 'class' => 'recordType')) .
                      html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'zoomdate[]', 'value' => date('Y-M-d h:i:s', strtotime($recfiles->recording_start)), 'class' => 'date_created')) .
                      html_writer::tag('div', 
                          html_writer::empty_tag('input', array('type' => 'text', 'name' => 'title[]', 'class' => 'form-control tit', 'id' => 'floatingInput', 'placeholder' => 'Media Title')) .
                          html_writer::tag('label', 'Enter your media title here..', array('for' => 'floatingInput')), 
                          array('class' => 'form-floating mb-3 tit1')
                      ), 
                      []
                  ) .
                  html_writer::tag('div', 
                      html_writer::empty_tag('input', array('class' => 'form-check-input', 'name' => 'chooser[]', 'type' => 'checkbox', 'value' => $recfiles->download_url, 'id' => 'flexCheckDefault')) .
                      html_writer::tag('label', $recfiles->download_url . 
                          (($recfiles->recording_type == "audio_only") ?
                              html_writer::tag('video', 
                                  html_writer::empty_tag('source', array('src' => $recfiles->download_url)), 
                                  array('width' => '300', 'height' => '40', 'controls' => true)
                              ) :
                              html_writer::tag('video', 
                                  html_writer::empty_tag('source', array('src' => $recfiles->download_url)), 
                                  array('width' => '300', 'height' => '220', 'controls' => true)
                              )
                          ), 
                          array('class' => 'form-check-label', 'for' => 'flexCheckDefault')
                      )
                  , array('class' => 'form-check m-2')) .
                  html_writer::tag('p', '<strong>Recording type:</strong> ' . $recfiles->recording_type, array('class' => 'bg-light')) . 
                  html_writer::end_tag('form'),
              array('class' => 'accordion-body accordion-border')
              )
          );
      }
  } else {
      $accordion_body .= html_writer::tag('p', 'No recording files available.');
  }

  return $accordion_body;
}
