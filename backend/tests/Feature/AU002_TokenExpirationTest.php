<?php

namespace Tests\Feature;

use Tests\TestCase;

class AU002_TokenExpirationTest extends TestCase
{
    public function test_sanctum_token_expiration_is_configured(): void
    {
        $expiration = config('sanctum.expiration');
        $this->assertNotNull($expiration, 'SANCTUM_TOKEN_EXPIRATION must be set (tokens must not be indefinite)');
        $this->assertGreaterThan(0, $expiration, 'Token expiration must be positive');
        $this->assertLessThanOrEqual(1440, $expiration, 'Token expiration should not exceed 24 hours');
    }
}
