<div class="sticky top-0 z-40 ui-header">
  <div class="h-1 bg-gradient-to-r from-primary via-info to-accent"></div>

  <div class="px-4 py-3 flex items-center gap-3">
    <div class="flex items-center gap-3">
      <img
        src="{{ asset('images/brand/tradeaxis.png') }}"
        alt="TradeAxis"
        class="w-10 h-10 rounded-2xl shadow-sm"
      >
      <div class="leading-tight">
        <div class="font-semibold tracking-wide">{{ $title ?? 'Screener' }}</div>
        <div class="text-xs opacity-70">Buylist & status monitor</div>
      </div>
    </div>

    {{-- Main menu --}}
    <div class="ml-2 hidden md:flex items-center gap-2">
      <a href="{{ url('/ticker') }}"
         class="btn btn-sm {{ request()->is('ticker') ? 'btn-secondary' : 'btn-ghost' }}">
        Ticker
      </a>
      <a href="{{ url('/watchlist') }}"
         class="btn btn-sm {{ request()->is('watchlist*') ? 'btn-secondary' : 'btn-ghost' }}">
        Watchlist
      </a>
      <a href="{{ url('/portfolio') }}"
         class="btn btn-sm {{ request()->is('portfolio*') ? 'btn-secondary' : 'btn-ghost' }}">
        Portfolio
      </a>
    </div>

    <div class="flex-1"></div>

    <label class="input input-bordered flex items-center gap-2 bg-white text-base-content border-white/30 shadow-md focus-within:ring-2 focus-within:ring-primary/30 w-[360px] max-w-full">
      <svg class="w-4 h-4 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="11" cy="11" r="8"></circle><path d="M21 21l-4.3-4.3"></path>
      </svg>
      <input id="global-search" type="text" class="grow bg-transparent outline-none" placeholder="Cari ticker… (/)">
      <kbd class="kbd kbd-sm opacity-70">/</kbd>
    </label>

    <button id="btn-panel" class="btn btn-ghost btn-sm lg:hidden">PANEL</button>
    <button id="btn-refresh" class="btn btn-primary btn-sm btn-cta hover:bg-primary/90 hover:text-primary-content">REFRESH</button>
  </div>
</div>