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
                            <p class="text-muted mt-2 mb-0">Przykładowy plik .csv poprawnego importu <a href="/import-examples/import-produktow-example.csv">Pobierz</a></p>
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
@endsection
