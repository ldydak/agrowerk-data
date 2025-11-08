@foreach (['success', 'danger', 'warning', 'info'] as $msg)
    @if(Session::has($msg))
    <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12 mt-4 mb-4">
            <div class="alert alert-{{ $msg }}" role="alert">
                {!! Session::get($msg) !!}
            </div>
    </div>
    @endif
@endforeach
