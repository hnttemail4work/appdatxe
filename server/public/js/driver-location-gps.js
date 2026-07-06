/**
 * Chia sẻ vị trí GPS tài xế — luồng chính (bản đồ chỉ dùng khi test local).
 */
(function () {
    var reverseUrl = window.__geocodeReverseUrl || '';
    var shareBtn = document.getElementById('driver-location-share-btn');
    var latInput = document.getElementById('driver-location-lat');
    var lngInput = document.getElementById('driver-location-lng');
    var detailInput = document.getElementById('driver-location-detail');
    var locationBar = document.getElementById('driver-location-bar');
    var sharing = false;

    function isPaused() {
        return locationBar && locationBar.getAttribute('data-driver-paused') === '1';
    }

    function setLatLng(lat, lng) {
        if (latInput) {
            latInput.value = String(lat);
        }
        if (lngInput) {
            lngInput.value = String(lng);
        }
    }

    function reverseGeocode(lat, lng) {
        if (!reverseUrl) {
            return Promise.resolve('');
        }
        return fetch(reverseUrl + '?lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng), {
            headers: { Accept: 'application/json' },
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) {
                    return '';
                }
                return String(data.display_name || data.label || data.address || '').trim();
            })
            .catch(function () { return ''; });
    }

    function dispatchApplied(lat, lng, address) {
        document.dispatchEvent(new CustomEvent('addressmap:applied', {
            detail: {
                targetInputId: detailInput ? detailInput.id : 'driver-location-detail',
                lat: lat,
                lng: lng,
                address: address,
            },
        }));
    }

    function shareCurrentLocation(options) {
        options = options || {};

        if (sharing || isPaused()) {
            return Promise.resolve(false);
        }

        if (!navigator.geolocation) {
            if (window.DriverLocationSave && window.DriverLocationSave.setMetaLine) {
                window.DriverLocationSave.setMetaLine('Trình duyệt không hỗ trợ GPS.');
            }
            return Promise.resolve(false);
        }

        sharing = true;
        if (shareBtn) {
            shareBtn.disabled = true;
        }
        if (window.DriverLocationSave && window.DriverLocationSave.setMetaLine) {
            window.DriverLocationSave.setMetaLine('Đang lấy vị trí GPS…');
        }

        return new Promise(function (resolve) {
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    var lat = pos.coords.latitude;
                    var lng = pos.coords.longitude;
                    setLatLng(lat, lng);
                    reverseGeocode(lat, lng).then(function (address) {
                        if (detailInput && address) {
                            detailInput.value = address;
                        }
                        dispatchApplied(lat, lng, address);
                        resolve(true);
                    }).finally(function () {
                        sharing = false;
                        if (shareBtn && !isPaused()) {
                            shareBtn.disabled = false;
                        }
                    });
                },
                function () {
                    if (window.DriverLocationSave && window.DriverLocationSave.setMetaLine) {
                        window.DriverLocationSave.setMetaLine('Không lấy được GPS — bật quyền vị trí và thử lại.');
                    }
                    sharing = false;
                    if (shareBtn && !isPaused()) {
                        shareBtn.disabled = false;
                    }
                    resolve(false);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: options.maximumAge != null ? options.maximumAge : 30000,
                }
            );
        });
    }

    if (shareBtn) {
        shareBtn.addEventListener('click', function () {
            shareCurrentLocation({ maximumAge: 0 });
        });
    }

    window.DriverLocationGps = {
        shareCurrentLocation: shareCurrentLocation,
    };
})();
