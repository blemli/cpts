<?php

declare(strict_types=1);

namespace Cpts\Event;

use Cpts\Score\ScoreResult;

class ValidationResult
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_FAIL = 'FAIL';
    public const STATUS_TRUSTED = 'TRUSTED';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_SKIPPED = 'SKIPPED';

    public function __construct(
        public readonly string $packageName,
        public readonly string $status,
        public readonly ?ScoreResult $scoreResult = null,
        public readonly ?string $error = null,
    ) {
    }

    public function isPassing(): bool
    {
        return in_array($this->status, [self::STATUS_PASS, self::STATUS_TRUSTED, self::STATUS_SKIPPED], true);
    }

    public function getScore(): ?float
    {
        return $this->scoreResult?->getScore();
    }
}
