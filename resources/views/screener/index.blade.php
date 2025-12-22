<style>
  .tbl {
    width: 100%;
    border-collapse: collapse; /* ini yang bikin border rapi */
    font-family: Arial, sans-serif;
    font-size: 14px;
  }
  .tbl th, .tbl td {
    border: 1px solid #333;
    padding: 6px 8px;
    vertical-align: top;
  }
  .tbl th {
    background: #f2f2f2;
    text-align: left;
  }
  .tbl tbody tr:nth-child(even) {
    background: #fafafa;
  }
</style>

<h3>Screener ({{ $trade_date ?? '-' }})</h3>

<table class="tbl">
  <thead>
    <tr>
      <th>Ticker</th><th>Company</th><th>Date</th>
      <th>Close</th><th>MA20</th><th>MA50</th><th>MA200</th>
      <th>Signal</th><th>Volume Label</th><th>VolRatio</th><th>RSI</th><th>Score</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $r)
      <tr>
        <td>{{ $r->ticker_code }}</td>
        <td>{{ $r->company_name }}</td>
        <td>{{ $r->trade_date }}</td>
        <td>{{ $r->close }}</td>
        <td>{{ $r->ma20 }}</td>
        <td>{{ $r->ma50 }}</td>
        <td>{{ $r->ma200 }}</td>
        <td>{{ $r->signal_name }}</td>
        <td>{{ $r->volume_label_name }}</td>
        <td>{{ $r->vol_ratio }}</td>
        <td>{{ $r->rsi14 }}</td>
        <td>{{ $r->score_total }}</td>
      </tr>
    @endforeach
  </tbody>
</table>
