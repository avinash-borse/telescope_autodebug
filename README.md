# 🔭 TelescopeAI AutoDebug

**AI-powered auto-debug & auto-fix for Laravel.** Monitors Telescope exceptions, analyzes them with multiple AI providers (OpenAI, Claude, Gemini, or local Ollama), generates code fixes, and creates GitHub PRs automatically.

[![Laravel](https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-FF2D20?logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

---

## ✨ Features

- 🔍 **Automatic Exception Detection** — Polls Telescope for new exceptions
- 🧠 **Multi-AI Engine** — Supports **OpenAI**, **Anthropic (Claude)**, **Google Gemini**, and **Ollama** (Local/Free)
- 🛠️ **Auto-Fix Generation** — AI suggests code patches with search/replace
- 🚀 **GitHub PR Creation** — Pushes fixes as PRs with detailed descriptions
- �️ **Terminal Diff Preview** — View suggested changes directly in your console
- �📊 **Web Dashboard** — View exceptions, AI analysis, confidence scores, and PR status
- 🔔 **Notifications** — Slack, email, and database logging
- 🔒 **Safety Guards** — Protected paths, deduplication, rate limiting, confidence thresholds

---

## 📦 Installation

### 1. Require the package

```bash
composer require telescopeai/autodebug
```

### 2. Run the install command

```bash
php artisan autodebug:install
```

### 3. Configure your `.env`

#### Option A: Local Ollama (Free & Private)
Best for internal development. No API keys required.
```env
AUTODEBUG_AI_PROVIDER=ollama
AUTODEBUG_OLLAMA_BASE_URL=http://localhost:11434
AUTODEBUG_OLLAMA_MODEL=deepseek-coder:6.7b
```

#### Option B: Google Gemini
Highly capable with generous free tiers.
```env
AUTODEBUG_AI_PROVIDER=google
AUTODEBUG_GOOGLE_API_KEY=your-gemini-key
AUTODEBUG_GOOGLE_MODEL=gemini-2.0-flash
```

#### Option C: OpenAI / Anthropic (Claude)
Professional grade models.
```env
# For OpenAI
AUTODEBUG_AI_PROVIDER=openai
AUTODEBUG_OPENAI_API_KEY=sk-your-key-here

# For Anthropic
AUTODEBUG_AI_PROVIDER=anthropic
AUTODEBUG_ANTHROPIC_API_KEY=sk-ant-your-key-here
```

### 4. GitHub Configuration
Required only if you want automatic PR creation.
```env
AUTODEBUG_GITHUB_ENABLED=true
AUTODEBUG_GITHUB_TOKEN=ghp_your-github-token
AUTODEBUG_GITHUB_OWNER=your-org-or-username
AUTODEBUG_GITHUB_REPO=your-repo-name
```

---

## 🚀 Usage

### CLI Commands

```bash
# Run analysis (Dry run doesn't create PRs)
php artisan autodebug:analyze --dry-run

# 🔥 See the file changes in terminal
php artisan autodebug:analyze --dry-run --diff

# Force analysis even if recently analyzed
php artisan autodebug:analyze --force
```

---

## ⚙️ Configuration

| Option | Default | Description |
|--------|---------|-------------|
| `ai.provider` | `openai` | `openai`, `anthropic`, `google`, or `ollama` |
| `analysis.min_confidence_for_pr` | `75` | Minimum AI confidence to create a PR |
| `analysis.max_calls_per_hour` | `10` | Rate limit for AI API calls |
| `analysis.dry_run` | `false` | Global dry run mode |
| `safety.protected_paths` | `[...]` | Files the AI is never allowed to touch |

---

## 🧪 Prerequisites

- **PHP 8.1+**
- **Laravel 10, 11, or 12**
- **Laravel Telescope**
- **GitHub Personal Access Token** (for PRs)

---

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.
