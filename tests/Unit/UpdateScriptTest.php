<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class UpdateScriptTest extends TestCase
{
    public function testUpdateScriptUsesGitCheckoutProgressAndSkipsUploadMigration(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/Update.sh');

        $this->assertIsString($script);
        $this->assertStringContainsString('__BL_PROGRESS__', $script);
        $this->assertStringContainsString('git checkout --detach --force', $script);
        $this->assertStringContainsString('composer install --no-dev', $script);
        $this->assertStringContainsString('npm ci', $script);
        $this->assertStringContainsString('doctrine:migrations:migrate', $script);
        $this->assertStringContainsString('cache:clear --env=prod', $script);
        $this->assertStringNotContainsString('migrate_public_uploads', $script);
        $this->assertStringNotContainsString('update-release.pub', $script);
        $this->assertStringNotContainsString('BikeLog-$VER-build.tar.gz', $script);
    }
}
