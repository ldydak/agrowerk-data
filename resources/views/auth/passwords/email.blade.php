@extends('layouts.auth')

@section('header')
<h1 class="h2">Reset hasła</h1>
<p class="lead"></p>
@endsection

@section('content')
    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" class="form-control form-control-lg @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <button type="submit" class="btn btn-lg btn-primary">
            Wyślij link do resetu hasła
        </button>
    </form>
@endsection
