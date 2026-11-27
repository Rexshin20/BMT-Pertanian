(function () {
  function initTableSearch(options) {
    var input = document.querySelector(options.input);
    var table = document.querySelector(options.table);

    if (!input || !table || !table.tBodies.length) {
      return;
    }

    var body = table.tBodies[0];
    var rows = Array.prototype.slice.call(body.querySelectorAll(options.row));
    var empty = body.querySelector(options.empty);

    if (!rows.length) {
      return;
    }

    var noResult = document.createElement("tr");
    var noResultCell = document.createElement("td");
    noResultCell.colSpan = options.colspan;
    noResultCell.className = "text-center text-muted py-4";
    noResultCell.textContent = options.noResultText;
    noResult.appendChild(noResultCell);
    noResult.hidden = true;
    body.appendChild(noResult);

    function normalize(value) {
      return (value || "").toLowerCase().trim();
    }

    function filterRows() {
      var keyword = normalize(input.value);
      var shown = 0;

      rows.forEach(function (row) {
        var searchable = row.getAttribute("data-" + options.searchAttr) || "";
        var matched = !keyword || normalize(searchable).indexOf(keyword) !== -1;
        row.hidden = !matched;

        if (matched) {
          shown += 1;
          var number = row.querySelector(options.number);
          if (number) {
            number.textContent = shown;
          }
        }
      });

      if (empty) {
        empty.hidden = shown > 0;
      }
      noResult.hidden = shown > 0 || !keyword;
    }

    input.addEventListener("input", filterRows);
    input.addEventListener("search", filterRows);
  }

  initTableSearch({
    input: "#stok-akhir-search",
    table: "#stok-akhir-table",
    row: "[data-stock-row]",
    number: "[data-stock-number]",
    empty: "[data-stock-empty]",
    searchAttr: "stock-search",
    colspan: 7,
    noResultText: "Barang tidak ditemukan.",
  });

  initTableSearch({
    input: "#omset-toko-search",
    table: "#omset-toko-table",
    row: "[data-store-row]",
    number: "[data-store-number]",
    empty: "[data-store-empty]",
    searchAttr: "store-search",
    colspan: 5,
    noResultText: "Toko tidak ditemukan.",
  });
})();
