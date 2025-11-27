@extends('layouts.app')

@section('header')
<h1 class="h3 mb-3"><strong>Wgrywanie</strong> zdjęć produktów Hygi oraz ich kompresja</h1>
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
                            <p class="text-muted mt-2 mb-0">Przykładowy plik .csv poprawnego importu <a href="/import-examples/import-zdjec-produktow-example.csv">Pobierz</a></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
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
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">Rozpocznij wgrywanie i kompresję</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
