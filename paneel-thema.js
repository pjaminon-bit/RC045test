// Themakeuze (systeem/licht/donker), zelfde opzet als site-i18n.js op
// de publieke pagina's, maar hier zelfstandig omdat beheer.php dat
// bestand niet laadt. Wordt zo vroeg mogelijk uitgevoerd (nog voor de
// rest van de pagina rendert) zodat er geen lichte flits te zien is
// als de bezoeker al voor donker had gekozen.
(function () {
  var SLEUTEL = 'rc045_thema';
  var STANDEN = ['systeem', 'licht', 'donker'];
  var ICONEN = { systeem: '\ud83c\udf13', licht: '\u2600\ufe0f', donker: '\ud83c\udf19' };
  var TEKST = { systeem: 'Thema: systeem', licht: 'Thema: licht', donker: 'Thema: donker' };
  var donkerQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function gekozenStand() {
    var opgeslagen = null;
    try { opgeslagen = sessionStorage.getItem(SLEUTEL); } catch (e) { /* privemodus */ }
    return STANDEN.indexOf(opgeslagen) === -1 ? 'systeem' : opgeslagen;
  }

  function werkKnopBij(stand) {
    var knop = document.getElementById('thema-switch');
    if (!knop) return;
    knop.textContent = ICONEN[stand];
    knop.setAttribute('aria-label', TEKST[stand]);
    knop.setAttribute('title', TEKST[stand]);
  }

  function toepassen() {
    var stand = gekozenStand();
    var donker = stand === 'donker' || (stand === 'systeem' && donkerQuery && donkerQuery.matches);
    document.documentElement.setAttribute('data-thema', donker ? 'donker' : 'licht');
    werkKnopBij(stand);
  }

  function volgende() {
    var nu = STANDEN.indexOf(gekozenStand());
    var nieuw = STANDEN[(nu + 1) % STANDEN.length];
    try { sessionStorage.setItem(SLEUTEL, nieuw); } catch (e) { /* privemodus */ }
    toepassen();
  }

  toepassen();

  if (donkerQuery) {
    if (donkerQuery.addEventListener) donkerQuery.addEventListener('change', toepassen);
    else if (donkerQuery.addListener) donkerQuery.addListener(toepassen);
  }

  function init() {
    var knop = document.getElementById('thema-switch');
    if (knop) knop.addEventListener('click', volgende);
    werkKnopBij(gekozenStand());
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
