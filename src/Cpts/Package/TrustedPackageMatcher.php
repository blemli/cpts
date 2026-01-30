<?php

declare(strict_types=1);

namespace Cpts\Package;

/**
 * Matches package names against trusted package patterns.
 * Supports wildcards: "vendor/*", "vendor/prefix-*"
 */
class TrustedPackageMatcher
{
    /** @var string[] */
    private array $patterns;

    /** @var string[] */
    private array $regexPatterns;

    /**
     * @param string[] $patterns
     */
    public function __construct(array $patterns)
    {
        $this->patterns = $patterns;
        $this->regexPatterns = array_map([$this, 'patternToRegex'], $patterns);
    }

    /**
     * Check if a package name matches any trusted pattern.
     */
    public function matches(string $packageName): bool
    {
        // Exact match first
        if (in_array($packageName, $this->patterns, true)) {
            return true;
        }

        // Pattern match
        foreach ($this->regexPatterns as $regex) {
            if (preg_match($regex, $packageName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a glob-like pattern to a regex.
     */
    private function patternToRegex(string $pattern): string
    {
        // Escape regex special chars except *
        $escaped = preg_quote($pattern, '#');

        // Convert * to regex .*
        $regex = str_replace('\*', '.*', $escaped);

        return '#^' . $regex . '$#';
    }

    /**
     * @return string[]
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }
}
