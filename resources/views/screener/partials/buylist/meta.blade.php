<div class="flex flex-wrap items-center gap-2">
  <div class="meta-chip">
    <span class="dot"></span> Today: {{ $today ?? '-' }}
  </div>
  <div class="meta-chip eod">
    <span class="dot"></span> EOD: {{ $eodDate ?? '-' }}
  </div>
  <div class="meta-chip cap">
    <span class="dot"></span> Capital: {{ $capital ?? '-' }}
  </div>

  <div class="flex-1"></div>

  <label class="label cursor-pointer gap-2">
    <span class="text-sm">Auto refresh</span>
    <input id="auto-refresh" type="checkbox" class="toggle toggle-primary" />
  </label>

  <select id="auto-interval" class="select select-bordered select-sm">
    <option value="30">30s</option>
    <option value="60" selected>60s</option>
    <option value="120">120s</option>
  </select>

  <div class="text-xs opacity-60" id="meta-server">—</div>
</div>
