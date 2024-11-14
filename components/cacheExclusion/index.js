import { Button, Container, TextareaField } from "@newfold/ui-component-library";

const CacheExclusion = ({ methods, constants }) => {
    const [ isEdited, setIsEdited ] = methods.useState(false);
	const [ isError, setIsError ] = methods.useState(false);
	const [ isSaved, setIsSaved ] = methods.useState(false);
    const [ currentValue, setCurrentValue ] = methods.useState(methods.NewfoldRuntime.sdk.cacheExclusion);
    const [ cacheExclusion, setCacheExclusion ] = methods.useState(methods.NewfoldRuntime.sdk.cacheExclusion);
	const apiUrl = methods.NewfoldRuntime.createApiUrl("/newfold-ecommerce/v1/cacheexclusion/update");

    const handleCacheExclusionChange = (e) => {
        if( e.target.value !== currentValue  ) {
            setIsEdited(true);            
        }else{
            setIsEdited(false);
        }
        setCurrentValue( e.target.value );
    }

    const handlingSaveButton = () => {
		methods.apiFetch({
			url: apiUrl,
			method: "POST",
			data: {cacheExclusion: currentValue}
		}).then((result)=>{
			  setIsSaved(true);
              setCacheExclusion(currentValue);
			  setIsEdited(false)
		}).catch((error) => {     
			setIsError(error.message);
		});
    };

	methods.useUpdateEffect(() => {
        methods.setStore({
            ...constants.store,
            CacheExclusion: cacheExclusion,
        });

        methods.makeNotice(
            "cache-exlusion-notice", 
            constants.text.cacheExclusionTitle,
            !isError ? constants.text.cacheExclusionSaved : isError,
            !isError ? "success" : "error",
            5000
        );
    }, [ isSaved, isError]);

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
				rows="1"
            />
			{isEdited && 
				<Button
					variant="secondary"
					className="save-cache-exclusion-button"
					disabled={isEdited ? false : true}
					onClick={handlingSaveButton}
				>
					{constants.text.cacheExclusionSaveButton}
				</Button>
			}
            
        </Container.SettingsField>
            
    );
;}

export default CacheExclusion;