/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/css/app.css"
/*!*******************************!*\
  !*** ./resources/css/app.css ***!
  \*******************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./resources/js/screener/buylist.js"
/*!******************************************!*\
  !*** ./resources/js/screener/buylist.js ***!
  \******************************************/
() {

function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
function _regenerator() { /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/babel/babel/blob/main/packages/babel-helpers/LICENSE */ var e, t, r = "function" == typeof Symbol ? Symbol : {}, n = r.iterator || "@@iterator", o = r.toStringTag || "@@toStringTag"; function i(r, n, o, i) { var c = n && n.prototype instanceof Generator ? n : Generator, u = Object.create(c.prototype); return _regeneratorDefine2(u, "_invoke", function (r, n, o) { var i, c, u, f = 0, p = o || [], y = !1, G = { p: 0, n: 0, v: e, a: d, f: d.bind(e, 4), d: function d(t, r) { return i = t, c = 0, u = e, G.n = r, a; } }; function d(r, n) { for (c = r, u = n, t = 0; !y && f && !o && t < p.length; t++) { var o, i = p[t], d = G.p, l = i[2]; r > 3 ? (o = l === n) && (u = i[(c = i[4]) ? 5 : (c = 3, 3)], i[4] = i[5] = e) : i[0] <= d && ((o = r < 2 && d < i[1]) ? (c = 0, G.v = n, G.n = i[1]) : d < l && (o = r < 3 || i[0] > n || n > l) && (i[4] = r, i[5] = n, G.n = l, c = 0)); } if (o || r > 1) return a; throw y = !0, n; } return function (o, p, l) { if (f > 1) throw TypeError("Generator is already running"); for (y && 1 === p && d(p, l), c = p, u = l; (t = c < 2 ? e : u) || !y;) { i || (c ? c < 3 ? (c > 1 && (G.n = -1), d(c, u)) : G.n = u : G.v = u); try { if (f = 2, i) { if (c || (o = "next"), t = i[o]) { if (!(t = t.call(i, u))) throw TypeError("iterator result is not an object"); if (!t.done) return t; u = t.value, c < 2 && (c = 0); } else 1 === c && (t = i["return"]) && t.call(i), c < 2 && (u = TypeError("The iterator does not provide a '" + o + "' method"), c = 1); i = e; } else if ((t = (y = G.n < 0) ? u : r.call(n, G)) !== a) break; } catch (t) { i = e, c = 1, u = t; } finally { f = 1; } } return { value: t, done: y }; }; }(r, o, i), !0), u; } var a = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} t = Object.getPrototypeOf; var c = [][n] ? t(t([][n]())) : (_regeneratorDefine2(t = {}, n, function () { return this; }), t), u = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(c); function f(e) { return Object.setPrototypeOf ? Object.setPrototypeOf(e, GeneratorFunctionPrototype) : (e.__proto__ = GeneratorFunctionPrototype, _regeneratorDefine2(e, o, "GeneratorFunction")), e.prototype = Object.create(u), e; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, _regeneratorDefine2(u, "constructor", GeneratorFunctionPrototype), _regeneratorDefine2(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = "GeneratorFunction", _regeneratorDefine2(GeneratorFunctionPrototype, o, "GeneratorFunction"), _regeneratorDefine2(u), _regeneratorDefine2(u, o, "Generator"), _regeneratorDefine2(u, n, function () { return this; }), _regeneratorDefine2(u, "toString", function () { return "[object Generator]"; }), (_regenerator = function _regenerator() { return { w: i, m: f }; })(); }
function _regeneratorDefine2(e, r, n, t) { var i = Object.defineProperty; try { i({}, "", {}); } catch (e) { i = 0; } _regeneratorDefine2 = function _regeneratorDefine(e, r, n, t) { function o(r, n) { _regeneratorDefine2(e, r, function (e) { return this._invoke(r, n, e); }); } r ? i ? i(e, r, { value: n, enumerable: !t, configurable: !t, writable: !t }) : e[r] = n : (o("next", 0), o("throw", 1), o("return", 2)); }, _regeneratorDefine2(e, r, n, t); }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }
(function (_window$__SCREENER__) {
  var endpoint = (_window$__SCREENER__ = window.__SCREENER__) === null || _window$__SCREENER__ === void 0 || (_window$__SCREENER__ = _window$__SCREENER__.endpoints) === null || _window$__SCREENER__ === void 0 ? void 0 : _window$__SCREENER__.buylist;
  var $ = function $(q) {
    return document.querySelector(q);
  };
  var state = {
    rows: [],
    recoRows: [],
    selected: null,
    kpiFilter: 'ALL',
    search: '',
    timer: null
  };
  function fmt(v) {
    if (v === null || v === undefined || v === '') return '—';
    return String(v);
  }
  function badge(cls, text, title) {
    var t = title ? " title=\"".concat(String(title).replace(/"/g, '&quot;'), "\"") : '';
    return "<span class=\"badge ".concat(cls, "\"").concat(t, ">").concat(fmt(text), "</span>");
  }
  function clsStatus(st) {
    var map = {
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
      EXPIRED: 'badge-ghost'
    };
    return map[st] || 'badge-outline';
  }
  function clsSignal(s) {
    var map = {
      'Layak Beli': 'badge-outline badge-success',
      'Perlu Konfirmasi': 'badge-outline badge-warning',
      'Hati - Hati': 'badge-outline badge-warning',
      'Hindari': 'badge-outline badge-error',
      'False Breakout / Batal': 'badge-outline badge-neutral'
    };
    return map[s] || 'badge-outline';
  }
  function clsVol(v) {
    var map = {
      'Strong Burst / Breakout': 'badge-success',
      'Volume Burst / Accumulation': 'badge-success',
      'Early Interest': 'badge-info',
      'Normal': 'badge-neutral',
      'Quiet': 'badge-neutral',
      'Quiet/Normal – Volume lemah': 'badge-neutral',
      'Dormant': 'badge-outline',
      'Ultra Dry': 'badge-outline',
      'Climax / Euphoria': 'badge-warning',
      'Climax / Euphoria – hati-hati': 'badge-warning'
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
    var st = row.status;
    if (state.kpiFilter === 'ALL') return true;
    if (state.kpiFilter === 'BUY') return isBuy(st);
    if (state.kpiFilter === 'WAIT') return isWait(st);
    if (state.kpiFilter === 'SKIP') return isSkip(st);
    if (state.kpiFilter === 'STALE') return isStale(st);
    return true;
  }
  function passSearch(row) {
    var q = (state.search || '').trim().toUpperCase();
    if (!q) return true;
    return String(row.ticker || '').toUpperCase().includes(q);
  }
  function applyClientFilter(rows) {
    return rows.filter(function (r) {
      return passKpi(r) && passSearch(r);
    });
  }
  function fetchData() {
    return _fetchData.apply(this, arguments);
  }
  function _fetchData() {
    _fetchData = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee2() {
      var res, json, rows, picks, note, recoRows, set;
      return _regenerator().w(function (_context2) {
        while (1) switch (_context2.n) {
          case 0:
            _context2.n = 1;
            return fetch(endpoint, {
              headers: {
                Accept: 'application/json'
              }
            });
          case 1:
            res = _context2.v;
            if (res.ok) {
              _context2.n = 2;
              break;
            }
            throw new Error("HTTP ".concat(res.status));
          case 2:
            _context2.n = 3;
            return res.json();
          case 3:
            json = _context2.v;
            rows = (json.rows || []).map(function (r) {
              var _ref2, _ref3, _r$last, _ref4, _r$reason, _r$snapshot_at, _r$last_bar_at, _ref5, _r$rank;
              return _objectSpread(_objectSpread({}, r), {}, {
                ticker: r.ticker || r.ticker_code || r.symbol,
                last: (_ref2 = (_ref3 = (_r$last = r.last) !== null && _r$last !== void 0 ? _r$last : r.last_price) !== null && _ref3 !== void 0 ? _ref3 : r.close) !== null && _ref2 !== void 0 ? _ref2 : r.price,
                reason: (_ref4 = (_r$reason = r.reason) !== null && _r$reason !== void 0 ? _r$reason : r.reason_text) !== null && _ref4 !== void 0 ? _ref4 : r.notes,
                snapshot_at: (_r$snapshot_at = r.snapshot_at) !== null && _r$snapshot_at !== void 0 ? _r$snapshot_at : r.snapshotAt,
                last_bar_at: (_r$last_bar_at = r.last_bar_at) !== null && _r$last_bar_at !== void 0 ? _r$last_bar_at : r.lastBarAt,
                rank: (_ref5 = (_r$rank = r.rank) !== null && _r$rank !== void 0 ? _r$rank : r.rank_score) !== null && _ref5 !== void 0 ? _ref5 : r.rankScore
              });
            });
            picks = json.reco && (json.reco.picks || json.reco.rows || json.reco) || [];
            note = json.reco && (json.reco.note || json.reco.message) || null;
            recoRows = [];
            if (Array.isArray(picks) && picks.length && _typeof(picks[0]) === 'object') {
              recoRows = picks.map(function (r) {
                var _ref6, _ref7, _r$last2, _ref8, _r$reason2, _r$snapshot_at2, _r$last_bar_at2, _ref9, _r$rank2;
                return _objectSpread(_objectSpread({}, r), {}, {
                  ticker: r.ticker || r.ticker_code || r.symbol,
                  last: (_ref6 = (_ref7 = (_r$last2 = r.last) !== null && _r$last2 !== void 0 ? _r$last2 : r.last_price) !== null && _ref7 !== void 0 ? _ref7 : r.close) !== null && _ref6 !== void 0 ? _ref6 : r.price,
                  reason: (_ref8 = (_r$reason2 = r.reason) !== null && _r$reason2 !== void 0 ? _r$reason2 : r.reason_text) !== null && _ref8 !== void 0 ? _ref8 : r.notes,
                  snapshot_at: (_r$snapshot_at2 = r.snapshot_at) !== null && _r$snapshot_at2 !== void 0 ? _r$snapshot_at2 : r.snapshotAt,
                  last_bar_at: (_r$last_bar_at2 = r.last_bar_at) !== null && _r$last_bar_at2 !== void 0 ? _r$last_bar_at2 : r.lastBarAt,
                  rank: (_ref9 = (_r$rank2 = r.rank) !== null && _r$rank2 !== void 0 ? _r$rank2 : r.rank_score) !== null && _ref9 !== void 0 ? _ref9 : r.rankScore
                });
              });
            } else {
              set = new Set((picks || []).map(String));
              recoRows = rows.filter(function (r) {
                return set.has(String(r.ticker));
              });
            }
            return _context2.a(2, {
              meta: json,
              rows: rows,
              recoRows: recoRows,
              note: note
            });
        }
      }, _callee2);
    }));
    return _fetchData.apply(this, arguments);
  }
  function setKpiCounts(allRows) {
    var buy = allRows.filter(function (r) {
      return isBuy(r.status);
    }).length;
    var wait = allRows.filter(function (r) {
      return isWait(r.status);
    }).length;
    var skip = allRows.filter(function (r) {
      return isSkip(r.status);
    }).length;
    var stale = allRows.filter(function (r) {
      return isStale(r.status);
    }).length;
    $('#kpi-buy').textContent = String(buy);
    $('#kpi-wait').textContent = String(wait);
    $('#kpi-skip').textContent = String(skip);
    $('#kpi-stale').textContent = String(stale);
    $('#kpi-all').textContent = String(allRows.length);
  }
  function paintKpiActive() {
    document.querySelectorAll('.kpi').forEach(function (btn) {
      var on = btn.getAttribute('data-kpi') === state.kpiFilter;
      btn.classList.toggle('ring-2', on);
      btn.classList.toggle('ring-primary', on);
    });
  }
  function renderPanel(row) {
    state.selected = row || null;
    $('#kpi-selected').textContent = row !== null && row !== void 0 && row.ticker ? row.ticker : '—';
    $('#p-ticker').textContent = fmt(row === null || row === void 0 ? void 0 : row.ticker);
    $('#p-badges').innerHTML = badge(clsStatus(row === null || row === void 0 ? void 0 : row.status), row === null || row === void 0 ? void 0 : row.status, row === null || row === void 0 ? void 0 : row.status) + badge(clsSignal(row === null || row === void 0 ? void 0 : row.signalName), row === null || row === void 0 ? void 0 : row.signalName, row === null || row === void 0 ? void 0 : row.signalName) + badge(clsVol(row === null || row === void 0 ? void 0 : row.volumeLabelName), row === null || row === void 0 ? void 0 : row.volumeLabelName, row === null || row === void 0 ? void 0 : row.volumeLabelName);
    $('#p-last').textContent = fmt(row === null || row === void 0 ? void 0 : row.last);
    $('#p-rank').textContent = fmt(row === null || row === void 0 ? void 0 : row.rank);
    $('#p-entry').textContent = fmt(row === null || row === void 0 ? void 0 : row.entry);
    $('#p-rr').textContent = fmt(row === null || row === void 0 ? void 0 : row.rr);
    $('#p-sl').textContent = fmt(row === null || row === void 0 ? void 0 : row.sl);
    $('#p-tp').textContent = fmt(row === null || row === void 0 ? void 0 : row.tp);
    $('#p-reason').textContent = fmt(row === null || row === void 0 ? void 0 : row.reason);
    $('#p-snapshot').textContent = fmt(row === null || row === void 0 ? void 0 : row.snapshot_at);
    $('#p-lastbar').textContent = fmt(row === null || row === void 0 ? void 0 : row.last_bar_at);
    $('#p-json').textContent = row ? JSON.stringify(row, null, 2) : '—';
  }
  function openDrawer() {
    var d = $('#drawer');
    if (d) d.classList.remove('hidden');
  }
  function closeDrawer() {
    var d = $('#drawer');
    if (d) d.classList.add('hidden');
  }
  var tblBuy = null;
  var tblAll = null;
  function makeTable(el, height) {
    return new Tabulator(el, {
      layout: 'fitColumns',
      height: height,
      selectable: 1,
      rowHeight: 44,
      placeholder: 'No data',
      columns: [{
        title: 'Ticker',
        field: 'ticker',
        width: 150,
        formatter: function formatter(c) {
          var _c$getValue, _c$getRow$getData$log, _c$getRow$getData;
          var t = ((_c$getValue = c.getValue()) !== null && _c$getValue !== void 0 ? _c$getValue : '').toString();
          var logo = ((_c$getRow$getData$log = (_c$getRow$getData = c.getRow().getData()) === null || _c$getRow$getData === void 0 ? void 0 : _c$getRow$getData.logoUrl) !== null && _c$getRow$getData$log !== void 0 ? _c$getRow$getData$log : '').toString(); // siap kalau nanti ada
          var img = logo ? "<img src=\"".concat(logo, "\" class=\"w-6 h-6 rounded-full\" onerror=\"this.style.display='none'\">") : "<div class=\"w-6 h-6 rounded-full bg-primary/10 text-primary grid place-items-center text-xs font-bold\">".concat(t.slice(0, 1) || '?', "</div>");
          return "<div class=\"flex items-center gap-2\">".concat(img, "<span class=\"font-semibold\">").concat(t, "</span></div>");
        }
      }, {
        title: 'Status',
        field: 'status',
        width: 150,
        formatter: function formatter(c) {
          return badge(clsStatus(c.getValue()), c.getValue(), c.getValue());
        }
      }, {
        title: 'Signal',
        field: 'signalName',
        width: 170,
        formatter: function formatter(c) {
          return badge(clsSignal(c.getValue()), c.getValue(), c.getValue());
        }
      }, {
        title: 'Vol',
        field: 'volumeLabelName',
        width: 150,
        formatter: function formatter(c) {
          return badge(clsVol(c.getValue()), c.getValue(), c.getValue());
        }
      }, {
        title: 'Last',
        field: 'last',
        hozAlign: 'right'
      }, {
        title: 'Entry',
        field: 'entry',
        hozAlign: 'right'
      }, {
        title: 'SL',
        field: 'sl',
        hozAlign: 'right'
      }, {
        title: 'TP',
        field: 'tp',
        hozAlign: 'right'
      }, {
        title: 'RR',
        field: 'rr',
        hozAlign: 'right'
      }, {
        title: 'Reason',
        field: 'reason',
        widthGrow: 2,
        formatter: function formatter(c) {
          var _c$getValue2;
          return ((_c$getValue2 = c.getValue()) !== null && _c$getValue2 !== void 0 ? _c$getValue2 : '').toString().slice(0, 90);
        }
      }],
      rowClick: function rowClick(_, row) {
        row.select();
        renderPanel(row.getData());
        if (window.innerWidth < 1024) openDrawer();
      },
      rowTap: function rowTap(_, row) {
        // buat touch / klik yang kadang miss
        row.select();
        renderPanel(row.getData());
        if (window.innerWidth < 1024) openDrawer();
      },
      cellClick: function cellClick(_, cell) {
        // klik badge/sel tetap kebaca
        var row = cell.getRow();
        row.select();
        renderPanel(row.getData());
        if (window.innerWidth < 1024) openDrawer();
      }
    });
  }
  function selectFirstIfNeeded() {
    var _state$selected;
    if ((_state$selected = state.selected) !== null && _state$selected !== void 0 && _state$selected.ticker) return;
    var src = state.recoRows.length ? state.recoRows : state.rows;
    if (src.length) renderPanel(src[0]);
  }
  function applyTables() {
    var buyFiltered = applyClientFilter(state.recoRows);
    var allFiltered = applyClientFilter(state.rows);
    tblBuy.replaceData(buyFiltered);
    tblAll.replaceData(allFiltered);
    $('#meta-buy').textContent = "".concat(buyFiltered.length, " rows");
    $('#meta-all').textContent = "".concat(allFiltered.length, " rows");
  }
  function fmtTicker(cell) {
    var t = cell.getValue() || '';
    var letter = t ? t[0] : '?';
    return "\n      <div style=\"display:flex;align-items:center;gap:10px\">\n        <div style=\"width:26px;height:26px;border-radius:999px;background:rgba(37,99,235,.12);display:grid;place-items:center;font-weight:800;color:rgba(37,99,235,.95)\">\n          ".concat(letter, "\n        </div>\n        <div style=\"font-weight:700\">").concat(t, "</div>\n      </div>\n    ");
  }
  function refresh() {
    return _refresh.apply(this, arguments);
  }
  function _refresh() {
    _refresh = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee3() {
      var _meta$today, _meta$eodDate;
      var _yield$fetchData, meta, rows, recoRows, note, noteEl;
      return _regenerator().w(function (_context3) {
        while (1) switch (_context3.n) {
          case 0:
            $('#meta-server').textContent = 'Loading…';
            _context3.n = 1;
            return fetchData();
          case 1:
            _yield$fetchData = _context3.v;
            meta = _yield$fetchData.meta;
            rows = _yield$fetchData.rows;
            recoRows = _yield$fetchData.recoRows;
            note = _yield$fetchData.note;
            state.rows = rows;
            state.recoRows = recoRows;
            setKpiCounts(rows);
            paintKpiActive();
            noteEl = $('#reco-note');
            if (note && String(note).trim()) {
              noteEl.style.display = '';
              noteEl.textContent = String(note);
            } else {
              noteEl.style.display = 'none';
              noteEl.textContent = '';
            }
            applyTables();
            selectFirstIfNeeded();
            $('#meta-server').textContent = "Server: ".concat((_meta$today = meta.today) !== null && _meta$today !== void 0 ? _meta$today : '-', " \u2022 EOD: ").concat((_meta$eodDate = meta.eodDate) !== null && _meta$eodDate !== void 0 ? _meta$eodDate : '-');
          case 2:
            return _context3.a(2);
        }
      }, _callee3);
    }));
    return _refresh.apply(this, arguments);
  }
  function startAuto() {
    stopAuto();
    var sec = parseInt($('#auto-interval').value || '60', 10);
    state.timer = setInterval(function () {
      return refresh()["catch"](console.error);
    }, sec * 1000);
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
    $('#btn-refresh').addEventListener('click', function () {
      return refresh()["catch"](console.error);
    });

    // auto refresh
    $('#auto-refresh').addEventListener('change', function (e) {
      return e.target.checked ? startAuto() : stopAuto();
    });
    $('#auto-interval').addEventListener('change', function () {
      return $('#auto-refresh').checked ? startAuto() : null;
    });

    // search
    var search = $('#global-search');
    search.addEventListener('input', function () {
      state.search = search.value || '';
      applyTables();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === '/') {
        e.preventDefault();
        search.focus();
      }
      if (e.key === 'Escape') closeDrawer();
    });

    // KPI filter clicks
    document.querySelectorAll('.kpi').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.kpiFilter = btn.getAttribute('data-kpi') || 'ALL';
        paintKpiActive();
        applyTables();
      });
    });

    // panel copy
    var copyBtn = $('#btn-copy-plan');
    if (copyBtn) {
      copyBtn.addEventListener('click', /*#__PURE__*/_asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee() {
        var _s$reason;
        var s, text, _t;
        return _regenerator().w(function (_context) {
          while (1) switch (_context.p = _context.n) {
            case 0:
              if (state.selected) {
                _context.n = 1;
                break;
              }
              return _context.a(2);
            case 1:
              s = state.selected;
              text = "".concat(s.ticker, "\n") + "Status: ".concat(s.status, "\n") + "Signal: ".concat(s.signalName, "\n") + "Vol: ".concat(s.volumeLabelName, "\n") + "Last: ".concat(s.last, "\n") + "Entry: ".concat(s.entry, " | SL: ").concat(s.sl, " | TP: ").concat(s.tp, " | RR: ").concat(s.rr, "\n") + "Reason: ".concat((_s$reason = s.reason) !== null && _s$reason !== void 0 ? _s$reason : '');
              _context.p = 2;
              _context.n = 3;
              return navigator.clipboard.writeText(text);
            case 3:
              _context.n = 5;
              break;
            case 4:
              _context.p = 4;
              _t = _context.v;
            case 5:
              return _context.a(2);
          }
        }, _callee, null, [[2, 4]]);
      })));
    }

    // mobile panel toggle
    var btnPanel = $('#btn-panel');
    if (btnPanel) btnPanel.addEventListener('click', openDrawer);

    // drawer close
    var back = $('#drawer-backdrop');
    var close = $('#drawer-close');
    if (back) back.addEventListener('click', closeDrawer);
    if (close) close.addEventListener('click', closeDrawer);
  }
  document.addEventListener('DOMContentLoaded', function () {
    wireUI();
    refresh()["catch"](console.error);
  });
})();

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Check if module exists (development only)
/******/ 		if (__webpack_modules__[moduleId] === undefined) {
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"/js/screener/buylist": 0,
/******/ 			"css/app": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = self["webpackChunk"] = self["webpackChunk"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	__webpack_require__.O(undefined, ["css/app"], () => (__webpack_require__("./resources/js/screener/buylist.js")))
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["css/app"], () => (__webpack_require__("./resources/css/app.css")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;