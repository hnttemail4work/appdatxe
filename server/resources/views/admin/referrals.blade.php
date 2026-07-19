@extends('layouts.console')

@section('console')
@php
    $qrTab = $qrTab ?? request('tab', 'codes');
    if (! in_array($qrTab, ['codes', 'rules', 'user-auth', 'driver-auth'], true)) {
        $qrTab = 'codes';
    }
@endphp
@include('partials.console-hero', [
    'title' => 'QR',
])

@include('partials.admin-nav-tabs', ['active' => 'qr'])

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="console-panel">
            <div class="console-panel-body">
                @include('partials.screen-tabs-start', [
                    'prefix' => 'admin-qr',
                    'activeKey' => $qrTab,
                    'tabs' => [
                        ['key' => 'codes', 'label' => 'Mã giảm giá'],
                        ['key' => 'rules', 'label' => 'Rule giảm giá'],
                        ['key' => 'user-auth', 'label' => 'Khách hàng'],
                        ['key' => 'driver-auth', 'label' => 'Tài xế'],
                    ],
                ])

                @include('partials.screen-tab-pane', ['prefix' => 'admin-qr', 'key' => 'codes', 'active' => $qrTab === 'codes'])
                @include('partials.admin-referrals-panel')
                @include('partials.admin-qr-driver-invite-section')
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-qr', 'key' => 'rules', 'active' => $qrTab === 'rules'])
                @include('partials.admin-qr-discount-rules-panel')
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-qr', 'key' => 'user-auth', 'active' => $qrTab === 'user-auth'])
                @include('partials.admin-qr-auth-panel', ['audience' => 'user'])
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tab-pane', ['prefix' => 'admin-qr', 'key' => 'driver-auth', 'active' => $qrTab === 'driver-auth'])
                @include('partials.admin-qr-auth-panel', ['audience' => 'driver'])
                @include('partials.screen-tab-pane-end')

                @include('partials.screen-tabs-end')

                @include('partials.referral-qr-modal')
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.driver-invite-admin__qr {
    padding: .55rem;
    border-radius: .65rem;
    background: #fff;
    display: inline-block;
    line-height: 0;
}
.driver-invite-admin__qr img {
    display: block;
    border-radius: .3rem;
}
.driver-qr-admin__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(16.5rem, 1fr));
    gap: 1rem;
}
.driver-qr-card {
    border: 1px solid rgba(255, 255, 255, .1);
    border-radius: .85rem;
    background: rgba(255, 255, 255, .03);
    padding: 1rem 1.05rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: .85rem;
    min-height: 100%;
}
.driver-qr-card--muted {
    opacity: .92;
}
.driver-qr-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .75rem;
}
.driver-qr-card__title {
    margin: 0;
    font-size: .95rem;
    font-weight: 700;
    color: #fff;
}
.driver-qr-card__body {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-start;
}
.driver-qr-card__form .form-label {
    font-size: .78rem;
    margin-bottom: .35rem;
}
</style>
@endpush

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
