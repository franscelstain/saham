<?php

namespace Tests\Unit\Calendar;

use App\Trade\Pricing\FeeConfig;
use App\Trade\Pricing\TickLadderConfig;
use App\Trade\Pricing\TickRule;
use App\Trade\Support\TradeClockConfig;
use App\Trade\Watchlist\WatchlistEngine;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class MarketCalendarSessionEdgeCasesTest extends TestCase
{
    private function buildEngineWithCalendarRow(array $row): WatchlistEngine
    {
        $ref = new ReflectionClass(WatchlistEngine::class);
        $ctor = $ref->getConstructor();
        if (!$ctor) {
            throw new \RuntimeException('WatchlistEngine has no constructor');
        }

        $args = [];
        foreach ($ctor->getParameters() as $p) {
            $t = $p->getType();
            $name = ($t && method_exists($t, 'getName')) ? $t->getName() : null;

            // Builtins
            if ($name === 'int') { $args[] = 0; continue; }
            if ($name === 'float') { $args[] = 0.0; continue; }
            if ($name === 'string') { $args[] = ''; continue; }
            if ($name === 'bool') { $args[] = false; continue; }
            if ($name === 'array') { $args[] = []; continue; }

            // Specific real instances to avoid mocking finals / typed properties
            if ($name === TickRule::class) {
                $args[] = new TickRule(new TickLadderConfig([
                    ['lt' => 200, 'tick' => 1],
                    ['lt' => 500, 'tick' => 2],
                    ['lt' => 2000, 'tick' => 5],
                    ['lt' => 5000, 'tick' => 10],
                    ['lt' => 20000, 'tick' => 25],
                    ['lt' => 50000, 'tick' => 50],
                    ['tick' => 100],
                ]));
                continue;
            }
            if ($name === FeeConfig::class) {
                $args[] = new FeeConfig(0.0, 0.0, 0.0, 0.0, 0.0);
                continue;
            }

            // MarketCalendarRepository-like: feed getCalendarRow(date) from fixture.
            if ($name && (class_exists($name) || interface_exists($name)) && preg_match('/MarketCalendarRepository$/', $name)) {
                $mock = $this->createMock($name);
                if (method_exists($mock, 'getCalendarRow')) {
                    $mock->method('getCalendarRow')->willReturn($row);
                }
                if (method_exists($mock, 'tableExists')) {
                    $mock->method('tableExists')->willReturn(true);
                }
                $args[] = $mock;
                continue;
            }

            // Avoid mocking final classes (PHPUnit cannot double finals by default).
            if ($name && class_exists($name)) {
                $rc = new \ReflectionClass($name);
                if ($rc->isFinal()) {
                    // Special-case the few finals used in the watchlist engine.
                    if ($name === TradeClockConfig::class) {
                        $args[] = new TradeClockConfig('Asia/Jakarta', 15, 50);
                        continue;
                    }

                    // Best-effort instantiate final classes with dummy constructor args.
                    $c = $rc->getConstructor();
                    if ($c) {
                        $ctorArgs = [];
                        foreach ($c->getParameters() as $cp) {
                            $ct = $cp->getType();
                            $cn = ($ct && method_exists($ct, 'getName')) ? $ct->getName() : null;
                            if ($cn === 'int') { $ctorArgs[] = 0; continue; }
                            if ($cn === 'float') { $ctorArgs[] = 0.0; continue; }
                            if ($cn === 'string') { $ctorArgs[] = ''; continue; }
                            if ($cn === 'bool') { $ctorArgs[] = false; continue; }
                            if ($cn === 'array') { $ctorArgs[] = []; continue; }
                            $ctorArgs[] = null;
                        }
                        try {
                            $args[] = $rc->newInstanceArgs($ctorArgs);
                            continue;
                        } catch (\Throwable $e) {
                            // fallthrough
                        }
                    }

                    $args[] = $rc->newInstanceWithoutConstructor();
                    continue;
                }
            }

            // Default: mock any class/interface.
            if ($name && (class_exists($name) || interface_exists($name))) {
                $args[] = $this->createMock($name);
                continue;
            }

            // Fallback
            $args[] = null;
        }

        return $ref->newInstanceArgs($args);
    }

    private function callPrivate(object $obj, string $method, array $args = [])
    {
        $m = new ReflectionMethod(get_class($obj), $method);
        $m->setAccessible(true);
        return $m->invokeArgs($obj, $args);
    }

    public function testResolveWindowsReplacesOpenCloseTokens(): void
    {
        $row = [
            'trade_date' => '2026-01-27',
            'is_trading_day' => 1,
            'session_open_time' => '09:30',
            'session_close_time' => '15:00',
            'breaks_json' => '[]',
        ];

        $engine = $this->buildEngineWithCalendarRow($row);

        $in = ['open-09:40', '14:00-close', '09:45-10:00'];
        $out = $this->callPrivate($engine, 'resolveWindows', [$in, $row['session_open_time'], $row['session_close_time']]);


        // Be tolerant to ordering/format differences, but ensure tokens are resolved.
        $this->assertCount(3, $out);
        foreach ($out as $w) {
            $this->assertStringNotContainsString('open', $w);
            $this->assertStringNotContainsString('close', $w);
        }

        // Order may vary; assert membership.
        $this->assertTrue(
            (bool) array_filter($out, fn($w) => str_starts_with($w, $row['session_open_time'] . '-') && str_ends_with($w, '-09:40')),
            'Expected a window resolved from open-09:40'
        );
        $this->assertTrue(
            (bool) array_filter($out, fn($w) => str_starts_with($w, '14:00-') && str_ends_with($w, '-' . $row['session_close_time'])),
            'Expected a window resolved from 14:00-close'
        );
        $this->assertContains('09:45-10:00', $out);
    }

    public function testSubtractBreaksSplitsOverlappingWindows(): void
    {
        $row = [
            'trade_date' => '2026-01-27',
            'is_trading_day' => 1,
            'session_open_time' => '09:00',
            'session_close_time' => '15:50',
            'breaks_json' => json_encode(['12:00-13:00'], JSON_UNESCAPED_SLASHES),
        ];

        $engine = $this->buildEngineWithCalendarRow($row);

        $windows = ['09:00-15:50'];
        $breaks = ['12:00-13:00'];
        $out = $this->callPrivate($engine, 'subtractBreaks', [$windows, $breaks]);

        $this->assertSame(['09:00-12:00', '13:00-15:50'], $out);
    }

    public function testSessionForDateUsesCalendarOverridesWithBreaksJson(): void
    {
        $row = [
            'trade_date' => '2026-01-27',
            'is_trading_day' => 1,
            'session_open_time' => '09:15',
            'session_close_time' => '15:30',
            'breaks_json' => json_encode(['12:00-12:45'], JSON_UNESCAPED_SLASHES),
        ];

        $engine = $this->buildEngineWithCalendarRow($row);

        // WatchlistEngine uses private sessionForDate(date)
        $sess = $this->callPrivate($engine, 'sessionForDate', ['2026-01-27']);

        $this->assertIsArray($sess);
        $open = $sess['open_time'] ?? null;
        $close = $sess['close_time'] ?? null;
        $breaks = $sess['breaks'] ?? null;

        $this->assertNotNull($open);
        $this->assertNotNull($close);
        $this->assertSame('09:15', (string)$open);
        $this->assertSame('15:30', (string)$close);
        $this->assertSame(['12:00-12:45'], $breaks);
    }
}
