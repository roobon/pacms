<?php

namespace App\Cms\Exchange;

/**
 * Import report (CMS-BLOCK-SCHEMA.md §16–17). Every entry has a level, a section, the JSON
 * Pointer of the place in the document and the author's node `key` when there is one.
 * Errors stop the import; warnings need confirmation; info is for the record.
 */
final class ImportReport
{
    public const SECTIONS = [
        'document' => 'Document',
        'blocks' => 'Blocks',
        'unsupported' => 'Unsupported properties',
        'security' => 'Security changes',
        'references' => 'Missing references',
        'assets' => 'Assets',
        'external' => 'External configuration requirements',
    ];

    /** @var list<array{level: string, section: string, path: string, key: string|null, message: string}> */
    private array $entries = [];

    /** @var array<string, mixed> */
    private array $summary = [];

    public function error(string $section, string $message, string $path = '', ?string $key = null): void
    {
        $this->add('error', $section, $message, $path, $key);
    }

    public function warning(string $section, string $message, string $path = '', ?string $key = null): void
    {
        $this->add('warning', $section, $message, $path, $key);
    }

    public function info(string $section, string $message, string $path = '', ?string $key = null): void
    {
        $this->add('info', $section, $message, $path, $key);
    }

    public function hasErrors(): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['level'] === 'error') {
                return true;
            }
        }

        return false;
    }

    public function summarize(string $key, mixed $value): void
    {
        $this->summary[$key] = $value;
    }

    /**
     * @param  array<string, mixed>  $data  a stored report (toArray()) to continue
     */
    public static function fromArray(array $data): self
    {
        $report = new self;
        $report->entries = array_values((array) ($data['entries'] ?? []));
        $report->summary = (array) ($data['summary'] ?? []);

        return $report;
    }

    /**
     * @return array{summary: array<string, mixed>, entries: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function toArray(): array
    {
        $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($this->entries as $entry) {
            $counts[$entry['level']]++;
        }

        return ['summary' => $this->summary, 'entries' => $this->entries, 'counts' => $counts];
    }

    private function add(string $level, string $section, string $message, string $path, ?string $key): void
    {
        $entry = ['level' => $level, 'section' => $section, 'path' => $path, 'key' => $key, 'message' => $message];

        // The same problem reported twice (e.g. on a second validation pass) is listed once.
        if (! in_array($entry, $this->entries, true)) {
            $this->entries[] = $entry;
        }
    }
}
