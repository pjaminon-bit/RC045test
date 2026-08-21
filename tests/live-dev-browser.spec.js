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

function target(route) {
  const suffix = route === '/' ? '/' : route;
  return new URL(basePath + suffix, baseUrl.origin).toString();
}

function slug(route) {
  if (route === '/') return 'home';
  return route.replace(/^\//, '').replace(/\/$/, '-index').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'page';
}

async function inspectPage(page, route, viewportName) {
  const consoleErrors = [];
  const pageErrors = [];
  const failedSameOrigin = [];
  page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
  page.on('pageerror', err => pageErrors.push(String(err)));
  page.on('requestfailed', req => {
    try {
      const u = new URL(req.url());
      if (u.origin === baseUrl.origin && u.pathname.startsWith(basePath + '/')) {
        failedSameOrigin.push(`${req.method()} ${req.url()} :: ${req.failure()?.errorText || 'failed'}`);
      }
    } catch (_) {}
  });

  const response = await page.goto(target(route), { waitUntil: 'networkidle', timeout: 45000 });
  expect(response, `Geen hoofdresponse voor ${route}`).not.toBeNull();
  expect(response.status(), `${route} gaf HTTP ${response.status()}`).toBeLessThan(400);
  await page.waitForTimeout(350);

  const diagnostics = await page.evaluate(() => {
    const visible = el => {
      const s = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return s.display !== 'none' && s.visibility !== 'hidden' && Number(s.opacity || '1') > 0 && r.width > 0 && r.height > 0;
    };
    const brokenImages = [...document.images]
      .filter(img => visible(img) && (!img.complete || img.naturalWidth === 0))
      .map(img => img.currentSrc || img.src || img.alt || '<img>');
    const unlabeledInputs = [...document.querySelectorAll('input, select, textarea')]
      .filter(el => visible(el) && el.type !== 'hidden' && el.type !== 'submit' && el.type !== 'button')
      .filter(el => {
        if (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.title) return false;
        if (el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`)) return false;
        return !el.closest('label');
      })
      .map(el => `${el.tagName.toLowerCase()}#${el.id || ''}[name="${el.name || ''}"]`);
    const tinyControls = [...document.querySelectorAll('button, a, input[type="submit"], input[type="button"]')]
      .filter(visible)
      .map(el => ({ el, r: el.getBoundingClientRect() }))
      .filter(x => x.r.width < 24 || x.r.height < 24)
      .slice(0, 20)
      .map(x => `${x.el.tagName.toLowerCase()}:${(x.el.textContent || x.el.getAttribute('aria-label') || '').trim().slice(0,40)} ${Math.round(x.r.width)}x${Math.round(x.r.height)}`);
    const viewportEscape = [...document.querySelectorAll('main, header, nav, footer, section, article, form, table, img')]
      .filter(visible)
      .map(el => ({ el, r: el.getBoundingClientRect() }))
      .filter(x => x.r.right > document.documentElement.clientWidth + 3 || x.r.left < -3)
      .slice(0, 20)
      .map(x => `${x.el.tagName.toLowerCase()}.${String(x.el.className || '').slice(0,50)} [${Math.round(x.r.left)},${Math.round(x.r.right)}]`);
    const main = document.querySelector('main') || document.body;
    const mainText = (main.innerText || '').trim();
    return {
      title: document.title,
      lang: document.documentElement.lang,
      h1Count: document.querySelectorAll('h1').length,
      mainTextLength: mainText.length,
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
      brokenImages,
      unlabeledInputs,
      tinyControls,
      viewportEscape,
    };
  });

  expect(diagnostics.title.trim(), `${route} heeft geen documenttitel`).not.toBe('');
  expect(diagnostics.lang, `${route} mist html[lang]`).toMatch(/^[a-z]{2}(?:-|$)/i);
  expect(diagnostics.h1Count, `${route} moet exact één zichtbare document-H1 hebben`).toBe(1);
  expect(diagnostics.mainTextLength, `${route} heeft vrijwel geen hoofdcontent`).toBeGreaterThan(40);
  expect(diagnostics.scrollWidth, `${route} heeft horizontale overflow bij ${viewportName}`).toBeLessThanOrEqual(diagnostics.clientWidth + 3);
  expect(diagnostics.brokenImages, `${route} heeft kapotte zichtbare afbeeldingen`).toEqual([]);
  expect(diagnostics.viewportEscape, `${route} heeft hoofdlayoutelementen buiten de viewport`).toEqual([]);
  expect(diagnostics.unlabeledInputs, `${route} heeft ongelabelde formuliervelden`).toEqual([]);
  // 24px is bewust een ondergrens; WCAG-doelgroottes kunnen groter zijn, maar zeer kleine controls zijn hier altijd verdacht.
  expect(diagnostics.tinyControls, `${route} bevat extreem kleine interactieve controls`).toEqual([]);
  expect(pageErrors, `${route} veroorzaakte JavaScript page errors`).toEqual([]);
  expect(failedSameOrigin, `${route} heeft mislukte same-origin requests`).toEqual([]);
  expect(consoleErrors, `${route} schreef console.error`).toEqual([]);

  await page.screenshot({ path: `${outputDir}/${viewportName}-${slug(route)}.png`, fullPage: true });
}

for (const viewport of viewports) {
  test.describe(`${viewport.name} optische acceptatie`, () => {
    test.use({ viewport: { width: viewport.width, height: viewport.height } });
    for (const route of entryRoutes) {
      test(`${route} rendert foutvrij`, async ({ page }) => {
        await inspectPage(page, route, viewport.name);
      });
    }
  });
}

test('crawl alle zichtbare interne publiekslinks vanaf home', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  const res = await page.goto(target('/'), { waitUntil: 'networkidle', timeout: 45000 });
  expect(res.status()).toBeLessThan(400);
  const links = await page.evaluate(({ origin, basePath }) => {
    const out = new Set();
    for (const a of document.querySelectorAll('a[href]')) {
      try {
        const u = new URL(a.href, location.href);
        if (u.origin !== origin || !u.pathname.startsWith(basePath + '/')) continue;
        if (/\.(?:jpg|jpeg|png|webp|gif|svg|pdf|zip|css|js|json|xml|ico)$/i.test(u.pathname)) continue;
        u.hash = '';
        out.add(u.toString());
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
  await page.goto(target('/'), { waitUntil: 'networkidle', timeout: 45000 });
  const homeForms = page.locator('form');
  expect(await homeForms.count(), 'Homepage bevat geen testbaar formulier').toBeGreaterThan(0);
  for (let i = 0; i < await homeForms.count(); i++) {
    const form = homeForms.nth(i);
    const invalid = await form.evaluate(el => typeof el.checkValidity === 'function' ? !el.checkValidity() : false);
    // Lege verplichte formulieren horen niet spontaan als geldig te gelden.
    const requiredCount = await form.locator('[required]').count();
    if (requiredCount > 0) expect(invalid).toBe(true);
  }

  await page.goto(target('/aanmelden.html'), { waitUntil: 'networkidle', timeout: 45000 });
  const form = page.locator('form').first();
  expect(await form.count(), 'Aanmeldpagina bevat geen formulier').toBe(1);
  expect(await form.locator('[required]').count(), 'Aanmeldformulier heeft geen verplichte velden').toBeGreaterThan(0);
  const method = (await form.getAttribute('method') || 'get').toLowerCase();
  expect(method, 'Aanmeldformulier moet mutaties via POST doen').toBe('post');
});
