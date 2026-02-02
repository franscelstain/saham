<?php

namespace App\Trade\Watchlist\Contracts;

use InvalidArgumentException;

/**
 * Strict validator for docs/watchlist/watchlist.md contract.
 * This validator is intentionally opinionated: invalid output must fail fast.
 */
class WatchlistContractValidator
{
    /** @throws InvalidArgumentException */
    public function validate(array $payload): void
    {
        $this->req($payload, 'trade_date');
        $this->req($payload, 'exec_trade_date');
        $this->req($payload, 'generated_at');
        $this->req($payload, 'policy');
        $this->req($payload, 'meta');
        $this->req($payload, 'recommendations');
        $this->req($payload, 'groups');

        $this->validatePolicy($payload['policy']);
        $this->validateMeta($payload['meta']);
        $this->validateRecommendations($payload['recommendations']);
        $this->validateGroups($payload['groups'], $payload['recommendations']);

        // invariants cross-fields
        $this->validateModeConsistency($payload);

        // reason codes governance
        $this->validateReasonCodes($payload);
    }

    private function validatePolicy($p): void
    {
        if (!is_array($p)) $this->fail('policy must be object');
        $this->req($p, 'selected');
        $allowed = ['WEEKLY_SWING','DIVIDEND_SWING','INTRADAY_LIGHT','POSITION_TRADE','NO_TRADE'];
        if (!in_array($p['selected'], $allowed, true)) {
            $this->fail('policy.selected invalid');
        }
        if (isset($p['policy_version']) && $p['policy_version'] !== null && !is_string($p['policy_version'])) {
            $this->fail('policy.policy_version must be string|null');
        }
    }

    private function validateMeta($m): void
    {
        if (!is_array($m)) $this->fail('meta must be object');
        foreach (['dow','market_regime','eod_canonical_ready','as_of_trade_date','missing_trading_dates','counts','notes','session'] as $k) {
            $this->req($m, $k);
        }
        if (!is_bool($m['eod_canonical_ready'])) $this->fail('meta.eod_canonical_ready must be bool');
        if (!is_array($m['missing_trading_dates'])) $this->fail('meta.missing_trading_dates must be array');
        if (!is_array($m['notes'])) $this->fail('meta.notes must be array');
        if (!is_array($m['counts'])) $this->fail('meta.counts must be object');
        foreach (['total','top_picks','secondary','watch_only'] as $k) {
            $this->req($m['counts'], $k);
            if (!is_int($m['counts'][$k])) $this->fail('meta.counts.* must be int');
        }
        if (!is_array($m['session'])) $this->fail('meta.session must be object');
        foreach (['open_time','close_time','breaks'] as $k) $this->req($m['session'], $k);
        if (!is_array($m['session']['breaks'])) $this->fail('meta.session.breaks must be array');
    }

    private function validateRecommendations($r): void
    {
        if (!is_array($r)) $this->fail('recommendations must be object');
        foreach (['mode','max_positions_today','risk_per_trade_pct','capital_total','allocations'] as $k) $this->req($r, $k);
        if (!is_string($r['mode'])) $this->fail('recommendations.mode must be string');
        if (!is_int($r['max_positions_today'])) $this->fail('recommendations.max_positions_today must be int');

        if ($r['risk_per_trade_pct'] !== null && !is_numeric($r['risk_per_trade_pct'])) $this->fail('risk_per_trade_pct must be null|number');
        if ($r['capital_total'] !== null && !is_numeric($r['capital_total'])) $this->fail('capital_total must be null|number');

        if (!is_array($r['allocations'])) $this->fail('allocations must be array');

        // allocations schema minimal
        foreach ($r['allocations'] as $a) {
            if (!is_array($a)) $this->fail('allocation item must be object');
            foreach (['ticker_code','entry_price_ref','lots_recommended','estimated_cost','remaining_cash'] as $k) $this->req($a, $k);
            if (!is_string($a['ticker_code'])) $this->fail('alloc.ticker_code must be string');
            if (!is_int($a['entry_price_ref'])) $this->fail('alloc.entry_price_ref must be int');
            if (!is_int($a['lots_recommended'])) $this->fail('alloc.lots_recommended must be int');
            if (!is_int($a['estimated_cost'])) $this->fail('alloc.estimated_cost must be int');
            if (!is_int($a['remaining_cash'])) $this->fail('alloc.remaining_cash must be int');
            if (isset($a['alloc_pct']) && $a['alloc_pct'] !== null && !is_numeric($a['alloc_pct'])) $this->fail('alloc.alloc_pct must be number|null');
            if (isset($a['alloc_budget']) && $a['alloc_budget'] !== null && !is_int($a['alloc_budget'])) $this->fail('alloc.alloc_budget must be int|null');
            if (isset($a['reason_codes']) && !is_array($a['reason_codes'])) $this->fail('alloc.reason_codes must be array');
        }
    }

    private function validateGroups($g, array $rec): void
    {
        if (!is_array($g)) $this->fail('groups must be object');
        foreach (['top_picks','secondary','watch_only'] as $k) $this->req($g, $k);
        foreach (['top_picks','secondary','watch_only'] as $k) {
            if (!is_array($g[$k])) $this->fail("groups.$k must be array");
            foreach ($g[$k] as $c) $this->validateCandidate($c);
        }

        // unique ticker_code across all groups
        $seen = [];
        foreach (['top_picks','secondary','watch_only'] as $k) {
            foreach ($g[$k] as $c) {
                $code = $c['ticker_code'] ?? null;
                if (!is_string($code)) continue;
                if (isset($seen[$code])) $this->fail("duplicate ticker_code across groups: $code");
                $seen[$code] = true;
            }
        }

        // NO_TRADE/CARRY_ONLY top_picks must be empty
        if (in_array($rec['mode'], ['NO_TRADE','CARRY_ONLY'], true) && count($g['top_picks']) > 0) {
            $this->fail('top_picks must be empty when mode is NO_TRADE or CARRY_ONLY');
        }
    }

    private function validateCandidate($c): void
    {
        if (!is_array($c)) $this->fail('candidate must be object');
        foreach (['ticker_id','ticker_code','rank','watchlist_score','confidence','setup_type','reason_codes','debug','ticker_flags','timing','levels','sizing','position','checklist'] as $k) {
            $this->req($c, $k);
        }
        if (!is_int($c['ticker_id'])) $this->fail('candidate.ticker_id must be int');
        if (!is_string($c['ticker_code'])) $this->fail('candidate.ticker_code must be string');
        if (!is_int($c['rank'])) $this->fail('candidate.rank must be int');
        if (!is_numeric($c['watchlist_score'])) $this->fail('candidate.watchlist_score must be number');
        if (!in_array($c['confidence'], ['High','Med','Low'], true)) $this->fail('candidate.confidence invalid');
        if (!is_array($c['reason_codes'])) $this->fail('candidate.reason_codes must be array');
        if (!is_array($c['debug'])) $this->fail('candidate.debug must be object');
        if (!is_array($c['ticker_flags'])) $this->fail('candidate.ticker_flags must be object');
        if (!is_array($c['timing'])) $this->fail('candidate.timing must be object');
        if (!is_array($c['levels'])) $this->fail('candidate.levels must be object');
        if (!is_array($c['sizing'])) $this->fail('candidate.sizing must be object');
        if (!is_array($c['position'])) $this->fail('candidate.position must be object');
        if (!is_array($c['checklist'])) $this->fail('candidate.checklist must be array');

        // timing fields
        foreach (['entry_windows','avoid_windows','entry_style','size_multiplier','trade_disabled','trade_disabled_reason','trade_disabled_reason_codes'] as $k) {
            $this->req($c['timing'], $k);
        }
        if (!is_array($c['timing']['entry_windows'])) $this->fail('timing.entry_windows must be array');
        if (!is_array($c['timing']['avoid_windows'])) $this->fail('timing.avoid_windows must be array');
        if (!is_bool($c['timing']['trade_disabled'])) $this->fail('timing.trade_disabled must be bool');

        // ticker_flags minimal
        foreach (['special_notations','is_suspended','status_quality','status_asof_trade_date','trading_mechanism'] as $k) $this->req($c['ticker_flags'], $k);
        if (!is_array($c['ticker_flags']['special_notations'])) $this->fail('ticker_flags.special_notations must be array');
        if (!is_bool($c['ticker_flags']['is_suspended'])) $this->fail('ticker_flags.is_suspended must be bool');

        // levels minimal (must exist keys)
        foreach (['entry_type','entry_trigger_price','entry_limit_low','entry_limit_high','stop_loss_price','tp1_price','tp2_price','be_price'] as $k) $this->req($c['levels'], $k);

        // sizing keys
        foreach (['lot_size','slices','slice_pct','lots_recommended','estimated_cost','remaining_cash','risk_pct','profit_tp2_net','rr_tp2_net'] as $k) $this->req($c['sizing'], $k);

        // global locks invariant 8.4
        if (($c['ticker_flags']['is_suspended'] ?? false) === true
            || ($c['ticker_flags']['trading_mechanism'] ?? '') === 'FULL_CALL_AUCTION'
            || in_array('X', $c['ticker_flags']['special_notations'] ?? [], true)
        ) {
            if (($c['timing']['trade_disabled'] ?? false) !== true) $this->fail('ticker tradeability lock requires trade_disabled=true');
            if (($c['levels']['entry_type'] ?? '') !== 'WATCH_ONLY') $this->fail('ticker tradeability lock requires levels.entry_type=WATCH_ONLY');
        }

        // NO_TRADE invariant 8.1 is validated in validateModeConsistency()
    }

    private function validateModeConsistency(array $payload): void
    {
        $r = is_array($payload['recommendations'] ?? null) ? $payload['recommendations'] : [];
        $g = is_array($payload['groups'] ?? null) ? $payload['groups'] : [];

        $mode = (string)($r['mode'] ?? '');

        $want = [
            'BUY_1' => 1,
            'BUY_2_SPLIT' => 2,
            'BUY_3_SMALL' => 3,
        ];

        if (isset($want[$mode])) {
            if ((int)($r['max_positions_today'] ?? 0) !== $want[$mode]) $this->fail("mode $mode requires max_positions_today={$want[$mode]}");
            if (count($r['allocations'] ?? []) !== $want[$mode]) $this->fail("mode $mode requires allocations.length={$want[$mode]}");
        }

        if (in_array($mode, ['NO_TRADE','CARRY_ONLY'], true)) {
            if (count($r['allocations'] ?? []) !== 0) $this->fail("mode $mode requires allocations=[]");
        }

        // NO_TRADE invariants on candidates
        if ($mode === 'NO_TRADE') {
            $open = (string)($payload['meta']['session']['open_time'] ?? '09:00');
            $close = (string)($payload['meta']['session']['close_time'] ?? '15:50');
            $fullAvoid = [$open . '-' . $close];

            foreach (['top_picks','secondary','watch_only'] as $k) {
                foreach (($g[$k] ?? []) as $c) {
                    if (($c['timing']['trade_disabled'] ?? false) !== true) $this->fail('NO_TRADE requires timing.trade_disabled=true');
                    if (($c['timing']['entry_style'] ?? '') !== 'No-trade') $this->fail('NO_TRADE requires timing.entry_style=No-trade');
                    if ((float)($c['timing']['size_multiplier'] ?? 1.0) !== 0.0) $this->fail('NO_TRADE requires timing.size_multiplier=0.0');
                    if (count($c['timing']['entry_windows'] ?? []) !== 0) $this->fail('NO_TRADE requires entry_windows=[]');
                    $avoid = $c['timing']['avoid_windows'] ?? [];
                    if ($avoid !== $fullAvoid) $this->fail('NO_TRADE requires avoid_windows to cover full session');
                }
            }
        }
    }

    private function validateReasonCodes(array $payload): void
    {
        $allowedPrefixes = ['WS_','DS_','IL_','PT_','GL_','NT_'];

        $scan = function($codes) use ($allowedPrefixes) {
            if (!is_array($codes)) return;
            foreach ($codes as $rc) {
                if (!is_string($rc) || $rc === '') $this->fail('reason code must be non-empty string');
                $ok = false;
                foreach ($allowedPrefixes as $p) {
                    if (strpos($rc, $p) === 0) { $ok = true; break; }
                }
                if (!$ok) $this->fail("invalid reason code prefix: $rc");
            }
        };

        // allocations reason codes
        foreach (($payload['recommendations']['allocations'] ?? []) as $a) {
            if (is_array($a) && isset($a['reason_codes'])) $scan($a['reason_codes']);
        }

        // candidates
        foreach (['top_picks','secondary','watch_only'] as $k) {
            foreach (($payload['groups'][$k] ?? []) as $c) {
                $scan($c['reason_codes'] ?? []);
                $scan($c['timing']['trade_disabled_reason_codes'] ?? []);
            }
        }
    }

    private function req(array $a, string $k): void
    {
        if (!array_key_exists($k, $a)) $this->fail("missing key: $k");
    }

    private function fail(string $msg): void
    {
        throw new InvalidArgumentException("Watchlist contract invalid: {$msg}");
    }
}
