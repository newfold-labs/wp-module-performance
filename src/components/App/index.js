// Initialize App Store and Styles
import '../../store';
import '../../styles/styles.css';

// Newfold
import { Container, Root, Page, Title } from '@newfold/ui-component-library';

// WordPress
import { useEffect } from '@wordpress/element';

// Components
import CacheSettings from '../../sections/CacheSettings';
import NotificationFeed from '../NotificationFeed';
import CacheExclusion from '../../sections/CacheExclusion';
import ClearCache from '../../sections/ClearCache';
import Skip404 from '../../sections/Skip404';
import JetpackBoost from '../../sections/JetpackBoost';
import LinkPrefetch from '../../sections/LinkPrefetch';
import ImageOptimization from '../../sections/ImageOptimization';

import getAppText from './getAppText';
import FontOptimization from '../../sections/FontOptimization';

const App = () => {
	
	const { title, description } = getAppText();
	const capabilities = NewfoldRuntime.capabilities || {};

	const hasLinkPrefetchClick = capabilities.hasOwnProperty('hasLinkPrefetchClick') ? capabilities.hasLinkPrefetchClick : true;
	const hasLinkPrefetchHover = capabilities.hasOwnProperty('hasLinkPrefetchHover') ? capabilities.hasLinkPrefetchHover : true;

	useEffect( () => {
		const brand = NewfoldRuntime.sdk?.plugin?.brand;
		if ( brand ) {
			document.body.classList.add( `nfd-brand--${ brand }` );
		}
	}, [] );

	return (
		<Root context={ { isRTL: false } }>
			<NotificationFeed />
			<Page title={ title }>
				<div>
					<Title as="h1" className="nfd-mb-2">
						{ title }
					</Title>
					<Title as="h2" className="nfd-font-normal nfd-text-[13px]">
						{ description }
					</Title>
				</div>
				<Container>
					<Container.Block
						separator
						className="newfold-cache-settings"
					>
						<CacheSettings />
					</Container.Block>
					<Container.Block
						separator
						className="newfold-cache-exclusion"
					>
						<CacheExclusion />
					</Container.Block>
					<Container.Block separator className="newfold-clear-cache">
						<ClearCache />
					</Container.Block>
					<Container.Block separator className="newfold-skip404">
						<Skip404 />
					</Container.Block>
					<Container.Block
						separator
						className="newfold-performance-advanced-settings"
					>
						<JetpackBoost />
					</Container.Block>
					{ ( false !== hasLinkPrefetchClick || false !== hasLinkPrefetchHover ) && (
						<Container.Block
							separator
							className="newfold-link-prefetch"
						>
							<LinkPrefetch hasLinkPrefetchClick={hasLinkPrefetchClick} hasLinkPrefetchHover={hasLinkPrefetchHover}/>
						</Container.Block>
					) }
					<Container.Block
						separator
						className="newfold-image-optimization"
					>
						<ImageOptimization />
					</Container.Block>
					<Container.Block className="newfold-font-optimization">
						<FontOptimization />
					</Container.Block>
				</Container>
			</Page>
		</Root>
	);
};

export default App;
