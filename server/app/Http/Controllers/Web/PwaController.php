<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use App\Support\AppBrandingSettings;
use App\Support\PushAudience;
use App\Support\PushNotificationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PwaController extends Controller
{
    public function __construct(
        private readonly PushNotificationService $push,
    ) {
    }

    public function manifest(Request $request): JsonResponse
    {
        if (! PushAudience::enabledFor($request->user())) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $audience = PushAudience::resolve($request->user());
        $appName = AppBrandingSettings::appName();
        $startUrl = PushAudience::startUrl($audience);

        return response()->json([
            'name'             => PushAudience::manifestName($audience, $appName),
            'short_name'       => PushAudience::shortLabel($audience),
            'description'      => AppBrandingSettings::brandTagline(),
            'start_url'        => $startUrl,
            'scope'            => '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#0f1419',
            'theme_color'      => '#0f1419',
            'lang'             => 'vi',
            'icons'            => AppBrandingSettings::manifestIcons(),
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function vapidPublicKey(): JsonResponse
    {
        if (! PushAudience::enabledFor(auth()->user())) {
            return response()->json(['enabled' => false, 'public_key' => null, 'audience' => null]);
        }

        $keys = PushNotificationSettings::vapidKeys();

        return response()->json([
            'enabled'    => PushNotificationSettings::isEnabled() && $keys !== null,
            'public_key' => $keys['public'] ?? null,
            'audience'   => PushAudience::resolve(auth()->user()),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        if (! PushAudience::enabledFor($request->user())) {
            return response()->json(['message' => 'Không hỗ trợ trên tài khoản này.'], 403);
        }

        if (! PushNotificationSettings::isEnabled()) {
            return response()->json(['message' => 'Thông báo đẩy đang tắt.'], 422);
        }

        if (! PushNotificationSettings::vapidKeys()) {
            return response()->json(['message' => 'Chưa cấu hình khóa thông báo.'], 422);
        }

        try {
            $subscription = $this->push->subscribe($request, $request->all());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'       => true,
            'audience' => $subscription->audience,
        ]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = trim((string) $request->input('endpoint', ''));
        if ($endpoint !== '') {
            $this->push->unsubscribe($endpoint);
        }

        return response()->json(['ok' => true]);
    }

    public function touchContact(Request $request): JsonResponse
    {
        if (! PushAudience::enabledFor($request->user())) {
            return response()->json(['ok' => true]);
        }

        $browserId = trim((string) $request->input('browser_id', ''));
        $phone = trim((string) $request->input('contact_phone', ''));

        if ($browserId !== '' && $phone !== '') {
            $this->push->touchContactPhone($browserId, $phone);
        }

        return response()->json(['ok' => true]);
    }
}
