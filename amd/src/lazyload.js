define(['jquery', 'core/ajax', 'core/notification'], function($, ajax, notification) {

    var max = false;
    var maxcount = 0;
    var currentPage = 0;
    var isLoading = false;
    /**
     * Handles scroll event to fetch more videos if needed.
     */
    function processScroll(){
        if (!max && !isLoading) {
            isLoading = true;

            var count = $('.kalvidassign_videocards').length;
            var vidassignid = $("#mod_kalvidassign_gallery").data("vid");
            var course = $('#mod_kalvidassign_gallery').data("course");
            var cmid = $('#mod_kalvidassign_gallery').data("cmid");

            var args = {count: count, vidassignid: vidassignid, courseid: course, cmid: cmid, page: currentPage};

            var ajaxCall = {
                methodname: 'mod_kalvidassign_fetch_videos',
                args: args,
                fail: notification.exception
            };

            var promise = ajax.call([ajaxCall]);
            promise[0].done(function(response) {
                maxcount = response.maxcount;

                if (response.videocount > 0 && $('.kalvidassign_videocards').length < maxcount) {
                    $.each(response.videos, function(index, value) {
                        var videocard = '<div class="card m-3 kalvidassign_videocards" style="width: 15rem;">' +
                        '<img id="' + value.id + '" class="card-img-top kalvidassign_thumbnail" src="' + value.thumbnailUrl + '">' +
                        '<div class="hide url" data-comment="' + value.commentid + '" data-totallikes="' + value.totallikes +
                        '" data-liked="' + value.liked + '" data-value="' + value.url + '" data-width="' + value.width +
                        '" data-height="' + value.height + '"></div>' +
                        '<div class="card-body">' +
                        '<p>Creator: ' + value.creator + '<p>' +
                        '<h5 class="card-title"> <b>' + value.name + '</b></h5>' +
                        '</div>' +
                        '</div>';

                        $("#mod_kalvidassign_gallery").find(".row").append(videocard);
                    });

                    currentPage++;
                } else {
                    max = true;
                    $(window).off('scroll');
                }

                isLoading = false; // Allow next scroll request
            }).fail(function() {
                isLoading = false; // Also release lock on failure
            });
        }
    }
    /**
     * Initializes the video gallery functionality.
     */
    function init() {
        var enableScroll = $('#mod_kalvidassign_gallery').data("scroll");
        if (enableScroll === true || enableScroll === 'true') {
            $(window).bind('scroll', processScroll);
        }
    }


    return {
        init: init
    };
});

