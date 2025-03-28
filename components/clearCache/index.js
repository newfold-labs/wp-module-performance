import { Button, Container } from '@newfold/ui-component-library';

const ClearCache = ( { methods, constants } ) => {

	const apiUrl = methods.NewfoldRuntime.createApiUrl(
		'/newfold-performance/v1/cache/settings'
	);

	const { store, setStore } = methods.useContext( methods.AppStore );

	const clearCache = () => {


		methods
			.apiFetch( {
				url: apiUrl,
				method: 'DELETE',
			} )
			.then( () => {
				methods.makeNotice(
					'disable-old-posts-comments-notice',
					constants.text.clearCacheNoticeTitle,
					null,
					'success',
					5000
				);
			} )
			.catch( ( error ) => {
				methods.setError( error )
			} );
	};

	const cacheLevel = store.cacheLevel ?? methods.NewfoldRuntime.sdk.cache.level;


	return (
		<Container.SettingsField
			title={ constants.text.clearCacheTitle }
			description={ constants.text.clearCacheDescription }
		>
			<Button
				variant="secondary"
				className="clear-cache-button"
				disabled={ cacheLevel > 0 ? false : true }
				onClick={ clearCache }
			>
				{ constants.text.clearCacheButton }
			</Button>
		</Container.SettingsField>
	);
};

export default ClearCache;
