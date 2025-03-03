class performancePage {
    //Locators
    _linkPrefetchText = '.newfold-link-prefetch';
    _dropDownForLinkPrefetch = '.nfd-select__button-label';
   _visitSiteButton = 'a.nfd-button.nfd-bg-white';
    _samplePageButton = '.wp-block-pages-list__item__link.wp-block-navigation-item__content';
    _excludeKeywordInputField = '#link-prefetch-ignore-keywords';
    _isToggleEnabled = 'button[data-id="link-prefetch-active-desktop"]';
    _selectedDropDown = '.nfd-select__option-label.nfd-font-semibold';
    _installButton = 'button.nfd-button--upsell';
    _upgradeButton = 'a.nfd-button.nfd-button--upsell';
    _boostLink = 'a[href="admin.php?page=jetpack-boost"]';
    _startForFreeButton = 'button:contains("Start for free")';
    _mobileScoreBar = '.jb-score-bar--mobile .jb-score-bar__filler';
    _advancedSettings = 'h3.nfd-title.nfd-title--4';
    _clearCacheButton = '.clear-cache-button';
    _notificationMessage = '.nfd-notifications';
    _cacheLevelOff = 'input[type="radio"]#cache-level-0';
    _cacheLevelOn = 'input[type="radio"]#cache-level-1';
    _clearCacheSettings = '.newfold-clear-cache';

    //All the methods related to performance page.
    getLinkPrefetchText() {
        return cy.get(this._linkPrefetchText);
    }

    getDropDownForLinkPrefetch() {
        return cy.get(this._dropDownForLinkPrefetch);
    }

    getVisitSiteButton() {
        return cy.get(this._visitSiteButton);
    }

    getSamplePageButton() {
        return cy.get(this._samplePageButton);
    }

    //Get Exclude input field
    getExcludeKeywordInputField() {
        return cy.get(this._excludeKeywordInputField);
    }

    getIsToggleEnabled() {
        return cy.get(this._isToggleEnabled);
    }

    getDropDownOptionLabel() {
        return cy.get('.nfd-select__option-label.nfd-font-semibold');
    }

    getListItems() {
        return cy.get('ul.nfd-select__options > li');
    }

    getInstallButton() {
        return cy.get(this._installButton);
    }

    getUpgradeButton() {
        return cy.get(this._upgradeButton);
    }

    getBoostLink() {
        return cy.get(this._boostLink);
    }

    getStartForFreeButton() {
        return cy.get(this._startForFreeButton);
    }

    getMobileScoreBar() {
        return cy.get(this._mobileScoreBar);
    }

    getAdvancedSettings() {
        return cy.get(this._advancedSettings).contains('Advanced settings');
    }
    interceptRequest(method, url, alias) {
        cy.intercept(method, url).as(alias);
    }

    getClearCacheButton() {
        return cy.get(this._clearCacheButton);
    }

    clickClearCacheButton() {
        this.getClearCacheButton().click();
    }

    getNotificationMessage() {
        return cy.get(this._notificationMessage);
    }
    verifyCacheClearedNotification() {
        this.getNotificationMessage()
            .contains('p', 'Cache cleared')
            .should('be.visible');
    }

    getCacheLevelOff() {
        return cy.get(this._cacheLevelOff);
    }
    getCacheLevelOn() {
        return cy.get(this._cacheLevelOn);
    }
    get clearCacheSettings() {
        return cy.get(this._clearCacheSettings);
    }

    selectCacheLevelOff() {
        this.getCacheLevelOff().check();
    }
    selectCacheLevelOn() {
        this.getCacheLevelOn().check();
    }

    verifyClearCacheButtonDisabled() {
        this.getClearCacheButton()
            .scrollIntoView()
            .should('have.attr', 'disabled');
    }
    verifyClearCacheButtonEnabled() {
        this.getClearCacheButton()
            .scrollIntoView()
            .should('not.have.attr', 'disabled');
    }
    selectCacheLevel(level) {
        if (level === 0) {
            this.selectCacheLevelOff();
        }
        else {
            this.selectCacheLevelOn();
        }
        cy.wait(500);
    }

    verifyClearCacheSettingsVisible() {
        this.clearCacheSettings.scrollIntoView().should('be.visible');
    }

    verifyAccessibility(appClass) {
        cy.wait(500);
        cy.checkA11y(`${appClass}-app-body`);
    }
    visitSamplePageAndIntercept(alias) {
        // Wait for the sample page link to be visible
        this.getSamplePageButton()
            .should('be.visible')
            .invoke('attr', 'href')
            .then((samplePageUrl) => {
                // Intercept the sample page URL with the given alias
                this.interceptRequest('GET', samplePageUrl, alias);

                // Reload the page after intercepting the URL
                cy.reload(true);
            });
    }

    assertApiRequest(alias, expectedValue, checkType = 'status') {
        cy.wait(`@${alias}`).then((interception) => {
            if (checkType === 'status') {
                expect(interception.response.statusCode).to.eq(expectedValue);
            } else if (checkType === 'requestCount') {
                expect(interception.requestCount || 0).to.eq(expectedValue);
            }
        });
    }

    extractSamplePageName(callback) {
        this.getSamplePageButton()
            .should('be.visible')
            .invoke('text')
            .then((pageName) => {
                cy.log(`Extracted Page Name: ${pageName}`);
                callback(pageName.trim()); // Use a callback to handle async execution
            });
    }

    verifyIfLinkPreFetchIsDisplayed() {
        this.getLinkPrefetchText()
            .scrollIntoView()
            .should('be.visible');
    }

    verifyIfToggleIsEnabled() {
        this.getIsToggleEnabled().then(($toggle) => {
            if ($toggle.attr('aria-checked') === 'false') {
                cy.wrap($toggle).click();
            }
        });
        this.getIsToggleEnabled().should(
            'have.attr',
            'aria-checked',
            'true'
        );
    }

    compareDropdownLabelAndSelectedOption() {
        // Get the text from the dropdown button label
        this.getDropDownForLinkPrefetch()
            .invoke('text')
            .then((buttonLabel) => {
                const trimmedButtonLabel = buttonLabel.trim();

                // Get the text from the selected option in the dropdown
                this.getDropDownOptionLabel()
                    .invoke('text')
                    .should('not.be.empty')
                    .then((selectedOptionText) => {
                        const trimmedOptionText = selectedOptionText.trim();
                        cy.log(`Dropdown Button Label: ${trimmedButtonLabel}`);
                        cy.log(`Selected Option Text: ${trimmedOptionText}`);

                        // Assert that both values are the same
                        expect(trimmedButtonLabel).to.eq(trimmedOptionText);
                    });
            });
    }

    interceptCallForMouseDownWithoutExcludeRunTimeURL(statusCode) {

        // Get the currently selected dropdown option
        this.getDropDownForLinkPrefetch()
            .invoke('text')
            .should('not.be.empty') // Ensure dropdown text is present
            .then((selectedText) => {
                selectedText = selectedText.trim();
                cy.log(`Currently selected option: ${selectedText}`);

                // Open the dropdown and get all options
                this.getDropDownForLinkPrefetch().click();
                this.compareDropdownLabelAndSelectedOption();

                this.getListItems() // Get all options dynamically
                    .should('have.length.at.least', 2) // Ensure there are at least two options
                    .then(($options) => {
                        const optionsText = $options.map((i, el) => Cypress.$(el).text().trim()).get();
                        cy.log(`Available options: ${optionsText}`);

                        // Select the second option if it's different from the current one
                        if (selectedText !== optionsText[1]) {
                            cy.wrap($options[1]).click(); // Click on the second option

                            // Verify the selection was updated correctly
                            this.getDropDownForLinkPrefetch()
                                .invoke('text')
                                .should('eq', optionsText[1]);
                        } else {
                            cy.log('Second option is already selected.');
                        }
                    });
            });

        // Clear exclude keyword input field
        this.getExcludeKeywordInputField().clear();

        // Visit site
        this.getVisitSiteButton()
            .invoke('removeAttr', 'target')
            .click();

        // Handle sample page navigation
        this.getSamplePageButton({ timeout: 6000 })
            .should('be.visible')
            .invoke('attr', 'href')
            .then((url) => {
                const alias = 'apiRequest';
                this.visitSamplePageAndIntercept(alias);

                this.getSamplePageButton().click();
                this.assertApiRequest(alias, statusCode, 'status');

                cy.go('back').go('back');
            });
    }

    interceptCallForMouseDownWithExcludeRunTimeURL(requestCount) {

        // Get the currently selected dropdown option
        this.getDropDownForLinkPrefetch()
            .invoke('text')
            .should('not.be.empty') // Ensure dropdown text is present
            .then((selectedText) => {
                selectedText = selectedText.trim();
                cy.log(`Currently selected option: ${selectedText}`);

                // Open the dropdown and get all options
                this.getDropDownForLinkPrefetch().click();
                this.compareDropdownLabelAndSelectedOption();

                this.getListItems() // Dynamically get all dropdown options
                    .should('have.length.at.least', 2) // Ensure at least two options exist
                    .then(($options) => {
                        const optionsText = $options.map((i, el) => Cypress.$(el).text().trim()).get();
                        cy.log(`Available options: ${optionsText}`);

                        // Select the second option (if it's different from the current one)
                        if (selectedText !== optionsText[1]) {
                            cy.wrap($options[1]).click(); // Click the second option

                            // Verify that the dropdown selection is updated
                            this.getDropDownForLinkPrefetch()
                                .invoke('text')
                                .should('eq', optionsText[1]);
                        } else {
                            cy.log('Second option is already selected.');
                        }
                    });
            });

        // Visit the site
        this.getVisitSiteButton()
            .invoke('removeAttr', 'target') // Prevent opening in a new tab
            .click();

        // Use the reusable function to extract the Sample Page Name
        this.extractSamplePageName((samplePageText) => {
            cy.go('back');

            this.getExcludeKeywordInputField()
                .clear()
                .type(samplePageText)
                .should('have.value', samplePageText);

            this.getVisitSiteButton()
                .invoke('removeAttr', 'target')
                .click();

            // Intercept the API call related to the sample page
            const alias = 'apiRequest';
            this.visitSamplePageAndIntercept(alias); // Using your existing method for intercepting the request

            // Perform Mouse Down on the Sample Page Button
            this.getSamplePageButton().click();

            // Assert API request count
            this.assertApiRequest(alias, requestCount, 'requestCount');

            cy.go('back').go('back');
        });
    }
    interceptCallForMouseHoverWithExcludeRunTimeURL(requestCount) {
        const forceReload = true;

        // Get the currently selected dropdown option
        this.getDropDownForLinkPrefetch()
            .invoke('text')
            .should('not.be.empty')
            .then((selectedText) => {
                selectedText = selectedText.trim();
                cy.log(`Currently selected option: ${selectedText}`);

                // Open dropdown and check available options
                this.getDropDownForLinkPrefetch().click();
                this.compareDropdownLabelAndSelectedOption();

                this.getListItems()
                    .should('have.length.at.least', 2)
                    .then(($options) => {
                        const firstOption = Cypress.$($options[0]).text().trim();
                        cy.log(`First dropdown option: ${firstOption}`);

                        if (selectedText !== firstOption) {
                            cy.wrap($options[0]).click();
                            this.getDropDownForLinkPrefetch()
                                .invoke('text')
                                .should('eq', firstOption);
                        } else {
                            cy.log('First option is already selected.');
                        }
                    });
            });

        // Visit the site
        this.getVisitSiteButton()
            .invoke('removeAttr', 'target')
            .click();

        // Extract Sample Page Name & Continue Actions
        this.getSamplePageButton()
            .invoke('prop', 'href')
            .then((url) => {
                const pageName = url.split('/').filter(Boolean).pop();
                cy.log(`Extracted page name: ${pageName}`);
                expect(pageName).to.not.be.empty;

                cy.go('back');

                // Enter extracted page name into Exclude Keywords field
                this.getExcludeKeywordInputField()
                    .clear()
                    .type(pageName)
                    .should('have.value', pageName);

                // Revisit site after setting the exclusion keyword
                this.getVisitSiteButton()
                    .invoke('removeAttr', 'target')
                    .click({ force: true });

                cy.reload(forceReload);

                // Intercept API call for the sample page
                cy.intercept('GET', url).as('apiRequest');

                // Perform Mouse Hover on the Sample Page Button
                this.getSamplePageButton().trigger('mouseover');

                // Assert API request count
                cy.wrap(requestCount).should('equal', 0);

                // Navigate back twice to return to the original page
                cy.go('back').then(() => {
                    cy.go('back');
                });
            });
    }

    installOrUpgradeFeatureForJetPack() {

        cy.get('body').then(($body) => {
            if ($body.find(this._installButton).length) {
                // Install button is present
                cy.get(this._installButton, { timeout: 10000 })
                    .should('be.visible')
                    .then(($btn) => {
                        cy.wrap($btn).click({ force: true }); // Handle cases where button may be detached
                    });

                // Ensure Upgrade button appears after clicking Install
                cy.wait(10000);
                cy.get(this._upgradeButton, { timeout: 10000 })
                    .should('be.visible')
                    .then(($btn) => {
                        cy.wrap($btn).invoke('removeAttr', 'target').click({ force: true });
                    });
            } else {
                // If Install button is NOT present, click Upgrade button directly
                cy.get(this._upgradeButton, { timeout: 10000 })
                    .should('be.visible')
                    .then(($btn) => {
                        cy.wrap($btn).invoke('removeAttr', 'target').click({ force: true });
                    });
            }
        });

    }

    scrollToAdvancedSettings() {
        this.getAdvancedSettings()
            .scrollIntoView()
            .should('be.visible');
    }

    clickBoostLink() {
        this.getBoostLink()
            .scrollIntoView()
            .should('be.visible')
            .click();
    }

    handleStartForFree() {
        cy.get('body').then(($body) => {
            if ($body.find(this._startForFreeButton).length > 0) {
                this.getStartForFreeButton()
                    .should('be.visible')
                    .click({ force: true }).wait(5000);;
            }
        });
    }

    verifyMobileScoreBar() {
        this.getMobileScoreBar()
            .scrollIntoView()
            .should('be.visible');
    }

    handleBoostAndMobileCheck() {
        this.clickBoostLink();
        this.handleStartForFree();
        this.verifyMobileScoreBar();
    }
}

export default performancePage;
