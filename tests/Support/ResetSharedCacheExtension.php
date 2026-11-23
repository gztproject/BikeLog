<?php

namespace App\Tests\Support;

use PHPUnit\Runner\BeforeTestHook;
use Symfony\Component\Filesystem\Filesystem;

/**
 * cache.app lives in the kernel share dir (var/share/test) and survives kernel
 * reboots and test runs. Sign-in throttling stores its counters there, so every
 * test that signs in from 127.0.0.1 would spend the same per-IP budget.
 */
final class ResetSharedCacheExtension implements BeforeTestHook
{
    public function executeBeforeTest(string $test): void
    {
        (new Filesystem())->remove(dirname(__DIR__, 2).'/var/share/test');
    }
}
