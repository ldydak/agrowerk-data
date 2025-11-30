@extends('layouts.app')

@section('header')
<h2 class="h3 my-3"><strong>Wgrywanie</strong> zdjęć produktów / wariantów oraz ich kompresja</h2>
@endsection
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            <div class="card-body">
                <form action="{{ route('images.import.import') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-12 col-md mb-3">
                            <label class="form-label" for="file">Wybierz plik .csv</label>
                            <input type="file" id="file" name="file">
                            @error('file')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror
                            <p class="text-muted mt-2 mb-0">Przykładowy plik .csv poprawnego importu zdjęć produktów <a href="/import-examples/import-zdjec-produktow-example.csv">Pobierz</a></p>
                            <p class="text-muted mt-2 mb-0">Przykładowy plik .csv poprawnego importu zdjęć wariantów <a href="/import-examples/import-zdjec-wariantow-example.csv">Pobierz</a></p>

                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-12 col-md mb-3">
                            <label class="form-check">
                                <input class="form-check-input" type="radio" value="skipExisted" name="imagesImportType" checked required>
                                <span class="form-check-label">
                                  Pomiń zdjęcia dla produktów, które juz posiadaja zdjecia o tej nazwie (nie aktualizuj zdjec), dodaj tylko nowe ktorych nie ma w bazie.
                                </span>
                              </label>

                              <label class="form-check">
                                <input class="form-check-input" type="radio" value="updateAll" name="imagesImportType" required>
                                <span class="form-check-label">
                                  Aktulizuj wszystkie zdjecia z pliku, zaktualizuj obecne o tej samej nazwie i dodaj nowe ktorych nie ma w bazie. Nie usuwam zbędnych plików z ftp.
                                </span>
                              </label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <label class="form-check">
                                <input class="form-check-input" type="radio" value="products" name="productsOrVariants" checked required>
                                <span class="form-check-label">
                                    Importuj zdjęcia dla produktów
                                </span>
                              </label>

                              <label class="form-check">
                                <input class="form-check-input" type="radio" value="variants" name="productsOrVariants" required>
                                <span class="form-check-label">
                                    Importuj zdjęcia dla wariantów
                                </span>
                              </label>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">Rozpocznij wgrywanie i kompresję</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
