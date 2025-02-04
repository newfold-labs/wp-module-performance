const customCommandTimeout = 20000;
const sampleLogoPath =
	'vendor/newfold-labs/wp-module-performance/tests/cypress/assets/image1.jpg';
describe( 'Enable Image Optimization Test', function () {
	const appClass = '.' + Cypress.env( 'appId' );

	before( () => {
		cy.visit(
			'/wp-admin/admin.php?page=' +
				Cypress.env( 'pluginId' ) +
				'#/performance'
		);
		cy.injectAxe();
	} );

	it( 'Is Accessible', () => {
		cy.wait( 500 );
		cy.checkA11y( appClass + '-app-body' );
	} );

	it( 'should enable image optimization if not already enabled', () => {
		cy.get(
			'button.nfd-toggle[data-id="image-optimization-enabled"]'
		).then( ( $button ) => {
			const isEnabled = $button.attr( 'aria-checked' ) === 'true';

			if ( isEnabled ) {
				cy.log( 'Image Optimization is already enabled' );
			} else {
				cy.wrap( $button ).click();
				cy.log(
					'Image Optimization was not enabled, so it was enabled now.'
				);
			}
		} );
		cy.get( '[data-id="auto-optimize-images"]', {
			timeout: customCommandTimeout,
		} )
			.scrollIntoView()
			.should( 'be.visible' )
			.click();

		cy.get( '[data-id="auto-delete-original"]', {
			timeout: customCommandTimeout,
		} )
			.scrollIntoView()
			.should( 'be.visible' )
			.click();
	} );

	it( 'Uploading images to upload.php', () => {
		cy.visit( '/wp-admin//upload.php' );
		cy.get( '.page-title-action', {
			timeout: customCommandTimeout,
		} )
			.should( 'exist' )
			.click();

		cy.wait( 5000 );
		cy.get( '#__wp-uploader-id-1' ).click();
		cy.wait( 5000 );
		cy.get( 'input[type="file"][id^="html5_"]' ).selectFile(
			sampleLogoPath,
			{ force: true }
		);
		cy.wait( 10000 );

		cy.visit( '/wp-admin//upload.php' );
		cy.get( '.select-mode-toggle-button', {
			timeout: customCommandTimeout,
		} )
			.should( 'exist' )
			.click();

		cy.get( '.attachments-wrapper' ) // click on Image  using above bulk select
			.find( 'ul.attachments' )
			.within( () => {
				cy.get( 'li' ).each( ( $el ) => {
					cy.wrap( $el )
						.invoke( 'attr', 'aria-checked' )
						.then( ( isChecked ) => {
							if ( isChecked !== 'true' ) {
								cy.wrap( $el )
									.find( 'button.check' )
									.click( { force: true } );
							}
						} );
				} );
			} );

		cy.wait( 5000 );
		cy.get( '#nfd-bulk-optimize-btn', {
			timeout: 10000,
		} )
			.should( 'exist' )
			.click( { force: true } );
		cy.wait( 5000 );
		cy.visit( '/wp-admin//upload.php' );
		cy.get( '.attachments-wrapper img' ).each( ( $img ) => {
			const imgSrc = $img.prop( 'src' );
			const fileExtension = imgSrc.split( '.' ).pop();
			cy.log( `Image source: ${ imgSrc }` );
			cy.log( `File extension: ${ fileExtension }` );
			if ( fileExtension === 'webp' ) {
				cy.log( 'This image is in WEBP format' );
			} else if ( fileExtension === 'jpeg' || fileExtension === 'jpg' ) {
				cy.log( 'This image is in JPEG format' );
			} else if ( fileExtension === 'png' ) {
				cy.log( 'This image is in PNG format' );
			}
		} );
		cy.get( '.select-mode-toggle-button', {
			timeout: customCommandTimeout,
		} )
			.should( 'exist' )
			.click();

		cy.get( '.attachments-wrapper' ) // click on Image  using above bulk select
			.find( 'ul.attachments' )
			.within( () => {
				cy.get( 'li' ).each( ( $el ) => {
					cy.wrap( $el )
						.invoke( 'attr', 'aria-checked' )
						.then( ( isChecked ) => {
							if ( isChecked !== 'true' ) {
								cy.wrap( $el )
									.find( 'button.check' )
									.click( { force: true } );
							}
						} );
				} );
			} );
		cy.get( '.delete-selected-button', { timeout: customCommandTimeout } )
			.should( 'exist' )
			.scrollIntoView()
			.click( { force: true } );

		cy.visit(
			'/wp-admin/admin.php?page=' +
				Cypress.env( 'pluginId' ) +
				'#/performance'
		);
		cy.get( '[data-id="image-optimization-enabled"]', {
			timeout: customCommandTimeout,
		} )
			.scrollIntoView()
			.should( 'be.visible' )
			.click();
	} );
} );
