<div class="hidden lg:block lg:sticky lg:top-[92px]">
  <div class="ui-surface p-4">
    <div class="flex items-center justify-between">
      <div class="font-semibold">Detail</div>
      <button id="btn-copy-plan" class="btn btn-primary btn-xs btn-cta hover:bg-primary/90 hover:text-primary-content">COPY</button>
    </div>

    <div class="mt-2 flex items-center gap-3">
      <img id="p-logo" class="hidden w-10 h-10 rounded-full border border-base-300 shadow-sm object-cover" alt="logo">
      <div class="text-2xl font-extrabold tracking-wide" id="p-ticker">—</div>
    </div>
    <div class="mt-2 flex flex-wrap gap-2" id="p-badges"></div>

    <div class="divider my-3 opacity-60"></div>

    <div class="grid grid-cols-2 gap-2 text-sm">
      <div><span class="opacity-70">Last:</span> <span class="font-semibold" id="p-last">—</span></div>
      <div><span class="opacity-70">Rank:</span> <span class="font-semibold" id="p-rank">—</span></div>
      <div><span class="opacity-70">Entry:</span> <span class="font-semibold" id="p-entry">—</span></div>
      <div><span class="opacity-70">RR:</span> <span class="font-semibold" id="p-rr">—</span></div>
      <div><span class="opacity-70">SL:</span> <span class="font-semibold" id="p-sl">—</span></div>
      <div><span class="opacity-70">TP:</span> <span class="font-semibold" id="p-tp">—</span></div>
    </div>

    <div class="mt-3">
      <div class="text-sm opacity-70 mb-1">Reason</div>
      <div class="detail-kv">
        <div class="v" id="p-reason">—</div>
      </div>
      <div class="mt-2 text-sm">
        <div><span class="opacity-70">snapshot_at:</span> <span id="p-snapshot">—</span></div>
        <div><span class="opacity-70">last_bar_at:</span> <span id="p-lastbar">—</span></div>
      </div>
    </div>

    <details class="collapse collapse-arrow border border-base-300/60 bg-base-100/60 mt-3">
      <summary class="collapse-title text-sm font-medium">Raw data</summary>
      <div class="collapse-content">
        <pre id="p-json" class="text-xs whitespace-pre-wrap opacity-90">—</pre>
      </div>
    </details>
  </div>
</div>