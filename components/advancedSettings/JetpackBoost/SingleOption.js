// WordPress
import apiFetch from '@wordpress/api-fetch';
import { useRef, useState } from '@wordpress/element';

// Newfold
import {
	ToggleField,
	Textarea,
	FeatureUpsell,
} from '@newfold/ui-component-library';
import { NewfoldRuntime } from '@newfold-labs/wp-module-runtime';

// Third-parts
import parse from 'html-react-parser';

const SingleOption = ( { params, isChild, methods, constants } ) => {
	const [ optionDetails, setOptionDetails ] = useState( {
		id: params.id,
		label: params.label,
		description: params.description,
		value: params.value ? String( params.value ) : '',
		type: params.type,
		externalText: params.externalText,
		premiumUrl: params.premiumUrl ?? '',
		children: params.children,
	} );

	const [ isShown, setIsShown ] = useState( false );

	const debounceTimeout = useRef( null ); // Mantiene il timeout tra i render

	const handleChangeOption = ( value, id ) => {
		if ( typeof value === 'object' ) {
			value = value.target.value;
		}

		setOptionDetails( { ...optionDetails, value } );

		// Clear the previous timeout if user types again.
		if ( debounceTimeout.current ) {
			clearTimeout( debounceTimeout.current );
		}

		// Set a new timeout of 2 seconds.
		debounceTimeout.current = setTimeout( () => {
			apiFetch( {
				path: 'newfold-performance/v1/jetpack/settings',
				method: 'POST',
				data: {
					field: {
						id,
						value,
					},
				},
			} )
				.then( () => {
					methods.makeNotice(
						'cache-level-change-notice',
						constants.text.optionSet,
						'',
						'success',
						5000
					);
				} )
				.catch( () => {
					methods.makeNotice(
						'cache-level-change-notice',
						constants.text.optionNotSet,
						'',
						'error',
						5000
					);
				} );
		}, 1000 );
	};

	const displayOption = ( option ) => {
		switch ( option.type ) {
			case 'toggle':
				return (
					<>
						{ option.premiumUrl &&
							! NewfoldRuntime.sdk.performance
								.jetpack_boost_premium_is_active && (
								<FeatureUpsell
									cardText="Upgrade to Unlock"
									cardLink={ option.premiumUrl }
								>
									<ToggleField
										id={ option.id }
										label={ option.label }
										description={ parse(
											option.description
										) }
										checked={ !! option.value }
										onChange={ ( value ) =>
											handleChangeOption(
												value,
												option.id
											)
										}
									/>
									{ option.externalText ? (
										<p
											style={ {
												textDecoration: 'underline',
												margin: '10px 0',
											} }
										>
											{ parse( option.externalText ) }
										</p>
									) : null }
								</FeatureUpsell>
							) }
						{ ( ! option.premiumUrl ||
							NewfoldRuntime.sdk.performance
								.jetpack_boost_premium_is_active ) && (
							<>
								<ToggleField
									id={ option.id }
									label={ option.label }
									description={ option.description }
									checked={ !! option.value }
									onChange={ ( value ) =>
										handleChangeOption( value, option.id )
									}
								/>
								{ option.externalText ? (
									<p
										style={ {
											textDecoration: 'underline',
											margin: '10px 0',
										} }
									>
										{ parse( option.externalText ) }
									</p>
								) : null }
							</>
						) }
					</>
				);

			case 'textarea':
				return (
					<>
						<p className="field-label">{ option.label }</p>
						<Textarea
							id={ option.id }
							description={ option.description }
							value={ option.value ?? '' }
							onChange={ ( value ) => {
								handleChangeOption( value, option.id );
							} }
						/>
					</>
				);
			default:
				return null;
		}
	};

	return (
		<>
			{ isChild && (
				<div className="child-field">
					<div
						className="wrap-button"
						style={ { textAlign: 'right' } }
					>
						<button onClick={ () => setIsShown( ! isShown ) }>
							{ isShown
								? constants.text.jetpackBoostShowLess
								: constants.text.jetpackBoostShowMore }
						</button>
					</div>
					{ isShown && displayOption( optionDetails ) }
				</div>
			) }
			{ ! isChild && displayOption( optionDetails ) }
		</>
	);
};

export default SingleOption;
