<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\CustomerProfileChangeRequest;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\DriverProfile;
use App\Models\DriverProfileChangeRequest;
use App\Models\DriverWallet;
use App\Models\DriverWalletTransaction;
use App\Models\User;
use App\Services\AdminOperatorAlertService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminOperatorAlertServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_record_driver_accepted_does_not_queue_admin_alert(): void
    {
        $booking = new Booking([
            'id'                => 42,
            'passenger_name'    => 'Khách Test',
            'booking_reference' => 'BK-001',
        ]);

        $service = app(AdminOperatorAlertService::class);
        $service->recordDriverAccepted($booking);

        $this->assertSame([], $service->pullAlerts());
    }

    public function test_record_pending_approvals_queue_admin_alerts(): void
    {
        $service = app(AdminOperatorAlertService::class);

        $customer = new User([
            'id'    => 11,
            'phone' => '0901111222',
            'name'  => '0901111222',
            'role'  => 'customer',
        ]);
        $customer->id = 11;
        $service->recordCustomerRegistrationPending($customer);

        $driverUser = new User([
            'name'  => 'TX Test',
            'phone' => '0903333444',
        ]);
        $profile = new DriverProfile(['id' => 7]);
        $profile->id = 7;
        $profile->setRelation('user', $driverUser);
        $service->recordDriverRegistrationPending($profile);

        $changeUser = new User([
            'id'    => 12,
            'phone' => '0905555666',
            'name'  => 'Nguyễn B',
            'role'  => 'customer',
        ]);
        $changeUser->id = 12;
        $customerChange = new CustomerProfileChangeRequest(['id' => 3, 'user_id' => 12]);
        $customerChange->id = 3;
        $customerChange->setRelation('user', $changeUser);
        $service->recordCustomerProfileChangePending($customerChange);

        $driverChange = new DriverProfileChangeRequest([
            'id'                => 4,
            'driver_profile_id' => 7,
        ]);
        $driverChange->id = 4;
        $driverChange->setRelation('profile', $profile);
        $service->recordDriverProfileChangePending($driverChange);

        $driverWallet = new DriverWallet(['id' => 1]);
        $driverWallet->setRelation('driverProfile', $profile);
        $driverDeposit = new DriverWalletTransaction([
            'id'     => 21,
            'amount' => 200000,
        ]);
        $driverDeposit->id = 21;
        $driverDeposit->setRelation('wallet', $driverWallet);
        $service->recordDriverDepositPending($driverDeposit);

        $customerWallet = new CustomerWallet(['id' => 2]);
        $customerWallet->setRelation('user', $changeUser);
        $customerDeposit = new CustomerWalletTransaction([
            'id'     => 22,
            'amount' => 100000,
        ]);
        $customerDeposit->id = 22;
        $customerDeposit->setRelation('wallet', $customerWallet);
        $service->recordCustomerDepositPending($customerDeposit);

        $alerts = $service->pullAlerts();
        $types = array_column($alerts, 'type');

        $this->assertContains('customer_registration_pending', $types);
        $this->assertContains('driver_registration_pending', $types);
        $this->assertContains('customer_profile_change_pending', $types);
        $this->assertContains('driver_profile_change_pending', $types);
        $this->assertContains('driver_deposit_pending', $types);
        $this->assertContains('customer_deposit_pending', $types);
        $this->assertCount(6, $alerts);
    }
}
