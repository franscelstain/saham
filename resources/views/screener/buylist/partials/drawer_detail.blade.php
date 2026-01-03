<div id="drawer" class="fixed inset-0 hidden z-50">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" id="drawer-backdrop"></div>

  <div class="absolute right-0 top-0 h-full w-full sm:w-[440px] bg-base-200 border-l border-base-300/60 p-4 overflow-auto">
    <div class="ui-surface p-4">
      <div class="flex items-center justify-between">
        <div class="font-semibold">Detail</div>
        <button id="drawer-close" class="btn btn-ghost btn-sm">Close</button>
      </div>

      <div class="mt-3 space-y-3">
        <div class="text-2xl font-extrabold tracking-wide" id="d-ticker">—</div>
        <div class="flex flex-wrap gap-2" id="d-badges"></div>

        <div class="divider my-2 opacity-60"></div>

        <div class="grid grid-cols-2 gap-2 text-sm">
          <div><span class="opacity-70">Rank:</span> <span class="font-semibold" id="d-rank">—</span></div>
          <div><span class="opacity-70">Entry:</span> <span class="font-semibold" id="d-entry">—</span></div>
          <div><span class="opacity-70">RR:</span> <span class="font-semibold" id="d-rr">—</span></div>
          <div><span class="opacity-70">SL:</span> <span class="font-semibold" id="d-sl">—</span></div>
          <div><span class="opacity-70">TP:</span> <span class="font-semibold" id="d-tp">—</span></div>
        </div>

        <div>
          <div class="text-sm opacity-70 mb-1">Reason</div>
          <div class="detail-kv">
            <div class="v" id="d-reason">—</div>
          </div>
        </div>

        <details class="collapse collapse-arrow border border-base-300/60 bg-base-100/60">
          <summary class="collapse-title text-sm font-medium">Raw data</summary>
          <div class="collapse-content">
            <pre id="d-json" class="text-xs whitespace-pre-wrap opacity-90">—</pre>
          </div>
        </details>
      </div>
    </div>
  </div>
</div>
