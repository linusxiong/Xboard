<?php

namespace App\Support;

use Illuminate\Validation\Validator as BaseValidator;

class Validator extends BaseValidator
{
    /**
     * Backport of the Laravel 12.60 fix for GHSA-5vg9-5847-vvmq: Laravel 10 has no
     * patched release, and CRLF in an address can inject headers into outbound mail.
     */
    public function validateEmail($attribute, $value, $parameters)
    {
        if ((is_string($value) || (is_object($value) && method_exists($value, '__toString')))
            && preg_match('/[\r\n]/', (string) $value) > 0) {
            return false;
        }

        return parent::validateEmail($attribute, $value, $parameters);
    }
}
