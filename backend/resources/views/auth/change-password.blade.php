@extends('layout')
@section('title', 'Change password')
@section('image', 'hq-reception.jpg')
@section('headline')Secure your <span>account</span>@endsection
@section('tagline', 'Replace your temporary password with one only you know.')
@section('content')
    <h1>Set a new password</h1>
    <p class="sub">You must replace your temporary password before continuing.</p>
    @include('auth._errors')
    <form method="POST" action="{{ route('password.change') }}">
        @csrf
        <label for="current_password">Current (temporary) password</label>
        <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
        <label for="password">New password</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">
        <p class="hint">At least 12 characters with upper and lower case letters, a number and a symbol.</p>
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        <button type="submit">Update password and continue</button>
    </form>
@endsection
