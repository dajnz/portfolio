<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use RuntimeException;

/**
 * Serializes the Resume PDF-cache integration suites (step 10.1) across
 * parallel test workers. The suites exercise the real `redis` cache store
 * (the resume-PDF keys `resume:pdf:*` are scanned by pattern on
 * invalidation — content-management.md:400-401), and every file flushes
 * the shared Redis DB in `beforeEach`. Without serialization one worker's
 * flush can erase another worker's freshly written `resume:pdf:*` keys
 * between its HTTP request and the assertion (observed flake, 2026-09-07:
 * `ResumePdfCacheInvalidationTest` "invalidates the cached paths when a
 * source row is saved"). An exclusive file lock is used instead of a
 * Redis lock — the suites flush the whole cache DB, which would delete a
 * Redis-held lock key; `flock` is immune and releases automatically when
 * a worker crashes. The lock must be taken BEFORE the cache flush.
 */
trait SerializesResumePdfCache
{
    /** @var resource|null */
    protected $resumePdfCacheLockHandle = null;

    protected function lockResumePdfCache(): void
    {
        $handle = fopen(sys_get_temp_dir().'/resume-pdf-cache-tests.lock', 'c');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the resume-PDF cache test lock file.');
        }

        flock($handle, LOCK_EX);

        $this->resumePdfCacheLockHandle = $handle;
    }

    protected function unlockResumePdfCache(): void
    {
        if (! is_resource($this->resumePdfCacheLockHandle)) {
            return;
        }

        flock($this->resumePdfCacheLockHandle, LOCK_UN);
        fclose($this->resumePdfCacheLockHandle);
        $this->resumePdfCacheLockHandle = null;
    }
}
