<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
  <button class="card bg-base-100 border border-base-300 shadow-sm hover:shadow kpi kpi-card" data-kpi="BUY">
    <div class="p-3">
      <div class="kpi-top">
        <div class="text-xs opacity-60 font-semibold">BUY (OK+PB)</div>
        <div class="kpi-ico">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M13 2L3 14h7l-1 8 12-14h-7l-1-6z"/>
          </svg>
        </div>
      </div>
      <div class="text-3xl font-extrabold mt-1" id="kpi-buy">0</div>
    </div>
  </button>

  <button class="card bg-base-100 border border-base-300 shadow-sm hover:shadow kpi kpi-card" data-kpi="WAIT">
    <div class="p-3">
      <div class="kpi-top">
        <div class="text-xs opacity-60 font-semibold">WAIT</div>
        <div class="kpi-ico wait">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 8v5l4 2-.9 1.8L10 14V8h2zm0-6a10 10 0 100 20 10 10 0 000-20z"/>
          </svg>
        </div>
      </div>
      <div class="text-3xl font-extrabold mt-1" id="kpi-wait">0</div>
    </div>
  </button>

  <button class="card bg-base-100 border border-base-300 shadow-sm hover:shadow kpi kpi-card" data-kpi="SKIP">
    <div class="p-3">
      <div class="kpi-top">
        <div class="text-xs opacity-60 font-semibold">SKIP</div>
        <div class="kpi-ico skip">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M3 5.27L4.28 4 20 19.72 18.73 21l-2.2-2.2A9.96 9.96 0 0112 22 10 10 0 012 12c0-2.1.65-4.05 1.76-5.66L3 5.27zM12 2a10 10 0 017.07 17.07l-1.42-1.42A8 8 0 0012 4a7.9 7.9 0 00-5.65 2.34L4.93 4.93A9.97 9.97 0 0112 2z"/>
          </svg>
        </div>
      </div>
      <div class="text-3xl font-extrabold mt-1" id="kpi-skip">0</div>
    </div>
  </button>

  <button class="card bg-base-100 border border-base-300 shadow-sm hover:shadow kpi kpi-card" data-kpi="STALE">
    <div class="p-3">
      <div class="kpi-top">
        <div class="text-xs opacity-60 font-semibold">STALE/NO</div>
        <div class="kpi-ico stale">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
          </svg>
        </div>
      </div>
      <div class="text-3xl font-extrabold mt-1" id="kpi-stale">0</div>
    </div>
  </button>

  <button class="card bg-base-100 border border-base-300 shadow-sm hover:shadow kpi kpi-card" data-kpi="ALL">
    <div class="p-3">
      <div class="kpi-top">
        <div class="text-xs opacity-60 font-semibold">ALL</div>
        <div class="kpi-ico all">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
            <path d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/>
          </svg>
        </div>
      </div>
      <div class="text-3xl font-extrabold mt-1" id="kpi-all">0</div>
    </div>
  </button>

  <div class="card bg-base-100 border border-base-300 shadow-sm rounded-2xl">
    <div class="p-3">
      <div class="text-xs opacity-60 font-semibold">Selected</div>
      <div class="text-sm font-semibold truncate mt-1" id="kpi-selected">—</div>
    </div>
  </div>
</div>
