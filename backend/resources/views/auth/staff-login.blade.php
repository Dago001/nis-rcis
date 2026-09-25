@extends('layout')
@section('title', 'Staff sign in')
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
@endsection
