<?php

declare(strict_types=1);

namespace Cpts\Exception;

class PackageNotFoundException extends CptsException
{
    public function __construct(
        public readonly string $packageName,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        $message = $message ?: "Package not found: {$packageName}";
        parent::__construct($message, 0, $previous);
    }
}
