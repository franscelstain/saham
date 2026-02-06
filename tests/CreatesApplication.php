<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        // PHPUnit must not be affected by stale artisan config cache from local dev.
        // This keeps config('trade.*') aligned with config/trade.php in the working tree.
        $cachedConfig = __DIR__ . '/../bootstrap/cache/config.php';
        if (is_file($cachedConfig)) {
            @unlink($cachedConfig);
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
