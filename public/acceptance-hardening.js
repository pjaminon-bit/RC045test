// Eindacceptatie-hardening voor generieke browsersemantiek.
(function () {
  function init() {
    // De landcode hoort semantisch bij het telefoonveld, maar is een eigen
    // select. Geef hem daarom een zelfstandige toegankelijke naam.
    document.querySelectorAll('select#landcode').forEach(function (select) {
      if (!select.getAttribute('aria-label') && !select.getAttribute('aria-labelledby')) {
        select.setAttribute('aria-label', 'Landcode');
      }
    });

    // Het publieke aanmeldformulier heeft al eigen foutmeldingen en JS-
    // validatie. De required-attributen maken dezelfde verplichtingen ook
    // zichtbaar voor browsers, assistieve technologie en form-validatie-API's.
    var form = document.getElementById('aanmeld-form');
    if (form) {
      ['voornaam','achternaam','geboortedatum','straat','huisnummer','postcode','stad','land','akkoord-reglement','akkoord-betaling']
        .forEach(function (id) {
          var veld = document.getElementById(id);
          if (veld) veld.required = true;
        });
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
