<style>
  .tbl{width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:13px;margin:10px 0}
  .tbl th,.tbl td{border:1px solid #333;padding:6px 8px;vertical-align:top}
  .tbl th{background:#f2f2f2;text-align:left}
  .tbl tbody tr:nth-child(even){background:#fafafa}
  .badge{display:inline-block;padding:2px 6px;border:1px solid #333;border-radius:4px;font-size:12px}
  .ok{font-weight:bold}
  .muted{color:#666}
</style>

<h2>Buylist Hari Ini</h2>

<div class="muted">
  Today: <b>{{ $today }}</b> |
  EOD Reference: <b>{{ $eod_date ?? '-' }}</b> |
  Capital: <b>{{ $capital !== null ? number_format($capital, 0, '.', ',') : '-' }}</b>
</div>

<form method="GET" action="" style="margin-top:10px">
  <label>Today (YYYY-mm-dd):</label>
  <input type="text" name="today" value="{{ request('today', $today) }}" style="width:120px">

  <label style="margin-left:10px">Capital:</label>
  <input type="text" name="capital" value="{{ request('capital', $capital) }}" style="width:140px">

  <button type="submit">Refresh</button>
</form>

@if(!empty($note))
  <p class="muted"><b>Note:</b> {{ $note }}</p>
@endif

<hr>

<h3>Recommended Buys (ONLY BUY_OK / BUY_PULLBACK)</h3>

<table class="tbl">
  <thead>
    <tr>
      <th>No</th>
      <th>Ticker</th>
      <th>Signal (EOD)</th>
      <th>Volume Label (EOD)</th>
      <th>Last</th>
      <th>RelVol</th>
      <th>Pos%</th>
      <th>Status</th>
      <th>Reason</th>

      <th>Entry</th>
      <th>Buy</th>
      <th>SL</th>
      <th>TP1</th>
      <th>TP2</th>
      <th>Lots</th>
      <th>Est Cost</th>
      <th>RR(TP2)</th>
    </tr>
  </thead>
  <tbody>
    @forelse($picks as $i => $r)
      <tr>
        <td>{{ $i+1 }}</td>
        <td><b>{{ $r->ticker_code ?? '-' }}</b></td>
        <td>{{ $r->signal_name ?? '-' }}</td>
        <td>{{ $r->volume_label_name ?? '-' }}</td>

        <td>{{ $r->last_price ?? '-' }}</td>
        <td>{{ $r->relvol_today !== null ? number_format($r->relvol_today, 4) : '-' }}</td>
        <td>{{ $r->pos_in_range !== null ? number_format($r->pos_in_range, 2) . '%' : '-' }}</td>

        @php
          $alias = $r->status_alias ?? $r->status ?? '-';
        @endphp
        <td class="ok">{{ $alias }}</td>
        <td>{{ $r->reason ?? '-' }}</td>

        <td>{{ $r->entry_ideal ?? '-' }}</td>
        <td>{{ $r->buy_steps ?? '-' }}</td>
        <td>{{ $r->stop_loss ?? '-' }}</td>
        <td>{{ $r->tp1 ?? '-' }}</td>
        <td>{{ $r->tp2 ?? '-' }}</td>
        <td>{{ $r->lots ?? '-' }}</td>
        <td>{{ isset($r->est_cost) && $r->est_cost !== null ? number_format($r->est_cost, 0, '.', ',') : '-' }}</td>
        <td>{{ $r->rr_tp2 ?? '-' }}</td>
      </tr>
    @empty
      <tr><td colspan="17">Tidak ada rekomendasi BUY_OK / BUY_PULLBACK saat ini.</td></tr>
    @endforelse
  </tbody>
</table>

<hr>

<h3>All Candidates (EOD + Intraday Status)</h3>

<table class="tbl">
  <thead>
    <tr>
      <th>No</th>
      <th>Ticker</th>
      <th>Company</th>
      <th>Signal (EOD)</th>
      <th>Volume Label (EOD)</th>

      <th>Last</th>
      <th>RelVol</th>
      <th>Pos%</th>

      <th>Open</th>
      <th>High</th>
      <th>Low</th>
      <th>EOD Low</th>

      <th>Price OK</th>
      <th>Status</th>
      <th>Reason</th>
      <th>Snapshot At</th>

      <th>Entry</th>
      <th>SL</th>
      <th>TP1</th>
      <th>TP2</th>
      <th>Lots</th>
      <th>RR(TP2)</th>
      <th>Rank</th>
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $i => $r)
      <tr>
        <td>{{ $i+1 }}</td>
        <td><b>{{ $r->ticker_code ?? '-' }}</b></td>
        <td>{{ $r->company_name ?? '-' }}</td>
        <td>{{ $r->signal_name ?? '-' }}</td>
        <td>{{ $r->volume_label_name ?? '-' }}</td>

        <td>{{ $r->last_price ?? '-' }}</td>
        <td>{{ $r->relvol_today !== null ? number_format($r->relvol_today, 4) : '-' }}</td>
        <td>{{ $r->pos_in_range !== null ? number_format($r->pos_in_range, 2) . '%' : '-' }}</td>

        <td>{{ $r->open_price ?? '-' }}</td>
        <td>{{ $r->high_price ?? '-' }}</td>
        <td>{{ $r->low_price ?? '-' }}</td>
        <td>{{ $r->eod_low ?? '-' }}</td>

        <td>{{ isset($r->price_ok) && $r->price_ok ? 'YES' : 'NO' }}</td>

        @php
          $alias = $r->status_alias ?? $r->status ?? '-';
        @endphp
        <td class="ok">{{ $alias }}</td>
        <td>{{ $r->reason ?? '-' }}</td>
        <td>{{ $r->snapshot_at ?? '-' }}</td>

        <td>{{ $r->entry_ideal ?? '-' }}</td>
        <td>{{ $r->stop_loss ?? '-' }}</td>
        <td>{{ $r->tp1 ?? '-' }}</td>
        <td>{{ $r->tp2 ?? '-' }}</td>
        <td>{{ $r->lots ?? '-' }}</td>
        <td>{{ $r->rr_tp2 ?? '-' }}</td>
        <td>{{ $r->rank_score ?? '-' }}</td>
      </tr>
    @empty
      <tr><td colspan="23">No data.</td></tr>
    @endforelse
  </tbody>
</table>
