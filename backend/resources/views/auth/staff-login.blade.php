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
        <label for="identifier">Service number or username</label>
        <input id="identifier" name="identifier" value="{{ old('identifier') }}" required autofocus>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <button type="submit">Sign in</button>
    </form>
    <div class="notice">
        <span aria-hidden="true">🔒</span>
        <span>Forgot your password? Contact your system administrator to issue a temporary password.</span>
    </div>
@endsection
