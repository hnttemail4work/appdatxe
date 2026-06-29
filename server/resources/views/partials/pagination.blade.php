@if(isset($paginator) && method_exists($paginator, 'hasPages') && $paginator->hasPages())
<nav class="app-pagination mt-3" aria-label="Phân trang">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        @if($paginator->onFirstPage())
            <li class="page-item disabled" aria-disabled="true">
                <span class="page-link">Trước</span>
            </li>
        @else
            <li class="page-item">
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Trước</a>
            </li>
        @endif

        @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
            <li class="page-item {{ $page === $paginator->currentPage() ? 'active' : '' }}" @if($page === $paginator->currentPage()) aria-current="page" @endif>
                @if($page === $paginator->currentPage())
                    <span class="page-link">{{ $page }}</span>
                @else
                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                @endif
            </li>
        @endforeach

        @if($paginator->hasMorePages())
            <li class="page-item">
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Sau</a>
            </li>
        @else
            <li class="page-item disabled" aria-disabled="true">
                <span class="page-link">Sau</span>
            </li>
        @endif
    </ul>
    <p class="app-pagination-meta text-center text-muted small mb-0 mt-2">
        Trang {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}
        — {{ number_format($paginator->total(), 0, ',', '.') }} mục
    </p>
</nav>
@endif
