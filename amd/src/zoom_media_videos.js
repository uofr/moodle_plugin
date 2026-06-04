import Ajax from 'core/ajax';
import Templates from 'core/templates';
import {debounce} from 'core/utils';
import Notification from 'core/notification';
import {getString} from 'core/str';

let _nextPageToken;
let _search;
let _totalRecords;
let _root;
let _loadingMoreVideos;

const SELECTORS = {
    SEARCH: '#zoom_video_search',
    CLEAR_SEARCH: '[data-action="zoom_video_clear_search"]',
    SEARCH_HEADER_AREA: '#zoom_video_search_header',
    SEARCH_HEADER: '.zoom-video-search-header',
    LOADMORE: '#zoom_media_load_more',
    VIDEO: '[data-item="zoom_video"]',
    LOADINGICON: '.zoom-media-loader',
    LOADINGCOVER: '.zoom-media-loading-cover',
    CONTAINER: '#zoom_videos_container'
};

const TEMPLATES = {
    ZOOM_VIDEO_LIST: 'local_mymedia/zoom_media_video_list',
    LOADING: 'local_mymedia/zoom_media_loader',
    LOADING_COVER: 'local_mymedia/zoom_media_loading_cover'
};

export const init = (rootSelector, nextPageToken, totalRecords) => {
    _root = document.querySelector(rootSelector);
    _nextPageToken = nextPageToken;
    _totalRecords = totalRecords;
    _search = '';
    registerEventListeners();
};

const registerEventListeners = () => {
    document.querySelector(SELECTORS.SEARCH).addEventListener('input', debounce(async(event) => {
        event.preventDefault();

        try {
            const search = event.target.value;
            _search = search;
            if (_search.length > 0 && _search.length < 3) {
                return;
            }

            const {html, js} = await Templates.renderForPromise(TEMPLATES.LOADING_COVER, {});
            await Templates.appendNodeContents(document.querySelector(SELECTORS.CONTAINER), html, js);
            await searchVideos();

            if (_search != '') {
                const clearSearch = document.querySelectorAll(SELECTORS.CLEAR_SEARCH);
                const searchHeader = document.querySelector(SELECTORS.SEARCH_HEADER_AREA);
                const searchHeaderText = searchHeader.querySelector(SELECTORS.SEARCH_HEADER);
                const searchString = await getString(
                    'showing_results_for',
                    'local_mymedia',
                    {total: _totalRecords, search: _search}
                );
                clearSearch.forEach((button) => {
                    button.style.display = 'inline';
                });
                searchHeader.style.display = 'inline-block';
                searchHeaderText.textContent = searchString;
            }
        }
        catch (error) {
            Notification.exception(error);
        }
        finally {
            document.querySelector(SELECTORS.CONTAINER).querySelector(SELECTORS.LOADINGCOVER).remove();
        }
    }, 500));

    const buttons = document.querySelectorAll(SELECTORS.CLEAR_SEARCH);
    buttons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();

            const searchbox = document.querySelector(SELECTORS.SEARCH);
            const clearSearch = document.querySelectorAll(SELECTORS.CLEAR_SEARCH);
            const searchHeader = document.querySelector(SELECTORS.SEARCH_HEADER_AREA);
            const inputEvent = new Event('input');

            searchbox.value = '';
            searchbox.dispatchEvent(inputEvent);

            clearSearch.forEach((button) => {
                button.style.display = 'none';
            });
            searchHeader.style.display = 'none';
        });
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(async (entry) => {
            if (entry.isIntersecting) {
                if (_loadingMoreVideos) {
                    return;
                }
                try {
                    _loadingMoreVideos = true;
                    const {html, js} = await Templates.renderForPromise(TEMPLATES.LOADING, {});
                    await Templates.appendNodeContents(document.querySelector(SELECTORS.LOADMORE), html, js);
                    await loadMoreVideos();
                }
                catch (error) {
                    Notification.exception(error);
                }
                finally {
                    _loadingMoreVideos = false;
                    document.querySelector(SELECTORS.LOADMORE).querySelector(SELECTORS.LOADINGICON).remove();
                }

            }
        });
    }, { threshold: 0.5 });

    const target = document.querySelector(SELECTORS.LOADMORE);
    observer.observe(target);
};

const loadMoreVideos = async () => {
    const totalVideos = document.querySelectorAll(SELECTORS.VIDEO).length;
    if (totalVideos == _totalRecords) {
        return;
    }

    const response = await Ajax.call([{
        methodname: 'local_mymedia_zoom_media_get_user_videos',
        args: {search: _search, nextpagetoken: _nextPageToken}
    }])[0];

    _nextPageToken = response.next_page_token;
    _totalRecords = response.total_records;

    const {html, js} = await Templates.renderForPromise(TEMPLATES.ZOOM_VIDEO_LIST, response);
    return Templates.appendNodeContents(_root, html, js);
};

const searchVideos = async () => {
    const response = await Ajax.call([{
        methodname: 'local_mymedia_zoom_media_get_user_videos',
        args: {search: _search, nextpagetoken: ''}
    }])[0];

    _nextPageToken = response.next_page_token;
    _totalRecords = response.total_records;

    const {html, js} = await Templates.renderForPromise(TEMPLATES.ZOOM_VIDEO_LIST, response);
    return Templates.replaceNodeContents(_root, html, js);
};