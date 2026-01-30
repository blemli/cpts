<?php

declare(strict_types=1);

namespace Cpts\Score;

use Cpts\Package\PackageInfo;

/**
 * Trust Bonus Calculator
 *
 * Additive bonuses/penalties clamped to [-1, +1]:
 * +0.5 maintainer has >=2 CPTS>70 repos
 * +0.3 verified org
 * +0.2 signed commits (>=50%)
 * -0.5 bus factor = 1
 * -0.5 abandoned pattern
 */
class TrustBonus
{
    private const BONUS_MAINTAINER_REPUTATION = 0.5;
    private const BONUS_VERIFIED_ORG = 0.3;
    private const BONUS_SIGNED_COMMITS = 0.2;
    private const PENALTY_BUS_FACTOR_ONE = -0.5;
    private const PENALTY_ABANDONED = -0.5;

    private const SIGNED_COMMIT_THRESHOLD = 0.5;
    private const ABANDONED_DAYS = 365;
    private const ABANDONED_OPEN_ISSUES = 10;

    public function calculate(PackageInfo $package): float
    {
        $bonus = 0.0;

        // Positive bonuses
        if ($this->hasMaintainerReputation($package)) {
            $bonus += self::BONUS_MAINTAINER_REPUTATION;
        }

        if ($this->isVerifiedOrganization($package)) {
            $bonus += self::BONUS_VERIFIED_ORG;
        }

        if ($this->hasSignedCommits($package)) {
            $bonus += self::BONUS_SIGNED_COMMITS;
        }

        // Negative penalties
        if ($this->hasBusFactorOne($package)) {
            $bonus += self::PENALTY_BUS_FACTOR_ONE;
        }

        if ($this->isAbandoned($package)) {
            $bonus += self::PENALTY_ABANDONED;
        }

        return $bonus;
    }

    /**
     * @return array<string, bool>
     */
    public function getBreakdown(PackageInfo $package): array
    {
        return [
            'maintainer_reputation' => $this->hasMaintainerReputation($package),
            'verified_org' => $this->isVerifiedOrganization($package),
            'signed_commits' => $this->hasSignedCommits($package),
            'bus_factor_one' => $this->hasBusFactorOne($package),
            'abandoned' => $this->isAbandoned($package),
        ];
    }

    private function hasMaintainerReputation(PackageInfo $package): bool
    {
        // Check if maintainer has >=2 packages with CPTS > 70
        // This requires cross-package scoring (cached/pre-computed)
        // For now, return false - this can be enhanced later
        return $package->getMaintainerReputationScore() >= 2;
    }

    private function isVerifiedOrganization(PackageInfo $package): bool
    {
        return $package->isVerifiedOrganization();
    }

    private function hasSignedCommits(PackageInfo $package): bool
    {
        $commits = $package->getRecentCommits();
        if (empty($commits)) {
            return false;
        }

        $signedCount = count(array_filter($commits, fn($c) => $c->isSigned));

        return ($signedCount / count($commits)) >= self::SIGNED_COMMIT_THRESHOLD;
    }

    private function hasBusFactorOne(PackageInfo $package): bool
    {
        return $package->getUniqueCommittersLast180Days() <= 1;
    }

    private function isAbandoned(PackageInfo $package): bool
    {
        // Abandoned pattern: no commits in 365+ days AND open issues > 10
        $daysSinceLastCommit = $package->getDaysSinceLastCommit();
        $openIssues = $package->getOpenIssueCount();

        return $daysSinceLastCommit > self::ABANDONED_DAYS
            && $openIssues > self::ABANDONED_OPEN_ISSUES;
    }
}
