import performancePageLocators from '../../../../wp-module-performance/tests/cypress/support/pageObjects/performancePage';
describe('Performance Page', { testIsolation: false }, () => {
    const appClass = '.' + Cypress.env('appId');
    const fixturePath = require('../../../../../../vendor/newfold-labs/wp-module-performance/tests/cypress/fixtures/performanceModule.json');
    let performanceLocators;
    let data;

    beforeEach(() => {
        data = fixturePath;
        cy.login(Cypress.env("wpUsername"), Cypress.env("wpPassword"));
        cy.visit(
            '/wp-admin/admin.php?page=' +
            Cypress.env('pluginId') +
            '#/performance'
        );
        cy.injectAxe();
        performanceLocators = new performancePageLocators();
    });

    it('Is Accessible', () => {
        performanceLocators.verifyAccessibility(appClass); // Passing appClass from the test
    });

    it('Has Cache Settings', () => {
        performanceLocators.verifyClearCacheSettingsVisible();
    });

    it('Clear Cache Disabled when Cache is Disabled', () => {
        performanceLocators.selectCacheLevel(data.cacheLevelZero);
        performanceLocators.isClearCacheButtonDisabled;
        performanceLocators.selectCacheLevel(data.cacheLevelOne);
        performanceLocators.isClearCacheButtonEnabled;
        performanceLocators.verifyCacheClearedNotification;
    });

    it('Clear Cache Button Functions', () => {
        performanceLocators.clickClearCacheButton();
        performanceLocators.verifyCacheClearedNotification();
    });

    it.only('Mouse down-> without exclude: Verify if "Link Prefetch" is displayed and intercept the network call', () => {
        performanceLocators.verifyIfLinkPreFetchIsDisplayed();
        performanceLocators.verifyIfToggleIsEnabled();
        performanceLocators.interceptCallForMouseDownWithoutExcludeRunTimeURL(
            data.statusCode

        );
    });

    it('Mouse Down-> with exclude:Extract RunTime Link value>>Verify if "Link Prefetch" is displayed and intercept the network call', () => {
        performanceLocators.verifyIfLinkPreFetchIsDisplayed();
        performanceLocators.verifyIfToggleIsEnabled();
        performanceLocators.interceptCallForMouseDownWithExcludeRunTimeURL(
            data.requestCount
        );
    });


    it('Mouse Hover-> with exclude:Verify if "Link Prefetch" is displayed and intercept network call', () => {
        performanceLocators.verifyIfLinkPreFetchIsDisplayed();
        performanceLocators.verifyIfToggleIsEnabled();
        performanceLocators.interceptCallForMouseHoverWithExcludeRunTimeURL(
            data.requestCount
        );
    });

    it('Handle JetPack boost', () => {
        performanceLocators.scrollToAdvancedSettings();
        performanceLocators.installOrUpgradeFeatureForJetPack();
        performanceLocators.clickBoostLink();
        performanceLocators.handleBoostAndMobileCheck();

    });
});
