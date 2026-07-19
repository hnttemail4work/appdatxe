@php
    $hasLiveTrips = ($pendingTripRequestGroups ?? collect())->isNotEmpty()
        || ($tripSchedules ?? collect())->isNotEmpty();
    $sheetInitiallyOpen = $sheetInitiallyOpen ?? $hasLiveTrips;
    $panelMode = $sheetInitiallyOpen ? 'trip' : 'idle';
@endphp
<section class="driver-bottom-panel is-{{ $panelMode }} {{ $sheetInitiallyOpen ? 'is-expanded' : '' }}"
         id="driver-bottom-panel"
         data-driver-bottom-panel
         data-has-live-trips="{{ $hasLiveTrips ? '1' : '0' }}"
         aria-label="Thanh điều khiển chuyến">
    <div class="driver-bottom-panel__idle" id="driver-bottom-idle" @if($sheetInitiallyOpen) hidden @endif>
        <input type="checkbox"
               class="driver-activity-toggle-input driver-ready-cta__input"
               id="driver-availability-input"
               @checked(! $driverPaused)
               aria-hidden="true"
               tabindex="-1">
        <button type="button"
                class="driver-ready-cta {{ $driverPaused ? 'is-off' : 'is-on' }}"
                id="driver-ready-cta"
                aria-pressed="{{ $driverPaused ? 'false' : 'true' }}">
            <span class="driver-ready-cta__icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v10"/>
                    <path d="M5.5 7.5a8 8 0 1 0 13 0"/>
                </svg>
            </span>
            <span class="driver-ready-cta__label" data-ready-label>
                {{ $driverPaused ? 'BẬT SẴN SÀNG' : 'TẮT SẴN SÀNG' }}
            </span>
        </button>
    </div>

    <div class="driver-bottom-panel__trip driver-trip-sheet {{ $sheetInitiallyOpen ? 'is-open is-visible' : '' }}"
         id="driver-trip-sheet"
         data-driver-sheet
         @if(! $sheetInitiallyOpen) hidden @endif>
        <div class="driver-trip-sheet__handle" aria-hidden="true"></div>

        <div class="driver-trip-sheet__live" id="driver-trip-live" @if(! $hasLiveTrips) hidden @endif>
            @if(($pendingTripRequestGroups ?? collect())->isNotEmpty())
                <div class="driver-trip-sheet__block" id="driver-trip-requests-list">
                    @foreach($pendingTripRequestGroups as $group)
                        @include('partials.driver-trip-request-card', [
                            'tripRequest' => $group['primary'],
                            'schedule' => $group['schedule'],
                            'passengers' => $group['passengers'],
                        ])
                    @endforeach
                </div>
            @endif

            @if(($tripSchedules ?? collect())->isNotEmpty())
                <div class="driver-trip-sheet__block" id="driver-trips-list">
                    @foreach($tripSchedules as $schedule)
                        @include('partials.driver-schedule-card', [
                            'schedule' => $schedule,
                            'showActions' => true,
                        ])
                    @endforeach
                </div>
                @include('partials.pagination', ['paginator' => $tripSchedules])
            @endif
        </div>
    </div>
</section>
