@extends('layouts.console')

@section('console')
@include('partials.console-hero', [
    'title' => 'Giới thiệu',
])

@include('partials.admin-nav-tabs', ['active' => 'referrals'])

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="console-panel">
            <div class="console-panel-body">
                @include('partials.admin-referrals-panel')
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('referrer-create-form');
    if (!form || form.dataset.csrfRefreshBound === '1') {
        return;
    }
    form.dataset.csrfRefreshBound = '1';

    function syncCsrfToken(token) {
        if (!token) {
            return;
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute('content', token);
        }
        form.querySelectorAll('input[name="_token"]').forEach(function (input) {
            input.value = token;
        });
    }

    form.addEventListener('submit', function (event) {
        if (form.dataset.csrfRefreshSubmitting === '1') {
            form.dataset.csrfRefreshSubmitting = '';
            return;
        }

        event.preventDefault();

        fetch('/csrf-token', { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                syncCsrfToken(data.token);
                form.dataset.csrfRefreshSubmitting = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            })
            .catch(function () {
                form.dataset.csrfRefreshSubmitting = '1';
                form.submit();
            });
    });
})();
</script>
<script src="{{ asset('js/referral-qr.js') }}"></script>
@endpush
