(function () {
  const endpoint = window.__SCREENER__?.endpoints?.buylist;
  const $ = (q) => document.querySelector(q);

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

    $('#kpi-buy').textContent = String(buy);
    $('#kpi-wait').textContent = String(wait);
    $('#kpi-skip').textContent = String(skip);
    $('#kpi-stale').textContent = String(stale);
    $('#kpi-all').textContent = String(allRows.length);
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
    $('#kpi-selected').textContent = row?.ticker ? row.ticker : '—';

    $('#p-ticker').textContent = fmt(row?.ticker);
    $('#p-badges').innerHTML =
      badge(clsStatus(row?.status), row?.status, row?.status) +
      badge(clsSignal(row?.signalName), row?.signalName, row?.signalName) +
      badge(clsVol(row?.volumeLabelName), row?.volumeLabelName, row?.volumeLabelName);

    $('#p-last').textContent = fmt(row?.last);
    $('#p-rank').textContent = fmt(row?.rank);
    $('#p-entry').textContent = fmt(row?.entry);
    $('#p-rr').textContent = fmt(row?.rr);
    $('#p-sl').textContent = fmt(row?.sl);
    $('#p-tp').textContent = fmt(row?.tp);

    $('#p-reason').textContent = fmt(row?.reason);
    $('#p-snapshot').textContent = fmt(row?.snapshot_at);
    $('#p-lastbar').textContent = fmt(row?.last_bar_at);

    $('#p-json').textContent = row ? JSON.stringify(row, null, 2) : '—';
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
      rowClick: (_, row) => {
        row.select();
        renderPanel(row.getData());
        if (window.innerWidth < 1024) openDrawer();
      },
      rowTap: (_, row) => { // buat touch / klik yang kadang miss
        row.select();
        renderPanel(row.getData());
        if (window.innerWidth < 1024) openDrawer();
      },
      cellClick: (_, cell) => { // klik badge/sel tetap kebaca
        const row = cell.getRow();
        row.select();
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

    $('#meta-buy').textContent = `${buyFiltered.length} rows`;
    $('#meta-all').textContent = `${allFiltered.length} rows`;
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
    $('#meta-server').textContent = 'Loading…';

    const { meta, rows, recoRows, note } = await fetchData();

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

    $('#meta-server').textContent = `Server: ${meta.today ?? '-'} • EOD: ${meta.eodDate ?? '-'}`;
  }

  function startAuto() {
    stopAuto();
    const sec = parseInt($('#auto-interval').value || '60', 10);
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
    $('#btn-refresh').addEventListener('click', () => refresh().catch(console.error));

    // auto refresh
    $('#auto-refresh').addEventListener('change', (e) => e.target.checked ? startAuto() : stopAuto());
    $('#auto-interval').addEventListener('change', () => $('#auto-refresh').checked ? startAuto() : null);

    // search
    const search = $('#global-search');
    search.addEventListener('input', () => {
      state.search = search.value || '';
      applyTables();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === '/') { e.preventDefault(); search.focus(); }
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
