// WordPress
import apiFetch from '@wordpress/api-fetch';
import { useRef, useState } from '@wordpress/element';

// Newfold
import { ToggleField, Textarea } from '@newfold/ui-component-library';

const SingleOption = ( { params, isChild, methods, constants } ) => {
	const [ optionDetails, setOptionDetails ] = useState( {
		id: params.id,
		label: params.label,
		description: params.description,
		value: params.value ? String( params.value ) : '',
		type: params.type,
		externalLink: params.externalLink,
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
						<ToggleField
							id={ option.id }
							label={ option.label }
							description={ option.description }
							checked={ option.value ? true : false }
							onChange={ ( value ) => {
								handleChangeOption( value, option.id );
							} }
						/>
						{ option.externalLink ? (
							<p
								style={ {
									textDecoration: 'underline',
									margin: '10px 0',
								} }
							>
								{ constants.text.jetpackBoostDicoverMore }{ ' ' }
								<a
									href={ `${ window.location.origin }/wp-admin/admin.php?page=jetpack-boost` }
								>
									{ ' ' }
									{ __(
										'here',
										'newfold-module-performance'
									) }{ ' ' }
								</a>
							</p>
						) : (
							''
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
