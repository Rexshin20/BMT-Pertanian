<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Koperasi Konsumen Cinde Barokah Sejahtera') - Koperasi Konsumen Cinde Barokah Sejahtera</title>
  <link rel="shortcut icon" type="image/png" href="{{ asset('assets/images/logos/bmt-cinde-barokah.png') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/styles.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/bmt-theme.css') }}?v={{ filemtime(public_path('assets/css/bmt-theme.css')) }}">
  @stack('styles')
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full" data-sidebar-position="fixed" data-header-position="fixed">
    <aside class="left-sidebar">
      <div>
        <div class="brand-logo d-flex align-items-center justify-content-between">
          <div class="bmt-brand text-nowrap">
            <img src="{{ asset('assets/images/logos/bmt-cinde-barokah.png') }}" alt="Logo Koperasi Konsumen Cinde Barokah Sejahtera">
            <span class="bmt-brand-name"><strong>KOPERASI KONSUMEN</strong><small>Cinde Barokah Sejahtera</small></span>
          </div>
          <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse"><i class="ti ti-x fs-8"></i></div>
        </div>
        <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
          <ul id="sidebarnav">
            <li class="nav-small-cap"><i class="ti ti-dots nav-small-cap-icon fs-4"></i><span class="hide-menu">HOME</span></li>
            <li class="sidebar-item">
              <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                <span><i class="ti ti-layout-dashboard"></i></span><span class="hide-menu">Dashboard</span>
              </a>
            </li>
            <li class="nav-small-cap"><i class="ti ti-dots nav-small-cap-icon fs-4"></i><span class="hide-menu">VIEW</span></li>
            <li class="sidebar-item">
              <a class="sidebar-link {{ request()->routeIs('penjualan.index', 'penjualan.master-data') ? 'active' : '' }}" href="{{ route('penjualan.index') }}">
                <span><i class="ti ti-shopping-cart"></i></span><span class="hide-menu">Penjualan</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link {{ request()->routeIs('pembelian.index', 'pembelian.master-data') ? 'active' : '' }}" href="{{ route('pembelian.index') }}">
                <span><i class="ti ti-basket"></i></span><span class="hide-menu">Pembelian</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link {{ request()->routeIs('laporan.laba-rugi') ? 'active' : '' }}" href="{{ route('laporan.laba-rugi') }}">
                <span><i class="ti ti-report-money"></i></span><span class="hide-menu">Laba Rugi</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link {{ request()->routeIs('laporan.stok-akhir') ? 'active' : '' }}" href="{{ route('laporan.stok-akhir') }}">
                <span><i class="ti ti-packages"></i></span><span class="hide-menu">Stok Akhir</span>
              </a>
            </li>
            <li class="nav-small-cap"><i class="ti ti-dots nav-small-cap-icon fs-4"></i><span class="hide-menu">MASTER DATA</span></li>
            <li class="sidebar-item">
              <a class="sidebar-link {{ request()->routeIs('pemilik.*') ? 'active' : '' }}" href="{{ route('pemilik.index') }}">
                <span><i class="ti ti-id"></i></span><span class="hide-menu">Master ID Nama</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link {{ request()->routeIs('penjualan.create') ? 'active' : '' }}" href="{{ route('penjualan.create') }}">
                <span><i class="ti ti-shopping-cart-plus"></i></span><span class="hide-menu">Create Penjualan</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link {{ request()->routeIs('pembelian.create') ? 'active' : '' }}" href="{{ route('pembelian.create') }}">
                <span><i class="ti ti-receipt"></i></span><span class="hide-menu">Create Pembelian</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link {{ request()->routeIs('impor.*') ? 'active' : '' }}" href="{{ route('impor.index') }}">
                <span><i class="ti ti-file-import"></i></span><span class="hide-menu">Impor</span>
              </a>
            </li>
            <li class="nav-small-cap"><i class="ti ti-dots nav-small-cap-icon fs-4"></i><span class="hide-menu">ACCOUNT</span></li>
            <li class="sidebar-item">
              <a class="sidebar-link {{ request()->routeIs('account.*') ? 'active' : '' }}" href="{{ route('account.edit') }}">
                <span><i class="ti ti-user"></i></span><span class="hide-menu">Edit Account</span>
              </a>
            </li>
            <li class="sidebar-item">
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="sidebar-link border-0 bg-transparent w-100 text-start" type="submit">
                  <span><i class="ti ti-logout"></i></span><span class="hide-menu">Logout</span>
                </button>
              </form>
            </li>
          </ul>
        </nav>
      </div>
    </aside>
    <div class="body-wrapper">
      <header class="app-header">
        <nav class="navbar navbar-expand-lg navbar-light">
          <ul class="navbar-nav">
            <li class="nav-item d-block d-xl-none">
              <a class="nav-link sidebartoggler nav-icon-hover" id="headerCollapse" href="javascript:void(0)"><i class="ti ti-menu-2"></i></a>
            </li>
          </ul>
          <div class="navbar-collapse justify-content-end px-0">
            <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-end">
              <li class="nav-item"><span class="nav-link">{{ auth()->user()->name }}</span></li>
              <li class="nav-item">
                <img src="{{ asset('assets/images/profile/user-1.jpg') }}" alt="Profil" width="35" height="35" class="rounded-circle">
              </li>
            </ul>
          </div>
        </nav>
      </header>
      <div class="container-fluid app-content-with-footer">
        <div class="d-flex align-items-center justify-content-between mb-4">
          <h4 class="fw-semibold mb-0">@yield('page-title')</h4>
        </div>
        @if (session('success'))
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        @endif
        @if ($errors->any())
          <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">Periksa kembali data yang dimasukkan:</div>
            <ul class="mb-0">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif
        @yield('content')
      </div>
    </div>
  </div>
  <script src="{{ asset('assets/libs/jquery/dist/jquery.min.js') }}"></script>
  <script src="{{ asset('assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('assets/js/sidebarmenu.js') }}"></script>
  <script src="{{ asset('assets/js/app.min.js') }}"></script>
  @stack('scripts')
</body>
</html>
