class performancePageLocators {

    //Locators 
    _linkPrefetchText = '.newfold-link-prefetch';
    _dropDownForLinkPrefetch = '.nfd-select__button-label';
    _visitSiteButton = 'a.nfd-button.nfd-bg-white';
    _samplePageButton = '.wp-block-pages-list__item__link';
    _mouseDownElement = 'ul.nfd-select__options > li:nth-child(2)'
    _excludeKeywordInputField = '#link-prefetch-ignore-keywords'
    


    //All the methods related to performance page.
    verifyIfLinkPreFectchIsDisplayed() {
        cy.get(this._linkPrefetchText).scrollIntoView().should('be.visible');
    }

    verifyTextOfDropDownDesktop(mouseDownToBeSelected, mouseHoverToBeSelected) {
        cy.get(this._dropDownForLinkPrefetch).should(($el) => {
    const text = $el.text().trim(); 
    expect([mouseDownToBeSelected, mouseHoverToBeSelected]).to.include(text);
  });
      
    }

    interceptCallForMouseDownWithoutExclude(selectedDropDown, url, statusCode) {
        const forceReload = true;
        cy.get(this._dropDownForLinkPrefetch).then(($buttonLabel) => {
            const selectedText = $buttonLabel.text().trim();
            Cypress.config('defaultCommandTimeout', 4000);
            cy.intercept('GET', url).as('apiRequest');

            if (selectedText === selectedDropDown) {
                cy.log('Second option is already selected. Proceeding with the test...');
                cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
                cy.wait(4000);
                //cy.intercept('GET', url).as('apiRequest');
                cy.get(this._visitSiteButton).invoke('removeAttr', 'target').click();
                cy.reload(forceReload);
                //cy.wait(4000);
                cy.get(this._samplePageButton).click();
                cy.wait('@apiRequest');
                cy.get('@apiRequest').its('response.statusCode').should('eq', statusCode);

            }
            else {
                cy.log('Second option is not selected. Selecting the second option...');
                cy.wait(4000);
                cy.get(this._dropDownForLinkPrefetch).click();
                cy.wait(4000);
                cy.get(this._mouseDownElement).click();
                cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
                cy.wait(4000);
                cy.get(this._visitSiteButton).invoke('removeAttr', 'target').click();
                cy.reload(forceReload);
                cy.get(this._samplePageButton).click();
                cy.wait('@apiRequest');
                cy.get('@apiRequest').its('response.statusCode').should('eq', statusCode);
            }
        });



    }

    interceptCallForMouseDownWithExclude(selectedDropDown, url, requestCount) {

        const forceReload = true;
        Cypress.config('defaultCommandTimeout', 4000);
        cy.intercept('GET', url).as('apiRequest');
        cy.get(this._dropDownForLinkPrefetch).then(($buttonLabel) => {
            const selectedText = $buttonLabel.text().trim();


            if (selectedText === selectedDropDown) {
                cy.log('Second option is already selected. Proceeding with the test...');
                cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
               
                cy.get(this._visitSiteButton).invoke('removeAttr', 'target').click();
              
                cy.get(this._samplePageButton).invoke('prop', 'href')
                    .then((url) => {
                        const pageName = url.split('/').filter(Boolean).pop();
                        cy.log('Extracted page name:', pageName);
                        expect(pageName).to.not.be.empty;
                        cy.go("back")
                        //cy.wait(4000);
                        cy.get(this._excludeKeywordInputField).clear().type(pageName);
                        cy.intercept('GET', url).as('apiRequest');
                        cy.get(this._visitSiteButton).invoke('removeAttr', 'target').click();
                        cy.reload(forceReload);
                        //cy.wait(4000);
                        cy.get(this._samplePageButton).click();
                        cy.wrap(requestCount).should('equal', 0);
                    });
            }

            else {
                cy.log('Second option is not selected. Selecting the second option...');
                cy.wait(4000);
                cy.get(this._dropDownForLinkPrefetch).click();
                cy.get(this._mouseDownElement).click();
                cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
                //cy.wait(4000);
                cy.get(this._visitSiteButton).invoke('removeAttr', 'target').click();
               // cy.wait(3000);
                cy.get(this._samplePageButton).invoke('prop', 'href')
                    .then((url) => {
                        const pageName = url.split('/').filter(Boolean).pop();
                        cy.log('Extracted page name:', pageName);
                        expect(pageName).to.not.be.empty;
                        cy.go("back")
                        //cy.wait(4000);
                        cy.get(this._excludeKeywordInputField).clear().type(pageName);
                        cy.intercept('GET', url).as('apiRequest');
                        cy.get(this._visitSiteButton).invoke('removeAttr', 'target').click();
                        cy.reload(forceReload);
                        //cy.wait(4000);
                        cy.get(this._samplePageButton).click();
                        cy.wrap(requestCount).should('equal', 0);
                    });
            }
        });
    }

}
export default performancePageLocators;