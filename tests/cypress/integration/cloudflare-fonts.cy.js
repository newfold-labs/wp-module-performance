// <reference types="Cypress" />
import PerformancePage from '../support/pageObjects/performancePage';
import {
	setSiteCapabilities,
	clearSiteCapabilities,
	clearFontOptimizationOption,
	assertHtaccessHasRule,
	assertHtaccessHasNoRule,
} from '../support/serverHelpers';

describe(
	'Cloudflare Font Optimization Toggle',
	{ testIsolation: true },
	() => {
		const performancePageLocators = new PerformancePage();

		beforeEach( () => {
			cy.login(
				Cypress.env( 'wpUsername' ),
				Cypress.env( 'wpPassword' )
			);
			clearFontOptimizationOption();
			cy.setPermalinkStructure();
		} );

		it( 'Shows Font Optimization section when capability is true and toggle is enabled', () => {
			// Visit the performance page to set the initial capabilities
			performancePageLocators.visitPerformancePage();
			cy.get( '#nfd-performance', { timeout: 10000 } ).should(
				'be.visible'
			);

			setSiteCapabilities( { hasCloudflareFonts: true } );
			cy.reload();

			performancePageLocators.getFontToggle().should( 'exist' );
			performancePageLocators
				.getFontToggle()
				.should( 'have.attr', 'aria-checked', 'true' )
				.click()
				.should( 'have.attr', 'aria-checked', 'false' );
		} );

		it( 'Does not show Font Optimization section when capability is false', () => {
			setSiteCapabilities( { hasCloudflareFonts: false } );

			performancePageLocators.visitPerformancePage();

			cy.get( '#nfd-performance', { timeout: 10000 } ).should(
				'be.visible'
			);

			performancePageLocators.getFontToggle().should( 'not.exist' );
		} );

		it( 'Writes correct rewrite rules to .htaccess when Font Optimization is enabled', () => {
			setSiteCapabilities( { hasCloudflareFonts: true } );

			performancePageLocators.visitPerformancePage();

			performancePageLocators
				.getFontToggle()
				.should( 'exist' )
				.and( 'have.attr', 'aria-checked', 'true' );

			assertHtaccessHasRule( '04d3b602' );
		} );

		it( 'Toggles Font Optimization on/off and updates .htaccess accordingly', () => {
			setSiteCapabilities( { hasCloudflareFonts: true } );

			performancePageLocators.visitPerformancePage();

			performancePageLocators
				.getFontToggle()
				.should( 'exist' )
				.and( 'have.attr', 'aria-checked', 'true' );

			assertHtaccessHasRule( '04d3b602' );

			// Toggle OFF
			performancePageLocators
				.getFontToggle()
				.click()
				.should( 'have.attr', 'aria-checked', 'false' );

			assertHtaccessHasNoRule( '04d3b602' );

			// Toggle ON again
			performancePageLocators
				.getFontToggle()
				.click()
				.should( 'have.attr', 'aria-checked', 'true' );

			assertHtaccessHasRule( '04d3b602' );
		} );

		after( () => {
			performancePageLocators.visitPerformancePage();

			cy.get( 'body' ).then( ( $body ) => {
				if ( $body.find( '[data-id="cloudflare-fonts"]' ).length > 0 ) {
					performancePageLocators
						.getFontToggle()
						.then( ( $toggle ) => {
							if ( $toggle.attr( 'aria-checked' ) === 'true' ) {
								cy.wrap( $toggle )
									.click()
									.should(
										'have.attr',
										'aria-checked',
										'false'
									);
							}
						} );
				}
			} );

			clearFontOptimizationOption();
			clearSiteCapabilities();
		} );
	}
);
