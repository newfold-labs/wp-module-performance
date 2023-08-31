import { Button } from "@newfold/ui-component-library";

const ClearCache = ({ methods, constants, Components }) => {

    const clearCache = () => {
        methods.newfoldPurgeCacheApiFetch(
            {}, 
            methods.setError, 
            (response) => {
                methods.makeNotice(
                    "disable-old-posts-comments-notice", 
                    constants.text.clearCacheNoticeTitle,
                    null,
                    "success",
                    5000
                );
            }
        );
    };

    return (
        <Components.SectionSettings
            title={constants.text.clearCacheTitle}
            description={constants.text.clearCacheDescription}
        >
            <Button
                variant="secondary"
                className="clear-cache-button"
                disabled={constants.store.cacheLevel > 0 ? false : true}
                onClick={clearCache}
                >
                {constants.text.clearCacheButton}
            </Button>
        </Components.SectionSettings>
            
    );
;}

export default ClearCache;