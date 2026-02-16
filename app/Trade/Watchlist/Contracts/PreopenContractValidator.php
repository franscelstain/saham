<?php

namespace App\Trade\Watchlist\Contracts;

/**
 * PreopenContractValidator
 *
 * Strict validator for docs/watchlist/preopen.md (LOCKED).
 * Enforces:
 * - required keys
 * - NO extra keys
 * - basic type checks
 *
 * NOTE: This validator is intentionally dependency-free (no DB, no Laravel helpers).
 */
class PreopenContractValidator
{
    public function validate(array $p): void
    {
        $this->assertExactKeys($p, ['meta','groups','recommendations','confirm'], '$');

        $this->validateMeta($p['meta'], '$.meta');
        $this->validateGroups($p['groups'], '$.groups');
        $this->validateRecommendations($p['recommendations'], '$.recommendations');
        $this->validateConfirm($p['confirm'], '$.confirm');
    }

    private function validateMeta($m, string $path): void
    {
        $this->assertIsArray($m, $path);
        $this->assertExactKeys($m, ['policy','trade_date','asof_eod_date','canonical_ready','flags','reasons'], $path);

        $this->assertIsString($m['policy'], $path.'.policy');
        $this->assertIsDate($m['trade_date'], $path.'.trade_date');
        $this->assertIsDate($m['asof_eod_date'], $path.'.asof_eod_date');
        $this->assertIsBool($m['canonical_ready'], $path.'.canonical_ready');

        $this->assertIsArray($m['flags'], $path.'.flags');
        foreach ($m['flags'] as $i => $f) {
            $this->assertIsString($f, $path.'.flags['.$i.']');
        }

        $this->assertIsArray($m['reasons'], $path.'.reasons');
        foreach ($m['reasons'] as $i => $r) {
	        $this->validateGlobalReason($r, $path.'.reasons['.$i.']');
        }
    }

    private function validateGroups($g, string $path): void
    {
        $this->assertIsArray($g, $path);
        $this->assertExactKeys($g, ['top_picks','secondary','watch_only','avoid','no_trade'], $path);

        foreach (['top_picks','secondary','watch_only','avoid','no_trade'] as $k) {
            $this->assertIsArray($g[$k], $path.'.'.$k);
            foreach ($g[$k] as $i => $it) {
                $this->validateTickerItem($it, $path.'.'.$k.'['.$i.']');
            }
        }
    }

    private function validateRecommendations($r, string $path): void
    {
        $this->assertIsArray($r, $path);
        $this->assertExactKeys($r, ['mode','capital_idr','items','reasons','skipped','cash_remaining_idr'], $path);

        $this->assertIsString($r['mode'], $path.'.mode');

        // Mode A allows null capital and null remaining cash.
        if ($r['capital_idr'] !== null) $this->assertIsInt($r['capital_idr'], $path.'.capital_idr');
        if ($r['cash_remaining_idr'] !== null) $this->assertIsInt($r['cash_remaining_idr'], $path.'.cash_remaining_idr');

        $this->assertIsArray($r['items'], $path.'.items');
        foreach ($r['items'] as $i => $it) {
            $this->validateRecommendationItem($it, $path.'.items['.$i.']');
        }

        // recommendations-level reasons (e.g. exposure cap)
        $this->assertIsArray($r['reasons'], $path.'.reasons');
        foreach ($r['reasons'] as $i => $rs) {
	        $this->validateGlobalReason($rs, $path.'.reasons['.$i.']');
        }

        // skipped tickers for UI audit
        $this->assertIsArray($r['skipped'], $path.'.skipped');
        foreach ($r['skipped'] as $i => $s) {
            $this->validateRecommendationSkip($s, $path.'.skipped['.$i.']');
        }
    }

    private function validateRecommendationSkip($s, string $path): void
    {
        $this->assertIsArray($s, $path);
        $this->assertExactKeys($s, ['ticker','reason'], $path);

        $this->assertIsString($s['ticker'], $path.'.ticker');
	    $this->validateGlobalReason($s['reason'], $path.'.reason');
    }

    private function validateConfirm($c, string $path): void
    {
        $this->assertIsArray($c, $path);
        $this->assertExactKeys($c, ['status','checked_count','by_ticker'], $path);

        $this->assertIsString($c['status'], $path.'.status');
        $this->assertIsInt($c['checked_count'], $path.'.checked_count');

        // by_ticker is an object map (ticker => ConfirmResult)
        if (!is_array($c['by_ticker'])) {
            throw new \InvalidArgumentException($path.'.by_ticker must be object/map');
        }
        foreach ($c['by_ticker'] as $ticker => $res) {
            $this->assertIsString($ticker, $path.'.by_ticker key');
            $this->validateConfirmResult($res, $path.'.by_ticker['.$ticker.']');
        }
    }

    private function validateTickerItem($it, string $path): void
    {
        $this->assertIsArray($it, $path);
        $this->assertExactKeys($it, ['ticker','rank','score_total','reasons','eod_bar','ticker_plan'], $path);

        $this->assertIsString($it['ticker'], $path.'.ticker');
        $this->assertIsInt($it['rank'], $path.'.rank');
        $this->assertIsNumber($it['score_total'], $path.'.score_total');

        $this->assertIsArray($it['reasons'], $path.'.reasons');
        foreach ($it['reasons'] as $i => $r) {
	        $this->validateCandidateReason($r, $path.'.reasons['.$i.']');
        }

        $this->validateEodBar($it['eod_bar'], $path.'.eod_bar');
        $this->validateTickerPlan($it['ticker_plan'], $path.'.ticker_plan');
    }

    private function validateEodBar($b, string $path): void
    {
        $this->assertIsArray($b, $path);
        $this->assertExactKeys($b, ['asof_eod_date','open','high','low','close','prev_close','gap_pct','volume_shares','value_idr'], $path);

        $this->assertIsDate($b['asof_eod_date'], $path.'.asof_eod_date');
        foreach (['open','high','low','close'] as $k) $this->assertIsInt($b[$k], $path.'.'.$k);

        if ($b['prev_close'] !== null) $this->assertIsInt($b['prev_close'], $path.'.prev_close');
        if ($b['gap_pct'] !== null) $this->assertIsNumber($b['gap_pct'], $path.'.gap_pct');

        $this->assertIsInt($b['volume_shares'], $path.'.volume_shares');
        $this->assertIsInt($b['value_idr'], $path.'.value_idr');
    }

    private function validateTickerPlan($p, string $path): void
    {
        $this->assertIsArray($p, $path);
        $this->assertExactKeys($p, ['setup_type','plan_entry','plan_stop','plan_tp1','rr_est','execution_slices'], $path);

        $this->assertIsString($p['setup_type'], $path.'.setup_type');
        if ($p['plan_entry'] !== null) $this->assertIsInt($p['plan_entry'], $path.'.plan_entry');
        if ($p['plan_stop'] !== null) $this->assertIsInt($p['plan_stop'], $path.'.plan_stop');
        if ($p['plan_tp1'] !== null) $this->assertIsInt($p['plan_tp1'], $path.'.plan_tp1');

        if ($p['rr_est'] !== null) $this->assertIsNumber($p['rr_est'], $path.'.rr_est');

        $this->assertIsArray($p['execution_slices'], $path.'.execution_slices');
        foreach ($p['execution_slices'] as $i => $s) {
            $this->validateExecutionSlicePlan($s, $path.'.execution_slices['.$i.']');
        }
    }

    private function validateExecutionSlicePlan($s, string $path): void
    {
        $this->assertIsArray($s, $path);
        $this->assertExactKeys($s, ['n','time','lots','plan_limit_price','plan_price_cap','plan_price_floor','trigger','reason'], $path);

        $this->assertIsInt($s['n'], $path.'.n');
        $this->assertIsString($s['time'], $path.'.time');

        if ($s['lots'] !== null) $this->assertIsInt($s['lots'], $path.'.lots');
        if ($s['plan_limit_price'] !== null) $this->assertIsInt($s['plan_limit_price'], $path.'.plan_limit_price');
        if ($s['plan_price_cap'] !== null) $this->assertIsInt($s['plan_price_cap'], $path.'.plan_price_cap');
        if ($s['plan_price_floor'] !== null) $this->assertIsInt($s['plan_price_floor'], $path.'.plan_price_floor');

        $this->assertIsString($s['trigger'], $path.'.trigger');
	    $this->validateCandidateReason($s['reason'], $path.'.reason');
    }

    private function validateRecommendationItem($it, string $path): void
    {
        $this->assertIsArray($it, $path);
        $this->assertExactKeys($it, ['ticker','rank_ref','weight_pct','planned_lots','estimated_cost_idr','fee_included','reasons','setup_type','plan_entry','plan_stop','plan_tp1','execution_slices'], $path);

        $this->assertIsString($it['ticker'], $path.'.ticker');
        $this->assertIsInt($it['rank_ref'], $path.'.rank_ref');
        $this->assertIsNumber($it['weight_pct'], $path.'.weight_pct');

        if ($it['planned_lots'] !== null) $this->assertIsInt($it['planned_lots'], $path.'.planned_lots');
        if ($it['estimated_cost_idr'] !== null) $this->assertIsInt($it['estimated_cost_idr'], $path.'.estimated_cost_idr');

        $this->assertIsBool($it['fee_included'], $path.'.fee_included');

        $this->assertIsArray($it['reasons'], $path.'.reasons');
        foreach ($it['reasons'] as $i => $r) {
	        $this->validateCandidateReason($r, $path.'.reasons['.$i.']');
        }

        $this->assertIsString($it['setup_type'], $path.'.setup_type');
        if ($it['plan_entry'] !== null) $this->assertIsInt($it['plan_entry'], $path.'.plan_entry');
        if ($it['plan_stop'] !== null) $this->assertIsInt($it['plan_stop'], $path.'.plan_stop');
        if ($it['plan_tp1'] !== null) $this->assertIsInt($it['plan_tp1'], $path.'.plan_tp1');

        $this->assertIsArray($it['execution_slices'], $path.'.execution_slices');
        foreach ($it['execution_slices'] as $i => $s) {
            $this->validateExecutionSlicePlan($s, $path.'.execution_slices['.$i.']');
        }
    }

    private function validateConfirmResult($r, string $path): void
    {
        $this->assertIsArray($r, $path);
        $this->assertExactKeys($r, ['checked_at','decision','eligible_now','next_check_at','reasons','retry','computed','recommended_orders'], $path);

        $this->assertIsString($r['checked_at'], $path.'.checked_at');
        $this->assertIsString($r['decision'], $path.'.decision');
        $this->assertIsBool($r['eligible_now'], $path.'.eligible_now');

        if ($r['next_check_at'] !== null) $this->assertIsString($r['next_check_at'], $path.'.next_check_at');

        $this->assertIsArray($r['reasons'], $path.'.reasons');
        foreach ($r['reasons'] as $i => $rs) {
            $this->validateCandidateReason($rs, $path.'.reasons['.$i.']');
        }

        $this->assertIsArray($r['retry'], $path.'.retry');
        $this->assertExactKeys($r['retry'], ['retry_count','max_retry_windows'], $path.'.retry');
        $this->assertIsInt($r['retry']['retry_count'], $path.'.retry.retry_count');
        $this->assertIsInt($r['retry']['max_retry_windows'], $path.'.retry.max_retry_windows');

        $this->assertIsArray($r['computed'], $path.'.computed');
        $this->assertExactKeys($r['computed'], ['gap_pct','spread_pct','chase_pct','snapshot_age_sec'], $path.'.computed');
        if ($r['computed']['gap_pct'] !== null) $this->assertIsNumber($r['computed']['gap_pct'], $path.'.computed.gap_pct');
        if ($r['computed']['spread_pct'] !== null) $this->assertIsNumber($r['computed']['spread_pct'], $path.'.computed.spread_pct');
        if ($r['computed']['chase_pct'] !== null) $this->assertIsNumber($r['computed']['chase_pct'], $path.'.computed.chase_pct');
        if ($r['computed']['snapshot_age_sec'] !== null) $this->assertIsInt($r['computed']['snapshot_age_sec'], $path.'.computed.snapshot_age_sec');

        $this->assertIsArray($r['recommended_orders'], $path.'.recommended_orders');
        foreach ($r['recommended_orders'] as $i => $o) {
            $this->validateConfirmOrder($o, $path.'.recommended_orders['.$i.']');
        }
    }

    private function validateConfirmOrder($o, string $path): void
    {
        $this->assertIsArray($o, $path);
        $this->assertExactKeys($o, ['n','time_window','action','lots','plan_limit_price','plan_price_cap','recommended_limit_price','reasons','inputs_used'], $path);

        $this->assertIsInt($o['n'], $path.'.n');
        $this->assertIsString($o['time_window'], $path.'.time_window');
        $this->assertIsString($o['action'], $path.'.action');

        if ($o['lots'] !== null) $this->assertIsInt($o['lots'], $path.'.lots');
        if ($o['plan_limit_price'] !== null) $this->assertIsInt($o['plan_limit_price'], $path.'.plan_limit_price');
        if ($o['plan_price_cap'] !== null) $this->assertIsInt($o['plan_price_cap'], $path.'.plan_price_cap');
        if ($o['recommended_limit_price'] !== null) $this->assertIsInt($o['recommended_limit_price'], $path.'.recommended_limit_price');

        $this->assertIsArray($o['reasons'], $path.'.reasons');
        foreach ($o['reasons'] as $i => $rs) {
            $this->validateCandidateReason($rs, $path.'.reasons['.$i.']');
        }

        $this->assertIsArray($o['inputs_used'], $path.'.inputs_used');
        $this->assertExactKeys($o['inputs_used'], ['ask_best','bid_best','spread_pct','snapshot_age_sec'], $path.'.inputs_used');
        if ($o['inputs_used']['ask_best'] !== null) $this->assertIsInt($o['inputs_used']['ask_best'], $path.'.inputs_used.ask_best');
        if ($o['inputs_used']['bid_best'] !== null) $this->assertIsInt($o['inputs_used']['bid_best'], $path.'.inputs_used.bid_best');
        if ($o['inputs_used']['spread_pct'] !== null) $this->assertIsNumber($o['inputs_used']['spread_pct'], $path.'.inputs_used.spread_pct');
        if ($o['inputs_used']['snapshot_age_sec'] !== null) $this->assertIsInt($o['inputs_used']['snapshot_age_sec'], $path.'.inputs_used.snapshot_age_sec');
    }

    private function validateGlobalReason($r, string $path): void
    {
        $this->assertIsArray($r, $path);
        $allowed = ['code','message','severity'];
        foreach ($r as $k => $v) {
            if (!in_array($k, $allowed, true)) {
                throw new \InvalidArgumentException($path.' has extra key: '.$k);
            }
        }
        if (!array_key_exists('code', $r) || !array_key_exists('message', $r)) {
            throw new \InvalidArgumentException($path.' must have code and message');
        }
        $this->assertIsString($r['code'], $path.'.code');
        $this->assertIsString($r['message'], $path.'.message');
        if (array_key_exists('severity', $r) && $r['severity'] !== null) {
            $this->assertIsString($r['severity'], $path.'.severity');
        }
    }

    private function validateCandidateReason($r, string $path): void
    {
        $this->assertIsArray($r, $path);
        $allowed = ['code','message','severity_level'];
        foreach ($r as $k => $v) {
            if (!in_array($k, $allowed, true)) {
                throw new \InvalidArgumentException($path.' has extra key: '.$k);
            }
        }
        if (!array_key_exists('code', $r) || !array_key_exists('message', $r) || !array_key_exists('severity_level', $r)) {
            throw new \InvalidArgumentException($path.' must have code, message, severity_level');
        }
        $this->assertIsString($r['code'], $path.'.code');
        $this->assertIsString($r['message'], $path.'.message');
        $this->assertIsString($r['severity_level'], $path.'.severity_level');
    }

    private function assertExactKeys(array $arr, array $keys, string $path): void
    {
        $actual = array_keys($arr);
        sort($actual);
        $expected = $keys;
        sort($expected);
        if ($actual !== $expected) {
            $extra = array_values(array_diff($actual, $expected));
            $missing = array_values(array_diff($expected, $actual));
            throw new \InvalidArgumentException($path.' keys mismatch. missing=['.implode(',', $missing).'] extra=['.implode(',', $extra).']');
        }
    }

    private function assertIsArray($v, string $path): void
    {
        if (!is_array($v)) throw new \InvalidArgumentException($path.' must be array/object');
    }

    private function assertIsString($v, string $path): void
    {
        if (!is_string($v)) throw new \InvalidArgumentException($path.' must be string');
    }

    private function assertIsBool($v, string $path): void
    {
        if (!is_bool($v)) throw new \InvalidArgumentException($path.' must be bool');
    }

    private function assertIsInt($v, string $path): void
    {
        if (!is_int($v)) throw new \InvalidArgumentException($path.' must be int');
    }

    private function assertIsNumber($v, string $path): void
    {
        if (!is_int($v) && !is_float($v)) throw new \InvalidArgumentException($path.' must be number');
    }

    private function assertIsDate($v, string $path): void
    {
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            throw new \InvalidArgumentException($path.' must be YYYY-MM-DD');
        }
    }
}
