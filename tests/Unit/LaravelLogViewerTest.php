<?php

use App\Services\LaravelLogViewer;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->testLogPath = storage_path('logs/hms_unit_test_laravel_log_viewer.log');
    if (is_file($this->testLogPath)) {
        unlink($this->testLogPath);
    }
});

afterEach(function (): void {
    if (is_file($this->testLogPath)) {
        unlink($this->testLogPath);
    }
});

it('parses monolog-style lines and keeps multiline bodies with errors', function (): void {
    file_put_contents($this->testLogPath, <<<'LOG'
[2026-01-01 08:00:00] local.INFO: not an error
[2026-01-02 09:00:00] local.ERROR: first error
#0 /app/trace.php(1): fail()
[2026-01-03 10:00:00] local.CRITICAL: second error
LOG);

    $viewer = new LaravelLogViewer;
    $entries = $viewer->parseEntries(file_get_contents($this->testLogPath));

    expect($entries)->toHaveCount(3);
    expect($entries[1]['level'])->toBe('ERROR');
    expect($entries[1]['body'])->toContain('#0 /app/trace.php');
});

it('paginates error levels newest first', function (): void {
    file_put_contents($this->testLogPath, <<<'LOG'
[2026-01-01 08:00:00] local.INFO: skip
[2026-01-02 09:00:00] local.ERROR: older
[2026-01-03 10:00:00] local.ERROR: newer
LOG);

    Paginator::currentPageResolver(fn (): int => 1);

    $viewer = new LaravelLogViewer;
    $page = $viewer->paginateErrors('hms_unit_test_laravel_log_viewer.log', 10);

    expect($page->total())->toBe(2);
    expect($page->items()[0]['body'])->toStartWith('newer');
    expect($page->items()[1]['body'])->toStartWith('older');
});

it('returns empty paginator for unknown log basename', function (): void {
    Paginator::currentPageResolver(fn (): int => 1);

    $viewer = new LaravelLogViewer;
    $page = $viewer->paginateErrors('definitely_missing_file_xyz.log', 10);

    expect($page->total())->toBe(0);
});
