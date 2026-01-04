(function () {
  const endpoint = window.__SCREENER__?.endpoints?.buylist;
  const $ = (q) => document.querySelector(q);

  // --- SAFE DOM helpers ---
  function el(q) { try { return document.querySelector(q); } catch (_) { return null; } }
  function setText(q, v) {
    const n = el(q);
    if (!n) return false;
    n.textContent = (v === null || v === undefined) ? '' : String(v);
    return true;
  }
  function setHtml(q, v) {
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
    capital: null, // integer (tanpa ribuan)
  };

  // -------------------------
  // Formatting & Utils
  // -------------------------
  function escapeHtml(s) {
    return (s ?? '').toString()
      .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function fmt(v) {
    if (v === null || v === undefined || v === '') return '—';
    return String(v);
  }

  function pretty(v) {
    if (v === null || v === undefined) return '—';
    return String(v).replaceAll('_', ' ');
  }

  // Harga saham IDX: default tanpa desimal, tapi kalau data punya pecahan, tampilkan max 4.
  function fmtPx(v) {
    if (v === null || v === undefined || v === '') return '—';
    const s = String(v);
    const n = Number(s);
    if (!Number.isFinite(n)) return '—';
    const hasFrac = s.includes('.') && !/^\d+\.0+$/.test(s);
    const maxFrac = hasFrac ? 4 : 0;
    return n.toLocaleString('id-ID', { maximumFractionDigits: maxFrac });
  }

  function fmtInt(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) return '—';
    return Math.round(n).toLocaleString('id-ID', { maximumFractionDigits: 0 });
  }

  function fmt2(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) return '—';
    return n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
  }

  function fmtPct(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) return '—';
    return `${n.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
  }

  function badge(cls, text, title = null) {
    const raw = (text ?? '—').toString();
    const disp = raw.replaceAll('_', ' ');
    const tt = (title === null || title === undefined || title === '') ? '' :
      ` title="${escapeHtml((title ?? raw).toString().replaceAll('_', ' '))}"`;

    return `
      <span class="inline-flex items-center justify-center whitespace-nowrap
                   px-2.5 py-1 text-xs font-semibold leading-none
                   rounded-md align-middle ${cls}"${tt}>
        ${escapeHtml(disp)}
      </span>
    `;
  }

  function firstVal(obj, keys) {
    for (const k of keys) {
      const v = obj?.[k];
      if (v !== null && v !== undefined && v !== '') return v;
    }
    return null;
  }

  // -------------------------
  // CSS class mapping
  // -------------------------
  function clsStatus(st) {
    const map = {
      BUY_OK: 'bg-emerald-700 text-white',
      BUY_PULLBACK: 'bg-emerald-700 text-white',

      WAIT: 'bg-amber-500 text-white',
      WAIT_ENTRY_WINDOW: 'bg-amber-500 text-white',
      WAIT_PULLBACK: 'bg-amber-500 text-white',
      WAIT_REL_VOL: 'bg-amber-500 text-white',
      WAIT_STRENGTH: 'bg-amber-500 text-white',
      WAIT_EOD_GUARD: 'bg-amber-500 text-white',
      WAIT_CALENDAR: 'bg-amber-500 text-white',

      CAPITAL_TOO_SMALL: 'bg-rose-600 text-white',
      RR_TOO_LOW: 'bg-rose-600 text-white',
      RISK_TOO_WIDE: 'bg-rose-600 text-white',
      NO_INTRADAY: 'bg-rose-600 text-white',
      STALE_INTRADAY: 'bg-rose-600 text-white',

      SKIP_BAD_INTRADAY: 'bg-slate-700 text-white',
      SKIP_BREAKDOWN: 'bg-slate-700 text-white',
      SKIP_EOD_GUARD: 'bg-slate-700 text-white',
      SKIP_FEE_DRAG: 'bg-slate-700 text-white',
      SKIP_DAY_FRIDAY: 'bg-slate-700 text-white',
      SKIP_DAY_THURSDAY_LATE: 'bg-slate-700 text-white',

      LATE_ENTRY: 'bg-gray-600 text-white',
      LUNCH_WINDOW: 'bg-gray-600 text-white',
      EXPIRED: 'bg-gray-600 text-white',
    };
    return map[st] || 'bg-slate-500 text-white';
  }

  function clsSignal(s) {
    const map = {
      'Layak Beli': 'bg-emerald-600 text-white',
      'Perlu Konfirmasi': 'bg-sky-600 text-white',
      'Hati - Hati': 'bg-amber-500 text-white',
      'Hindari': 'bg-rose-600 text-white',
      'False Breakout / Batal': 'bg-slate-600 text-white',
      'Unknown': 'bg-slate-500 text-white',
    };
    return map[s] || 'bg-slate-500 text-white';
  }

  function clsVol(v) {
    const map = {
      'Strong Burst / Breakout': 'bg-emerald-700 text-white',
      'Volume Burst / Accumulation': 'bg-emerald-600 text-white',
      'Early Interest': 'bg-sky-600 text-white',

      'Normal': 'bg-slate-600 text-white',
      'Quiet': 'bg-slate-500 text-white',
      'Quiet/Normal – Volume lemah': 'bg-slate-500 text-white',
      'Dormant': 'bg-slate-400 text-white',
      'Ultra Dry': 'bg-slate-300 text-slate-900',

      'Climax / Euphoria': 'bg-orange-600 text-white',
      'Climax / Euphoria – hati-hati': 'bg-orange-500 text-white',
    };
    return map[v] || 'bg-slate-500 text-white';
  }

  // -------------------------
  // Capital input: display ribuan, send integer
  // -------------------------
  function parseCapital(str) {
    const raw = (str ?? '').toString().replace(/[^\d]/g, '');
    if (!raw) return null;
    const n = parseInt(raw, 10);
    return Number.isFinite(n) && n > 0 ? n : null;
  }
  function formatCapital(n) {
    if (!n) return '';
    return n.toLocaleString('id-ID');
  }
  function readCapital() {
    const n = el('#capital-input');
    return parseCapital(n?.value ?? '');
  }
  function writeCapital(v) {
    const n = el('#capital-input');
    if (!n) return;
    const num = (v === null || v === undefined || v === '') ? null : Number(v);
    n.value = num ? formatCapital(Math.round(num)) : '';
  }
  function formatWithCaret(inputEl, formatFn, parseFn) {
    if (!inputEl) return;

    const oldValue = inputEl.value ?? '';
    const oldPos = inputEl.selectionStart ?? oldValue.length;

    // Hitung berapa digit yang ada di kiri cursor (sebelum format)
    const leftPart = oldValue.slice(0, oldPos);
    const digitsLeft = (leftPart.match(/\d/g) || []).length;

    // Parse -> format
    const n = parseFn(oldValue);              // integer atau null
    const newValue = formatFn(n);             // string dengan ribuan atau ''

    inputEl.value = newValue;

    // Set cursor ke posisi setelah digitLeft digit di string baru
    if (!newValue) {
      try { inputEl.setSelectionRange(0, 0); } catch (_) {}
      return;
    }

    let pos = 0;
    let seen = 0;

    while (pos < newValue.length) {
      if (/\d/.test(newValue[pos])) {
        seen++;
        if (seen >= digitsLeft) { pos++; break; }
      }
      pos++;
    }

    // Kalau digitsLeft lebih besar dari digit yang ada, taruh di akhir
    if (seen < digitsLeft) pos = newValue.length;

    try { inputEl.setSelectionRange(pos, pos); } catch (_) {}
  }

  // -------------------------
  // Status grouping
  // -------------------------
  function isBuy(st) { return st === 'BUY_OK' || st === 'BUY_PULLBACK'; }
  function isWait(st) { return (st || '').startsWith('WAIT'); }
  function isSkip(st) { return (st || '').startsWith('SKIP') || st === 'LATE_ENTRY' || st === 'LUNCH_WINDOW' || st === 'EXPIRED'; }
  function isStale(st) { return st === 'STALE_INTRADAY' || st === 'NO_INTRADAY'; }

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

  // -------------------------
  // Note renderer (badge token)
  // -------------------------
  function renderNoteWithStatusBadges(note) {
    const s = (note ?? '').toString();
    if (!s.trim()) return '';

    const re = /\b[A-Z][A-Z0-9_]{2,}\b/g;
    let out = '';
    let last = 0;

    const matches = [...s.matchAll(re)];
    for (const m of matches) {
      const token = m[0];
      const idx = m.index ?? 0;

      out += escapeHtml(s.slice(last, idx));

      const cls = clsStatus(token);
      // Kalau token dikenal (selain fallback default), jadikan badge
      if (cls && cls !== 'bg-slate-500 text-white') {
        out += badge(cls, token.replaceAll('_', ' '));
      } else {
        out += escapeHtml(token.replaceAll('_', ' '));
      }

      last = idx + token.length;
    }

    out += escapeHtml(s.slice(last));
    return out;
  }

  // -------------------------
  // OHLC resolution
  // -------------------------
  function resolveOHLC(row) {
    const o_i = firstVal(row, ['open_price', 'i_open', 'intraday_open']);
    const h_i = firstVal(row, ['high_price', 'i_high', 'intraday_high']);
    const l_i = firstVal(row, ['low_price', 'i_low', 'intraday_low']);
    const c_i = firstVal(row, ['last_price', 'intraday_close', 'i_close']);

    const intradayOk = (o_i !== null || h_i !== null || l_i !== null) && (c_i !== null);
    if (intradayOk) return { src: 'Intraday', o: o_i, h: h_i, l: l_i, c: c_i };

    return {
      src: 'EOD',
      o: firstVal(row, ['open']),
      h: firstVal(row, ['high']),
      l: firstVal(row, ['low']),
      c: firstVal(row, ['close']),
    };
  }

  function setPanelLogo(logoUrl, ticker) {
    const img = el('#p-logo-img');
    const fb = el('#p-logo-fallback');

    const t = (ticker ?? '').toString().trim().toUpperCase();
    if (fb) fb.textContent = (t[0] || '?');

    if (!img || !fb) return;

    const url = (logoUrl ?? '').toString().trim();
    if (!url) {
      img.classList.add('hidden');
      fb.classList.remove('hidden');
      return;
    }

    img.onload = () => { img.classList.remove('hidden'); fb.classList.add('hidden'); };
    img.onerror = () => { img.classList.add('hidden'); fb.classList.remove('hidden'); };
    img.src = url;
  }

  // -------------------------
  // Data normalization
  // -------------------------
  function normalizeRow(r) {
    return {
      ...r,
      ticker: r.ticker || r.ticker_code || r.symbol,
      last: r.last ?? r.last_price ?? r.close ?? r.price,
      reason: r.reason ?? r.reason_text ?? r.notes,
      snapshot_at: r.snapshot_at ?? r.snapshotAt,
      last_bar_at: r.last_bar_at ?? r.lastBarAt,
      rank: r.rank ?? r.rank_score ?? r.rankScore,
      signalName: r.signalName ?? r.signal_name ?? r.signal,
      volumeLabelName: r.volumeLabelName ?? r.volume_label_name ?? r.volume_label,
    };
  }

  // -------------------------
  // Fetch
  // -------------------------
  async function fetchData() {
    if (!endpoint) throw new Error('Missing endpoint buylist');

    const url = new URL(endpoint, window.location.origin);
    if (state.capital) url.searchParams.set('capital', String(state.capital));

    const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();

    const rows = (json.rows || []).map(normalizeRow);

    const picks = (json.reco && (json.reco.picks || json.reco.rows || json.reco)) || [];
    const note = (json.reco && (json.reco.note || json.reco.message)) || null;

    let recoRows = [];
    if (Array.isArray(picks) && picks.length && typeof picks[0] === 'object') {
      recoRows = picks.map(normalizeRow);
    } else {
      const set = new Set((picks || []).map(String));
      recoRows = rows.filter(r => set.has(String(r.ticker)));
    }

    return { meta: json, rows, recoRows, note };
  }

  // -------------------------
  // KPI
  // -------------------------
  function setKpiCounts(allRows) {
    setText('#kpi-buy', String(allRows.filter(r => isBuy(r.status)).length));
    setText('#kpi-wait', String(allRows.filter(r => isWait(r.status)).length));
    setText('#kpi-skip', String(allRows.filter(r => isSkip(r.status)).length));
    setText('#kpi-stale', String(allRows.filter(r => isStale(r.status)).length));
    setText('#kpi-all', String(allRows.length));
  }

  function paintKpiActive() {
    document.querySelectorAll('.kpi').forEach(btn => {
      const on = btn.getAttribute('data-kpi') === state.kpiFilter;
      btn.classList.toggle('ring-2', on);
      btn.classList.toggle('ring-primary', on);
    });
  }

  // -------------------------
  // Panel render
  // -------------------------
  function renderPanel(row) {
    state.selected = row || null;
    setText('#kpi-selected', row?.ticker ? row.ticker : '—');

    const ticker = row?.ticker ?? row?.ticker_code ?? '—';
    const name = row?.company_name ?? row?.companyName ?? '—';
    const priceRaw = row?.last_price ?? row?.last ?? row?.close ?? null;

    setText('#p-ticker', fmt(ticker));
    setText('#p-name', fmt(name));
    setText('#p-price', fmtPx(priceRaw));

    const url = row?.logoUrl || row?.logo_url || row?.logo || row?.company_logo || null;
    setPanelLogo(url, ticker);

    const ohlc = resolveOHLC(row);
    setText('#p-ohlc-src', ohlc.src ? `(${ohlc.src})` : '—');
    setText('#p-o', fmtPx(ohlc.o));
    setText('#p-h', fmtPx(ohlc.h));
    setText('#p-l', fmtPx(ohlc.l));
    setText('#p-c', fmtPx(ohlc.c));

    setHtml('#p-badges',
      badge(clsStatus(row?.status), pretty(row?.status), row?.status) +
      badge(clsSignal(row?.signalName), pretty(row?.signalName), row?.signalName) +
      badge(clsVol(row?.volumeLabelName), pretty(row?.volumeLabelName), row?.volumeLabelName)
    );

    // Rank + Reason + timestamps
    setText('#p-rank', fmt(row?.rank));
    setText('#p-reason', fmt(row?.reason));
    setText('#p-snapshot', fmt(row?.snapshot_at));
    setText('#p-lastbar', fmt(row?.last_bar_at));

    // JSON
    setText('#p-json', row ? JSON.stringify(row, null, 2) : '—');

    // Market
    setText('#p-relvol', fmt2(firstVal(row, ['relvol_today', 'relvol', 'vol_ratio'])));
    setText('#p-pos', fmtPct(firstVal(row, ['pos_in_range', 'pos_pct', 'pos'])));
    setText('#p-eodlow', fmtInt(firstVal(row, ['eod_low'])));
    setText('#p-priceok', String(firstVal(row, ['price_ok']) ?? '—').replaceAll('_', ' '));

    // Plan
    setText('#p-entry', fmtInt(firstVal(row, ['entry'])));
    setText('#p-steps', String(firstVal(row, ['buy_steps', 'steps', 'buySteps']) ?? '—'));
    setText('#p-sl', fmtInt(firstVal(row, ['sl'])));
    setText('#p-tp1', fmtInt(firstVal(row, ['tp1', 'tp_1'])));
    setText('#p-tp2', fmtInt(firstVal(row, ['tp2', 'tp_2', 'tp2_price'])));
    setText('#p-be', fmtInt(firstVal(row, ['be', 'break_even', 'breakEven'])));
    setText('#p-out', fmtInt(firstVal(row, ['out', 'out_buy_fee', 'out_buyfee'])));
    setText('#p-lots', String(firstVal(row, ['lots']) ?? '—'));
    setText('#p-cost', fmtInt(firstVal(row, ['est_cost', 'estCost', 'cost_est'])));

    // Risk/Result
    setText('#p-rr', fmt2(firstVal(row, ['rr'])));
    setText('#p-risk', fmtPct(firstVal(row, ['risk_pct', 'risk_percent', 'risk'])));
    setText('#p-profit2', fmtInt(firstVal(row, ['profit_tp2_net', 'profit2_net', 'profit_tp2'])));
    setText('#p-rr2net', fmt2(firstVal(row, ['rr_tp2_net', 'rr2_net'])));
    setText('#p-rr2', fmt2(firstVal(row, ['rr_tp2', 'rr2'])));

    renderDrawer(row);
  }

  function renderDrawer(row) {
    // kalau drawer markup tidak ada / tidak lengkap, jangan crash
    const required = ['#d-ticker', '#d-badges', '#d-rank', '#d-entry', '#d-rr', '#d-sl', '#d-tp', '#d-reason', '#d-json'];
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

  // -------------------------
  // Tables
  // -------------------------
  let tblBuy = null;
  let tblAll = null;

  // --- Tabulator safety: avoid renderer=null crash (race / DOM replaced) ---
  function isTableAlive(t) {
    return !!(t && t.element && document.body.contains(t.element));
  }

  function waitTableBuilt(t) {
    if (!t) return Promise.reject(new Error('Tabulator: table is null'));
    if (t.__built) return Promise.resolve();
    return new Promise((resolve) => {
      try {
        t.on('tableBuilt', () => {
          t.__built = true;
          resolve();
        });
      } catch (_) {
        // if event hook fails, just resolve (won't block app)
        t.__built = true;
        resolve();
      }
    });
  }

  async function ensureTablesReady() {
    // called from refresh/apply to prevent "verticalFillMode" / renderer null
    if (!tblBuy || !tblAll) return false;
    if (!isTableAlive(tblBuy) || !isTableAlive(tblAll)) return false;
    await Promise.all([waitTableBuilt(tblBuy), waitTableBuilt(tblAll)]);
    return true;
  }

  function makeTable(containerEl, height) {
    const t = new Tabulator(containerEl, {
      layout: 'fitDataFill',
      height: height || '520px',
      selectableRows: false,
      rowHeight: 44,
      placeholder: 'No data',
      columnDefaults: { headerSort: false, resizable: false },
      columns: [
        {
          title: 'Ticker', field: 'ticker', width: 160,
          formatter: (c) => {
            const t = (c.getValue() ?? '').toString();
            const logo = (c.getRow().getData()?.logoUrl ?? '').toString();
            const img = logo
              ? `<img src="${logo}" class="w-6 h-6 rounded-full" onerror="this.style.display='none'">`
              : `<div class="w-6 h-6 rounded-full bg-primary/10 text-primary grid place-items-center text-xs font-bold">${(t.slice(0, 1) || '?')}</div>`;
            return `<div class="flex items-center gap-2">${img}<span class="font-semibold">${escapeHtml(t)}</span></div>`;
          },
        },
        {
          title: 'Status', field: 'status', width: 190,
          formatter: (c) => badge(clsStatus(c.getValue()), c.getValue(), c.getValue()),
        },
        {
          title: 'Signal', field: 'signalName', minWidth: 170,
          formatter: (c) => badge(clsSignal(c.getValue()), c.getValue(), c.getValue()),
        },
        {
          title: 'Vol', field: 'volumeLabelName', minWidth: 220, widthGrow: 1,
          formatter: (c) => badge(clsVol(c.getValue()), c.getValue(), c.getValue()),
        },
        {
          title: 'Rank', field: 'rank', width: 90, hozAlign: 'right',
          formatter: (c) => {
            const v = c.getValue();
            return (v === null || v === undefined || v === '') ? '—' : String(v);
          },
        },
      ],
    });

    // mark built as soon as Tabulator finishes init
    t.on('tableBuilt', () => { t.__built = true; });

    t.on("rowClick", function (_, row) {
      renderPanel(row.getData());
      if (window.innerWidth < 1024) openDrawer();
    });

    t.on("cellClick", function (_, cell) {
      const row = cell.getRow();
      renderPanel(row.getData());
      if (window.innerWidth < 1024) openDrawer();
    });

    return t;
  }

  function selectFirstIfNeeded() {
    if (state.selected?.ticker) return;
    const src = state.recoRows.length ? state.recoRows : state.rows;
    if (src.length) renderPanel(src[0]);
  }

  async function applyTables() {
    // Guard: Tabulator can throw renderer=null if replaceData runs too early / element detached
    const ready = await ensureTablesReady();
    if (!ready) return;

    const buyFiltered = applyClientFilter(state.recoRows || []);
    const allFiltered = applyClientFilter(state.rows || []);

    try {
      tblBuy.replaceData(buyFiltered);
      tblAll.replaceData(allFiltered);
    } catch (e) {
      console.error('Tabulator replaceData failed; trying redraw+setData', e);
      try {
        tblBuy.redraw(true);
        tblAll.redraw(true);
        tblBuy.setData(buyFiltered);
        tblAll.setData(allFiltered);
      } catch (e2) {
        console.error('Tabulator fallback setData failed', e2);
        return;
      }
    }

    setText('#meta-buy', `${buyFiltered.length} rows`);
    setText('#meta-all', `${allFiltered.length} rows`);
  }

  // -------------------------
  // Refresh cycle
  // -------------------------
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
    if (noteEl && note && String(note).trim()) {
      noteEl.style.display = '';
      noteEl.innerHTML = renderNoteWithStatusBadges(note);
    } else if (noteEl) {
      noteEl.style.display = 'none';
      noteEl.innerHTML = '';
    }

    await applyTables();
    selectFirstIfNeeded();

    const eod = meta.eodDate ?? meta.eod_date ?? meta.eodDateStr ?? '-';
    setText('#meta-server', `Server: ${meta.today ?? '-'} • EOD: ${eod}`);
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

  // -------------------------
  // UI wiring
  // -------------------------
  function wireUI() {
    tblBuy = makeTable(document.getElementById('tbl-buy'), '200px');
    tblAll = makeTable(document.getElementById('tbl-all'), '560px');

    // refresh
    const btnRefresh = el('#btn-refresh');
    if (btnRefresh) btnRefresh.addEventListener('click', () => refresh().catch(console.error));

    // capital (persist + re-fetch)
    const capIn = el('#capital-input');
    const capApply = el('#btn-apply-capital');

    // load from localStorage
    try {
      const saved = localStorage.getItem('screener.capital');
      if (saved && !readCapital()) {
        state.capital = parseCapital(saved);
        writeCapital(state.capital);
      } else {
        // keep state.capital in sync with input initial
        state.capital = readCapital();
      }
    } catch (_) { /* ignore */ }

    // live format while typing
    if (capIn) {
      capIn.addEventListener('input', () => {
        formatWithCaret(capIn, formatCapital, parseCapital);
        state.capital = readCapital(); // integer bersih
      });

      // optional: biar paste “5.000.000” / “5000000” tetap aman
      capIn.addEventListener('paste', () => {
        requestAnimationFrame(() => {
          formatWithCaret(capIn, formatCapital, parseCapital);
          state.capital = readCapital();
        });
      });
    }

    function applyCapital() {
      state.capital = readCapital();
      try {
        if (state.capital) localStorage.setItem('screener.capital', String(state.capital));
        else localStorage.removeItem('screener.capital');
      } catch (_) { /* ignore */ }
      refresh().catch(console.error);
    }

    if (capApply) capApply.addEventListener('click', applyCapital);
    if (capIn) capIn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); applyCapital(); }
    });

    // auto refresh
    const autoRefresh = el('#auto-refresh');
    if (autoRefresh) {
      autoRefresh.addEventListener('change', (e) => e.target.checked ? startAuto() : stopAuto());

      // FIX: setelah klik pakai mouse/touch, hilangkan fokus biar toggle gak kelihatan "stuck"
      autoRefresh.addEventListener('pointerup', (e) => {
        if (e && e.pointerType) autoRefresh.blur();
      });
    }
    const autoInterval = el('#auto-interval');
    if (autoInterval) autoInterval.addEventListener('change', () => (el('#auto-refresh') && el('#auto-refresh').checked) ? startAuto() : null);

    // search
    const search = el('#global-search');
    if (search) search.addEventListener('input', () => {
      state.search = search.value || '';
      applyTables().catch(console.error);
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === '/') { e.preventDefault(); if (search) search.focus(); }
      if (e.key === 'Escape') closeDrawer();
    });

    // KPI filters
    document.querySelectorAll('.kpi').forEach(btn => {
      btn.addEventListener('click', () => {
        state.kpiFilter = btn.getAttribute('data-kpi') || 'ALL';
        paintKpiActive();
        applyTables().catch(console.error);
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
        try { await navigator.clipboard.writeText(text); } catch (_) { /* ignore */ }
      });
    }

    // intraday update (GET intraday/capture?ticker=...)
    const btnUpd = el('#btn-update-intraday');
    if (btnUpd) {
      btnUpd.addEventListener('click', async () => {
        const t = state?.selected?.ticker || state?.selected?.ticker_code || null;
        if (!t) { alert('Pilih ticker dulu.'); return; }

        const url = new URL('intraday/capture', window.location.origin);
        url.searchParams.set('ticker', t);

        try {
          btnUpd.disabled = true;

          const res = await fetch(url.toString(), { method: 'GET' });
          if (!res.ok) throw new Error(`HTTP ${res.status}`);

          // langsung refresh supaya tabel/panel ikut update
          await refresh();

        } catch (e) {
          console.error(e);
          alert('Gagal update intraday.');
        } finally {
          btnUpd.disabled = false;
        }
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
    // Wait Tabulator fully built to avoid renderer=null during first refresh
    ensureTablesReady().then(() => refresh().catch(console.error)).catch(() => refresh().catch(console.error));
  });
})();
