import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString} from 'core/str';

let _root;
let _nextPageToken;
let _loadingMoreChannels;
let _search;

const SELECTORS = {
    LOADMORE: '#zoom_media_load_more',
    LOADINGICON: '.zoom-media-loader',
    LOADINGCOVER: '.zoom-media-loading-cover',
    SEARCHFORM: '#zoom_channel_search_form',
    SEARCHTEXT: '#zoom_channel_search_field',
    CHANNELSAREA: '[data-region="zoom-channels-area"]',
    CLEAR_SEARCH: '[data-action="zoom_channel_clear_search"]',
    SEARCH_HEADER_AREA: '#zoom_channel_search_header',
    SEARCH_HEADER_TEXT: '.zoom-channel-search-header'
};

const TEMPLATES = {
    CHANNEL_LIST: 'local_mymedia/zoom_media_channel_list',
    LOADING: 'local_mymedia/zoom_media_loader',
    LOADING_COVER: 'local_mymedia/zoom_media_loading_cover'
};

export const init = async (rootSelector) => {
    _root = document.querySelector(rootSelector);
    _nextPageToken = '';
    _loadingMoreChannels = false;
    _search = '';
    initChannels();
    registerEventListeners();
};

const registerEventListeners = () => {
    const searchForm = document.querySelector(SELECTORS.SEARCHFORM);
    if (searchForm) {
        searchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const searchText = document.querySelector(SELECTORS.SEARCHTEXT).value;
            _search = searchText;
            if (_search == '') {
                initChannels();
                document.querySelector(SELECTORS.CLEAR_SEARCH).style.display = 'none';
                const searchheader = document.querySelector(SELECTORS.SEARCH_HEADER_AREA);
                searchheader.style.display = 'none';
                searchheader.querySelector(SELECTORS.SEARCH_HEADER_TEXT).textContent = '';
            }
            else {
                document.querySelector(SELECTORS.CLEAR_SEARCH).style.display = 'inline';
                searchChannels();
            }
        });
    }

    const searchClear = document.querySelectorAll(SELECTORS.CLEAR_SEARCH);
    if (searchClear) {
        searchClear.forEach((clearbutton) => {
            clearbutton.addEventListener('click', (event) => {
                event.preventDefault();
                _search = '';
                document.querySelector(SELECTORS.SEARCHTEXT).value = '';
                initChannels();
                document.querySelector(SELECTORS.CLEAR_SEARCH).style.display = 'none';
                const searchheader = document.querySelector(SELECTORS.SEARCH_HEADER_AREA);
                searchheader.style.display = 'none';
                searchheader.querySelector(SELECTORS.SEARCH_HEADER_TEXT).textContent = '';
            });
        });
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(async (entry) => {
            if (entry.isIntersecting) {
                loadMoreChannels();
            }
        });
    }, { threshold: 0.5 });

    const target = document.querySelector(SELECTORS.LOADMORE);
    observer.observe(target);
};

const initChannels = async () => {
    const loadMoreArea = document.querySelector(SELECTORS.LOADMORE);
    try {
        const {html, js} = await Templates.renderForPromise(TEMPLATES.LOADING, {});
        Templates.appendNodeContents(loadMoreArea, html, js);

        const response = await getChannels('');
        if (response.next_page_token) {
            _nextPageToken = response.next_page_token;
        }
        else {
            _nextPageToken = '';
        }

        const responses = await getChannelData(response.channels);
        const channelDataMap = getChannelDataMap(responses);

        renderChannels(response, channelDataMap);
    }
    catch (error) {
        Notification.exception(error);
    }
    finally {
        loadMoreArea.querySelector(SELECTORS.LOADINGICON).remove();
    }
};

const loadMoreChannels = async () => {
    if (_loadingMoreChannels) {
        return;
    }

    if (_nextPageToken == '') {
        return;
    }

    const loadMoreArea = document.querySelector(SELECTORS.LOADMORE);

    try {
        _loadingMoreChannels = true;

        const {html, js} = await Templates.renderForPromise(TEMPLATES.LOADING, {});
        Templates.appendNodeContents(document.querySelector(SELECTORS.LOADMORE), html, js);

        const response = await getChannels(_nextPageToken);
        if (response.next_page_token) {
            _nextPageToken = response.next_page_token;
        }
        else {
            _nextPageToken = '';
        }

        const responses = await getChannelData(response.channels);
        const channelDataMap = getChannelDataMap(responses);

        renderMoreChannels(response, channelDataMap);
    }
    catch (error) {
        Notification.exception(error);
    }
    finally {
        _loadingMoreChannels = false;
        loadMoreArea.querySelector(SELECTORS.LOADINGICON).remove();
    }
};

const searchChannels = async () => {
    if (_loadingMoreChannels) {
        return;
    }

    const channelarea = document.querySelector(SELECTORS.CHANNELSAREA);

    try {
        _loadingMoreChannels = true;

        const {html, js} = await Templates.renderForPromise(TEMPLATES.LOADING_COVER, {});
        Templates.appendNodeContents(channelarea, html, js);

        const {response, username, email} = await getChannelsSearch(_search, _nextPageToken);
        if (!response) {
            return;
        }

        if (response.next_page_token) {
            _nextPageToken = response.next_page_token;
        }
        else {
            _nextPageToken = '';
        }

        const responses = await getChannelData(response.channels);
        const channelDataMap = getChannelDataMap(responses);

        renderChannels(response, channelDataMap);

        let resultsString;
        if (response.total_records == 1) {
            resultsString = await getString(
                'showing_result_for',
                'local_mymedia',
                {
                    total: response.total_records,
                    search: `${username}(${email})`
                }
            );
        }
        else {
            resultsString = await getString(
                'showing_results_for',
                'local_mymedia',
                {
                    total: response.total_records,
                    search: `${username}(${email})`
                }
            );
        }

        const searchheader = document.querySelector(SELECTORS.SEARCH_HEADER_AREA);
        searchheader.style.display = 'block';
        searchheader.querySelector(SELECTORS.SEARCH_HEADER_TEXT).textContent = resultsString;
    }
    catch (error) {
        Notification.exception(error);
    }
    finally {
        _loadingMoreChannels = false;
        channelarea.querySelector(SELECTORS.LOADINGCOVER).remove();
    }
};

const getChannels = (nextPageToken) => {
    return Ajax.call([{
        methodname: 'local_mymedia_zoom_media_get_channels',
        args: {next_page_token: nextPageToken}
    }])[0];
};

const getChannelsSearch = (search, nextPageToken) => {
    return Ajax.call([{
        methodname: 'local_mymedia_zoom_media_search_channels',
        args: {search: search, next_page_token: nextPageToken}
    }])[0];
};

const getChannelData = (channels) => {
    const channelInfoRequests = channels.map((channel) => {
        return {
            methodname: 'local_mymedia_zoom_media_get_channel_info',
            args: {channelid: channel.channel_id}
        };
    });

    const channelVideoRequests = channels.map((channel) => {
        return {
            methodname: 'local_mymedia_zoom_media_get_channel_videos',
            args: {channelid: channel.channel_id}
        };
    });

    const channelCourseInfoRequests = channels.map((channel) => {
        return {
            methodname: 'local_mymedia_zoom_media_get_channel_course_info',
            args: {channelid: channel.channel_id}
        };
    });

    return Promise.all(Ajax.call([...channelInfoRequests, ...channelVideoRequests, ...channelCourseInfoRequests]));
};

const getChannelDataMap = (responses) => {
    const channelData = new Map();

    responses.forEach((response) => {
        const existing = channelData.get(response.channel_id);
        if (!existing) {
            channelData.set(response.channel_id, response);
        } else {
            channelData.set(response.channel_id, {...existing, ...response});
        }
    });

    return channelData;
};

const renderChannels = async (response, channelDataMap) => {
    const templateContext = getTemplateContext(response.channels, channelDataMap);
    const {html, js} = await Templates.renderForPromise(TEMPLATES.CHANNEL_LIST, templateContext);
    Templates.replaceNodeContents(_root, html, js);
};

const renderMoreChannels = async (response, channelDataMap) => {
    const templateContext = getTemplateContext(response.channels, channelDataMap);
    const {html, js} = await Templates.renderForPromise(TEMPLATES.CHANNEL_LIST, templateContext);
    Templates.appendNodeContents(_root, html, js);
};

const getTemplateContext = (channels, channelDataMap) => {
    const templateContext = channels.map((channel) => {
        const otherData = channelDataMap.get(channel.channel_id);
        return {
            ...channel,
            thumbnailurl: otherData.thumbnails[0].file_url,
            channellink: otherData.channel_link,
            courselink: otherData.courselink,
            coursename: otherData.coursefullname,
            videocount: otherData.total_records
        };
    });

    return {channels: templateContext};
};
