const { test, expect } = require('@playwright/test');

const BASE = process.env.PLAYWRIGHT_TEST_BASE_URL || 'https://rc045.nl/dev';
const baseUrl = new URL(BASE.endsWith('/') ? BASE : BASE + '/');
const basePath = baseUrl.pathname.replace(/\/$/, '');
const outputDir = 'test-results/screenshots';

const viewports = [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'tablet', width: 820, height: 1180 },
  { name: 'mobile', width: 390, height: 844 },
];
const entryRoutes = ['/', '/ontstaan.html', '/baanreglement.html', '/aanmelden.html', '/beheer/', '/leden/'];

function target(route) { return new URL(basePath + (route === '/' ? '/' : route), baseUrl.origin).toString(); }
function slug(route) { if (route === '/') return 'home'; return route.replace(/^\//, '').replace(/\/$/, '-index').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'page'; }

async function inspectPage(page, route, viewportName) {
  const consoleErrors = [];
  const pageErrors = [];
  const failedSameOrigin = [];
  const badResponses = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      const loc = msg.location();
      consoleErrors.push(`${msg.text()}${loc?.url ? ` @ ${loc.url}` : ''}`);
    }
  });
  page.on('pageerror', err => pageErrors.push(String(err)));
  page.on('requestfailed', req => {
    try {
      const u = new URL(req.url());
      if (u.origin === baseUrl.origin && u.pathname.startsWith(basePath + '/')) failedSameOrigin.push(`${req.method()} ${req.url()} :: ${req.failure()?.errorText || 'failed'}`);
    } catch (_) {}
  });
  page.on('response', res => {
    try {
      const u = new URL(res.url());
      if (res.status() >= 400 && u.origin === baseUrl.origin && u.pathname.startsWith(basePath + '/')) badResponses.push(`${res.status()} ${res.request().method()} ${res.url()}`);
    } catch (_) {}
  });

  const response = await page.goto(target(route), { waitUntil: 'domcontentloaded', timeout: 45000 });
  expect(response, `Geen hoofdresponse voor ${route}`).not.toBeNull();
  expect(response.status(), `${route} gaf HTTP ${response.status()}`).toBeLessThan(400);
  await page.waitForTimeout(1200);

  const diagnostics = await page.evaluate(() => {
    const visible = el => {
      const s = getComputedStyle(el); const r = el.getBoundingClientRect();
      return s.display !== 'none' && s.visibility !== 'hidden' && Number(s.opacity || '1') > 0 && r.width > 0 && r.height > 0;
    };
    const label = el => {
      const id = el.id ? `#${el.id}` : '';
      const cls = typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).slice(0,4).join('.') : '';
      return `${el.tagName.toLowerCase()}${id}${cls}`;
    };
    const clientWidth = document.documentElement.clientWidth;
    const brokenImages = [...document.images].filter(img => visible(img) && (!img.complete || img.naturalWidth === 0)).map(img => img.currentSrc || img.src || img.alt || '<img>');
    const overflowElements = [...document.body.querySelectorAll('*')]
      .filter(visible)
      .map(el => ({ el, r: el.getBoundingClientRect(), sw: el.scrollWidth, cw: el.clientWidth }))
      .filter(x => x.r.right > clientWidth + 3 || x.r.left < -3 || x.sw > Math.max(x.cw + 3, clientWidth + 3))
      .sort((a,b) => Math.max(b.r.right-clientWidth,b.sw-clientWidth) - Math.max(a.r.right-clientWidth,a.sw-clientWidth))
      .slice(0,30)
      .map(x => `${label(x.el)} rect=[${Math.round(x.r.left)},${Math.round(x.r.right)}] width=${Math.round(x.r.width)} scroll=${x.sw}/${x.cw}`);
    const unlabeledInputs = [...document.querySelectorAll('input, select, textarea')]
      .filter(el => visible(el) && el.type !== 'hidden' && el.type !== 'submit' && el.type !== 'button')
      .filter(el => !(el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.title || (el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`)) || el.closest('label')))
      .map(el => `${el.tagName.toLowerCase()}#${el.id || ''}[name="${el.name || ''}"]`);
    const tinyControls = [...document.querySelectorAll('button, a, input[type="submit"], input[type="button"]')]
      .filter(visible).map(el => ({ el, r: el.getBoundingClientRect() }))
      .filter(x => x.r.width < 24 || x.r.height < 24).slice(0,30)
      .map(x => `${label(x.el)}:${(x.el.textContent || x.el.getAttribute('aria-label') || '').trim().slice(0,40)} ${Math.round(x.r.width)}x${Math.round(x.r.height)}`);
    const main = document.querySelector('main') || document.body;
    return {
      title: document.title, lang: document.documentElement.lang, h1Count: document.querySelectorAll('h1').length,
      mainTextLength: (main.innerText || '').trim().length,
      scrollWidth: document.documentElement.scrollWidth, clientWidth,
      brokenImages, overflowElements, unlabeledInputs, tinyControls,
    };
  });

  await page.screenshot({ path: `${outputDir}/${viewportName}-${slug(route)}.png`, fullPage: true });
  console.log(`DIAGNOSTICS ${viewportName} ${route}: ${JSON.stringify({ diagnostics, badResponses, consoleErrors, pageErrors, failedSameOrigin })}`);

  expect(diagnostics.title.trim(), `${route} heeft geen documenttitel`).not.toBe('');
  expect(diagnostics.lang, `${route} mist html[lang]`).toMatch(/^[a-z]{2}(?:-|$)/i);
  expect(diagnostics.h1Count, `${route} moet exact één document-H1 hebben`).toBe(1);
  expect(diagnostics.mainTextLength, `${route} heeft vrijwel geen hoofdcontent`).toBeGreaterThan(40);
  expect(diagnostics.scrollWidth, `${route} heeft horizontale overflow bij ${viewportName}: ${diagnostics.overflowElements.join(' | ')}`).toBeLessThanOrEqual(diagnostics.clientWidth + 3);
  expect(diagnostics.brokenImages, `${route} heeft kapotte zichtbare afbeeldingen`).toEqual([]);
  expect(diagnostics.unlabeledInputs, `${route} heeft ongelabelde formuliervelden`).toEqual([]);
  expect(diagnostics.tinyControls, `${route} bevat extreem kleine interactieve controls`).toEqual([]);
  expect(pageErrors, `${route} veroorzaakte JavaScript page errors`).toEqual([]);
  expect(failedSameOrigin, `${route} heeft mislukte same-origin requests`).toEqual([]);
  expect(badResponses, `${route} heeft same-origin HTTP-fouten`).toEqual([]);
  expect(consoleErrors, `${route} schreef console.error`).toEqual([]);
}

for (const viewport of viewports) {
  test.describe(`${viewport.name} optische acceptatie`, () => {
    test.use({ viewport: { width: viewport.width, height: viewport.height } });
    for (const route of entryRoutes) test(`${route} rendert foutvrij`, async ({ page }) => inspectPage(page, route, viewport.name));
  });
}

test('crawl alle zichtbare interne publiekslinks vanaf home', async ({ page }) => {
  test.setTimeout(120000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  const res = await page.goto(target('/'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  expect(res.status()).toBeLessThan(400);
  await page.waitForTimeout(700);
  const links = await page.evaluate(({ origin, basePath }) => {
    const out = new Set();
    for (const a of document.querySelectorAll('a[href]')) {
      try {
        const u = new URL(a.href, location.href);
        if (u.origin !== origin || !u.pathname.startsWith(basePath + '/')) continue;
        if (/\.(?:jpg|jpeg|png|webp|gif|svg|pdf|zip|css|js|json|xml|ico)$/i.test(u.pathname)) continue;
        u.hash = ''; out.add(u.toString());
      } catch (_) {}
    }
    return [...out].slice(0, 60);
  }, { origin: baseUrl.origin, basePath });
  expect(links.length, 'Home moet interne navigatielinks opleveren').toBeGreaterThan(3);
  for (const url of links) {
    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    expect(response, `Geen response voor ${url}`).not.toBeNull();
    expect(response.status(), `${url} gaf HTTP ${response.status()}`).toBeLessThan(400);
    const hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 3);
    expect(hasOverflow, `${url} heeft horizontale overflow`).toBe(false);
  }
});

test('publieke formulieren hebben werkende browservalidatie zonder mutatie', async ({ page }) => {
  await page.goto(target('/'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(500);
  const homeForms = page.locator('form');
  expect(await homeForms.count(), 'Homepage bevat geen testbaar formulier').toBeGreaterThan(0);
  for (let i = 0; i < await homeForms.count(); i++) {
    const form = homeForms.nth(i); const requiredCount = await form.locator('[required]').count();
    if (requiredCount > 0) expect(await form.evaluate(el => !el.checkValidity())).toBe(true);
  }
  await page.goto(target('/aanmelden.html'), { waitUntil: 'domcontentloaded', timeout: 45000 });
  const form = page.locator('form').first();
  expect(await form.count(), 'Aanmeldpagina bevat geen formulier').toBe(1);
  expect(await form.locator('[required]').count(), 'Aanmeldformulier heeft geen verplichte velden').toBeGreaterThan(0);
  expect((await form.getAttribute('method') || 'get').toLowerCase(), 'Aanmeldformulier moet mutaties via POST doen').toBe('post');
});
