<div id="drawer" class="fixed inset-0 hidden z-50">
  <div class="absolute inset-0 bg-black/30" id="drawer-backdrop"></div>

  <div class="absolute right-0 top-0 h-full w-full sm:w-[440px] bg-base-100 border-l border-base-300 p-4 overflow-auto">
    <div class="flex items-center justify-between">
      <div class="font-semibold">Detail</div>
      <button id="drawer-close" class="btn btn-ghost btn-sm">Close</button>
    </div>

    <div class="mt-3 space-y-3">
      <div class="text-2xl font-bold" id="d-ticker">—</div>
      <div class="flex flex-wrap gap-2" id="d-badges"></div>

      <div class="divider my-2"></div>

      <div class="grid grid-cols-2 gap-2 text-sm">
        <div><span class="opacity-60">Last:</span> <span id="d-last">—</span></div>
        <div><span class="opacity-60">Entry:</span> <span id="d-entry">—</span></div>
        <div><span class="opacity-60">SL:</span> <span id="d-sl">—</span></div>
        <div><span class="opacity-60">TP:</span> <span id="d-tp">—</span></div>
        <div><span class="opacity-60">RR:</span> <span id="d-rr">—</span></div>
        <div><span class="opacity-60">Rank:</span> <span id="d-rank">—</span></div>
      </div>

      <div>
        <div class="text-sm opacity-60 mb-1">Reason</div>
        <div class="text-sm whitespace-pre-wrap" id="d-reason">—</div>
      </div>

      <details class="collapse collapse-arrow border border-base-300 bg-base-100">
        <summary class="collapse-title text-sm font-medium">Raw data</summary>
        <div class="collapse-content">
          <pre id="d-json" class="text-xs whitespace-pre-wrap">—</pre>
        </div>
      </details>
    </div>
  </div>
</div>
