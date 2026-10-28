<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - Koperasi Konsumen Cinde Barokah Sejahtera</title>
  <link rel="shortcut icon" type="image/png" href="{{ asset('assets/images/logos/bmt-cinde-barokah.png') }}" />
  <link rel="stylesheet" href="{{ asset('assets/css/styles.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/css/bmt-theme.css') }}" />
</head>

<body>
  <!--  Body Wrapper -->
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">
    <!-- Sidebar Start -->
    <aside class="left-sidebar">
      <!-- Sidebar scroll-->
      <div>
        <div class="brand-logo d-flex align-items-center justify-content-between">
          <div class="bmt-brand text-nowrap">
            <img src="{{ asset('assets/images/logos/bmt-cinde-barokah.png') }}" alt="Logo Koperasi Konsumen Cinde Barokah Sejahtera" />
            <span class="bmt-brand-name"><strong>KOPERASI KONSUMEN</strong><small>Cinde Barokah Sejahtera</small></span>
          </div>
          <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
            <i class="ti ti-x fs-8"></i>
          </div>
        </div>
        <!-- Sidebar navigation-->
        <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
          <ul id="sidebarnav">
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">Home</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="{{ route('dashboard') }}" aria-expanded="false">
                <span>
                  <i class="ti ti-layout-dashboard"></i>
                </span>
                <span class="hide-menu">Dashboard</span>
              </a>
            </li>
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">VIEW</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="{{ route('penjualan.index') }}">
                <span><i class="ti ti-shopping-cart"></i></span>
                <span class="hide-menu">Penjualan</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="{{ route('pembelian.index') }}">
                <span><i class="ti ti-basket"></i></span>
                <span class="hide-menu">Pembelian</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="{{ route('laporan.laba-rugi') }}">
                <span><i class="ti ti-report-money"></i></span>
                <span class="hide-menu">Laba Rugi</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="{{ route('laporan.stok-akhir') }}">
                <span><i class="ti ti-packages"></i></span>
                <span class="hide-menu">Stok Akhir</span>
              </a>
            </li>
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">MASTER DATA</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="{{ route('pemilik.index') }}">
                <span><i class="ti ti-id"></i></span>
                <span class="hide-menu">Master ID Nama</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="{{ route('penjualan.create') }}">
                <span><i class="ti ti-shopping-cart-plus"></i></span>
                <span class="hide-menu">Create Penjualan</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="{{ route('pembelian.create') }}">
                <span><i class="ti ti-receipt"></i></span>
                <span class="hide-menu">Create Pembelian</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="{{ route('impor.index') }}">
                <span><i class="ti ti-file-import"></i></span>
                <span class="hide-menu">Impor</span>
              </a>
            </li>
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">ACCOUNT</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="{{ route('account.edit') }}" aria-expanded="false">
                <span>
                  <i class="ti ti-user"></i>
                </span>
                <span class="hide-menu">Edit Account</span>
              </a>
            </li>
            <li class="sidebar-item">
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="sidebar-link border-0 bg-transparent w-100 text-start" type="submit">
                  <span><i class="ti ti-logout"></i></span>
                  <span class="hide-menu">Logout</span>
                </button>
              </form>
            </li>
          </ul>
        </nav>
        <!-- End Sidebar navigation -->
      </div>
      <!-- End Sidebar scroll-->
    </aside>
    <!--  Sidebar End -->
    <!--  Main wrapper -->
    <div class="body-wrapper">
      <!--  Header Start -->
      <header class="app-header">
        <nav class="navbar navbar-expand-lg navbar-light">
          <ul class="navbar-nav">
            <li class="nav-item d-block d-xl-none">
              <a class="nav-link sidebartoggler nav-icon-hover" id="headerCollapse" href="javascript:void(0)">
                <i class="ti ti-menu-2"></i>
              </a>
            </li>
            <li class="nav-item">
              <span class="nav-link">Koperasi Konsumen Cinde Barokah Sejahtera</span>
            </li>
          </ul>
          <div class="navbar-collapse justify-content-end px-0" id="navbarNav">
            <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-end">
              <li class="nav-item dropdown">
                <a class="nav-link nav-icon-hover" href="javascript:void(0)" id="drop2" data-bs-toggle="dropdown"
                  aria-expanded="false">
                  <img src="{{ asset('assets/images/profile/user-1.jpg') }}" alt="" width="35" height="35" class="rounded-circle">
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop2">
                  <div class="message-body">
                    <a href="{{ route('account.edit') }}" class="d-flex align-items-center gap-2 dropdown-item">
                      <i class="ti ti-user fs-6"></i>
                      <p class="mb-0 fs-3">My Profile</p>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                      @csrf
                      <button type="submit" class="btn btn-outline-primary mx-3 mt-2 d-block">Logout</button>
                    </form>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </nav>
      </header>
      <!--  Header End -->
      <div class="container-fluid app-content-with-footer">
        <!--  Row 1 -->
        <div class="row">
          <div class="col-lg-8 d-flex align-items-stretch">
            <div class="card w-100">
              <div class="card-body">
                <div class="d-sm-flex d-block align-items-center justify-content-between mb-9">
                  <div class="mb-3 mb-sm-0">
                    <h5 class="card-title fw-semibold">Penjualan & Pembelian Harian</h5>
                  </div>
                  <span class="text-muted">{{ $dashboardData['monthName'] }}</span>
                </div>
                <div id="chart"></div>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="row">
              <div class="col-lg-12">
                <!-- Yearly Breakup -->
                <div class="card overflow-hidden">
                  <div class="card-body p-4">
                    <h5 class="card-title mb-9 fw-semibold">Ringkasan Penjualan {{ $dashboardData['year'] }}</h5>
                    <div class="row align-items-center">
                      <div class="col-8">
                        <h4 class="fw-semibold mb-3">Rp {{ number_format($dashboardData['yearSales'], 0, ',', '.') }}</h4>
                        <div class="d-flex align-items-center">
                          <div class="me-4">
                            <span class="round-8 bg-primary rounded-circle me-2 d-inline-block"></span>
                            <span class="fs-2">Modal Rp {{ number_format($dashboardData['yearCost'], 0, ',', '.') }}</span>
                          </div>
                          <div>
                            <span class="round-8 bg-light-primary rounded-circle me-2 d-inline-block"></span>
                            <span class="fs-2">{{ $dashboardData['yearProfit'] < 0 ? 'Rugi' : 'Laba' }} Rp {{ number_format(abs($dashboardData['yearProfit']), 0, ',', '.') }}</span>
                          </div>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="d-flex justify-content-center">
                          <div id="breakup"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-12">
                <!-- Monthly Earnings -->
                <div class="card">
                  <div class="card-body">
                    <div class="row alig n-items-start">
                      <div class="col-8">
                        <h5 class="card-title mb-9 fw-semibold">Laba / Rugi Bulan Ini</h5>
                        <h4 class="fw-semibold mb-3 {{ $dashboardData['monthProfit'] < 0 ? 'text-danger' : 'text-success' }}">Rp {{ number_format($dashboardData['monthProfit'], 0, ',', '.') }}</h4>
                        <p class="fs-3 mb-0 text-muted">{{ $dashboardData['monthName'] }}</p>
                      </div>
                      <div class="col-4">
                        <div class="d-flex justify-content-end">
                          <div
                            class="text-white bg-secondary rounded-circle p-6 d-flex align-items-center justify-content-center">
                            <i class="ti ti-report-money fs-6"></i>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div id="earning"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-lg-4 d-flex align-items-stretch">
            <div class="card w-100">
              <div class="card-body p-4">
                <div class="mb-4">
                  <h5 class="card-title fw-semibold">Aktivitas Transaksi</h5>
                </div>
                <ul class="timeline-widget mb-0 position-relative mb-n5">
                  @forelse ($recentTransactions as $transaction)
                    <li class="timeline-item d-flex position-relative overflow-hidden">
                      <div class="timeline-time text-dark flex-shrink-0 text-end">{{ $transaction['date']->format('d/m') }}</div>
                      <div class="timeline-badge-wrap d-flex flex-column align-items-center">
                        <span class="timeline-badge border-2 border border-{{ $transaction['type'] === 'Penjualan' ? 'primary' : 'danger' }} flex-shrink-0 my-8"></span>
                        @if (!$loop->last)<span class="timeline-badge-border d-block flex-shrink-0"></span>@endif
                      </div>
                      <div class="timeline-desc fs-3 text-dark mt-n1">
                        <span class="fw-semibold">{{ $transaction['type'] }}</span> {{ $transaction['name'] }}
                        <span class="d-block text-muted">Rp {{ number_format($transaction['amount'], 0, ',', '.') }}</span>
                      </div>
                    </li>
                  @empty
                    <li class="text-muted">Belum ada transaksi.</li>
                  @endforelse
                </ul>
              </div>
            </div>
          </div>
          <div class="col-lg-8 d-flex align-items-stretch">
            <div class="card w-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                  <h5 class="card-title fw-semibold mb-0">Transaksi Terbaru</h5>
                  <a href="{{ route('penjualan.index') }}" class="text-primary">Lihat semua</a>
                </div>
                <div class="table-responsive">
                  <table class="table text-nowrap mb-0 align-middle">
                    <thead class="text-dark fs-4">
                      <tr>
                        <th class="border-bottom-0"><h6 class="fw-semibold mb-0">No.</h6></th>
                        <th class="border-bottom-0"><h6 class="fw-semibold mb-0">Tgl</h6></th>
                        <th class="border-bottom-0"><h6 class="fw-semibold mb-0">Nama Barang</h6></th>
                        <th class="border-bottom-0"><h6 class="fw-semibold mb-0">Transaksi</h6></th>
                        <th class="border-bottom-0 text-end"><h6 class="fw-semibold mb-0">Jumlah</h6></th>
                        <th class="border-bottom-0 text-end"><h6 class="fw-semibold mb-0">Total</h6></th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse ($recentTransactions as $transaction)
                        <tr>
                          <td class="border-bottom-0"><h6 class="fw-semibold mb-0">{{ $transaction['id'] }}</h6></td>
                          <td class="border-bottom-0">{{ $transaction['date']->format('d/m/Y') }}</td>
                          <td class="border-bottom-0"><h6 class="fw-semibold mb-0">{{ $transaction['name'] }}</h6><span class="text-muted">{{ $transaction['unit'] }}</span></td>
                          <td class="border-bottom-0"><span class="badge {{ $transaction['type'] === 'Penjualan' ? 'bg-primary' : 'bg-danger' }} rounded-0 fw-semibold">{{ $transaction['type'] }}</span></td>
                          <td class="border-bottom-0 text-end">{{ rtrim(rtrim(number_format($transaction['quantity'], 3, ',', '.'), '0'), ',') }}</td>
                          <td class="border-bottom-0 text-end"><h6 class="fw-semibold mb-0 fs-4">Rp {{ number_format($transaction['amount'], 0, ',', '.') }}</h6></td>
                        </tr>
                      @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="{{ asset('assets/libs/jquery/dist/jquery.min.js') }}"></script>
  <script src="{{ asset('assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('assets/js/sidebarmenu.js') }}"></script>
  <script src="{{ asset('assets/js/app.min.js') }}"></script>
  <script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
  <script src="{{ asset('assets/libs/simplebar/dist/simplebar.js') }}"></script>
  <script>window.dashboardData = @json($dashboardData);</script>
  <script src="{{ asset('assets/js/dashboard.js') }}?v={{ filemtime(public_path('assets/js/dashboard.js')) }}"></script>
</body>

</html>