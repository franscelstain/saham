<div class="hidden lg:block lg:sticky lg:top-[92px]">
  <div class="card bg-base-100 border border-base-300 shadow-sm rounded-2xl">
    <div class="card-body">
      <div class="flex items-center justify-between">
        <div class="font-semibold">Detail</div>
        <button id="btn-copy-plan" class="btn btn-primary btn-xs btn-cta">COPY</button>
      </div>

      <div class="mt-2 text-2xl font-bold" id="p-ticker">—</div>
      <div class="mt-2 flex flex-wrap gap-2" id="p-badges"></div>

      <div class="divider my-3"></div>

      <div class="grid grid-cols-2 gap-2 text-sm">
        <div><span class="opacity-60">Last:</span> <span id="p-last">—</span></div>
        <div><span class="opacity-60">Rank:</span> <span id="p-rank">—</span></div>
        <div><span class="opacity-60">Entry:</span> <span id="p-entry">—</span></div>
        <div><span class="opacity-60">RR:</span> <span id="p-rr">—</span></div>
        <div><span class="opacity-60">SL:</span> <span id="p-sl">—</span></div>
        <div><span class="opacity-60">TP:</span> <span id="p-tp">—</span></div>
      </div>

      <div class="mt-3">
        <div class="text-sm opacity-60 mb-1">Reason</div>
        <div class="text-sm whitespace-pre-wrap" id="p-reason">—</div>
      </div>

      <div class="mt-3">
        <div class="text-sm opacity-60 mb-1">Freshness</div>
        <div class="text-sm">
          <div><span class="opacity-60">snapshot_at:</span> <span id="p-snapshot">—</span></div>
          <div><span class="opacity-60">last_bar_at:</span> <span id="p-lastbar">—</span></div>
        </div>
      </div>

      <details class="collapse collapse-arrow border border-base-300 bg-base-100 mt-3">
        <summary class="collapse-title text-sm font-medium">Raw data</summary>
        <div class="collapse-content">
          <pre id="p-json" class="text-xs whitespace-pre-wrap">—</pre>
        </div>
      </details>
    </div>
  </div>
</div>
