'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function storage() {
  const values = new Map();
  return {
    getItem(key) { return values.has(String(key)) ? values.get(String(key)) : null; },
    setItem(key, value) { values.set(String(key), String(value)); },
    removeItem(key) { values.delete(String(key)); },
    clear() { values.clear(); },
  };
}

const source = fs.readFileSync(path.join(__dirname, '..', 'site-i18n.js'), 'utf8');
const marker = '\nfunction updateLiveDocumentTitle';
const markerAt = source.indexOf(marker);
assert(markerAt > 0, 'Kon gedeelde taalhelpers niet isoleren uit site-i18n.js');

const localStorage = storage();
const context = {
  window: { location: { search: '' }, verenigingSiteContext: null },
  localStorage,
  URLSearchParams,
  console,
};
vm.createContext(context);
vm.runInContext(source.slice(0, markerAt), context, { filename: 'site-i18n.js' });

const translations = { nl: {}, en: {}, de: {} };

// Default RC045 behoudt de bestaande sleutel exact.
assert(context.getLanguageStorageKey() === 'rc045_lang', 'Default RC045 moet rc045_lang blijven gebruiken');
assert(context.setStoredLanguage('en') === true, 'Default taal schrijven moet slagen');
assert(localStorage.getItem('rc045_lang') === 'en', 'Default taal moet onder rc045_lang worden opgeslagen');
assert(context.getStoredLanguage() === 'en', 'Default lezen en schrijven moeten dezelfde sleutel gebruiken');
assert(context.getInitialLang(translations) === 'en', 'Default opgeslagen taal moet worden hersteld');

// Externe tenant gebruikt uitsluitend de stabiele tenantKey; displaynaam doet niet mee.
localStorage.clear();
localStorage.setItem('rc045_lang', 'de');
context.window.verenigingSiteContext = { external: true, tenantKey: 'test-club', name: 'Test Club' };
assert(context.getLanguageStorageKey() === 'test-club_lang', 'Externe tenant moet tenantKey_lang gebruiken');
assert(context.getStoredLanguage() === null, 'Externe tenant mag rc045_lang niet als migratiebron overnemen');
assert(context.getInitialLang(translations) === 'nl', 'Externe tenant zonder eigen voorkeur moet op standaardtaal starten');
assert(context.setStoredLanguage('en') === true, 'Externe tenanttaal schrijven moet slagen');
assert(localStorage.getItem('test-club_lang') === 'en', 'Externe tenanttaal moet onder de tenantKey worden opgeslagen');
assert(localStorage.getItem('rc045_lang') === 'de', 'Externe tenant mag legacy/default rc045_lang niet muteren');
assert(localStorage.getItem('Test Club_lang') === null, 'Displaynaam mag nooit een storagekey vormen');
assert(context.getStoredLanguage() === 'en', 'Externe tenant moet exact dezelfde sleutel lezen als schrijven');

// Een gewijzigde displaynaam verandert de technische sleutel niet.
context.window.verenigingSiteContext.name = 'Nieuwe Clubnaam';
assert(context.getLanguageStorageKey() === 'test-club_lang', 'Displaynaam mag technische sleutel niet beïnvloeden');

// Tenant A en B houden aantoonbaar onafhankelijke taalstate.
context.window.verenigingSiteContext = { external: true, tenantKey: 'tenant-a', name: 'Tenant A' };
context.setStoredLanguage('en');
context.window.verenigingSiteContext = { external: true, tenantKey: 'tenant-b', name: 'Tenant B' };
context.setStoredLanguage('de');
assert(localStorage.getItem('tenant-a_lang') === 'en', 'Tenant A voorkeur ontbreekt');
assert(localStorage.getItem('tenant-b_lang') === 'de', 'Tenant B voorkeur ontbreekt');
assert(context.getStoredLanguage() === 'de', 'Tenant B moet eigen voorkeur lezen');
context.window.verenigingSiteContext = { external: true, tenantKey: 'tenant-a', name: 'Tenant A hernoemd' };
assert(context.getStoredLanguage() === 'en', 'Tenant A moet na tenantwissel eigen voorkeur lezen');

// Fail closed wanneer een externe context geen stabiele tenantKey heeft.
localStorage.setItem('rc045_lang', 'de');
context.window.verenigingSiteContext = { external: true, tenantKey: '   ', name: 'Zonder sleutel' };
assert(context.getLanguageStorageKey() === null, 'Externe context zonder tenantKey moet geen fallback-key krijgen');
assert(context.getStoredLanguage() === null, 'Externe context zonder tenantKey mag shared state niet lezen');
assert(context.setStoredLanguage('en') === false, 'Externe context zonder tenantKey mag shared state niet schrijven');
assert(localStorage.getItem('rc045_lang') === 'de', 'Fail-closed pad mag rc045_lang niet wijzigen');

// Expliciete URL-keuze blijft boven opgeslagen state gaan.
context.window.verenigingSiteContext = { external: true, tenantKey: 'test-club', name: 'Test Club' };
localStorage.setItem('test-club_lang', 'de');
context.window.location.search = '?lang=en';
assert(context.getInitialLang(translations) === 'en', 'URL-taal moet opgeslagen voorkeur overrulen');

console.log('OK: #223 tenant language storage contract');
