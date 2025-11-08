

@if (env('REGISTER')=='OFF')
    @php
        header("Location: " . URL::to('/'), true, 302);
        exit();
    @endphp
@endif



@extends('layouts.auth')

@section('header')
<h1 class="h2">Rejestracja</h1>
<p class="lead"></p>
@endsection

@section('content')

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Imię i nazwisko</label>
                <input id="name" type="text" class="form-control form-control-lg @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                @error('name')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
        </div>
        <div class="mb-3">
            <label class="form-label">E-mail</label>
                <input id="email" type="email" class="form-control form-control-lg @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">

                @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Hasło</label>
                <input id="password" type="password" class="form-control form-control-lg @error('password') is-invalid @enderror" name="password" required>

                @error('password')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Powtórz hasło</label>
                <input id="password-confirm" type="password" class="form-control form-control-lg @error('password-confirm') is-invalid @enderror" name="password_confirmation" required>

                @error('password-confirm')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
        </div>
        <div class="d-grid gap-2 mt-3">
            <button type="submit" class="btn btn-lg btn-primary">
                Zarejestruj się
            </button>
        </div>
    </form>
@endsection
