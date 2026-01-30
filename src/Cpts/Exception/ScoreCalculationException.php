<?php

declare(strict_types=1);

namespace Cpts\Exception;

class ScoreCalculationException extends CptsException
{
    public function __construct(
        string $message,
        public readonly string $packageName,
        public readonly ?string $metricName = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
