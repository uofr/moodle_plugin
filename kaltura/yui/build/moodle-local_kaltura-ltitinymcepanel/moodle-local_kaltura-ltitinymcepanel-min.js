YUI.add("moodle-local_kaltura-ltitinymcepanel",function(e,t){var n=function(){n.superclass.constructor.apply(this,arguments)};e.extend(n,e.Base,{contextid:0,init:function(t){if(""===t.ltilaunchurl||""===t.objecttagheight||""===t.objecttagid||""===t.previewiframeid){alert("Some parameters were not initialized.");return}this.load_lti_content(t.ltilaunchurl,t.objecttagid,t.objecttagheight),e.one("#closeltipanel").on("click",this.user_selected_video_callback,this,t.objecttagid,t.previewiframeid,t.objecttagheight),null!==e.one("#page-footer")&&e.one("#page-footer").setStyle("display","none")},load_lti_content:function(t,n,r){0===this.contextid&&(this.contextid=e.one("#lti_launch_context_id").get("value"));var i='<iframe id="lti_view_element" height="'+r+'px" width="100%" src="'+t+"&amp;contextid="+this.contextid+'" allow="autoplay *; fullscreen *; encrypted-media *; camera *; microphone *;"></iframe>';e.one("#"+n).setContent(i)},user_selected_video_callback:function(t,n,r,i){e.one("#"+n).setContent("");var s=e.Node.create("<center></center>"),o=e.Node.create("<iframe></iframe>");o.setAttribute("allowfullscreen",""),o.setAttribute("width",e.one("#width").get("value")+"px"),o.setAttribute("height",i+"px"),o.setAttribute("src",e.one("#video_preview_frame").getAttribute("src")),s.append(o),e.one("#"+r).append(s)}},{NAME:"moodle-local_kaltura-ltitinymcepanel",ATTRS:{ltilaunchurl:{value:""},objecttagheight:{value:""},objecttagid:{value:""},previewiframeid:{value:""}}}),M.local_kaltura=M.local_kaltura||{},M.local_kaltura.init=function(e){return new n(e)}},"@VERSION@",{requires:["base","node","panel","node-event-simulate"]});
