'use strict';

const { test, expect } = require('@playwright/test');

const BASE = process.env.PLAYWRIGHT_TEST_BASE_URL || 'https://test.vps.holox.nl';
const baseUrl = new URL(BASE.endsWith('/') ? BASE : BASE + '/');
const basePath = baseUrl.pathname.replace(/\/$/, '');
function target(route) {
  return new URL(basePath + route, baseUrl.origin).toString();
}

test('default RC045 taalvoorkeur blijft over publieke paginas gelijk', async ({ page }) => {
  await page.goto(target('/media.html'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.evaluate(() => localStorage.clear());

  await page.evaluate(() => setLang('en'));
  expect(await page.evaluate(() => localStorage.getItem('rc045_lang'))).toBe('en');
  expect(await page.evaluate(() => document.documentElement.lang)).toBe('en');

  // Zonder ?lang moet aanmelden dezelfde opgeslagen key lezen.
  await page.goto(target('/aanmelden.html'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  expect(await page.evaluate(() => document.documentElement.lang)).toBe('en');
  expect(await page.evaluate(() => localStorage.getItem('rc045_lang'))).toBe('en');

  await page.evaluate(() => setLang('de'));
  expect(await page.evaluate(() => localStorage.getItem('rc045_lang'))).toBe('de');

  // Ook de bedanktpagina moet dezelfde default-key lezen en schrijven.
  await page.goto(target('/bedankt.html'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  expect(await page.evaluate(() => document.documentElement.lang)).toBe('de');
  await page.evaluate(() => setLang('en'));
  expect(await page.evaluate(() => localStorage.getItem('rc045_lang'))).toBe('en');
});
