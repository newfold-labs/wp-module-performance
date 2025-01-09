// <reference types="Cypress" />
//import performancePageLocators from '../../../../../../tests/cypress/support/pageObjects/performancePageLocators'

import performancePageLocators from '../../../../../../vendor/newfold-labs/wp-module-performance/cypress/support/pageObjects/performancePageLocators';
describe( 'Performance Page', function () {
	const appClass = '.' + Cypress.env( 'appId' );
	const fixturePath = require( '../../../../../../vendor/newfold-labs/wp-module-performance/cypress/fixtures/performanceModule.json' );
	let performanceLocators;

	before( () => {
		cy.visit(
			'/wp-admin/admin.php?page=' +
				Cypress.env( 'pluginId' ) +
				'#/performance'
		);
		cy.injectAxe();
		this.data = fixturePath;
		performanceLocators = new performancePageLocators();
	} );

	it( 'Is Accessible', () => {
		cy.wait( 500 );
		cy.checkA11y( appClass + '-app-body' );
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

	//Case 1
	it( 'Mouse Hover-> without exclude: Verify if "Link Prefetch" is displayed and intercept the network call', () => {
		const forceReload = true;
		const mouseHoverifSelected = 'Prefetch on Mouse Hover (Recommended)';
		const localURL = 'http://perset.local/sample-page/';
		const statusCode = 200;

		cy.get( '.newfold-link-prefetch' )
			.scrollIntoView()
			.should( 'be.visible' );

		cy.wait( 5000 );
		cy.get( '.nfd-toggle--checked' ).should(
			'have.attr',
			'aria-checked',
			'true'
		);
		cy.get( '.nfd-select__button-label' ).then( ( $buttonLabel ) => {
			const selectedText = $buttonLabel.text().trim();

			if ( selectedText === 'Prefetch on Mouse Hover (Recommended)' ) {
				cy.log(
					'First option is already selected. Proceeding with the test...'
				);
				cy.get( '.nfd-select__button-label' ).should(
					'have.text',
					'Prefetch on Mouse Hover (Recommended)'
				);
				cy.wait( 5000 );
				cy.intercept( 'GET', localURL ).as( 'apiRequest' );
				cy.get( 'a.nfd-button.nfd-bg-white' )
					.invoke( 'removeAttr', 'target' )
					.click();
				cy.reload( forceReload );
				cy.wait( 5000 );
				cy.get( '.wp-block-pages-list__item__link' ).trigger(
					'mouseover'
				);
				cy.wait( '@apiRequest' );
				cy.get( '@apiRequest' )
					.its( 'response.statusCode' )
					.should( 'eq', 200 );
				cy.go( 'back' );
			} else {
				cy.log(
					'First option is not selected. Selecting the first option...'
				);
				cy.get( '.nfd-select__button-label' ).click();
				cy.get( 'ul.nfd-select__options > li:nth-child(1)' ).click();
				cy.get( '.nfd-select__button-label' ).should(
					'have.text',
					'Prefetch on Mouse Hover (Recommended)'
				);
				cy.wait( 5000 );
				cy.intercept( 'GET', localURL ).as( 'apiRequest' );
				cy.get( 'a.nfd-button.nfd-bg-white' )
					.invoke( 'removeAttr', 'target' )
					.click();
				cy.reload( forceReload );
				cy.wait( 5000 );
				cy.get( '.wp-block-pages-list__item__link' ).trigger(
					'mouseover'
				);
				cy.wait( '@apiRequest' );
				cy.get( '@apiRequest' )
					.its( 'response.statusCode' )
					.should( 'eq', 200 );
				cy.go( 'back' );
			}
		} );
	} );

	//case 2
	it( 'Mouse down-> without exclude: Verify if "Link Prefetch" is displayed and intercept the network call', () => {
		performanceLocators.verifyIfLinkPreFectchIsDisplayed();
		performanceLocators.verifyTextOfDropDownDesktop(
			this.data.mouseHoverToBeSelected,
			this.data.mouseDownToBeSelected
		);
		performanceLocators.interceptCallForMouseDownWithoutExclude(
			this.data.mouseDownToBeSelected,
			this.data.localAppURL,
			this.data.statusCode
		);
	} );

	//case 3
	it.only( 'Mouse Down: with exclude:Extract RunTime Link value>> Verify if "Link Prefetch" is displayed and intercept the network call', () => {
		performanceLocators.verifyIfLinkPreFectchIsDisplayed();
		performanceLocators.verifyTextOfDropDownDesktop(
			this.data.mouseHoverToBeSelected,
			this.data.mouseDownToBeSelected
		);
		performanceLocators.interceptCallForMouseDownWithExclude(
			this.data.mouseDownToBeSelected,
			this.data.localAppURL,
			this.data.requestCount
		);
	} );

	//case 4
	it( 'Mouse Hover: with exclude:Extract RunTime Link value>: Verify if "Link Prefetch" is displayed and intercept the network call', () => {
		const forceReload = true;
		const localURL = 'http://perset.local/sample-page/';
		const requestCount = 0;
		Cypress.config( 'defaultCommandTimeout', 4000 );

		cy.get( '.newfold-link-prefetch' )
			.scrollIntoView()
			.should( 'be.visible' );
		cy.get( '.nfd-toggle--checked' ).should(
			'have.attr',
			'aria-checked',
			'true'
		);
		cy.get( '.nfd-select__button-label' ).then( ( $buttonLabel ) => {
			const selectedText = $buttonLabel.text().trim();

			if ( selectedText === 'Prefetch on Mouse Hover (Recommended)' ) {
				cy.log(
					'First option is already selected. Proceeding with the test...'
				);
				cy.get( '.nfd-select__button-label' ).should(
					'have.text',
					'Prefetch on Mouse Hover (Recommended)'
				);
				cy.get( 'a.nfd-button.nfd-bg-white' )
					.invoke( 'removeAttr', 'target' )
					.click();
				cy.get( 'a.wp-block-pages-list__item__link' )
					.invoke( 'prop', 'href' )
					.then( ( url ) => {
						const pageName = url
							.split( '/' )
							.filter( Boolean )
							.pop();
						cy.log( 'Extracted page name:', pageName );
						expect( pageName ).to.not.be.empty;
						cy.go( 'back' );
						cy.get( '#link-prefetch-ignore-keywords' )
							.clear()
							.type( pageName );
						cy.intercept( 'GET', localURL ).as( 'apiRequest' );
						cy.get( 'a.nfd-button.nfd-bg-white' )
							.invoke( 'removeAttr', 'target' )
							.click();
						cy.reload( forceReload );
						cy.get( '.wp-block-pages-list__item__link' ).trigger(
							'mouseover'
						);
						cy.wrap( requestCount ).should( 'equal', 0 );
					} );
			} else {
				cy.log(
					'First option is not selected. Selecting the first option...'
				);
				cy.get( '.nfd-select__button-label' ).click();
				cy.wait( 4000 );
				cy.get( 'ul.nfd-select__options > li:nth-child(1)' ).click();
				cy.get( '.nfd-select__button-label' ).should(
					'have.text',
					'Prefetch on Mouse Hover (Recommended)'
				);
				cy.get( 'a.nfd-button.nfd-bg-white' )
					.invoke( 'removeAttr', 'target' )
					.click();
				cy.get( 'a.wp-block-pages-list__item__link' )
					.invoke( 'prop', 'href' )
					.then( ( url ) => {
						const pageName = url
							.split( '/' )
							.filter( Boolean )
							.pop();
						cy.log( 'Extracted page name:', pageName );
						expect( pageName ).to.not.be.empty;
						cy.go( 'back' );
						cy.get( '#link-prefetch-ignore-keywords' )
							.clear()
							.type( pageName );
						cy.intercept( 'GET', localURL ).as( 'apiRequest' );
						cy.get( 'a.nfd-button.nfd-bg-white' )
							.invoke( 'removeAttr', 'target' )
							.click();
						cy.reload( forceReload );
						cy.get( '.wp-block-pages-list__item__link' ).trigger(
							'mouseover'
						);
						cy.wrap( requestCount ).should( 'equal', 0 );
					} );
			}
		} );
	} );
} );
