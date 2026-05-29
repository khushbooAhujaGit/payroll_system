@if ($paginator->hasPages())
<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:12px;margin-top:4px">

  {{-- Previous --}}
  @if ($paginator->onFirstPage())
    <span class="btn sm" style="opacity:.4;cursor:default;padding:4px 9px">&lsaquo;</span>
  @else
    <a class="btn sm" style="padding:4px 9px" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo;</a>
  @endif

  {{-- Page numbers --}}
  @foreach ($elements as $element)
    @if (is_string($element))
      <span style="color:var(--t3);padding:0 2px">…</span>
    @endif

    @if (is_array($element))
      @foreach ($element as $page => $url)
        @if ($page == $paginator->currentPage())
          <span class="btn sm" style="background:var(--acc);color:#fff;border-color:var(--acc);padding:4px 9px;min-width:30px;text-align:center;cursor:default">{{ $page }}</span>
        @else
          <a class="btn sm" style="padding:4px 9px;min-width:30px;text-align:center" href="{{ $url }}">{{ $page }}</a>
        @endif
      @endforeach
    @endif
  @endforeach

  {{-- Next --}}
  @if ($paginator->hasMorePages())
    <a class="btn sm" style="padding:4px 9px" href="{{ $paginator->nextPageUrl() }}" rel="next">&rsaquo;</a>
  @else
    <span class="btn sm" style="opacity:.4;cursor:default;padding:4px 9px">&rsaquo;</span>
  @endif

  <span style="color:var(--t3);margin-left:6px">
    {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}
    of {{ number_format($paginator->total()) }}
  </span>

</div>
@endif
