<div class="hidden lg:block lg:sticky lg:top-[92px]">
  <div class="ui-surface p-4">
    <div class="flex items-center justify-between">
      <div class="font-semibold">Detail</div>
      <div class="flex items-center gap-2">
        <button id="btn-update-intraday" class="btn btn-outline btn-xs">UPDATE</button>
        <button id="btn-copy-plan" class="btn btn-primary btn-xs btn-cta hover:bg-primary/90 hover:text-primary-content">COPY</button>
      </div>
    </div>

    <div class="mt-2 flex items-center gap-3">
      <!-- Logo (fallback huruf pertama kalau logo kosong/error) -->
      <div class="w-12 h-12 rounded-full overflow-hidden ring-1 ring-base-300 bg-base-200 grid place-items-center">
        <div id="p-logo-fallback" class="w-full h-full grid place-items-center bg-primary/10 text-primary font-extrabold">
          ?
        </div>
        <img id="p-logo-img" class="w-full h-full object-cover hidden" alt="logo">
      </div>

      <div class="min-w-0">
        <div class="text-2xl font-extrabold tracking-wide leading-tight" id="p-ticker">—</div>
        <div class="text-sm opacity-70 truncate" id="p-name">—</div>
        <div class="text-3xl font-extrabold mt-1 leading-none" id="p-price">—</div>
      </div>
    </div>
    <div class="mt-2 flex flex-wrap gap-2" id="p-badges"></div>    

    <div class="mt-3 grid gap-3">
      <div class="detail-kv">
        <div class="flex items-center justify-between">
          <div class="k font-medium">OHLC <span class="opacity-70" id="p-ohlc-src">—</span></div>
        </div>
        <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
          <div><span class="opacity-70">Open</span> <span class="font-semibold" id="p-o">—</span></div>
          <div><span class="opacity-70">High</span> <span class="font-semibold" id="p-h">—</span></div>
          <div><span class="opacity-70">Low</span> <span class="font-semibold" id="p-l">—</span></div>
          <div><span class="opacity-70">Close</span> <span class="font-semibold" id="p-c">—</span></div>
        </div>
      </div>

      <div class="detail-kv">
        <div class="k font-medium">Market</div>
        <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
          <div><span class="opacity-70">RelVol</span> <span class="font-semibold" id="p-relvol">—</span></div>
          <div><span class="opacity-70">Pos%</span> <span class="font-semibold" id="p-pos">—</span></div>
          <div><span class="opacity-70">EOD Low</span> <span class="font-semibold" id="p-eodlow">—</span></div>
          <div><span class="opacity-70">Price OK</span> <span class="font-semibold" id="p-priceok">—</span></div>
        </div>
      </div>

      <div class="detail-kv">
        <div class="k font-medium">Plan</div>
        <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
          <div><span class="opacity-70">Entry</span> <span class="font-semibold" id="p-entry">—</span></div>
          <div><span class="opacity-70">Buy Steps</span> <span class="font-semibold" id="p-steps">—</span></div>
          <div><span class="opacity-70">SL</span> <span class="font-semibold" id="p-sl">—</span></div>
          <div><span class="opacity-70">TP1</span> <span class="font-semibold" id="p-tp1">—</span></div>
          <div><span class="opacity-70">TP2</span> <span class="font-semibold" id="p-tp2">—</span></div>
          <div><span class="opacity-70">BE</span> <span class="font-semibold" id="p-be">—</span></div>
          <div><span class="opacity-70">Out</span> <span class="font-semibold" id="p-out">—</span></div>
          <div><span class="opacity-70">Lots</span> <span class="font-semibold" id="p-lots">—</span></div>
          <div><span class="opacity-70">Est Cost</span> <span class="font-semibold" id="p-cost">—</span></div>
        </div>
      </div>

      <div class="detail-kv">
        <div class="k font-medium">Risk / Result</div>
        <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
          <div><span class="opacity-70">RR</span> <span class="font-semibold" id="p-rr">—</span></div>
          <div><span class="opacity-70">Risk%</span> <span class="font-semibold" id="p-risk">—</span></div>
          <div><span class="opacity-70">Profit TP2 (Net)</span> <span class="font-semibold" id="p-profit2">—</span></div>
          <div><span class="opacity-70">RR TP2 (Net)</span> <span class="font-semibold" id="p-rr2net">—</span></div>
          <div><span class="opacity-70">RR (TP2)</span> <span class="font-semibold" id="p-rr2">—</span></div>
        </div>
      </div>

      <div class="detail-kv">
        <div class="k font-medium">Meta</div>
        <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
          <div><span class="opacity-70">Rank</span> <span class="font-semibold" id="p-rank">—</span></div>
          <div><span class="opacity-70">Snapshot At</span> <span class="font-semibold" id="p-snapshot">—</span></div>
          <div><span class="opacity-70">Last Bar At</span> <span class="font-semibold" id="p-lastbar">—</span></div>
        </div>
      </div>

      <div class="detail-kv">
        <div class="k font-medium">Reason</div>
        <div class="mt-2">
          <div class="v" id="p-reason">—</div>
        </div>
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