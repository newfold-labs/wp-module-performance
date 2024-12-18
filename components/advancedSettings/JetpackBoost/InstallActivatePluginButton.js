// WordPress
import { useState } from '@wordpress/element';

// Newfold
import { Spinner } from '@newfold/ui-component-library';
import { NewfoldRuntime } from '@newfold-labs/wp-module-runtime';

const InstallActivatePluginButton = ( {
	methods,
	constants,
	setModuleStatus,
} ) => {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isActive, setIsActive ] = useState( false );
	const [ message, setMessage ] = useState( null );

	const handleInstallActivate = async () => {
		setIsLoading( true );
		setMessage( constants.text.jetpackBoostInstalling );

		const apiUrl = methods.NewfoldRuntime.createApiUrl(
			'/newfold-installer/v1/plugins/install'
		);
		const INSTALL_TOKEN = NewfoldRuntime.sdk.performance.install_token;
		const plugin = 'jetpack-boost';

		try {
			await methods.apiFetch( {
				url: apiUrl,
				method: 'POST',
				headers: { 'X-NFD-INSTALLER': INSTALL_TOKEN },
				data: { plugin, activate: true, queue: false },
			} );
			setIsActive( true );
			setModuleStatus( true );
			setMessage( constants.text.jetpackBoostInstalling );
		} catch ( error ) {
			setMessage( constants.text.jetpackBoostActivationFailed );
		} finally {
			setIsLoading( false );
		}
	};

	return (
		<div className="nfd-performance-jetpack-boost-container-install-activate-button">
			<button
				className="nfd-button--upsell"
				onClick={ handleInstallActivate }
			>
				{ isLoading ? (
					<>
						<Spinner />
						<p>{ message }</p>
					</>
				) : (
					! isActive && (
						<>
							<span>
								<svg
									xmlns="http://www.w3.org/2000/svg"
									fill="none"
									viewBox="0 0 24 24"
									strokeWidth="2"
									stroke="currentColor"
									aria-hidden="true"
									className="nfd-w-5 nfd-h-5 nfd--ml-1 nfd-shrink-0"
									role="img"
								>
									<path
										strokeLinecap="round"
										strokeLinejoin="round"
										d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"
									></path>
								</svg>
							</span>
							<span>{ constants.text.jetpackBoostCtaText }</span>
						</>
					)
				) }
			</button>
		</div>
	);
};

export default InstallActivatePluginButton;
