<?php

declare(strict_types=1);

namespace Cpts\Metric;

use Cpts\Package\PackageInfo;
use Cpts\Score\MetricResult;

/**
 * Hygiene Metric
 *
 * Code quality signals:
 * - tests_norm = min(test_files / src_files / 0.5, 1)
 * - todo_norm = 1 - min((todo_count / loc) / 0.002, 1)
 * - stub_norm = 1 - min(stubs / 10, 1)
 *
 * hygiene_norm = 0.5*tests + 0.3*todo + 0.2*stub
 */
class HygieneMetric extends AbstractMetric
{
    private const TEST_RATIO_TARGET = 0.5;
    private const TODO_RATIO_THRESHOLD = 0.002;
    private const STUB_MAX = 10;

    private const WEIGHT_TESTS = 0.5;
    private const WEIGHT_TODO = 0.3;
    private const WEIGHT_STUB = 0.2;

    public function getName(): string
    {
        return 'hygiene';
    }

    public function getDescription(): string
    {
        return 'Code hygiene (tests, TODOs, stubs)';
    }

    public function getDefaultWeight(): float
    {
        return 1.0;
    }

    public function getEmoji(): string
    {
        return '🧹';
    }

    public function isApplicable(PackageInfo $package): bool
    {
        return $package->hasGitHubData();
    }

    public function calculate(PackageInfo $package): MetricResult
    {
        $testFiles = $package->getTestFileCount();
        $srcFiles = $package->getSourceFileCount();
        $todoCount = $package->getTodoCount();
        $loc = $package->getLinesOfCode();
        $stubCount = $package->getStubCount();

        // tests_norm = min(test_files/src_files/0.5, 1)
        $testRatio = $srcFiles > 0 ? $testFiles / $srcFiles : 0;
        $testsNorm = min($testRatio / self::TEST_RATIO_TARGET, 1.0);

        // todo_norm = 1 - min((todo/loc)/0.002, 1)
        $todoRatio = $loc > 0 ? $todoCount / $loc : 0;
        $todoNorm = 1 - min($todoRatio / self::TODO_RATIO_THRESHOLD, 1.0);

        // stub_norm = 1 - min(stubs/10, 1)
        $stubNorm = 1 - min($stubCount / self::STUB_MAX, 1.0);

        // hygiene_norm = 0.5*tests + 0.3*todo + 0.2*stub
        $normalized = (self::WEIGHT_TESTS * $testsNorm)
            + (self::WEIGHT_TODO * $todoNorm)
            + (self::WEIGHT_STUB * $stubNorm);

        return $this->result($normalized, [
            'test_files' => $testFiles,
            'src_files' => $srcFiles,
            'test_ratio' => round($testRatio, 3),
            'tests_norm' => round($testsNorm, 3),
            'todo_count' => $todoCount,
            'loc' => $loc,
            'todo_norm' => round($todoNorm, 3),
            'stub_count' => $stubCount,
            'stub_norm' => round($stubNorm, 3),
        ]);
    }
}
