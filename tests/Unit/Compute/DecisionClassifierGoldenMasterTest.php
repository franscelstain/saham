<?php

namespace Tests\Unit\Compute;

use App\Trade\Compute\Classifiers\DecisionClassifier;
use App\Trade\Compute\Config\DecisionGuardrails;
use Tests\TestCase;

class DecisionClassifierGoldenMasterTest extends TestCase
{
    public function testDecisionClassifierMatchesFixtureCases(): void
    {
        $casesPath = __DIR__ . '/../../Fixtures/compute/decision_classifier_cases.json';
        $this->assertFileExists($casesPath, 'Missing fixture: decision_classifier_cases.json');

        $cases = json_decode((string)file_get_contents($casesPath), true);
        $this->assertIsArray($cases);
        $this->assertNotEmpty($cases);

        $g = new DecisionGuardrails();
        $clf = new DecisionClassifier($g);

        foreach ($cases as $i => $case) {
            $this->assertIsArray($case, "case[$i] must be object");
            $m = $case['input'] ?? null;
            $exp = $case['expected_decision'] ?? null;
            $this->assertIsArray($m, "case[$i].input missing");
            $this->assertIsInt($exp, "case[$i].expected_decision missing");

            $act = $clf->classify($m);
            $this->assertSame($exp, $act, "case[$i] mismatch");
        }
    }
}
