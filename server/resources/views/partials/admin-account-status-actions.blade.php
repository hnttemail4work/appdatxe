@php
    /**
     * Toggle tài khoản: Đang hoạt động ↔ Tạm ngưng.
     *
     * @var string $suspendAction  URL POST tạm ngưng
     * @var string $resumeAction   URL POST mở lại
     * @var bool   $isRunning
     * @var string $entityLabel    "tài xế" | "khách"
     * @var string|null $suspendMethod  DELETE nếu route destroy
     * @var string $layout         block|inline
     */
    $entityLabel = $entityLabel ?? 'tài khoản';
    $layout = $layout ?? 'block';
    $suspendMethod = $suspendMethod ?? null;
    $isRunning = (bool) ($isRunning ?? false);
    $wrapClass = $layout === 'inline' ? 'd-inline' : 'mt-3';
@endphp
@if($isRunning)
    <form method="POST" action="{{ $suspendAction }}" class="{{ $wrapClass }}"
          data-confirm="Tạm ngưng {{ $entityLabel }} này? Họ sẽ không đăng nhập / hoạt động được."
          data-confirm-title="Tạm ngưng"
          data-confirm-variant="danger"
          data-confirm-ok="Ngưng">
        @csrf
        @if($suspendMethod)
            @method($suspendMethod)
        @endif
        <button type="submit" class="btn {{ $layout === 'inline' ? 'btn-sm btn-outline-danger' : 'btn-outline-danger' }}">
            Ngưng
        </button>
    </form>
@else
    <form method="POST" action="{{ $resumeAction }}" class="{{ $wrapClass }}"
          data-confirm="Mở lại {{ $entityLabel }} này?"
          data-confirm-title="Mở hoạt động"
          data-confirm-ok="Mở">
        @csrf
        <button type="submit" class="btn {{ $layout === 'inline' ? 'btn-sm btn-outline-success' : 'btn-outline-primary' }}">
            Mở
        </button>
    </form>
@endif
