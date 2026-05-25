<?php

namespace Tests\Feature;

use App\Models\User;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class PassportSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => database_path('testing.sqlite'),
        ]);

        if (!file_exists(database_path('testing.sqlite'))) {
            touch(database_path('testing.sqlite'));
        }

        Schema::dropIfExists('v2_settings');
        Schema::dropIfExists('v2_user');

        Schema::create('v2_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('v2_user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 64)->unique();
            $table->string('password', 255);
            $table->char('password_algo', 10)->nullable();
            $table->char('password_salt', 10)->nullable();
            $table->boolean('banned')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->string('uuid', 36);
            $table->char('token', 32);
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Cache::flush();
    }

    public function testLoginWithMailLinkDoesNotReturnMagicLoginLink(): void
    {
        Queue::fake();
        $this->putSetting('login_with_mail_link_enable', '1');
        $this->putSetting('app_url', 'https://example.test');
        $this->createUser('user@example.com');

        $response = $this->postJson('/api/v1/passport/auth/loginWithMailLink', [
            'email' => 'user@example.com',
            'redirect' => 'dashboard',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data', true);

        $this->assertStringNotContainsString('verify=', $response->getContent());
        $this->assertStringNotContainsString('TEMP_TOKEN', $response->getContent());
    }

    public function testForgetNormalizesEmailAndClearsSessionsAfterPasswordReset(): void
    {
        $user = $this->createUser('reset@example.com');
        Cache::put(CacheKey::get('EMAIL_VERIFY_CODE', 'reset@example.com'), '123456', 300);
        Cache::put(CacheKey::get('USER_SESSIONS', $user->id), ['session-id' => ['ip' => '127.0.0.1']]);

        $response = $this->postJson('/api/v1/passport/auth/forget', [
            'email' => 'RESET@example.com',
            'password' => 'new-password',
            'email_code' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data', true);

        $user->refresh();
        $this->assertTrue(password_verify('new-password', $user->password));
        $this->assertNull($user->password_algo);
        $this->assertNull($user->password_salt);
        $this->assertFalse(Cache::has(CacheKey::get('EMAIL_VERIFY_CODE', 'reset@example.com')));
        $this->assertFalse(Cache::has(CacheKey::get('USER_SESSIONS', $user->id)));
    }

    public function testForgetRejectsNonSixDigitEmailCode(): void
    {
        $this->createUser('reset@example.com');
        Cache::put(CacheKey::get('EMAIL_VERIFY_CODE', 'reset@example.com'), '123456', 300);

        $response = $this->postJson('/api/v1/passport/auth/forget', [
            'email' => 'reset@example.com',
            'password' => 'new-password',
            'email_code' => '1234567',
        ]);

        $response->assertStatus(422);
    }

    private function putSetting(string $name, string $value): void
    {
        \DB::table('v2_settings')->updateOrInsert(
            ['name' => $name],
            ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
        );
        Cache::forget('admin_settings');
    }

    private function createUser(string $email): User
    {
        return User::query()->create([
            'email' => $email,
            'password' => password_hash('old-password', PASSWORD_DEFAULT),
            'uuid' => '00000000-0000-0000-0000-000000000000',
            'token' => str_repeat('a', 32),
        ]);
    }
}
