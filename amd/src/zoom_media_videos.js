import Ajax from 'core/ajax';
import Templates from 'core/templates';
import {debounce} from 'core/utils';

const SELECTORS = {
    SEARCH: '#zoom_video_search',
    LOADMORE: '#zoom_media_load_more'
};

const TEMPLATES = {
    ZOOM_VIDEO_LIST: 'local_mymedia/zoom_media_video_list'
};

export const init = (rootSelector, nextPageToken) => {
    const root = document.querySelector(rootSelector);
    registerEventListeners(root, nextPageToken);
};

const registerEventListeners = (root, nextPageToken) => {
    document.querySelector(SELECTORS.SEARCH).addEventListener('input', debounce(async(event) => {
        event.preventDefault();

        const search = event.target.value;
        if (search.lenght > 0 && search.length < 3) {
            return;
        }

        const response = await Ajax.call([{
            methodname: 'local_mymedia_zoom_media_get_user_videos',
            args: {search: search, nextpagetoken: ''}
        }])[0];

        const {html, js} = await Templates.renderForPromise(TEMPLATES.ZOOM_VIDEO_LIST, response);
        await Templates.replaceNodeContents(root, html, js);

    }, 200));

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(async (entry) => {
            if (entry.isIntersecting) {
                const response = await Ajax.call([{
                    methodname: 'local_mymedia_zoom_media_get_user_videos',
                    args: {search: '', nextpagetoken: nextPageToken}
                }])[0];

                console.log(response);

                nextPageToken = response.next_page_token;

                const {html, js} = await Templates.renderForPromise(TEMPLATES.ZOOM_VIDEO_LIST, response);
                await Templates.appendNodeContents(root, html, js);
            }
        });
    }, { threshold: 0.5 });

    const target = document.querySelector(SELECTORS.LOADMORE);
    observer.observe(target);
};
