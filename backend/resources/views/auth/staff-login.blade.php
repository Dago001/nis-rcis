@extends('layout')
@section('title', 'Staff sign in')
@section('image', 'hq-reception.jpg')
@section('headline')Staff <span>Console</span>@endsection
@section('tagline', 'Approval queue, biometrics desk, card register and reports for authorised NIS officers.')
@section('content')
    <h1>Staff sign in</h1>
    <p class="sub">Authorised NIS officers only. All access is logged.</p>
    @include('auth._errors')
    <form method="POST" action="{{ route('login.staff') }}" autocomplete="off">
        @csrf
        @if(request('returnTo'))
            <input type="hidden" name="returnTo" value="{{ request('returnTo') }}">
        @endif
        <label for="identifier">Service number or username</label>
        <input id="identifier" name="identifier" value="{{ old('identifier') }}" required autofocus>
        <label for="password">Password</label>
        <div class="pw">
            <input id="password" type="password" name="password" required autocomplete="current-password">
            <button type="button" class="eye" aria-label="Show password" aria-pressed="false" onclick="togglePw(event, this)">
                <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24M1 1l22 22"/></svg>
            </button>
        </div>
        <button type="submit">Sign in</button>
    </form>
    <div class="notice">
        <span aria-hidden="true">🔒</span>
        <span>Forgot your password? Contact your system administrator to issue a temporary password.</span>
    </div>
@endsection
