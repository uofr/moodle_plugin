import Templates from 'core/templates';
import ZoomMediaTemplates from 'local_mymedia/zoom_media_templates';
import ZoomMediaSelectors from 'local_mymedia/zoom_media_selectors';

export default {
    renderLoadingSpinner: (region) => {
        const template = ZoomMediaTemplates.ZOOM_MEDIA_LOADING_SPINNER;
        renderLoading(template, region);
    },

    renderLoadingOverlay: (region) => {
        const template = ZoomMediaTemplates.ZOOM_MEDIA_LOADING_OVERLAY;
        renderLoading(template, region);
    },

    removeLoadingSpinners: (region) => {
        const loadingSpinners = region.querySelectorAll(ZoomMediaSelectors.LOADING_SPINNER);
        loadingSpinners.forEach((spinner) => {
            if (spinner) {
                spinner.remove();
            }
        });
    }
};

const renderLoading = async (loadTemplate, region) => {
    const {html, js} = await Templates.renderForPromise(loadTemplate, {});
    Templates.appendNodeContents(region, html, js);
};
