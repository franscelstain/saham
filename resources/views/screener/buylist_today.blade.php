<style>
  .tbl{width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:13px}
  .tbl th,.tbl td{border:1px solid #333;padding:6px 8px;vertical-align:top;white-space:nowrap}
  .tbl th{background:#f2f2f2;text-align:left;position:sticky;top:0;z-index:2}
  .tbl tbody tr:nth-child(even){background:#fafafa}

  .badge{display:inline-block;padding:2px 6px;border-radius:4px;font-weight:bold}
  .b-ok{background:#e7f7ee;border:1px solid #2e7d32;color:#2e7d32}
  .b-wait{background:#fff7e6;border:1px solid #b26a00;color:#b26a00}
  .b-skip{background:#ffe7e7;border:1px solid #b00020;color:#b00020}
  .b-info{background:#e8f1ff;border:1px solid #1a73e8;color:#1a73e8}

  .muted{color:#666}
</style>

@php
  // Helper kecil untuk badge status
  $statusClass = function($s){
    if ($s === 'BUY_OK') return 'b-ok';
    if (in_array($s, ['WAIT_PULLBACK','WAIT_STRENGTH','WAIT_REL_VOL','WAIT_MARKET_OPEN','WAIT'], true)) return 'b-wait';
    if (in_array($s, ['SKIP_BREAKDOWN','SKIP_GAP_DOWN','EXPIRED'], true)) return 'b-skip';
    return 'b-info';
  };
@endphp

<h3 style="margin:0 0 8px 0;">
  Buylist Hari Ini
  <span class="muted">
    (Today: {{ $today }} | EOD Reference: {{ $eod_date ?? '-' }} | Modal: {{ isset($capital) && $capital ? number_format((float)$capital,0,',','.') : '-' }})
  </span>
</h3>

<table class="tbl">
  <thead>
    <tr>
      <th>No</th>
      <th>Rank</th>
      <th>Ticker</th>

      <th>Signal (EOD)</th>
      <th>Volume Label (EOD)</th>

      <th>Last</th>
      <th>Open</th>
      <th>High</th>
      <th>Low</th>
      <th>Low(EOD)</th>

      <th>RelVol</th>
      <th>Pos%</th>
      <th>Price OK</th>

      <th>Status</th>
      <th>Reason</th>

      <th>Score</th>
      <th>RR(TP2)</th>

      <th>Snapshot At</th>

      <th>Entry Ideal</th>
      <th>Buy</th>

      <th>Lots</th>
      <th>Est Cost</th>
      <th>Est Risk</th>
      <th>Risk%</th>

      <th>SL</th>
      <th>TP1</th>
      <th>TP2</th>
      <th>Sell</th>
    </tr>
  </thead>

  <tbody>
    @forelse($rows as $i => $r)
      <tr>
        <td>{{ $i+1 }}</td>

        <td>
          @if(isset($r->rank_score))
            <b>{{ $r->rank_score }}</b>
          @else
            -
          @endif
        </td>

        <td><b>{{ $r->ticker_code }}</b></td>

        <td>{{ $r->signal_name ?? $r->signal_code ?? '-' }}</td>
        <td>{{ $r->volume_label_name ?? $r->volume_label_code ?? '-' }}</td>

        <td>{{ $r->last_price ?? '-' }}</td>
        <td>{{ $r->open_price ?? '-' }}</td>
        <td>{{ $r->high_price ?? '-' }}</td>
        <td>{{ $r->low_price ?? '-' }}</td>
        <td>{{ $r->eod_low ?? '-' }}</td>

        <td>
          @if($r->relvol_today !== null)
            {{ number_format((float)$r->relvol_today, 4) }}
          @else
            -
          @endif
        </td>

        <td>
          @if($r->pos_in_range !== null)
            {{ number_format((float)$r->pos_in_range, 2) }}%
          @else
            -
          @endif
        </td>

        <td>{{ !empty($r->price_ok) ? 'YES' : 'NO' }}</td>

        <td>
          <span class="badge {{ $statusClass($r->status ?? '') }}">
            {{ $r->status ?? '-' }}
          </span>
        </td>

        <td style="white-space:normal;min-width:180px">
          {{ $r->reason ?? '-' }}
        </td>

        <td>{{ $r->score_total ?? '-' }}</td>
        <td>{{ $r->rr_tp2 ?? '-' }}</td>

        <td>{{ $r->snapshot_at ?? '-' }}</td>

        <td>{{ $r->entry_ideal ?? '-' }}</td>
        <td style="white-space:normal;min-width:200px">
          {{ $r->buy_steps ?? '-' }}
        </td>

        <td>{{ $r->lots ?? '-' }}</td>
        <td>
          @if(isset($r->est_cost) && $r->est_cost !== null)
            {{ number_format((float)$r->est_cost, 0) }}
          @else
            -
          @endif
        </td>
        <td>
          @if(isset($r->est_risk) && $r->est_risk !== null)
            {{ number_format((float)$r->est_risk, 0) }}
          @else
            -
          @endif
        </td>
        <td>
          @if(isset($r->risk_pct_real) && $r->risk_pct_real !== null)
            {{ number_format((float)$r->risk_pct_real * 100, 2) }}%
          @else
            -
          @endif
        </td>

        <td>{{ $r->stop_loss ?? '-' }}</td>
        <td>{{ $r->tp1 ?? '-' }}</td>
        <td>{{ $r->tp2 ?? '-' }}</td>

        <td style="white-space:normal;min-width:260px">
          {{ $r->sell_steps ?? '-' }}
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="28">No data.</td>
      </tr>
    @endforelse
  </tbody>
</table>
