import { test, expect } from '@playwright/test';
import {
  CLOUDFLARE_HASHES,
  setSiteCapabilities,
  clearSiteCapabilities,
  clearFontOptimizationOption,
  getCloudflareToggle,
  verifyCloudflareToggleState,
  setCloudflareToggle,
  assertFrontendSetsOptimizationCookie,
  assertFrontendOmitsOptimizationCookie,
  assertHtaccessHasNoCfOptimizationBlock,
  navigateToPerformancePage,
  waitForPerformancePage,
  ensureHealthyHtaccess,
  auth,
} from '../helpers/index.mjs';

test.describe('Cloudflare Font Optimization Toggle', () => {
  test.beforeEach(async ({ page }) => {
    await clearFontOptimizationOption();
    const htaccess = await ensureHealthyHtaccess();
    test.skip(!htaccess.ok, htaccess.reason);
    await auth.loginToWordPress(page);
  });

  test.afterAll(async () => {
    await clearFontOptimizationOption();
    await clearSiteCapabilities();
  });

  test('Shows Font Optimization section when capability is true and toggle is enabled', async ({ page }) => {
    // Visit page first to initialize, then set capability, then reload
    await navigateToPerformancePage(page);
    let ready = await waitForPerformancePage(page);
    test.skip(!ready, 'Performance page unavailable after recovery attempts.');

    const pre = await setSiteCapabilities({ hasCloudflareFonts: true });
    test.skip(!pre.ok, pre.reason);
    await page.reload();
    ready = await waitForPerformancePage(page);
    test.skip(!ready, 'Performance page unavailable after recovery attempts.');

    // Verify toggle exists and is enabled
    const toggle = getCloudflareToggle(page, 'fonts');
    await expect(toggle).toBeVisible({ timeout: 20000 });
    await expect(toggle).toHaveAttribute('aria-checked', 'true', { timeout: 20000 });

    // Click to disable and verify
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-checked', 'false', { timeout: 20000 });
  });

  test('Does not show Font Optimization section when capability is false', async ({ page }) => {
    const pre = await setSiteCapabilities({ hasCloudflareFonts: false });
    test.skip(!pre.ok, pre.reason);

    await navigateToPerformancePage(page);
    const ready = await waitForPerformancePage(page);
    test.skip(!ready, 'Performance page unavailable after recovery attempts.');

    const toggle = getCloudflareToggle(page, 'fonts');
    await expect(toggle).toHaveCount(0);
  });

  test('Sets the optimization cookie via front-end script (no Set-Cookie header, no .htaccess block) when Font Optimization is enabled', async ({ page }) => {
    const pre = await setSiteCapabilities({ hasCloudflareFonts: true });
    test.skip(!pre.ok, pre.reason);

    await navigateToPerformancePage(page);
    const ready = await waitForPerformancePage(page);
    test.skip(!ready, 'Performance page unavailable after recovery attempts.');

    // Verify toggle is enabled
    await verifyCloudflareToggleState(page, 'fonts', 'true');

    // Front end sets the cookie client-side; the response stays cacheable and the
    // legacy Set-Cookie .htaccess block must not be present.
    await assertFrontendSetsOptimizationCookie(page, CLOUDFLARE_HASHES.fonts);
    await assertHtaccessHasNoCfOptimizationBlock();
  });

  test('Toggles Font Optimization on/off and updates the front-end cookie accordingly', async ({ page }) => {
    const pre = await setSiteCapabilities({ hasCloudflareFonts: true });
    test.skip(!pre.ok, pre.reason);

    await navigateToPerformancePage(page);
    const ready = await waitForPerformancePage(page);
    test.skip(!ready, 'Performance page unavailable after recovery attempts.');

    // Verify initially enabled
    await verifyCloudflareToggleState(page, 'fonts', 'true');
    await assertFrontendSetsOptimizationCookie(page, CLOUDFLARE_HASHES.fonts);

    // Toggle OFF
    await setCloudflareToggle(page, 'fonts', false);
    await assertFrontendOmitsOptimizationCookie(page, CLOUDFLARE_HASHES.fonts);

    // Toggle ON again
    await setCloudflareToggle(page, 'fonts', true);
    await assertFrontendSetsOptimizationCookie(page, CLOUDFLARE_HASHES.fonts);
  });
});
