<?php

namespace Tests\Unit\AntiDrift;

use PHPUnit\Framework\TestCase;

class DtoPurityAntiDriftTest extends TestCase
{
    private function read(string $path): string
    {
        // php artisan test runs from project root (cwd).
        $root = getcwd();
        $full = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        $this->assertFileExists($full, "Missing file: {$path} (resolved: {$full})");

        $src = (string) file_get_contents($full);
        $this->assertNotSame('', $src, "Empty file: {$path}");
        return $src;
    }

    public function test_candidate_dto_from_array_must_not_synthesize_execution_slices(): void
    {
        $src = $this->read('app/DTO/Watchlist/Scorecard/CandidateDto.php');

        // DTO must not embed domain defaulting for execution slices / chase thresholds.
        $this->assertStringNotContainsString('maxChasePct', $src);
        $this->assertStringNotContainsString('max_chase_pct', $src);

        // DTO must not pull legacy threshold from levels into guards.
        $this->assertStringNotContainsString('max_chase_from_close_pct', $src);
        $this->assertStringNotContainsString('maxChaseFromClose', $src);
    }

    public function test_candidate_dto_source_must_not_reference_domain_guards_or_thresholds(): void
    {
        // IMPORTANT: DTO may carry guard fields as DATA (e.g. "guards").
        // We only forbid domain-defaulting / legacy threshold mapping inside DTO parsing.
        $src = $this->read('app/DTO/Watchlist/Scorecard/CandidateDto.php');

        $this->assertStringNotContainsString('max_chase_from_close_pct', $src);
        $this->assertStringNotContainsString('maxChaseFromClose', $src);
    }

    public function test_eligibility_result_dto_must_not_map_legacy_string_reasons(): void
    {
        $src = $this->read('app/DTO/Watchlist/Scorecard/EligibilityResultDto.php');

        // DTO must not use domain catalog mapping.
        $this->assertStringNotContainsString('ReasonCatalog', $src);

        // DTO must not map legacy CF_* codes directly.
        $this->assertStringNotContainsString('CF_', $src);

        // DTO must not accept string reasons and convert them (mapping belongs in service/mapper).
        $this->assertStringNotContainsString('is_string($r)', $src);
    }
}
