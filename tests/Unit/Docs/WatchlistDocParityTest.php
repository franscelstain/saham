<?php

namespace Tests\Unit\Docs;

use Tests\TestCase;

final class WatchlistDocParityTest extends TestCase
{
    public function testGroupSemanticsDocsReferenceSingleSourceOfTruth(): void
    {
        // Canonical index + single-source-of-truth snippet lives in 2.watchlist.md.
        $docPath = base_path('docs/watchlist/2.watchlist.md');
        $this->assertFileExists($docPath);

        $md = (string) file_get_contents($docPath);

        // Hard guardrail against unit confusion
        // docs use backticks for field names
        $this->assertStringContainsString('`score_total` (fraction, range [0..1])', $md);
        $this->assertStringContainsString('Group Semantics (Option A)', $md);
        $this->assertStringContainsString('`top_cut = max(TOPPICK_MIN_SCORE, S0 - TOPPICK_SCORE_GAP)`', $md);

        // Single source of truth: docs must NOT hardcode numeric thresholds.
        // Docs must reference config keys + env names.
        $this->assertStringContainsString("config('trade.watchlist.group_semantics.toppick_min_score')", $md);
        $this->assertStringContainsString('WATCHLIST_TOPPICK_MIN_SCORE', $md);

        $this->assertStringContainsString("config('trade.watchlist.group_semantics.toppick_score_gap')", $md);
        $this->assertStringContainsString('WATCHLIST_TOPPICK_SCORE_GAP', $md);

        $this->assertStringContainsString("config('trade.watchlist.group_semantics.secondary_min_score')", $md);
        $this->assertStringContainsString('WATCHLIST_SECONDARY_MIN_SCORE', $md);

        $this->assertStringContainsString("config('trade.watchlist.group_semantics.watch_only_min_score')", $md);
        $this->assertStringContainsString('WATCHLIST_WATCH_ONLY_MIN_SCORE', $md);

        // Make it harder to regress into numeric duplication.
        $this->assertStringNotContainsString('TOPPICK_MIN_SCORE = 0.', $md);
        $this->assertStringNotContainsString('SECONDARY_MIN_SCORE = 0.', $md);
        $this->assertStringNotContainsString('WATCH_ONLY_MIN_SCORE = 0.', $md);
    }
}
