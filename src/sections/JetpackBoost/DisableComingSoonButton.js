// WordPress
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

// Newfold
import { Spinner } from '@newfold/ui-component-library';
import { NewfoldRuntime } from '@newfold/wp-module-runtime';

// Texts
import getJetpackBoostText from './getJetpackBoostText';

const DisableComingSoonButton = ({setIsComingSoonActive, children}) => {
	const {
		jetpackBoostDisableComingSoonLabel,
	} = getJetpackBoostText();

	const handleDisableComingSoon = async () => {
		await window.NewfoldRuntime.comingSoon.disable()
		.then(response => {
			if ( response.success && !response.comingSoon ) {
				setIsComingSoonActive(false);
			}
		})
	};

	return (
		<div className="nfd-performance-jetpack-boost-container-disablecomingsoon-button nfd-feature-upsell nfd-feature-upsell--card">
			<div className="nfd-absolute nfd-inset-0 nfd-ring-1 nfd-ring-black nfd-ring-opacity-5 nfd-shadow-lg nfd-rounded-md"></div>
			<div className="nfd-space-y-8 nfd-grayscale">
				{children}
			</div>
			<div className="nfd-absolute nfd-inset-0 nfd-flex nfd-items-center nfd-justify-center">
				<button
					className="nfd-button nfd-button--upsell nfd-gap-2 nfd-shadow-lg nfd-shadow-amber-700/30"
					onClick={ handleDisableComingSoon }
				>
					{jetpackBoostDisableComingSoonLabel}
				</button>
			</div>
		</div>
	);
};

export default DisableComingSoonButton;
