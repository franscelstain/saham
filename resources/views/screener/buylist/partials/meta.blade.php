<div class="flex flex-wrap items-center gap-2">
  <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-2xl border border-base-300/60 bg-base-100/70 text-sm">
    <span class="w-2 h-2 rounded-full bg-info"></span>
    <span class="opacity-80">Today:</span>
    <span class="font-semibold">{{ $today ?? '-' }}</span>
  </div>

  <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-2xl border border-base-300/60 bg-base-100/70 text-sm">
    <span class="w-2 h-2 rounded-full bg-warning"></span>
    <span class="opacity-80">EOD:</span>
    <span class="font-semibold">{{ $eodDate ?? '-' }}</span>
  </div>

  <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-2xl border border-base-300/60 bg-base-100/70 text-sm">
    <span class="w-2 h-2 rounded-full bg-accent"></span>
    <span class="opacity-80">Capital:</span>
    <span class="font-semibold">{{ $capital ?? '-' }}</span>
  </div>

  <div class="flex-1"></div>

  <label class="label cursor-pointer gap-2 py-0">
    <span class="text-sm opacity-80">Auto refresh</span>
    <input id="auto-refresh" type="checkbox" class="toggle toggle-primary" />
  </label>

  <select id="auto-interval" class="select select-bordered select-sm bg-base-100/70 border-base-300/60">
    <option value="30">30s</option>
    <option value="60" selected>60s</option>
    <option value="120">120s</option>
  </select>

  <div class="text-xs opacity-70" id="meta-server">—</div>
</div>
