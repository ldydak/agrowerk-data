@extends('layouts.app')

@section('header')
<h2 class="h3 my-3"><strong>Ustawienia</strong> cen i marży</h2>
@endsection
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Ustawienia cen dla całego sklepu</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('settings.prices.update') }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <p class="text-muted">Wpisz kurs euro, który będzie brany do obliczenia aktualnych cen w sklepie przy kolejnym imporcie lub aktualizacji automatycznej.</p>
                            <label class="form-label" for="exchangeRate">Kurs euro</label>
                            <input type="text" name="exchangeRate" data-inputmask="'mask': '9.99'" class="form-control" value="{{$data->exchangeRate}}" required>
                            @error('exchangeRate')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md mb-3">
                            <p class="text-muted">Wpisz Twoją marżę, która będzie dodawana do ceny produktu i wariantu z importowanego sklepu przy kolejnym imporcie lub aktualizacji automatycznej.</p>
                            <label class="form-label" for="profit">Marża (%)</label>
                            <input type="text" min="0" name="profit" data-inputmask="'mask': '99'" value="{{$data->profit}}" class="form-control" required>
                            @error('profit')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">Zapisz</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Przeliczanie</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('settings.prices.countAndUpdatePrices') }}" method="post" id="countAndUpdatePrices">
                    @csrf
                    <div class="row">
                        <div class="col-12">
                            <p class="text-muted">Każdy produkt i wariant posiada zapisaną oryginalną cenę w euro z oryginalnego sklepu (zapisaną podczas ostatniego importu albo aktualizacji automatycznej). Jeśli zmieniłeś powyżej kurs euro, bądź marżę sklepu, możesz wymusić dokonanie przeliczenia cen PLN w sklepie. W przeciwnym razie przeliczenie wykona się dopiero podczas następnego importu lub aktualizacji automatycznej.</p>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-success">Przelicz i zaktualizuj ceny PLN w sklepie</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

@endsection
@section('scripts')
<script>
  $('#countAndUpdatePrices button[type=submit]').on('click', function(e){
      e.preventDefault();
      Swal.fire({
            title: 'Czy jesteś pewien?',
            text: 'Proces rozpocznie zmianę cen w systemie. Najlepiej wykonaj to w czasie o małym natężeniu ruchu w sklepie.',
            icon: 'warning',
            showCancelButton: false,
            confirmButtonText: 'Tak, rozpocznij',
            showCloseButton: true,
        }).then((result) => {
            if(result.isConfirmed){
                $('form#countAndUpdatePrices').submit();
            }
        })
  });
</script>
@endsection
