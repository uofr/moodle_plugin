import ZoomMediaEvents from 'local_mymedia/zoom_media_events';
import ZoomMediaTemplates from 'local_mymedia/zoom_media_templates';
import ZoomMediaLoading from 'local_mymedia/zoom_media_loading';
import ZoomMediaSelectors from 'local_mymedia/zoom_media_selectors';

import Notification from 'core/notification';
import Templates from 'core/templates';
import {getUserChannels, getChannelInfo, getChannelVideos} from 'local_mymedia/zoom_media_ajax';
import {subscribe} from 'core/pubsub';
import {getString} from 'core/str';

let _userSearch = '';
let _nextPageToken = '';
let _root = null;
let _loading = false;

const SELECTORS = {
    ZOOM_MEDIA_CHANNEL_LIST: '[data-region="zoom-media-channel-list"]',
    ZOOM_MEDIA_CHANNEL: '[data-item="zoom_channel"]',
    ZOOM_MEDIA_CHANNEL_THUMBNAIL: '[data-region="zoom-channel-thumbnail"]',
    ZOOM_MEDIA_CHANNEL_VIDEO_COUNT: '[data-region="zoom-channel-video-count"]',
    SEARCH_HEADER: '[data-region="zoom-channel-search-header"]',
};

const getUserSearch = () => {
    return _userSearch;
};

const setUserSearch = (userSearch) => {
    _userSearch = userSearch;
};

const getNextPageToken = () => {
    return _nextPageToken;
};

const setNextPageToken = (nextPageToken) => {
    _nextPageToken = nextPageToken;
};

const getRoot = () => {
    return _root;
};

const setRoot = (rootSelector) => {
    _root = document.querySelector(rootSelector);
};

const isLoading = () => {
    return _loading;
};

const setLoading = (loading) => {
    _loading = loading;
};

export const init = (rootSelector) => {
    setRoot(rootSelector);
    renderChannels();
    registerEventListeners();
};

const registerEventListeners = () => {
    subscribe(ZoomMediaEvents.ZOOM_MEDIA_SEARCH, async (searchText) => {
        setUserSearch(searchText);
        setNextPageToken('');
        renderChannels();
    });

    subscribe(ZoomMediaEvents.ZOOM_MEDIA_SEARCH_RESET, () => {
        setUserSearch('');
        setNextPageToken('');
        renderChannels();
    });

    subscribe(ZoomMediaEvents.ZOOM_MEDIA_LOAD_MORE, () => {
        const nextPageToken = getNextPageToken();

        if (nextPageToken == '') {
            return;
        }

        renderNextPage();
    });
};

const renderChannels = () => {
    const root = getRoot();
    const channelListArea = root.querySelector(SELECTORS.ZOOM_MEDIA_CHANNEL_LIST);

    loadChannels(
        channelListArea,
        Templates.replaceNodeContents,
        root,
        ZoomMediaLoading.renderLoadingOverlay
    );
};

const renderNextPage = () => {
    const root = getRoot();
    const channelListArea = root.querySelector(SELECTORS.ZOOM_MEDIA_CHANNEL_LIST);
    const loadMoreBox = root.querySelector(ZoomMediaSelectors.LOAD_MORE);

    loadChannels(
        channelListArea,
        Templates.appendNodeContents,
        loadMoreBox,
        ZoomMediaLoading.renderLoadingSpinner
    );
};

const loadChannels = async (renderArea, renderCallback, loadingArea, loadingCallback) => {
    if (isLoading()) {
        return;
    }

    const nextPageToken = getNextPageToken();
    const userSearch = getUserSearch();
    setLoading(true);

    try {
        loadingCallback(loadingArea);

        const response = await getUserChannels(nextPageToken, userSearch);

        if (response.next_page_token) {
            setNextPageToken(response.next_page_token);
        }
        else {
            setNextPageToken('');
        }

        if (response.channels.length) {
            const channelIds = response.channels.map(channel => channel.channel_id);
            loadThumbnails(channelIds);
            getChannelVideoCounts(channelIds);
        }

        const {html, js} = await Templates.renderForPromise(ZoomMediaTemplates.ZOOM_MEDIA_CHANNEL_LIST, response);
        renderCallback(renderArea, html, js);

        if (userSearch == '') {
            const searchHeader = document.querySelector(SELECTORS.SEARCH_HEADER);
            searchHeader.textContent = '';
        }
        else if (response.error) {
            const searchHeader = document.querySelector(SELECTORS.SEARCH_HEADER);
            searchHeader.textContent = response.error;
        }
        else {
            const searchHeader = document.querySelector(SELECTORS.SEARCH_HEADER);
            const stringKey = 'showing_channel_results_for';
            const stringComponent = 'local_mymedia';
            const stringVariables = userSearch;
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

const loadThumbnails = async (channelIds) => {
    const channelInfo = await Promise.all(getChannelInfo(channelIds));

    channelInfo.forEach((channel) => {
        const channelSelector = `[data-channel-id="${channel.channel_id}"]`;
        const thumbnailSelector = `${channelSelector} ${SELECTORS.ZOOM_MEDIA_CHANNEL_THUMBNAIL}`;
        const thumbnailRegion = document.querySelector(thumbnailSelector);
        if (!thumbnailRegion) {
            return;
        }

        thumbnailRegion.innerHTML = '';

        const thumbnails = channel.thumbnails ? channel.thumbnails : null;
        if (!thumbnails || thumbnails.length == 0) {
            return;
        }

        const thumbnailImage = document.createElement('img');
        const thumbnailSrc = thumbnails.shift().file_url;
        thumbnailImage.src = thumbnailSrc;
        thumbnailImage.classList.add('card-img-top');
        thumbnailRegion.appendChild(thumbnailImage);
    });
};

const getChannelVideoCounts = async (channelIds) => {
    const channelVideos = await Promise.all(getChannelVideos(channelIds));
    channelVideos.forEach(async (response) => {
        const channelSelector = `[data-channel-id="${response.channel_id}"]`;
        const videoCountSelector = `${channelSelector} ${SELECTORS.ZOOM_MEDIA_CHANNEL_VIDEO_COUNT}`;
        const videoCountRegion = document.querySelector(videoCountSelector);
        videoCountRegion.textContent = await getString('channel_video_count', 'local_mymedia', response.total_records);
    });
};