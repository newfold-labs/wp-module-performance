import domReady from '@wordpress/dom-ready';
import { createRoot, createPortal, useEffect, useState } from '@wordpress/element';

import App from './components/App';

import { NFD_PERFORMANCE_ELEMENT_ID } from './data/constants';

// mount the app on standalone page - legacy
domReady( () => {
	const mountNode = document.getElementById( NFD_PERFORMANCE_ELEMENT_ID );

	if ( mountNode ) {
		const root = createRoot( mountNode );
		root.render( <App /> );
	}
} );


/**
 * Performance Portal App Setup
 */
const WP_PERFORMANCE_FILL_ELEMENT = 'nfd-performance-portal'; // DOM Element ID for performance app
// the portal id is staging-portal and set in the plugin settings and connected to the portal in the registry
let root = null; // Root for detecting if app is already rendered

const PerformancePortalAppRender = () => {
	const DOM_ELEMENT = document.getElementById( WP_PERFORMANCE_FILL_ELEMENT );
	if ( null !== DOM_ELEMENT ) {
		if ( 'undefined' !== typeof createRoot ) {
			if ( ! root ) {
				root = createRoot( DOM_ELEMENT );
			}
			root.render( <PerformancePortalApp /> );
		}
	}
};

export const PerformancePortalApp = () => {
	const [ container, setContainer ] = useState( null );

	useEffect( () => {
		const registry = window.NFDPortalRegistry;
		// Check for required registry
		if ( ! registry ) {
			return;
		}

		const updateContainer = ( el ) => {
			setContainer( el );
		};

		const clearContainer = () => {
			setContainer( null );
		};

		// Subscribe to portal readiness updates
		registry.onReady( 'performance', updateContainer );
		registry.onRemoved( 'performance', clearContainer );

		// Immediately try to get the container if already registered
		const current = registry.getElement( 'performance' );
		if ( current ) {
			updateContainer( current );
		}
	}, [] );

	if ( ! container ) {
		return null;
	}

	return createPortal(
		<div className="performance-fill">
			<App />
		</div>,
		container
	);
};

// Render (hidden)App on Page Load - but portal only kicks in when/if DOM element is available
domReady( PerformancePortalAppRender );