<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EmailValidationSecurityTest extends TestCase
{
    public function testEmailRulesRejectCrlf(): void
    {
        $payloads = [
            "user@example.com\r\nBcc: victim@example.com",
            "user@example.com\nBcc: victim@example.com",
            "\"user\r\n injected\"@example.com",
            "user\r@example.com",
        ];

        foreach (['email', 'email:strict', 'email:rfc,filter'] as $rule) {
            foreach ($payloads as $payload) {
                $this->assertTrue(
                    Validator::make(['email' => $payload], ['email' => $rule])->fails(),
                    "Rule {$rule} accepted " . json_encode($payload)
                );
            }
        }
    }

    public function testEmailRulesStillAcceptNormalAddresses(): void
    {
        foreach (['email', 'email:strict'] as $rule) {
            $this->assertTrue(Validator::make(['email' => 'user+tag@example.com'], ['email' => $rule])->passes());
        }
    }
}
