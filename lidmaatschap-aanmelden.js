(() => {
  'use strict';

  const form = document.getElementById('aanmeld-form');
  const dob = document.getElementById('geboortedatum');
  const content = document.getElementById('contributie-content');
  const badge = document.getElementById('lidtype-badge');
  const legacyHidden = document.getElementById('contributiebedrag-hidden');
  if (!form || !dob || !content || !badge) return;

  const teksten = {
    nl: { label: 'Lidmaatschapstype', choose: 'Kies een lidmaatschapstype', fillDob: 'Vul eerst je geboortedatum in.', none: 'Voor deze geboortedatum is geen actief lidmaatschapstype beschikbaar.', fee: 'Contributie', signup: 'Inschrijfgeld', total: 'Totaal te betalen', year: 'Jaarbedrag' },
    en: { label: 'Membership type', choose: 'Choose a membership type', fillDob: 'Enter your date of birth first.', none: 'No active membership type is available for this date of birth.', fee: 'Membership fee', signup: 'Registration fee', total: 'Total due', year: 'Annual fee' },
    de: { label: 'Mitgliedschaftsart', choose: 'Mitgliedschaftsart wählen', fillDob: 'Bitte zuerst dein Geburtsdatum eingeben.', none: 'Für dieses Geburtsdatum ist keine aktive Mitgliedschaftsart verfügbar.', fee: 'Mitgliedsbeitrag', signup: 'Anmeldegebühr', total: 'Gesamtbetrag', year: 'Jahresbeitrag' }
  };

  let types = [];
  let observer = null;

  function lang() {
    const l = String(document.documentElement.lang || 'nl').toLowerCase().slice(0, 2);
    return Object.prototype.hasOwnProperty.call(teksten, l) ? l : 'nl';
  }

  function label(type) {
    const labels = type && typeof type.label === 'object' ? type.label : { nl: String(type?.label || type?.id || '') };
    return String(labels[lang()] || labels.nl || type?.id || '');
  }

  function ageAtJan1(value) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));
    if (!m) return null;
    const birth = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    if (Number.isNaN(birth.getTime())) return null;
    const year = new Date().getFullYear();
    let age = year - birth.getFullYear();
    const jan1Month = 0;
    const jan1Day = 1;
    if (jan1Month < birth.getMonth() || (jan1Month === birth.getMonth() && jan1Day < birth.getDate())) age--;
    return age >= 0 && age <= 130 ? age : null;
  }

  function allowed(type, age) {
    if (!type || type.actief === false) return false;
    const min = type.leeftijd_min === null || type.leeftijd_min === '' || type.leeftijd_min === undefined ? null : Number(type.leeftijd_min);
    const max = type.leeftijd_max === null || type.leeftijd_max === '' || type.leeftijd_max === undefined ? null : Number(type.leeftijd_max);
    if (min === null && max === null) return true;
    if (age === null) return false;
    if (min !== null && age < min) return false;
    if (max !== null && age > max) return false;
    return true;
  }

  const group = document.createElement('div');
  group.className = 'form-group full';
  group.id = 'lidmaatschap-type-group';
  const labelEl = document.createElement('label');
  labelEl.htmlFor = 'lidmaatschap-type';
  const select = document.createElement('select');
  select.id = 'lidmaatschap-type';
  select.name = 'lidmaatschap_type';
  select.required = true;
  select.disabled = true;
  group.append(labelEl, select);

  const dobGroup = dob.closest('.form-group');
  if (dobGroup?.parentElement) dobGroup.parentElement.appendChild(group);
  else form.prepend(group);

  function refreshOptions() {
    const t = teksten[lang()];
    labelEl.textContent = t.label;
    const age = ageAtJan1(dob.value);
    const previous = select.value;
    const eligible = types.filter(type => allowed(type, age));
    select.textContent = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = dob.value ? t.choose : t.fillDob;
    select.appendChild(placeholder);

    eligible.forEach(type => {
      const option = document.createElement('option');
      option.value = String(type.id || '');
      option.textContent = label(type);
      select.appendChild(option);
    });

    select.disabled = !dob.value || eligible.length === 0;
    if (eligible.some(type => String(type.id) === previous)) select.value = previous;
    else if (eligible.length === 1) select.value = String(eligible[0].id);
    else select.value = '';
    return eligible;
  }

  function euro(value) {
    return new Intl.NumberFormat(lang() === 'en' ? 'en-GB' : lang() === 'de' ? 'de-DE' : 'nl-NL', { style: 'currency', currency: 'EUR' }).format(Number(value || 0));
  }

  function contribution(type) {
    const annual = Math.max(0, Number(type.jaarbedrag || 0));
    if (type.pro_rata === false) return annual;
    const month = new Date().getMonth() + 1;
    if (month === 12) return 0;
    return Math.round(annual * (12 - month) / 12);
  }

  function render() {
    if (observer) observer.disconnect();
    try {
      const t = teksten[lang()];
      const age = ageAtJan1(dob.value);
      const eligible = types.filter(type => allowed(type, age));
      const type = eligible.find(item => String(item.id) === select.value) || null;

      content.textContent = '';
      badge.textContent = '';
      badge.style.display = 'none';

      if (!dob.value) {
        content.textContent = t.fillDob;
        if (legacyHidden) legacyHidden.value = '';
        return;
      }
      if (eligible.length === 0) {
        content.textContent = t.none;
        if (legacyHidden) legacyHidden.value = '';
        return;
      }
      if (!type) {
        content.textContent = t.choose;
        if (legacyHidden) legacyHidden.value = '';
        return;
      }

      const fee = contribution(type);
      const signup = Math.max(0, Number(type.inschrijfgeld || 0));
      const total = fee + signup;
      badge.textContent = label(type);
      badge.style.display = '';

      const lines = [
        `${t.year}: ${euro(type.jaarbedrag)}`,
        `${t.fee}: ${euro(fee)}`,
        `${t.signup}: ${euro(signup)}`,
        `${t.total}: ${euro(total)}`
      ];
      lines.forEach((line, index) => {
        const node = index === lines.length - 1 ? document.createElement('strong') : document.createElement('span');
        node.textContent = line;
        content.appendChild(node);
        if (index < lines.length - 1) content.appendChild(document.createElement('br'));
      });
      // Alleen compatibility/Formspree-display; de lokale server rekent zelf.
      if (legacyHidden) legacyHidden.value = `${label(type)}: ${euro(total)}`;
    } finally {
      if (observer) observer.observe(content, { childList: true, subtree: true, characterData: true });
    }
  }

  observer = new MutationObserver(() => requestAnimationFrame(render));
  observer.observe(content, { childList: true, subtree: true, characterData: true });

  dob.addEventListener('change', () => { refreshOptions(); render(); });
  select.addEventListener('change', render);
  document.addEventListener('click', event => {
    if (event.target.closest('.lang-flag')) setTimeout(() => { refreshOptions(); render(); }, 0);
  });

  fetch('data/lidmaatschapstypen.json', { cache: 'no-store' })
    .then(response => response.ok ? response.json() : Promise.reject(new Error('membership types unavailable')))
    .then(data => {
      types = Array.isArray(data?.types) ? data.types.filter(type => type && type.actief !== false && type.id) : [];
      refreshOptions();
      render();
    })
    .catch(() => {
      // De bestaande calculator blijft zichtbaar als de configuratie niet kan
      // worden geladen. De server blijft bij POST de uiteindelijke waarheid.
      group.remove();
      observer.disconnect();
    });
})();
