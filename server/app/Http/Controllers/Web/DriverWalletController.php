<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\DriverWalletDepositRequest;
use App\Models\DriverProfile;
use App\Services\DriverWalletService;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class DriverWalletController extends Controller
{
    public function __construct(private readonly DriverWalletService $wallets)
    {
    }

    public function deposit(DriverWalletDepositRequest $request)
    {
        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();

        try {
            $this->wallets->requestDeposit(
                $profile,
                (int) $request->validated()['amount'],
                $request->file('proof_image'),
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('driver.dashboard', ['tab' => 'wallet'])
                ->withErrors(['wallet' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('driver.dashboard', ['tab' => 'wallet']);
    }
}
