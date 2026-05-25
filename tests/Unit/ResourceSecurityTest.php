<?php

namespace Tests\Unit;

use App\Http\Resources\InviteCodeResource;
use App\Http\Resources\TicketResource;
use App\Http\Resources\TrafficLogResource;
use Illuminate\Http\Request;
use Tests\TestCase;

class ResourceSecurityTest extends TestCase
{
    public function testUserResourcesDoNotExposeInternalUserIdsByDefault(): void
    {
        config(['hidden_features.enable_exposed_user_count_fix' => true]);

        $request = Request::create('/api/v1/user/resource-security-test');

        $this->assertArrayNotHasKey('user_id', (new InviteCodeResource([
            'user_id' => 123,
            'code' => 'INVITE',
            'pv' => 0,
            'status' => 0,
            'created_at' => 1,
            'updated_at' => 1,
        ]))->toArray($request));

        $this->assertArrayNotHasKey('user_id', (new TicketResource([
            'id' => 1,
            'user_id' => 123,
            'level' => 0,
            'reply_status' => 0,
            'status' => 0,
            'subject' => 'Subject',
            'created_at' => 1,
            'updated_at' => 1,
        ]))->toArray($request));

        $this->assertArrayNotHasKey('user_id', (new TrafficLogResource([
            'user_id' => 123,
            'd' => 0,
            'u' => 0,
            'record_at' => 1,
            'server_rate' => 1,
        ]))->toArray($request));
    }
}
