(function () {
  const endpoint = window.__SCREENER__?.endpoints?.buylist;
  const $ = (q) => document.querySelector(q);

// --- SAFE DOM helpers (tahan banting) ---
function el(q){ try { return document.querySelector(q); } catch(_) { return null; } }
function setText(q, v){
  const n = el(q);
  if (!n) return false;
  n.textContent = (v === null || v === undefined) ? '' : String(v);
  return true;
}
function setHtml(q, v){
  const n = el(q);
  if (!n) return false;
  n.innerHTML = (v === null || v === undefined) ? '' : String(v);
  return true;
}

  const state = {
    rows: [],
    recoRows: [],
    selected: null,
    kpiFilter: 'ALL',
    search: '',
    timer: null,
  };

  function fmt(v) {
    if (v === null || v === undefined || v === '') return '—';
    return String(v);
  }

  function pretty(v) {
    if (v === null || v === undefined) return '—';
    const s = String(v);
    return s.replaceAll('_', ' ');
  }


  function badge(cls, text, title) {
    const t = title ? ` title="${String(title).replace(/"/g, '&quot;')}"` : '';
    return `<span class="badge ${cls}"${t}>${fmt(text)}</span>`;
  }

  function clsStatus(st) {
    const map = {
      BUY_OK: 'badge-success',
      BUY_PULLBACK: 'badge-success',
      WAIT: 'badge-warning',
      WAIT_ENTRY_WINDOW: 'badge-warning',
      WAIT_PULLBACK: 'badge-warning',
      WAIT_REL_VOL: 'badge-warning',
      WAIT_STRENGTH: 'badge-warning',
      WAIT_EOD_GUARD: 'badge-warning',
      WAIT_CALENDAR: 'badge-warning',
      CAPITAL_TOO_SMALL: 'badge-error',
      RR_TOO_LOW: 'badge-error',
      RISK_TOO_WIDE: 'badge-error',
      NO_INTRADAY: 'badge-error',
      STALE_INTRADAY: 'badge-error',
      SKIP_BAD_INTRADAY: 'badge-neutral',
      SKIP_BREAKDOWN: 'badge-neutral',
      SKIP_EOD_GUARD: 'badge-neutral',
      SKIP_FEE_DRAG: 'badge-neutral',
      SKIP_DAY_FRIDAY: 'badge-neutral',
      SKIP_DAY_THURSDAY_LATE: 'badge-neutral',
      LATE_ENTRY: 'badge-ghost',
      LUNCH_WINDOW: 'badge-ghost',
      EXPIRED: 'badge-ghost',
    };
    return map[st] || 'badge-outline';
  }

  function clsSignal(s) {
    const map = {
      'Layak Beli': 'badge-outline badge-success',
      'Perlu Konfirmasi': 'badge-outline badge-warning',
      'Hati - Hati': 'badge-outline badge-warning',
      'Hindari': 'badge-outline badge-error',
      'False Breakout / Batal': 'badge-outline badge-neutral',
    };
    return map[s] || 'badge-outline';
  }

  function clsVol(v) {
    const map = {
      'Strong Burst / Breakout': 'badge-success',
      'Volume Burst / Accumulation': 'badge-success',
      'Early Interest': 'badge-info',
      'Normal': 'badge-neutral',
      'Quiet': 'badge-neutral',
      'Quiet/Normal – Volume lemah': 'badge-neutral',
      'Dormant': 'badge-outline',
      'Ultra Dry': 'badge-outline',
      'Climax / Euphoria': 'badge-warning',
      'Climax / Euphoria – hati-hati': 'badge-warning',
    };
    return map[v] || 'badge-outline';
  }

  function isBuy(st) {
    return st === 'BUY_OK' || st === 'BUY_PULLBACK';
  }
  function isWait(st) {
    return (st || '').startsWith('WAIT');
  }
  function isSkip(st) {
    return (st || '').startsWith('SKIP') || st === 'LATE_ENTRY' || st === 'LUNCH_WINDOW' || st === 'EXPIRED';
  }
  function isStale(st) {
    return st === 'STALE_INTRADAY' || st === 'NO_INTRADAY';
  }

  function passKpi(row) {
    const st = row.status;
    if (state.kpiFilter === 'ALL') return true;
    if (state.kpiFilter === 'BUY') return isBuy(st);
    if (state.kpiFilter === 'WAIT') return isWait(st);
    if (state.kpiFilter === 'SKIP') return isSkip(st);
    if (state.kpiFilter === 'STALE') return isStale(st);
    return true;
  }

  function passSearch(row) {
    const q = (state.search || '').trim().toUpperCase();
    if (!q) return true;
    return String(row.ticker || '').toUpperCase().includes(q);
  }

  function applyClientFilter(rows) {
    return rows.filter((r) => passKpi(r) && passSearch(r));
  }

  async function fetchData() {
    const res = await fetch(endpoint, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();

    const rows = (json.rows || []).map((r) => ({
      ...r,
      ticker: r.ticker || r.ticker_code || r.symbol,
      last: r.last ?? r.last_price ?? r.close ?? r.price,
      reason: r.reason ?? r.reason_text ?? r.notes,
      snapshot_at: r.snapshot_at ?? r.snapshotAt,
      last_bar_at: r.last_bar_at ?? r.lastBarAt,
      rank: r.rank ?? r.rank_score ?? r.rankScore,
    }));

    const picks = (json.reco && (json.reco.picks || json.reco.rows || json.reco)) || [];
    const note = (json.reco && (json.reco.note || json.reco.message)) || null;

    let recoRows = [];
    if (Array.isArray(picks) && picks.length && typeof picks[0] === 'object') {
      recoRows = picks.map((r) => ({
        ...r,
        ticker: r.ticker || r.ticker_code || r.symbol,
        last: r.last ?? r.last_price ?? r.close ?? r.price,
        reason: r.reason ?? r.reason_text ?? r.notes,
        snapshot_at: r.snapshot_at ?? r.snapshotAt,
        last_bar_at: r.last_bar_at ?? r.lastBarAt,
        rank: r.rank ?? r.rank_score ?? r.rankScore,
      }));
    } else {
      const set = new Set((picks || []).map(String));
      recoRows = rows.filter(r => set.has(String(r.ticker)));
    }

    return { meta: json, rows, recoRows, note };
  }

  function setKpiCounts(allRows) {
    const buy = allRows.filter(r => isBuy(r.status)).length;
    const wait = allRows.filter(r => isWait(r.status)).length;
    const skip = allRows.filter(r => isSkip(r.status)).length;
    const stale = allRows.filter(r => isStale(r.status)).length;

    setText('#kpi-buy', String(buy));
    setText('#kpi-wait', String(wait));
    setText('#kpi-skip', String(skip));
    setText('#kpi-stale', String(stale));
    setText('#kpi-all', String(allRows.length));
  }

  function paintKpiActive() {
    document.querySelectorAll('.kpi').forEach(btn => {
      const on = btn.getAttribute('data-kpi') === state.kpiFilter;
      btn.classList.toggle('ring-2', on);
      btn.classList.toggle('ring-primary', on);
    });
  }

  function renderPanel(row) {
    state.selected = row || null;
    setText('#kpi-selected', row?.ticker ? row.ticker : '—');

    setText('#p-ticker', fmt(row?.ticker));
    const logo = $('#p-logo');
    if (logo) {
      const url = row?.logoUrl || row?.logo_url || row?.logo || row?.company_logo || null;
      if (url) { logo.src = url; logo.classList.remove('hidden'); }
      else { logo.classList.add('hidden'); }
    }
    setHtml('#p-badges',
      badge(clsStatus(row?.status), pretty(row?.status), row?.status) +
      badge(clsSignal(row?.signalName), pretty(row?.signalName), row?.signalName) +
      badge(clsVol(row?.volumeLabelName), pretty(row?.volumeLabelName), row?.volumeLabelName) 
    );

    setText('#p-last', fmt(row?.last));
    setText('#p-rank', fmt(row?.rank));
    setText('#p-entry', fmt(row?.entry));
    setText('#p-rr', fmt(row?.rr));
    setText('#p-sl', fmt(row?.sl));
    setText('#p-tp', fmt(row?.tp));

    setText('#p-reason', fmt(row?.reason));
    setText('#p-snapshot', fmt(row?.snapshot_at));
    setText('#p-lastbar', fmt(row?.last_bar_at));

    setText('#p-json', row ? JSON.stringify(row, null, 2) : '—');
    renderDrawer(row);
  }

  
  function renderDrawer(row) {
    // Mobile drawer uses d-* ids; kalau drawer markup tidak ada / tidak lengkap, jangan crash
    const required = ['#d-ticker','#d-badges','#d-last','#d-rank','#d-entry','#d-rr','#d-sl','#d-tp','#d-reason','#d-json'];
    for (const q of required) { if (!el(q)) return; }

    setText('#d-ticker', fmt(row?.ticker));
    setHtml('#d-badges',
      badge(clsStatus(row?.status), pretty(row?.status), row?.status) +
      badge(clsSignal(row?.signalName), pretty(row?.signalName), row?.signalName) +
      badge(clsVol(row?.volumeLabelName), pretty(row?.volumeLabelName), row?.volumeLabelName) 
    );

    setText('#d-last', fmt(row?.last));
    setText('#d-rank', fmt(row?.rank));
    setText('#d-entry', fmt(row?.entry));
    setText('#d-rr', fmt(row?.rr));
    setText('#d-sl', fmt(row?.sl));
    setText('#d-tp', fmt(row?.tp));
    setText('#d-reason', (row?.reason ?? '—').toString());
    setText('#d-json', JSON.stringify(row ?? {}, null, 2));
  }
function openDrawer() {
    const d = $('#drawer');
    if (d) d.classList.remove('hidden');
  }
  function closeDrawer() {
    const d = $('#drawer');
    if (d) d.classList.add('hidden');
  }

  let tblBuy = null;
  let tblAll = null;

  function makeTable(el, height) {
    return new Tabulator(el, {
      layout: 'fitColumns',
      height,
      selectable: 1,
      rowHeight: 44,
      placeholder: 'No data',
      columns: [
        {
          title: 'Ticker', field: 'ticker', width: 150,
          formatter: (c) => {
            const t = (c.getValue() ?? '').toString();
            const logo = (c.getRow().getData()?.logoUrl ?? '').toString(); // siap kalau nanti ada
            const img = logo
              ? `<img src="${logo}" class="w-6 h-6 rounded-full" onerror="this.style.display='none'">`
              : `<div class="w-6 h-6 rounded-full bg-primary/10 text-primary grid place-items-center text-xs font-bold">${t.slice(0,1) || '?'}</div>`;
            return `<div class="flex items-center gap-2">${img}<span class="font-semibold">${t}</span></div>`;
          },
        },
        {
          title: 'Status', field: 'status', width: 150,
          formatter: (c) => badge(clsStatus(c.getValue()), c.getValue(), c.getValue()),
        },
        {
          title: 'Signal', field: 'signalName', width: 170,
          formatter: (c) => badge(clsSignal(c.getValue()), c.getValue(), c.getValue()),
        },
        {
          title: 'Vol', field: 'volumeLabelName', width: 150,
          formatter: (c) => badge(clsVol(c.getValue()), c.getValue(), c.getValue()),
        },
        { title: 'Last', field: 'last', hozAlign: 'right' },
        { title: 'Entry', field: 'entry', hozAlign: 'right' },
        { title: 'SL', field: 'sl', hozAlign: 'right' },
        { title: 'TP', field: 'tp', hozAlign: 'right' },
        { title: 'RR', field: 'rr', hozAlign: 'right' },
        {
          title: 'Reason', field: 'reason', widthGrow: 2,
          formatter: (c) => (c.getValue() ?? '').toString().slice(0, 90),
        },
      ],
      // Fallback: kalau rowClick/cellClick tidak kepanggil karena overlay/formatter,
      // update panel lewat event selection (pasti kepanggil saat row ter-select).
      rowSelected: (row) => { try { renderPanel(row.getData()); } catch (e) {} },
      rowSelectionChanged: (data, rows) => {
        if (!rows || !rows.length) return;
        try { renderPanel(rows[0].getData()); } catch (e) {}
      },

      rowClick: (_, row) => {
        // Tabulator can throw if selectable is not enabled. Don't let it block panel render.
        try { row.select(); } catch (e) {}
        renderPanel(row.getData());
        if (window.innerWidth < 1024) openDrawer();
      },
      rowTap: (_, row) => { // touch / klik yang kadang miss
        try { row.select(); } catch (e) {}
        renderPanel(row.getData());
        if (window.innerWidth < 1024) openDrawer();
      },
      cellClick: (_, cell) => { // klik badge/sel tetap kebaca
        const row = cell.getRow();
        try { row.select(); } catch (e) {}
        renderPanel(row.getData());
        if (window.innerWidth < 1024) openDrawer();
      },
    });
  }

  function selectFirstIfNeeded() {
    if (state.selected?.ticker) return;
    const src = state.recoRows.length ? state.recoRows : state.rows;
    if (src.length) renderPanel(src[0]);
  }

  function applyTables() {
    const buyFiltered = applyClientFilter(state.recoRows);
    const allFiltered = applyClientFilter(state.rows);

    tblBuy.replaceData(buyFiltered);
    tblAll.replaceData(allFiltered);

    setText('#meta-buy', `${buyFiltered.length} rows`);
    setText('#meta-all', `${allFiltered.length} rows`);
  }

  function fmtTicker(cell){
    const t = cell.getValue() || '';
    const letter = t ? t[0] : '?';
    return `
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:26px;height:26px;border-radius:999px;background:rgba(37,99,235,.12);display:grid;place-items:center;font-weight:800;color:rgba(37,99,235,.95)">
          ${letter}
        </div>
        <div style="font-weight:700">${t}</div>
      </div>
    `;
  }

  async function refresh() {
    const metaEl = el('#meta-server');
    if (metaEl) metaEl.textContent = 'Loading…';

    let meta, rows, recoRows, note;
    try {
      ({ meta, rows, recoRows, note } = await fetchData());
    } catch (e) {
      console.error(e);
      if (metaEl) metaEl.textContent = 'Server: error (lihat console)';
      return;
    }

    state.rows = rows;
    state.recoRows = recoRows;

    setKpiCounts(rows);
    paintKpiActive();

    const noteEl = $('#reco-note');
    if (note && String(note).trim()) {
      noteEl.style.display = '';
      noteEl.textContent = String(note);
    } else {
      noteEl.style.display = 'none';
      noteEl.textContent = '';
    }

    applyTables();
    selectFirstIfNeeded();

    setText('#meta-server', `Server: ${meta.today ?? '-'} • EOD: ${meta.eodDate ?? '-'}`);
  }

  function startAuto() {
    stopAuto();
    const ai = el('#auto-interval');
    const sec = parseInt((ai ? ai.value : '60') || '60', 10);
    state.timer = setInterval(() => refresh().catch(console.error), sec * 1000);
  }
  function stopAuto() {
    if (state.timer) clearInterval(state.timer);
    state.timer = null;
  }

  function wireUI() {
    // tables init
    tblBuy = makeTable(document.getElementById('tbl-buy'), '200px');
    tblAll = makeTable(document.getElementById('tbl-all'), '560px');

    // refresh
    const btnRefresh = el('#btn-refresh');
    if (btnRefresh) btnRefresh.addEventListener('click', () => refresh().catch(console.error));

    // auto refresh
    const autoRefresh = el('#auto-refresh');
    if (autoRefresh) autoRefresh.addEventListener('change', (e) => e.target.checked ? startAuto() : stopAuto());
    const autoInterval = el('#auto-interval');
    if (autoInterval) autoInterval.addEventListener('change', () => (el('#auto-refresh') && el('#auto-refresh').checked) ? startAuto() : null);

    // search
    const search = el('#global-search');
    if (search) search.addEventListener('input', () => {
      state.search = search.value || '';
      applyTables();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === '/') { e.preventDefault(); if (search) search.focus(); }
      if (e.key === 'Escape') closeDrawer();
    });

    // KPI filter clicks
    document.querySelectorAll('.kpi').forEach(btn => {
      btn.addEventListener('click', () => {
        state.kpiFilter = btn.getAttribute('data-kpi') || 'ALL';
        paintKpiActive();
        applyTables();
      });
    });

    // panel copy
    const copyBtn = $('#btn-copy-plan');
    if (copyBtn) {
      copyBtn.addEventListener('click', async () => {
        if (!state.selected) return;
        const s = state.selected;
        const text =
          `${s.ticker}\n` +
          `Status: ${s.status}\n` +
          `Signal: ${s.signalName}\n` +
          `Vol: ${s.volumeLabelName}\n` +
          `Last: ${s.last}\n` +
          `Entry: ${s.entry} | SL: ${s.sl} | TP: ${s.tp} | RR: ${s.rr}\n` +
          `Reason: ${s.reason ?? ''}`;
        try { await navigator.clipboard.writeText(text); } catch (_) {}
      });
    }

    // mobile panel toggle
    const btnPanel = $('#btn-panel');
    if (btnPanel) btnPanel.addEventListener('click', openDrawer);

    // drawer close
    const back = $('#drawer-backdrop');
    const close = $('#drawer-close');
    if (back) back.addEventListener('click', closeDrawer);
    if (close) close.addEventListener('click', closeDrawer);
  }

  document.addEventListener('DOMContentLoaded', () => {
    wireUI();
    refresh().catch(console.error);
  });
})();