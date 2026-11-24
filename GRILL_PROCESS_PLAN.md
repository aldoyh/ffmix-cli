# ffMix CLI — Grill Process Implementation Plan

This plan addresses differences and issues identified during the grill process (review of PRD requirements vs. actual implementation), including 2 failing tests and 2 implementation gaps.

---

## Baseline State

- **Branch**: `main` (up to date with `origin/main`)
- **Test suite**: 32 tests, 30 passed, **2 failed**, 339 assertions
- **Uncommitted**: `README.md` (browser port 8000 → 8077)
- **Untracked**: `docs/FfMix-CLI-PRD-V3.pdf`

---

## Issues Identified

| # | Issue | Source | Severity | PRD Ref |
|---|-------|--------|----------|---------|
| 1 | TUI banner missing text `Show Control, Watchdog & HTML DSK` | Failing test `FfmixBroadcastFeaturesTest::it renders the multi-line ffMix ASCII banner...` | Critical (test failure) | §3 Broadcast |
| 2 | `DownstreamKeyService::listTemplates()` returns 4 templates, test expects 1 starter template | Failing test `FfmixBroadcastFeaturesTest::it discovers HTML Downstream Key templates...` | Critical (test failure) | §5.2 Template Management |
| 3 | `ffmix:stream` CLI lacks `qualities` action; PRD documents `ffmix:stream qualities --stream-url=...` | PRD §7.2 CLI Commands vs `FfmixStreamCommand` (uses `hls` action only) | Medium | §7.2 |
| 4 | `README.md` browser port 8000→8077 change uncommitted | `git status` / `git diff README.md` | Low | README Quick Start |

---

## Phase 1 — Fix Failing Tests (Critical)

### Issue 1: Add missing banner text `Show Control, Watchdog & HTML DSK`

**Root cause**: `App\Tui\DashboardRenderer::renderShowTab()` (Tab 9 — Show/Mixer/DSK) does not render the string `Show Control, Watchdog & HTML DSK` that the test asserts must be present in the full rendered output.

**Fix**: Add the line `Show Control, Watchdog & HTML DSK` to the Show tab's left column, immediately after the active Show name line in `renderShowTab()`.

**File**: `app/Tui/DashboardRenderer.php`
- In `renderShowTab()`, after `$leftLines[] = $theme->accent('SHOW').': ...`, insert a muted line containing exactly `Show Control, Watchdog & HTML DSK`.

**Verification**: Run `php artisan test --filter="it renders the multi-line ffMix ASCII banner"` → must pass.

---

### Issue 2: DSK template count mismatch (4 vs 1)

**Root cause**: `App\Services\Ffmix\DownstreamKeyService::listTemplates()` calls `ensureSampleTemplates($dir)` which copies **all 4** sample templates from `resources/dsk/` into the target folder, then lists all that exist. The test creates an empty temp dir and expects only the starter template (`sample-lower-third.html`) to be present/counted.

**PRD intent**: §5.2 states "`ensureSampleTemplate()` copies starter template if none exist" (singular starter template), and §3.3 states "Ships with a starter template (`resources/dsk/sample-lower-third.html`)" (singular).

**Fix**: Change `ensureSampleTemplates()` (and `listTemplates()` behavior) so that when a folder is empty, only the **starter template** (`sample-lower-third.html`) is copied/ensured, not all 4. The other 3 sample files remain in `resources/dsk/` but are not auto-copied unless explicitly requested.

**Files**:
- `app/Services/Ffmix/DownstreamKeyService.php` — `ensureSampleTemplates()`: only copy `sample-lower-third.html` as the starter; keep the other samples available but not auto-deployed.

**Verification**: Run `php artisan test --filter="it discovers HTML Downstream Key templates"` → must pass (expects 1 template).

> ⚠️ Decision: If the product intends to ship all 4 templates by default, the test expectation should be updated to 4 instead. That requires approval (do not modify tests without it). The plan above aligns implementation with the PRD's singular "starter template" language and the existing test.

---

## Phase 2 — Align CLI with PRD

### Issue 3: Add `qualities` action to `ffmix:stream`

**Root cause**: PRD §7.2 documents `php artisan ffmix:stream qualities --stream-url="<URL>"` to probe and display available qualities. `FfmixStreamCommand` only supports `record|hls|list|info|jobs|monitor` — the `hls` action already probes qualities but there is no `qualities` alias.

**Fix**: Add `qualities` as an accepted action that delegates to `handleHls()` (which already probes and displays the quality ladder).

**File**: `app/Console/Commands/FfmixStreamCommand.php`
- Update the `$signature` action description to include `qualities`.
- Add `if ($action === 'qualities') { return $this->handleHls($hls); }` before the unknown-action error.

**Verification**: `php artisan ffmix:stream qualities --stream-url="<HLS_URL>"` should display quality variants (requires network/ffmpeg).

---

## Phase 3 — Housekeeping

### Issue 4: Commit README browser port fix

**Root cause**: `README.md` has an uncommitted change updating the browser port from `8000` to `8077` (matching actual implementation).

**Fix**: `git add README.md && git commit -m "docs: update browser port to 8077 in README quick start"`.

---

## Phase 4 — Verification

1. Run the full test suite: `composer test` (or `vendor/bin/pest`) — expect 32/32 passing.
2. Run Pint formatting: `vendor/bin/pint --dirty --format agent`.
3. Run PHPStan: `vendor/bin/phpstan analyse` (level 7).
4. Confirm `git status` is clean (README committed).

---

## Estimated Effort

- Phase 1 (Issues 1–2): ~1 hour (2 small code fixes + test validation)
- Phase 2 (Issue 3): ~30 minutes (1 command edit)
- Phase 3 (Issue 4): ~10 minutes (commit)
- Phase 4 (Verification): ~30 minutes (test suite + lint + static analysis)

**Total**: ~2.5 hours

---

## Notes / Assumptions

- No new dependencies are required.
- No database migrations are needed.
- Tests are not modified per project rules (`Do not delete tests or test files without approval`).
- The `qualities` CLI alias reuses the existing `handleHls()` logic; no new service methods needed.
