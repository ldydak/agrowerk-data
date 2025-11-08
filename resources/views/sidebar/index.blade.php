@auth
<nav id="sidebar" class="sidebar js-sidebar">
    <div class="sidebar-content js-simplebar">
        <a class="sidebar-brand" href="/">
            <span class="align-middle">DATA Schomann.pl</span>
        </a>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a class="sidebar-link" href="{{route('products.import.show')}}">
                    <span class="align-middle">Import produktów</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link" href="{{route('images.import.show')}}">
                    <span class="align-middle">Import zdjęć</span>
                </a>
            </li>
        </ul>

    </div>
</nav>
@section('scripts')
<script>
    $(document).ready(function() {
        var url = window.location;
        $('a.sidebar-link').filter(function () {
            return this.href == url;
        }).parent().addClass('active');
    });
</script>
@endsection
@endauth