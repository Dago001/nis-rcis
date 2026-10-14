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
        <div class="pw">
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
            <button type="button" class="eye" aria-label="Show password" aria-pressed="false" onclick="togglePw(event, this)">
                <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24M1 1l22 22"/></svg>
            </button>
        </div>
        <label for="password">New password</label>
        <div class="pw">
            <input id="password" type="password" name="password" required minlength="6" autocomplete="new-password">
            <button type="button" class="eye" aria-label="Show password" aria-pressed="false" onclick="togglePw(event, this)">
                <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24M1 1l22 22"/></svg>
            </button>
        </div>
        <p class="hint">At least 6 characters with uppercase and lowercase letters, a number, and a special character.</p>
        <label for="password_confirmation">Confirm new password</label>
        <div class="pw">
            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="6" autocomplete="new-password">
            <button type="button" class="eye" aria-label="Show password" aria-pressed="false" onclick="togglePw(event, this)">
                <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24M1 1l22 22"/></svg>
            </button>
        </div>
        <button type="submit">Update password and continue</button>
    </form>
@endsection
