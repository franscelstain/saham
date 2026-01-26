<?php

namespace Tests\Unit\Compute;

use App\Trade\Compute\Classifiers\VolumeLabelClassifier;
use PHPUnit\Framework\TestCase;

class VolumeLabelClassifierTest extends TestCase
{
    public function testDefaultThresholdMapping(): void
    {
        $c = new VolumeLabelClassifier([0.4, 0.7, 1.0, 1.5, 2.0, 3.0, 4.0]);

        $this->assertSame(1, $c->classify(null));
        $this->assertSame(1, $c->classify(0.0));

        $this->assertSame(2, $c->classify(0.3));
        $this->assertSame(3, $c->classify(0.6));
        $this->assertSame(4, $c->classify(0.9));
        $this->assertSame(5, $c->classify(1.2));
        $this->assertSame(6, $c->classify(1.6));
        $this->assertSame(7, $c->classify(3.5));
        $this->assertSame(8, $c->classify(4.0));
    }
}
