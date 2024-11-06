import { Button, Container, TextareaField } from "@newfold/ui-component-library";

const CacheExclusion = ({ methods, constants }) => {

    const [ currentValue, setCurrentValue ] = methods.useState(constants.store.cacheExclusion);
    const [ isEdited, setIsEdited ] = methods.useState(false);
    const [ cacheExclusion, setCacheExclusion ] = methods.useState(constants.store.cacheExclusion);


    const handleCacheExclusionChange = (e) => {
        if( e.target.value !== cacheExclusion  ) {
            setIsEdited(true);            
        }else{
            setIsEdited(false);
        }
        setCurrentValue( e.target.value );
    }
    

    const handlingSaveButton = () => {
        methods.newfoldSettingsApiFetch(
            { cacheExclusion: currentValue }, 
            methods.setError, (response) => {
                setCacheExclusion( currentValue )
                methods.makeNotice(
                    "disable-old-posts-comments-notice", 
                    constants.text.cacheExclusionSaved,
                    null,
                    "success",
                    5000
                );
                setIsEdited(false);
            }
        );
        
    };

    return (
        <Container.SettingsField
            title={constants.text.cacheExclusionTitle}
            description={constants.text.cacheExclusionDescription}
        >
            <TextareaField
                id="cache-exclusion"
                name="cache-xxclusion"
                onChange={ handleCacheExclusionChange }
                value={currentValue}
            />
            <Button
                variant="secondary"
                className="save-cache-exclusion-button"
                disabled={isEdited ? false : true}
                onClick={handlingSaveButton}
            >
                {constants.text.cacheExclusionSaveButton}
            </Button>
            
        </Container.SettingsField>
            
    );
;}

export default CacheExclusion;