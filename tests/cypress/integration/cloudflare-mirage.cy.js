// <reference types="Cypress" />
import PerformancePage from '../support/pageObjects/performancePage';
import {
	clearImageOptimizationOption,
	setSiteCapabilities,
	clearSiteCapabilities,
	assertHtaccessHasRule,
	assertHtaccessHasNoRule,
} from '../support/serverHelpers';

describe( 'Cloudflare Mirage Toggle', { testIsolation: true }, () => {
	const performancePageLocators = new PerformancePage();

	beforeEach( () => {
		cy.login( Cypress.env( 'wpUsername' ), Cypress.env( 'wpPassword' ) );
		clearImageOptimizationOption();
		cy.setPermalinkStructure();
	} );

	it( 'Shows Mirage section when capability is true and toggle is enabled', () => {
		setSiteCapabilities( { hasCloudflareMirage: true } );

		performanceLocators.visitPerformancePage();

		cy.get( '#nfd-performance', { timeout: 10000 } ).should( 'be.visible' );

		performancePageLocators.getMirageToggle().should( 'exist' );
		performancePageLocators
			.getMirageToggle()
			.should( 'have.attr', 'aria-checked', 'true' )
			.click()
			.should( 'have.attr', 'aria-checked', 'false' );
	} );

	it( 'Does not show Mirage section when capability is false', () => {
		setSiteCapabilities( { hasCloudflareMirage: false } );

		performanceLocators.visitPerformancePage();

		cy.get( '#nfd-performance', { timeout: 10000 } ).should( 'be.visible' );

		performancePageLocators.getMirageToggle().should( 'not.exist' );
	} );

	it( 'Writes correct rewrite rules to .htaccess when Mirage is enabled', () => {
		setSiteCapabilities( { hasCloudflareMirage: true } );

		performanceLocators.visitPerformancePage();

		performancePageLocators
			.getMirageToggle()
			.should( 'exist' )
			.and( 'have.attr', 'aria-checked', 'true' );

		assertHtaccessHasRule( '63a6825d' );
	} );

	it( 'Toggles Mirage on/off and updates .htaccess accordingly', () => {
		setSiteCapabilities( { hasCloudflareMirage: true } );

		performanceLocators.visitPerformancePage();

		performancePageLocators
			.getMirageToggle()
			.should( 'exist' )
			.and( 'have.attr', 'aria-checked', 'true' );

		assertHtaccessHasRule( '63a6825d' );

		// Toggle OFF
		performancePageLocators
			.getMirageToggle()
			.click()
			.should( 'have.attr', 'aria-checked', 'false' );

		assertHtaccessHasNoRule( '63a6825d' );

		// Toggle ON again
		performancePageLocators
			.getMirageToggle()
			.click()
			.should( 'have.attr', 'aria-checked', 'true' );

		assertHtaccessHasRule( '63a6825d' );
	} );

	after( () => {
		performanceLocators.visitPerformancePage();

		cy.get( 'body' ).then( ( $body ) => {
			if ( $body.find( '[data-id="cloudflare-mirage"]' ).length > 0 ) {
				performancePageLocators.getMirageToggle().then( ( $toggle ) => {
					if ( $toggle.attr( 'aria-checked' ) === 'true' ) {
						cy.wrap( $toggle )
							.click()
							.should( 'have.attr', 'aria-checked', 'false' );
					}
				} );
			}
		} );

		clearImageOptimizationOption();
		clearSiteCapabilities();
	} );
} );
