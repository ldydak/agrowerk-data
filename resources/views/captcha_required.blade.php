<!DOCTYPE html>
<html>
<head>
    <title>Weryfikacja przed pobraniem</title>
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    <style>
        body{
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 20px;
            font-family: system-ui, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }
    </style>
</head>
<body >
<img src="https://agrowerk.pl/img/sanipro_logo.svg" style="width: 180px;" alt="agrowerk.pl">
<h2>Aby pobrać plik, potwierdź że nie jesteś botem</h2>

@if(session('error'))
    <p style="color:red;">{{ session('error') }}</p>
@endif

<form method="POST" action="{{ route('download-file.verify', $hash) }}" style="display: flex;
    flex-direction: column;">
    @csrf

    <div class="h-captcha" data-sitekey="{{ env('HCAPTCHA_SITEKEY') }}"></div>

    <button type="submit" style="margin-top: 20px;
    padding: 15px;
    background-color: #3c50e0;
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: 15px;
    font-weight: 500;cursor:pointer">
        Potwierdź i pobierz plik
    </button>
</form>

</body>
</html>
