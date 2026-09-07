'use strict';

const { test, expect } = require('@playwright/test');

const BASE = process.env.PLAYWRIGHT_TEST_BASE_URL || 'https://test.vps.holox.nl';
const baseUrl = new URL(BASE.endsWith('/') ? BASE : BASE + '/');
const basePath = baseUrl.pathname.replace(/\/$/, '');
function target(route) {
  return new URL(basePath + route, baseUrl.origin).toString();
}

async function languageStorageState(page) {
  return page.evaluate(() => {
    const context = window.verenigingSiteContext || null;
    return {
      external: Boolean(context && context.external),
      tenantKey: context && typeof context.tenantKey === 'string' ? context.tenantKey : null,
      displayName: context && typeof context.name === 'string' ? context.name : null,
      storageKey: getLanguageStorageKey(),
      stored: getStoredLanguage(),
      legacy: localStorage.getItem('rc045_lang'),
      htmlLang: document.documentElement.lang,
    };
  });
}

test('taalvoorkeur gebruikt dezelfde tenantbewuste sleutel over publieke paginas', async ({ page }) => {
  await page.goto(target('/media.html'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.evaluate(() => localStorage.clear());

  const initial = await languageStorageState(page);
  expect(initial.storageKey).toBeTruthy();
  if (initial.external) {
    expect(initial.tenantKey).toBeTruthy();
    expect(initial.storageKey).toBe(initial.tenantKey + '_lang');
    expect(initial.storageKey).not.toBe('rc045_lang');
  } else {
    expect(initial.storageKey).toBe('rc045_lang');
  }

  await page.evaluate(() => setLang('en'));
  const media = await languageStorageState(page);
  expect(media.stored).toBe('en');
  expect(media.htmlLang).toBe('en');
  expect(media.storageKey).toBe(initial.storageKey);
  if (media.external) expect(media.legacy).toBeNull();

  // Zonder ?lang moet aanmelden exact dezelfde opgeslagen tenantkey lezen.
  await page.goto(target('/aanmelden.html'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const aanmelden = await languageStorageState(page);
  expect(aanmelden.storageKey).toBe(initial.storageKey);
  expect(aanmelden.stored).toBe('en');
  expect(aanmelden.htmlLang).toBe('en');
  if (aanmelden.external) expect(aanmelden.legacy).toBeNull();

  await page.evaluate(() => setLang('de'));
  const aanmeldenDe = await languageStorageState(page);
  expect(aanmeldenDe.stored).toBe('de');
  if (aanmeldenDe.external) expect(aanmeldenDe.legacy).toBeNull();

  // Ook de bedanktpagina leest en schrijft hetzelfde centrale contract.
  await page.goto(target('/bedankt.html'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const bedankt = await languageStorageState(page);
  expect(bedankt.storageKey).toBe(initial.storageKey);
  expect(bedankt.stored).toBe('de');
  expect(bedankt.htmlLang).toBe('de');

  await page.evaluate(() => setLang('en'));
  const bedanktEn = await languageStorageState(page);
  expect(bedanktEn.stored).toBe('en');
  if (bedanktEn.external) expect(bedanktEn.legacy).toBeNull();
});
