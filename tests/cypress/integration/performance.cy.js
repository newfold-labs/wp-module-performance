// <reference types="Cypress" />
import performancePageLocators from '../support/pageObjects/performancePage';

describe( 'Performance Page', { testIsolation: true }, () => {
	const fixturePath = require( '../fixtures/performanceModule.json' );
	let performanceLocators;
	let data;

	beforeEach( () => {
		cy.setPermalinkStructure();
		data = fixturePath;
		cy.login( Cypress.env( 'wpUsername' ), Cypress.env( 'wpPassword' ) );
		cy.visit( '/wp-admin/admin.php?page=nfd-performance' );
		performanceLocators = new performancePageLocators();
	} );

	it( 'Is Accessible', () => {
		cy.injectAxe();
		cy.get( '#nfd-performance' ).then( ( $el ) => {
			cy.checkA11y( $el );
		} );
	} );

	it( 'Has Cache Settings', () => {
		cy.get( '.newfold-cache-settings' )
			.scrollIntoView()
			.should( 'be.visible' );
	} );

	it( 'Has Clear Cache Settings', () => {
		cy.get( '.newfold-clear-cache' )
			.scrollIntoView()
			.should( 'be.visible' );
	} );

	it( 'Clear Cache Disabled when Cache is Disabled', () => {
		cy.get( 'input[type="radio"]#cache-level-0' ).check();

		cy.wait( 500 );

		cy.get( '.clear-cache-button' )
			.scrollIntoView()
			.should( 'have.attr', 'disabled' );

		cy.get( 'input[type="radio"]#cache-level-1' ).check();

		cy.get( '.clear-cache-button' )
			.scrollIntoView()
			.should( 'not.have.attr', 'disabled' );

		cy.get( '.nfd-notifications' )
			.contains( 'p', 'Cache' )
			.should( 'be.visible' );
	} );

	it( 'Clear Cache Button Functions', () => {
		cy.get( '.clear-cache-button' ).click();

		cy.get( '.nfd-notifications' )
			.contains( 'p', 'Cache cleared' )
			.should( 'be.visible' );
	} );

	it( 'Mouse down-> without exclude: Verify if "Link Prefetch" is displayed and intercept the network call', () => {
		performanceLocators.verifyIfLinkPreFetchIsDisplayed();
		performanceLocators.verifyIfToggleIsEnabled();
		performanceLocators.interceptCallForMouseDownWithoutExcludeRunTimeURL(
			data.statusCode
		);
	} );

	it( 'Mouse Down-> with exclude:Extract RunTime Link value>>Verify if "Link Prefetch" is displayed and intercept the network call', () => {
		performanceLocators.verifyIfLinkPreFetchIsDisplayed();
		performanceLocators.verifyIfToggleIsEnabled();
		performanceLocators.interceptCallForMouseDownWithExcludeRunTimeURL(
			data.requestCount
		);
	} );

	it( 'Mouse Hover-> with exclude:Verify if "Link Prefetch" is displayed and intercept network call', () => {
		performanceLocators.verifyIfLinkPreFetchIsDisplayed();
		performanceLocators.verifyIfToggleIsEnabled();
		performanceLocators.interceptCallForMouseHoverWithExcludeRunTimeURL(
			data.requestCount
		);
	} );

	it( 'hasLinkPrefetchClick capability', () => {
		cy.exec(
			`npx wp-env run cli wp option update nfd_link_prefetch_settings '{"activeOnDesktop": true, "behavior": "mouseDown"}' --format=json`
		);
		cy.exec(
			`npx wp-env run cli wp option update _transient_nfd_site_capabilities '{"hasLinkPrefetchClick": true, "hasLinkPrefetchHover": false}' --format=json`	
		);
		cy.reload();
		performanceLocators.verifyIfLinkPreFetchIsDisplayed();
		performanceLocators.verifyIfToggleIsEnabled();
		performanceLocators.linkPrefetchCapabilityCheck('onlyMouseDown');
	} );

	it( 'hasLinkPrefetchHover capability', () => {
		cy.exec(
			`npx wp-env run cli wp option update nfd_link_prefetch_settings '{"activeOnDesktop": true, "behavior": "mouseHover"}' --format=json`	
		);
		cy.exec(
			`npx wp-env run cli wp option update _transient_nfd_site_capabilities '{"hasLinkPrefetchClick": true, "hasLinkPrefetchHover": true}' --format=json`	
		);
		cy.reload();
		performanceLocators.verifyIfLinkPreFetchIsDisplayed();
		performanceLocators.verifyIfToggleIsEnabled();
		performanceLocators.linkPrefetchCapabilityCheck('both');
	} );

	it( 'LinkPrefetch Capabilities to false', () => {
		cy.exec(
			`npx wp-env run cli wp option update _transient_nfd_site_capabilities '{"hasLinkPrefetchClick": false, "hasLinkPrefetchHover": false}' --format=json`	
		);
		cy.reload();
		cy.get( performanceLocators._linkPrefetchText ).should( 'not.exist' );
	} );

} );
