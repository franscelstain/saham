<?php

namespace App\Trade\Watchlist\Scorecard;

use App\DTO\Watchlist\Scorecard\StrategyRunDto;
use App\Trade\Watchlist\Config\ScorecardConfig;
use Illuminate\Support\Facades\DB;

class StrategyRunRepository
{
    /** @var ScorecardConfig */
    private $cfg;

    public function __construct(ScorecardConfig $cfg)
    {
        $this->cfg = $cfg;
    }

    /**
     * Upsert a strategy run from a watchlist contract payload.
     * Returns run_id.
     *
     * @param array<string,mixed> $payload
     */
    private function upsertFromPayload(array $payload, string $source = 'watchlist'): int
    {
        $tradeDate = (string) ($payload['trade_date'] ?? '');
        $execDate = (string) (($payload['exec_trade_date'] ?? '') ?: ($payload['exec_date'] ?? ''));
        $policy = (string) (($payload['policy']['selected'] ?? '') ?: ($payload['policy'] ?? ''));
        if ($tradeDate === '' || $execDate === '' || $policy === '') {
            throw new \InvalidArgumentException('invalid strategy run payload: missing trade_date/exec_trade_date/policy');
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('failed to json_encode payload');
        }

        $generatedAt = null;
        // Prefer scorecard meta.generated_at, fall back to top-level generated_at for backward compat.
        if (isset($payload['meta']['generated_at']) && is_string($payload['meta']['generated_at']) && $payload['meta']['generated_at'] !== '') {
            $generatedAt = $payload['meta']['generated_at'];
        } elseif (isset($payload['generated_at']) && is_string($payload['generated_at']) && $payload['generated_at'] !== '') {
            $generatedAt = $payload['generated_at'];
        }

        // MariaDB/MySQL upsert: use unique key (trade_date, exec_trade_date, policy, source)
        DB::table('watchlist_strategy_runs')->upsert([
            [
                'trade_date' => $tradeDate,
                'exec_trade_date' => $execDate,
                'policy' => $policy,
                'source' => $source,
                'payload_json' => $json,
                'generated_at' => $generatedAt,
                // Avoid now() helper inside repo. Let DB fill timestamps.
                'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
                'created_at' => DB::raw('CURRENT_TIMESTAMP'),
            ],
        ], ['trade_date', 'exec_trade_date', 'policy', 'source'], ['payload_json', 'generated_at', 'updated_at']);

        $row = DB::table('watchlist_strategy_runs')
            ->where('trade_date', $tradeDate)
            ->where('exec_trade_date', $execDate)
            ->where('policy', $policy)
            ->where('source', $source)
            ->select('run_id')
            ->first();

        return $row ? (int) $row->run_id : 0;
    }

    public function upsertFromDto(StrategyRunDto $dto, string $source = 'watchlist'): int
    {
        $payload = $dto->toPayloadArray();
        return $this->upsertFromPayload($payload, $source);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getRun(string $tradeDate, string $execDate, string $policy, string $source = 'watchlist'): ?array
    {
        $row = DB::table('watchlist_strategy_runs')
            ->where('trade_date', $tradeDate)
            ->where('exec_trade_date', $execDate)
            ->where('policy', $policy)
            ->where('source', $source)
            ->select(['run_id', 'payload_json', 'generated_at'])
            ->first();

        if (!$row) return null;

        $payload = json_decode((string) $row->payload_json, true);
        if (!is_array($payload)) return null;

        $payload['_run_id'] = (int) $row->run_id;
        return $payload;
    }

    public function getRunDto(string $tradeDate, string $execDate, string $policy, string $source = 'watchlist'): ?StrategyRunDto
    {
        $row = DB::table('watchlist_strategy_runs')
            ->where('trade_date', $tradeDate)
            ->where('exec_trade_date', $execDate)
            ->where('policy', $policy)
            ->where('source', $source)
            ->select(['run_id', 'payload_json'])
            ->first();

        if (!$row) return null;
        $payload = json_decode((string)$row->payload_json, true);
        if (!is_array($payload)) return null;

        return StrategyRunDto::fromPayloadArray($payload, (int)$row->run_id, $this->cfg);
    }

    private function getRunId(string $tradeDate, string $execDate, string $policy, string $source = 'watchlist'): int
    {
        $row = DB::table('watchlist_strategy_runs')
            ->where('trade_date', $tradeDate)
            ->where('exec_trade_date', $execDate)
            ->where('policy', $policy)
            ->where('source', $source)
            ->select('run_id')
            ->first();

        return $row ? (int) $row->run_id : 0;
    }
}
