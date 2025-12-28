<style>
  body{font-family:Arial,sans-serif;}
  .tbl{width:100%;border-collapse:collapse;font-size:13px;margin:10px 0}
  .tbl th,.tbl td{border:1px solid #333;padding:6px 8px;vertical-align:top}
  .tbl th{background:#f2f2f2;text-align:left}
  .tbl tbody tr:nth-child(even){background:#fafafa}
  .ok{font-weight:bold}
  .muted{color:#666}
  .pill{display:inline-block;padding:2px 6px;border:1px solid #333;border-radius:4px;font-size:12px;white-space:nowrap}
  .pill-buy{background:#e9fbe9;border-color:#2e7d32;color:#1b5e20}
  .pill-wait{background:#e8f0fe;border-color:#1a73e8;color:#174ea6}
  .pill-warn{background:#fff4e5;border-color:#f29900;color:#8a5a00}
  .pill-bad{background:#fde8e8;border-color:#d93025;color:#a50e0e}
  .pill-muted{background:#f1f3f4;border-color:#9aa0a6;color:#5f6368}
  .right{text-align:right}
  .center{text-align:center}
  .nowrap{white-space:nowrap}
</style>

<h2>Buylist Hari Ini</h2>

<div class="muted" style="margin-bottom:8px">
  Today: <b>{{ $today }}</b> |
  EOD Reference: <b>{{ $eod_date ?? '-' }}</b> |
  Capital: <b>{{ $capital !== null ? number_format($capital, 0, '.', ',') : '-' }}</b>
</div>

<form method="GET" action="" style="margin:8px 0 14px 0">
  <label class="nowrap">Today (YYYY-mm-dd):</label>
  <input type="text" name="today" value="{{ request('today', $today) }}" style="width:120px">

  <label class="nowrap" style="margin-left:10px">Capital:</label>
  <input type="text" name="capital" value="{{ request('capital', $capital) }}" style="width:140px">

  <button type="submit">Refresh</button>
</form>

@if(!empty($note))
  <p class="muted" style="margin:8px 0"><b>Note:</b> {{ $note }}</p>
@endif

<hr>

<h3>Recommended Buys (ONLY BUY_OK / BUY_PULLBACK)</h3>

<table class="tbl">
  <thead>
    <tr>
      <th class="center">#</th>
      <th>Ticker</th>
      <th>Signal</th>
      <th>Vol Label</th>

      <th class="right">Last</th>
      <th class="right">RelVol</th>
      <th class="right">Pos%</th>

      <th>Status</th>
      <th>Reason</th>

      <th class="right">Entry</th>
      <th>Buy Steps</th>
      <th class="right">SL</th>
      <th class="right">TP1</th>
      <th class="right">TP2</th>

      <th class="right">BE</th>
      <th class="right">Out (Buy+Fee)</th>
      <th class="right">Profit TP2 (Net)</th>
      <th class="right">RR TP2 (Net)</th>

      <th class="right">Risk%</th>
      <th class="right">RR(TP2)</th>

      <th class="right">Lots</th>
      <th class="right">Est Cost</th>
    </tr>
  </thead>
  <tbody>
    @forelse($picks as $i => $r)
      @php
        $alias = $r->status_alias ?? $r->status ?? '-';
        $riskVal = $r->risk_pct_real ?? $r->risk_pct ?? null;
        $risk = $riskVal !== null ? number_format($riskVal * 100, 2) . '%' : '-';
        $rr    = $r->rr_tp2 ?? '-';
        $statusKey = (string) ($r->status ?? $alias ?? '');
        $pillClass = 'pill pill-muted';

        if (in_array($statusKey, ['BUY_OK','BUY_PULLBACK'], true)) {
            $pillClass = 'pill pill-buy';
        } elseif ($statusKey === 'EXPIRED') {
            $pillClass = 'pill pill-muted';
        } elseif (strpos($statusKey, 'WAIT_') === 0) {
            // WAIT_* (umumnya nunggu kondisi)
            $pillClass = in_array($statusKey, ['WAIT_PULLBACK'], true) ? 'pill pill-warn' : 'pill pill-wait';
        } elseif (in_array($statusKey, ['LUNCH_WINDOW'], true)) {
            $pillClass = 'pill pill-warn';
        } elseif (strpos($statusKey, 'SKIP_') === 0 || in_array($statusKey, ['RR_TOO_LOW','RISK_TOO_WIDE','CAPITAL_TOO_SMALL','STALE_INTRADAY','NO_INTRADAY','LATE_ENTRY','SKIP_EOD_GUARD'], true)) {
            $pillClass = 'pill pill-bad';
        } else {
            $pillClass = 'pill pill-warn';
        }
      @endphp
      <tr>
        <td class="center">{{ $i+1 }}</td>
        <td class="ok">{{ $r->ticker_code ?? '-' }}</td>
        <td>{{ $r->signal_name ?? ($r->signal_code ?? '-') }}</td>
        <td>{{ $r->volume_label_name ?? ($r->volume_label_code ?? '-') }}</td>

        <td class="right">{{ $r->last_price ?? '-' }}</td>
        <td class="right">{{ $r->relvol_today !== null ? number_format($r->relvol_today, 4) : '-' }}</td>
        <td class="right">{{ $r->pos_in_range !== null ? number_format($r->pos_in_range, 2) . '%' : '-' }}</td>

        <td class="ok"><span class="{{ $pillClass }}">{{ $alias }}</span></td>
        <td>{{ $r->reason ?? '-' }}</td>

        <td class="right">{{ $r->entry_ideal ?? '-' }}</td>
        <td>{{ $r->buy_steps ?? '-' }}</td>
        <td class="right">{{ $r->stop_loss ?? '-' }}</td>
        <td class="right">{{ $r->tp1 ?? '-' }}</td>
        <td class="right">{{ $r->tp2 ?? '-' }}</td>

        <td class="right">{{ $r->break_even ?? '-' }}</td>
        <td class="right">{{ isset($r->est_out_total) && $r->est_out_total !== null ? number_format($r->est_out_total, 0, '.', ',') : '-' }}</td>
        <td class="right">{{ isset($r->est_profit_tp2) && $r->est_profit_tp2 !== null ? number_format($r->est_profit_tp2, 0, '.', ',') : '-' }}</td>
        <td class="right">{{ $r->rr_net_tp2 ?? '-' }}</td>

        <td class="right">{{ $risk }}</td>
        <td class="right">{{ $rr }}</td>

        <td class="right">{{ $r->lots ?? '-' }}</td>
        <td class="right">
          {{ isset($r->est_cost) && $r->est_cost !== null ? number_format($r->est_cost, 0, '.', ',') : '-' }}
        </td>
      </tr>
    @empty
      <tr><td colspan="22">Tidak ada rekomendasi BUY_OK / BUY_PULLBACK saat ini.</td></tr>
    @endforelse
  </tbody>
</table>

<hr>

<h3>All Candidates (EOD + Intraday Status)</h3>

<table class="tbl">
  <thead>
    <tr>
      <th class="center">#</th>
      <th>Ticker</th>
      <th>Company</th>
      <th>Signal</th>
      <th>Vol Label</th>

      <th class="right">Last</th>
      <th class="right">RelVol</th>
      <th class="right">Pos%</th>

      <th class="right">Open</th>
      <th class="right">High</th>
      <th class="right">Low</th>
      <th class="right">EOD Low</th>

      <th class="center">Price OK</th>
      <th>Status</th>
      <th>Reason</th>
      <th class="nowrap">Snapshot At</th>

      <th class="right">Entry</th>
      <th class="right">SL</th>
      <th class="right">TP1</th>
      <th class="right">TP2</th>
      <th class="right">Risk%</th>
      <th class="right">RR(TP2)</th>

      <th class="right">Lots</th>
      <th class="right">Rank</th>
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $i => $r)
      @php
        $alias = $r->status_alias ?? $r->status ?? '-';
        $riskVal = $r->risk_pct_real ?? $r->risk_pct ?? null;
        $risk = $riskVal !== null ? number_format($riskVal * 100, 2) . '%' : '-';
        $rr    = $r->rr_tp2 ?? '-';
        $statusKey = (string) ($r->status ?? $alias ?? '');
        $pillClass = 'pill pill-muted';

        if (in_array($statusKey, ['BUY_OK','BUY_PULLBACK'], true)) {
            $pillClass = 'pill pill-buy';
        } elseif ($statusKey === 'EXPIRED') {
            $pillClass = 'pill pill-muted';
        } elseif (strpos($statusKey, 'WAIT_') === 0) {
            // WAIT_* (umumnya nunggu kondisi)
            $pillClass = in_array($statusKey, ['WAIT_PULLBACK'], true) ? 'pill pill-warn' : 'pill pill-wait';
        } elseif (in_array($statusKey, ['LUNCH_WINDOW'], true)) {
            $pillClass = 'pill pill-warn';
        } elseif (strpos($statusKey, 'SKIP_') === 0 || in_array($statusKey, ['RR_TOO_LOW','RISK_TOO_WIDE','CAPITAL_TOO_SMALL','STALE_INTRADAY','NO_INTRADAY','LATE_ENTRY','SKIP_EOD_GUARD'], true)) {
            $pillClass = 'pill pill-bad';
        } else {
            $pillClass = 'pill pill-warn';
        }
      @endphp
      <tr>
        <td class="center">{{ $i+1 }}</td>
        <td class="ok">{{ $r->ticker_code ?? '-' }}</td>
        <td>{{ $r->company_name ?? '-' }}</td>
        <td>{{ $r->signal_name ?? ($r->signal_code ?? '-') }}</td>
        <td>{{ $r->volume_label_name ?? ($r->volume_label_code ?? '-') }}</td>

        <td class="right">{{ $r->last_price ?? '-' }}</td>
        <td class="right">{{ $r->relvol_today !== null ? number_format($r->relvol_today, 4) : '-' }}</td>
        <td class="right">{{ $r->pos_in_range !== null ? number_format($r->pos_in_range, 2) . '%' : '-' }}</td>

        <td class="right">{{ $r->open_price ?? '-' }}</td>
        <td class="right">{{ $r->high_price ?? '-' }}</td>
        <td class="right">{{ $r->low_price ?? '-' }}</td>
        <td class="right">{{ $r->eod_low ?? '-' }}</td>

        <td class="center">{{ !empty($r->price_ok) ? 'YES' : 'NO' }}</td>

        <td class="ok"><span class="{{ $pillClass }}">{{ $alias }}</span></td>
        <td>{{ $r->reason ?? '-' }}</td>
        <td class="nowrap">{{ $r->snapshot_at ?? '-' }}</td>

        <td class="right">{{ $r->entry_ideal ?? '-' }}</td>
        <td class="right">{{ $r->stop_loss ?? '-' }}</td>
        <td class="right">{{ $r->tp1 ?? '-' }}</td>
        <td class="right">{{ $r->tp2 ?? '-' }}</td>

        <td class="right">{{ $risk }}</td>
        <td class="right">{{ $rr }}</td>

        <td class="right">{{ $r->lots ?? '-' }}</td>
        <td class="right">{{ $r->rank_score ?? '-' }}</td>
      </tr>
    @empty
      <tr><td colspan="24">No data.</td></tr>
    @endforelse
  </tbody>
</table>
