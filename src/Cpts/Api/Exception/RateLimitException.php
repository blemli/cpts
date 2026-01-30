<?php

declare(strict_types=1);

namespace Cpts\Api\Exception;

class RateLimitException extends ApiException
{
    public function __construct(
        public readonly int $remaining,
        public readonly \DateTimeInterface $resetsAt,
        string $message = 'API rate limit exceeded',
    ) {
        parent::__construct($message);
    }

    public function getSecondsUntilReset(): int
    {
        return max(0, $this->resetsAt->getTimestamp() - time());
    }
}
