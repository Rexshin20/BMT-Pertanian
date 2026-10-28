<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Koperasi Konsumen Cinde Barokah Sejahtera</title>
  <link rel="shortcut icon" type="image/png" href="{{ asset('assets/images/logos/bmt-cinde-barokah.png') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/styles.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/bmt-theme.css') }}?v={{ filemtime(public_path('assets/css/bmt-theme.css')) }}">
</head>
<body>
  <div class="page-wrapper bmt-login-page min-vh-100 d-flex align-items-center justify-content-center" id="main-wrapper">
        <div class="row justify-content-center w-100">
          <div class="col-sm-10 col-md-7 col-lg-5 col-xxl-3">
            <div class="card bmt-login-card mb-0">
              <div class="card-body">
                <div class="bmt-auth-brand text-center py-3 w-100">
                  <img src="{{ asset('assets/images/logos/bmt-cinde-barokah.png') }}" alt="Logo Koperasi Konsumen Cinde Barokah Sejahtera">
                  <strong>KOPERASI KONSUMEN</strong>
                  <small>Cinde Barokah Sejahtera</small>
                </div>
                <h4 class="text-center mb-4">Sign in to your account</h4>
                @if ($errors->any())
                  <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('login.store') }}">
                  @csrf
                  <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                  </div>
                  <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                  </div>
                  <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                  </div>
                  <button type="submit" class="btn btn-primary w-100 py-2 mb-3">Login</button>
                </form>
              </div>
              @include('partials.footer', ['footerClass' => 'bmt-login-footer'])
            </div>
          </div>
        </div>
  </div>
</body>
</html>
