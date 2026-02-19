<?php

declare(strict_types=1);

namespace Tests\Unit\AntiDrift;

use App\DTO\Watchlist\Scorecard\CandidateDto;
use App\DTO\Watchlist\Scorecard\EligibilityResultDto;
use PHPUnit\Framework\TestCase;

final class DtoPurityAntiDriftTest extends TestCase
{
    private function read(string $relativePath): string
    {
        $base = dirname(__DIR__, 2); // tests/
        $path = $base . '/../' . ltrim($relativePath, '/');
        $this->assertFileExists($path, "Missing file: {$relativePath}");
        $content = file_get_contents($path);
        $this->assertIsString($content);
        return $content;
    }

    public function test_candidate_dto_from_array_must_not_synthesize_execution_slices(): void
    {
        // DTO.md: DTO must not contain domain defaulting/synthesis logic.
        // Runtime proof: fromArray should not create slices when none provided.
        $dto = CandidateDto::fromArray([
            'ticker_code' => 'TEST',
            'asof_date' => '2026-02-01',
            'entry_trigger' => [
                'method' => 'AT_OPEN',
            ],
            // intentionally omit execution_slices
        ]);

        $arr = $dto->toArray();
        $this->assertArrayNotHasKey('execution_slices', $arr, 'DTO must not synthesize execution_slices when missing');
    }

    public function test_candidate_dto_source_must_not_reference_domain_guards_or_thresholds(): void
    {
        // Static guard: fromArray must not embed domain config/guard knowledge.
        $src = $this->read('app/DTO/Watchlist/Scorecard/CandidateDto.php');
        $this->assertStringNotContainsString('maxChasePct', $src);
        $this->assertStringNotContainsString('max_chase_pct', $src);
        $this->assertStringNotContainsString('guards', $src);
    }

    public function test_eligibility_result_dto_must_not_map_legacy_string_reasons(): void
    {
        // DTO.md: DTO must not perform domain mapping (string code -> reason object).
        // If legacy strings are passed, they must not be converted into structured reasons here.
        $dto = EligibilityResultDto::fromArray([
            'is_eligible' => false,
            'reasons' => ['CF_OK', 'CF_BOOK_TOO_THIN'],
        ]);

        $arr = $dto->toArray();
        $this->assertIsArray($arr['reasons'] ?? null);
        $this->assertCount(0, $arr['reasons'], 'DTO must not accept/convert legacy string reasons');
    }
}
