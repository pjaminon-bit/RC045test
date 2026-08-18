(function() {
      // ===== Datumvelden: meteen herschrijven naar dd-mm-jjjj =====
      // Al deze velden hebben hetzelfde format-attribuut (placeholder
      // dd-mm-jjjj), ook de velden die pas later worden toegevoegd (extra
      // agenda-, nieuws-, media- of contributieregel). Daarom wordt hier
      // niet één keer bij het laden gezocht, maar geluisterd op het
      // document zelf, met capture aan want blur borrelt niet omhoog.
      // Dit is puur de weergave; wat er echt wordt opgeslagen bepaalt nog
      // steeds datumNaarIso()/ledenParseDatum() in de PHP, dus een format
      // dat hier niet wordt herkend blijft gewoon staan zoals ingetypt en
      // krijgt bij het opslaan de normale foutmelding.
      function datumHerkennen(tekst) {
        tekst = (tekst || '').trim();
        var m;
        if ((m = tekst.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/))) {
          return datumValideren(parseInt(m[3], 10), parseInt(m[2], 10), parseInt(m[1], 10));
        }
        if ((m = tekst.match(/^(\d{2})(\d{2})(\d{4})$/))) {
          return datumValideren(parseInt(m[1], 10), parseInt(m[2], 10), parseInt(m[3], 10));
        }
        if ((m = tekst.match(/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})$/))) {
          var jaar = parseInt(m[3], 10);
          if (m[3].length < 4) jaar += (jaar > 50 ? 1900 : 2000);
          return datumValideren(parseInt(m[1], 10), parseInt(m[2], 10), jaar);
        }
        return null;
      }

      function datumValideren(dag, maand, jaar) {
        if (!dag || !maand || !jaar) return null;
        var d = new Date(jaar, maand - 1, dag);
        if (d.getFullYear() !== jaar || d.getMonth() !== maand - 1 || d.getDate() !== dag) return null;
        return [dag, maand, jaar];
      }

      function tweeCijfers(n) { return (n < 10 ? '0' : '') + n; }

      document.addEventListener('blur', function(e) {
        var el = e.target;
        if (!el || el.tagName !== 'INPUT' || el.getAttribute('placeholder') !== 'dd-mm-jjjj') return;
        var d = datumHerkennen(el.value);
        if (d) el.value = tweeCijfers(d[0]) + '-' + tweeCijfers(d[1]) + '-' + d[2];
      }, true);

      // ===== Tijdveld vergadering: zelfde herschrijving, nu ook live =====
      // vergaderingParseTijd() in vergaderingen-opslag.php deed dit al bij
      // het opslaan (1930 werd 19:30), maar dan zag je het pas na het
      // opslaan terug. Nu gebeurt het ook meteen bij het verlaten van
      // het veld, met dezelfde regels.
      var vergTijdVeld = document.getElementById('verg-tijd');
      if (vergTijdVeld) {
        vergTijdVeld.addEventListener('blur', function() {
          var tekst = vergTijdVeld.value.trim();
          if (tekst === '') return;
          var m = tekst.match(/^(\d{1,2})[:.h ]?(\d{2})$/i);
          if (m) {
            var uur = parseInt(m[1], 10), minuut = parseInt(m[2], 10);
            if (uur <= 23 && minuut <= 59) {
              vergTijdVeld.value = tweeCijfers(uur) + ':' + tweeCijfers(minuut);
            }
            return;
          }
          m = tekst.match(/^(\d{1,2})$/);
          if (m) {
            var uurAlleen = parseInt(m[1], 10);
            if (uurAlleen <= 23) {
              vergTijdVeld.value = tweeCijfers(uurAlleen) + ':00';
            }
          }
        });
      }

      // De lijst met tabbladen komt uit de menuknoppen zelf. Stond hier
      // eerder als vaste lijst, met als gevolg dat een nieuw tabblad wel een
      // knop en een paneel had maar niet openging omdat het niet in de lijst
      // stond. Zo kunnen die twee niet meer uit elkaar lopen.
      var menuItems = document.querySelectorAll('.menu-item');
      var tabs = Array.prototype.map.call(menuItems, function (btn) {
        return btn.getAttribute('data-tab');
      });

      // ===== Hamburger (alleen zichtbaar op smalle schermen, zie CSS) =====
      var menuNav = document.getElementById('beheer-menu');
      var menuKnop = document.getElementById('beheer-menu-knop');
      var menuHuidigLabel = document.getElementById('beheer-menu-huidig');

      function sluitMobielMenu() {
        if (menuNav) menuNav.classList.remove('open');
        if (menuKnop) menuKnop.setAttribute('aria-expanded', 'false');
      }

      if (menuKnop && menuNav) {
        menuKnop.addEventListener('click', function() {
          var open = menuNav.classList.toggle('open');
          menuKnop.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        // Ergens anders op de pagina klikken terwijl het paneel open staat: dicht.
        document.addEventListener('click', function(e) {
          if (!menuNav.classList.contains('open')) return;
          if (menuNav.contains(e.target) || menuKnop.contains(e.target)) return;
          sluitMobielMenu();
        });
      }

      // ===== Menu-groepen in/uitklappen =====
      // Standaard staan alle groepen dicht (aria-expanded="false" + hidden
      // in de HTML) en dat blijft ook zo bij het laden van de pagina, ook al
      // is een tab uit die groep de actieve. Een groep gaat alleen open
      // doordat je er zelf op klikt.
      var menuGroepLabels = document.querySelectorAll('.menu-groep-label');
      function zetGroepOpen(label, open) {
        if (!label) return;
        label.setAttribute('aria-expanded', open ? 'true' : 'false');
        var items = document.getElementById('menu-groep-items-' + label.getAttribute('data-groep'));
        if (items) items.hidden = !open;
      }
      menuGroepLabels.forEach(function(label) {
        label.addEventListener('click', function() {
          var open = label.getAttribute('aria-expanded') === 'true';
          zetGroepOpen(label, !open);
        });
      });

      function toonTab(naam) {
        if (tabs.indexOf(naam) === -1) naam = tabs[0];
        tabs.forEach(function(t) {
          var paneel = document.getElementById('tab-' + t);
          if (paneel) paneel.style.display = (t === naam) ? 'flex' : 'none';
        });
        menuItems.forEach(function(btn) {
          var actief = btn.getAttribute('data-tab') === naam;
          btn.classList.toggle('actief', actief);
          if (actief && menuHuidigLabel) menuHuidigLabel.textContent = btn.textContent.trim();
        });
      }

      menuItems.forEach(function(btn) {
        btn.addEventListener('click', function() {
          var naam = btn.getAttribute('data-tab');
          history.replaceState(null, '', '#' + naam);
          toonTab(naam);
          sluitMobielMenu();
          btn.scrollIntoView({ block: 'nearest', inline: 'center' });
        });
      });

      toonTab((location.hash || '').replace('#', ''));
    })();

    // ===== Multiselect (dropdown met zoekvak en vinkjes) =====
    // Werkt voor elke ".multiselect" op de pagina onafhankelijk van elkaar,
    // dus ook als er (zoals bij Gebruikers) meerdere op dezelfde pagina
    // staan. De echte waarden zijn gewone checkboxes, dit is puur de schil
    // eromheen: knop met "X geselecteerd", paneel met zoekvak en alles/niets.
    (function () {
      var instanties = Array.prototype.slice.call(document.querySelectorAll('.multiselect'));
      if (instanties.length === 0) return;

      function label(instantie) {
        var vinkjes = Array.prototype.slice.call(instantie.querySelectorAll('.multiselect-optie input'));
        var totaal = vinkjes.length;
        var aan = vinkjes.filter(function (v) { return v.checked; }).length;
        var el = instantie.querySelector('.multiselect-label');
        if (!el) return;
        if (totaal === 0) el.textContent = 'Geen opties';
        else if (aan === totaal) el.textContent = 'Alles (' + totaal + ')';
        else if (aan === 0) el.textContent = 'Niets geselecteerd';
        else el.textContent = aan + ' van ' + totaal;
      }

      function sluiten(instantie) {
        var paneel = instantie.querySelector('.multiselect-paneel');
        var trigger = instantie.querySelector('.multiselect-trigger');
        if (paneel) paneel.hidden = true;
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
      }

      function alleSluiten(behalve) {
        instanties.forEach(function (i) { if (i !== behalve) sluiten(i); });
      }

      instanties.forEach(function (instantie) {
        var trigger = instantie.querySelector('.multiselect-trigger');
        var paneel = instantie.querySelector('.multiselect-paneel');
        var zoek = instantie.querySelector('.multiselect-zoek');
        var opties = Array.prototype.slice.call(instantie.querySelectorAll('.multiselect-optie'));
        if (!trigger || !paneel) return;

        label(instantie);

        trigger.addEventListener('click', function (e) {
          e.stopPropagation();
          var openen = paneel.hidden;
          alleSluiten(instantie);
          paneel.hidden = !openen;
          trigger.setAttribute('aria-expanded', openen ? 'true' : 'false');
          if (openen) {
            if (zoek) {
              zoek.value = '';
              opties.forEach(function (o) { o.classList.remove('verborgen'); });
              zoek.focus();
            }
          }
        });

        opties.forEach(function (optie) {
          var vinkje = optie.querySelector('input');
          if (vinkje) vinkje.addEventListener('change', function () { label(instantie); });
        });

        if (zoek) {
          zoek.addEventListener('input', function () {
            var term = zoek.value.trim().toLowerCase();
            opties.forEach(function (optie) {
              var tekst = optie.textContent.trim().toLowerCase();
              optie.classList.toggle('verborgen', term !== '' && tekst.indexOf(term) === -1);
            });
          });
          // Klikken/typen in het zoekvak mag het paneel niet laten sluiten
          // via de document-click-listener hieronder.
          zoek.addEventListener('click', function (e) { e.stopPropagation(); });
        }

        Array.prototype.slice.call(instantie.querySelectorAll('.multiselect-acties button')).forEach(function (knop) {
          knop.addEventListener('click', function (e) {
            e.stopPropagation();
            var aan = knop.getAttribute('data-actie') === 'alles';
            opties.forEach(function (optie) {
              if (optie.classList.contains('verborgen')) return; // alleen wat gefilterd zichtbaar is
              var vinkje = optie.querySelector('input');
              if (vinkje) vinkje.checked = aan;
            });
            label(instantie);
          });
        });
      });

      document.addEventListener('click', function (e) {
        instanties.forEach(function (instantie) {
          if (!instantie.contains(e.target)) sluiten(instantie);
        });
      });
    })();

    // ===== Fotoboek: foto's herordenen met de pijltjes =====
    // De volgorde waarin de blokken hier in de pagina staan bepaalt de
    // volgorde waarin ze worden opgeslagen (formuliervelden worden in
    // documentvolgorde verzonden), dus verplaatsen in de DOM is voldoende.
    function fotoboekVolgordeBijwerken(lijst) {
      var bloks = lijst.querySelectorAll('.fotoboek-foto-blok');
      bloks.forEach(function(blok, idx) {
        var knoppen = blok.querySelectorAll('.fotoboek-foto-volgorde button');
        knoppen[0].disabled = (idx === 0);
        knoppen[1].disabled = (idx === bloks.length - 1);
      });
    }
    function fotoboekVerplaats(knop, richting) {
      var blok = knop.closest('.fotoboek-foto-blok');
      var lijst = blok.parentNode;
      if (richting < 0 && blok.previousElementSibling) {
        lijst.insertBefore(blok, blok.previousElementSibling);
      } else if (richting > 0 && blok.nextElementSibling) {
        lijst.insertBefore(blok.nextElementSibling, blok);
      }
      fotoboekVolgordeBijwerken(lijst);
    }
    document.querySelectorAll('.fotoboek-foto-lijst').forEach(fotoboekVolgordeBijwerken);

    // ===== Fotoboek: HEIC omzetten en video-thumbnail maken vóór het uploaden =====
    // HEIC (iPhone-foto's) kan de server niet lezen: er is geen HEIC-decoder in
    // GD en zeker geen Imagick met libheif op gedeelde hosting. Daarom wordt
    // HEIC hier, in de browser, omgezet naar JPEG met heic2any (WASM, zelf
    // gehost in vendor/heic2any/, geen externe afhankelijkheid).
    // Voor mp4-video's is er geen ffmpeg op de server om automatisch een
    // thumbnail te trekken; die wordt hier gegrabt uit de video zelf via een
    // canvas. Lukt dat een keer niet (oude browser, vreemde codec), dan gaat
    // de video gewoon mee zonder voorbeeldbeeld en toont de website een
    // generiek video-icoon in plaats van vast te lopen.
    function fotoboekIsHeic(bestand) {
      var naam = (bestand.name || '').toLowerCase();
      return naam.endsWith('.heic') || naam.endsWith('.heif') || bestand.type === 'image/heic' || bestand.type === 'image/heif';
    }
    // Weerspiegelt $fotoboekVideoAan in PHP: staat video-upload tijdelijk uit,
    // dan doet de JS net alsof er nooit een video wordt geselecteerd (ook als
    // iemand het accept-filter omzeilt), zodat de trage/zware
    // thumbnail-generatie niet eens geprobeerd wordt. De server weigert het
    // bestand dan alsnog met een duidelijke melding.
    function fotoboekIsVideo(bestand) {
      if (!FOTOBOEK_VIDEO_AAN) return false;
      var naam = (bestand.name || '').toLowerCase();
      return naam.endsWith('.mp4') || bestand.type === 'video/mp4';
    }
    function fotoboekHeicScriptLaden() {
      if (window.heic2any) return Promise.resolve();
      if (window.__heic2anyLaden) return window.__heic2anyLaden;
      window.__heic2anyLaden = new Promise(function(resolve, reject) {
        var script = document.createElement('script');
        script.src = 'vendor/heic2any/heic2any.min.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
      });
      return window.__heic2anyLaden;
    }
    function fotoboekHeicNaarJpeg(bestand) {
      return window.heic2any({ blob: bestand, toType: 'image/jpeg', quality: 0.85 }).then(function(resultaat) {
        var blob = Array.isArray(resultaat) ? resultaat[0] : resultaat;
        var naam = bestand.name.replace(/\.(heic|heif)$/i, '.jpg');
        return new File([blob], naam, { type: 'image/jpeg' });
      });
    }
    function fotoboekVideoThumbnail(bestand) {
      return new Promise(function(resolve) {
        var klaar = false;
        var video = document.createElement('video');
        video.muted = true;
        video.playsInline = true;
        video.preload = 'auto';
        var url = URL.createObjectURL(bestand);
        video.src = url;

        var afronden = function(dataUrl) {
          if (klaar) return;
          klaar = true;
          URL.revokeObjectURL(url);
          resolve(dataUrl);
        };
        var timeout = setTimeout(function() { afronden(null); }, 8000);

        video.addEventListener('error', function() { clearTimeout(timeout); afronden(null); });
        video.addEventListener('loadeddata', function() {
          try {
            video.currentTime = Math.min(0.5, (video.duration || 1) / 2);
          } catch (e) { clearTimeout(timeout); afronden(null); }
        });
        video.addEventListener('seeked', function() {
          clearTimeout(timeout);
          try {
            var maxBreedte = 1200;
            var breedte = video.videoWidth || 320;
            var hoogte = video.videoHeight || 180;
            var schaal = breedte > maxBreedte ? maxBreedte / breedte : 1;
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(breedte * schaal);
            canvas.height = Math.round(hoogte * schaal);
            var ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            afronden(canvas.toDataURL('image/jpeg', 0.82));
          } catch (e) {
            afronden(null);
          }
        });
      });
    }
    // Elke foto wordt apart omgezet en apart geupload: eerst converteren
    // (HEIC/video-thumbnail), dan direct die ene foto versturen, dan pas de
    // volgende. Dat lijkt trager dan groepjes tegelijk doen, maar is juist
    // betrouwbaarder: (1) nooit meer dan één zware HEIC/video-omzetting
    // tegelijk, dus geen zware belasting op oudere telefoons/laptops, (2) elk
    // serververzoek is klein en snel klaar, dus geen risico dat Strato's
    // tijdslimiet voor een verzoek wordt overschreden bij een groep van
    // meerdere zware foto's, (3) gaat er één keer iets mis, dan raakt alleen
    // die ene foto kwijt in plaats van een hele groep. Zet dit op 1.
    var FOTOBOEK_BATCH_GROOTTE = 1;

    // Blijft "true" zolang een batch-upload loopt. Ververst of sluit iemand de
    // pagina in die tussentijd (bijv. uit ongeduld, omdat het een tijdje kan
    // duren), dan breekt dat de nog lopende batches keihard af - en omdat deze
    // pagina eerder soms het resultaat van een POST is, kan verversen ook nog
    // een oud formulier opnieuw verzenden. De waarschuwing hieronder voorkomt
    // dat iemand dat per ongeluk doet.
    var fotoboekUploadBezig = false;
    window.addEventListener('beforeunload', function(event) {
      if (!fotoboekUploadBezig) return;
      event.preventDefault();
      event.returnValue = '';
    });

    // Verzamelt alle overige formuliervelden (titel, datum, bestaande foto's,
    // csrf, enz.) zodat elke batch hetzelfde album bijwerkt. Het bulk-
    // watermerkvinkje wordt bewust apart gehouden: dat zou anders bij elke
    // batch opnieuw alle bestaande foto's doorlopen, onnodig traag bij een
    // groot album.
    //
    // "foto[...]"-velden (bijschriften/verwijderen/cover van de foto's die al
    // in het album staan) worden alleen meegestuurd als weglaten=false. Dat is
    // bewust: die velden weerspiegelen de stand van het album bij het LADEN
    // van de pagina, dus alleen bij het eerste verzoek van een batch-upload
    // klopt dat nog. Bij vervolgverzoeken zou het opnieuw meesturen ervan de
    // net (door eerdere verzoeken in dezelfde upload) toegevoegde foto's weer
    // ongedaan maken - de server herbouwt het foto-overzicht namelijk uit dit
    // veld zodra het aanwezig is. Zie ook de PHP-kant bij "Bestaande foto's".
    function fotoboekAndereVelden(form, weglaten) {
      var velden = [];
      Array.prototype.forEach.call(form.elements, function(el) {
        if (!el.name || el.disabled) return;
        if (el.name === 'nieuwe_fotos[]') return;
        if (el.name.indexOf('video_poster[') === 0) return;
        if (el.name === 'album_watermerk_alle') return;
        if (weglaten && el.name.indexOf('foto[') === 0) return;
        if (el.type === 'file') return;
        if (el.type === 'checkbox' || el.type === 'radio') {
          if (el.checked) velden.push([el.name, el.value]);
        } else {
          velden.push([el.name, el.value]);
        }
      });
      return velden;
    }

    // Maakt een kleine voortgangsbalk direct onder de upload-knop, zodat
    // bij een grote upload (tientallen tot honderden foto's) duidelijk
    // zichtbaar blijft dat het proces nog loopt en hoe ver het is - alleen
    // een veranderende knoptekst bleek onvoldoende: bij een trage batch
    // leek het net alsof de pagina vastzat.
    function fotoboekMaakVoortgangsbalk(knop) {
      var wrap = document.createElement('div');
      wrap.className = 'fotoboek-voortgang';
      wrap.innerHTML = '<div class="fotoboek-voortgang-balk"><div class="fotoboek-voortgang-vulling"></div></div><p class="fotoboek-voortgang-tekst"></p>';
      if (knop && knop.parentNode) knop.parentNode.insertBefore(wrap, knop.nextSibling);
      return wrap;
    }
    function fotoboekVoortgangBijwerken(voortgang, klaar, totaal, tekst) {
      if (!voortgang) return;
      var pct = totaal > 0 ? Math.round((klaar / totaal) * 100) : 0;
      var vulling = voortgang.querySelector('.fotoboek-voortgang-vulling');
      if (vulling) vulling.style.width = pct + '%';
      var label = voortgang.querySelector('.fotoboek-voortgang-tekst');
      if (label) label.textContent = tekst + ' (' + pct + '%)';
    }

    // Verwerkt (HEIC/video) en verstuurt één foto, en roept zichzelf pas
    // daarna aan voor de volgende. Zo draait er nooit meer dan één zware
    // conversie tegelijk en blijft elk serververzoek klein.
    function fotoboekVerwerkEnVerstuurBatch(form, knop, voortgang, andereVeldenEerste, andereVeldenVervolg, watermerkAlle, ruweBatches, index, totaalBestanden) {
      if (index >= ruweBatches.length) {
        fotoboekUploadBezig = false;
        if (knop) knop.textContent = 'Klaar, pagina wordt ververst...';
        fotoboekVoortgangBijwerken(voortgang, totaalBestanden, totaalBestanden, 'Klaar');
        window.location.reload();
        return Promise.resolve();
      }
      var volgnr = index + 1;
      // Alleen het allereerste verzoek stuurt de "foto[...]"-velden mee (zie
      // fotoboekAndereVelden hierboven en de PHP-kant): dat voorkomt dat
      // vervolgverzoeken de zojuist toegevoegde foto's weer wegschrijven.
      var andereVelden = index === 0 ? andereVeldenEerste : andereVeldenVervolg;
      if (knop) {
        knop.textContent = 'Bezig met verwerken (foto ' + volgnr + ' van ' + totaalBestanden + ')...';
      }
      fotoboekVoortgangBijwerken(voortgang, index, totaalBestanden, 'Foto ' + volgnr + ' van ' + totaalBestanden + ' - verwerken...');
      return Promise.all(ruweBatches[index].map(function(bestand) {
        if (fotoboekIsHeic(bestand) && window.heic2any) {
          return fotoboekHeicNaarJpeg(bestand).catch(function() { return bestand; }).then(function(b) {
            return { bestand: b, poster: null };
          });
        }
        if (fotoboekIsVideo(bestand)) {
          return fotoboekVideoThumbnail(bestand).then(function(dataUrl) {
            return { bestand: bestand, poster: dataUrl };
          });
        }
        return { bestand: bestand, poster: null };
      })).then(function(verwerkteBatch) {
        if (knop) knop.textContent = 'Bezig met uploaden (foto ' + volgnr + ' van ' + totaalBestanden + ')... niet verversen of sluiten';
        fotoboekVoortgangBijwerken(voortgang, index, totaalBestanden, 'Foto ' + volgnr + ' van ' + totaalBestanden + ' - uploaden...');
        var data = new FormData();
        andereVelden.forEach(function(paar) { data.append(paar[0], paar[1]); });
        if (watermerkAlle && index === ruweBatches.length - 1) data.append('album_watermerk_alle', '1');
        // Vertelt de server waar deze batch begint/eindigt, zodat foutmeldingen
        // van tussentijdse verzoeken verzameld en pas bij het laatste verzoek
        // in hun geheel getoond worden (zie PHP: $batchVerzoek).
        if (index === 0) data.append('batch_start', '1');
        if (index === ruweBatches.length - 1) data.append('batch_laatste', '1');
        verwerkteBatch.forEach(function(item, i) {
          data.append('nieuwe_fotos[]', item.bestand);
          if (item.poster) data.append('video_poster[' + i + ']', item.poster);
        });
        return fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin' });
      }).catch(function() {
        // Deze batch (verwerken of versturen) mislukte: gewoon doorgaan met de
        // volgende, beter dan de hele upload te laten vastlopen.
      }).then(function() {
        return fotoboekVerwerkEnVerstuurBatch(form, knop, voortgang, andereVeldenEerste, andereVeldenVervolg, watermerkAlle, ruweBatches, index + 1, totaalBestanden);
      });
    }

    document.querySelectorAll('.fotoboek-album-form').forEach(function(form) {
      form.addEventListener('submit', function(event) {
        var input = form.querySelector('input[name="nieuwe_fotos[]"]');
        if (!input || !input.files || input.files.length === 0) return;

        var heeftHeic = false, heeftVideo = false;
        for (var i = 0; i < input.files.length; i++) {
          if (fotoboekIsHeic(input.files[i])) heeftHeic = true;
          if (fotoboekIsVideo(input.files[i])) heeftVideo = true;
        }
        var vindtBatchenNodig = input.files.length > FOTOBOEK_BATCH_GROOTTE;
        if (!heeftHeic && !heeftVideo && !vindtBatchenNodig) return; // klein, gewoon foto's: gewoon versturen zoals altijd

        if (typeof DataTransfer === 'undefined' || typeof fetch === 'undefined') return; // oude browser: gewoon proberen te uploaden zoals het is

        event.preventDefault();
        var knop = form.querySelector('button[type="submit"]');
        if (knop) { knop.disabled = true; knop.dataset.oorspronkelijkeTekst = knop.textContent; knop.textContent = 'Bezig met verwerken...'; }
        var voortgang = fotoboekMaakVoortgangsbalk(knop);

        var watermerkAlle = !!form.querySelector('input[name="album_watermerk_alle"]:checked');
        var alleBestanden = Array.prototype.slice.call(input.files);
        var andereVeldenEerste = fotoboekAndereVelden(form, false);
        var andereVeldenVervolg = fotoboekAndereVelden(form, true);
        var ruweBatches = [];
        for (var start = 0; start < alleBestanden.length; start += FOTOBOEK_BATCH_GROOTTE) {
          ruweBatches.push(alleBestanden.slice(start, start + FOTOBOEK_BATCH_GROOTTE));
        }

        fotoboekUploadBezig = true;
        var laadPromise = heeftHeic ? fotoboekHeicScriptLaden().catch(function() {}) : Promise.resolve();
        laadPromise.then(function() {
          return fotoboekVerwerkEnVerstuurBatch(form, knop, voortgang, andereVeldenEerste, andereVeldenVervolg, watermerkAlle, ruweBatches, 0, alleBestanden.length);
        }).catch(function() {
          // Iets ging structureel mis (bijv. heic2any kon niet laden): toch
          // proberen te versturen met de originele bestanden, dat is beter
          // dan de gebruiker te laten vastlopen.
          fotoboekUploadBezig = false;
          form.submit();
        });
      });
    });

    // ===== Agenda: kaart direct dimmen/badge tonen zodra "afgelopen" wordt aangevinkt =====
    function agendaAfgelopenBijwerken(vinkje) {
      var blok = vinkje.closest('.item-blok');
      if (!blok) return;
      blok.classList.toggle('is-afgelopen', vinkje.checked);
      var badge = blok.querySelector('.afgelopen-badge');
      if (badge) badge.style.display = vinkje.checked ? '' : 'none';
    }

    // ===== FAQ / sponsors / media: extra leeg blok toevoegen zonder maximum =====
    // Deze drie secties vulden vroeger altijd aan tot precies 8 lege blokken;
    // een negende vraag, sponsor of media-item kon dan alleen door de "8" in
    // beheer.php zelf te verhogen (dus via GitHub). Nu toont de pagina steeds
    // de bestaande items plus 1 leeg blok aan het einde (zie faqData/
    // sponsorData/mediaData in PHP hierboven); deze knop kloont dat laatste,
    // lege blok zodat je er clientside nog meer bij kan zetten voordat je
    // opslaat. Werkt voor alle drie omdat de velden twee naamstijlen volgen:
    // "sectie[3][veld]" (tekst/textarea/select) of "sectie_logo_3" (het
    // sponsor-logo-bestandsveld); beide worden hier herkend en herindexeerd.
    function itemBlokToevoegen(lijstId, labelPrefix) {
      var lijst = document.getElementById(lijstId);
      if (!lijst) return;
      var blokken = lijst.querySelectorAll('.item-blok');
      if (blokken.length === 0) return;
      var laatste = blokken[blokken.length - 1];
      var nieuweIndex = blokken.length;
      var nieuw = laatste.cloneNode(true);

      nieuw.querySelectorAll('input, textarea, select').forEach(function(veld) {
        if (veld.name) {
          veld.name = veld.name
            .replace(/\[(\d+)\]/, '[' + nieuweIndex + ']')
            .replace(/_(\d+)$/, '_' + nieuweIndex);
        }
        if (veld.id) veld.id = veld.id.replace(/-(\d+)$/, '-' + nieuweIndex);

        // De agenda-volgordelijst wordt hierna apart bijgewerkt (opties
        // moeten met het nieuwe totaal meegroeien), dus die slaan we hier over.
        var isVolgordeSelect = veld.tagName === 'SELECT' && /\[volgorde\]$/.test(veld.name || '');

        if (isVolgordeSelect) {
          // niets doen, komt hierna aan de beurt
        } else if (veld.tagName === 'SELECT') {
          veld.selectedIndex = 0;
        } else if (veld.type === 'checkbox' || veld.type === 'radio') {
          veld.checked = false;
        } else if (veld.type !== 'file') {
          veld.value = '';
        }
      });

      nieuw.querySelectorAll('label[for]').forEach(function(label) {
        label.htmlFor = label.htmlFor.replace(/-(\d+)$/, '-' + nieuweIndex);
      });

      // data-taal-scope meenummeren, en het gekloonde blok altijd dichtgeklapt
      // en op onbeantwoord (NL) laten beginnen, ook als het origineel openstond.
      nieuw.querySelectorAll('[data-taal-scope]').forEach(function(el) {
        var scope = el.getAttribute('data-taal-scope').replace(/-(\d+)$/, '-' + nieuweIndex);
        el.setAttribute('data-taal-scope', scope);
        el.classList.remove('toon-en', 'toon-de');
      });
      nieuw.querySelectorAll('.taal-toggle-btn').forEach(function(knop) {
        knop.setAttribute('aria-pressed', 'false');
      });

      // Een gekloond blok kan een bestaand sponsorlogo tonen; een nieuw leeg
      // blok hoort dat niet te doen.
      nieuw.querySelectorAll('img').forEach(function(img) { img.remove(); });

      var nrLabel = nieuw.querySelector('.item-blok-nr');
      if (nrLabel) nrLabel.textContent = labelPrefix + ' ' + (nieuweIndex + 1);

      lijst.appendChild(nieuw);
      agendaVolgordeOpnieuwOpbouwen(lijst);
      nieuw.scrollIntoView({ block: 'center', behavior: 'smooth' });
      var eersteVeld = nieuw.querySelector('input, textarea');
      if (eersteVeld) eersteVeld.focus();
    }

    // Agenda: als er een nieuwe kaart bijkomt, moeten alle volgorde-
    // keuzelijstjes een extra optie krijgen (het nieuwe totaal), en het
    // zojuist toegevoegde (nog lege) blok krijgt standaard de laatste
    // plek. Bestaande keuzes van andere kaarten blijven ongemoeid. Bij
    // andere tabs dan Agenda vindt deze functie geen volgorde-select en
    // doet dan simpelweg niets.
    function agendaVolgordeOpnieuwOpbouwen(lijst) {
      var selects = lijst.querySelectorAll('select[name$="[volgorde]"]');
      if (selects.length === 0) return;
      var totaal = selects.length;
      selects.forEach(function(sel) {
        for (var p = sel.options.length + 1; p <= totaal; p++) {
          var optie = document.createElement('option');
          optie.value = String(p);
          optie.textContent = String(p);
          sel.appendChild(optie);
        }
      });
      selects[selects.length - 1].value = String(totaal);
    }

    // ===== Logboek: filter per kolom via een uitklapbaar vinkjeslijstje =====
    // Zelfde idee als een Excel-autofilter: klik op "Filter" bij een
    // kolomkop, vink aan welke waarden zichtbaar moeten blijven. Alle drie
    // de kolommen werken onafhankelijk van elkaar (EN, niet OF). Bij Tijd
    // wordt gefilterd op de datum (niet het exacte tijdstip) en bij Actie op
    // het actietype zonder de bijgevoegde details, anders zou de lijst met
    // mogelijke waarden bijna net zo lang worden als het aantal regels.
    // Puur client-side: er wordt sowieso maar één pagina met maximaal 100
    // regels getoond, dus een serverzoekactie heeft hier geen meerwaarde.
    (function() {
      var tabel = document.getElementById('logboek-tabel');
      if (!tabel) return;
      var knoppen = Array.prototype.slice.call(tabel.querySelectorAll('.logboek-filter-knop'));
      if (knoppen.length === 0) return;
      var geenResultatenMelding = document.querySelector('.logboek-geen-resultaten-melding');
      var dataRijen = Array.prototype.slice.call(tabel.querySelectorAll('tr')).filter(function(rij) {
        return rij.querySelector('td');
      });

      // kolomindex -> array van aangevinkte waarden. Geen sleutel voor een
      // kolom betekent "geen filter, alles tonen" (zo hoeven we niet steeds
      // de volledige waardenlijst te onthouden voor kolommen zonder filter).
      var geselecteerd = {};

      function celWaarde(cel) {
        return cel ? (cel.getAttribute('data-filterwaarde') || cel.textContent) : '';
      }

      function alleWaarden(kolom) {
        var set = {};
        dataRijen.forEach(function(rij) {
          set[celWaarde(rij.querySelectorAll('td')[kolom])] = true;
        });
        var waarden = Object.keys(set);
        if (kolom === 0) {
          // Tijd staat als dd-mm-jjjj: chronologisch sorteren, niet alfabetisch.
          waarden.sort(function(a, b) {
            var pa = a.split('-'), pb = b.split('-');
            return new Date(pa[2], pa[1] - 1, pa[0]) - new Date(pb[2], pb[1] - 1, pb[0]);
          });
        } else {
          waarden.sort(function(a, b) { return a.localeCompare(b, 'nl'); });
        }
        return waarden;
      }

      function toepassen() {
        var aantalZichtbaar = 0;
        dataRijen.forEach(function(rij) {
          var cellen = rij.querySelectorAll('td');
          var zichtbaar = Object.keys(geselecteerd).every(function(kolomStr) {
            return geselecteerd[kolomStr].indexOf(celWaarde(cellen[kolomStr])) !== -1;
          });
          rij.style.display = zichtbaar ? '' : 'none';
          if (zichtbaar) aantalZichtbaar++;
        });
        if (geenResultatenMelding) geenResultatenMelding.hidden = aantalZichtbaar !== 0;
        knoppen.forEach(function(knop) {
          knop.classList.toggle('actief', geselecteerd.hasOwnProperty(knop.getAttribute('data-kolom')));
        });
      }

      function paneelSluiten() {
        var open = tabel.querySelector('.logboek-filter-paneel');
        if (open) open.remove();
      }

      function paneelOpenen(knop) {
        var kolom = knop.getAttribute('data-kolom');
        var bestaandeWaarden = alleWaarden(kolom);
        var actief = geselecteerd[kolom]; // undefined = alles aan

        var paneel = document.createElement('div');
        paneel.className = 'logboek-filter-paneel';

        var acties = document.createElement('div');
        acties.className = 'logboek-filter-paneel-acties';
        var alleKnop = document.createElement('button');
        alleKnop.type = 'button';
        alleKnop.textContent = 'Alles';
        var geenKnop = document.createElement('button');
        geenKnop.type = 'button';
        geenKnop.textContent = 'Niets';
        acties.appendChild(alleKnop);
        acties.appendChild(geenKnop);
        paneel.appendChild(acties);

        var vinkjes = [];
        bestaandeWaarden.forEach(function(waarde, i) {
          var optie = document.createElement('div');
          optie.className = 'logboek-filter-optie';
          var id = 'logboek-filter-' + kolom + '-' + i;
          var vinkje = document.createElement('input');
          vinkje.type = 'checkbox';
          vinkje.id = id;
          vinkje.checked = !actief || actief.indexOf(waarde) !== -1;
          vinkje.value = waarde;
          var label = document.createElement('label');
          label.setAttribute('for', id);
          label.textContent = waarde === '' ? '(leeg)' : waarde;
          optie.appendChild(vinkje);
          optie.appendChild(label);
          paneel.appendChild(optie);
          vinkjes.push(vinkje);
        });

        function bijwerken() {
          var aangevinkt = vinkjes.filter(function(v) { return v.checked; }).map(function(v) { return v.value; });
          if (aangevinkt.length === bestaandeWaarden.length) {
            delete geselecteerd[kolom];
          } else {
            geselecteerd[kolom] = aangevinkt;
          }
          toepassen();
        }

        vinkjes.forEach(function(v) { v.addEventListener('change', bijwerken); });
        alleKnop.addEventListener('click', function() { vinkjes.forEach(function(v) { v.checked = true; }); bijwerken(); });
        geenKnop.addEventListener('click', function() { vinkjes.forEach(function(v) { v.checked = false; }); bijwerken(); });
        paneel.addEventListener('click', function(e) { e.stopPropagation(); });

        knop.parentElement.appendChild(paneel);
      }

      knoppen.forEach(function(knop) {
        knop.addEventListener('click', function(e) {
          e.stopPropagation();
          var bestondAl = knop.parentElement.querySelector('.logboek-filter-paneel');
          paneelSluiten();
          if (!bestondAl) paneelOpenen(knop);
        });
      });
      document.addEventListener('click', paneelSluiten);
    })();
    // ===== Klikken op een vergaderingsrij =====
    // Losse afhandeling, want de klikafhandeling van de ledentabel hangt
    // aan die ene tbody en geldt dus niet voor deze tabel. Zelfde gedrag:
    // een klik op een link binnen de rij doet gewoon zijn eigen ding.
    (function () {
      var vergTabel = document.getElementById('vergaderingen-tabel');
      if (!vergTabel) return;
      vergTabel.addEventListener('click', function (e) {
        if (e.target.closest('a, input')) return;
        var rij = e.target.closest('tr[data-href]');
        if (!rij) return;
        window.location.href = rij.getAttribute('data-href');
      });
    })();
    // Zelfde gedrag voor Ledenvergaderingen en Takenlijst: aparte tabellen,
    // dus aparte listener, net als hierboven bij de bestuursvergaderingen.
    ['ledenvergaderingen-tabel', 'takenlijst-tabel', 'operationele-taken-tabel', 'evenementen-tabel'].forEach(function (tabelId) {
      var tabel = document.getElementById(tabelId);
      if (!tabel) return;
      tabel.addEventListener('click', function (e) {
        if (e.target.closest('a, input')) return;
        var rij = e.target.closest('tr[data-href]');
        if (!rij) return;
        window.location.href = rij.getAttribute('data-href');
      });
    });

    // ===== Datumkiezers (kalenderknopje naast elk datumveld) =====
    // Het tekstveld (dd-mm-jjjj) blijft leidend en is nog steeds gewoon met
    // de hand in te vullen; het onzichtbare input[type=date] hierboven is
    // puur een hulpmiddel om een datum te kiezen zonder te hoeven typen.
    (function () {
      function tekstNaarIso(tekst) {
        var m = /^(\d{2})-(\d{2})-(\d{4})$/.exec(String(tekst || '').trim());
        return m ? (m[3] + '-' + m[2] + '-' + m[1]) : '';
      }
      function isoNaarTekst(iso) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || '').trim());
        return m ? (m[3] + '-' + m[2] + '-' + m[1]) : '';
      }
      document.querySelectorAll('.datum-picker-wrap').forEach(function (knop) {
        var picker = knop.querySelector('.datum-picker');
        var doel = picker ? document.getElementById(picker.getAttribute('data-doel')) : null;
        if (!picker || !doel) return;
        // Een klik op het onzichtbare input[type=date] zelf opent de kalender
        // niet betrouwbaar in elke browser (met name Firefox niet), dus hier
        // expliciet aanroepen vanuit de knop. Eerst vullen met de datum die al
        // in het tekstveld staat, zodat je niet steeds bij vandaag begint.
        knop.addEventListener('click', function () {
          var iso = tekstNaarIso(doel.value);
          if (iso) picker.value = iso;
          if (typeof picker.showPicker === 'function') {
            try { picker.showPicker(); return; } catch (err) { /* val terug op focus hieronder */ }
          }
          picker.focus();
        });
        picker.addEventListener('change', function () {
          var tekst = isoNaarTekst(picker.value);
          if (!tekst) return;
          doel.value = tekst;
          doel.dispatchEvent(new Event('input', { bubbles: true }));
          doel.dispatchEvent(new Event('change', { bubbles: true }));
        });
      });
    })();

    // ===== Zoeken en filteren in de ledenlijst =====
    // In de pagina zelf, zodat er niet bij elke toetsaanslag herladen hoeft
    // te worden. Zonder JavaScript blijft gewoon de hele lijst zichtbaar.
    (function () {
      var zoek = document.getElementById('leden-zoek');
      var filter = document.getElementById('leden-filter-status');
      var filterContributie = document.getElementById('leden-filter-contributie');
      var filterRol = document.getElementById('leden-filter-rol');
      var tabel = document.getElementById('leden-tabel');
      // Geen return hier: dat brak per ongeluk ook de "Gebruik ..."-knop
      // en (in potentie) de vertaalknoppen hieronder, want die staan in
      // dezelfde functie en zijn niet afhankelijk van de tabel. Zoeken,
      // filteren, sorteren en bulk-selecteren hebben de tabel wel nodig,
      // dus die zitten voortaan in dit blok, de rest niet.
      if (zoek && filter && tabel) {
      var rijen = tabel.querySelectorAll('tbody tr');
      var geenResultaat = document.getElementById('leden-geen-resultaat');
      var statusBadges = document.querySelectorAll('.leden-badge-status');
      var contributieBadges = document.querySelectorAll('.leden-badge-contributie');

      function badgesBijwerken() {
        Array.prototype.forEach.call(statusBadges, function (badge) {
          var actief = badge.getAttribute('data-status') === filter.value;
          badge.setAttribute('aria-pressed', actief ? 'true' : 'false');
        });
        Array.prototype.forEach.call(contributieBadges, function (badge) {
          var actief = filterContributie && badge.getAttribute('data-contributie') === filterContributie.value;
          badge.setAttribute('aria-pressed', actief ? 'true' : 'false');
        });
      }

      function filteren() {
        var term = zoek.value.trim().toLowerCase();
        var status = filter.value;
        var contributie = filterContributie ? filterContributie.value : '';
        var rol = filterRol ? filterRol.value : '';
        var zichtbaar = 0;
        Array.prototype.forEach.call(rijen, function (rij) {
          var pastTekst = term === '' || (rij.getAttribute('data-zoek') || '').indexOf(term) !== -1;
          var pastStatus = status === '' || rij.getAttribute('data-status') === status;
          var pastContributie = contributie === '' || rij.getAttribute('data-contributie') === contributie;
          // Spaties eromheen, anders zou "functie:bestuurslid" ook matchen
          // op een filter dat alleen "bestuur" zoekt.
          var pastRol = rol === '' || (rij.getAttribute('data-rol') || '').indexOf(' ' + rol + ' ') !== -1;
          var toon = pastTekst && pastStatus && pastContributie && pastRol;
          rij.hidden = !toon;
          if (toon) zichtbaar++;
        });
        if (geenResultaat) geenResultaat.hidden = zichtbaar !== 0;
        badgesBijwerken();
      }

      zoek.addEventListener('input', filteren);
      filter.addEventListener('change', filteren);
      if (filterContributie) filterContributie.addEventListener('change', filteren);
      if (filterRol) filterRol.addEventListener('change', filteren);

      // ===== Klikbare statusbadges bovenaan =====
      // Klik op bijvoorbeeld "Nieuw: 3" zet het statusfilter hierboven op
      // die status. Nog een keer klikken op dezelfde badge heft het filter
      // weer op, net als "Alle statussen" kiezen in de vervolgkeuzelijst.
      // Zelfde idee voor de contributiebadges eronder, die zetten het
      // contributiefilter in plaats van het statusfilter.
      Array.prototype.forEach.call(statusBadges, function (badge) {
        badge.addEventListener('click', function () {
          var status = badge.getAttribute('data-status');
          filter.value = (filter.value === status) ? '' : status;
          filteren();
        });
      });
      Array.prototype.forEach.call(contributieBadges, function (badge) {
        badge.addEventListener('click', function () {
          if (!filterContributie) return;
          var contributie = badge.getAttribute('data-contributie');
          filterContributie.value = (filterContributie.value === contributie) ? '' : contributie;
          filteren();
        });
      });

      // ===== Sorteren op kolom =====
      // Klik op een kolomkop sorteert erop; nog een keer klikken keert de
      // volgorde om. Werkt los van het zoeken/filteren hierboven, want dat
      // verbergt alleen rijen (hidden) en verandert de volgorde niet.
      var tbody = tabel.querySelector('tbody');
      var koppen = tabel.querySelectorAll('thead th[data-kolom]');
      var sorteerKeuze = document.getElementById('leden-sorteer');
      var sorteerKnop = document.getElementById('leden-sorteer-richting');
      var oorspronkelijkeVolgorde = Array.prototype.slice.call(rijen);
      var sorteerKolom = null;
      var sorteerRichting = 1; // 1 = oplopend, -1 = aflopend

      function sorteerWaarde(rij, kolom) {
        return rij.getAttribute('data-sort-' + kolom) || '';
      }

      function vergelijkRijen(a, b) {
        var wa = sorteerWaarde(a, sorteerKolom);
        var wb = sorteerWaarde(b, sorteerKolom);
        var na = parseFloat(wa);
        var nb = parseFloat(wb);
        var beideGetallen = wa !== '' && wb !== '' && !isNaN(na) && !isNaN(nb);
        if (beideGetallen) return (na - nb) * sorteerRichting;
        return wa.localeCompare(wb, 'nl') * sorteerRichting;
      }

      function sorteerTabel() {
        var volgorde = sorteerKolom
          ? Array.prototype.slice.call(rijen).sort(vergelijkRijen)
          : oorspronkelijkeVolgorde;
        volgorde.forEach(function (rij) { tbody.appendChild(rij); });
      }

      // Zowel de kolomkoppen (breed scherm) als de keuzelijst eronder
      // (smal scherm, waar de koppen verborgen zijn) komen hier uit, zodat
      // de twee bedieningen elkaar niet tegenspreken.
      function sorteringZetten(kolom, richting) {
        sorteerKolom = kolom || null;
        sorteerRichting = richting;
        Array.prototype.forEach.call(koppen, function (k) {
          var actief = k.getAttribute('data-kolom') === sorteerKolom;
          k.classList.toggle('leden-sorteer-op', actief);
          k.classList.toggle('leden-sorteer-aflopend', actief && sorteerRichting === -1);
          if (actief) {
            k.setAttribute('aria-sort', sorteerRichting === 1 ? 'ascending' : 'descending');
          } else {
            k.removeAttribute('aria-sort');
          }
        });
        if (sorteerKeuze) sorteerKeuze.value = sorteerKolom || '';
        if (sorteerKnop) {
          sorteerKnop.innerHTML = sorteerRichting === 1 ? '&uarr;' : '&darr;';
          sorteerKnop.disabled = sorteerKolom === null;
        }
        sorteerTabel();
        // Onthouden zodat de sortering blijft staan als je een lid opent
        // en weer sluit (dat is telkens een nieuwe paginalading).
        try {
          if (sorteerKolom) {
            localStorage.setItem('ledenSortering', JSON.stringify({ kolom: sorteerKolom, richting: sorteerRichting }));
          } else {
            localStorage.removeItem('ledenSortering');
          }
        } catch (e) {}
      }

      Array.prototype.forEach.call(koppen, function (kop) {
        function activeer() {
          var kolom = kop.getAttribute('data-kolom');
          sorteringZetten(kolom, sorteerKolom === kolom ? sorteerRichting * -1 : 1);
        }
        kop.addEventListener('click', activeer);
        kop.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activeer(); }
        });
      });

      if (sorteerKeuze) {
        sorteerKeuze.addEventListener('change', function () {
          sorteringZetten(sorteerKeuze.value, 1);
        });
      }
      if (sorteerKnop) {
        sorteerKnop.disabled = true;
        sorteerKnop.addEventListener('click', function () {
          if (!sorteerKolom) return;
          sorteringZetten(sorteerKolom, sorteerRichting * -1);
        });
      }

      // Onthouden sortering terugzetten bij het laden van de pagina.
      try {
        var opgeslagenSortering = JSON.parse(localStorage.getItem('ledenSortering') || 'null');
        if (opgeslagenSortering && opgeslagenSortering.kolom) {
          sorteringZetten(opgeslagenSortering.kolom, opgeslagenSortering.richting === -1 ? -1 : 1);
        }
      } catch (e) {}

      // ===== Klikken op een rij opent de bewerkpagina =====
      // Behalve als er op een link of het selectievinkje binnen de rij
      // geklikt wordt: die doen dan gewoon hun eigen ding.
      tbody.addEventListener('click', function (e) {
        if (e.target.closest('a, input')) return;
        var rij = e.target.closest('tr[data-href]');
        if (!rij) return;
        window.location.href = rij.getAttribute('data-href');
      });

      // ===== Bulk-status wijzigen =====
      // Vinkje per rij (in de naamkolom) plus "alles selecteren" erboven,
      // die alleen kijkt naar wat er nu zichtbaar is (rij.hidden), dus een
      // actief zoek/filter beperkt ook wat er geselecteerd wordt. Zodra er
      // 1 of meer aangevinkt zijn verschijnt de balk met een statuskeuze
      // en een knop, die in een keer de status van alle geselecteerde
      // leden aanpast via het leden_bulk_status-formulier.
      (function () {
        var vinkjes = tabel.querySelectorAll('.leden-select-vink');
        var allesVinkje = document.getElementById('leden-select-alles');
        var bulkBalk = document.getElementById('leden-bulk-balk');
        var bulkTelling = document.getElementById('leden-bulk-telling');
        var bulkIds = document.getElementById('leden-bulk-ids');
        if (vinkjes.length === 0 || !bulkBalk) return;

        function geselecteerd() {
          return Array.prototype.filter.call(vinkjes, function (v) { return v.checked; });
        }

        function bulkBijwerken() {
          var lijst = geselecteerd();
          bulkBalk.style.display = lijst.length > 0 ? 'flex' : 'none';
          if (bulkTelling) {
            bulkTelling.textContent = lijst.length + (lijst.length === 1 ? ' lid geselecteerd' : ' leden geselecteerd');
          }
          if (bulkIds) {
            bulkIds.value = lijst.map(function (v) { return v.value; }).join(',');
          }
        }

        Array.prototype.forEach.call(vinkjes, function (vinkje) {
          vinkje.addEventListener('change', function () {
            if (allesVinkje && !vinkje.checked) allesVinkje.checked = false;
            bulkBijwerken();
          });
        });

        if (allesVinkje) {
          allesVinkje.addEventListener('change', function () {
            Array.prototype.forEach.call(vinkjes, function (vinkje) {
              var rij = vinkje.closest('tr');
              if (rij && rij.hidden) return;
              vinkje.checked = allesVinkje.checked;
            });
            bulkBijwerken();
          });
        }
      })();
      }

      // ===== "Gebruik X" knop bij het lidnummer =====
      // Vult het eerstvolgende vrije nummer meteen in, handig om een
      // dubbel lidnummer op te lossen zonder zelf te hoeven uitzoeken
      // welk nummer nog vrij is.
      (function () {
        var knop = document.getElementById('lid-nummer-vrij');
        var veld = document.getElementById('lid-nummer');
        if (!knop || !veld) return;
        knop.addEventListener('click', function () {
          veld.value = knop.getAttribute('data-vrij');
          veld.focus();
        });
      })();

      // ===== Snelkeuze contributiebedrag =====
      // Vult het bijbehorende bedragveld met de gekozen waarde uit de
      // rekentabel. Het bedragveld zelf blijft altijd gewoon te bewerken,
      // voor de uitzondering waarbij handmatig een ander bedrag nodig is.
      Array.prototype.forEach.call(document.querySelectorAll('.leden-bedrag-snelkeuze'), function (select) {
        var veld = document.getElementById(select.getAttribute('data-doel'));
        if (!veld) return;
        select.addEventListener('change', function () {
          if (select.value === '') return;
          veld.value = select.value;
          select.value = '';
        });
      });

      // ===== Vertalingen tonen/verbergen, per onderdeel =====
      // Elk onderdeel met vertaalbare velden (een groep, een album, een item...)
      // heeft een data-taal-scope en eigen EN/DE-knopjes in de kop van dat
      // onderdeel. Een klik zet toon-en/toon-de op dat ene onderdeel, niet op
      // de hele pagina, zodat secties los van elkaar opengezet kunnen worden.
      // Werkt via event delegation zodat het ook meteen goed staat voor
      // itemblokken die je later met "+ toevoegen" erbij klikt.
      (function() {
        var opslagsleutel = 'rc045-beheer-vertalingen';
        var opgeslagen = {};
        try {
          opgeslagen = JSON.parse(localStorage.getItem(opslagsleutel)) || {};
        } catch (e) { opgeslagen = {}; }

        function bewaar() {
          try { localStorage.setItem(opslagsleutel, JSON.stringify(opgeslagen)); } catch (e) {}
        }

        function scopeToepassen(scopeEl) {
          var sleutel = scopeEl.getAttribute('data-taal-scope');
          var actief = opgeslagen[sleutel] || [];
          ['en', 'de'].forEach(function(taal) {
            var aan = actief.indexOf(taal) !== -1;
            scopeEl.classList.toggle('toon-' + taal, aan);
            var knop = scopeEl.querySelector('.taal-toggle-btn[data-taal="' + taal + '"]');
            if (knop) knop.setAttribute('aria-pressed', aan ? 'true' : 'false');
          });
        }

        document.querySelectorAll('[data-taal-scope]').forEach(scopeToepassen);

        document.addEventListener('click', function(e) {
          var knop = e.target.closest('.taal-toggle-btn');
          if (!knop) return;
          var scopeEl = knop.closest('[data-taal-scope]');
          if (!scopeEl) return;
          // Voorkomt dat een knopje in een <summary> meteen de kaart open/dicht klapt.
          e.preventDefault();
          e.stopPropagation();

          var taal = knop.getAttribute('data-taal');
          var sleutel = scopeEl.getAttribute('data-taal-scope');
          var aan = scopeEl.classList.toggle('toon-' + taal);
          knop.setAttribute('aria-pressed', aan ? 'true' : 'false');

          var lijst = opgeslagen[sleutel] || [];
          lijst = lijst.filter(function(t) { return t !== taal; });
          if (aan) lijst.push(taal);
          opgeslagen[sleutel] = lijst;
          bewaar();
        });
      })();

      // ===== Automatisch vertalen via DeepL =====
      // Eén "Vertaal"-knopje per data-taal-scope (naast de bestaande EN/DE
      // toon-knopjes). Verzamelt alle ingevulde NL-velden binnen dat ene
      // onderdeel, stuurt ze in één keer naar vertaal.php, en zet het
      // resultaat terug in de bijbehorende EN/DE-velden (gevonden via het
      // id-patroon "...-nl" -> "...-en"/"...-de", zelfde patroon dat overal
      // in dit bestand wordt gebruikt). De vertaling is een startpunt: na
      // het vertalen blijven de EN/DE-velden gewoon met de hand aanpasbaar,
      // en pas bewaren doet nog steeds de eigen opslaan-knop van het formulier.
      (function() {
        document.addEventListener('click', function(e) {
          var knop = e.target.closest('.auto-vertaal-btn');
          if (!knop) return;
          var scopeEl = knop.closest('[data-taal-scope]');
          if (!scopeEl) return;
          e.preventDefault();
          e.stopPropagation();

          var nlVelden = scopeEl.querySelectorAll('.taal-nl input[id], .taal-nl textarea[id], input.taal-nl[id], textarea.taal-nl[id]');
          var teVertalen = [];
          nlVelden.forEach(function(veld) {
            var tekst = veld.value.trim();
            if (tekst) teVertalen.push({ veld: veld, tekst: tekst });
          });
          if (teVertalen.length === 0) {
            alert('Vul eerst de Nederlandse tekst in, daar vertaalt dit knopje vanuit.');
            return;
          }

          knop.disabled = true;
          var oorspronkelijkeTekst = knop.textContent;
          knop.textContent = '\u2026';

          fetch('vertaal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              teksten: teVertalen.map(function(t) { return t.tekst; }),
              doeltalen: ['EN', 'DE']
            })
          })
            .then(function(res) {
              return res.json().then(function(data) {
                if (!res.ok || data.error) throw new Error(data.error || ('Serverfout (' + res.status + ')'));
                return data;
              });
            })
            .then(function(data) {
              ['en', 'de'].forEach(function(taal) {
                var vertalingen = data[taal.toUpperCase()] || [];
                teVertalen.forEach(function(t, i) {
                  var doelId = t.veld.id.replace(/-nl(?=(-\d+)?$)/, '-' + taal);
                  var doelVeld = document.getElementById(doelId);
                  if (doelVeld && typeof vertalingen[i] === 'string') doelVeld.value = vertalingen[i];
                });
                // Klapt het EN/DE-blok open via de bestaande toon-knop, zodat
                // ook de "onthouden welke talen openstaan"-logica hierboven
                // gewoon blijft werken in plaats van dat hier dubbel te doen.
                var toggle = scopeEl.querySelector('.taal-toggle-btn[data-taal="' + taal + '"]');
                if (toggle && toggle.getAttribute('aria-pressed') !== 'true') toggle.click();
              });
            })
            .catch(function(err) {
              alert('Vertalen mislukt: ' + err.message);
            })
            .finally(function() {
              knop.disabled = false;
              knop.textContent = oorspronkelijkeTekst;
            });
        });
      })();

      // ===== Changelog: filteren op categorie en zoekwoord =====
      // Puur in de browser, zonder herladen. De categorieknoppen werken als
      // aan/uit: niets aangeklikt betekent alles tonen.
      (function() {
        var lijst = document.getElementById('cl-lijst');
        if (!lijst) return; // tabblad niet zichtbaar voor deze gebruiker
        var regels = lijst.querySelectorAll('.cl-regel');
        var knoppen = document.querySelectorAll('.cl-filter-knop');
        var zoekVeld = document.getElementById('cl-zoek');
        var telling = document.getElementById('cl-telling');

        function toepassen() {
          var actief = [];
          knoppen.forEach(function(k) {
            if (k.getAttribute('aria-pressed') === 'true') actief.push(k.getAttribute('data-cat'));
          });
          var zoek = zoekVeld ? zoekVeld.value.trim().toLowerCase() : '';
          var zichtbaar = 0;

          regels.forEach(function(regel) {
            var catOk = actief.length === 0 || actief.indexOf(regel.getAttribute('data-cat')) !== -1;
            var zoekOk = zoek === '' || (regel.getAttribute('data-zoek') || '').indexOf(zoek) !== -1;
            var toon = catOk && zoekOk;
            regel.hidden = !toon;
            if (toon) zichtbaar++;
          });

          if (telling) {
            telling.textContent = zichtbaar === regels.length
              ? regels.length + ' regel' + (regels.length === 1 ? '' : 's')
              : zichtbaar + ' van ' + regels.length + ' regels';
          }
        }

        knoppen.forEach(function(k) {
          k.addEventListener('click', function() {
            k.setAttribute('aria-pressed', k.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
            toepassen();
          });
        });
        if (zoekVeld) zoekVeld.addEventListener('input', toepassen);
      })();
    })();
