<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AdminOperatorAlertService;

/** Poll thông báo chờ duyệt / sự kiện vận hành cho admin console. */
class AdminAlertController extends Controller
{
    public function __construct(
        private readonly AdminOperatorAlertService $alerts,
    ) {
    }

    public function poll()
    {
        return response()->json([
            'alerts' => $this->alerts->pullAlerts(),
        ]);
    }
}
