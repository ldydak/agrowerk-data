@extends('layouts.app')

@section('header')
<h2 class="h3 my-3"><strong>Ustawienia</strong> bloga</h2>
@endsection

@section('content')
@php
    $oldCategoryIds = collect(old('category_ids', []))->map(fn ($id) => (int) $id)->all();
    $selectSize = min(14, max(6, $categoryOptions->count()));
@endphp

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Dodaj powiązania bloga z kategoriami produktów</h5>
            </div>
            <div class="card-body">
                @if($blogPosts->isEmpty())
                    <div class="alert alert-warning mb-0">Brak wpisów blogowych w bazie sklepu.</div>
                @elseif($categoryOptions->isEmpty())
                    <div class="alert alert-warning mb-0">Brak kategorii produktów w bazie sklepu.</div>
                @else
                    <form action="{{ route('settings.blog.store') }}" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-12 col-lg-4 mb-3">
                                <label class="form-label" for="blog_post_id">Wpis blogowy</label>
                                <select name="blog_post_id" id="blog_post_id" class="form-select" required>
                                    <option value="">Wybierz wpis</option>
                                    @foreach($blogPosts as $post)
                                        <option value="{{ $post['id'] }}" {{ (int) old('blog_post_id') === $post['id'] ? 'selected' : '' }}>
                                            {{ $post['title'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('blog_post_id')
                                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-lg-8 mb-3">
                                <label class="form-label" for="category_ids">Kategorie produktów</label>
                                <select name="category_ids[]" id="category_ids" class="form-select" multiple size="{{ $selectSize }}" required>
                                    @foreach($categoryOptions as $category)
                                        <option value="{{ $category['id'] }}" {{ in_array($category['id'], $oldCategoryIds, true) ? 'selected' : '' }}>
                                            {{ $category['path'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-muted mt-2 mb-0">Możesz wybrać kategorie główne, dzieci i dowolny głębszy poziom drzewa.</p>
                                @error('category_ids')
                                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                                @enderror
                                @error('category_ids.*')
                                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary">Dodaj powiązania</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Aktualne powiązania</h5>
            </div>
            <div class="card-body">
                @if($assignments->isEmpty())
                    <p class="text-muted mb-0">Brak zapisanych powiązań w tabeli blog_product_categories.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Wpis blogowy</th>
                                    <th>Kategorie</th>
                                    <th>Edycja</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($assignments as $assignment)
                                    <tr>
                                        <td style="width: 28%;">
                                            <div class="fw-semibold">{{ $assignment['post']['title'] }}</div>
                                            <div class="text-muted small">
                                                ID: {{ $assignment['blog_post_id'] }}
                                                @if(!empty($assignment['post']['slug']))
                                                    <br>Slug: {{ $assignment['post']['slug'] }}
                                                @endif
                                                @if(!empty($assignment['post']['publish_status']))
                                                    <br>Status: {{ $assignment['post']['publish_status'] }}
                                                @endif
                                            </div>
                                        </td>
                                        <td style="width: 34%;">
                                            @foreach($assignment['categories'] as $category)
                                                <form action="{{ route('settings.blog.destroy-category', [$assignment['blog_post_id'], $category['id']]) }}"
                                                    method="post"
                                                    class="d-inline js-confirm-delete"
                                                    data-confirm-title="Usunąć kategorię?"
                                                    data-confirm-text="Usuniesz tylko tę jedną kategorię z powiązań wpisu blogowego.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary mb-1 me-1">
                                                        {{ $category['path'] }} <span class="text-danger ms-1">&times;</span>
                                                    </button>
                                                </form>
                                            @endforeach
                                        </td>
                                        <td>
                                            <form action="{{ route('settings.blog.update', $assignment['blog_post_id']) }}" method="post" class="mb-2">
                                                @csrf
                                                @method('PUT')
                                                <select name="category_ids[]" class="form-select mb-2" multiple size="{{ $selectSize }}">
                                                    @foreach($categoryOptions as $category)
                                                        <option value="{{ $category['id'] }}" {{ in_array($category['id'], $assignment['category_ids'], true) ? 'selected' : '' }}>
                                                            {{ $category['path'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <div class="d-flex justify-content-end">
                                                    <button type="submit" class="btn btn-primary btn-sm">Zapisz kategorie</button>
                                                </div>
                                            </form>
                                            <form action="{{ route('settings.blog.destroy', $assignment['blog_post_id']) }}"
                                                method="post"
                                                class="d-flex justify-content-end js-confirm-delete"
                                                data-confirm-title="Usunąć wszystkie powiązania?"
                                                data-confirm-text="Usuniesz cały zestaw kategorii przypisany do tego wpisu blogowego.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Usuń całość</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
<script>
    $('.js-confirm-delete').on('submit', function(e) {
        e.preventDefault();

        const form = this;
        Swal.fire({
            title: $(form).data('confirm-title'),
            text: $(form).data('confirm-text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Tak, usuń',
            cancelButtonText: 'Anuluj',
            showCloseButton: true,
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
</script>
@endsection
