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
  const consoleErrors=[]; const pageErrors=[]; const failedSameOrigin=[]; const badResponses=[];
  page.on('console', msg => { if (msg.type()==='error') consoleErrors.push(msg.text()); });
  page.on('pageerror', err => pageErrors.push(String(err)));
  page.on('requestfailed', req => { try { const u=new URL(req.url()); if(u.origin===baseUrl.origin&&u.pathname.startsWith(basePath+'/')) failedSameOrigin.push(`${req.method()} ${req.url()} :: ${req.failure()?.errorText||'failed'}`); } catch(_){} });
  page.on('response', res => { try { const u=new URL(res.url()); if(res.status()>=400&&u.origin===baseUrl.origin&&u.pathname.startsWith(basePath+'/')) badResponses.push(`${res.status()} ${res.request().method()} ${res.url()}`); } catch(_){} });
  const response=await page.goto(target(route),{waitUntil:'domcontentloaded',timeout:45000});
  expect(response,`Geen hoofdresponse voor ${route}`).not.toBeNull(); expect(response.status(),`${route} gaf HTTP ${response.status()}`).toBeLessThan(400);
  await page.waitForTimeout(1000);
  const diagnostics=await page.evaluate(({origin,basePath})=>{
    const visible=el=>{const s=getComputedStyle(el),r=el.getBoundingClientRect();return s.display!=='none'&&s.visibility!=='hidden'&&Number(s.opacity||'1')>0&&r.width>0&&r.height>0;};
    const sameOriginBroken=[...document.images].filter(img=>visible(img)&&img.complete&&img.naturalWidth===0).map(img=>img.currentSrc||img.src||'').filter(src=>{try{const u=new URL(src,location.href);return u.origin===origin&&u.pathname.startsWith(basePath+'/');}catch(_){return false;}});
    const unlabeled=[...document.querySelectorAll('input,select,textarea')].filter(el=>visible(el)&&el.type!=='hidden'&&el.type!=='submit'&&el.type!=='button').filter(el=>!(el.getAttribute('aria-label')||el.getAttribute('aria-labelledby')||el.title||(el.id&&document.querySelector(`label[for="${CSS.escape(el.id)}"]`))||el.closest('label'))).map(el=>`${el.tagName.toLowerCase()}#${el.id||''}`);
    const tiny=[...document.querySelectorAll('button,input[type="submit"],input[type="button"],a')].filter(visible).filter(el=>{if(el.tagName==='A'&&getComputedStyle(el).display==='inline')return false;const r=el.getBoundingClientRect();return r.width<24||r.height<24;}).map(el=>{const r=el.getBoundingClientRect();return `${el.tagName.toLowerCase()}#${el.id||''}.${String(el.className||'').split(/\s+/).slice(0,3).join('.')} ${Math.round(r.width)}x${Math.round(r.height)}`;}).slice(0,30);
    const main=document.querySelector('main')||document.body;
    return {title:document.title,lang:document.documentElement.lang,h1Count:document.querySelectorAll('h1').length,mainTextLength:(main.innerText||'').trim().length,scrollWidth:document.documentElement.scrollWidth,clientWidth:document.documentElement.clientWidth,brokenImages:sameOriginBroken,unlabeledInputs:unlabeled,tinyControls:tiny};
  },{origin:baseUrl.origin,basePath});
  await page.screenshot({path:`${outputDir}/${viewportName}-${slug(route)}.png`,fullPage:true});
  console.log(`DIAGNOSTICS ${viewportName} ${route}: ${JSON.stringify({diagnostics,badResponses,consoleErrors,pageErrors,failedSameOrigin})}`);
  expect(diagnostics.title.trim()).not.toBe(''); expect(diagnostics.lang).toMatch(/^[a-z]{2}(?:-|$)/i); expect(diagnostics.h1Count).toBe(1); expect(diagnostics.mainTextLength).toBeGreaterThan(40);
  expect(diagnostics.scrollWidth,`${route} heeft horizontale overflow bij ${viewportName}`).toBeLessThanOrEqual(diagnostics.clientWidth+3);
  expect(diagnostics.brokenImages,`${route} heeft kapotte same-origin afbeeldingen`).toEqual([]); expect(diagnostics.unlabeledInputs,`${route} heeft ongelabelde formuliervelden`).toEqual([]); expect(diagnostics.tinyControls,`${route} bevat extreem kleine niet-inline controls`).toEqual([]);
  expect(pageErrors,`${route} veroorzaakte JavaScript page errors`).toEqual([]); expect(failedSameOrigin,`${route} heeft mislukte same-origin requests`).toEqual([]); expect(badResponses,`${route} heeft same-origin HTTP-fouten`).toEqual([]); expect(consoleErrors,`${route} schreef console.error`).toEqual([]);
}

for(const viewport of viewports){test.describe(`${viewport.name} optische acceptatie`,()=>{test.use({viewport:{width:viewport.width,height:viewport.height}});for(const route of entryRoutes)test(`${route} rendert foutvrij`,async({page})=>inspectPage(page,route,viewport.name));});}

test('crawl alle zichtbare interne publiekslinks vanaf home',async({page})=>{test.setTimeout(120000);await page.setViewportSize({width:1440,height:1000});const res=await page.goto(target('/'),{waitUntil:'domcontentloaded',timeout:45000});expect(res.status()).toBeLessThan(400);await page.waitForTimeout(500);const links=await page.evaluate(({origin,basePath})=>{const out=new Set();for(const a of document.querySelectorAll('a[href]')){try{const u=new URL(a.href,location.href);if(u.origin!==origin||!u.pathname.startsWith(basePath+'/'))continue;if(/\.(?:jpg|jpeg|png|webp|gif|svg|pdf|zip|css|js|json|xml|ico)$/i.test(u.pathname))continue;u.hash='';out.add(u.toString());}catch(_){}}return[...out].slice(0,60);},{origin:baseUrl.origin,basePath});expect(links.length).toBeGreaterThan(3);for(const url of links){const r=await page.goto(url,{waitUntil:'domcontentloaded',timeout:30000});expect(r,`Geen response voor ${url}`).not.toBeNull();expect(r.status(),`${url} gaf HTTP ${r.status()}`).toBeLessThan(400);expect(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth+3),`${url} heeft horizontale overflow`).toBe(false);}});

test('publieke formulieren hebben werkende browservalidatie zonder mutatie',async({page})=>{await page.goto(target('/aanmelden.html'),{waitUntil:'domcontentloaded',timeout:45000});await page.waitForTimeout(500);const form=page.locator('#aanmeld-form');expect(await form.count()).toBe(1);expect(await form.locator('[required]').count(),'Aanmeldformulier heeft geen verplichte velden').toBeGreaterThan(0);expect(await form.evaluate(el=>!el.checkValidity()),'Leeg aanmeldformulier moet ongeldig zijn').toBe(true);expect((await form.getAttribute('method')||'get').toLowerCase()).toBe('post');});
