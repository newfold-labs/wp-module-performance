import {
	Button,
	Container,
	TextareaField,
} from '@newfold/ui-component-library';

import { useState, useEffect, useRef } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

import { STORE_NAME } from '../../data/constants';
import { NewfoldRuntime } from '@newfold/wp-module-runtime';
import getCacheExclusionText from './getCacheExclusionText';

// Must match backend: CacheExclusion::CACHE_EXCLUSION_VALIDATE_REGEX (includes/Cache/CacheExclusion.php)
const CACHE_EXCLUSION_VALIDATE_REGEX = /^[a-z0-9,-]*$/;

const {
	cacheExclusionTitle,
	cacheExclusionDescription,
	cacheExclusionSaved,
	cacheExclusionSaveButton,
	cacheExclusionInvalidInput,
	cacheExclusionPlaceholder
} = getCacheExclusionText();

// Custom hook to mimic componentDidUpdate behavior
const useUpdateEffect = ( effect, deps ) => {
	const isFirst = useRef( true );

	useEffect( () => {
		if ( isFirst.current ) {
			isFirst.current = false;
			return;
		}
		return effect();
	}, deps );
};

const CacheExclusion = () => {
	const [ isEdited, setIsEdited ] = useState( false );
	const [ hadInvalidInput, setHadInvalidInput ] = useState( false );
	const [ isError, setIsError ] = useState( false );
	const [ isSaved, setIsSaved ] = useState( false );

	const runtimeExclusion = NewfoldRuntime?.sdk?.cache?.exclusion ?? '';
	const [ currentValue, setCurrentValue ] = useState( runtimeExclusion );
	const [ cacheExclusion, setCacheExclusion ] = useState( runtimeExclusion );

	const apiUrl = NewfoldRuntime.createApiUrl(
		'/newfold-performance/v1/cache/settings'
	);
	const { pushNotification } = useDispatch( STORE_NAME );

	const makeNotice = (
		id,
		title,
		description,
		variant = 'success',
		duration = false
	) => {
		pushNotification( id, {
			title,
			description,
			variant,
			autoDismiss: duration,
		} );
	};

	const handleCacheExclusionChange = ( e ) => {
		const newValue = e.target.value;
		const normalized = newValue.replace( /\s+/g, '' ).replace( /,$/, '' );
		const valid = CACHE_EXCLUSION_VALIDATE_REGEX.test( normalized );
		setIsEdited( newValue !== cacheExclusion );
		if ( ! valid ) {
			setHadInvalidInput( true );
		}
		setCurrentValue( newValue );
		setIsError( false );
	};

	// Normalization must match backend: CacheExclusion::normalize()
	const normalizedRules = currentValue.replace( /\s+/g, '' ).replace( /,$/, '' );
	const isInputValid = CACHE_EXCLUSION_VALIDATE_REGEX.test( normalizedRules );

	const handleSave = () => {
		if ( ! isInputValid ) {
			return;
		}

		apiFetch( {
			url: apiUrl,
			method: 'POST',
			data: { cacheExclusion: normalizedRules },
		} )
			.then( () => {
				setIsError( false );
				setHadInvalidInput( false );
				setIsSaved( true );
				setCacheExclusion( normalizedRules );
				setCurrentValue( normalizedRules );
				setIsEdited( false );
			} )
			.catch( ( error ) => {
				setIsError( error.message );
				setCurrentValue( normalizedRules );
			} );
	};

	useUpdateEffect( () => {
		makeNotice(
			'cache-exclusion-notice',
			cacheExclusionTitle,
			! isError ? cacheExclusionSaved : isError,
			! isError ? 'success' : 'error',
			5000
		);
		setIsSaved( false );
	}, [ isSaved, isError ] );

	return (
		<Container.SettingsField
			title={ cacheExclusionTitle }
			description={ cacheExclusionDescription }
		>
			<TextareaField
				id="cache-exclusion"
				name="cache-exclusion"
				onChange={ handleCacheExclusionChange }
				value={ currentValue }
				placeholder={ cacheExclusionPlaceholder }
				rows="1"
				label={ cacheExclusionTitle }
			/>
			{ ( isEdited || ( hadInvalidInput && isInputValid ) ) && ! isInputValid && (
				<p className="nfd-text-sm nfd-text-red-600 nfd-mt-1">
					{ cacheExclusionInvalidInput }
				</p>
			) }
			{ ( isEdited || ( hadInvalidInput && isInputValid ) ) && (
				<Button
					variant="secondary"
					className="save-cache-exclusion-button"
					onClick={ handleSave }
					disabled={ ! isInputValid }
				>
					{ cacheExclusionSaveButton }
				</Button>
			) }
		</Container.SettingsField>
	);
};

export default CacheExclusion;
