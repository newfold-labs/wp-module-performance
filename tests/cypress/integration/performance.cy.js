// <reference types="Cypress" />

describe('Performance Page', function () {
	let appClass = '.' + Cypress.env('appId');

	before(() => {
		cy.visit('/wp-admin/admin.php?page=' + Cypress.env('pluginId') + '#/performance');
		cy.injectAxe();
		
	});

	it('Is Accessible', () => {
		cy.wait(500);
		cy.checkA11y( appClass + '-app-body');
	});

	it('Has Cache Settings', () => {
		cy.get('.newfold-cache-settings')
			.scrollIntoView()
			.should('be.visible');
	});

	it('Has Clear Cache Settings', () => {
		cy.get('.newfold-clear-cache')
			.scrollIntoView()
			.should('be.visible');
	});

	it('Clear Cache Disabled when Cache is Disabled', () => {

		cy.get('input[type="radio"]#cache-level-0').check();

		cy.wait(500);

		cy.get('.clear-cache-button')
			.scrollIntoView()
			.should('have.attr', 'disabled');

		cy.get('input[type="radio"]#cache-level-1').check();

		cy.get('.clear-cache-button')
			.scrollIntoView()
			.should('not.have.attr', 'disabled');
		
		cy.get('.nfd-notifications')
            .contains('p', 'Cache')
            .should('be.visible');
	});

	it('Clear Cache Button Functions', () => {
		
		cy.get('.clear-cache-button').click();

			
		cy.get('.nfd-notifications')
            .contains('p', 'Cache cleared')
            .should('be.visible');
			
	});

});
