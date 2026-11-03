@extends('layout')
@section('title', 'Two-factor sign in')
@section('image', 'hq-reception.jpg')
@section('headline')Staff <span>Console</span>@endsection
@section('tagline', 'A code from your authenticator app protects your account even if your password is stolen.')
@section('content')
    @if ($setup)
        <h1>Set up your authenticator app</h1>
        <p class="sub">Staff accounts require an authenticator app. Open <strong>Google Authenticator</strong> or <strong>Microsoft Authenticator</strong> on your phone and scan the barcode below.</p>
        <div class="qr">{!! $qr !!}</div>
        <p class="hint" style="text-align:center; margin-bottom:12px;">Can't scan the barcode? Choose "enter a setup key" in your app and type:<br><code class="secret">{{ $secret }}</code></p>
        @if(!empty($canSwitchToVerify))
            <p style="text-align:center; margin-bottom:14px;">
                <a href="{{ route('login.staff.two-factor') }}" style="font-size:0.85rem; color:#0f766e; text-decoration:underline;">Already scanned? Enter code directly</a>
            </p>
        @endif
    @else
        <h1>Enter your authenticator code</h1>
        <p class="sub">Open your authenticator app and enter the current 6-digit code for <strong>NIS-RCIS</strong>.</p>
    @endif

    @include('auth._errors')

    <form method="POST" action="{{ url('/login/staff/two-factor') }}" autocomplete="off">
        @csrf
        @if(request('returnTo'))
            <input type="hidden" name="returnTo" value="{{ request('returnTo') }}">
        @endif
        @if($setup)
            <input type="hidden" name="setup" value="1">
        @endif
        <label for="code">6-digit code</label>
        <input id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" required autofocus autocomplete="one-time-code" style="text-align:center; font-size:1.35rem; letter-spacing:0.25em; font-weight:700;" placeholder="000 000">
        <button type="submit">{{ $setup ? 'Confirm and sign in' : 'Verify and sign in' }}</button>
    </form>
    <div class="notice">
        <span aria-hidden="true">🔒</span>
        <span>Lost your phone or changed it? Contact a Super Administrator to reset your authenticator.</span>
    </div>
@endsection
