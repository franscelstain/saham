<?php

namespace App\Trade\Watchlist;

use App\DTO\Watchlist\CandidateInput;

/**
 * SRP: klasifikasi setup_type berbasis EOD (signal_code + struktur sederhana).
 * Output: Breakout / Pullback / Continuation / Reversal / Base.
 */
class SetupTypeClassifier
{
    public function classify(CandidateInput $c): string
    {
        $signal = (int) ($c->signalCode ?? 0);

        // mapping paling deterministik: ikuti LabelCatalog signalMap
        if (in_array($signal, [4, 5], true)) return 'Breakout';
        if (in_array($signal, [6, 7], true)) return 'Pullback';

        // distribution/climax/false breakout → treat as reversal risk
        if (in_array($signal, [8, 9, 10], true)) return 'Reversal';

        // early uptrend / accumulation biasanya continuation
        if (in_array($signal, [2, 3], true)) return 'Continuation';

        // base/sideways atau unknown
        return 'Base';
    }
}
