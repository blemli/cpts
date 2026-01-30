<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Package\PackageInfo;
use Cpts\Score\MetricResult;

/**
 * AIRS v2: AI-Workflow Risk Score
 *
 * Detects AI-generated/AI-assisted codebases through heuristics:
 * - 50% Hard artifacts (AI tool config files)
 * - 20% README fingerprint (emoji headings, polished structure)
 * - 30% Git patterns (long commits, batch commits, generic messages)
 *
 * Score 0-100 (higher = more AI signals)
 * Normalized: 1 - (airs/100) so lower AI signals = higher CPTS
 */
class AirsMetric extends AbstractMetric
{
    // Hard artifact file patterns (50% weight)
    private const AI_ARTIFACTS = [
        // Claude
        'claude.md',
        'CLAUDE.md',
        '.claude',
        // GPT/OpenAI
        'gpt.md',
        'GPT.md',
        // Generic AI prompts
        'prompt.md',
        'PROMPT.md',
        'system.md',
        'SYSTEM.md',
        // Cursor
        '.cursorrules',
        '.cursor',
        // Continue.dev
        '.continue',
        // GitHub Copilot
        '.github/copilot-instructions.md',
        // Generic AI directories
        '.prompts',
        '.ai',
        '.llm',
    ];

    // README emoji patterns (20% weight)
    private const EMOJI_HEADING_PATTERN = '/^##\s+[\x{1F300}-\x{1F9FF}]/mu';

    // Common AI-polished section titles
    private const POLISHED_SECTIONS = [
        'features',
        'installation',
        'usage',
        'configuration',
        'contributing',
        'license',
        'getting started',
        'quick start',
        'requirements',
    ];

    private const WEIGHT_ARTIFACTS = 0.50;
    private const WEIGHT_README = 0.20;
    private const WEIGHT_GIT = 0.30;

    public function getName(): string
    {
        return 'airs';
    }

    public function getDescription(): string
    {
        return 'AI-workflow risk score (lower is better)';
    }

    public function getDefaultWeight(): float
    {
        return 3.0;
    }

    public function getEmoji(): string
    {
        return '🤖';
    }

    public function isHigherBetter(): bool
    {
        return false; // Lower AIRS = better
    }

    public function isApplicable(PackageInfo $package): bool
    {
        return $package->hasGitHubData();
    }

    public function calculate(PackageInfo $package): MetricResult
    {
        $artifactScore = $this->calculateArtifactScore($package);
        $readmeScore = $this->calculateReadmeScore($package);
        $gitScore = $this->calculateGitPatternScore($package);

        // Weighted AIRS score (0-100)
        $airsScore = 100 * (
            (self::WEIGHT_ARTIFACTS * $artifactScore) +
            (self::WEIGHT_README * $readmeScore) +
            (self::WEIGHT_GIT * $gitScore)
        );

        // Normalize: 1 - (airs/100) so low AI signals = high score
        $normalized = 1 - ($airsScore / 100);

        return $this->result($normalized, [
            'airs_score' => round($airsScore, 2),
            'artifact_score' => round($artifactScore, 3),
            'readme_score' => round($readmeScore, 3),
            'git_score' => round($gitScore, 3),
            'detected_artifacts' => $package->getDetectedAiArtifacts(),
        ]);
    }

    /**
     * Calculate artifact score (0-1).
     * More AI config files = higher score.
     */
    private function calculateArtifactScore(PackageInfo $package): float
    {
        $detected = $package->getDetectedAiArtifacts();
        $count = count($detected);

        if ($count === 0) {
            return 0.0;
        }

        // 1 artifact = 0.5, 2 = 0.75, 3+ = 1.0
        return min(1.0, 0.25 + ($count * 0.25));
    }

    /**
     * Calculate README fingerprint score (0-1).
     * Emoji headings and over-polished structure.
     */
    private function calculateReadmeScore(PackageInfo $package): float
    {
        $readme = $package->getReadmeContent();
        if ($readme === null || $readme === '') {
            return 0.0;
        }

        $score = 0.0;

        // Check for emoji section headings
        $emojiHeadings = preg_match_all(self::EMOJI_HEADING_PATTERN, $readme);
        if ($emojiHeadings >= 3) {
            $score += 0.6;
        } elseif ($emojiHeadings >= 1) {
            $score += 0.3;
        }

        // Check for suspiciously symmetric sections
        $sectionCount = 0;
        $readmeLower = strtolower($readme);

        foreach (self::POLISHED_SECTIONS as $section) {
            if (str_contains($readmeLower, "## {$section}") ||
                str_contains($readmeLower, "# {$section}")) {
                $sectionCount++;
            }
        }

        if ($sectionCount >= 6) {
            $score += 0.4;
        } elseif ($sectionCount >= 4) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    /**
     * Calculate git pattern score (0-1).
     * Long commit messages, batch commits, generic messages.
     */
    private function calculateGitPatternScore(PackageInfo $package): float
    {
        $commits = $package->getRecentCommits();
        if (empty($commits)) {
            return 0.0;
        }

        $score = 0.0;
        $total = count($commits);

        // Count long commit messages (>120 chars)
        $longMessages = 0;
        $genericMessages = 0;

        foreach ($commits as $commit) {
            if ($commit->getMessageLength() > 120) {
                $longMessages++;
            }
            if ($commit->isGenericMessage()) {
                $genericMessages++;
            }
        }

        // Frequent long messages (AI tends to be verbose)
        $longRatio = $longMessages / $total;
        if ($longRatio >= 0.5) {
            $score += 0.5;
        } elseif ($longRatio >= 0.25) {
            $score += 0.25;
        }

        // Frequent generic messages
        $genericRatio = $genericMessages / $total;
        if ($genericRatio >= 0.3) {
            $score += 0.5;
        } elseif ($genericRatio >= 0.15) {
            $score += 0.25;
        }

        return min(1.0, $score);
    }
}
