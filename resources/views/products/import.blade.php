@extends('layouts.app')


@section('content')
<h2 class="h3 my-3"><strong>Import</strong> produktów i kategorii</h2>
<div class="row">
    <div class="col-12">
        <div class="card">

            <div class="card-body">
                <form action="{{ route('products.import.import') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-12 col-md mb-3">
                            <label class="form-label" for="file">Wybierz plik .csv</label>
                            <input type="file" id="file" name="file">
                            @error('file')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror
                            <p class="text-muted mt-2 mb-0">Przykładowy plik .csv poprawnego importu produktów <a href="/import-examples/import-produktow-example.csv">Pobierz</a></p>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-2">
                        @if(Auth::user()->id == 1)
                            {{-- <a href="{{ route('hygi.delete') }}" class="btn btn-danger me-3">Wyczysc baze</a> --}}
                        @endif
                        <button type="submit" class="btn btn-primary">Importuj produkty</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<h2 class="h3 my-3"><strong>Import</strong> wariantów do produktów</h2>

<div class="row">
    <div class="col-12">
        <div class="card">

            <div class="card-body">
                <form action="{{ route('products.import.wariantsImport') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-12 col-md mb-3">
                            <label class="form-label" for="file">Wybierz plik .csv</label>
                            <input type="file" id="file" name="file">
                            @error('file')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror
                            <p class="text-muted mt-2 mb-0">Przykładowy plik .csv poprawnego importu wariantów <a href="/import-examples/import-wariantow-example.csv">Pobierz</a></p>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-2">
                        <button type="submit" class="btn btn-primary">Importuj warianty</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<h2 class="h3 my-3"><strong>Aktualizacja</strong> oryginalnych cen produktów</h2>

<div class="row">
    <div class="col-12">
        <div class="card">

            <div class="card-body">
                <form action="{{ route('products.import.newPricesImport') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-12 col-md mb-3">
                            <label class="form-label" for="file">Wybierz plik .csv</label>
                            <input type="file" id="file" name="file">
                            @error('file')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror
                            <p class="text-muted mt-2 mb-0">Plik wraz z SQL i skryptem Python do aktualizacji, znajdują się w repozytorium w folderze /python/</p>

                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-2">
                        <button type="submit" class="btn btn-primary">Importuj ceny</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<h2 class="h3 my-3"><strong>Import FAQ</strong> do produktów (rodziców, głównych)</h2>

<div class="row">
    <div class="col-12">
        <div class="card">

            <div class="card-body">
                <form action="{{ route('products.import.productFaqImport') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-12 col-md mb-3">
                            <label class="form-label" for="file">Wybierz plik .csv</label>
                            <input type="file" id="file" name="file">
                            @error('file')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror
    
                            <p class="text-muted mt-2 mb-0">Przykładowy plik .csv poprawnego importu opisów FAQ dla głównych produktów <a href="/import-examples/import-product-faq-example.csv">Pobierz</a></p>

                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-2">
                        <button type="submit" class="btn btn-primary">Importuj opisy faq</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<h2 class="h3 my-3"><strong>Tworzenie</strong> upsell produktów</h2>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('products.import.generateRelatedProducts') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-check">
                                <input class="form-check-input" type="radio" value="pokrewne" name="relatedProductsType" checked required>
                                    <span class="form-check-label">
                                    Produkty pokrewne
                                    </span>
                              </label>

                              {{-- <label class="form-check">
                                <input class="form-check-input" type="radio" value="uzupelniajace" name="relatedProductsType" required>
                                <span class="form-check-label">
                                  Produkty uzupełniające
                                </span>
                              </label>

                              <label class="form-check">
                                <input class="form-check-input" type="radio" value="powiazane" name="relatedProductsType" required>
                                <span class="form-check-label">
                                  Produkty powiązane
                                </span>
                              </label> --}}
                        </div>

                    </div>
                    <div class="d-flex justify-content-end mt-2">
                        <button type="submit" class="btn btn-primary">Wykonaj akcję</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<h2 class="h3 my-3">Generuj <strong>Google Merchant</strong> product feed</h2>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                    <p class="text-muted mt-2 mb-0">Dostępny pod adresem <a href="https://data.sanipro.pl/google-merchant_feed.xml" target="_blank">https://data.sanipro.pl/google-merchant_feed.xml</a><br>
                    Funkcja generuje feed tylko dla produktów głównych (bez wariantów)</p>

                    <div class="d-flex justify-content-end mt-2">
                        <a href="{{route('settings.generate-google-merchant-feed')}}" class="btn btn-primary">Generuj</a>
                    </div>
            </div>
        </div>
    </div>
</div>
@endsection
