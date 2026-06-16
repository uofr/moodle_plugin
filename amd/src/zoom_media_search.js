import ZoomMediaEvents from 'local_mymedia/zoom_media_events';
import {publish} from 'core/pubsub';

const SELECTORS = {
    SEARCH_INPUT: '[name="zoom-media-search-text"]',
    SEARCH_RESET: '[type="reset"]'
};

export const init = (rootSelector) => {
    const root = document.querySelector(rootSelector);
    registerEventListeners(root);
};

const registerEventListeners = (root) => {
    root.addEventListener('submit', (event) => {
        event.preventDefault();

        const searchInput = root.querySelector(SELECTORS.SEARCH_INPUT);
        const searchReset = root.querySelector(SELECTORS.SEARCH_RESET);
        if (searchInput == '') {
            searchReset.style.display = 'none';
        }
        else {
            searchReset.style.display = 'block';
        }

        publish(ZoomMediaEvents.ZOOM_MEDIA_SEARCH, searchInput.value);
    });

    root.addEventListener('reset', () => {
        const searchReset = root.querySelector(SELECTORS.SEARCH_RESET);
        searchReset.style.display = 'none';
        publish(ZoomMediaEvents.ZOOM_MEDIA_SEARCH_RESET, {});
    });
};