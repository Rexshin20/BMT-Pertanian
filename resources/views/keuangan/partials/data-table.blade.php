@php
  $withExports = $withExports ?? true;
  $renumberFirstColumn = $renumberFirstColumn ?? true;
  $nonOrderableColumns = $nonOrderableColumns ?? [0];
@endphp

@push('styles')
  <link rel="stylesheet" href="https://cdn.datatables.net/2.3.4/css/dataTables.bootstrap5.min.css">
  @if ($withExports)
    <style>
      #{{ $tableId }}_wrapper .dt-layout-row:first-child {
        position: relative;
      }

      #{{ $tableId }}_wrapper .dt-layout-row:first-child .dt-layout-cell.dt-layout-start {
        flex: 1 1 100%;
        justify-content: center;
      }

      #{{ $tableId }}_wrapper .dt-layout-row:first-child .dt-layout-cell.dt-layout-end {
        position: absolute;
        top: 0;
        right: 0;
      }

      #{{ $tableId }}_wrapper .dt-buttons {
        display: flex;
        justify-content: center;
        width: 100%;
      }

      @media (max-width: 767px) {
        #{{ $tableId }}_wrapper .dt-layout-row:first-child .dt-layout-cell.dt-layout-end {
          position: static;
          flex-basis: 100%;
          justify-content: flex-end;
        }
      }
    </style>
  @endif
  @if ($withExports)
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.2.5/css/buttons.dataTables.min.css">
  @endif
@endpush

@push('scripts')
  <script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/2.3.4/js/dataTables.bootstrap5.min.js"></script>
  @if ($withExports)
    <script src="https://cdn.datatables.net/buttons/3.2.5/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.12/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.12/vfs_fonts.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.5/js/buttons.html5.min.js"></script>
  @endif
  <script>
    const dateFrom = document.getElementById('{{ $tableId }}-date-from');
    const dateTo = document.getElementById('{{ $tableId }}-date-to');
    const ownerFilter = document.getElementById('{{ $tableId }}-owner-filter');
    const ownerFilterForm = document.querySelector('[data-owner-filter-form="{{ $tableId }}"]');
    const tableTitle = @json($title);

    if (ownerFilter) {
      ownerFilter.addEventListener('change', () => {
        const form = ownerFilter.form;
        const dateFromQuery = document.getElementById('{{ $tableId }}-date-from-query');
        const dateToQuery = document.getElementById('{{ $tableId }}-date-to-query');

        if (dateFromQuery && dateToQuery) {
          dateFromQuery.value = dateFrom?.value || '';
          dateToQuery.value = dateTo?.value || '';
        }

        form.requestSubmit();
      });
    }

    if (dateFrom && dateTo) {
      DataTable.ext.search.push((settings, _data, dataIndex) => {
        if (settings.nTable.id !== '{{ $tableId }}') {
          return true;
        }

        const transactionDate = settings.aoData[dataIndex].nTr.dataset.date || '';

        return (!dateFrom.value || transactionDate >= dateFrom.value)
          && (!dateTo.value || transactionDate <= dateTo.value);
      });
    }

    const table = new DataTable('#{{ $tableId }}', {
      order: [],
      pageLength: 20,
      lengthMenu: [10, 20, 50, 100],
      columnDefs: [{ targets: @json($nonOrderableColumns), orderable: false, searchable: false }],
      layout: {
        topStart: @json($withExports) ? {
          buttons: window['customButtons_{{ $tableId }}'] || [
            { extend: 'csvHtml5', text: 'CSV', title: tableTitle, exportOptions: { columns: ':visible', modifier: { search: 'applied' } } },
            { extend: 'excelHtml5', text: 'Excel', title: tableTitle, exportOptions: { columns: ':visible', modifier: { search: 'applied' } } },
            { extend: 'pdfHtml5', text: 'PDF', title: tableTitle, orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':visible', modifier: { search: 'applied' } } }
          ]
        } : 'pageLength',
        topEnd: 'search',
        bottomStart: 'info',
        bottomEnd: 'paging'
      },
      language: {
        search: 'Cari:',
        lengthMenu: 'Tampilkan _MENU_ data',
        info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
        infoEmpty: 'Tidak ada data untuk ditampilkan',
        zeroRecords: 'Data tidak ditemukan',
        emptyTable: @json($emptyMessage),
        paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
        buttons: { csv: 'CSV', excel: 'Excel', pdf: 'PDF' }
      },
      @if ($renumberFirstColumn)
      drawCallback: function () {
        const api = this.api();
        api.rows({ search: 'applied', order: 'applied' }).indexes().each((rowIndex, index) => {
          api.cell(rowIndex, 0).data(index + 1);
        });
      },
      @endif
    });

    if (dateFrom && dateTo) {
      const syncDateQuery = () => {
        const dateFromQuery = document.getElementById('{{ $tableId }}-date-from-query');
        const dateToQuery = document.getElementById('{{ $tableId }}-date-to-query');
        if (dateFromQuery && dateToQuery) {
          dateFromQuery.value = dateFrom.value;
          dateToQuery.value = dateTo.value;
        }
      };

      dateFrom.addEventListener('change', () => {
        syncDateQuery();
        table.draw();
      });
      dateTo.addEventListener('change', () => {
        syncDateQuery();
        table.draw();
      });
    }
  </script>
@endpush
