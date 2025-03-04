// Newfold
import { Container, RadioGroup } from '@newfold/ui-component-library';

const CacheSettings = ( { methods, constants, Components } ) => {
	const [ cacheLevel, setCacheLevel ] = methods.useState(
		constants.store.cacheLevel
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
				' ' +
				constants.text.cacheLevel1Recommendation,
			value: 1,
			notice: constants.text.cacheLevel1NoticeText,
		},
		{
			label: constants.text.cacheLevel2Label,
			description:
				constants.text.cacheLevel2Description +
				' ' +
				constants.text.cacheLevel2Recommendation,
			value: 2,
			notice: constants.text.cacheLevel2NoticeText,
		},
		{
			label: constants.text.cacheLevel3Label,
			description:
				constants.text.cacheLevel3Description +
				' ' +
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

	const handleCacheLevelChange = ( e ) => {
		methods.newfoldSettingsApiFetch(
			{ cacheLevel: parseInt( e.target.value ) },
			methods.setError,
			() => {
				setCacheLevel( parseInt( e.target.value ) );
			}
		);
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
		</>
	);
};

export default CacheSettings;
