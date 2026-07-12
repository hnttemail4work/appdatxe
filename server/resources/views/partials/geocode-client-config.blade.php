window.__geocodeReverseUrl = @json(route('geocode.reverse'));
window.__geocodeSearchUrl = @json(route('geocode.search'));
window.__geocodeResolveUrl = @json(route('geocode.resolve'));
window.__provinceCenters = @json(\App\Support\ProvinceCenters::centersForCatalog());
window.__goongMaptilesKey = @json(config('services.goong.maptiles_key') ?: '');
