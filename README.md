# 🔭 TelescopeAI AutoDebug

**AI-powered auto-debug & auto-fix for Laravel.** Monitors Telescope exceptions, analyzes them with AI (OpenAI/Claude), generates code fixes, and creates GitHub PRs automatically.

[![Laravel](https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-FF2D20?logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

---

## ✨ Features

- 🔍 **Automatic Exception Detection** — Polls Telescope for new exceptions
- 🧠 **AI-Powered Analysis** — Root cause analysis via OpenAI or Claude
- 🛠️ **Auto-Fix Generation** — AI suggests code patches with search/replace
- 🚀 **GitHub PR Creation** — Pushes fixes as PRs with detailed descriptions
- 📊 **Web Dashboard** — View exceptions, AI analysis, confidence scores, and PR status
- 🔔 **Notifications** — Slack, email, and database logging
- 🔒 **Safety Guards** — Protected paths, deduplication, rate limiting, confidence thresholds
- 🏜️ **Dry Run Mode** — Preview AI analysis without creating PRs

---

## 📦 Installation

### 1. Require the package

```bash
composer require telescopeai/autodebug
```

> The package uses Laravel auto-discovery, so the service provider registers automatically.

### 2. Run the install command

```bash
php artisan autodebug:install
```

This will:
- Publish the configuration file
- Run the database migration
- Check that Telescope is installed
- Show you the required `.env` variables

### 3. Configure your `.env`

```env
# Required
AUTODEBUG_ENABLED=true
AUTODEBUG_AI_PROVIDER=openai
AUTODEBUG_OPENAI_API_KEY=sk-your-key-here

# GitHub (for automatic PR creation)
AUTODEBUG_GITHUB_ENABLED=true
AUTODEBUG_GITHUB_TOKEN=ghp_your-github-token
AUTODEBUG_GITHUB_OWNER=your-org-or-username
AUTODEBUG_GITHUB_REPO=your-repo-name
AUTODEBUG_GITHUB_BASE_BRANCH=main
```

### 4. Schedule the analyzer

**Laravel 11+** (`routes/console.php`):

```php
Schedule::command('autodebug:analyze')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

**Laravel 10** (`app/Console/Kernel.php`):

```php
$schedule->command('autodebug:analyze')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

---

## 🚀 Usage

### CLI

```bash
# Run exception analysis
php artisan autodebug:analyze

# Dry run — analyze without creating PRs
php artisan autodebug:analyze --dry-run

# Process more exceptions at once
php artisan autodebug:analyze --batch=10

# Bypass rate limiting
php artisan autodebug:analyze --force
```

### Dashboard

Navigate to `/auto-debug` in your browser. The dashboard shows:
- Exception stats (total, pending, analyzed, PRs created)
- Filterable exception list with status badges
- Detailed view with AI analysis, confidence scores, and side-by-side patches
- Actions: re-analyze, ignore, view PR

### Access Control

By default, the dashboard is accessible to everyone in local/dev environments. In production, the `Authorize` middleware checks for:
1. A `canAccessAutoDebug()` method on the user model
2. An `admin` role (if using Spatie Permission)
3. Falls back to requiring authentication

To customize, add this method to your `User` model:

```php
public function canAccessAutoDebug(): bool
{
    return $this->is_admin; // your logic here
}
```

---

## ⚙️ Configuration

Publish the config to customize:

```bash
php artisan vendor:publish --tag=autodebug-config
```

Key options in `config/autodebug.php`:

| Option | Default | Description |
|--------|---------|-------------|
| `ai.provider` | `openai` | AI provider (`openai` or `anthropic`) |
| `analysis.min_confidence_for_pr` | `75` | Minimum AI confidence to create a PR |
| `analysis.max_calls_per_hour` | `10` | Rate limit for AI API calls |
| `analysis.batch_size` | `5` | Exceptions per analysis run |
| `analysis.dry_run` | `false` | Global dry run mode |
| `github.enabled` | `true` | Enable/disable GitHub PR creation |
| `notifications.channels` | `database` | Notification channels (slack, mail, database) |

---

## 📐 Publishing Assets

```bash
# Publish everything
php artisan vendor:publish --tag=autodebug

# Publish only config
php artisan vendor:publish --tag=autodebug-config

# Publish only migrations
php artisan vendor:publish --tag=autodebug-migrations

# Publish views for customization
php artisan vendor:publish --tag=autodebug-views
```

---

## 🔒 Safety Features

| Feature | Description |
|---------|-------------|
| **Protected Paths** | Never modifies migrations, `.env`, config, vendor, storage |
| **Ignored Exceptions** | Skips validation, auth, 404, and model-not-found |
| **Deduplication** | Same exception (class + file + line) analyzed only once |
| **Rate Limiting** | Configurable AI API call limits |
| **Confidence Threshold** | PRs only for high-confidence fixes |
| **Patch Validation** | Verifies search strings exist before committing |
| **No Local Git** | All changes via GitHub API |

---

## 🧪 Prerequisites

- **PHP 8.1+**
- **Laravel 10, 11, or 12**
- **Laravel Telescope** (`composer require laravel/telescope`)
- **OpenAI or Anthropic API key**
- **GitHub Personal Access Token** (with `repo` scope)

---

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.
