export const setSiteCapabilities = ( capabilities ) => {
	const phpArray = Object.entries( capabilities )
		.map( ( [ key, value ] ) => {
			const phpValue =
				typeof value === 'boolean' ? value.toString() : `'${ value }'`;
			return `'${ key }' => ${ phpValue }`;
		} )
		.join( ', ' );

	const command = `npx wp-env run cli wp eval "set_transient('nfd_site_capabilities', array(${ phpArray }));"`;

	cy.exec( command );
};

export const clearImageOptimizationOption = () => {
	cy.exec(
		`npx wp-env run cli wp option delete nfd_image_optimization || true`
	);
};

export const clearFontOptimizationOption = () => {
	cy.exec(
		`npx wp-env run cli wp option delete nfd_fonts_optimization || true`
	);
};

export const readHtaccessViaCli = () => {
	return cy
		.exec( 'npx wp-env run cli cat .htaccess', {
			failOnNonZeroExit: false,
			timeout: 5000,
		} )
		.then( ( result ) => result.stdout );
};

export const clearSiteCapabilities = () => {
	cy.exec(
		'npx wp-env run cli wp option delete _transient_nfd_site_capabilities || true'
	);
};

export const assertHtaccessHasRule = ( hash ) => {
	readHtaccessViaCli().then( ( htaccess ) => {
		expect( htaccess ).to.include(
			'# BEGIN Newfold CF Optimization Header'
		);
		expect( htaccess ).to.include( '# END Newfold CF Optimization Header' );
		expect( htaccess )
			.to.include( `nfd-enable-cf-opt` )
			.and.to.include( hash );
		expect( htaccess )
			.to.include( `Set-Cookie "nfd-enable-cf-opt=` )
			.and.to.include( hash );
	} );
};

export const assertHtaccessHasNoRule = ( hash ) => {
	readHtaccessViaCli().then( ( htaccess ) => {
		expect( htaccess ).to.not.include( hash );
	} );
};
