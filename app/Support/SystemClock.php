<?php

namespace App\Support;

use Carbon\Carbon;

class SystemClock implements Clock
{
    public function nowRfc3339(): string
    {
        return Carbon::now()->toRfc3339String();
    }
}
