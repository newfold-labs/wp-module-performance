import { Button, Container, TextareaField } from "@newfold/ui-component-library";

const CacheExclusion = ({ methods, constants }) => {
    const [ isEdited, setIsEdited ] = methods.useState(false);
	const [ isError, setIsError ] = methods.useState(false);
	const [ isSaved, setIsSaved ] = methods.useState(false);
    const [ cacheExclusion, setCacheExclusion ] = methods.useState(methods.NewfoldRuntime.sdk.cacheExclusion);
	const apiUrl = methods.NewfoldRuntime.createApiUrl("/newfold-ecommerce/v1/cacheexclusion/update");

    const handleCacheExclusionChange = (e) => {
        if( e.target.value !== cacheExclusion  ) {
            setIsEdited(true);            
        }else{
            setIsEdited(false);
        }
        setCacheExclusion( e.target.value );
    }

    const handlingSaveButton = () => {
		methods.apiFetch({
			url: apiUrl,
			method: "POST",
			data: {cacheExclusion: cacheExclusion}
		}).then((result)=>{
			  setIsSaved(true);
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
                value={cacheExclusion}
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