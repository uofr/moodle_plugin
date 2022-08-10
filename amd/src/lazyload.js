/**
 * A tool for sorting instances based on column header.
 *
 * @module moodle-report_quizoverview-sort
 */

/**
 * @class M.report_quizoverview
 * @extends Base
 * @constructor
 */

define(['jquery','core/ajax','core/notification'], function($, ajax,notification) {

    var max = false;
    var maxcount = 0;

    //On scroll load next ten videos
    function processScroll(){

        //check if at max
        if(!max){
            //get current count of videos
            var count = $('.kalvidassign_videocards').length;
            var vidassignid = $("#mod_kalvidassign_gallery").data("vid");
            var course = $('#mod_kalvidassign_gallery').data("course");
            var cmid = $('#mod_kalvidassign_gallery').data("cmid");

            //send ajax request
            var args = {count: count, vidassignid: vidassignid, courseid:course, cmid:cmid};

            // set ajax call
            var ajaxCall = {
                methodname: 'mod_kalvidassign_fetch_videos',
                args: args,
                fail: notification.exception
            };

            // initiate ajax call
            var promise = ajax.call([ajaxCall]);
            promise[0].done(function(response) {
                //self.spinnerCheck("hide");
                maxcount = response.maxcount;
                //check if no more videos
                var count = $('.kalvidassign_videocards').length;

                if(response.videocount > 0 && count < response.maxcount){

                    maxcount = response.maxcount;
                    //create each video template and
                    //append to end of video section
                    $.each(response.videos, function(index, value) {

                        var videocard = '<div class="card m-3 kalvidassign_videocards" style="width: 15rem;">'+
                                        '<img id="'+value.id+'" class = "card-img-top kalvidassign_thumbnail" src="'+value.thumbnailUrl+'">'+
                                        '<div class="hide url" data-comment="'+value.commentid+'" data-totallikes="'+value.totallikes+'" data-liked="'+value.liked+'" data-value = '+value.url+' data-width = '+value.width+' data-height = '+value.height+'></div>'+
                                        '<div class="card-body">'+
                                        ' <p>Creator: '+value.creator+'<p>'+
                                            '<h5 class="card-title"> <b>'+value.name+'</b></h5>'+
                                        '</div>'+
                                        '</div>';

                        $("#mod_kalvidassign_gallery").find(".row").append(videocard);
                    });
                }else{
                    max =true;
                    $(window).off('scroll');
                }
            }).fail(function() {
                //self.spinnerCheck("hide");
            });
        }else{
            //unbind scroll
            $(window).off('scroll');
        }
    }

    function init() {
        $(window).bind('scroll', processScroll);
    }

    return {
        init: init
    };
});

