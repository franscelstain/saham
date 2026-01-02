@extends('layouts.screener')

@push('head')
  <link rel="stylesheet" href="https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator.min.css">
@endpush

@section('content')
<div class="min-h-screen bg-base-200">
  @include('screener.partials.topbar_buylist', ['title' => 'Buylist Today'])

  <div class="px-4 py-4 space-y-4">
    @include('screener.partials.buylist.meta', [
      'today' => $today ?? null,
      'eodDate' => $eodDate ?? null,
      'capital' => $capital ?? null,
    ])

    @include('screener.partials.buylist.kpi_row')

    <div class="grid grid-cols-12 gap-4">
      {{-- LEFT: tables --}}
      <div class="col-span-12 lg:col-span-8 xl:col-span-9 space-y-4">
        @include('screener.partials.buylist.table_recommended')
        @include('screener.partials.buylist.table_all')
      </div>

      {{-- RIGHT: detail panel (desktop) --}}
      <div class="col-span-12 lg:col-span-4 xl:col-span-3">
        @include('screener.partials.buylist.right_panel')
      </div>
    </div>
  </div>

  {{-- Mobile drawer (optional) --}}
  @include('screener.partials.buylist.drawer_detail')
</div>
@endsection

@push('scripts')
  <script src="https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js"></script>
  <script>
    window.__SCREENER__ = {
      endpoints: { buylist: "{{ url('/screener/buylist/data') }}" }
    };
  </script>
  <script src="{{ mix('/js/screener/buylist.js') }}"></script>
@endpush
