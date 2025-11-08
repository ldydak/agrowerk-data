@extends('layouts.app')

@section('header')
<h1 class="h3 my-3"><strong>Import</strong> produktów i kategorii</h1>
@endsection
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            <div class="card-body">
                <form action="{{ route('products.import.import') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-12 col-md mb-3">
                            <p class="text-muted">Działanie importera:</p>
                            <ul>
                                <li>...</li>
                            </ul>
                            <label class="form-label" for="file">Wybierz plik .csv</label>
                            <input type="file" id="file" name="file">
                            @error('file')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
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

@endsection
