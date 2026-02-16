<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * CandidateReasonDto
 *
 * LOCKED (docs/watchlist/1.contract.md & 3.preopen.md).
 * - code: stable machine code
 * - message: 1 kalimat user-facing
 * - severity_level: INFO|WARN|SOFT_BLOCK|HARD_EXCLUDE
 */
class CandidateReasonDto
{
    /** @var string */
    public $code;
    /** @var string */
    public $message;
    /** @var string */
    public $severityLevel;

    public function __construct($code, $message, $severityLevel = 'INFO')
    {
        $this->code = (string)$code;
        $this->message = (string)$message;
        $lvl = strtoupper(trim((string)$severityLevel));
        if ($lvl === '') $lvl = 'INFO';

        // Normalize legacy values
        if ($lvl === 'ERROR' || $lvl === 'BLOCK') $lvl = 'SOFT_BLOCK';
        if (!in_array($lvl, ['INFO','WARN','SOFT_BLOCK','HARD_EXCLUDE'], true)) {
            $lvl = 'INFO';
        }
        $this->severityLevel = $lvl;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'severity_level' => $this->severityLevel,
        ];
    }

    /**
     * Accepts either candidate schema (severity_level) or legacy schema (severity).
     *
     * @param array<string,mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $code = (string)($a['code'] ?? '');
        $msg = (string)($a['message'] ?? '');
        $lvl = null;
        if (isset($a['severity_level'])) $lvl = (string)$a['severity_level'];
        elseif (isset($a['severity'])) {
            $sev = strtoupper(trim((string)$a['severity']));
            if ($sev === 'WARN') $lvl = 'WARN';
            elseif ($sev === 'INFO') $lvl = 'INFO';
            else $lvl = 'SOFT_BLOCK';
        }
        return new self($code, $msg, $lvl ?: 'INFO');
    }
}
