<?php

declare(strict_types=1);

namespace Cpts\Api\GitHub\Dto;

class FileContent
{
    public function __construct(
        public readonly string $path,
        public readonly string $name,
        public readonly string $type,
        public readonly int $size,
        public readonly ?string $content,
        public readonly ?string $encoding,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            path: $data['path'] ?? '',
            name: $data['name'] ?? '',
            type: $data['type'] ?? 'file',
            size: (int) ($data['size'] ?? 0),
            content: $data['content'] ?? null,
            encoding: $data['encoding'] ?? null,
        );
    }

    public function isDirectory(): bool
    {
        return $this->type === 'dir';
    }

    public function getDecodedContent(): ?string
    {
        if ($this->content === null) {
            return null;
        }

        if ($this->encoding === 'base64') {
            $decoded = base64_decode($this->content, true);

            return $decoded !== false ? $decoded : null;
        }

        return $this->content;
    }
}
