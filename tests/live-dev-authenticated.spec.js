const { test, expect } = require('@playwright/test');
const BASE = process.env.PLAYWRIGHT_TEST_BASE_URL || 'https://test.vps.holox.nl';
const BASE_HOST = new URL(BASE).hostname;
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
