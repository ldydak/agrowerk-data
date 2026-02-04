<!DOCTYPE html>
<html>
<head>
    <title>Weryfikacja przed pobraniem</title>
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
</head>
<body style="display: flex;
    flex-direction: column;
    align-items: center;
    margin-top: 20px;">
<img src="https://sanipro.pl/img/sanipro_logo.svg" style="width: 180px;" alt="Sanipro.pl">
<h2>Aby pobrać plik, potwierdź że nie jesteś botem</h2>

@if(session('error'))
    <p style="color:red;">{{ session('error') }}</p>
@endif

<form method="POST" action="{{ route('download-file.verify', $hash) }}" style="display: flex;
    flex-direction: column;">
    @csrf

    <div class="h-captcha" data-sitekey="{{ env('HCAPTCHA_SITEKEY') }}"></div>

    <button type="submit" style="margin-top: 20px;
    padding: 10px 20px;
    background-color: #3c50e0;
    border: none;
    border-radius: 8px;
    color: #000;
    font-size: 15px;
    font-weight: 500;cursor:pointer">
        Potwierdź i pobierz plik
    </button>
</form>

</body>
</html>
