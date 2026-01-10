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

/***/ "./resources/js/screener/ticker.js"
/*!*****************************************!*\
  !*** ./resources/js/screener/ticker.js ***!
  \*****************************************/
() {

function _regenerator() { /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/babel/babel/blob/main/packages/babel-helpers/LICENSE */ var e, t, r = "function" == typeof Symbol ? Symbol : {}, n = r.iterator || "@@iterator", o = r.toStringTag || "@@toStringTag"; function i(r, n, o, i) { var c = n && n.prototype instanceof Generator ? n : Generator, u = Object.create(c.prototype); return _regeneratorDefine2(u, "_invoke", function (r, n, o) { var i, c, u, f = 0, p = o || [], y = !1, G = { p: 0, n: 0, v: e, a: d, f: d.bind(e, 4), d: function d(t, r) { return i = t, c = 0, u = e, G.n = r, a; } }; function d(r, n) { for (c = r, u = n, t = 0; !y && f && !o && t < p.length; t++) { var o, i = p[t], d = G.p, l = i[2]; r > 3 ? (o = l === n) && (u = i[(c = i[4]) ? 5 : (c = 3, 3)], i[4] = i[5] = e) : i[0] <= d && ((o = r < 2 && d < i[1]) ? (c = 0, G.v = n, G.n = i[1]) : d < l && (o = r < 3 || i[0] > n || n > l) && (i[4] = r, i[5] = n, G.n = l, c = 0)); } if (o || r > 1) return a; throw y = !0, n; } return function (o, p, l) { if (f > 1) throw TypeError("Generator is already running"); for (y && 1 === p && d(p, l), c = p, u = l; (t = c < 2 ? e : u) || !y;) { i || (c ? c < 3 ? (c > 1 && (G.n = -1), d(c, u)) : G.n = u : G.v = u); try { if (f = 2, i) { if (c || (o = "next"), t = i[o]) { if (!(t = t.call(i, u))) throw TypeError("iterator result is not an object"); if (!t.done) return t; u = t.value, c < 2 && (c = 0); } else 1 === c && (t = i["return"]) && t.call(i), c < 2 && (u = TypeError("The iterator does not provide a '" + o + "' method"), c = 1); i = e; } else if ((t = (y = G.n < 0) ? u : r.call(n, G)) !== a) break; } catch (t) { i = e, c = 1, u = t; } finally { f = 1; } } return { value: t, done: y }; }; }(r, o, i), !0), u; } var a = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} t = Object.getPrototypeOf; var c = [][n] ? t(t([][n]())) : (_regeneratorDefine2(t = {}, n, function () { return this; }), t), u = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(c); function f(e) { return Object.setPrototypeOf ? Object.setPrototypeOf(e, GeneratorFunctionPrototype) : (e.__proto__ = GeneratorFunctionPrototype, _regeneratorDefine2(e, o, "GeneratorFunction")), e.prototype = Object.create(u), e; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, _regeneratorDefine2(u, "constructor", GeneratorFunctionPrototype), _regeneratorDefine2(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = "GeneratorFunction", _regeneratorDefine2(GeneratorFunctionPrototype, o, "GeneratorFunction"), _regeneratorDefine2(u), _regeneratorDefine2(u, o, "Generator"), _regeneratorDefine2(u, n, function () { return this; }), _regeneratorDefine2(u, "toString", function () { return "[object Generator]"; }), (_regenerator = function _regenerator() { return { w: i, m: f }; })(); }
function _regeneratorDefine2(e, r, n, t) { var i = Object.defineProperty; try { i({}, "", {}); } catch (e) { i = 0; } _regeneratorDefine2 = function _regeneratorDefine(e, r, n, t) { function o(r, n) { _regeneratorDefine2(e, r, function (e) { return this._invoke(r, n, e); }); } r ? i ? i(e, r, { value: n, enumerable: !t, configurable: !t, writable: !t }) : e[r] = n : (o("next", 0), o("throw", 1), o("return", 2)); }, _regeneratorDefine2(e, r, n, t); }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }
/* global Tabulator */
(function (_el2, _el3, _el4, _el5) {
  var cfg = window.__TA || {};
  var el = function el(q) {
    return document.querySelector(q);
  };
  var fmtInt = function fmtInt(n) {
    if (n === null || n === undefined || n === '') return '-';
    var x = Number(n);
    if (!Number.isFinite(x)) return '-';
    return x.toLocaleString('en-US');
  };
  var fmtNum = function fmtNum(n) {
    if (n === null || n === undefined || n === '') return '-';
    var x = Number(n);
    if (!Number.isFinite(x)) return '-';
    // harga biasanya integer, tapi biar aman:
    return x % 1 === 0 ? x.toLocaleString('en-US') : x.toLocaleString('en-US', {
      maximumFractionDigits: 4
    });
  };
  var toast = function toast(msg) {
    return console.log(msg);
  };
  function post(_x, _x2) {
    return _post.apply(this, arguments);
  } // logo cell with fallback letter ticker
  function _post() {
    _post = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee2(url, body) {
      var res, data;
      return _regenerator().w(function (_context2) {
        while (1) switch (_context2.n) {
          case 0:
            _context2.n = 1;
            return fetch(url, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf || ''
              },
              body: JSON.stringify(body || {})
            });
          case 1:
            res = _context2.v;
            _context2.n = 2;
            return res.json()["catch"](function () {
              return {};
            });
          case 2:
            data = _context2.v;
            if (res.ok) {
              _context2.n = 3;
              break;
            }
            throw new Error(data.message || 'HTTP ' + res.status);
          case 3:
            return _context2.a(2, data);
        }
      }, _callee2);
    }));
    return _post.apply(this, arguments);
  }
  function logoFormatter(cell) {
    var row = cell.getRow().getData();
    var code = (row.ticker_code || '').trim();
    var logo = (row.company_logo || '').trim();
    var letter = code ? code[0].toUpperCase() : '?';

    // if no logo: fallback avatar
    if (!logo) {
      return "<div class=\"w-8 h-8 rounded-xl bg-base-200 border border-base-300 grid place-items-center font-bold\">".concat(letter, "</div>");
    }

    // local public path: assume already like "images/companies/xx.png" or "/images/.."
    var src = logo.startsWith('/') ? logo : '/' + logo;

    // onerror fallback to letter
    return "\n      <div class=\"w-8 h-8 rounded-xl overflow-hidden border border-base-300 bg-base-200 grid place-items-center\">\n        <img src=\"".concat(src, "\" alt=\"").concat(code, "\" style=\"width:100%;height:100%;object-fit:cover\"\n             onerror=\"this.onerror=null;this.remove(); this.parentNode.textContent='").concat(letter, "'; this.parentNode.className='w-8 h-8 rounded-xl bg-base-200 border border-base-300 grid place-items-center font-bold';\">\n      </div>\n    ");
  }
  var table = new Tabulator('#tblTickers', {
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
      data: "data"
    },
    ajaxURLGenerator: function ajaxURLGenerator(url, config, params) {
      var _el;
      // Tabulator remote params: page, size, sorters...
      var page = params.page || 1;
      var size = params.size || 25;
      var q = (((_el = el('#q')) === null || _el === void 0 ? void 0 : _el.value) || '').trim();
      var sorters = params.sorters || [];
      var s = sorters[0] || {};
      var sort = s.field || 'ticker_code';
      var dir = s.dir || 'asc';
      var usp = new URLSearchParams();
      usp.set('page', page);
      usp.set('size', size);
      usp.set('search', q);
      usp.set('sort', sort);
      usp.set('dir', dir);
      return url + '?' + usp.toString();
    },
    placeholder: 'No data',
    columns: [{
      title: '',
      field: 'company_logo',
      width: 60,
      hozAlign: 'center',
      headerSort: false,
      formatter: logoFormatter
    }, {
      title: 'Ticker',
      field: 'ticker_code',
      width: 110,
      sorter: 'string'
    }, {
      title: 'Company',
      field: 'company_name',
      minWidth: 240,
      sorter: 'string'
    }, {
      title: 'Date',
      field: 'trade_date',
      width: 120,
      sorter: 'string'
    }, {
      title: 'Open',
      field: 'open',
      width: 110,
      hozAlign: 'right',
      formatter: function formatter(c) {
        return fmtNum(c.getValue());
      },
      sorter: 'number'
    }, {
      title: 'High',
      field: 'high',
      width: 110,
      hozAlign: 'right',
      formatter: function formatter(c) {
        return fmtNum(c.getValue());
      },
      sorter: 'number'
    }, {
      title: 'Low',
      field: 'low',
      width: 110,
      hozAlign: 'right',
      formatter: function formatter(c) {
        return fmtNum(c.getValue());
      },
      sorter: 'number'
    }, {
      title: 'Close',
      field: 'close',
      width: 110,
      hozAlign: 'right',
      formatter: function formatter(c) {
        return fmtNum(c.getValue());
      },
      sorter: 'number'
    }, {
      title: 'Vol',
      field: 'volume',
      width: 140,
      hozAlign: 'right',
      formatter: function formatter(c) {
        return fmtInt(c.getValue());
      },
      sorter: 'number'
    }]
  });

  // UI wiring
  (_el2 = el('#btnReload')) === null || _el2 === void 0 || _el2.addEventListener('click', function () {
    return table.replaceData();
  });
  (_el3 = el('#pageSize')) === null || _el3 === void 0 || _el3.addEventListener('change', function (e) {
    table.setPageSize(parseInt(e.target.value, 10) || 25);
    table.setPage(1);
  });
  var tSearch = null;
  (_el4 = el('#q')) === null || _el4 === void 0 || _el4.addEventListener('input', function () {
    clearTimeout(tSearch);
    tSearch = setTimeout(function () {
      table.setPage(1);
      table.replaceData();
    }, 250);
  });
  (_el5 = el('#btnYahoo')) === null || _el5 === void 0 || _el5.addEventListener('click', /*#__PURE__*/function () {
    var _ref = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee(e) {
      var btn, old, res, _t;
      return _regenerator().w(function (_context) {
        while (1) switch (_context.p = _context.n) {
          case 0:
            btn = e.currentTarget;
            btn.disabled = true;
            old = btn.textContent;
            btn.textContent = 'Updating...';
            _context.p = 1;
            _context.n = 2;
            return post(cfg.yahooUrl, {});
          case 2:
            res = _context.v;
            toast(res.message || 'OK');
            _context.n = 4;
            break;
          case 3:
            _context.p = 3;
            _t = _context.v;
            toast('FAILED: ' + _t.message);
          case 4:
            _context.p = 4;
            btn.disabled = false;
            btn.textContent = old;
            return _context.f(4);
          case 5:
            return _context.a(2);
        }
      }, _callee, null, [[1, 3, 4, 5]]);
    }));
    return function (_x3) {
      return _ref.apply(this, arguments);
    };
  }());
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
/******/ 			"/js/screener/ticker": 0,
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
/******/ 	__webpack_require__.O(undefined, ["css/app"], () => (__webpack_require__("./resources/js/screener/ticker.js")))
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["css/app"], () => (__webpack_require__("./resources/css/app.css")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;