<?php

declare(strict_types=1);

namespace core\Audit;

/**
 * A baseline of accepted findings, so the audit can be adopted on an existing
 * codebase without fixing everything up front: baselined findings are
 * suppressed, and only NEW findings are reported.
 *
 * Matching is count-based per (file, rule, message) — it tolerates line shifts
 * (edits above a finding), but a new occurrence of the same issue in the same
 * file exceeds the baselined count and is reported.
 *
 * Paths are stored as given, so generate and apply the baseline from the same
 * working directory with the same (preferably relative) path arguments.
 */
final class Baseline
{
    /** @param array<string, int> $counts key => accepted count */
    private function __construct(private readonly array $counts)
    {
    }

    private static function key(string $file, string $rule, string $message): string
    {
        return $file . "\0" . $rule . "\0" . $message;
    }

    /**
     * Serialize the given findings into a diffable JSON baseline.
     *
     * @param list<Finding> $findings
     */
    public static function generate(array $findings): string
    {
        /** @var array<string, array{file: string, rule: string, message: string, count: int}> $entries */
        $entries = [];
        foreach ($findings as $f) {
            $key = self::key($f->file, $f->rule, $f->message);
            if (isset($entries[$key])) {
                $entries[$key]['count']++;
            } else {
                $entries[$key] = ['file' => $f->file, 'rule' => $f->rule, 'message' => $f->message, 'count' => 1];
            }
        }

        $list = array_values($entries);
        usort($list, static function (array $a, array $b): int {
            return [$a['file'], $a['rule'], $a['message']] <=> [$b['file'], $b['rule'], $b['message']];
        });

        return (string) json_encode(
            ['version' => 1, 'findings' => $list],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @throws \RuntimeException if the file is missing or malformed
     */
    public static function fromFile(string $path): self
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("baseline file not found: {$path}");
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['findings']) || !is_array($data['findings'])) {
            throw new \RuntimeException("baseline file is malformed: {$path}");
        }

        $counts = [];
        foreach ($data['findings'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = self::key((string) $entry['file'], (string) $entry['rule'], (string) $entry['message']);
            $counts[$key] = ($counts[$key] ?? 0) + (int) $entry['count'];
        }

        return new self($counts);
    }

    /**
     * Drop findings covered by the baseline (consuming its budget), returning
     * only the findings that are new relative to the baseline.
     *
     * @param list<Finding> $findings
     * @return list<Finding>
     */
    public function filter(array $findings): array
    {
        $remaining = $this->counts;
        $new       = [];
        foreach ($findings as $f) {
            $key = self::key($f->file, $f->rule, $f->message);
            if (($remaining[$key] ?? 0) > 0) {
                $remaining[$key]--;
                continue;
            }
            $new[] = $f;
        }
        return $new;
    }
}
