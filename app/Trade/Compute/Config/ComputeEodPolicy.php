<?php

namespace App\Trade\Compute\Config;

/**
 * SRP: Semua config untuk compute-eod harus dibaca di Provider (composition root),
 * lalu di-inject ke service/domain.
 */
final class ComputeEodPolicy
{
    /**
     * Jumlah trading days minimum untuk lookback indikator (contoh 260).
     */
    public int $lookbackTradingDays;

    /**
     * Tambahan trading days untuk warmup (stabilisasi RSI/ATR/MA). Default 60.
     */
    public int $warmupExtraTradingDays;

    /**
     * Batch size untuk upsert ke DB.
     */
    public int $upsertBatchSize;

    public function __construct(int $lookbackTradingDays = 260, int $warmupExtraTradingDays = 60, int $upsertBatchSize = 500)
    {
        $this->lookbackTradingDays = max(1, (int) $lookbackTradingDays);
        $this->warmupExtraTradingDays = max(0, (int) $warmupExtraTradingDays);
        $this->upsertBatchSize = max(1, (int) $upsertBatchSize);
    }
}
