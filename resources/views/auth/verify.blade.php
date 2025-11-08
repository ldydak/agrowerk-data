@extends('layouts.auth')

@section('header')
<h1 class="h2">Weryfikacja</h1>
<p class="lead"></p>
@endsection

@section('content')
        @if (session('resent'))
            <div class="alert alert-success" role="alert">
                Na Twój adres e-mail został wysłany świeży link weryfikacyjny.
            </div>
        @endif

        Zanim przejdziesz dalej, sprawdź swoją wiadomość e-mail, aby otrzymać link weryfikacyjny.
        Jeśli nie otrzymałeś maila,
        <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="btn btn-link p-0 m-0 align-baseline">kliknij tutaj, aby otrzymać kolejny</button>.
        </form>
@endsection
