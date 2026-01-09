import { test, expect } from '@playwright/test';
import {
  SELECTORS,
  setupAndNavigate,
  setSiteCapabilities,
  clearSiteCapabilities,
  clearFontOptimizationOption,
  getCloudflareToggle,
  verifyCloudflareToggleState,
  setCloudflareToggle,
  assertHtaccessHasRule,
  assertHtaccessHasNoRule,
  navigateToPerformancePage,
  waitForPerformancePage,
  auth,
} from '../helpers/index.mjs';

test.describe('Cloudflare Font Optimization Toggle', () => {
  test.beforeEach(async ({ page }) => {
    await clearFontOptimizationOption();
    await auth.loginToWordPress(page);
  });

  test.afterAll(async () => {
    await clearFontOptimizationOption();
    await clearSiteCapabilities();
  });

  test('Shows Font Optimization section when capability is true and toggle is enabled', async ({ page }) => {
    // Visit page first to initialize, then set capability, then reload (matching Cypress pattern)
    await navigateToPerformancePage(page);
    await waitForPerformancePage(page);

    await setSiteCapabilities({ hasCloudflareFonts: true });
    await page.reload();
    await waitForPerformancePage(page);

    // Verify toggle exists and is enabled
    const toggle = getCloudflareToggle(page, 'fonts');
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-checked', 'true');
    
    // Click to disable and verify
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-checked', 'false');
  });

  test('Does not show Font Optimization section when capability is false', async ({ page }) => {
    await setSiteCapabilities({ hasCloudflareFonts: false });

    await navigateToPerformancePage(page);
    await waitForPerformancePage(page);

    const toggle = getCloudflareToggle(page, 'fonts');
    await expect(toggle).toHaveCount(0);
  });

  test('Writes correct rewrite rules to .htaccess when Font Optimization is enabled', async ({ page }) => {
    await setSiteCapabilities({ hasCloudflareFonts: true });

    await navigateToPerformancePage(page);
    await waitForPerformancePage(page);

    // Verify toggle is enabled
    await verifyCloudflareToggleState(page, 'fonts', 'true');

    // Check .htaccess has the rule
    await assertHtaccessHasRule('04d3b602');
  });

  test('Toggles Font Optimization on/off and updates .htaccess accordingly', async ({ page }) => {
    await setSiteCapabilities({ hasCloudflareFonts: true });

    await navigateToPerformancePage(page);
    await waitForPerformancePage(page);

    // Verify initially enabled
    await verifyCloudflareToggleState(page, 'fonts', 'true');
    await assertHtaccessHasRule('04d3b602');

    // Toggle OFF
    await setCloudflareToggle(page, 'fonts', false);
    await assertHtaccessHasNoRule('04d3b602');

    // Toggle ON again
    await setCloudflareToggle(page, 'fonts', true);
    await assertHtaccessHasRule('04d3b602');
  });
});
