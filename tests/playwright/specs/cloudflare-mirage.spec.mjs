import { test, expect } from '@playwright/test';
import {
  SELECTORS,
  setSiteCapabilities,
  clearSiteCapabilities,
  clearImageOptimizationOption,
  getCloudflareToggle,
  verifyCloudflareToggleState,
  setCloudflareToggle,
  assertHtaccessHasRule,
  assertHtaccessHasNoRule,
  navigateToPerformancePage,
  waitForPerformancePage,
  auth,
} from '../helpers/index.mjs';

test.describe('Cloudflare Mirage Toggle', () => {
  test.beforeEach(async ({ page }) => {
    await clearImageOptimizationOption();
    await auth.loginToWordPress(page);
  });

  test.afterAll(async () => {
    await clearImageOptimizationOption();
    await clearSiteCapabilities();
  });

  test('Shows Mirage section when capability is true and toggle is enabled', async ({ page }) => {
    await setSiteCapabilities({ hasCloudflareMirage: true });

    await navigateToPerformancePage(page);
    await waitForPerformancePage(page);

    // Verify toggle exists and is enabled
    const toggle = getCloudflareToggle(page, 'mirage');
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-checked', 'true');
    
    // Click to disable and verify
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-checked', 'false');
  });

  test('Does not show Mirage section when capability is false', async ({ page }) => {
    await setSiteCapabilities({ hasCloudflareMirage: false });

    await navigateToPerformancePage(page);
    await waitForPerformancePage(page);

    const toggle = getCloudflareToggle(page, 'mirage');
    await expect(toggle).toHaveCount(0);
  });

  test('Writes correct rewrite rules to .htaccess when Mirage is enabled', async ({ page }) => {
    await setSiteCapabilities({ hasCloudflareMirage: true });

    await navigateToPerformancePage(page);
    await waitForPerformancePage(page);

    // Verify toggle is enabled
    await verifyCloudflareToggleState(page, 'mirage', 'true');

    // Check .htaccess has the rule
    await assertHtaccessHasRule('63a6825d');
  });

  test('Toggles Mirage on/off and updates .htaccess accordingly', async ({ page }) => {
    await setSiteCapabilities({ hasCloudflareMirage: true });

    await navigateToPerformancePage(page);
    await waitForPerformancePage(page);

    // Verify initially enabled
    await verifyCloudflareToggleState(page, 'mirage', 'true');
    await assertHtaccessHasRule('63a6825d');

    // Toggle OFF
    await setCloudflareToggle(page, 'mirage', false);
    await assertHtaccessHasNoRule('63a6825d');

    // Toggle ON again
    await setCloudflareToggle(page, 'mirage', true);
    await assertHtaccessHasRule('63a6825d');
  });
});
