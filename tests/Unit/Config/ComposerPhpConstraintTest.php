<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

final class ComposerPhpConstraintTest extends TestCase
{
    public function testComposerJsonDeclaresPhp74CompatibleConstraint(): void
    {
        $composerJson = base_path('composer.json');
        if (!is_file($composerJson)) {
            $this->markTestSkipped('composer.json not found in project root');
        }

        $json = json_decode((string) file_get_contents($composerJson), true);
        $this->assertIsArray($json);

        $require = $json['require'] ?? null;
        $this->assertIsArray($require, 'composer.json must have require section');

        $php = $require['php'] ?? null;
        $this->assertIsString($php, 'composer.json must declare require.php');

        // We run this project on PHP 7.4. Allow ^7.3 or ^7.4, disallow PHP 8-only constraints.
        $this->assertFalse(
            (bool) preg_match('/(^|[^0-9])8\./', $php),
            'composer.json php constraint looks like PHP 8+: ' . $php
        );
        $this->assertTrue(
            (bool) preg_match('/7\.(3|4)/', $php),
            'composer.json php constraint should include 7.3 or 7.4: ' . $php
        );
    }
}
