@extends('layouts.modernize')

@section('title', 'Laporan Stok Akhir')
@section('page-title', 'Laporan Stok Akhir')

@section('content')
<div class="row">
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <h6 class="text-muted fw-semibold">Total Stok Awal</h6>
      <h4 class="fw-semibold mb-0">{{ rtrim(rtrim(number_format((float) $totalStokAwal, 3, ',', '.'), '0'), ',') }}</h4>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <h6 class="text-muted fw-semibold">Total Penjualan</h6>
      <h4 class="fw-semibold mb-0">{{ rtrim(rtrim(number_format((float) $totalPenjualan, 3, ',', '.'), '0'), ',') }}</h4>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <h6 class="text-muted fw-semibold">Total Stok Akhir</h6>
      <h4 class="fw-semibold mb-0 {{ (float) $totalStokAkhir < 0 ? 'text-danger' : 'text-success' }}">{{ rtrim(rtrim(number_format((float) $totalStokAkhir, 3, ',', '.'), '0'), ',') }}</h4>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h5 class="card-title fw-semibold mb-1">Rincian Stok Akhir</h5>
        <span class="text-muted fs-3">{{ \Illuminate\Support\Carbon::parse($tanggalMulai)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($tanggalSelesai)->format('d/m/Y') }}</span>
      </div>
    </div>
    <form method="GET" action="{{ route('laporan.stok-akhir') }}" class="row align-items-end g-3 mb-3">
      <div class="col-sm-6 col-md-3">
        <label class="form-label" for="stok-akhir-table-owner-filter">Tampilkan barang milik</label>
        <select class="form-select" id="stok-akhir-table-owner-filter" name="pemilik"
          onchange="stokAkhirOnPemilikChange(this)">
          <option value="" data-alamat="">Semua nama</option>
          @foreach ($pemiliks as $pemilik)
            <option value="{{ $pemilik->kode }}"
              data-alamat="{{ $pemilik->alamat ?? '' }}"
              @selected($pemilikTerpilih === $pemilik->kode)
            >{{ $pemilik->id_tampilan }} — {{ $pemilik->nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-sm-6 col-md-3">
        <label class="form-label" for="stok-akhir-alamat">Alamat / Desa</label>
        <input class="form-control bg-light" type="text" id="stok-akhir-alamat" name="alamat"
          value="{{ $alamat }}" placeholder="Alamat pemilik" readonly tabindex="-1"
          title="Alamat diambil dari Master ID Nama">
      </div>
      <div class="col-sm-6 col-md-3">
        <label class="form-label" for="stok-akhir-date-from">Tanggal dari</label>
        <input class="form-control" type="date" id="stok-akhir-date-from" name="tanggal_mulai"
          value="{{ $tanggalMulai }}" onchange="this.form.requestSubmit()">
      </div>
      <div class="col-sm-6 col-md-3">
        <label class="form-label" for="stok-akhir-date-to">Tanggal sampai</label>
        <input class="form-control" type="date" id="stok-akhir-date-to" name="tanggal_selesai"
          value="{{ $tanggalSelesai }}" onchange="this.form.requestSubmit()">
      </div>
    </form>
    <div class="table-responsive">
      <table id="stok-akhir-table" class="table table-hover align-middle text-nowrap mb-0">
        <thead>
          <tr>
            <th class="text-center" style="width: 56px;">No.</th>
            <th style="min-width: 240px;">Nama Barang</th>
            <th class="text-center" style="width: 110px;">Berat Bersih</th>
            <th class="text-end" style="width: 130px;">Stok Barang</th>
            <th class="text-end" style="width: 130px;">Barang Terjual</th>
            <th class="text-end" style="width: 130px;">Stok Akhir</th>
            <th style="min-width: 140px;">Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($laporans as $laporan)
            <tr>
              <td class="text-center">{{ $loop->iteration }}</td>
              <td class="fw-semibold">{{ $laporan->nama_barang }}</td>
              <td class="text-center">{{ $laporan->berat_bersih }}</td>
              <td class="text-end" data-order="{{ $laporan->stok_awal }}">{{ rtrim(rtrim(number_format((float) $laporan->stok_awal, 3, ',', '.'), '0'), ',') }}</td>
              <td class="text-end" data-order="{{ $laporan->penjualan }}">{{ rtrim(rtrim(number_format((float) $laporan->penjualan, 3, ',', '.'), '0'), ',') }}</td>
              <td class="text-end fw-semibold {{ (float) $laporan->stok_akhir < 0 ? 'text-danger' : '' }}" data-order="{{ $laporan->stok_akhir }}">{{ rtrim(rtrim(number_format((float) $laporan->stok_akhir, 3, ',', '.'), '0'), ',') }}</td>
              <td></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
@php
  $logoBase64File = public_path('assets/images/logos/logo-base64.txt');
  $logoBase64 = file_exists($logoBase64File) ? trim(file_get_contents($logoBase64File)) : '';
  $logoKopFile = public_path('assets/images/logos/koperasi-indonesia.png');
  $logoKopBase64 = file_exists($logoKopFile)
    ? base64_encode(file_get_contents($logoKopFile))
    : '';
  $alamatTampil = trim((string) ($alamat ?? '')) !== '' ? $alamat : '-';
@endphp
<script>
  // Auto-fill alamat saat pemilik berubah
  function stokAkhirOnPemilikChange(sel) {
    const alamatInput = document.getElementById('stok-akhir-alamat');
    const alamat = sel.options[sel.selectedIndex].dataset.alamat || '';
    alamatInput.value = alamat;
    sel.form.requestSubmit();
  }
  window['customButtons_stok-akhir-table'] = [
    {
      extend: 'csvHtml5',
      text: 'CSV',
      title: null,
      filename: 'Stok-Akhir-{{ str_replace([" ", "/", "\\\\"], "-", $pemilikTerpilihNama ?? "Semua") }}-{{ $tanggalMulai }}-sd-{{ $tanggalSelesai }}',
      exportOptions: {
        columns: [0, 1, 2, 3, 4, 5, 6],
        modifier: { search: 'applied' }
      },
      customize: function (csv) {
        const kop = [
          'KOPERASI KONSUMEN CINDE BAROKAH SEJAHTERA',
          'AHU-0004655.AH.01.29.TAHUN 2026',
          'NIK : 3523070230034 - NIB : 2105260044023',
          'Telepon : 085177421819, email : konsumencindebarokahsejahtera@gmail.com',
          'Ds. Plumpung RT.05 RW.04 DS. Sumurcinde Kec. Soko Kab. Tuban',
          '--------------------------------------------------------------------------------',
          'STOK AKHIR',
          'Nama           : {{ $pemilikTerpilihNama ?? "Semua Pemilik" }}',
          'Alamat         : {{ $alamatTampil }}',
          'Hari, Tanggal  : {{ $tanggalFormatted }}',
          ''
        ].join('\r\n');

        const footer = [
          '',
          '',
          'Pengirim\t\t\tMengetahui\t\t\tPenerima',
          '\t\t\tKetua Pengurus',
          '',
          '',
          '',
          '(....................)\t\t\tSUDIONO\t\t\t(....................)'
        ].join('\r\n');

        return kop + '\r\n' + csv + '\r\n' + footer;
      }
    },
    {
      extend: 'excelHtml5',
      text: 'Excel',
      title: null,
      createEmptyCells: true,
      filename: 'Stok-Akhir-{{ str_replace([" ", "/", "\\\\"], "-", $pemilikTerpilihNama ?? "Semua") }}-{{ $tanggalMulai }}-sd-{{ $tanggalSelesai }}',
      exportOptions: {
        columns: [0, 1, 2, 3, 4, 5, 6],
        modifier: { search: 'applied' }
      },
      customize: function (xlsx) {
        const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        const sheet = xlsx.xl.worksheets['sheet1.xml'];
        const styles = xlsx.xl['styles.xml'];
        const numRows = $('row', sheet).length; // header + baris data (sebelum kop disisip)

        // --- helper bikin node XML via DOM API (aman untuk dokumen XML Excel) ---
        const mk = (doc, name, attrs, text) => {
          const n = doc.createElementNS(NS, name);
          if (attrs) { Object.keys(attrs).forEach(k => n.setAttribute(k, attrs[k])); }
          if (text !== undefined) {
            const d = document.createElement('textarea'); // decode entity Blade (&amp; dll)
            d.innerHTML = text;
            n.textContent = d.value;
          }
          return n;
        };
        const inlineCell = (doc, ref, text, s) => {
          const c = mk(doc, 'c', { r: ref, t: 'inlineStr' });
          if (s !== undefined) { c.setAttribute('s', String(s)); }
          const is = mk(doc, 'is');
          is.appendChild(mk(doc, 't', null, text));
          c.appendChild(is);
          return c;
        };
        const styledCell = (doc, ref, s) => {
          const c = mk(doc, 'c', { r: ref });
          c.setAttribute('s', String(s));
          return c;
        };
        const mkRow = (doc, r, cells, ht) => {
          const attrs = { r: String(r) };
          if (ht) { attrs.ht = ht; attrs.customHeight = '1'; }
          const row = mk(doc, 'row', attrs);
          cells.forEach(c => row.appendChild(c));
          return row;
        };

        // --- 1. Geser tabel turun 10 baris (ruang kop) ---
        $('row', sheet).each(function () {
          const r = parseInt($(this).attr('r'), 10);
          $(this).attr('r', r + 10);
          $('c', this).each(function () {
            const ref = $(this).attr('r');
            const col = ref.replace(/[0-9]/g, '');
            const rowNum = parseInt(ref.replace(/[^0-9]/g, ''), 10);
            $(this).attr('r', col + (rowNum + 10));
          });
        });

        // --- 2. Tambah style baru di styles.xml ---
        let nextStyle = parseInt($('cellXfs', styles).attr('count'), 10); // bawaan: 68
        const addXf = (o) => {
          const n = mk(styles, 'xf', {
            numFmtId: '0', fontId: String(o.font || 0), fillId: String(o.fill || 0),
            borderId: String(o.border || 0), xfId: '0',
            applyFont: '1', applyFill: '1', applyBorder: '1'
          });
          if (o.align) {
            n.setAttribute('applyAlignment', '1');
            n.appendChild(mk(styles, 'alignment', { horizontal: o.align, vertical: 'center' }));
          }
          $('cellXfs', styles)[0].appendChild(n);
          return nextStyle++;
        };

        const $fonts = $('fonts', styles);
        const fontTitle = parseInt($fonts.attr('count'), 10); // font baru: bold 14
        const fTitle = mk(styles, 'font');
        fTitle.appendChild(mk(styles, 'sz', { val: '14' }));
        fTitle.appendChild(mk(styles, 'name', { val: 'Calibri' }));
        fTitle.appendChild(mk(styles, 'b'));
        $fonts[0].appendChild(fTitle);
        $fonts.attr('count', fontTitle + 1);

        const $fills = $('fills', styles);
        const fillGreen = parseInt($fills.attr('count'), 10); // fill baru: hijau #95ce67 (sama dgn PDF)
        const fill = mk(styles, 'fill');
        const pat = mk(styles, 'patternFill', { patternType: 'solid' });
        pat.appendChild(mk(styles, 'fgColor', { rgb: 'FF95CE67' }));
        pat.appendChild(mk(styles, 'bgColor', { indexed: '64' }));
        fill.appendChild(pat);
        $fills[0].appendChild(fill);
        $fills.attr('count', fillGreen + 1);

        const $borders = $('borders', styles);
        const borderDouble = parseInt($borders.attr('count'), 10); // border baru: garis double bawah
        const bd = mk(styles, 'border', { diagonalUp: 'false', diagonalDown: 'false' });
        ['left', 'right', 'top'].forEach(n => bd.appendChild(mk(styles, n)));
        const bot = mk(styles, 'bottom', { style: 'double' });
        bot.appendChild(mk(styles, 'color', { auto: '1' }));
        bd.appendChild(bot);
        bd.appendChild(mk(styles, 'diagonal'));
        $borders[0].appendChild(bd);
        $borders.attr('count', borderDouble + 1);

        const S = {};
        S.title   = addXf({ font: fontTitle, align: 'center' });                      // judul kop (bold 14 center)
        S.boldCen = addXf({ font: 2, align: 'center' });                              // bold center
        S.header  = addXf({ font: 2, fill: fillGreen, border: 1, align: 'center' });  // header tabel
        S.num     = addXf({ border: 1, align: 'center' });                            // isi angka: center + border
        S.line    = addXf({ border: borderDouble });                                  // garis double pemisah kop
        $('cellXfs', styles).attr('count', nextStyle);

        // --- 3. Sisipkan kop + judul + info (10 baris, merge A:G, center) ---
        const sheetData = $('sheetData', sheet)[0];
        const anchor = sheetData.firstChild; // baris pertama tabel (akan jadi row 11)
        const merges = [];
        const cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        const kopLines = [
          'KOPERASI KONSUMEN CINDE BAROKAH SEJAHTERA',
          'AHU-0004655.AH.01.29.TAHUN 2026',
          'NIK : 3523070230034 - NIB : 2105260044023',
          'Telepon : 085177421819, email : konsumencindebarokahsejahtera@gmail.com',
          'Ds. Plumpung RT.05 RW.04 DS. Sumurcinde Kec. Soko Kab. Tuban'
        ];
        kopLines.forEach(function (t, i) {
          const r = i + 1;
          merges.push('A' + r + ':G' + r);
          sheetData.insertBefore(mkRow(sheet, r, [inlineCell(sheet, 'A' + r, t, r === 1 ? S.title : 51)], r === 1 ? '20' : undefined), anchor);
        });
        // garis pemisah double (row 6) biar mirip kop PDF
        sheetData.insertBefore(mkRow(sheet, 6, cols.map(c => styledCell(sheet, c + '6', S.line))), anchor);
        // judul laporan
        merges.push('A7:G7');
        sheetData.insertBefore(mkRow(sheet, 7, [inlineCell(sheet, 'A7', 'STOK AKHIR', S.boldCen)], '18'), anchor);
        // info parameter
        sheetData.insertBefore(mkRow(sheet, 8, [inlineCell(sheet, 'B8', 'Nama           : {{ $pemilikTerpilihNama ?? "Semua Pemilik" }}')]), anchor);
        sheetData.insertBefore(mkRow(sheet, 9, [inlineCell(sheet, 'B9', 'Alamat         : {{ $alamatTampil }}')]), anchor);
        sheetData.insertBefore(mkRow(sheet, 10, [inlineCell(sheet, 'B10', 'Hari, Tanggal  : {{ $tanggalFormatted }}')]), anchor);

        // --- 4. Tanda tangan: Mengetahui/Ketua Pengurus/SUDIONO di bawah garis titik-titik (seperti PDF) ---
        const sigStart = numRows + 13;
        [
          { r: sigStart,      cells: [{ c: 'A', c2: 'B', t: 'Pengirim' }, { c: 'F', c2: 'G', t: 'Penerima' }] },
          { r: sigStart + 3,  cells: [{ c: 'A', c2: 'B', t: '( .................... )' }, { c: 'F', c2: 'G', t: '( .................... )' }] },
          { r: sigStart + 5,  cells: [{ c: 'C', c2: 'E', t: 'Mengetahui' }] },
          { r: sigStart + 6,  cells: [{ c: 'C', c2: 'E', t: 'Ketua Pengurus' }] },
          { r: sigStart + 10, cells: [{ c: 'C', c2: 'E', t: 'SUDIONO' }] }
        ].forEach(function (item) {
          const cells = item.cells.map(function (x) {
            merges.push(x.c + item.r + ':' + x.c2 + item.r);
            return inlineCell(sheet, x.c + item.r, x.t, 51);
          });
          sheetData.appendChild(mkRow(sheet, item.r, cells));
        });

        // --- 5. Terapkan semua merge cell ---
        const $mc = $('mergeCells', sheet);
        merges.forEach(function (ref) { $mc[0].appendChild(mk(sheet, 'mergeCell', { ref: ref })); });
        $mc.attr('count', merges.length);

        // --- 6. Style header tabel + isi tabel (border tipis) ---
        // 25 = teks biasa + border | S.num = angka center + border
        const bodyStyle = { A: S.num, B: 25, C: S.num, D: S.num, E: S.num, F: S.num, G: 25 };
        $('row', sheet).each(function (i) {
          if (i === 10) { // header tabel (row 11)
            $('c', this).attr('s', S.header);
          } else if (i > 10 && i <= 9 + numRows) { // baris data
            $('c', this).each(function () {
              const col = $(this).attr('r').replace(/[0-9]/g, '');
              $(this).attr('s', bodyStyle[col] !== undefined ? bodyStyle[col] : 25);
            });
          }
        });

        // --- 7. Lebar kolom ---
        const widths = [6, 42, 16, 15, 16, 15, 22];
        $('col', sheet).each(function (i) {
          if (widths[i]) { $(this).attr('width', widths[i]); }
        });

        // --- 8. Print setup: landscape + muat 1 halaman lebar ---
        const ws = $('worksheet', sheet)[0];
        const sheetPr = mk(sheet, 'sheetPr');
        sheetPr.appendChild(mk(sheet, 'pageSetUpPr', { fitToPage: '1' }));
        ws.insertBefore(sheetPr, ws.firstChild); // sheetPr harus child pertama worksheet
        $mc[0].after(mk(sheet, 'pageSetup', { orientation: 'landscape', fitToWidth: '1', fitToHeight: '0' }));

        // --- 9. Logo (kiri: Koperasi, kanan: BMT) di baris kop ---
        const logoKopB64 = @json($logoKopBase64 ?? '');
        const logoBmtB64 = @json($logoBase64 ?? '');
        if (logoKopB64 || logoBmtB64) {
          // elemen <drawing> di worksheet (harus SETELAH pageSetup)
          const drawing = mk(sheet, 'drawing');
          drawing.setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', 'rId1');
          ws.appendChild(drawing);

          // [Content_Types].xml: default png + override drawing part
          const ctNS = 'http://schemas.openxmlformats.org/package/2006/content-types';
          const ct = xlsx['[Content_Types].xml'];
          const defPng = ct.createElementNS(ctNS, 'Default');
          defPng.setAttribute('Extension', 'png');
          defPng.setAttribute('ContentType', 'image/png');
          ct.documentElement.insertBefore(defPng, ct.getElementsByTagNameNS(ctNS, 'Override')[0]);
          const ovDraw = ct.createElementNS(ctNS, 'Override');
          ovDraw.setAttribute('PartName', '/xl/drawings/drawing1.xml');
          ovDraw.setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.drawing+xml');
          ct.documentElement.appendChild(ovDraw);
        }
      },
      customizeZip: function (zip) {
        // Sisipkan file gambar + drawing (dipanggil Buttons setelah semua file lain masuk zip)
        const logoKopB64 = @json($logoKopBase64 ?? '');
        const logoBmtB64 = @json($logoBase64 ?? '');
        if (!logoKopB64 && !logoBmtB64) { return; }

        const items = [];
        if (logoKopB64) { items.push({ b64: logoKopB64, file: 'image1.png', col: 0, off: 190500, roff: 95250, cx: 527700, cy: 514350, name: 'Logo Koperasi' }); }
        if (logoBmtB64) { items.push({ b64: logoBmtB64, file: 'image' + (items.length + 1) + '.png', col: 6, off: 0, roff: 0, cx: 558200, cy: 558200, name: 'Logo BMT' }); }

        // xl/media/imageN.png
        items.forEach(function (it) { zip.file('xl/media/' + it.file, it.b64, { base64: true }); });

        // xl/drawings/_rels/drawing1.xml.rels -> media
        let relsDrawing = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
          '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        items.forEach(function (it, i) {
          relsDrawing += '<Relationship Id="rId' + (i + 1) + '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' + it.file + '"/>';
        });
        relsDrawing += '</Relationships>';
        zip.file('xl/drawings/_rels/drawing1.xml.rels', relsDrawing);

        // xl/drawings/drawing1.xml: anchor logo kiri (kolom A) & kanan (kolom G) di baris kop
        let anchors = '';
        items.forEach(function (it, i) {
          anchors += '<xdr:oneCellAnchor>' +
            '<xdr:from><xdr:col>' + it.col + '</xdr:col><xdr:colOff>' + it.off + '</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>' + it.roff + '</xdr:rowOff></xdr:from>' +
            '<xdr:ext cx="' + it.cx + '" cy="' + it.cy + '"/>' +
            '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' + (i + 2) + '" name="' + it.name + '"/><xdr:cNvPicPr/></xdr:nvPicPr>' +
            '<xdr:blipFill><a:blip r:embed="rId' + (i + 1) + '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>' +
            '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' + it.cx + '" cy="' + it.cy + '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>' +
            '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
        });
        const drawingXml = '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
          '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"' +
          ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"' +
          ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
          anchors + '</xdr:wsDr>';
        zip.file('xl/drawings/drawing1.xml', drawingXml);

        // xl/worksheets/_rels/sheet1.xml.rels -> drawing
        zip.file('xl/worksheets/_rels/sheet1.xml.rels',
          '<' + '?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
          '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
          '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>' +
          '</Relationships>');
      }
    },
    {
      extend: 'pdfHtml5',
      text: 'PDF',
      title: '',
      filename: 'Stok-Akhir-{{ str_replace([" ", "/", "\\\\"], "-", $pemilikTerpilihNama ?? "Semua") }}-{{ $tanggalMulai }}-sd-{{ $tanggalSelesai }}',
      pageSize: 'A4',
      orientation: 'portrait',
      exportOptions: {
        columns: [0, 1, 2, 3, 4, 5, 6],
        modifier: { search: 'applied', order: 'applied' }
      },
      customize: function (doc) {
        doc.pageMargins = [20, 20, 20, 20];
        doc.defaultStyle.fontSize = 8;
        doc.styles = doc.styles || {};
        doc.styles.tableHeader = {
          fontSize: 8,
          bold: true,
          color: 'black',
          fillColor: '#95ce67',
          alignment: 'center'
        };

        const logoBmtBase64  = @json($logoBase64 ? 'data:image/png;base64,'.$logoBase64 : '');
        const logoKopBase64  = @json($logoKopBase64 ? 'data:image/png;base64,'.$logoKopBase64 : '');

        // === KOP: Logo Koperasi (kiri) | Teks tengah | Logo BMT (kanan) ===
        // Ukuran diset biar TINGGI ISI kedua logo sama (~54px):
        // - Koperasi: canvas 154x150, isi nyaris full -> 55.4 x 54
        // - BMT: canvas 189x189 dengan padding kosong (isi 129x173) -> 58.6 x 58.6
        const kopColumns = [];

        // Logo Koperasi Indonesia – kiri
        if (logoKopBase64) {
          kopColumns.push({
            image: logoKopBase64,
            width: 55.4, height: 54,
            alignment: 'center',
            margin: [0, 0, 6, 0]
          });
        } else {
          kopColumns.push({ width: 60, text: '' });
        }

        // Teks kop – tengah
        kopColumns.push({
          width: '*',
          stack: [
            { text: 'KOPERASI KONSUMEN CINDE BAROKAH SEJAHTERA', fontSize: 11, bold: true, alignment: 'center', margin: [0, 0, 0, 2] },
            { text: 'AHU-0004655.AH.01.29.TAHUN 2026', fontSize: 8.5, bold: true, alignment: 'center', margin: [0, 0, 0, 1] },
            { text: 'NIK : 3523070230034 - NIB : 2105260044023', fontSize: 8, bold: true, alignment: 'center', margin: [0, 0, 0, 1] },
            { text: 'Telepon : 085177421819, email : konsumencindebarokahsejahtera@gmail.com', fontSize: 7.5, bold: true, alignment: 'center', margin: [0, 0, 0, 1] },
            { text: 'Ds. Plumpung RT.05 RW.04 DS. Sumurcinde Kec. Soko Kab. Tuban', fontSize: 7.5, bold: true, alignment: 'center' }
          ]
        });

        // Logo BMT Cinde Barokah – kanan
        if (logoBmtBase64) {
          kopColumns.push({
            image: logoBmtBase64,
            width: 58.6, height: 58.6,
            alignment: 'center',
            margin: [6, 0, 0, 0]
          });
        } else {
          kopColumns.push({ width: 60, text: '' });
        }

        const kopBlock = {
          stack: [
            { columns: kopColumns, alignment: 'center' },
            {
              canvas: [
                { type: 'line', x1: 0, y1: 5, x2: 555, y2: 5, lineWidth: 1.5, lineColor: '#000000' },
                { type: 'line', x1: 0, y1: 7, x2: 555, y2: 7, lineWidth: 0.5, lineColor: '#000000' }
              ],
              margin: [0, 4, 0, 10]
            },
            { text: 'STOK AKHIR', fontSize: 11, bold: true, alignment: 'center', margin: [0, 0, 0, 8] },
            {
              columns: [
                {
                  width: '70%',
                  stack: [
                    {
                      columns: [
                        { text: 'Nama', width: 90, fontSize: 8.5, bold: true },
                        { text: ': {{ $pemilikTerpilihNama ?? "Semua Pemilik" }}', width: '*', fontSize: 8.5 }
                      ],
                      margin: [0, 0, 0, 2]
                    },
                    {
                      columns: [
                        { text: 'Alamat', width: 90, fontSize: 8.5, bold: true },
                        { text: ': {{ $alamatTampil }}', width: '*', fontSize: 8.5 }
                      ],
                      margin: [0, 0, 0, 2]
                    },
                    {
                      columns: [
                        { text: 'Hari, Tanggal', width: 90, fontSize: 8.5, bold: true },
                        { text: ': {{ $tanggalFormatted }}', width: '*', fontSize: 8.5 }
                      ]
                    }
                  ]
                }
              ],
              margin: [6, 0, 0, 8]
            }
          ]
        };

        const tableBody = doc.content[0].table.body;
        doc.content = [kopBlock, doc.content[0]];

        // Adjust column widths to fit 100% of the printable area without right clipping
        doc.content[1].table.widths = ['5%', '27%', '14%', '13%', '14%', '13%', '14%'];
        for (let i = 0; i < tableBody.length; i++) {
          const row = tableBody[i];
          if (i === 0) {
            row.forEach(cell => {
              cell.fillColor = '#95ce67';
              cell.alignment = 'center';
            });
          } else {
            row[0].alignment = 'center'; // NO
            row[1].alignment = 'left';   // NAMA BARANG
            row[2].alignment = 'center'; // BERAT BERSIH
            row[3].alignment = 'center'; // STOK BARANG
            row[4].alignment = 'center'; // BARANG TERJUAL
            row[5].alignment = 'center'; // STOK AKHIR
            row[6].alignment = 'left';   // KETERANGAN
          }
        }

        doc.content[1].layout = {
          hLineWidth: function () { return 0.6; },
          vLineWidth: function () { return 0.6; },
          hLineColor: function () { return '#222222'; },
          vLineColor: function () { return '#222222'; },
          paddingLeft: function () { return 4; },
          paddingRight: function () { return 4; },
          paddingTop: function () { return 4; },
          paddingBottom: function () { return 4; }
        };

        // === TANDA TANGAN ===
        // Mengetahui (tengah) lebih ke bawah dari Pengirim & Penerima
        const signatureBlock = {
          margin: [0, 28, 0, 0],
          columns: [
            {
              width: '33%',
              stack: [
                { text: 'Pengirim', bold: true, alignment: 'center' },
                { text: '', margin: [0, 48, 0, 0] },
                { text: '( ................................... )', alignment: 'center' }
              ]
            },
            {
              width: '34%',
              stack: [
                // Spacer berupa baris nyata (bukan margin) supaya blok tengah tetap
                // turun walau tanda tangan dimulai di halaman baru.
                { text: '\u00A0\n\u00A0\n\u00A0\n\u00A0\n\u00A0\n\u00A0\n\u00A0', fontSize: 8 },
                { text: 'Mengetahui', bold: true, alignment: 'center' },
                { text: 'Ketua Pengurus', bold: true, alignment: 'center', margin: [0, 0, 0, 0] },
                { text: '', margin: [0, 44, 0, 0] },
                { text: 'SUDIONO', bold: true, decoration: 'underline', alignment: 'center' }
              ]
            },
            {
              width: '33%',
              stack: [
                { text: 'Penerima', bold: true, alignment: 'center' },
                { text: '', margin: [0, 48, 0, 0] },
                { text: '( ................................... )', alignment: 'center' }
              ]
            }
          ]
        };

        doc.content.push(signatureBlock);
      }
    }
  ];
</script>
@endpush

@include('keuangan.partials.data-table', [
    'tableId' => 'stok-akhir-table',
    'title' => $pemilikTerpilihNama ? 'Laporan Stok Akhir - '.$pemilikTerpilihNama : 'Laporan Stok Akhir',
    'emptyMessage' => 'Tidak ada data stok pada periode ini.',
])

