const { test, expect } = require('@playwright/test');

const BASE = process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://127.0.0.1:8080';
const routes = ['/', '/ontstaan.html', '/baanreglement.html', '/aanmelden.html'];

for (const route of routes) {
  test(`${route} behoudt publieke rendersemantiek achter frontcontroller`, async ({ page }) => {
    await page.setViewportSize({ width: 820, height: 1180 });
    const response = await page.goto(new URL(route, BASE).toString(), {
      waitUntil: 'domcontentloaded',
      timeout: 30000,
    });
    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(400);
    await page.waitForTimeout(400);

    expect((await page.title()).trim(), `${route} mist documenttitel`).not.toBe('');
    expect(await page.locator('link[href*="acceptance-hardening.css"]').count(), `${route} mist hardening CSS`).toBeGreaterThan(0);
    expect(await page.locator('script[src*="acceptance-hardening.js"]').count(), `${route} mist hardening JS`).toBeGreaterThan(0);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `${route} heeft horizontale overflow op tablet`).toBeLessThanOrEqual(3);
  });
}

test('/aanmelden.html behoudt client-side validatiehardening', async ({ page }) => {
  await page.goto(new URL('/aanmelden.html', BASE).toString(), {
    waitUntil: 'domcontentloaded',
    timeout: 30000,
  });
  await page.waitForTimeout(400);
  const form = page.locator('#aanmeld-form');
  expect(await form.count()).toBe(1);
  expect(await form.locator('[required]').count()).toBeGreaterThan(0);
  expect(await form.evaluate((el) => !el.checkValidity())).toBe(true);
});