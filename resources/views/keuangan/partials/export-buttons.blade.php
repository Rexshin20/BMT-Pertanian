@php
  $judulExport = $judulExport ?? 'LAPORAN';
  $prefixFile = $prefixFile ?? 'Laporan';
  $orientasi = $orientasi ?? 'portrait';
  $kolomHuruf = $kolomHuruf ?? ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
  $kolomExport = $kolomExport ?? range(0, count($kolomHuruf) - 1);
  $tipeKolom = $tipeKolom ?? [];
  $lebarExcel = $lebarExcel ?? array_fill(0, count($kolomHuruf), 14);
  $lebarPdf = $lebarPdf ?? array_fill(0, count($kolomHuruf), (100 / count($kolomHuruf)).'%');
  $rataKiri = $rataKiri ?? [];
  $rataKanan = $rataKanan ?? [];
  $namaPemilik = $pemilikTerpilihNama ?? null;
  $alamat = trim((string) ($alamat ?? ($pemilikTerpilihObj->alamat ?? '')));
  $alamatTampil = $alamat !== '' ? $alamat : '-';

  $mulai = $tanggalMulai ?? '';
  $selesai = $tanggalSelesai ?? '';
  if ($mulai !== '' && $selesai !== '') {
      $tanggalFormatted = \Illuminate\Support\Carbon::parse($mulai)->locale('id')->translatedFormat('d F Y')
          .' s/d '.\Illuminate\Support\Carbon::parse($selesai)->locale('id')->translatedFormat('d F Y');
  } elseif ($mulai !== '' || $selesai !== '') {
      $tanggalFormatted = \Illuminate\Support\Carbon::parse($mulai ?: $selesai)->locale('id')->translatedFormat('l, d F Y');
  } else {
      $tanggalFormatted = \Illuminate\Support\Carbon::today()->locale('id')->translatedFormat('l, d F Y');
  }

  $jumlahKolom = count($kolomHuruf);
  $kolomTerakhir = $kolomHuruf[$jumlahKolom - 1];
  $kolomKanan1 = $kolomHuruf[$jumlahKolom - 2];
  $kolomTengahAwal = $kolomHuruf[2];
  $kolomTengahAkhir = $kolomHuruf[$jumlahKolom - 3];

  $logoBase64File = public_path('assets/images/logos/logo-base64.txt');
  $logoBase64 = file_exists($logoBase64File) ? trim(file_get_contents($logoBase64File)) : '';
  $logoKopFile = public_path('assets/images/logos/koperasi-indonesia.png');
  $logoKopBase64 = file_exists($logoKopFile) ? base64_encode(file_get_contents($logoKopFile)) : '';
@endphp

@push('scripts')
<script>
  // Tanggal kop mengikuti filter tanggal yang sedang aktif di tabel.
  function tanggalKopExport_{{ str_replace("-", "_", $tableId) }}() {
    const fmt = function (val) {
      if (!val) { return ''; }
      const d = new Date(val + 'T00:00:00');
      if (isNaN(d.getTime())) { return val; }
      return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
    };
    const fromEl = document.getElementById('{{ $tableId }}-date-from');
    const toEl = document.getElementById('{{ $tableId }}-date-to');
    const from = fromEl ? fromEl.value : '';
    const to = toEl ? toEl.value : '';

    if (from && to) { return fmt(from) + ' s/d ' + fmt(to); }
    if (from || to) { return fmt(from || to); }

    return @json($tanggalFormatted);
  }

  window['customButtons_{{ $tableId }}'] = [
    {
      extend: 'csvHtml5',
      text: 'CSV',
      title: null,
      filename: '{{ $prefixFile }}-{{ str_replace([" ", "/", "\\\\"], "-", $namaPemilik ?? "Semua") }}-{{ $tanggalMulai ?: "awal" }}-sd-{{ $tanggalSelesai ?: "akhir" }}',
      exportOptions: {
        columns: @json($kolomExport),
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
          @json($judulExport),
          'Nama           : ' + @json($namaPemilik ?? 'Semua Pemilik'),
          'Alamat         : ' + @json($alamatTampil),
          'Hari, Tanggal  : ' + tanggalKopExport_{{ str_replace("-", "_", $tableId) }}(),
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
      filename: '{{ $prefixFile }}-{{ str_replace([" ", "/", "\\\\"], "-", $namaPemilik ?? "Semua") }}-{{ $tanggalMulai ?: "awal" }}-sd-{{ $tanggalSelesai ?: "akhir" }}',
      exportOptions: {
        columns: @json($kolomExport),
        modifier: { search: 'applied' }
      },
      customize: function (xlsx) {
        const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        const sheet = xlsx.xl.worksheets['sheet1.xml'];
        const styles = xlsx.xl['styles.xml'];
        const numRows = $('row', sheet).length;

        const mk = (doc, name, attrs, text) => {
          const n = doc.createElementNS(NS, name);
          if (attrs) { Object.keys(attrs).forEach(k => n.setAttribute(k, attrs[k])); }
          if (text !== undefined) {
            const d = document.createElement('textarea');
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

        // 1. Geser tabel turun 10 baris (ruang kop)
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

        // 2. Style tambahan
        let nextStyle = parseInt($('cellXfs', styles).attr('count'), 10);
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
        const fontTitle = parseInt($fonts.attr('count'), 10);
        const fTitle = mk(styles, 'font');
        fTitle.appendChild(mk(styles, 'sz', { val: '14' }));
        fTitle.appendChild(mk(styles, 'name', { val: 'Calibri' }));
        fTitle.appendChild(mk(styles, 'b'));
        $fonts[0].appendChild(fTitle);
        $fonts.attr('count', fontTitle + 1);

        const $fills = $('fills', styles);
        const fillGreen = parseInt($fills.attr('count'), 10);
        const fill = mk(styles, 'fill');
        const pat = mk(styles, 'patternFill', { patternType: 'solid' });
        pat.appendChild(mk(styles, 'fgColor', { rgb: 'FF95CE67' }));
        pat.appendChild(mk(styles, 'bgColor', { indexed: '64' }));
        fill.appendChild(pat);
        $fills[0].appendChild(fill);
        $fills.attr('count', fillGreen + 1);

        const $borders = $('borders', styles);
        const borderDouble = parseInt($borders.attr('count'), 10);
        const bd = mk(styles, 'border', { diagonalUp: 'false', diagonalDown: 'false' });
        ['left', 'right', 'top'].forEach(n => bd.appendChild(mk(styles, n)));
        const bot = mk(styles, 'bottom', { style: 'double' });
        bot.appendChild(mk(styles, 'color', { auto: '1' }));
        bd.appendChild(bot);
        bd.appendChild(mk(styles, 'diagonal'));
        $borders[0].appendChild(bd);
        $borders.attr('count', borderDouble + 1);

        const S = {};
        S.title   = addXf({ font: fontTitle, align: 'center' });
        S.boldCen = addXf({ font: 2, align: 'center' });
        S.header  = addXf({ font: 2, fill: fillGreen, border: 1, align: 'center' });
        S.num     = addXf({ border: 1, align: 'center' });
        S.line    = addXf({ border: borderDouble });
        $('cellXfs', styles).attr('count', nextStyle);

        // 3. Kop + judul + info
        const sheetData = $('sheetData', sheet)[0];
        const anchor = sheetData.firstChild;
        const merges = [];
        const kolomHuruf = @json($kolomHuruf);
        const last = @json($kolomTerakhir);

        const kopLines = [
          'KOPERASI KONSUMEN CINDE BAROKAH SEJAHTERA',
          'AHU-0004655.AH.01.29.TAHUN 2026',
          'NIK : 3523070230034 - NIB : 2105260044023',
          'Telepon : 085177421819, email : konsumencindebarokahsejahtera@gmail.com',
          'Ds. Plumpung RT.05 RW.04 DS. Sumurcinde Kec. Soko Kab. Tuban'
        ];
        kopLines.forEach(function (t, i) {
          const r = i + 1;
          merges.push('A' + r + ':' + last + r);
          sheetData.insertBefore(mkRow(sheet, r, [inlineCell(sheet, 'A' + r, t, r === 1 ? S.title : 51)], r === 1 ? '20' : undefined), anchor);
        });

        // garis pemisah double
        sheetData.insertBefore(mkRow(sheet, 6, kolomHuruf.map(c => styledCell(sheet, c + '6', S.line))), anchor);

        // judul laporan
        merges.push('A7:' + last + '7');
        sheetData.insertBefore(mkRow(sheet, 7, [inlineCell(sheet, 'A7', @json($judulExport), S.boldCen)], '18'), anchor);

        // info parameter
        sheetData.insertBefore(mkRow(sheet, 8, [inlineCell(sheet, 'B8', 'Nama           : ' + @json($namaPemilik ?? 'Semua Pemilik'))]), anchor);
        sheetData.insertBefore(mkRow(sheet, 9, [inlineCell(sheet, 'B9', 'Alamat         : ' + @json($alamatTampil))]), anchor);
        sheetData.insertBefore(mkRow(sheet, 10, [inlineCell(sheet, 'B10', 'Hari, Tanggal  : ' + tanggalKopExport_{{ str_replace("-", "_", $tableId) }}())]), anchor);

        // 4. Tanda tangan
        const sigStart = numRows + 13;
        const kiri2 = kolomHuruf[1];
        [
          { r: sigStart,      cells: [{ c: 'A', c2: kiri2, t: 'Pengirim' }, { c: @json($kolomKanan1), c2: last, t: 'Penerima' }] },
          { r: sigStart + 3,  cells: [{ c: 'A', c2: kiri2, t: '( .................... )' }, { c: @json($kolomKanan1), c2: last, t: '( .................... )' }] },
          { r: sigStart + 5,  cells: [{ c: @json($kolomTengahAwal), c2: @json($kolomTengahAkhir), t: 'Mengetahui' }] },
          { r: sigStart + 6,  cells: [{ c: @json($kolomTengahAwal), c2: @json($kolomTengahAkhir), t: 'Ketua Pengurus' }] },
          { r: sigStart + 10, cells: [{ c: @json($kolomTengahAwal), c2: @json($kolomTengahAkhir), t: 'SUDIONO' }] }
        ].forEach(function (item) {
          const cells = item.cells.map(function (x) {
            merges.push(x.c + item.r + ':' + x.c2 + item.r);
            return inlineCell(sheet, x.c + item.r, x.t, 51);
          });
          sheetData.appendChild(mkRow(sheet, item.r, cells));
        });

        // 5. Merge cell
        const $mc = $('mergeCells', sheet);
        merges.forEach(function (ref) { $mc[0].appendChild(mk(sheet, 'mergeCell', { ref: ref })); });
        $mc.attr('count', merges.length);

        // 6. Style header + isi tabel
        const bodyStyle = { @foreach ($kolomHuruf as $h) {{ $h }}: {{ (($tipeKolom[$h] ?? 'text') === 'num' ? 'S.num' : '25') }}, @endforeach };
        $('row', sheet).each(function (i) {
          if (i === 10) {
            $('c', this).attr('s', S.header);
          } else if (i > 10 && i <= 9 + numRows) {
            $('c', this).each(function () {
              const col = $(this).attr('r').replace(/[0-9]/g, '');
              $(this).attr('s', bodyStyle[col] !== undefined ? bodyStyle[col] : 25);
            });
          }
        });

        // 7. Lebar kolom
        const widths = @json($lebarExcel);
        $('col', sheet).each(function (i) {
          if (widths[i]) { $(this).attr('width', widths[i]); }
        });

        // 8. Print setup
        const ws = $('worksheet', sheet)[0];
        const sheetPr = mk(sheet, 'sheetPr');
        sheetPr.appendChild(mk(sheet, 'pageSetUpPr', { fitToPage: '1' }));
        ws.insertBefore(sheetPr, ws.firstChild);
        $mc[0].after(mk(sheet, 'pageSetup', { orientation: @json($orientasi), fitToWidth: '1', fitToHeight: '0' }));

        // 9. Logo
        const logoKopB64 = @json($logoKopBase64 ?? '');
        const logoBmtB64 = @json($logoBase64 ?? '');
        if (logoKopB64 || logoBmtB64) {
          const drawing = mk(sheet, 'drawing');
          drawing.setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', 'rId1');
          ws.appendChild(drawing);

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
        const logoKopB64 = @json($logoKopBase64 ?? '');
        const logoBmtB64 = @json($logoBase64 ?? '');
        if (!logoKopB64 && !logoBmtB64) { return; }

        const kolomHuruf = @json($kolomHuruf);
        const items = [];
        if (logoKopB64) { items.push({ b64: logoKopB64, file: 'image1.png', col: 0, off: 190500, roff: 95250, cx: 527700, cy: 514350, name: 'Logo Koperasi' }); }
        if (logoBmtB64) { items.push({ b64: logoBmtB64, file: 'image' + (items.length + 1) + '.png', col: kolomHuruf.length - 1, off: 0, roff: 0, cx: 558200, cy: 558200, name: 'Logo BMT' }); }

        items.forEach(function (it) { zip.file('xl/media/' + it.file, it.b64, { base64: true }); });

        let relsDrawing = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
          '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        items.forEach(function (it, i) {
          relsDrawing += '<Relationship Id="rId' + (i + 1) + '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' + it.file + '"/>';
        });
        relsDrawing += '</Relationships>';
        zip.file('xl/drawings/_rels/drawing1.xml.rels', relsDrawing);

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
        const drawingXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
          '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"' +
          ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"' +
          ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
          anchors + '</xdr:wsDr>';
        zip.file('xl/drawings/drawing1.xml', drawingXml);

        zip.file('xl/worksheets/_rels/sheet1.xml.rels',
          '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
          '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
          '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>' +
          '</Relationships>');
      }
    },
    {
      extend: 'pdfHtml5',
      text: 'PDF',
      title: '',
      filename: '{{ $prefixFile }}-{{ str_replace([" ", "/", "\\\\"], "-", $namaPemilik ?? "Semua") }}-{{ $tanggalMulai ?: "awal" }}-sd-{{ $tanggalSelesai ?: "akhir" }}',
      pageSize: 'A4',
      orientation: @json($orientasi),
      exportOptions: {
        columns: @json($kolomExport),
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

        const kopColumns = [];
        if (logoKopBase64) {
          kopColumns.push({ image: logoKopBase64, width: 55.4, height: 54, alignment: 'center', margin: [0, 0, 6, 0] });
        } else {
          kopColumns.push({ width: 60, text: '' });
        }

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

        if (logoBmtBase64) {
          kopColumns.push({ image: logoBmtBase64, width: 58.6, height: 58.6, alignment: 'center', margin: [6, 0, 0, 0] });
        } else {
          kopColumns.push({ width: 60, text: '' });
        }

        const lineWidth = @json($orientasi === 'landscape' ? 800 : 555);
        const kopBlock = {
          stack: [
            { columns: kopColumns, alignment: 'center' },
            {
              canvas: [
                { type: 'line', x1: 0, y1: 5, x2: lineWidth, y2: 5, lineWidth: 1.5, lineColor: '#000000' },
                { type: 'line', x1: 0, y1: 7, x2: lineWidth, y2: 7, lineWidth: 0.5, lineColor: '#000000' }
              ],
              margin: [0, 4, 0, 10]
            },
            { text: @json($judulExport), fontSize: 11, bold: true, alignment: 'center', margin: [0, 0, 0, 8] },
            {
              columns: [
                {
                  width: '70%',
                  stack: [
                    {
                      columns: [
                        { text: 'Nama', width: 90, fontSize: 8.5, bold: true },
                        { text: ': ' + @json($namaPemilik ?? 'Semua Pemilik'), width: '*', fontSize: 8.5 }
                      ],
                      margin: [0, 0, 0, 2]
                    },
                    {
                      columns: [
                        { text: 'Alamat', width: 90, fontSize: 8.5, bold: true },
                        { text: ': ' + @json($alamatTampil), width: '*', fontSize: 8.5 }
                      ],
                      margin: [0, 0, 0, 2]
                    },
                    {
                      columns: [
                        { text: 'Hari, Tanggal', width: 90, fontSize: 8.5, bold: true },
                        { text: ': ' + tanggalKopExport_{{ str_replace("-", "_", $tableId) }}(), width: '*', fontSize: 8.5 }
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

        doc.content[1].table.widths = @json($lebarPdf);
        const rataKiri = @json($rataKiri);
        const rataKanan = @json($rataKanan);
        for (let i = 0; i < tableBody.length; i++) {
          const row = tableBody[i];
          if (i === 0) {
            row.forEach(cell => {
              cell.fillColor = '#95ce67';
              cell.alignment = 'center';
            });
          } else {
            row.forEach(function (cell, idx) {
              if (rataKiri.indexOf(idx) !== -1) {
                cell.alignment = 'left';
              } else if (rataKanan.indexOf(idx) !== -1) {
                cell.alignment = 'right';
              } else {
                cell.alignment = 'center';
              }
            });
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
