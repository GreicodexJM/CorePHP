# corephp audit

**Know before you ship.** A compile-time analyzer that scans your PHP code and reports every
**SAFE / SECURE / STABLE** risk — with file, line, severity, and the fix — so silent failures
never reach production. Pairs with the CorePHP runtime: the audit catches issues statically; the
runtime catches whatever slips through.

## Install & run

```bash
composer install                          # inside tools/audit (dev)
bin/corephp-audit path/to/your/src        # or: --json, --min-severity=high
```

Exit code is `0` when clean and `1` when findings exist (at/above `--min-severity`), so it drops
straight into CI:

```yaml
- run: vendor/bin/corephp-audit src --min-severity=high
```

## What it checks

| Pillar | Detects | Severity | Suggested fix |
|---|---|---|---|
| **SAFE** | `json_decode`, `file_get_contents`, `file_put_contents`, `preg_replace`, `base64_decode`, `curl_exec`, `stream_get_contents`, `simplexml_load_string`/`_file` (+ `intval`/`floatval`/`strtotime`/`getenv` at LOW) | MED / LOW | the matching `s_*()` shim, or handle the `false`/`null` return |
| **SECURE** | `eval`, `unserialize`, `exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open`, `assert('…')`, `create_function`, `extract` | HIGH | disable / JSON / typed DTO / real closures |
| **STABLE** | `exit`/`die` (kills the whole **worker**, not just the request), `global`, static class properties, function `static` vars, **global-runtime mutation** (`ini_set`, `putenv`, `setlocale`, `date_default_timezone_set`, …), `$GLOBALS` writes | HIGH / MED | return a response; set global config once at bootstrap; avoid cross-request state |

Two STABLE rules are specific to the persistent-worker model, where the process outlives the request:
- **`exit`/`die`** terminates the entire worker, not just the current request.
- **global-runtime mutation** (`ini_set`, `date_default_timezone_set`, `putenv`, …) changes process-global
  config that then **leaks into every later request** the worker handles.

## Example

```
CorePHP Audit — SAFE / SECURE / STABLE readiness

  SECURE 0   STABLE 1   SAFE 2

STABLE (1)
  demo/app/worker.php:40  MED   global state leaks across requests in a persistent worker.
      → Pass dependencies explicitly instead of reaching for globals.

SAFE (2)
  demo/app/app.php:43  MED   file_get_contents() returns false when the file is missing/unreadable.
      → Use s_file(), which throws instead of failing silently.
  demo/app/app.php:44  MED   json_decode() returns null on invalid JSON — the bug surfaces far from here.
      → Use s_json(), which throws instead of failing silently.

3 finding(s). Exit 1.
```

## Adopting on an existing codebase (baseline)

A large codebase will have findings on day one. Capture them as an accepted **baseline**, then let
the audit block only *new* issues from there on:

```bash
# 1. Record the current findings (writes corephp-audit-baseline.json).
corephp-audit --generate-baseline src

# 2. Commit the baseline, then audit against it in CI — only NEW issues are reported.
corephp-audit --baseline src --min-severity=high
```

Matching is count-based per `(file, rule, message)`: it tolerates edits that shift line numbers, but a
**new** occurrence of the same issue in the same file still surfaces. Generate and apply the baseline
from the same working directory with the same (preferably relative) path arguments. Regenerate it after
you fix findings to keep it tidy.

## Ignoring a finding

When a finding is a deliberate, reviewed exception, mark it inline with a
`corephp-audit-ignore` comment — on the same line, or the line directly above:

```php
$blob = unserialize($trusted);   // corephp-audit-ignore: trusted internal cache

// corephp-audit-ignore: last-resort exit — the worker cannot continue
exit(1);
```

Keep the reason in the comment so the next reader knows why it's allowed.

## Options

| Option | Effect |
|---|---|
| `--json` | Emit findings as JSON (`{summary, findings}`) for CI/tooling. |
| `--min-severity=low\|medium\|high` | Only report/gate on findings at or above this level (default `low`). |
| `--generate-baseline[=F]` | Write current findings to a baseline file (default `corephp-audit-baseline.json`) and exit 0. |
| `--baseline[=F]` | Suppress findings recorded in the baseline; report only new ones. |
| `--no-color` | Disable ANSI colours (also honours `NO_COLOR`). |
| `-h`, `--help` | Usage. |

## Scope & roadmap

This MVP is a **syntactic** analyzer (php-parser AST): it flags risky *usage* and suggests the fix.
It deliberately does **not** yet do data-flow analysis to prove a result is *actually* unchecked —
that precision is the planned evolution into a shippable PHPStan ruleset (v2.0), so it can run inside
the type engine and be enforced as a standard rule level. Use `--min-severity` to tune signal.

## Development

```bash
composer install
vendor/bin/phpunit           # 21 tests
```
