(() => {
  'use strict';

  const search = document.getElementById('tenant-search');
  const filter = document.getElementById('tenant-filter');
  const cards = [...document.querySelectorAll('.tenant-card')];
  const count = document.getElementById('tenant-count');
  const empty = document.getElementById('no-results');

  const applyTenantFilter = () => {
    if (!cards.length) return;
    const q = (search?.value || '').trim().toLowerCase();
    const status = filter?.value || 'all';
    let visible = 0;
    cards.forEach((card) => {
      const matchesText = q === '' || (card.dataset.tenant || '').includes(q);
      const matchesStatus = status === 'all' || (status === 'attention' ? card.dataset.attention === '1' : card.dataset.status === status);
      const show = matchesText && matchesStatus;
      card.classList.toggle('hidden', !show);
      if (show) visible += 1;
    });
    if (count) count.textContent = `${visible} zichtbaar`;
    if (empty) empty.hidden = !(cards.length > 0 && visible === 0);
  };
  search?.addEventListener('input', applyTenantFilter);
  filter?.addEventListener('change', applyTenantFilter);

  document.querySelectorAll('[data-confirm-suspend="1"]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const tenant = form.dataset.tenantLabel || 'deze vereniging';
      const ok = window.confirm(`Weet je het zeker?\n\nJe schakelt ${tenant} uit. De website toont daarna een tijdelijke placeholder en tenant-runtime/database worden gestopt. Je kunt de vereniging later weer heractiveren.`);
      if (!ok) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-confirm-action]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const text = form.dataset.confirmAction || 'Weet je zeker dat je deze actie wilt uitvoeren?';
      if (!window.confirm(text)) event.preventDefault();
    });
  });

  document.querySelectorAll('.copy-btn[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.dataset.copy || '';
      if (!value) return;
      const old = button.textContent;
      try {
        await navigator.clipboard.writeText(value);
        button.textContent = 'Gekopieerd';
        button.classList.add('copy-ok');
      } catch (_) {
        button.textContent = 'Kopiëren mislukt';
      }
      window.setTimeout(() => {
        button.textContent = old;
        button.classList.remove('copy-ok');
      }, 1800);
    });
  });

  document.querySelectorAll('[data-focus-search]').forEach((button) => {
    button.addEventListener('click', () => search?.focus());
  });

  applyTenantFilter();
})();
