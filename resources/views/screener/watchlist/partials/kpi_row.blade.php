<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
  <button class="kpi kpi-card p-3 text-left border border-base-300/60" data-kpi="BUY">
    <div class="kpi-top">
      <div class="text-xs opacity-70 font-semibold">BUY (OK+PB)</div>
      <div class="kpi-ico w-9 h-9 rounded-xl grid place-items-center">
        <x-icon.bolt class="w-5 h-5" />
      </div>
    </div>
    <div class="text-3xl font-extrabold mt-1" id="kpi-buy">0</div>
  </button>

  <button class="kpi kpi-card p-3 text-left border border-base-300/60" data-kpi="WAIT">
    <div class="kpi-top">
      <div class="text-xs opacity-70 font-semibold">WAIT</div>
      <div class="kpi-ico w-9 h-9 rounded-xl grid place-items-center">
        <x-icon.clock class="w-5 h-5" />
      </div>
    </div>
    <div class="text-3xl font-extrabold mt-1" id="kpi-wait">0</div>
  </button>

  <button class="kpi kpi-card p-3 text-left border border-base-300/60" data-kpi="SKIP">
    <div class="kpi-top">
      <div class="text-xs opacity-70 font-semibold">SKIP</div>
      <div class="kpi-ico w-9 h-9 rounded-xl grid place-items-center">
        <x-icon.ban class="w-5 h-5" />
      </div>
    </div>
    <div class="text-3xl font-extrabold mt-1" id="kpi-skip">0</div>
  </button>

  <button class="kpi kpi-card p-3 text-left border border-base-300/60" data-kpi="STALE">
    <div class="kpi-top">
      <div class="text-xs opacity-70 font-semibold">STALE/NO</div>
      <div class="kpi-ico w-9 h-9 rounded-xl grid place-items-center">
        <x-icon.alert class="w-5 h-5" />
      </div>
    </div>
    <div class="text-3xl font-extrabold mt-1" id="kpi-stale">0</div>
  </button>

  <button class="kpi kpi-card p-3 text-left border border-base-300/60" data-kpi="ALL">
    <div class="kpi-top">
      <div class="text-xs opacity-70 font-semibold">ALL</div>
      <div class="kpi-ico w-9 h-9 rounded-xl grid place-items-center">
        <x-icon.grid class="w-5 h-5" />
      </div>
    </div>
    <div class="text-3xl font-extrabold mt-1" id="kpi-all">0</div>
  </button>

  <div class="ui-surface p-3 border border-violet-300/70 bg-violet-200/40">
    <div class="kpi-top">
      <div class="text-xs opacity-70 font-semibold">Selected</div>
      <div class="w-9 h-9 rounded-xl grid place-items-center ring-1 ring-violet-300/70 bg-violet-100/70 text-violet-700">
        <x-icon.grid class="w-5 h-5" />
      </div>
    </div>
    <div class="text-sm font-semibold truncate mt-1" id="kpi-selected">—</div>
  </div>
</div>
