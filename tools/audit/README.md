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
| **SAFE** | `json_decode`, `file_get_contents`, `file_put_contents`, `preg_replace`, `base64_decode`, `curl_exec` (+ `intval`/`floatval` at LOW) | MED / LOW | the matching `s_*()` shim (throws instead of returning `null`/`false`/`0`) |
| **SECURE** | `eval`, `unserialize`, `exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open`, `assert('…')`, `create_function`, `extract` | HIGH | disable / JSON / typed DTO / real closures |
| **STABLE** | `exit`/`die` (kills the whole **worker**, not just the request), `global`, static class properties, function `static` vars | HIGH / MED | return a response; avoid cross-request state |

The **`exit`/`die`** rule is specific to the persistent-worker model: in a long-running runtime,
`exit()` terminates the entire worker process, not just the current request.

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

## Options

| Option | Effect |
|---|---|
| `--json` | Emit findings as JSON (`{summary, findings}`) for CI/tooling. |
| `--min-severity=low\|medium\|high` | Only report/gate on findings at or above this level (default `low`). |
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
vendor/bin/phpunit           # 13 tests
```
