class performancePageLocators {
    //Locators
    _linkPrefetchText = '.newfold-link-prefetch';
    _dropDownForLinkPrefetch = '.nfd-select__button-label';
    _visitSiteButton = 'a.nfd-button.nfd-bg-white';
    _samplePageButton = '.wp-block-pages-list__item__link';
    _mouseHoverElement = 'ul.nfd-select__options > li:nth-child(1)';
    _mouseDownElement = 'ul.nfd-select__options > li:nth-child(2)';
    _excludeKeywordInputField = '#link-prefetch-ignore-keywords';
    _isToggleEnabled = 'button[data-id="link-prefetch-active-desktop"]';

    //All the methods related to performance page.
    verifyIfLinkPreFectchIsDisplayed() {
        cy.get(this._linkPrefetchText)
            .scrollIntoView()
            .should('be.visible');
    }

    verifyIfToggleIsEnabled() {
        cy.get(this._isToggleEnabled).then(($toggle) => {
            if ($toggle.attr('aria-checked') === 'false') {
                cy.wrap($toggle).click();
            }
        });

        cy.get(this._isToggleEnabled).should(
            'have.attr',
            'aria-checked',
            'true'
        );
    }

    verifyTextOfDropDownDesktop(
        mouseDownToBeSelected,
        mouseHoverToBeSelected
    ) {
        cy.get(this._dropDownForLinkPrefetch).should(($el) => {
            const text = $el.text().trim();
            expect([
                mouseDownToBeSelected,
                mouseHoverToBeSelected,
            ]).to.include(text);
        });
    }

   
 interceptCallForMouseDownWithoutExcludeRunTimeURL(selectedDropDown, statusCode) {
        const forceReload = true;
    
        // Get the dropdown element text
        cy.get(this._dropDownForLinkPrefetch).then(($buttonLabel) => {
            const selectedText = $buttonLabel.text().trim();
    
            // Adjust command timeout if needed
            Cypress.config('defaultCommandTimeout', 4000);
    
            // Check if the correct dropdown option is selected
            if (selectedText === selectedDropDown) {
                cy.log('Second option is already selected. Proceeding with the test...');
                cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
            } else {
                cy.log('Second option is not selected. Selecting the second option...');
                cy.get(this._dropDownForLinkPrefetch).click();
                cy.get(this._mouseDownElement).click();
                cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
            }
    
            // Clear the input field
            cy.get(this._excludeKeywordInputField).clear();
    
            // Visit the site and handle navigation
            cy.get(this._visitSiteButton)
                .invoke('removeAttr', 'target') // Prevent opening in a new tab
                .click();
    
            // Wait for the sample page link to be available
            cy.get('.wp-block-pages-list__item__link.wp-block-navigation-item__content', { timeout: 6000 })
                .should('be.visible')
                .invoke('attr', 'href')
                .then((url) => {
                    // Intercept the API request with the extracted URL
                    cy.intercept('GET', url).as('apiRequest');
    
                    // Reload the page if necessary
                    cy.reload(forceReload);
    
                    // Navigate to the sample page and wait for the API request to complete
                    cy.get(this._samplePageButton).click();
                    cy.wait('@apiRequest');
    
                    // Assert the API response status code
                    cy.get('@apiRequest')
                        .its('response.statusCode')
                        .should('eq', statusCode);
    
                    // Go back twice
                    cy.go('back');
                    cy.go('back');
                });
        });
    }

    interceptCallForMouseDownWithExcludeRunTimeURL(selectedDropDown, requestCount) {
        const forceReload = true;
        Cypress.config('defaultCommandTimeout', 4000);
    
        // Function to extract page name from URL
        const extractPageName = (url) => {
            const pageName = url.split('/').filter(Boolean).pop();
            cy.log('Extracted page name:', pageName);
            expect(pageName).to.not.be.empty;
            return pageName;
        };
    
        // Function to visit the site and check request count
        const visitSiteAndCheckRequestCount = (url, pageName) => {
            cy.get(this._excludeKeywordInputField)
                .clear()
                .type(pageName);
    
            cy.intercept('GET', url).as('apiRequest');
            cy.get(this._visitSiteButton)
                .invoke('removeAttr', 'target')
                .click({ force: true });
    
            cy.reload(forceReload);
            cy.get(this._samplePageButton).click();
            cy.wrap(requestCount).should('equal', 0);
    
            cy.go('back').then(() => {
                cy.go('back');
            });
        };
    
        // Function for dropdown interaction logic
        const handleDropdownSelection = () => {
            cy.get(this._dropDownForLinkPrefetch).then(($buttonLabel) => {
                const selectedText = $buttonLabel.text().trim();
    
                if (selectedText === selectedDropDown) {
                    cy.log('Second option is already selected. Proceeding with the test...');
                    cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
                } else {
                    cy.log('Second option is not selected. Selecting the second option...');
                    cy.get(this._dropDownForLinkPrefetch).click();
                    cy.get(this._mouseDownElement).click();
                    cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
                }
    
                // Visit site first to make the sample page link visible
                cy.get(this._visitSiteButton)
                    .invoke('removeAttr', 'target')
                    .click();
    
                // Wait for the sample page link to appear and extract its URL
                cy.get('.wp-block-pages-list__item__link.wp-block-navigation-item__content', { timeout: 6000 })
                    .should('be.visible')
                    .invoke('attr', 'href')
                    .then((url) => {
                        const pageName = extractPageName(url);
                        cy.go('back'); // Go back after extracting the URL
    
                        visitSiteAndCheckRequestCount(url, pageName);
                    });
            });
        };
    
        handleDropdownSelection(); // Call the refactored function
    }
    
    interceptCallForMouseHoverWithoutExcludeRunTimeURL(selectedDropDown, statusCode) {
        const forceReload = true;
        Cypress.config('defaultCommandTimeout', 4000);
    
        // Function to visit the site, extract URL, trigger mouse hover, and check status code
        const visitSiteAndCheckStatusCode = () => {
            cy.get(this._excludeKeywordInputField).clear();
            
            cy.get(this._visitSiteButton)
                .invoke('removeAttr', 'target')
                .click();
    
            // Wait for the sample page link to appear and extract its URL
            cy.get('.wp-block-pages-list__item__link.wp-block-navigation-item__content', { timeout: 6000 })
                .should('be.visible')
                .invoke('attr', 'href')
                .then((url) => {
                    // Intercept the API request with the extracted URL
                    cy.intercept('GET', url).as('apiRequest');
    
                    cy.reload(forceReload);
                    cy.get(this._samplePageButton).trigger('mouseover'); // Trigger mouse hover
                    cy.wait('@apiRequest');
                    
                    // Validate API response status code
                    cy.get('@apiRequest')
                        .its('response.statusCode')
                        .should('eq', statusCode);
                    
                    cy.go('back');
                });
        };
    
        // Function for dropdown interaction logic
        const handleDropdownSelection = () => {
            cy.get(this._dropDownForLinkPrefetch).then(($buttonLabel) => {
                const selectedText = $buttonLabel.text().trim();
    
                if (selectedText === selectedDropDown) {
                    cy.log('First option is already selected. Proceeding with the test...');
                    cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
                } else {
                    cy.log('First option is not selected. Selecting the first option...');
                    cy.get(this._dropDownForLinkPrefetch).click();
                    cy.get(this._mouseHoverElement).click();
                    cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
                }
    
                // Visit site and check API response
                visitSiteAndCheckStatusCode();
            });
        };
    
        handleDropdownSelection(); // Call the refactored function
    }

    interceptCallForMouseHoverWithExcludeRunTimeURL(selectedDropDown, requestCount) {
        const forceReload = true;
        Cypress.config('defaultCommandTimeout', 4000);
    
        // Function to extract page name from URL
        const extractPageName = (url) => {
            const pageName = url.split('/').filter(Boolean).pop();
            cy.log('Extracted page name:', pageName);
            expect(pageName).to.not.be.empty;
            return pageName;
        };
    
        // Function to visit site, set exclude keyword, and check request count
        const visitSiteAndCheckRequestCount = (url, pageName) => {
            cy.get(this._excludeKeywordInputField)
                .clear()
                .type(pageName);
    
            cy.intercept('GET', url).as('apiRequest');
            cy.get(this._visitSiteButton)
                .invoke('removeAttr', 'target')
                .click({ force: true });
    
            cy.reload(forceReload);
            cy.get(this._samplePageButton).trigger('mouseover');
            cy.wrap(requestCount).should('equal', 0);
    
            cy.go('back').then(() => {
                cy.go('back');
            });
        };
    
        // Function for dropdown interaction logic
        const handleDropdownSelection = () => {
            cy.get(this._dropDownForLinkPrefetch).then(($buttonLabel) => {
                const selectedText = $buttonLabel.text().trim();
    
                if (selectedText === selectedDropDown) {
                    cy.log('First option is already selected. Proceeding with the test...');
                    cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
                } else {
                    cy.log('First option is not selected. Selecting the first option...');
                    cy.get(this._dropDownForLinkPrefetch).click();
                    cy.get(this._mouseHoverElement).click();
                    cy.get(this._dropDownForLinkPrefetch).should('have.text', selectedDropDown);
                }
    
                // Visit site first to make the sample page link visible
                cy.get(this._visitSiteButton)
                    .invoke('removeAttr', 'target')
                    .click();
    
                // Wait for the sample page link to appear and extract its URL
                cy.get('.wp-block-pages-list__item__link.wp-block-navigation-item__content', { timeout: 6000 })
                    .should('be.visible')
                    .invoke('attr', 'href')
                    .then((url) => {
                        const pageName = extractPageName(url);
                        cy.go('back'); // Go back after extracting the URL
    
                        visitSiteAndCheckRequestCount(url, pageName);
                    });
            });
        };
    
        handleDropdownSelection(); // Call the refactored function
    }
}
export default performancePageLocators;
