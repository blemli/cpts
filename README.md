# CPTS - Composer Package Trust Score

A Composer plugin that scores dependencies (0-100) based on activity, maintainer engagement, and AI-workflow risk.

## Installation

```bash
composer require --dev blemli/cpts
```

## Metrics

| | Metric | Weight | What it measures |
|--|--------|--------|------------------|
| 🤖 | Robotic | 3 | AI-workflow risk (lower is better) |
| 🗓️ | Activity | 4 | Commit recency + frequency |
| 👨‍💻 | Committers | 5 | Bus factor (unique contributors) |
| ⭐ | Stars | 1 | GitHub attention signal |
| 📦 | Dependents | 2 | Production adoption |
| 🕰️ | Repo Age | 2 | Maturity |
| 🧹 | Hygiene | 1 | Tests, TODOs, stubs |
| 🎫 | Issues | 4 | Responsiveness to issues/PRs |
| 🔗 | Dependencies | 3 | Direct dep count (fewer = better) |

Colors: 🟢 ≥80% · 🟡 ≥60% · 🟠 ≥40% · 🔴 ≥20% · ⚫ <20% or N/A

**Trust Bonus** (±10 points): verified org, signed commits, maintainer reputation, bus factor penalties.

## Usage

```json
{
  "extra": {
    "cpts": {
      "min_cpts": 20,
      "trusted_packages": ["symfony/*", "laravel/*"]
    }
  }
}
```

```bash
composer cpts:check                    # Check all dependencies (advisory)
composer cpts:check --fail-under=30    # Fail in CI if any score < 30
composer cpts:score monolog/monolog    # Score single package
composer cpts:trust symfony/*          # Trust all symfony packages
composer cpts:trust vendor/package     # Trust specific package
composer cpts:trust symfony/* --remove # Remove from trusted
CPTS_DISABLE=1 composer install        # Bypass checks
```

## Configuration

Default config is added to `composer.json` automatically on install:

```json
{
  "extra": {
    "cpts": {
      "min_cpts": 20,
      "trusted_packages": []
    }
  }
}
```

| Option | Default | Description |
|--------|---------|-------------|
| `min_cpts` | 20 | Minimum score threshold (warns below) |
| `trusted_packages` | [] | Patterns to skip (supports wildcards) |
| `weights` | {} | Custom metric weights (see below) |

### Custom Weights

Override default weights in `extra.cpts.weights`:

```json
{
  "extra": {
    "cpts": {
      "weights": {
        "repo_age": 4,
        "activity": 6,
        "airs": 0
      }
    }
  }
}
```

Metric names: `robotic`, `activity`, `committers`, `stars`, `dependents`, `repo_age`, `hygiene`, `issue_behaviour`, `dependency_count`

### GitHub Token

**Required for projects with 30+ dependencies.** Without a token, GitHub rate limits to 60 requests/hour (vs 5000 with token).

```bash
# .env (recommended)
GITHUB_TOKEN=ghp_xxxxxxxxxxxx

# or export
export GITHUB_TOKEN=ghp_xxxxxxxxxxxx
```

Create a token at https://github.com/settings/tokens (no scopes needed for public repos).
