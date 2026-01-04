@extends('layouts.screener')

@push('head')
  <link rel="stylesheet" href="https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator.min.css">
@endpush

@section('content')
<div class="min-h-screen bg-base-200">
  @include('screener.buylist.partials.topbar', ['title' => 'TradeAxis'])

  <div class="px-4 py-4 space-y-4">
    @include('screener.buylist.partials.meta', [
      'today' => $today ?? null,
      'eodDate' => $eodDate ?? null,
      'capital' => $capital ?? null,
    ])

    @include('screener.buylist.partials.kpi_row')

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
      <div class="lg:col-span-8 space-y-4">
        @include('screener.buylist.partials.table_recommended')
        @include('screener.buylist.partials.table_all')
      </div>

      <div class="lg:col-span-4">
        @include('screener.buylist.partials.right_panel')
      </div>
    </div>
  </div>

  {{-- Mobile drawer --}}
  @include('screener.buylist.partials.drawer_detail')
</div>
@endsection

@push('scripts')
  <script src="https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js"></script>
  <script>
    window.__SCREENER__ = {
      endpoints: {
        buylist: "{{ url('/screener/buylist/data') }}",
        intradayUpdate: "{{ url('/screener/intraday/update') }}"
      }
    };
  </script>
  <script src="{{ mix('/js/screener/buylist.js') }}"></script>
@endpush
