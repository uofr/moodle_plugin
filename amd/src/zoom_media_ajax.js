import Ajax from 'core/ajax';

export const getUserVideos = (search, nextPageToken) => {
    return Ajax.call([{
        methodname: 'local_mymedia_zoom_media_get_user_videos',
        args: {
            search: search,
            nextpagetoken: nextPageToken
        }
    }])[0];
};

export const getUserChannels = (nextPageToken, userSearch) => {
    return Ajax.call([{
        methodname: 'local_mymedia_zoom_media_get_channels',
        args: {
            next_page_token: nextPageToken,
            user_search: userSearch
        }
    }])[0];
};

export const getChannelInfo = (channelIds) => {
    const requests = channelIds.map((channelId) => {
        return {
            methodname: 'local_mymedia_zoom_media_get_channel_info',
            args: {
                channel_id: channelId
            }
        };
    });
    return Ajax.call(requests);
};

export const getChannelVideos = (channelIds) => {
    const requests = channelIds.map((channelId) => {
        return {
            methodname: 'local_mymedia_zoom_media_get_channel_videos',
            args: {
                channel_id: channelId
            }
        };
    });
    return Ajax.call(requests);
};