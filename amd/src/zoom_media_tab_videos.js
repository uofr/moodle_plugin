import ZoomMediaEvents from 'local_mymedia/zoom_media_events';
import ZoomMediaTemplates from 'local_mymedia/zoom_media_templates';
import ZoomMediaLoading from 'local_mymedia/zoom_media_loading';
import ZoomMediaSelectors from 'local_mymedia/zoom_media_selectors';

import Notification from 'core/notification';
import Templates from 'core/templates';
import {getUserVideos} from 'local_mymedia/zoom_media_ajax';
import {subscribe} from 'core/pubsub';
import {getString} from 'core/str';

let _search = '';
let _nextPageToken = '';
let _totalRecords = 0;
let _loading = false;
let _root = null;

const SELECTORS = {
    ZOOM_MEDIA_VIDEO_LIST: '[data-region="zoom-media-video-list"]',
    ZOOM_MEDIA_VIDEO: '[data-item="zoom_video"]',
    SEARCH_HEADER: '[data-region="zoom-video-search-header"]'
};

const getSearch = () => {
    return _search;
};

const setSearch = (search) => {
    _search = search;
};

const setNextPageToken = (nextPageToken) => {
    _nextPageToken = nextPageToken;
};

const getNextPageToken = () => {
    return _nextPageToken;
};

const setRoot = (rootSelector) => {
    _root = document.querySelector(rootSelector);
};

const getRoot = () => {
    return _root;
};

const setTotalRecords = (totalRecords) => {
    _totalRecords = totalRecords;
};

const getTotalRecords = () => {
    return _totalRecords;
};

const setLoading = (loading) => {
    _loading = loading;
};

const isLoading = () => {
    return _loading;
};

export const init = (rootSelector) => {
    setRoot(rootSelector);
    renderVideos();
    registerEventListeners();
};

const registerEventListeners = () => {
    subscribe(ZoomMediaEvents.ZOOM_MEDIA_SEARCH, (searchText) => {
        setSearch(searchText);
        setNextPageToken('');
        renderVideos();
    });

    subscribe(ZoomMediaEvents.ZOOM_MEDIA_SEARCH_RESET, () => {
        setSearch('');
        setNextPageToken('');
        renderVideos();
    });

    subscribe(ZoomMediaEvents.ZOOM_MEDIA_LOAD_MORE, () => {
        const nextPageToken = getNextPageToken();
        const totalRecords = getTotalRecords();
        const totalLoaded = countLoadedVideos();

        if (nextPageToken == '' || totalLoaded == totalRecords) {
            return;
        }

        renderNextPage();
    });
};

const renderVideos = () => {
    const root = getRoot();
    const videoListArea = root.querySelector(SELECTORS.ZOOM_MEDIA_VIDEO_LIST);

    loadVideos(
        videoListArea,
        Templates.replaceNodeContents,
        root,
        ZoomMediaLoading.renderLoadingOverlay
    );
};

const renderNextPage = () => {
    const root = getRoot();
    const videoListArea = root.querySelector(SELECTORS.ZOOM_MEDIA_VIDEO_LIST);
    const loadMoreBox = root.querySelector(ZoomMediaSelectors.LOAD_MORE);

    loadVideos(
        videoListArea,
        Templates.appendNodeContents,
        loadMoreBox,
        ZoomMediaLoading.renderLoadingSpinner
    );
};

const loadVideos = async (renderArea, renderCallback, loadingArea, loadingCallback) => {
    if (isLoading()) {
        return;
    }

    const search = getSearch();
    const nextPageToken = getNextPageToken();
    setLoading(true);

    try {
        loadingCallback(loadingArea);

        const response = await getUserVideos(search, nextPageToken);
        console.log(response);
        setTotalRecords(response.total_records);
        if (response.next_page_token) {
            setNextPageToken(response.next_page_token);
        }
        else {
            setNextPageToken('');
        }

        const {html, js} = await Templates.renderForPromise(ZoomMediaTemplates.ZOOM_MEDIA_VIDEO_LIST, response);
        renderCallback(renderArea, html, js);

        if (search == '') {
            const searchHeader = document.querySelector(SELECTORS.SEARCH_HEADER);
            searchHeader.textContent = '';
        }
        else {
            const searchHeader = document.querySelector(SELECTORS.SEARCH_HEADER);
            const totalRecords = getTotalRecords();
            const stringKey = totalRecords == 1 ? 'showing_result_for' : 'showing_results_for';
            const stringComponent = 'local_mymedia';
            const stringVariables = {total: totalRecords, search: search};
            const searchHeaderText = await getString(stringKey, stringComponent, stringVariables);
            searchHeader.textContent = searchHeaderText;
        }
    }
    catch (error) {
        Notification.exception(error);
    }
    finally {
        ZoomMediaLoading.removeLoadingSpinners(loadingArea);
        setLoading(false);
    }
};

const countLoadedVideos = () => {
    const root = getRoot();
    const videoList = root.querySelector(SELECTORS.ZOOM_MEDIA_VIDEO_LIST);
    const loadedVideos = videoList.querySelectorAll(SELECTORS.ZOOM_MEDIA_VIDEO);

    return loadedVideos.length;
};