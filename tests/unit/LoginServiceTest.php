<?php

declare(strict_types=1);

use App\Models\UserModel;
use App\Services\Auth\LoginService;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LoginServiceTest extends TestCase
{
    public function testMessagesFollowTheExistingOrder(): void
    {
        $service = new LoginService(new FakeLoginUsers());

        foreach ([['nobody', 'x', 'Username tidak ditemukan'], ['ada', 'wrong', 'Kata sandi salah'], ['', 'x', 'Username tidak ditemukan']] as [$user, $pass, $message]) {
            try {
                $service->attempt($user, $pass);
                $this->fail('Sign-in must fail for ' . $user);
            } catch (DomainException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testSuspendedIsOnlyRevealedWithTheRightPassword(): void
    {
        $service = new LoginService(new FakeLoginUsers());

        try {
            $service->attempt('sus', 'wrong');
            $this->fail('Wrong password must fail first.');
        } catch (DomainException $exception) {
            $this->assertSame('Kata sandi salah', $exception->getMessage());
        }

        $this->expectExceptionMessage('Akun Anda ditangguhkan. Hubungi admin.');
        $service->attempt('sus', 'secret-pass');
    }

    public function testSuccessReturnsSessionValuesWithVersion(): void
    {
        $values = (new LoginService(new FakeLoginUsers()))->attempt('ada', 'secret-pass');

        $this->assertSame(['id_user' => 7, 'username' => 'ada', 'access_level' => 'User', 'session_version' => 4, 'logged_in' => true], $values);
    }
}

class FakeLoginUsers extends UserModel
{
    private array $rows;

    public function __construct()
    {
        $hash       = password_hash('secret-pass', PASSWORD_DEFAULT);
        $this->rows = [
            'ada' => ['id_user' => 7, 'username' => 'ada', 'password' => $hash, 'access_level' => 'User', 'account_status' => 'enabled', 'session_version' => 4],
            'sus' => ['id_user' => 8, 'username' => 'sus', 'password' => $hash, 'access_level' => 'User', 'account_status' => 'suspended', 'session_version' => 1],
        ];
    }

    public function getUserByUsername($username)
    {
        return $this->rows[$username] ?? null;
    }
}
