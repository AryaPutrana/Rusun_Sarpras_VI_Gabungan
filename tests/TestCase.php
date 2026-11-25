<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Setup the test environment.
     * 
     * CATATAN: CSRF middleware TIDAK dimatikan secara global.
     * Test yang perlu bypass CSRF harus eksplisit memanggil
     * $this->withoutMiddleware() di test method.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // JANGAN: $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        // Biarkan CSRF aktif untuk memastikan test valid
    }
}
