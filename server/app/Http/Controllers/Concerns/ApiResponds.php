<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Chuẩn hóa response cho các action vừa phục vụ Blade (redirect + flash)
 * vừa phục vụ JS gọi fetch() (JSON). Dùng cho controller Web hybrid —
 * không ép về BaseApiController vì phần lớn action ở đây render cả view.
 */
trait ApiResponds
{
    /**
     * Lỗi nghiệp vụ (ví dụ InvalidArgumentException): JSON 422 nếu request
     * cần JSON, ngược lại back() kèm errors theo field truyền vào.
     */
    protected function errorResponse(Request $request, string $message, string $errorField): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors([$errorField => $message]);
    }
}
