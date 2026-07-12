<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CustomerAccountService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerAccountService $accounts,
    ) {
    }

    public function account(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab', 'profile');

        if (! in_array($tab, ['profile', 'trips', 'reviews'], true)) {
            $tab = 'profile';
        }

        $this->accounts->linkExistingBookings($user);

        return view('customer.account', [
            'user'         => $user,
            'profile'      => $this->accounts->profileSummary($user),
            'activeTab'    => $tab,
            'tripHistory'  => $tab === 'trips'
                ? $this->accounts->tripHistory($user, (int) $request->query('page', 1))
                    ->through(fn ($booking) => $this->accounts->serializeTrip($booking))
                : null,
            'reviews'      => $tab === 'reviews'
                ? $this->accounts->reviews($user, (int) $request->query('page', 1))
                : null,
            'recentTrips'  => $tab === 'profile'
                ? $this->accounts->recentTrips($user, 5)->map(fn ($booking) => $this->accounts->serializeTrip($booking))
                : collect(),
        ]);
    }
}
