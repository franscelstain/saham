<?php

namespace App\Support;

interface Clock
{
    /** Return current time in RFC3339 string (Asia/Jakarta or app tz). */
    public function nowRfc3339(): string;
}
