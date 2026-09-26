@extends('layout')
@section('title', 'Applicant sign in')
@section('image', 'hq-entrance.jpg')
@section('headline')Obtain your <span>Residence Card</span>@endsection
@section('tagline', 'Apply, pay, book your biometrics appointment and track your application online.')
@section('content')
    <h1>Applicant sign in</h1>
    <p class="sub">Sign in to apply for, track or renew your residence card.</p>
    @include('auth._errors')
    <form method="POST" action="{{ route('login.applicant') }}">
        @csrf
        <label for="identifier">E-mail address</label>
        <input id="identifier" type="email" name="identifier" value="{{ old('identifier') }}" required autofocus autocomplete="email">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <button type="submit">Sign in</button>
    </form>
    <div class="links">
        <a href="{{ $registerUrl }}">Create an account</a>
        <a href="{{ $forgotUrl }}">Forgot password?</a>
    </div>
@endsection
