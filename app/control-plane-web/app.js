(() => {
  'use strict';

  const search = document.getElementById('tenant-search');
  const filter = document.getElementById('tenant-filter');
  const cards = [...document.querySelectorAll('.tenant-card')];
  const count = document.getElementById('tenant-count');
  const empty = document.getElementById('no-results');

  const systemGrid = document.querySelector('.system-grid');
  if (systemGrid) {
    const iconPaths = [
      'M4 6h16v12H4z M8 14h.01 M12 14h4',
      'M8 6h8v12H8z M9 2v4 M13 2v4 M17 2v4 M9 18v4 M13 18v4 M17 18v4 M2 9h6 M2 13h6 M16 9h6 M16 13h6',
      'M4 18a8 8 0 1 1 16 0 M12 14l4-4',
      'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18 M12 7v5l3 2',
      'M4 12h5 M15 12h5 M9 12a3 3 0 1 0 6 0a3 3 0 1 0-6 0',
      'M4 5h16v14H4z M7 9l3 3-3 3 M12 15h5',
    ];

    const addSystemMetricIcons = () => {
      systemGrid.querySelectorAll('.metric').forEach((metric, index) => {
        const label = metric.querySelector(':scope > span');
        const pathData = iconPaths[index];
        if (!label || !pathData || label.querySelector('.metric-icon')) return;

        label.style.display = 'flex';
        label.style.alignItems = 'center';
        label.style.gap = '6px';
        label.style.fontWeight = '650';

        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '1.8');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        svg.classList.add('metric-icon');
        svg.style.width = '16px';
        svg.style.height = '16px';
        svg.style.flex = '0 0 16px';
        svg.style.color = '#315c40';

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', pathData);
        svg.appendChild(path);
        label.prepend(svg);
      });
    };

    const fitSystemGrid = () => {
      const width = systemGrid.getBoundingClientRect().width;
      const columns = width >= 650 ? 3 : (width >= 300 ? 2 : 1);
      systemGrid.style.gridTemplateColumns = `repeat(${columns}, minmax(0, 1fr))`;

      systemGrid.querySelectorAll('.metric strong').forEach((value) => {
        value.style.wordBreak = 'normal';
        value.style.overflowWrap = 'normal';
        value.style.whiteSpace = 'nowrap';
      });
    };

    addSystemMetricIcons();
    fitSystemGrid();
    if ('ResizeObserver' in window) {
      const systemGridObserver = new ResizeObserver(fitSystemGrid);
      systemGridObserver.observe(systemGrid);
    } else {
      window.addEventListener('resize', fitSystemGrid, { passive: true });
    }
  }

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

  const params = new URLSearchParams(window.location.search);
  if (params.get('section') === 'onboarding') {
    const title = document.querySelector('.section-title');
    if (title && !document.getElementById('resume-onboarding-link')) {
      const link = document.createElement('a');
      link.id = 'resume-onboarding-link';
      link.href = '/onboarding.php';
      link.className = 'btn primary';
      link.textContent = 'Automatische onboarding openen';
      link.style.marginTop = '10px';
      title.appendChild(link);
    }
  }

  applyTenantFilter();
})();