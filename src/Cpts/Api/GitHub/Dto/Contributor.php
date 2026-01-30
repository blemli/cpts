<?php

declare(strict_types=1);

namespace Cpts\Api\GitHub\Dto;

class Contributor
{
    public function __construct(
        public readonly string $login,
        public readonly int $contributions,
        public readonly string $type,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            login: $data['login'] ?? '',
            contributions: (int) ($data['contributions'] ?? 0),
            type: $data['type'] ?? 'User',
        );
    }

    public function isBot(): bool
    {
        return $this->type === 'Bot'
            || str_ends_with($this->login, '[bot]')
            || str_ends_with($this->login, '-bot');
    }
}
