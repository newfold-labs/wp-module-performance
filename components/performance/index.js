// Newfold.
import { Container } from '@newfold/ui-component-library';

// Components.
import { default as CacheSettings } from '../cacheSettings/';
import { default as ClearCache } from '../clearCache/';
import { default as AdvancedSettings } from '../advancedSettings';
import { default as defaultText } from './defaultText';
import ImageOptimizationSettings from '../imageOptimizationSettings';
import { default as LinkPrefetch } from '../linkPrefetch/';

/**
 * Performance Module
 * For use in brand plugin apps to display performance page and settings
 *
 * @param {*} props
 * @return
 */
const Performance = ( { methods, constants, Components, ...props } ) => {
	const { store, setStore } = methods.useContext( methods.AppStore );
	const [ isError, setError ] = methods.useState( false );

	const notify = methods.useNotification();

	// set default text if not provided
	constants.text = Object.assign( defaultText, constants.text );

	const makeNotice = (
		id,
		title,
		description,
		variant = 'success',
		duration = false
	) => {
		notify.push( `performance-notice-${ id }`, {
			title,
			description: <span>{ description }</span>,
			variant,
			autoDismiss: duration,
		} );
	};
	constants.store = store;
	methods.makeNotice = makeNotice;
	methods.setStore = setStore;
	methods.setError = setError;

	return (
		<>
			<Container.Block
				separator={ true }
				className={ 'newfold-cache-settings' }
			>
				<CacheSettings
					methods={ methods }
					constants={ constants }
					Components={ Components }
				/>
			</Container.Block>
			<Container.Block
				separator={ true }
				className={ 'newfold-clear-cache' }
			>
				<ClearCache methods={ methods } constants={ constants } />
			</Container.Block>
			<Container.Block
				separator={ true }
				className={ 'newfold-performance-advanced-settings' }
			>
				<AdvancedSettings constants={ constants } methods={ methods } />
			</Container.Block>
			<Container.Block
				className={ 'newfold-link-prefetch' }
				separator={ true }
			>
				<LinkPrefetch methods={ methods } constants={ constants } />
			</Container.Block>
			<Container.Block className={ 'newfold-image-optimization' }>
				<ImageOptimizationSettings
					methods={ methods }
					constants={ constants }
				/>
			</Container.Block>
		</>
	);
};

export default Performance;
