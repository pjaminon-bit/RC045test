const { test, expect } = require('@playwright/test');
const BASE = process.env.PLAYWRIGHT_TEST_BASE_URL || 'https://rc045.nl/dev';
const ADMIN = process.env.E2E_ADMIN_USER;
const MEMBER = process.env.E2E_MEMBER_USER;
const PASSWORD = process.env.E2E_PASSWORD;

function url(path){ return BASE.replace(/\/$/,'') + path; }
async function login(page, path, user){
  await page.goto(url(path), {waitUntil:'domcontentloaded', timeout:45000});
  await page.locator('#login-gebruikersnaam').fill(user);
  await page.locator('#login-wachtwoord').fill(PASSWORD);
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.getByRole('button',{name:'Inloggen'}).click(),
  ]);
  await expect(page.locator('#login-wachtwoord')).toHaveCount(0);
}

for (const viewport of [
  {name:'desktop', width:1440, height:1000},
  {name:'mobile', width:390, height:844},
]) {
  test(`${viewport.name}: beheer login, autorisatie en logout`, async ({page, context}) => {
    await page.setViewportSize({width:viewport.width,height:viewport.height});
    await login(page, '/beheer/', ADMIN);
    await expect(page.getByRole('button',{name:/uitloggen/i})).toBeVisible();
    const cookies = await context.cookies();
    const sessie = cookies.find(c => c.domain.includes('rc045.nl') && c.httpOnly);
    expect(sessie, 'Geen HttpOnly sessiecookie na beheerlogin').toBeTruthy();
    expect(sessie.secure).toBe(true);
    expect(String(sessie.sameSite).toLowerCase()).toBe('lax');
    let r = await page.goto(url('/beheer/leden.php'), {waitUntil:'domcontentloaded'});
    expect(r.status()).toBeLessThan(400);
    r = await page.goto(url('/beheer/gebruikers.php'), {waitUntil:'domcontentloaded'});
    expect(r.status()).toBeLessThan(400);
    await expect(page.getByRole('heading',{name:/Gebruikers/i})).toBeVisible();
    await page.goto(url('/beheer/'), {waitUntil:'domcontentloaded'});
    await expect(page.getByRole('button',{name:/uitloggen/i})).toBeVisible();
    await page.getByRole('button',{name:/uitloggen/i}).click();
    await expect(page.locator('#login-wachtwoord')).toBeVisible();
  });

  test(`${viewport.name}: beperkt account kan ledenportaal in maar geen gebruikersbeheer`, async ({page}) => {
    await page.setViewportSize({width:viewport.width,height:viewport.height});
    await login(page, '/leden/', MEMBER);
    await expect(page.getByRole('heading',{name:/Welkom/i})).toBeVisible();
    await expect(page.getByText('Account nog niet gekoppeld')).toBeVisible();
    const denied = await page.goto(url('/beheer/gebruikers.php'), {waitUntil:'domcontentloaded'});
    expect(denied.status()).toBe(403);
    await page.goto(url('/leden/'), {waitUntil:'domcontentloaded'});
    await page.getByRole('button',{name:/uitloggen/i}).click();
    await expect(page.locator('#login-wachtwoord')).toBeVisible();
  });
}
