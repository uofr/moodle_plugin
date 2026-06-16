import ZoomMediaEvents from 'local_mymedia/zoom_media_events';

import {publish} from 'core/pubsub';

export const init = (rootSelector) => {
    const root = document.querySelector(rootSelector);
    registerEventListeners(root);
};

const registerEventListeners = (root) => {
    const observerSettings = {threshold: 0.5};
    const observer = new IntersectionObserver(intersectFunction, observerSettings);

    observer.observe(root);
};

const intersectFunction = (entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            publish(ZoomMediaEvents.ZOOM_MEDIA_LOAD_MORE, {});
        }
    });
};