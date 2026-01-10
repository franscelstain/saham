/* global Tabulator */
(function () {
  const cfg = window.__TA || {};
  const el = (q) => document.querySelector(q);

  const fmtInt = (n) => {
    if (n === null || n === undefined || n === '') return '-';
    const x = Number(n);
    if (!Number.isFinite(x)) return '-';
    return x.toLocaleString('en-US');
  };

  const fmtNum = (n) => {
    if (n === null || n === undefined || n === '') return '-';
    const x = Number(n);
    if (!Number.isFinite(x)) return '-';
    // harga biasanya integer, tapi biar aman:
    return x % 1 === 0 ? x.toLocaleString('en-US') : x.toLocaleString('en-US', { maximumFractionDigits: 4 });
  };

  const toast = (msg) => console.log(msg);

  async function post(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': cfg.csrf || '',
      },
      body: JSON.stringify(body || {}),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || ('HTTP ' + res.status));
    return data;
  }

  // logo cell with fallback letter ticker
  function logoFormatter(cell) {
    const row = cell.getRow().getData();
    const code = (row.ticker_code || '').trim();
    const logo = (row.company_logo || '').trim();

    const letter = code ? code[0].toUpperCase() : '?';

    // if no logo: fallback avatar
    if (!logo) {
      return `<div class="w-8 h-8 rounded-xl bg-base-200 border border-base-300 grid place-items-center font-bold">${letter}</div>`;
    }

    // local public path: assume already like "images/companies/xx.png" or "/images/.."
    const src = logo.startsWith('/') ? logo : ('/' + logo);

    // onerror fallback to letter
    return `
      <div class="w-8 h-8 rounded-xl overflow-hidden border border-base-300 bg-base-200 grid place-items-center">
        <img src="${src}" alt="${code}" style="width:100%;height:100%;object-fit:cover"
             onerror="this.onerror=null;this.remove(); this.parentNode.textContent='${letter}'; this.parentNode.className='w-8 h-8 rounded-xl bg-base-200 border border-base-300 grid place-items-center font-bold';">
      </div>
    `;
  }

  const table = new Tabulator('#tblTickers', {
    layout: 'fitColumns',
    height: '560px',
    pagination: true,
    paginationMode: 'remote',
    paginationSize: 25,
    paginationSizeSelector: [25, 50, 100],
    ajaxURL: cfg.dataUrl,
    ajaxConfig: 'GET',
    dataReceiveParams: {
      last_page: "last_page",
      data: "data",
    },
    ajaxURLGenerator: function (url, config, params) {
      // Tabulator remote params: page, size, sorters...
      const page = params.page || 1;
      const size = params.size || 25;

      const q = (el('#q')?.value || '').trim();
      const sorters = params.sorters || [];
      const s = sorters[0] || {};
      const sort = s.field || 'ticker_code';
      const dir = s.dir || 'asc';

      const usp = new URLSearchParams();
      usp.set('page', page);
      usp.set('size', size);
      usp.set('search', q);
      usp.set('sort', sort);
      usp.set('dir', dir);

      return url + '?' + usp.toString();
    },
    placeholder: 'No data',
    columns: [
      { title: '', field: 'company_logo', width: 60, hozAlign: 'center', headerSort: false, formatter: logoFormatter },
      { title: 'Ticker', field: 'ticker_code', width: 110, sorter: 'string' },
      { title: 'Company', field: 'company_name', minWidth: 240, sorter: 'string' },
      { title: 'Date', field: 'trade_date', width: 120, sorter: 'string' },
      { title: 'Open', field: 'open', width: 110, hozAlign: 'right', formatter: (c) => fmtNum(c.getValue()), sorter: 'number' },
      { title: 'High', field: 'high', width: 110, hozAlign: 'right', formatter: (c) => fmtNum(c.getValue()), sorter: 'number' },
      { title: 'Low', field: 'low', width: 110, hozAlign: 'right', formatter: (c) => fmtNum(c.getValue()), sorter: 'number' },
      { title: 'Close', field: 'close', width: 110, hozAlign: 'right', formatter: (c) => fmtNum(c.getValue()), sorter: 'number' },
      { title: 'Vol', field: 'volume', width: 140, hozAlign: 'right', formatter: (c) => fmtInt(c.getValue()), sorter: 'number' },
    ],
  });

  // UI wiring
  el('#btnReload')?.addEventListener('click', () => table.replaceData());
  el('#pageSize')?.addEventListener('change', (e) => {
    table.setPageSize(parseInt(e.target.value, 10) || 25);
    table.setPage(1);
  });

  let tSearch = null;
  el('#q')?.addEventListener('input', () => {
    clearTimeout(tSearch);
    tSearch = setTimeout(() => {
      table.setPage(1);
      table.replaceData();
    }, 250);
  });

  el('#btnYahoo')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    const old = btn.textContent;
    btn.textContent = 'Updating...';

    try {
      const res = await post(cfg.yahooUrl, {});
      toast(res.message || 'OK');
    } catch (err) {
      toast('FAILED: ' + err.message);
    } finally {
      btn.disabled = false;
      btn.textContent = old;
    }
  });
})();
