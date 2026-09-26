@extends('layout')
@section('title', 'Authorize application')
@section('content')
    <h1>Authorize {{ $client->name }}</h1>
    <p class="sub">This application is requesting access to your NIS-RCIS account.</p>
    @if (count($scopes) > 0)
        <p><strong>It will be able to:</strong></p>
        <ul class="scopes">
            @foreach ($scopes as $scope)
                <li>{{ $scope->description }}</li>
            @endforeach
        </ul>
    @endif
    <form method="POST" action="{{ route('passport.authorizations.approve') }}">
        @csrf
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <button type="submit">Authorize</button>
    </form>
    <form method="POST" action="{{ route('passport.authorizations.deny') }}">
        @csrf
        @method('DELETE')
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <button type="submit" class="secondary">Cancel</button>
    </form>
@endsection
