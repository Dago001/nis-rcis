@extends('layout')
@section('title', 'Two-factor sign in')
@section('image', 'hq-reception.jpg')
@section('headline')Staff <span>Console</span>@endsection
@section('tagline', 'A code from your authenticator app protects your account even if your password is stolen.')
@section('content')
    @if ($setup)
        <h1>Set up your authenticator app</h1>
        <p class="sub">Staff accounts need a second sign-in step. Install <strong>Google Authenticator</strong> or <strong>Microsoft Authenticator</strong> on your phone, then scan this code.</p>
        <div class="qr">{!! $qr !!}</div>
        <p class="hint">Can't scan it? Choose "enter a setup key" in the app and type:<br><code class="secret">{{ $secret }}</code></p>
    @else
        <h1>Enter your authenticator code</h1>
        <p class="sub">Open your authenticator app and enter the 6-digit code for <strong>NIS-RCIS</strong>.</p>
    @endif
    @include('auth._errors')
    <form method="POST" action="{{ url('/login/staff/two-factor') }}" autocomplete="off">
        @csrf
        <label for="code">6-digit code</label>
        <input id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" required autofocus autocomplete="one-time-code">
        <button type="submit">{{ $setup ? 'Confirm and sign in' : 'Verify and sign in' }}</button>
    </form>
    <div class="notice">
        <span aria-hidden="true">🔒</span>
        <span>Lost your phone or changed it? Ask the Super Administrator to reset your authenticator; you will set it up again at your next sign-in.</span>
    </div>
@endsection
