@extends('layouts.screener')

@push('head')
  <link rel="stylesheet" href="https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator.min.css">
@endpush

@section('content')
  {{-- Pakai topbar global yang sudah ada --}}
  @include('partials.topbar', ['title' => 'TradeAxis'])

  <div class="p-4">
    <div class="ui-surface">
      <div class="px-4 py-3 border-b border-base-300/60 flex items-center justify-between gap-3">
        <div class="font-semibold">Daftar Saham</div>

        <div class="flex items-center gap-2">
          <input id="q" class="input input-sm input-bordered w-56" placeholder="Search ticker / company">
          <select id="pageSize" class="select select-sm select-bordered">
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>

          <button id="btnReload" class="btn btn-sm">Reload</button>
          <button id="btnYahoo" class="btn btn-sm btn-primary">Update Yahoo</button>
        </div>
      </div>

      <div class="p-2">
        <div id="tblTickers" class="min-h-[560px]"></div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js"></script>
  <script>
    window.__TA = {
      csrf: "{{ csrf_token() }}",
      dataUrl: "{{ url('ticker/data') }}",
      yahooUrl: "{{ url('yahoo/history') }}"
    };
  </script>
  <script src="{{ mix('/js/screener/ticker.js') }}"></script>
@endpush
