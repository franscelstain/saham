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
function _toConsumableArray(r) { return _arrayWithoutHoles(r) || _iterableToArray(r) || _unsupportedIterableToArray(r) || _nonIterableSpread(); }
function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _iterableToArray(r) { if ("undefined" != typeof Symbol && null != r[Symbol.iterator] || null != r["@@iterator"]) return Array.from(r); }
function _arrayWithoutHoles(r) { if (Array.isArray(r)) return _arrayLikeToArray(r); }
function _createForOfIteratorHelper(r, e) { var t = "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (!t) { if (Array.isArray(r) || (t = _unsupportedIterableToArray(r)) || e && r && "number" == typeof r.length) { t && (r = t); var _n = 0, F = function F() {}; return { s: F, n: function n() { return _n >= r.length ? { done: !0 } : { done: !1, value: r[_n++] }; }, e: function e(r) { throw r; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var o, a = !0, u = !1; return { s: function s() { t = t.call(r); }, n: function n() { var r = t.next(); return a = r.done, r; }, e: function e(r) { u = !0, o = r; }, f: function f() { try { a || null == t["return"] || t["return"](); } finally { if (u) throw o; } } }; }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
(function (_window$__SCREENER__) {
  var endpoint = (_window$__SCREENER__ = window.__SCREENER__) === null || _window$__SCREENER__ === void 0 || (_window$__SCREENER__ = _window$__SCREENER__.endpoints) === null || _window$__SCREENER__ === void 0 ? void 0 : _window$__SCREENER__.buylist;
  var $ = function $(q) {
    return document.querySelector(q);
  };

  // --- SAFE DOM helpers (tahan banting) ---
  function el(q) {
    try {
      return document.querySelector(q);
    } catch (_) {
      return null;
    }
  }
  function setText(q, v) {
    var n = el(q);
    if (!n) return false;
    n.textContent = v === null || v === undefined ? '' : String(v);
    return true;
  }
  function setHtml(q, v) {
    var n = el(q);
    if (!n) return false;
    n.innerHTML = v === null || v === undefined ? '' : String(v);
    return true;
  }
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
  function pretty(v) {
    if (v === null || v === undefined) return '—';
    var s = String(v);
    return s.replaceAll('_', ' ');
  }
  function badge(cls, text) {
    var title = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;
    var raw = (text !== null && text !== void 0 ? text : '—').toString();
    var disp = raw.replaceAll('_', ' ');
    var tt = title === null || title === undefined || title === '' ? '' : " title=\"".concat(escapeHtml((title !== null && title !== void 0 ? title : raw).toString().replaceAll('_', ' ')), "\"");
    return "\n      <span class=\"inline-flex items-center justify-center whitespace-nowrap\n                   px-2.5 py-1 text-xs font-semibold leading-none\n                   rounded-md align-middle ".concat(cls, "\"").concat(tt, ">\n        ").concat(escapeHtml(disp), "\n      </span>\n    ");
  }
  function escapeHtml(s) {
    return (s !== null && s !== void 0 ? s : '').toString().replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", "&#039;");
  }
  function clsStatus(st) {
    var map = {
      // BUY = paling yakin (hijau tua)
      BUY_OK: 'bg-emerald-700 text-white',
      BUY_PULLBACK: 'bg-emerald-700 text-white',
      // WAIT = butuh syarat (amber)
      WAIT: 'bg-amber-500 text-white',
      WAIT_ENTRY_WINDOW: 'bg-amber-500 text-white',
      WAIT_PULLBACK: 'bg-amber-500 text-white',
      WAIT_REL_VOL: 'bg-amber-500 text-white',
      WAIT_STRENGTH: 'bg-amber-500 text-white',
      WAIT_EOD_GUARD: 'bg-amber-500 text-white',
      WAIT_CALENDAR: 'bg-amber-500 text-white',
      // ERROR / tidak layak (merah)
      CAPITAL_TOO_SMALL: 'bg-rose-600 text-white',
      RR_TOO_LOW: 'bg-rose-600 text-white',
      RISK_TOO_WIDE: 'bg-rose-600 text-white',
      NO_INTRADAY: 'bg-rose-600 text-white',
      STALE_INTRADAY: 'bg-rose-600 text-white',
      // SKIP / neutral (slate)
      SKIP_BAD_INTRADAY: 'bg-slate-700 text-white',
      SKIP_BREAKDOWN: 'bg-slate-700 text-white',
      SKIP_EOD_GUARD: 'bg-slate-700 text-white',
      SKIP_FEE_DRAG: 'bg-slate-700 text-white',
      SKIP_DAY_FRIDAY: 'bg-slate-700 text-white',
      SKIP_DAY_THURSDAY_LATE: 'bg-slate-700 text-white',
      // ghost/info (abu)
      LATE_ENTRY: 'bg-gray-600 text-white',
      LUNCH_WINDOW: 'bg-gray-600 text-white',
      EXPIRED: 'bg-gray-600 text-white'
    };
    return map[st] || 'bg-slate-500 text-white';
  }
  function fmtPrice(v) {
    var n = Number(v);
    if (!Number.isFinite(n)) return '—';
    return n.toLocaleString('id-ID', {
      maximumFractionDigits: 2
    });
  }

  // Harga saham IDX: default tanpa desimal, tapi kalau data punya pecahan, tampilkan max 4.
  function fmtPx(v) {
    if (v === null || v === undefined || v === '') return '—';
    var s = String(v);
    var n = Number(s);
    if (!Number.isFinite(n)) return '—';
    var hasFrac = s.includes('.') && !/^\d+\.0+$/.test(s);
    var maxFrac = hasFrac ? 4 : 0;
    return n.toLocaleString('id-ID', {
      maximumFractionDigits: maxFrac
    });
  }
  function fmtInt(v) {
    var n = Number(v);
    if (!Number.isFinite(n)) return '—';
    var i = Math.round(n);
    return i.toLocaleString('id-ID', {
      maximumFractionDigits: 0
    });
  }
  function fmt2(v) {
    var n = Number(v);
    if (!Number.isFinite(n)) return '—';
    return n.toLocaleString('id-ID', {
      maximumFractionDigits: 2
    });
  }
  function fmtPct(v) {
    var n = Number(v);
    if (!Number.isFinite(n)) return '—';
    return "".concat(n.toLocaleString('id-ID', {
      maximumFractionDigits: 2
    }), "%");
  }
  function firstVal(obj, keys) {
    var _iterator = _createForOfIteratorHelper(keys),
      _step;
    try {
      for (_iterator.s(); !(_step = _iterator.n()).done;) {
        var k = _step.value;
        var v = obj === null || obj === void 0 ? void 0 : obj[k];
        if (v !== null && v !== undefined && v !== '') return v;
      }
    } catch (err) {
      _iterator.e(err);
    } finally {
      _iterator.f();
    }
    return null;
  }
  function resolveOHLC(row) {
    // Intraday candidates (kalau sudah ada)
    var o_i = firstVal(row, ['open_price', 'i_open', 'intraday_open']);
    var h_i = firstVal(row, ['high_price', 'i_high', 'intraday_high']);
    var l_i = firstVal(row, ['low_price', 'i_low', 'intraday_low']);
    var c_i = firstVal(row, ['last_price', 'intraday_close', 'i_close']);
    var intradayOk = (o_i !== null || h_i !== null || l_i !== null) && c_i !== null;
    if (intradayOk) {
      return {
        src: 'Intraday',
        o: o_i,
        h: h_i,
        l: l_i,
        c: c_i
      };
    }

    // Fallback ke EOD
    var o = firstVal(row, ['open']);
    var h = firstVal(row, ['high']);
    var l = firstVal(row, ['low']);
    var c = firstVal(row, ['close']);
    return {
      src: 'EOD',
      o: o,
      h: h,
      l: l,
      c: c
    };
  }
  function setPanelLogo(logoUrl, ticker) {
    var img = el('#p-logo-img');
    var fb = el('#p-logo-fallback');
    var t = (ticker !== null && ticker !== void 0 ? ticker : '').toString().trim().toUpperCase();
    if (fb) fb.textContent = t[0] || '?';
    if (!img || !fb) return;
    var url = (logoUrl !== null && logoUrl !== void 0 ? logoUrl : '').toString().trim();
    if (!url) {
      img.classList.add('hidden');
      fb.classList.remove('hidden');
      return;
    }
    img.onload = function () {
      img.classList.remove('hidden');
      fb.classList.add('hidden');
    };
    img.onerror = function () {
      img.classList.add('hidden');
      fb.classList.remove('hidden');
    };
    img.src = url;
  }
  function clsSignal(s) {
    var map = {
      // urutan kualitas sinyal (paling kuat -> paling lemah)
      'Layak Beli': 'bg-emerald-600 text-white',
      'Perlu Konfirmasi': 'bg-sky-600 text-white',
      'Hati - Hati': 'bg-amber-500 text-white',
      'Hindari': 'bg-rose-600 text-white',
      'False Breakout / Batal': 'bg-slate-600 text-white',
      'Unknown': 'bg-slate-500 text-white'
    };
    return map[s] || 'bg-slate-500 text-white';
  }
  ;
  function clsVol(v) {
    var map = {
      // volume momentum: beda tingkat
      'Strong Burst / Breakout': 'bg-emerald-700 text-white',
      'Volume Burst / Accumulation': 'bg-emerald-600 text-white',
      'Early Interest': 'bg-sky-600 text-white',
      // normal -> lemah
      'Normal': 'bg-slate-600 text-white',
      'Quiet': 'bg-slate-500 text-white',
      'Quiet/Normal – Volume lemah': 'bg-slate-500 text-white',
      'Dormant': 'bg-slate-400 text-white',
      'Ultra Dry': 'bg-slate-300 text-slate-900',
      // euforia
      'Climax / Euphoria': 'bg-orange-600 text-white',
      'Climax / Euphoria – hati-hati': 'bg-orange-500 text-white'
    };
    return map[v] || 'bg-slate-500 text-white';
  }
  ;
  function renderNoteWithStatusBadges(note) {
    var s = (note !== null && note !== void 0 ? note : '').toString();
    if (!s.trim()) return '';
    var re = /\b[A-Z][A-Z0-9_]{2,}\b/g;
    var out = '';
    var last = 0;
    var matches = _toConsumableArray(s.matchAll(re));
    var _iterator2 = _createForOfIteratorHelper(matches),
      _step2;
    try {
      for (_iterator2.s(); !(_step2 = _iterator2.n()).done;) {
        var _m$index;
        var m = _step2.value;
        var token = m[0];
        var idx = (_m$index = m.index) !== null && _m$index !== void 0 ? _m$index : 0;

        // teks sebelum token (escape)
        out += escapeHtml(s.slice(last, idx));

        // token jadi badge (JANGAN di-escape)
        var cls = clsStatus(token);
        // kalau token tidak dikenal, tampilkan teks biasa
        if (cls && cls !== 'badge-outline' && cls !== 'bg-slate-500 text-white') {
          out += badge(cls, token.replaceAll('_', ' ')); // text display sudah spasi
        } else {
          out += escapeHtml(token.replaceAll('_', ' '));
        }
        last = idx + token.length;
      }
    } catch (err) {
      _iterator2.e(err);
    } finally {
      _iterator2.f();
    }
    out += escapeHtml(s.slice(last));
    return out;
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
              var _ref6, _ref7, _r$last, _ref8, _r$reason, _r$snapshot_at, _r$last_bar_at, _ref9, _r$rank, _ref0, _r$signalName, _ref1, _r$volumeLabelName;
              return _objectSpread(_objectSpread({}, r), {}, {
                ticker: r.ticker || r.ticker_code || r.symbol,
                last: (_ref6 = (_ref7 = (_r$last = r.last) !== null && _r$last !== void 0 ? _r$last : r.last_price) !== null && _ref7 !== void 0 ? _ref7 : r.close) !== null && _ref6 !== void 0 ? _ref6 : r.price,
                reason: (_ref8 = (_r$reason = r.reason) !== null && _r$reason !== void 0 ? _r$reason : r.reason_text) !== null && _ref8 !== void 0 ? _ref8 : r.notes,
                snapshot_at: (_r$snapshot_at = r.snapshot_at) !== null && _r$snapshot_at !== void 0 ? _r$snapshot_at : r.snapshotAt,
                last_bar_at: (_r$last_bar_at = r.last_bar_at) !== null && _r$last_bar_at !== void 0 ? _r$last_bar_at : r.lastBarAt,
                rank: (_ref9 = (_r$rank = r.rank) !== null && _r$rank !== void 0 ? _r$rank : r.rank_score) !== null && _ref9 !== void 0 ? _ref9 : r.rankScore,
                signalName: (_ref0 = (_r$signalName = r.signalName) !== null && _r$signalName !== void 0 ? _r$signalName : r.signal_name) !== null && _ref0 !== void 0 ? _ref0 : r.signal,
                volumeLabelName: (_ref1 = (_r$volumeLabelName = r.volumeLabelName) !== null && _r$volumeLabelName !== void 0 ? _r$volumeLabelName : r.volume_label_name) !== null && _ref1 !== void 0 ? _ref1 : r.volume_label
              });
            });
            picks = json.reco && (json.reco.picks || json.reco.rows || json.reco) || [];
            note = json.reco && (json.reco.note || json.reco.message) || null;
            recoRows = [];
            if (Array.isArray(picks) && picks.length && _typeof(picks[0]) === 'object') {
              recoRows = picks.map(function (r) {
                var _ref10, _ref11, _r$last2, _ref12, _r$reason2, _r$snapshot_at2, _r$last_bar_at2, _ref13, _r$rank2, _ref14, _r$signalName2, _ref15, _r$volumeLabelName2;
                return _objectSpread(_objectSpread({}, r), {}, {
                  ticker: r.ticker || r.ticker_code || r.symbol,
                  last: (_ref10 = (_ref11 = (_r$last2 = r.last) !== null && _r$last2 !== void 0 ? _r$last2 : r.last_price) !== null && _ref11 !== void 0 ? _ref11 : r.close) !== null && _ref10 !== void 0 ? _ref10 : r.price,
                  reason: (_ref12 = (_r$reason2 = r.reason) !== null && _r$reason2 !== void 0 ? _r$reason2 : r.reason_text) !== null && _ref12 !== void 0 ? _ref12 : r.notes,
                  snapshot_at: (_r$snapshot_at2 = r.snapshot_at) !== null && _r$snapshot_at2 !== void 0 ? _r$snapshot_at2 : r.snapshotAt,
                  last_bar_at: (_r$last_bar_at2 = r.last_bar_at) !== null && _r$last_bar_at2 !== void 0 ? _r$last_bar_at2 : r.lastBarAt,
                  rank: (_ref13 = (_r$rank2 = r.rank) !== null && _r$rank2 !== void 0 ? _r$rank2 : r.rank_score) !== null && _ref13 !== void 0 ? _ref13 : r.rankScore,
                  signalName: (_ref14 = (_r$signalName2 = r.signalName) !== null && _r$signalName2 !== void 0 ? _r$signalName2 : r.signal_name) !== null && _ref14 !== void 0 ? _ref14 : r.signal,
                  volumeLabelName: (_ref15 = (_r$volumeLabelName2 = r.volumeLabelName) !== null && _r$volumeLabelName2 !== void 0 ? _r$volumeLabelName2 : r.volume_label_name) !== null && _ref15 !== void 0 ? _ref15 : r.volume_label
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
    setText('#kpi-buy', String(buy));
    setText('#kpi-wait', String(wait));
    setText('#kpi-skip', String(skip));
    setText('#kpi-stale', String(stale));
    setText('#kpi-all', String(allRows.length));
  }
  function paintKpiActive() {
    document.querySelectorAll('.kpi').forEach(function (btn) {
      var on = btn.getAttribute('data-kpi') === state.kpiFilter;
      btn.classList.toggle('ring-2', on);
      btn.classList.toggle('ring-primary', on);
    });
  }
  function renderPanel(row) {
    var _ref, _row$ticker, _ref2, _row$company_name, _ref3, _ref4, _row$last_price, _firstVal, _firstVal2, _firstVal3;
    state.selected = row || null;
    setText('#kpi-selected', row !== null && row !== void 0 && row.ticker ? row.ticker : '—');
    var ticker = (_ref = (_row$ticker = row === null || row === void 0 ? void 0 : row.ticker) !== null && _row$ticker !== void 0 ? _row$ticker : row === null || row === void 0 ? void 0 : row.ticker_code) !== null && _ref !== void 0 ? _ref : '—';
    var name = (_ref2 = (_row$company_name = row === null || row === void 0 ? void 0 : row.company_name) !== null && _row$company_name !== void 0 ? _row$company_name : row === null || row === void 0 ? void 0 : row.companyName) !== null && _ref2 !== void 0 ? _ref2 : '—';

    // harga saat ini: prioritas intraday last_price, fallback ke last, lalu close (EOD)
    var priceRaw = (_ref3 = (_ref4 = (_row$last_price = row === null || row === void 0 ? void 0 : row.last_price) !== null && _row$last_price !== void 0 ? _row$last_price : row === null || row === void 0 ? void 0 : row.last) !== null && _ref4 !== void 0 ? _ref4 : row === null || row === void 0 ? void 0 : row.close) !== null && _ref3 !== void 0 ? _ref3 : null;
    setText('#p-ticker', fmt(ticker));
    setText('#p-name', fmt(name));
    setText('#p-price', fmtPx(priceRaw));
    var url = (row === null || row === void 0 ? void 0 : row.logoUrl) || (row === null || row === void 0 ? void 0 : row.logo_url) || (row === null || row === void 0 ? void 0 : row.logo) || (row === null || row === void 0 ? void 0 : row.company_logo) || null;
    setPanelLogo(url, ticker);
    var ohlc = resolveOHLC(row);
    setText('#p-ohlc-src', ohlc.src ? "(".concat(ohlc.src, ")") : '—');
    setText('#p-o', fmtPx(ohlc.o));
    setText('#p-h', fmtPx(ohlc.h));
    setText('#p-l', fmtPx(ohlc.l));
    setText('#p-c', fmtPx(ohlc.c));
    setHtml('#p-badges', badge(clsStatus(row === null || row === void 0 ? void 0 : row.status), pretty(row === null || row === void 0 ? void 0 : row.status), row === null || row === void 0 ? void 0 : row.status) + badge(clsSignal(row === null || row === void 0 ? void 0 : row.signalName), pretty(row === null || row === void 0 ? void 0 : row.signalName), row === null || row === void 0 ? void 0 : row.signalName) + badge(clsVol(row === null || row === void 0 ? void 0 : row.volumeLabelName), pretty(row === null || row === void 0 ? void 0 : row.volumeLabelName), row === null || row === void 0 ? void 0 : row.volumeLabelName));
    setText('#p-rank', fmt(row === null || row === void 0 ? void 0 : row.rank));
    setText('#p-entry', fmt(row === null || row === void 0 ? void 0 : row.entry));
    setText('#p-rr', fmt(row === null || row === void 0 ? void 0 : row.rr));
    setText('#p-sl', fmt(row === null || row === void 0 ? void 0 : row.sl));
    setText('#p-tp', fmt(row === null || row === void 0 ? void 0 : row.tp));
    setText('#p-reason', fmt(row === null || row === void 0 ? void 0 : row.reason));
    setText('#p-snapshot', fmt(row === null || row === void 0 ? void 0 : row.snapshot_at));
    setText('#p-lastbar', fmt(row === null || row === void 0 ? void 0 : row.last_bar_at));
    setText('#p-json', row ? JSON.stringify(row, null, 2) : '—');

    // Market
    setText('#p-relvol', fmt2(firstVal(row, ['relvol_today', 'relvol', 'vol_ratio'])));
    setText('#p-pos', fmtPct(firstVal(row, ['pos_in_range', 'pos_pct', 'pos'])));
    setText('#p-eodlow', fmtInt(firstVal(row, ['eod_low'])));
    setText('#p-priceok', String((_firstVal = firstVal(row, ['price_ok'])) !== null && _firstVal !== void 0 ? _firstVal : '—').replaceAll('_', ' '));

    // Plan
    setText('#p-entry', fmtInt(firstVal(row, ['entry'])));
    setText('#p-steps', String((_firstVal2 = firstVal(row, ['buy_steps', 'steps', 'buySteps'])) !== null && _firstVal2 !== void 0 ? _firstVal2 : '—'));
    setText('#p-sl', fmtInt(firstVal(row, ['sl'])));
    setText('#p-tp1', fmtInt(firstVal(row, ['tp1', 'tp_1'])));
    setText('#p-tp2', fmtInt(firstVal(row, ['tp2', 'tp_2', 'tp2_price'])));
    setText('#p-be', fmtInt(firstVal(row, ['be', 'break_even', 'breakEven'])));
    setText('#p-out', fmtInt(firstVal(row, ['out', 'out_buy_fee', 'out_buyfee'])));
    setText('#p-lots', String((_firstVal3 = firstVal(row, ['lots'])) !== null && _firstVal3 !== void 0 ? _firstVal3 : '—'));
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
    var _row$reason;
    // Mobile drawer uses d-* ids; kalau drawer markup tidak ada / tidak lengkap, jangan crash
    var required = ['#d-ticker', '#d-badges', '#d-rank', '#d-entry', '#d-rr', '#d-sl', '#d-tp', '#d-reason', '#d-json'];
    for (var _i = 0, _required = required; _i < _required.length; _i++) {
      var q = _required[_i];
      if (!el(q)) return;
    }
    setText('#d-ticker', fmt(row === null || row === void 0 ? void 0 : row.ticker));
    setHtml('#d-badges', badge(clsStatus(row === null || row === void 0 ? void 0 : row.status), pretty(row === null || row === void 0 ? void 0 : row.status), row === null || row === void 0 ? void 0 : row.status) + badge(clsSignal(row === null || row === void 0 ? void 0 : row.signalName), pretty(row === null || row === void 0 ? void 0 : row.signalName), row === null || row === void 0 ? void 0 : row.signalName) + badge(clsVol(row === null || row === void 0 ? void 0 : row.volumeLabelName), pretty(row === null || row === void 0 ? void 0 : row.volumeLabelName), row === null || row === void 0 ? void 0 : row.volumeLabelName));
    setText('#d-last', fmt(row === null || row === void 0 ? void 0 : row.last));
    setText('#d-rank', fmt(row === null || row === void 0 ? void 0 : row.rank));
    setText('#d-entry', fmt(row === null || row === void 0 ? void 0 : row.entry));
    setText('#d-rr', fmt(row === null || row === void 0 ? void 0 : row.rr));
    setText('#d-sl', fmt(row === null || row === void 0 ? void 0 : row.sl));
    setText('#d-tp', fmt(row === null || row === void 0 ? void 0 : row.tp));
    setText('#d-reason', ((_row$reason = row === null || row === void 0 ? void 0 : row.reason) !== null && _row$reason !== void 0 ? _row$reason : '—').toString());
    setText('#d-json', JSON.stringify(row !== null && row !== void 0 ? row : {}, null, 2));
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
    var t = new Tabulator(el, {
      layout: 'fitColumns',
      height: height,
      selectableRows: false,
      rowHeight: 44,
      placeholder: 'No data',
      columnDefaults: {
        headerSort: false,
        resizable: false
      },
      columns: [{
        title: 'Ticker',
        field: 'ticker',
        width: 160,
        formatter: function formatter(c) {
          var _c$getValue, _c$getRow$getData$log, _c$getRow$getData;
          var t = ((_c$getValue = c.getValue()) !== null && _c$getValue !== void 0 ? _c$getValue : '').toString();
          var logo = ((_c$getRow$getData$log = (_c$getRow$getData = c.getRow().getData()) === null || _c$getRow$getData === void 0 ? void 0 : _c$getRow$getData.logoUrl) !== null && _c$getRow$getData$log !== void 0 ? _c$getRow$getData$log : '').toString();
          var img = logo ? "<img src=\"".concat(logo, "\" class=\"w-6 h-6 rounded-full\" onerror=\"this.style.display='none'\">") : "<div class=\"w-6 h-6 rounded-full bg-primary/10 text-primary grid place-items-center text-xs font-bold\">".concat(t.slice(0, 1) || '?', "</div>");
          return "<div class=\"flex items-center gap-2\">".concat(img, "<span class=\"font-semibold\">").concat(t, "</span></div>");
        }
      }, {
        title: 'Status',
        field: 'status',
        width: 190,
        formatter: function formatter(c) {
          return badge(clsStatus(c.getValue()), c.getValue(), c.getValue());
        }
      }, {
        title: 'Signal',
        field: 'signalName',
        minWidth: 170,
        formatter: function formatter(c) {
          return badge(clsSignal(c.getValue()), c.getValue(), c.getValue());
        }
      },
      // Vol yang menyerap sisa lebar, jadi nggak ada “kolom kosong”
      {
        title: 'Vol',
        field: 'volumeLabelName',
        minWidth: 220,
        widthGrow: 1,
        formatter: function formatter(c) {
          return badge(clsVol(c.getValue()), c.getValue(), c.getValue());
        }
      }, {
        title: 'Rank',
        field: 'rank',
        width: 90,
        hozAlign: 'right',
        formatter: function formatter(c) {
          var v = c.getValue();
          return v === null || v === undefined || v === '' ? '—' : String(v);
        }
      }],
      rowSelectionChanged: function rowSelectionChanged(_, rows) {
        if (!(rows !== null && rows !== void 0 && rows.length)) return;
        renderPanel(rows[0].getData());
        if (window.innerWidth < 1024) openDrawer();
      }
    });

    // HARD BIND: ini yang bikin klik selalu update panel kanan
    t.on("rowClick", function (e, row) {
      renderPanel(row.getData());
      if (window.innerWidth < 1024) openDrawer();
    });
    t.on("cellClick", function (e, cell) {
      var row = cell.getRow();
      renderPanel(row.getData());
      if (window.innerWidth < 1024) openDrawer();
    });

    // optional: kalau mau tetap dukung select-based update
    t.on("rowSelected", function (row) {
      try {
        renderPanel(row.getData());
      } catch (_) {}
    });
    return t;
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
    setText('#meta-buy', "".concat(buyFiltered.length, " rows"));
    setText('#meta-all', "".concat(allFiltered.length, " rows"));
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
      var metaEl, meta, rows, recoRows, note, _yield$fetchData, noteEl, _t2;
      return _regenerator().w(function (_context3) {
        while (1) switch (_context3.p = _context3.n) {
          case 0:
            metaEl = el('#meta-server');
            if (metaEl) metaEl.textContent = 'Loading…';
            _context3.p = 1;
            _context3.n = 2;
            return fetchData();
          case 2:
            _yield$fetchData = _context3.v;
            meta = _yield$fetchData.meta;
            rows = _yield$fetchData.rows;
            recoRows = _yield$fetchData.recoRows;
            note = _yield$fetchData.note;
            _context3.n = 4;
            break;
          case 3:
            _context3.p = 3;
            _t2 = _context3.v;
            console.error(_t2);
            if (metaEl) metaEl.textContent = 'Server: error (lihat console)';
            return _context3.a(2);
          case 4:
            state.rows = rows;
            state.recoRows = recoRows;
            setKpiCounts(rows);
            paintKpiActive();
            noteEl = $('#reco-note');
            if (noteEl && note && String(note).trim()) {
              noteEl.style.display = '';
              noteEl.innerHTML = renderNoteWithStatusBadges(note);
            } else if (noteEl) {
              noteEl.style.display = 'none';
              noteEl.innerHTML = '';
            }
            applyTables();
            selectFirstIfNeeded();
            setText('#meta-server', "Server: ".concat((_meta$today = meta.today) !== null && _meta$today !== void 0 ? _meta$today : '-', " \u2022 EOD: ").concat((_meta$eodDate = meta.eodDate) !== null && _meta$eodDate !== void 0 ? _meta$eodDate : '-'));
          case 5:
            return _context3.a(2);
        }
      }, _callee3, null, [[1, 3]]);
    }));
    return _refresh.apply(this, arguments);
  }
  function startAuto() {
    stopAuto();
    var ai = el('#auto-interval');
    var sec = parseInt((ai ? ai.value : '60') || '60', 10);
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
    var btnRefresh = el('#btn-refresh');
    if (btnRefresh) btnRefresh.addEventListener('click', function () {
      return refresh()["catch"](console.error);
    });

    // auto refresh
    var autoRefresh = el('#auto-refresh');
    if (autoRefresh) autoRefresh.addEventListener('change', function (e) {
      return e.target.checked ? startAuto() : stopAuto();
    });
    var autoInterval = el('#auto-interval');
    if (autoInterval) autoInterval.addEventListener('change', function () {
      return el('#auto-refresh') && el('#auto-refresh').checked ? startAuto() : null;
    });

    // search
    var search = el('#global-search');
    if (search) search.addEventListener('input', function () {
      state.search = search.value || '';
      applyTables();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === '/') {
        e.preventDefault();
        if (search) search.focus();
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