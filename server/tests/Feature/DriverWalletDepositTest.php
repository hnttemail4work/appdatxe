<?php



namespace Tests\Feature;



use App\Models\DriverProfile;

use App\Models\DriverWallet;

use App\Models\DriverWalletTransaction;

use App\Models\User;

use App\Support\DriverWalletConfig;

use Illuminate\Http\UploadedFile;

use Illuminate\Foundation\Testing\DatabaseTransactions;

use Tests\TestCase;



class DriverWalletDepositTest extends TestCase

{

    use DatabaseTransactions;



    public function createApplication()

    {

        $base = dirname(__DIR__, 2);

        $this->applyMysqlEnvFromDotEnv($base);



        $app = require $base . '/bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $app['config']->set('database.default', 'mysql');



        return $app;

    }



    private function applyMysqlEnvFromDotEnv(string $base): void

    {

        $envFile = $base . '/.env';

        if (! is_file($envFile)) {

            return;

        }



        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {

            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {

                continue;

            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);

            $value = trim($value, " \t\"'");

            if (! str_starts_with($key, 'DB_')) {

                continue;

            }

            putenv("{$key}={$value}");

            $_ENV[$key] = $value;

            $_SERVER[$key] = $value;

        }



        putenv('DB_CONNECTION=mysql');

        $_ENV['DB_CONNECTION'] = 'mysql';

        $_SERVER['DB_CONNECTION'] = 'mysql';

    }



    public function test_driver_can_submit_wallet_deposit_request(): void

    {

        [$driver, $profile] = $this->seedDriverWithWallet();



        $response = $this->actingAs($driver)->post(route('driver.wallet.deposit'), [
            'amount'      => DriverWalletConfig::MIN_DEPOSIT,
            'proof_image' => $this->fakeDepositProof(),
        ]);



        $response->assertRedirect(route('driver.dashboard', ['tab' => 'wallet']));

        $response->assertSessionHas('success');



        $this->assertDatabaseHas('driver_wallet_transactions', [

            'driver_wallet_id' => $profile->wallet()->first()->id,

            'type'             => 'deposit',

            'amount'           => DriverWalletConfig::MIN_DEPOSIT,

            'status'           => 'pending',

        ]);

    }



    public function test_driver_cannot_submit_while_pending_deposit_exists(): void

    {

        [$driver, $profile] = $this->seedDriverWithWallet();

        $wallet = $profile->wallet()->first();



        DriverWalletTransaction::query()->create([

            'driver_wallet_id' => $wallet->id,

            'type'             => 'deposit',

            'amount'           => DriverWalletConfig::MIN_DEPOSIT,

            'status'           => 'pending',

        ]);



        $response = $this->actingAs($driver)->post(route('driver.wallet.deposit'), [
            'amount'      => DriverWalletConfig::MIN_DEPOSIT * 2,
            'proof_image' => $this->fakeDepositProof(),
        ]);



        $response->assertRedirect(route('driver.dashboard', ['tab' => 'wallet']));

        $response->assertSessionHasErrors('wallet');



        $this->assertSame(1, DriverWalletTransaction::query()

            ->where('driver_wallet_id', $wallet->id)

            ->where('type', 'deposit')

            ->where('status', 'pending')

            ->count());

    }



    public function test_driver_cannot_exceed_max_pending_deposits(): void

    {

        [$driver, $profile] = $this->seedDriverWithWallet();

        $wallet = $profile->wallet()->first();



        for ($i = 0; $i < DriverWalletConfig::MAX_PENDING_DEPOSITS; $i++) {

            DriverWalletTransaction::query()->create([

                'driver_wallet_id' => $wallet->id,

                'type'             => 'deposit',

                'amount'           => DriverWalletConfig::MIN_DEPOSIT,

                'status'           => 'pending',

            ]);

        }



        $response = $this->actingAs($driver)->post(route('driver.wallet.deposit'), [
            'amount'      => DriverWalletConfig::MIN_DEPOSIT,
            'proof_image' => $this->fakeDepositProof(),
        ]);



        $response->assertRedirect(route('driver.dashboard', ['tab' => 'wallet']));

        $response->assertSessionHasErrors('wallet');

    }



    /** @return array{0: User, 1: DriverProfile} */
    private function seedDriverWithWallet(): array

    {

        $operator = User::factory()->create(['role' => 'admin']);

        $driver = User::factory()->create([

            'role'  => 'driver',

            'phone' => '09' . random_int(10000000, 99999999),

        ]);



        $profile = DriverProfile::query()->create([

            'user_id'             => $driver->id,

            'operator_id'         => $operator->id,

            'driver_code'         => 'TX' . random_int(1000, 9999),

            'license_number'      => 'L' . random_int(100000, 999999),

            'license_class'       => 'B2',

            'license_expiry'      => now()->addYears(2)->toDateString(),

            'status'              => 'active',

            'approval_status'     => 'approved',

            'availability_status' => 'available',

        ]);



        DriverWallet::query()->firstOrCreate(

            ['driver_profile_id' => $profile->id],

            ['balance' => 0],

        );



        return [$driver, $profile];
    }

    private function fakeDepositProof(): UploadedFile
    {
        return UploadedFile::fake()->image('deposit-proof.jpg', 900, 1600);
    }
}

