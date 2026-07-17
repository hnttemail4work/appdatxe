<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AdminRevenueService;
use App\Services\ScheduleLifecycleService;
use Illuminate\Http\Request;

/**
 * Nhóm "báo cáo doanh thu" — tách ra từ AdminController (Fat Controller).
 */
class AdminRevenueController extends Controller
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly AdminRevenueService $revenue,
    ) {
    }

    public function revenueReport(Request $request)
    {
        $this->scheduleLifecycle->sync();

        return view('admin.revenue', [
            'summary'         => $this->revenue->completedTripsSummary(),
            'referrerRows'    => $this->revenue->referrerSummaryRows(),
            'completedTrips'  => $this->revenue->paginatedCompletedTrips($request),
        ]);
    }
}
