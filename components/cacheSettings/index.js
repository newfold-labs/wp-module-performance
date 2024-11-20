// WordPress
import apiFetch from '@wordpress/api-fetch';

// Newfold
import { Checkbox, Container, RadioGroup } from '@newfold/ui-component-library';
import { NewfoldRuntime } from '@newfold-labs/wp-module-runtime';

const CacheSettings = ( { methods, constants, Components } ) => {
	const [ cacheLevel, setCacheLevel ] = methods.useState(
		constants.store.cacheLevel
	);
	const [ skip404, setSkip404 ] = methods.useState(
		NewfoldRuntime.sdk.skip404
	);

	const cacheOptions = [
		{
			label: constants.text.cacheLevel0Label,
			description:
				constants.text.cacheLevel0Description +
				constants.text.cacheLevel0Recommendation,
			value: 0,
			notice: constants.text.cacheLevel0NoticeText,
		},
		{
			label: constants.text.cacheLevel1Label,
			description:
				constants.text.cacheLevel1Description +
				constants.text.cacheLevel1Recommendation,
			value: 1,
			notice: constants.text.cacheLevel1NoticeText,
		},
		{
			label: constants.text.cacheLevel2Label,
			description:
				constants.text.cacheLevel2Description +
				constants.text.cacheLevel2Recommendation,
			value: 2,
			notice: constants.text.cacheLevel2NoticeText,
		},
		{
			label: constants.text.cacheLevel3Label,
			description:
				constants.text.cacheLevel3Description +
				constants.text.cacheLevel3Recommendation,
			value: 3,
			notice: constants.text.cacheLevel3NoticeText,
		},
	];

	const getCacheLevelNoticeTitle = () => {
		return constants.text.cacheLevelNoticeTitle;
	};

	const getCacheLevelNoticeText = () => {
		return cacheOptions[ cacheLevel ].notice;
	};

	const getSkip404NoticeTitle = () => {
		return constants.text.skip404NoticeTitle;
	};

	const handleCacheLevelChange = ( e ) => {
		methods.newfoldSettingsApiFetch(
			{ cacheLevel: parseInt( e.target.value ) },
			methods.setError,
			() => {
				setCacheLevel( parseInt( e.target.value ) );
			}
		);
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
				setSkip404( value );

				methods.makeNotice(
					'skip-404-change-notice',
					getSkip404NoticeTitle(),
					'',
					'success',
					5000
				);
			} )
			.catch( () => {
				methods.makeNotice(
					'skip-404-change-notice',
					constants.text.optionNotSet,
					'',
					'error',
					5000
				);
			} );
	};

	methods.useUpdateEffect( () => {
		methods.setStore( {
			...constants.store,
			cacheLevel,
		} );

		methods.makeNotice(
			'cache-level-change-notice',
			getCacheLevelNoticeTitle(),
			getCacheLevelNoticeText(),
			'success',
			5000
		);
	}, [ cacheLevel ] );

	return (
		<>
			<Container.SettingsField
				title={ constants.text.cacheLevelTitle }
				description={ constants.text.cacheLevelDescription }
			>
				<RadioGroup
					className="cache-options"
					id="cache-type"
					name="cache-level"
					value=""
				>
					{ cacheOptions.map( ( option ) => {
						return (
							<Components.Fragment key={ option.value }>
								<RadioGroup.Radio
									defaultChecked={
										option.value ===
										constants.store.cacheLevel
									}
									id={ 'cache-level-' + option.value }
									label={ option.label }
									value={ option.value }
									name="cache-level"
									onChange={ handleCacheLevelChange }
								/>
								<div className="nfd-radio__description">
									{ option.description }
								</div>
							</Components.Fragment>
						);
					} ) }
				</RadioGroup>
			</Container.SettingsField>
			<Container.SettingsField title={ constants.text.skip404Title }>
				<Checkbox
					id="skip-404"
					name="skip-404"
					onChange={ handleSkip404Change }
					value={ skip404 }
					checked={ skip404 }
					label={ __(
						'Skip 404 Handling For Static Files',
						'wp-module-performance'
					) }
				/>
			</Container.SettingsField>
		</>
	);
};

export default CacheSettings;
