<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Horizon\Horizon;
use Tests\TestCase;

class HorizonAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => database_path('testing.sqlite'),
        ]);

        if (!file_exists(database_path('testing.sqlite'))) {
            touch(database_path('testing.sqlite'));
        }

        Schema::dropIfExists('v2_user');

        Schema::create('v2_user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 64)->unique();
            $table->string('password', 255);
            $table->boolean('banned')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_staff')->default(false);
            $table->string('uuid', 36);
            $table->char('token', 32);
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Cache::flush();
    }

    public function testHorizonAcceptsXboardAdminAuthorizationHeader(): void
    {
        $request = Request::create('/monitor/api/stats', 'GET');
        $request->headers->set('authorization', $this->authDataFor(true));

        $this->assertTrue(Horizon::check($request));
    }

    public function testHorizonRejectsXboardNonAdminAuthorizationHeader(): void
    {
        $request = Request::create('/monitor/api/stats', 'GET');
        $request->headers->set('authorization', $this->authDataFor(false));

        $this->assertFalse(Horizon::check($request));
    }

    private function authDataFor(bool $isAdmin): string
    {
        $user = User::query()->create([
            'email' => $isAdmin ? 'admin@example.com' : 'user@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'is_admin' => $isAdmin,
            'is_staff' => false,
            'uuid' => $isAdmin ? '00000000-0000-0000-0000-000000000001' : '00000000-0000-0000-0000-000000000002',
            'token' => str_repeat($isAdmin ? 'a' : 'b', 32),
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        return (new AuthService($user))->generateAuthData(Request::create('/'))['auth_data'];
    }
}
