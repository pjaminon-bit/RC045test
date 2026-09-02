const { test, expect } = require('@playwright/test');
const BASE = process.env.PLAYWRIGHT_TEST_BASE_URL || 'https://test.vps.holox.nl';
const BASE_URL = new URL(BASE.endsWith('/') ? BASE : BASE + '/');
const BASE_HOST = BASE_URL.hostname;
const ADMIN = process.env.E2E_ADMIN_USER;
const MEMBER = process.env.E2E_MEMBER_USER;
const PASSWORD = process.env.E2E_PASSWORD;

function url(path){ return BASE.replace(/\/$/,'') + path; }
function cookieHoortBijHost(cookie){
  const domain = String(cookie.domain || '').replace(/^\./, '');
  return domain !== '' && (BASE_HOST === domain || BASE_HOST.endsWith('.' + domain));
}
async function login(page, path, user){
  const start = await page.goto(url(path), {waitUntil:'domcontentloaded', timeout:45000});
  expect(start, `Geen HTTP-response voor loginpagina ${path}`).toBeTruthy();
  expect(start.status(), `Loginpagina ${path} gaf HTTP ${start.status()}`).toBeLessThan(400);
  await page.locator('#login-gebruikersnaam').fill(user);
  await page.locator('#login-wachtwoord').fill(PASSWORD);
  const [response] = await Promise.all([
    page.waitForNavigation({waitUntil:'domcontentloaded', timeout:45000}),
    page.getByRole('button',{name:'Inloggen'}).click(),
  ]);
  expect(response, `Geen navigatieresponse na login op ${path}`).toBeTruthy();
  expect(response.status(), `Authenticated pagina ${path} gaf HTTP ${response.status()} na login`).toBeLessThan(400);
  await expect(page.locator('#login-wachtwoord')).toHaveCount(0);
  return response;
}
async function screenshot(page, testInfo, name){
  await page.screenshot({path:testInfo.outputPath(name), fullPage:true});
}

async function openAanmeldingenAlsAdmin(page, status='alles') {
  await page.goto(url(`/beheer/aanmeldingen.php?status=${encodeURIComponent(status)}`), {waitUntil:'domcontentloaded', timeout:45000});
  if (await page.locator('#login-wachtwoord').count()) {
    await login(page, '/beheer/', ADMIN);
    await page.goto(url(`/beheer/aanmeldingen.php?status=${encodeURIComponent(status)}`), {waitUntil:'domcontentloaded', timeout:45000});
  }
  await expect(page.getByRole('heading',{name:'Aanmeldingen'})).toBeVisible();
}

async function cleanupAanmelding(page, email) {
  try {
    await openAanmeldingenAlsAdmin(page, 'alles');
    let kaart = page.locator('.kaart').filter({hasText:email});
    if (await kaart.count() === 0) return;

    const afwijzen = kaart.locator('form:has(input[name="actie"][value="afwijzen"])');
    if (await afwijzen.count()) {
      await Promise.all([
        page.waitForNavigation({waitUntil:'domcontentloaded', timeout:45000}),
        afwijzen.getByRole('button',{name:/Afwijzen/i}).click(),
      ]);
    }

    await openAanmeldingenAlsAdmin(page, 'afgewezen');
    kaart = page.locator('.kaart').filter({hasText:email});
    if (await kaart.count() === 0) return;
    const verwijderen = kaart.locator('form:has(input[name="actie"][value="verwijderen"])');
    if (await verwijderen.count()) {
      page.once('dialog', dialog => dialog.accept());
      await Promise.all([
        page.waitForNavigation({waitUntil:'domcontentloaded', timeout:45000}),
        verwijderen.getByRole('button',{name:/Inboxrecord verwijderen/i}).click(),
      ]);
    }
  } catch (e) {
    console.error(`E2E cleanup aanmelding ${email} faalde: ${String(e)}`);
  }
}

test('aanmeldformulier slaat exact één lokaal inboxrecord op en lekt geen PII naar Formspree', async ({page}) => {
  test.setTimeout(120000);
  const token = `${Date.now()}-${Math.random().toString(16).slice(2,10)}`;
  const email = `e2e-aanmelding-${token}@example.test`;
  const achternaam = `Aanmelding-${token}`;
  const formspreePosts = [];
  const intakeResponses = [];

  page.on('request', req => {
    if (req.method() !== 'POST') return;
    try {
      const u = new URL(req.url());
      if (u.hostname === 'formspree.io' || u.hostname.endsWith('.formspree.io')) formspreePosts.push(req.url());
    } catch (_) {}
  });
  page.on('response', res => {
    if (res.request().method() !== 'POST') return;
    try {
      const u = new URL(res.url());
      if (u.origin === BASE_URL.origin && u.pathname.endsWith('/aanmelden-ontvangst.php')) intakeResponses.push(res);
    } catch (_) {}
  });

  try {
    const start = await page.goto(url('/aanmelden.html'), {waitUntil:'domcontentloaded', timeout:45000});
    expect(start.status()).toBeLessThan(400);
    const form = page.locator('#aanmeld-form');
    await expect(form).toHaveCount(1);
    const action = await form.getAttribute('action');
    expect(action).toBeTruthy();
    expect(new URL(action, page.url()).origin).toBe(BASE_URL.origin);
    expect(new URL(action, page.url()).pathname).toMatch(/\/aanmelden-ontvangst\.php$/);
    expect((await page.content()).toLowerCase()).not.toContain('formspree.io');

    await page.locator('#voornaam').fill('E2E');
    await page.locator('#achternaam').fill(achternaam);
    await page.locator('#geboortedatum').fill('1990-01-01');
    await page.locator('#straat').fill('Teststraat');
    await page.locator('#huisnummer').fill('159');
    await page.locator('#postcode').fill('1234AB');
    await page.locator('#stad').fill('Teststad');
    await page.locator('#email').fill(email);
    await page.locator('#akkoord-reglement').check();
    await page.locator('#akkoord-betaling').check();

    const intake = page.waitForResponse(res => {
      if (res.request().method() !== 'POST') return false;
      try {
        const u = new URL(res.url());
        return u.origin === BASE_URL.origin && u.pathname.endsWith('/aanmelden-ontvangst.php');
      } catch (_) { return false; }
    }, {timeout:45000});
    await page.locator('#submit-btn').click();
    const intakeResponse = await intake;
    expect(intakeResponse.status()).toBe(200);
    await expect(page.locator('#bedankt-modal')).toHaveClass(/open/);
    await page.waitForTimeout(400);
    expect(formspreePosts, 'Aanmeldformulier stuurde PII naar Formspree').toEqual([]);
    expect(intakeResponses.length, 'Een submit moet exact één lokale intake-POST doen').toBe(1);

    await openAanmeldingenAlsAdmin(page, 'open');
    let kaart = page.locator('.kaart').filter({hasText:email});
    await expect(kaart, 'Lokale intake ontbreekt in beheerinbox').toHaveCount(1);
    await expect(kaart).toContainText(achternaam);

    const afwijzen = kaart.locator('form:has(input[name="actie"][value="afwijzen"])');
    await expect(afwijzen).toHaveCount(1);
    await Promise.all([
      page.waitForNavigation({waitUntil:'domcontentloaded', timeout:45000}),
      afwijzen.getByRole('button',{name:/Afwijzen/i}).click(),
    ]);

    await openAanmeldingenAlsAdmin(page, 'afgewezen');
    kaart = page.locator('.kaart').filter({hasText:email});
    await expect(kaart).toHaveCount(1);
    const verwijderen = kaart.locator('form:has(input[name="actie"][value="verwijderen"])');
    await expect(verwijderen).toHaveCount(1);
    page.once('dialog', dialog => dialog.accept());
    await Promise.all([
      page.waitForNavigation({waitUntil:'domcontentloaded', timeout:45000}),
      verwijderen.getByRole('button',{name:/Inboxrecord verwijderen/i}).click(),
    ]);

    await openAanmeldingenAlsAdmin(page, 'alles');
    await expect(page.locator('.kaart').filter({hasText:email}), 'E2E inboxrecord is niet opgeruimd').toHaveCount(0);
  } finally {
    await cleanupAanmelding(page, email);
  }
});

for (const viewport of [
  {name:'desktop', width:1440, height:1000},
  {name:'tablet', width:820, height:1180},
  {name:'mobile', width:390, height:844},
]) {
  test(`${viewport.name}: beheer login, autorisatie en logout`, async ({page, context}, testInfo) => {
    await page.setViewportSize({width:viewport.width,height:viewport.height});
    await login(page, '/beheer/', ADMIN);
    await expect(page.getByRole('button',{name:/uitloggen/i})).toBeVisible();
    const cookies = await context.cookies();
    const sessie = cookies.find(c => cookieHoortBijHost(c) && c.httpOnly);
    expect(sessie, 'Geen HttpOnly sessiecookie na beheerlogin').toBeTruthy();
    expect(sessie.secure).toBe(true);
    expect(String(sessie.sameSite).toLowerCase()).toBe('lax');
    let r = await page.goto(url('/beheer/leden.php'), {waitUntil:'domcontentloaded'});
    expect(r.status()).toBeLessThan(400);
    await screenshot(page, testInfo, `beheer-leden-${viewport.name}.png`);
    r = await page.goto(url('/beheer/gebruikers.php'), {waitUntil:'domcontentloaded'});
    expect(r.status()).toBeLessThan(400);
    await expect(page.getByRole('heading',{name:/Gebruikers/i})).toBeVisible();
    await screenshot(page, testInfo, `beheer-gebruikers-${viewport.name}.png`);
    await page.goto(url('/beheer/'), {waitUntil:'domcontentloaded'});
    await expect(page.getByRole('button',{name:/uitloggen/i})).toBeVisible();
    await page.getByRole('button',{name:/uitloggen/i}).click();
    await expect(page.locator('#login-wachtwoord')).toBeVisible();
  });

  test(`${viewport.name}: gekoppeld testlid ziet eigen portaldata maar geen gebruikersbeheer`, async ({page}, testInfo) => {
    await page.setViewportSize({width:viewport.width,height:viewport.height});
    await login(page, '/leden/', MEMBER);
    await expect(page.getByText('Account nog niet gekoppeld'), 'E2E-memberaccount is niet aan het fixturelid gekoppeld').toHaveCount(0);
    await expect(page.getByRole('heading',{name:/Welkom, E2E/i})).toBeVisible();
    await expect(page.getByText('E2E Testlid')).toBeVisible();
    await expect(page.getByText('E2E Testcommissie')).toBeVisible();
    await expect(page.getByText('Deels betaald')).toBeVisible();
    await expect(page.getByText('E2E agendapunt')).toBeVisible();
    await expect(page.getByText('E2E definitieve notulen')).toBeVisible();
    await expect(page.getByText('E2E taak voor testlid')).toBeVisible();
    await screenshot(page, testInfo, `ledenportaal-${viewport.name}.png`);
    const denied = await page.goto(url('/beheer/gebruikers.php'), {waitUntil:'domcontentloaded'});
    expect(denied.status()).toBe(403);
    await page.goto(url('/leden/'), {waitUntil:'domcontentloaded'});
    await page.getByRole('button',{name:/uitloggen/i}).click();
    await expect(page.locator('#login-wachtwoord')).toBeVisible();
  });
}
