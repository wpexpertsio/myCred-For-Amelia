# GitHub Actions CI/CD for myCred Amelia WordPress Plugin

Set up GitHub Actions workflows for the myCred Amelia WordPress plugin to:
1. **On Pull Request** — Run WordPress Coding Standards checks (PHPCS/WPCS) and detect critical changes to the main plugin file (filename, version, plugin title, etc.)
2. **On Tag Creation** — Deploy the plugin to WordPress.org SVN using the [10up/action-wordpress-plugin-deploy](https://github.com/10up/action-wordpress-plugin-deploy) action.

## User Review Required

> [!IMPORTANT]
> **GitHub Repository Secrets Required**: You need to add these secrets in your GitHub repo settings (`Settings → Secrets and variables → Actions`):
> - `SVN_USERNAME` — Your WordPress.org SVN username
> - `SVN_PASSWORD` — Your WordPress.org SVN password

> [!WARNING]
> **Version Mismatch Detected**: Your `readme.txt` has `Stable tag: 1.1.9` but `mycred-amelia.php` has `Version: 2.1.0`. These should match before deploying. The PR check workflow will flag this automatically.

> [!IMPORTANT]
> **WordPress.org Assets**: The 10up action looks for a `.wordpress-org` directory for banners/icons/screenshots. We'll create a `.wordpress-org/` directory to hold WordPress.org-specific assets (banner, icon) separately from plugin code assets.

## Open Questions

> [!IMPORTANT]
> 1. **Which branch is your main/default branch?** (e.g., `main`, `master`) — This determines the PR target for checks.
> 2. **Do you want screenshots pushed to WordPress.org SVN?** If so, we'll move or copy the relevant screenshots into `.wordpress-org/`.

## Proposed Changes

### Workflow 1: PR Quality Checks

---

#### [NEW] .github/workflows/pr-checks.yml

Runs on every pull request. Contains 2 jobs:

**Job 1: `phpcs` — WordPress Coding Standards**
- Checks out the code
- Sets up PHP 8.0+ with Composer
- Installs `wp-coding-standards/wpcs` (latest) and `phpcompatibility/phpcompatibility-wp`
- Configures PHPCS with WordPress coding standards
- Runs PHPCS on all PHP files with `WordPress` standard
- Reports errors/warnings as GitHub annotations

**Job 2: `plugin-metadata-check` — Critical Change Detection**
- Custom bash script that compares the PR branch against the base branch for:
  - **Main plugin filename** (`mycred-amelia.php`) — MUST NOT be renamed
  - **Plugin Name** header — warns if changed
  - **Version** in `mycred-amelia.php` header — notifies if changed
  - **Version match** between `mycred-amelia.php` header and `readme.txt` `Stable tag`
  - **Plugin URI**, **Author**, **Text Domain** — warns if changed
  - **Requires at least** / **Tested up to** / **Requires PHP** — notifies if changed
- Posts a PR comment summary with all detected changes using `actions/github-script`

---

### Workflow 2: Deploy to WordPress.org SVN

---

#### [NEW] .github/workflows/deploy.yml

Runs when a **tag is pushed** (e.g., `v2.1.0` or `2.1.0`).

- Checks out the full code
- Uses `10up/action-wordpress-plugin-deploy@stable` to push to WordPress.org SVN
- Passes `SVN_USERNAME` and `SVN_PASSWORD` from GitHub secrets
- Sets `SLUG: mycred-amelia`
- Sets `ASSETS_DIR: .wordpress-org`
- Generates a zip artifact for the GitHub release

---

### Supporting Files

---

#### [NEW] .distignore

Files/directories to exclude from the WordPress.org SVN deployment:
```
/.git
/.github
/.distignore
/.gitignore
/.editorconfig
/.wordpress-org
/phpcs.xml.dist
/implementation_plan_git_release.md
```

#### [NEW] .wordpress-org/

Directory for WordPress.org SVN assets (banners, icons). Initially contains a `.gitkeep`. You can add:
- `banner-772x250.png` / `banner-1544x500.png`
- `icon-128x128.png` / `icon-256x256.png`

#### [NEW] phpcs.xml.dist

PHPCS configuration file for the project:
- Ruleset: `WordPress` (includes WordPress-Core, WordPress-Docs, WordPress-Extra)
- Text domain: `mycred-amelia`
- Minimum WP version: `4.8`
- Excludes: `node_modules/`, `vendor/`, `assets/build/`, `assets/libs/`

---

### Summary of All New Files

| File | Purpose |
|------|---------|
| `.github/workflows/pr-checks.yml` | PHPCS + metadata change detection on PRs |
| `.github/workflows/deploy.yml` | Deploy to WordPress.org SVN on tag push |
| `.distignore` | Exclude dev files from SVN deployment |
| `.wordpress-org/.gitkeep` | WordPress.org SVN assets directory |
| `phpcs.xml.dist` | PHPCS configuration for WordPress standards |

## Verification Plan

### Manual Verification
1. Create a test branch, push a PR to trigger `pr-checks.yml` → verify PHPCS runs, metadata checks post a comment
2. Create a test tag to trigger `deploy.yml` → use `dry-run: true` first to verify the SVN deployment process without actually committing
3. Verify that `.distignore` correctly excludes dev-only files from the SVN package
