@if ($paginator->hasPages())
    <nav class="d-flex align-items-center justify-content-between" aria-label="Paginacion">
        <div class="d-flex d-sm-none align-items-center justify-content-between gap-2 w-100">
            <ul class="pagination mb-0">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link" aria-hidden="true"><i class="bi bi-chevron-left"></i></span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                    </li>
                @endif
            </ul>

            <div class="small text-muted text-center" aria-live="polite">
                Pagina <strong>{{ $paginator->currentPage() }}</strong>
                @if(method_exists($paginator, 'lastPage'))
                    de <strong>{{ $paginator->lastPage() }}</strong>
                @endif
            </div>

            <ul class="pagination mb-0">
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                    </li>
                @endif
            </ul>
        </div>

        <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between">
            <div class="small text-muted">
                Mostrando <span class="fw-semibold">{{ $paginator->firstItem() }}</span>
                a <span class="fw-semibold">{{ $paginator->lastItem() }}</span>
                de <span class="fw-semibold">{{ $paginator->total() }}</span> resultados
            </div>

            <div>
                <ul class="pagination mb-0">
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                            <span class="page-link" aria-hidden="true">&lsaquo;</span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">&lsaquo;</a>
                        </li>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                                @else
                                    <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">&rsaquo;</a>
                        </li>
                    @else
                        <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                            <span class="page-link" aria-hidden="true">&rsaquo;</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
@endif
