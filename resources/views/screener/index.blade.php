@extends('layouts.screener')

@section('content')

<h3>Screener ({{ $trade_date ?? '-' }})</h3>

<table class="tbl">
  <thead>
    <tr>
      <th>No</th><th>Ticker</th><th>Company</th><th>Date</th>
      <th>Close</th><th>MA20</th><th>MA50</th><th>MA200</th>
      <th>Decision</th><th>Volume Label</th><th>VolRatio</th><th>RSI</th><th>Score</th>
    </tr>
  </thead>
  <tbody>
    @php $no = 1; @endphp
    @foreach($rows as $r)
      <tr>
        <td>{{ $no++ }}</td>
        <td>{{ $r->ticker_code }}</td>
        <td>{{ $r->company_name }}</td>
        <td>{{ $r->trade_date }}</td>
        <td>{{ $r->close }}</td>
        <td>{{ $r->ma20 }}</td>
        <td>{{ $r->ma50 }}</td>
        <td>{{ $r->ma200 }}</td>
        <td>{{ $r->decision_name }}</td>
        <td>{{ $r->volume_label_name }}</td>
        <td>{{ $r->vol_ratio }}</td>
        <td>{{ $r->rsi14 }}</td>
        <td>{{ $r->score_total }}</td>
      </tr>
    @endforeach
  </tbody>
</table>
@endsection