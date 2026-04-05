<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

final class LaravelLogViewer
{
    /** @var list<string> */
    private const ERROR_LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /**
     * @return list<string> Basenames under storage/logs, newest mtime first.
     */
    public function listLogFiles(): array
    {
        $dir = storage_path('logs');
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.log') ?: [] as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }
            $files[] = [
                'name' => basename($path),
                'mtime' => @filemtime($path) ?: 0,
            ];
        }

        usort($files, fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return array_values(array_map(fn (array $f): string => $f['name'], $files));
    }

    /**
     * @return LengthAwarePaginator<int, array{datetime: Carbon|null, datetime_raw: string, channel: string, level: string, body: string}>
     */
    public function paginateErrors(?string $basename, int $perPage = 20): LengthAwarePaginator
    {
        $path = $this->resolveLogPath($basename);
        if ($path === null) {
            return new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
        }

        $content = $this->readLogTail($path);
        $entries = $this->parseEntries($content);
        $errors = array_values(array_filter(
            $entries,
            fn (array $e): bool => in_array(strtoupper($e['level']), self::ERROR_LEVELS, true)
        ));

        usort($errors, fn (array $a, array $b): int => $b['datetime_raw'] <=> $a['datetime_raw']);

        $page = Paginator::resolveCurrentPage('page');
        $total = count($errors);
        $offset = max(0, ($page - 1) * $perPage);
        $slice = array_slice($errors, $offset, $perPage);

        return new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
            'query' => request()->query(),
        ]);
    }

    public function resolveLogPath(?string $basename): ?string
    {
        $allowed = $this->listLogFiles();
        if ($basename === null || $basename === '') {
            $basename = $allowed[0] ?? null;
        }
        if ($basename === null || ! in_array($basename, $allowed, true)) {
            return null;
        }

        $path = storage_path('logs'.DIRECTORY_SEPARATOR.$basename);
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        return $path;
    }

    public function logFileExceedsReadLimit(?string $basename): bool
    {
        $path = $this->resolveLogPath($basename);
        if ($path === null) {
            return false;
        }

        $maxBytes = $this->maxReadBytes();
        $size = @filesize($path);

        return $size !== false && $size > $maxBytes;
    }

    public function maxReadBytes(): int
    {
        return max(64_000, (int) config('hms.log_viewer_max_bytes', 2_097_152));
    }

    /**
     * @return list<array{datetime: Carbon|null, datetime_raw: string, channel: string, level: string, body: string}>
     */
    public function parseEntries(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $pattern = '/\R(?=\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?\])/';
        $chunks = preg_split($pattern, $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $entries = [];
        foreach ($chunks as $chunk) {
            $chunk = ltrim($chunk);
            if ($chunk === '') {
                continue;
            }

            $firstLineEnd = strpos($chunk, "\n");
            $firstLine = $firstLineEnd === false ? $chunk : substr($chunk, 0, $firstLineEnd);
            $rest = $firstLineEnd === false ? '' : substr($chunk, $firstLineEnd + 1);

            if (! preg_match(
                '/^\[(?P<dt>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?)\]\s+(?P<channel>[\w-]+)\.(?P<level>\w+):\s*(?P<msg>.*)$/s',
                $firstLine,
                $m
            )) {
                continue;
            }

            $body = $m['msg'].($rest !== '' ? "\n".$rest : '');

            $entries[] = [
                'datetime' => $this->parseDateTime($m['dt']),
                'datetime_raw' => $m['dt'],
                'channel' => $m['channel'],
                'level' => strtoupper($m['level']),
                'body' => $body,
            ];
        }

        return $entries;
    }

    private function parseDateTime(string $dt): ?Carbon
    {
        $withoutMicro = preg_replace('/\.\d+$/', '', $dt) ?? $dt;

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $withoutMicro);
        } catch (\Throwable) {
            return null;
        }
    }

    private function readLogTail(string $absolutePath): string
    {
        $maxBytes = $this->maxReadBytes();
        $size = @filesize($absolutePath);
        if ($size === false || $size === 0) {
            return '';
        }

        if ($size <= $maxBytes) {
            $data = file_get_contents($absolutePath);

            return $data !== false ? $data : '';
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return '';
        }

        try {
            fseek($handle, -$maxBytes, SEEK_END);

            return (string) fread($handle, $maxBytes);
        } finally {
            fclose($handle);
        }
    }
}
