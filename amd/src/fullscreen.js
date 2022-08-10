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

define(['jquery','core/templates','core/ajax','core/notification', 'core/str'], function($, Templates, ajax,notification,Str) {

    var MEDIABOX = function(allowcomments, allowlikes){
        /*
        * Variables accessible
        * in the class
        */
        var vars = {
            _sidebarwidth : 300,
            _navbarheight : 60,
            currentitem : null,
            _allowcomments: true,
            _allowlikes: true,
            _contextid: 0,
            _cmid: 0,
            _courseid: 0,
        };

        /*
        * Can access this.method
        * inside other methods using
        * root.method()
        */
        var root = this;

        /*
        * Constructor
        */
        this.construct = function(allowcomments, allowlikes){

            _allowlikes=allowlikes;
            _allowcomments=allowcomments;
            _contextid= $('#mod_kalvidassign_gallery').data("context");
            _cmid= $('#mod_kalvidassign_gallery').data("cmid");
            _courseid= $('#mod_kalvidassign_gallery').data("course");

            enable();
            build();
        };

        var build = async function() {

            //get variables together
            var data = {
                allowlikes:_allowlikes,
                allowcomments:_allowcomments
            };
            //RENDER TEMPLATE
            var template =  await render('mod_kalvidassign/sidebar', data);
            $('body').append(template);

            root.overlay = $('#mediabox-overlay');
            root.mediabox = $('#mediabox');
            root.navbar = $('#mediabox-navbar');
            resizeoverlay();

            root.album = $(".kalvidassign_videocards");

            root.overlay.bind('click', function() {
                stop();
            });

            $('#mediabox', '#mediabox-navbar, #mediabox-navbar-container').click(function() {
                if (e.currentTarget.get('id') === 'mediabox-navbar-container') {
                    return false;
                }
                stop();
            });


            //for lazy load to catch all newly added videos
            $(window).scroll(function() {
                enable();
            });

            $(window).resize(function() {
                resizeoverlay();
                repositionitem();
            });

            //collapse sidebar if on smaller device or screen size
            if ($(window).width() < 400) {
                if(!$("#mediabox").hasClass("sidebarhidden")){
                    $("#mediabox").addClass('sidebarhidden');
                    $("#sidebar-actions").addClass('mod-kalvidassign-sidebar-shrink');
                    $("#mediabox-metainfo").addClass('hide');
                    $("#mediabox-social").addClass('hide');
                    $("#mediabox-comments").addClass('hide');
                    $(this).addClass('fa-plus').removeClass('fa-minus');
                    resizeoverlay();
                    repositionitem();
                }
             }
             else {
                if($("#mediabox").hasClass("sidebarhidden")){
                    $("#mediabox").removeClass('sidebarhidden');
                    $("#sidebar-actions").removeClass('mod-kalvidassign-sidebar-shrink');
                    $("#mediabox-metainfo").removeClass('hide');
                    $("#mediabox-social").removeClass('hide');
                    $("#mediabox-comments").removeClass('hide');
                    $(this).addClass('fa-minus').removeClass('fa-minus');
                    resizeoverlay();
                    repositionitem();
                }
             }

            // Sidebar hide/expand button.
            $('#sidebar-actions .sidebartoggle').click(function() {
                $("#mediabox").toggleClass('sidebarhidden');
                $("#sidebar-actions").toggleClass('mod-kalvidassign-sidebar-shrink');
                $("#mediabox-metainfo").toggleClass('hide');
                $("#mediabox-social").toggleClass('hide');
                $("#mediabox-comments").toggleClass('hide');

                if($(this).hasClass("fa-minus")){
                    $(this).addClass('fa-plus').removeClass('fa-minus');
                }else{
                    $(this).addClass('fa-minus').removeClass('fa-plus');
                }
                resizeoverlay();
                repositionitem();
            });

            for (var i = 0; i < root.album.length; i++) {
                var navitem = Y.Node.create('<div class="navitem"></div>');
                navitem.setAttribute('data-id', i);
                var item = Y.Node.create('<img/>');
                item.setAttribute('src', root.album[i].children[0].getAttribute('src'));
                item.appendTo(navitem);

                navitem.appendTo('#mediabox-navbar-container');
            }
            Y.delegate('click', function(e) {
                e.preventDefault();
                changeitem(e.currentTarget.getAttribute('data-id'));
            }, '#mediabox-navbar', '.navitem');

            if(_allowlikes){
                // Like action.
                $('#liked').click(function(){
                    if ($('#liked').hasClass('fa-heart')) {
                        likedbyme = 0;
                        $('#liked').removeClass('fa-heart');
                        $('#liked').addClass('fa-heart-o');
                    }else{
                        var likedbyme = 1;
                        $('#liked').removeClass('fa-heart-o');
                        $('#liked').addClass('fa-heart');
                    }

                    //send ajax request
                    var args = {
                        vidid: $(root.currentitem).attr('id'),
                        liked: likedbyme};
                    // set ajax call
                    var ajaxCall = {
                        methodname: 'mod_kalvidassign_update_likes',
                        args: args,
                        fail: notification.exception
                    };

                    // initiate ajax call
                    var promise = ajax.call([ajaxCall]);
                    promise[0].done(function(response) {
                        if(response.result){
                        $hidden = $(root.currentitem).find(".url");
                        $hidden.data("liked",likedbyme);
                        }
                    });
                });
            }

            $( "#mediabox-metainfo a" ).click(function() {
                if($(this).find("i").hasClass("fa-caret-right")){
                    $(this).find("i").addClass('fa-caret-down').removeClass('fa-caret-right');
                }else{
                    $(this).find("i").addClass('fa-caret-right').removeClass('fa-caret-down');
                }
            });

            $('#previous').click(function() {
                if (root.currentitemindex === 0) {
                    changeitem(root.album.length - 1);
                } else {
                    changeitem(root.currentitemindex - 1);
                }
                return false;
            });

            $('#next').click(function() {
                if (root.currentitemindex === root.album.length - 1) {
                    changeitem(0);
                } else {
                    changeitem(root.currentitemindex + 1);
                }
                return false;
            });

            $('#sidebar-actions .mbclose').click(function() {
                stop();
                return false;
            });

            //if more videos are added via lazyload then update alum and click events
            $("#mod_kalvidassign_gallery").bind("DOMNodeInserted",function(){
                root.album = $(".kalvidassign_videocards");
                enable();
            });

        };

       var updatenavbarselection = function(itemnumber) {
            var current = root.navbar.one('.navitem.current');
            if (current) {
                current.removeClass('current');
            }
            root.navbar.one('.navitem[data-id="' + itemnumber + '"]').addClass('current');

            // Let's keep the currently displayed image centered in the navbar.
            var navbar = $('#mediabox-navbar');
            var currentitem = $('#mediabox-navbar-container .current');
            var itemwidth = currentitem.get('clientWidth') + 2 * parseInt(currentitem.css('margin-left'), 10);
            var items = $('#mediabox-navbar-container .navitem');
            var index = items.index(currentitem);

            var navwidth = navbar.get('clientWidth');
            var margin = (navwidth / 2) - (itemwidth / 2) - (index * itemwidth);

            $('#mediabox-navbar-container').css('margin-left', margin + 'px');
        };

        var changeitem = async function(itemnumber) {
            if (root.currentitemindex === itemnumber) {
                return;
            }
            var content = $('#mediabox-content');
            var playerinfo = $(root.album[itemnumber]).find(".url");
            content.empty();

            updatenavbarselection(itemnumber);

            root.currentitemindex = parseInt(itemnumber, 10);
            root.currentitem = root.album[itemnumber];

            //remove current iframe
            $("#mediabox-content").find("iframe").remove();

            //update with new iframe
            url = $(playerinfo).data("value");
            width= $(playerinfo).data("width");
            height = $(playerinfo).data("height");

            var player = '<div class = "kaltura-player-container" >'
            +'<iframe class="kaltura-player-iframe" src="'+url+'" allowfullscreen="true" allow="autoplay *; fullscreen *; encrypted-media *; camera *; microphone *;" frameborder="0" height="402" width="608" ></iframe>'
            +'</div>';

            //add player to mediabox
            $("#mediabox-content").html(player);

            //get metainfo and sidebar info
            var creator = $(playerinfo).parent().find("#thumbcreator").text();
            var title = $(playerinfo).parent().find("#thumbtitle").text();
            var liked = $(playerinfo).data("liked");
            var totallikes = $(playerinfo).data("totallikes");
            var commentid = $(playerinfo).data("comment");
            var commentbox = $("#mediabox-comments");

            $("#creators").text(creator);
            $("#caption").text("Caption: "+title);
            $("#liketally").empty();

            if(liked==0){
                $("#liked").removeClass("fa-heart");
                $("#liked").addClass("fa-heart-o");
                $("#liketally").text(' Liked by: '+totallikes+" others");
            }else{
                $("#liked").removeClass("fa-heart-o");
                $("#liked").addClass("fa-heart");
                $("#liketally").text('Liked by: you, '+totallikes+" others");
            }
            $("#caption").text("Caption: "+title);

            //Fetch Comments
            if(_allowcomments){
                await fetchcomments($(root.album[itemnumber]).attr('id'), commentid);

                var data = {
                    linktext: "Comments",
                    cid : commentid,
                    contextid: _contextid,
                    notoggle:false,
                    pix:true,
                    displaytotalcount:true,
                    count:0,
                    //"template" : "gallery",
                    canpost:true,
                    displaycancel:true,
                    itemid:$(root.album[itemnumber]).attr('id'),
                    commentarea:"item",
                    component : 'mod_kalvidassign',
                    autostart : true,
                    fullwidth:false
                };

                //RENDER TEMPLATE
                var template =  await render('mod_kalvidassign/comment_area', data);
                $(commentbox).html(template);
                $(commentbox).find(".comment-ctrl").show();
                $( ".comment-link" ).click(function() {
                    if($(this).find("i").hasClass("fa-caret-right")){
                        $(this).find("i").addClass('fa-caret-down').removeClass('fa-caret-right');
                    }else{
                        $(this).find("i").addClass('fa-caret-right').removeClass('fa-caret-down');
                    }
                });

                Str.get_strings([
                    { key: 'addcomment', component: 'moodle' },
                    { key: 'comments', component: 'moodle' },
                    { key: 'commentscount', component: 'moodle' },
                    { key: 'commentsrequirelogin', component: 'moodle' },
                    { key: 'deletecommentbyon', component: 'moodle' },
                ]).then(function() {
                    // Kick off when strings are loaded.
                    Y.use('core_comment', function(Y) {
                    M.core_comment.init(Y, {
                            client_id: data.cid,
                            commentarea:data.commentarea,
                            itemid: data.itemid,
                            area:'item',
                            page: 0,
                            courseid: _courseid,
                            contextid: _contextid,
                            component: data.component,
                            notoggle: false,
                            autostart: true
                        });
                    });
                });
            }
        };

        var fetchcomments = async function(itemid, commentid){
            //send ajax request
            var args = {
                itemid: itemid,
                contextid: _contextid,
                clientid: commentid,
                courseid: _courseid,
            };

             // set ajax call
            var ajaxCall = {
                methodname: 'mod_kalvidassign_get_comments',
                args: args,
                fail: notification.exception
            };

            // initiate ajax call
            var promise = ajax.call([ajaxCall]);
            promise[0].done(function(response) {
                if(response.result){
                    $.each( response.comments, function( key, value ) {
                       $('#comment-list-'+commentid).append(value.html);
                    });

                    $('#comment-link-count-'+commentid).text("("+response.count+")");
                }
            });
        };

        var enable = function() {

            var mediaboxtarget = '.kalvidassign_videocards';
            return $('body').find(mediaboxtarget).bind('click', function(e) {
                e.preventDefault();
                start(e.currentTarget);
                return false;
            });
        };

        var repositionitem = function(width, height) {

            var offsetTop, offsetLeft;
            var newwidth = '';
            var newheight = '';
            var content = $('#mediabox-content');
            var innercontent = $('.kaltura-player-container');
            var sidebarwidth = $("#mediabox-sidebar").width();

            if (root.mediabox.hasClass('sidebarhidden')) {
                sidebarwidth = 0;
            }

            if (typeof innercontent === "undefined") {
                return;
            }

            if (typeof width === "undefined") {
                width= $(innercontent).find("iframe").width();
                height = $(innercontent).find("iframe").height();
            }

            var winwidth = $('body').width();
            var winheight = $('body').height();

            var maxwidth = winwidth - sidebarwidth;
            var maxheight = winheight - vars._navbarheight;

            if (width > maxwidth || height > maxheight) {
                if ((width / maxwidth) > (height / maxheight)) {
                    newwidth = maxwidth;
                    newheight = parseInt(height / (width / maxwidth), 10);
                    offsetLeft = 0;
                    offsetTop = (winheight - newheight) / 2;
                } else {
                    newheight = maxheight;
                    newwidth = parseInt(width / (height / maxheight), 10);
                    offsetTop = 0;
                    offsetLeft = (winwidth - newwidth - sidebarwidth) / 2;
                }
                newwidth += 'px';
                newheight += 'px';
            } else {
                offsetLeft = (winwidth - width - sidebarwidth) / 2;
                offsetTop = (winheight - height - vars._navbarheight) / 2;
            }
            innercontent.css('width', newwidth);
            innercontent.css('height', newheight);

            content.css('top', offsetTop + 'px');
            content.css('left', offsetLeft + 'px');
        };

        var resizeoverlay = function() {
            var viewportwidth = screen.width;
            var viewportheight = screen.height;
            root.overlay.css('width', viewportwidth);
            root.overlay.css('height', viewportheight);
        };

        var start = function(target) {

            $('body').addClass('noscroll mediaboxactive');

            root.overlay.css('display', 'block');
            root.mediabox.css('display', 'block');

            //get variables
            url = $(target).find(".url").data("value");
            width= $(target).find(".url").data("width");
            height = $(target).find(".url").data("height");


            var player = '<div class = "kaltura-player-container">'
            +'<iframe  class="kaltura-player-iframe" src="'+url+'" allowfullscreen="true" allow="autoplay *; fullscreen *; encrypted-media *; camera *; microphone *;" frameborder="0"  height="402" width="608" ></iframe>'
            +'</div>';

            //add player to mediabox
            $("#mediabox-content").html(player);

            resizeoverlay();
            repositionitem();

            var itemnumber = 0;

            for (var i = 0; i < root.album.length; i++) {
                if (root.album[i].getAttribute('id') === target.getAttribute('id')) {
                    itemnumber = i;
                }
            }

            if (root.currentitemindex !== itemnumber) {
                $('#mediabox-content').empty();
            }

            setfullscreenimg();
            changeitem(itemnumber);
        };


        var setfullscreenimg = function() {
            if (!$('#mediabox-sidebar-actions .fullscreen')) {
                return;
            }
            var imgurl = M.util.image_url('fullscreen', 'mod_mediagallery');
            if (screenfull.isFullscreen) {
                imgurl = M.util.image_url('fullscreenexit', 'mod_mediagallery');
            }
            $('#mediabox-sidebar-actions .fullscreen').attr('src', imgurl);
        };

        var stop = function() {
            if (screenfull.isFullscreen) {
                screenfull.exit();
            }
            $('body').removeClass('noscroll mediaboxactive');
            root.overlay.css('display', '');
            root.mediabox.css('display', '');
        };

         /**
         * Update the modal body using given template and data.
         *
         * @method render
         * @param {String} template - The name of the template to render.
         * @param {Object} data - Data for template.
         * @param {Object} breadcrumbData - Data required for rending breadcrumbs.
         */
        var render = async function(template,data) {
            const renderPromise = await Templates.render(template, data);
            return renderPromise;
        };

    /*}, {
        NAME : 'moodle-mod_mediagallery-mediabox',
        ATTRS : {
            enablecomments: {
                value : true,
                validator: function(val) {
                    return Y.Lang.isBoolean(val);
                }
            },
            enablelikes: {
                value : true,
                validator: function(val) {
                    return Y.Lang.isBoolean(val);
                }
            },
            metainfouri: {
                value : '',
                validator: function(val) {
                    return Y.Lang.isString(val);
                }
            },
            dataidfield: {
                value : 'data-id',
                validator: function(val) {
                    return Y.Lang.isString(val);
                }
            },
            metainfodata: {
                value: {},
                validator: function(val) {
                    return Y.Lang.isObject(val);
                }
            }
        }*/
    /*
     * Pass options when class instantiated
     */
        root.construct(allowcomments, allowlikes);
    };

    var init = function(allowcomments, allowlikes) {
        return new MEDIABOX(allowcomments, allowlikes);
    };

    return {
        init: init
    };
});





