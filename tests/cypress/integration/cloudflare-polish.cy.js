// <reference types="Cypress" />
import PerformancePage from '../support/pageObjects/performancePage';
import {
	clearImageOptimizationOption,
	setSiteCapabilities,
	clearSiteCapabilities,
	assertHtaccessHasRule,
	assertHtaccessHasNoRule,
} from '../support/serverHelpers';

describe( 'Cloudflare Polish Toggle', { testIsolation: true }, () => {
	const performancePageLocators = new PerformancePage();

	beforeEach( () => {
		cy.login( Cypress.env( 'wpUsername' ), Cypress.env( 'wpPassword' ) );
		clearImageOptimizationOption();
		cy.setPermalinkStructure();
	} );

	it( 'Shows Polish section when capability is true and toggle is enabled', () => {
		setSiteCapabilities( { hasCloudflarePolish: true } );

		performanceLocators.visitPerformancePage();
		cy.get( '#nfd-performance', { timeout: 10000 } ).should( 'be.visible' );

		performancePageLocators.getPolishToggle().should( 'exist' );
		performancePageLocators
			.getPolishToggle()
			.should( 'have.attr', 'aria-checked', 'true' )
			.click()
			.should( 'have.attr', 'aria-checked', 'false' );
	} );

	it( 'Does not show Polish section when capability is false', () => {
		setSiteCapabilities( { hasCloudflarePolish: false } );

		performanceLocators.visitPerformancePage();
		cy.get( '#nfd-performance', { timeout: 10000 } ).should( 'be.visible' );

		performancePageLocators.getPolishToggle().should( 'not.exist' );
	} );

	it( 'Writes correct rewrite rules to .htaccess when Polish is enabled', () => {
		setSiteCapabilities( { hasCloudflarePolish: true } );

		performanceLocators.visitPerformancePage();

		performancePageLocators
			.getPolishToggle()
			.should( 'exist' )
			.and( 'have.attr', 'aria-checked', 'true' );

		assertHtaccessHasRule( '27cab0f2' );
	} );

	it( 'Toggles Polish on/off and updates .htaccess accordingly', () => {
		setSiteCapabilities( { hasCloudflarePolish: true } );

		performanceLocators.visitPerformancePage();

		performancePageLocators
			.getPolishToggle()
			.should( 'exist' )
			.and( 'have.attr', 'aria-checked', 'true' );

		assertHtaccessHasRule( '27cab0f2' );

		performancePageLocators
			.getPolishToggle()
			.click()
			.should( 'have.attr', 'aria-checked', 'false' );

		assertHtaccessHasNoRule( '27cab0f2' );

		performancePageLocators
			.getPolishToggle()
			.click()
			.should( 'have.attr', 'aria-checked', 'true' );

		assertHtaccessHasRule( '27cab0f2' );
	} );

	after( () => {
		performanceLocators.visitPerformancePage();

		cy.get( 'body' ).then( ( $body ) => {
			if ( $body.find( '[data-id="cloudflare-polish"]' ).length > 0 ) {
				performancePageLocators.getPolishToggle().then( ( $toggle ) => {
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
