<style>
  .tbl{width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px}
  .tbl th,.tbl td{border:1px solid #333;padding:6px 8px;vertical-align:top}
  .tbl th{background:#f2f2f2;text-align:left}
  .tbl tbody tr:nth-child(even){background:#fafafa}
</style>

<h3>Kandidat Beli (EOD: {{ $trade_date ?? '-' }})</h3>

<table class="tbl">
  <thead>
    <tr>
      <th>No</th>
      <th>Ticker</th>
      <th>Company</th>
      <th>Signal</th>
      <th>Volume Label</th>
      <th>Close</th>
      <th>VolRatio</th>
      <th>RSI14</th>
      <th>Score</th>
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $i => $r)
      <tr>
        <td>{{ $i+1 }}</td>
        <td>{{ $r->ticker_code }}</td>
        <td>{{ $r->company_name }}</td>
        <td>{{ $r->signal_name }}</td>
        <td>{{ $r->volume_label_name }}</td>
        <td>{{ $r->close }}</td>
        <td>{{ $r->vol_ratio }}</td>
        <td>{{ $r->rsi14 }}</td>
        <td>{{ $r->score_total }}</td>
      </tr>
    @empty
      <tr><td colspan="9">No data.</td></tr>
    @endforelse
  </tbody>
</table>
