$(function () {
  const dashboardData = window.dashboardData;
  const dailyChartEl = document.querySelector("#chart");

  // Tinggi grafik mengikuti sisa ruang di dalam kartu, supaya bagian bawah
  // grafik menyentuh batas kotak (dikurangi offset bawaan ApexCharts 15px).
  function dailyChartHeight() {
    if (!dailyChartEl) {
      return 345;
    }

    const body = dailyChartEl.parentElement;
    const bodyHeight = body.getBoundingClientRect().height;
    const bodyStyle = window.getComputedStyle(body);
    const paddingY = parseFloat(bodyStyle.paddingTop || 0) + parseFloat(bodyStyle.paddingBottom || 0);
    const header = dailyChartEl.previousElementSibling;
    let headerHeight = 0;

    if (header) {
      headerHeight = header.getBoundingClientRect().height
        + parseFloat(window.getComputedStyle(header).marginBottom || 0);
    }

    const available = bodyHeight - paddingY - headerHeight;

    return Math.max(320, Math.round(available));
  }

  // =====================================
  // Daily sales and purchases
  // =====================================
  var dailyChartOptions = {
    series: [
      { name: "Penjualan (HJ)", data: dashboardData.sales },
      { name: "Pembelian (HB)", data: dashboardData.purchases },
    ],

    chart: {
      type: "bar",
      height: 345,
      offsetX: -15,
      toolbar: { show: true },
      foreColor: "#adb0bb",
      fontFamily: 'inherit',
      sparkline: { enabled: false },
    },


    colors: ["#16834A", "#DC3545"],


    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: "60%",
        borderRadius: [5],
        borderRadiusApplication: 'end',
        borderRadiusWhenStacked: 'all'
      },
    },
    markers: { size: 0 },

    dataLabels: {
      enabled: false,
    },


    legend: {
      show: true,
      position: "top",
      horizontalAlign: "right",
      markers: { width: 10, height: 10, radius: 12 },
      itemMargin: { horizontal: 8 },
    },


    grid: {
      borderColor: "rgba(0,0,0,0.1)",
      strokeDashArray: 3,
      xaxis: {
        lines: {
          show: false,
        },
      },
    },

    xaxis: {
      type: "category",
      categories: dashboardData.labels,
      labels: {
        style: { cssClass: "grey--text lighten-2--text fill-color" },
      },
    },


    yaxis: {
      show: true,
      min: 0,
      labels: {
        formatter: (value) => new Intl.NumberFormat("id-ID", {
          notation: "compact",
          maximumFractionDigits: 1
        }).format(value),
        style: {
          cssClass: "grey--text lighten-2--text fill-color",
        },
      },
    },
    stroke: {
      show: true,
      width: 3,
      lineCap: "butt",
      colors: ["transparent"],
    },


    tooltip: {
      theme: "light",
      y: {
        formatter: (value) => "Rp " + new Intl.NumberFormat("id-ID").format(value)
      }
    },

    responsive: [
      {
        breakpoint: 600,
        options: {
          plotOptions: {
            bar: {
              borderRadius: 3,
            }
          },
        }
      }
    ]


  };


  // =====================================
  // Breakup
  // =====================================
  var breakup = {
    color: "#adb5bd",
    series: [dashboardData.yearCost, Math.max(dashboardData.yearProfit, 0), Math.max(-dashboardData.yearProfit, 0)],
    labels: ["Modal terjual", "Laba", "Rugi"],
    chart: {
      width: 180,
      type: "donut",
      fontFamily: "Plus Jakarta Sans', sans-serif",
      foreColor: "#adb0bb",
    },
    plotOptions: {
      pie: {
        startAngle: 0,
        endAngle: 360,
        donut: {
          size: '75%',
        },
      },
    },
    stroke: {
      show: false,
    },

    dataLabels: {
      enabled: false,
    },

    legend: {
      show: false,
    },
    colors: ["#16834A", "#52A879", "#F9F9FD"],

    responsive: [
      {
        breakpoint: 991,
        options: {
          chart: {
            width: 150,
          },
        },
      },
    ],
    tooltip: {
      theme: "dark",
      fillSeriesColor: false,
      y: {
        formatter: (value) => "Rp " + new Intl.NumberFormat("id-ID").format(value)
      }
    },
  };

  var chart = new ApexCharts(document.querySelector("#breakup"), breakup);
  chart.render();



  // =====================================
  // Earning
  // =====================================
  var earning = {
    chart: {
      id: "sparkline3",
      type: "area",
      height: 60,
      sparkline: {
        enabled: true,
      },
      group: "sparklines",
      fontFamily: "Plus Jakarta Sans', sans-serif",
      foreColor: "#adb0bb",
    },
    series: [
      {
        name: "Laba / Rugi",
        color: "#16834A",
        data: dashboardData.profit,
      },
    ],
    stroke: {
      curve: "smooth",
      width: 2,
    },
    fill: {
      colors: ["#e7f5ec"],
      type: "solid",
      opacity: 0.05,
    },

    markers: {
      size: 0,
    },
    tooltip: {
      theme: "dark",
      y: {
        formatter: (value) => "Rp " + new Intl.NumberFormat("id-ID").format(value)
      },
      fixed: {
        enabled: true,
        position: "right",
      },
      x: {
        show: false,
      },
    },
  };
  new ApexCharts(document.querySelector("#earning"), earning).render();

  // Grafik harian dirender paling akhir supaya tinggi kartu kanan sudah final.
  // Tingginya dihitung agar bagian bawah grafik menyentuh batas kotak.
  function renderDailyChart() {
    dailyChartOptions.chart.height = dailyChartHeight();
    new ApexCharts(dailyChartEl, dailyChartOptions).render();
    // ApexCharts menambah 15px pada min-height container; hilangkan supaya pas.
    dailyChartEl.style.minHeight = "0px";
  }

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(renderDailyChart);
  } else {
    renderDailyChart();
  }
})