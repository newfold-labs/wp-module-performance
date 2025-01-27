// WordPress
import apiFetch from '@wordpress/api-fetch';

// Newfold
import { ToggleField, Container } from '@newfold/ui-component-library';
import { NewfoldRuntime } from '@newfold/wp-module-runtime';

const Skip404 = ( { methods, constants } ) => {
	const [ skip404, setSkip404 ] = methods.useState(
		NewfoldRuntime.sdk.performance.skip404
	);

	const getSkip404NoticeTitle = () => {
		return constants.text.skip404NoticeTitle;
	};

	const handleSkip404Change = () => {
		const value = ! skip404;
		apiFetch( {
			path: 'newfold-performance/v1/settings',
			method: 'POST',
			data: {
				field: {
					id: 'skip404',
					value,
				},
			},
		} )
			.then( () => {
				methods.makeNotice(
					'skip404-change-notice',
					getSkip404NoticeTitle(),
					'',
					'success',
					5000
				);
			} )
			.catch( () => {
				methods.makeNotice(
					'skip404-change-notice',
					constants.text.optionNotSet,
					'',
					'error',
					5000
				);
			} );

		setSkip404( value );
	};

	return (
		<Container.SettingsField
			title={ constants.text.skip404Title }
			description={ constants.text.skip404Description }
		>
			<ToggleField
				id="skip-404"
				label={ constants.text.skip404OptionLabel }
				checked={ skip404 }
				onChange={ () => handleSkip404Change( skip404, setSkip404 ) }
			/>
		</Container.SettingsField>
	);
};

export default Skip404;
