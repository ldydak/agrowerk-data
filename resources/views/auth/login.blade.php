@extends('layouts.auth')

@section('header')
    <h1 class="h2">Zaloguj się</h1>
    <p class="lead"></p>
@endsection
@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input id="email" type="email" placeholder="Podaj email" class="form-control form-control-lg @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
            
        </div>
        <div class="mb-3">
            <label class="form-label">Hasło</label>
            <input id="password" type="password"placeholder="Podaj hasło" class="form-control form-control-lg @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
                </div>
            <button type="submit" class="btn btn-lg btn-primary">
                Zaloguj się
            </button>
            @if (Route::has('password.request'))
                <a class="btn btn-link" href="{{ route('password.request') }}">
                    Przypomnieć hasło?
                </a>
            @endif
    </form>
@endsection
