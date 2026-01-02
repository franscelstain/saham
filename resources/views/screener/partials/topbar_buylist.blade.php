<div class="sticky top-0 z-40 bg-base-100 border-b border-base-300 shadow-sm">
  <div class="h-1 bg-primary"></div>

  <div class="px-4 py-3 flex items-center gap-3">
    <div class="flex items-center gap-2">
      <div class="w-10 h-10 rounded-2xl bg-primary text-primary-content grid place-items-center font-bold">S</div>
      <div class="leading-tight">
        <div class="font-semibold">{{ $title ?? 'Screener' }}</div>
        <div class="text-xs opacity-60">ajaib-ish blue</div>
      </div>
    </div>

    <div class="flex-1"></div>

    <label class="input input-bordered flex items-center gap-2 bg-base-100 w-[360px] max-w-full">
      <svg class="w-4 h-4 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"></circle><path d="M21 21l-4.3-4.3"></path>
      </svg>
      <input id="global-search" type="text" class="grow" placeholder="Cari ticker… (/)">
      <kbd class="kbd kbd-sm">/</kbd>
    </label>

    <button id="btn-panel" class="btn btn-ghost btn-sm lg:hidden">PANEL</button>
    <button id="btn-refresh" class="btn btn-primary btn-sm btn-cta">REFRESH</button>
  </div>
</div>
