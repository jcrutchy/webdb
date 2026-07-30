<?php
declare(strict_types=1);

/**
 * Parallel PDF Diff Engine (PHP 8.2+)
 *
 * Renders two PDFs across worker processes and diffs them page-by-page,
 * with support for inserted/removed pages so that renumbering doesn't
 * throw off the alignment between old and new pages.
 *
 * PAGE ALIGNMENT
 *   Pass $insertedPages (page numbers in the NEW pdf that don't correspond
 *   to anything in the old pdf) and $removedPages (page numbers in the OLD
 *   pdf that don't survive into the new pdf). Everything else is assumed to
 *   be the same content, just possibly at a different page number. This is
 *   validated up front:
 *       newTotal === oldTotal + count($insertedPages) - count($removedPages)
 *   An alignment plan is then built by walking both page sequences in
 *   lockstep, producing an ordered list of 'paired' / 'inserted' / 'removed'
 *   entries. Workers operate on slices of this plan rather than on raw page
 *   ranges, since a paired old/new page number no longer has to match.
 *
 * MARGINS / CONTENT-AREA CROPPING
 *   $marginTopCm and $marginBottomCm apply to every page. $marginLeftCm and
 *   $marginRightCm apply to ODD pages; EVEN pages automatically get those
 *   two swapped (mirrors a typical inner/outer binding margin). Before a
 *   paired page is hashed/compared, both sides are cropped to their own
 *   content area using their OWN page number's parity -- so if old page 5
 *   (odd) becomes new page 6 (even) after an insertion, each side is
 *   cropped correctly for its own position, and a footer/page-number that
 *   only exists because of renumbering never causes a false 'changed'.
 *   The published full-res old/new images remain uncropped for context;
 *   only the comparison itself (and the diff visualization) is confined to
 *   the content area.
 *
 *   Note: when a page's parity flips (e.g. old page 5 -> new page 6), the
 *   crop windows for old vs new come from a genuinely different pixel
 *   offset, so anti-aliased text is never byte-identical even when the
 *   underlying content hasn't changed -- an exact hash match is only
 *   reliable when both sides share the same parity. For a parity flip, the
 *   comparison always falls through to an RMSE check against
 *   $changeThreshold, which absorbs that sub-pixel rasterization noise
 *   without needing an exact match. Calibrate $changeThreshold against your
 *   own documents/DPI if needed (observed noise floor from a pure parity
 *   flip was ~0.013-0.014 in testing at 100 DPI with the default; a real
 *   content change was ~0.06-0.09 in the same test).
 *
 * I/O strategy (this is where the time goes on 1000+ page documents):
 *   - unchanged pages (identical content-area crop): ONE thumbnail is
 *     written, no full-res publish at all. Full-res is rendered on demand
 *     via renderSinglePage() / the render_page.php example if the user
 *     wants to zoom into one.
 *   - changed / inserted / removed pages: full-res is published eagerly,
 *     since a human is very likely to want to inspect these closely.
 *
 * A /dev/shm scratch dir is used only as fast temporary render space and is
 * discarded when the engine is destroyed.
 *
 * NOTE: pcntl_fork() should not be called from inside a PHP-FPM / mod_php
 * request worker. Invoke this class from a CLI script (queue worker, cron,
 * or a process spawned by the web app), and have the web app poll
 * outputDir/manifest.json or a job-status row for completion.
 */
final class PdfFastDiffEngine
{
    private string $pdfPath1;
    private string $pdfPath2;
    private string $outputDir;   // persistent, web-accessible
    private string $scratchDir;  // ephemeral, /dev/shm
    private int $cpuCount;
    private int $renderDpi;
    private int $thumbWidth;

    /** @var list<int> */
    private array $insertedPages;
    /** @var list<int> */
    private array $removedPages;

    private float $marginTopCm;
    private float $marginBottomCm;
    private float $marginLeftCm;   // odd-page value; swapped for even pages
    private float $marginRightCm;  // odd-page value; swapped for even pages
    private float $changeThreshold;
    private float $diffFuzzPercent;
    private int $diffSmoothingRadius;

    private int $oldTotal = 0;
    private int $newTotal = 0;
    private int $padWidth = 4;
    private int $ownerPid;

    /**
     * @param list<int> $insertedPages Page numbers in $pdfPath2 with no old-side counterpart.
     * @param list<int> $removedPages  Page numbers in $pdfPath1 with no new-side counterpart.
     */
    public function __construct(
        string $pdfPath1,
        string $pdfPath2,
        string $outputDir,
        array $insertedPages = [],
        array $removedPages = [],
        float $marginTopCm = 0.0,
        float $marginBottomCm = 0.0,
        float $marginLeftCm = 0.0,
        float $marginRightCm = 0.0,
        float $changeThreshold = 0.03,
        float $diffFuzzPercent = 10.0,
        ?int $diffSmoothingRadius = null,
        int $cpuCount = 8,
        int $renderDpi = 100,
        int $thumbWidth = 320
    ) {
        if (!file_exists($pdfPath1)) {
            throw new InvalidArgumentException("File not found: {$pdfPath1}");
        }
        if (!file_exists($pdfPath2)) {
            throw new InvalidArgumentException("File not found: {$pdfPath2}");
        }
        if ($cpuCount < 1) {
            throw new InvalidArgumentException('cpuCount must be >= 1');
        }
        foreach (['marginTopCm' => $marginTopCm, 'marginBottomCm' => $marginBottomCm,
                  'marginLeftCm' => $marginLeftCm, 'marginRightCm' => $marginRightCm] as $name => $val) {
            if ($val < 0) {
                throw new InvalidArgumentException("{$name} must be >= 0");
            }
        }

        $this->pdfPath1 = $pdfPath1;
        $this->pdfPath2 = $pdfPath2;
        $this->outputDir = rtrim($outputDir, '/');
        $this->insertedPages = array_values(array_unique(array_map('intval', $insertedPages)));
        $this->removedPages = array_values(array_unique(array_map('intval', $removedPages)));
        $this->marginTopCm = $marginTopCm;
        $this->marginBottomCm = $marginBottomCm;
        $this->marginLeftCm = $marginLeftCm;
        $this->marginRightCm = $marginRightCm;
        $this->changeThreshold = $changeThreshold;
        $this->diffFuzzPercent = $diffFuzzPercent;
        $this->cpuCount = $cpuCount;
        $this->renderDpi = $renderDpi;
        $this->thumbWidth = $thumbWidth;
        // Anti-aliased text/line edges produce a scatter of single-pixel
        // differences that read as "jagged" in the diff visualization. The
        // smoothing radius consolidates those into solid highlighted
        // regions (see generateDiffImage). Scale with DPI so it stays
        // proportionally sized if renderDpi is changed from the default.
        $this->diffSmoothingRadius = $diffSmoothingRadius ?? max(1, (int)round($renderDpi / 100 * 1.5));

        self::assertBinaryExists('pdfinfo');
        self::assertBinaryExists('pdftocairo');
        // Debian Bookworm's stock `imagemagick` apt package is ImageMagick 6,
        // which does not ship the unified `magick` wrapper -- only the
        // separate legacy binaries below. Those also work fine on IM7.
        self::assertBinaryExists('compare');
        self::assertBinaryExists('convert');

        foreach (["{$this->outputDir}/full", "{$this->outputDir}/thumb"] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException("Failed to create output directory: {$dir}");
            }
        }

        $this->scratchDir = '/dev/shm/pdf_diff_' . bin2hex(random_bytes(6));
        if (!mkdir($this->scratchDir, 0755, true) && !is_dir($this->scratchDir)) {
            throw new RuntimeException("Failed to create RAM disk workspace at {$this->scratchDir}");
        }

        $this->ownerPid = getmypid();
    }

    public function __destruct()
    {
        // pcntl_fork() duplicates this object into every worker child. Each
        // child also runs this destructor on exit(), so without this guard
        // the first worker to finish would delete the shared scratch tree
        // out from under every other worker still rendering into it.
        if (getmypid() !== $this->ownerPid) {
            return;
        }
        // Only the ephemeral scratch space is deleted. outputDir (where the
        // web app reads diff/thumbnail images from) is never touched here.
        $this->recursiveDelete($this->scratchDir);
    }

    /**
     * @return array{meta: array<string, mixed>, pages: array<int, array<string, mixed>>}
     */
    public function run(): array
    {
        $startTime = microtime(true);

        $this->oldTotal = $this->getPdfPageCount($this->pdfPath1);
        $this->newTotal = $this->getPdfPageCount($this->pdfPath2);
        $this->padWidth = max(4, strlen((string)max($this->oldTotal, $this->newTotal)));

        $this->validateAlignment();
        $plan = $this->buildAlignmentPlan();

        $workerCount = min($this->cpuCount, count($plan));
        $chunkSize = (int)ceil(count($plan) / $workerCount);

        fwrite(STDERR, "[+] Old: {$this->oldTotal}pp, New: {$this->newTotal}pp, "
            . count($this->insertedPages) . " inserted, " . count($this->removedPages) . " removed, "
            . count($plan) . " total diff units across {$workerCount} workers...\n");

        $pids = [];
        for ($i = 0; $i < $workerCount; $i++) {
            $slice = array_slice($plan, $i * $chunkSize, $chunkSize, true);
            if ($slice === []) {
                break;
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('Critical: failed to fork worker process.');
            }

            if ($pid === 0) {
                // --- CHILD WORKER ---
                $exitCode = 0;
                try {
                    $chunkResult = $this->processChunk($slice, $i);
                    file_put_contents(
                        "{$this->scratchDir}/result_{$i}.json",
                        json_encode($chunkResult, JSON_THROW_ON_ERROR)
                    );
                } catch (Throwable $e) {
                    fwrite(STDERR, "[worker {$i}] fatal: {$e->getMessage()}\n");
                    $exitCode = 1;
                }
                exit($exitCode);
            }

            $pids[$i] = $pid;
        }

        $manifest = [];
        foreach ($pids as $i => $pid) {
            pcntl_waitpid($pid, $status);
            $resultFile = "{$this->scratchDir}/result_{$i}.json";
            if (!file_exists($resultFile)) {
                fwrite(STDERR, "[-] Worker {$i} produced no result file (exit status {$status}).\n");
                continue;
            }
            $chunkData = json_decode(file_get_contents($resultFile), true);
            if (is_array($chunkData)) {
                // Union (+), not array_merge(): merge preserves integer
                // plan-index keys instead of renumbering them sequentially.
                $manifest = $manifest + $chunkData;
            }
        }

        ksort($manifest, SORT_NUMERIC);

        $result = [
            'meta' => [
                'sourceOld' => $this->pdfPath1,
                'sourceNew' => $this->pdfPath2,
                'oldTotalPages' => $this->oldTotal,
                'newTotalPages' => $this->newTotal,
                'insertedPages' => $this->insertedPages,
                'removedPages' => $this->removedPages,
                'marginTopCm' => $this->marginTopCm,
                'marginBottomCm' => $this->marginBottomCm,
                'marginLeftCm' => $this->marginLeftCm,
                'marginRightCm' => $this->marginRightCm,
                'changeThreshold' => $this->changeThreshold,
                'diffFuzzPercent' => $this->diffFuzzPercent,
                'diffSmoothingRadius' => $this->diffSmoothingRadius,
                'renderDpi' => $this->renderDpi,
                'generatedAt' => date('c'),
            ],
            'pages' => $manifest,
        ];

        file_put_contents(
            "{$this->outputDir}/manifest.json",
            json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $duration = round(microtime(true) - $startTime, 2);
        fwrite(STDERR, "[+] Diff complete in {$duration}s.\n");

        return $result;
    }

    private function validateAlignment(): void
    {
        $expectedNewTotal = $this->oldTotal + count($this->insertedPages) - count($this->removedPages);
        if ($expectedNewTotal !== $this->newTotal) {
            throw new InvalidArgumentException(sprintf(
                'Page alignment does not add up: old=%d + inserted=%d - removed=%d = %d, but new pdf has %d pages.',
                $this->oldTotal,
                count($this->insertedPages),
                count($this->removedPages),
                $expectedNewTotal,
                $this->newTotal
            ));
        }
        foreach ($this->insertedPages as $p) {
            if ($p < 1 || $p > $this->newTotal) {
                throw new InvalidArgumentException("insertedPages contains {$p}, out of range 1..{$this->newTotal}");
            }
        }
        foreach ($this->removedPages as $p) {
            if ($p < 1 || $p > $this->oldTotal) {
                throw new InvalidArgumentException("removedPages contains {$p}, out of range 1..{$this->oldTotal}");
            }
        }
    }

    /**
     * Walks both page sequences in lockstep to produce an ordered list of
     * diff units. Keyed by sequential plan index (not page number), since
     * paired old/new page numbers can differ once pages have shifted.
     *
     * @return array<int, array{idx: int, type: string, oldPage: ?int, newPage: ?int}>
     */
    private function buildAlignmentPlan(): array
    {
        $removedSet = array_flip($this->removedPages);
        $insertedSet = array_flip($this->insertedPages);

        $plan = [];
        $idx = 0;
        $oldP = 1;
        $newP = 1;
        while ($oldP <= $this->oldTotal || $newP <= $this->newTotal) {
            if ($oldP <= $this->oldTotal && isset($removedSet[$oldP])) {
                $plan[$idx] = ['idx' => $idx, 'type' => 'removed', 'oldPage' => $oldP, 'newPage' => null];
                $idx++;
                $oldP++;
                continue;
            }
            if ($newP <= $this->newTotal && isset($insertedSet[$newP])) {
                $plan[$idx] = ['idx' => $idx, 'type' => 'inserted', 'oldPage' => null, 'newPage' => $newP];
                $idx++;
                $newP++;
                continue;
            }
            // Both within range and neither removed nor inserted at this
            // position -- validateAlignment() guarantees this pairs up
            // cleanly (both counters exhausted together).
            $plan[$idx] = ['idx' => $idx, 'type' => 'paired', 'oldPage' => $oldP, 'newPage' => $newP];
            $idx++;
            $oldP++;
            $newP++;
        }

        return $plan;
    }

    private static function assertBinaryExists(string $binary): void
    {
        $path = trim((string)shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));
        if ($path === '') {
            throw new RuntimeException("Required binary '{$binary}' not found on PATH.");
        }
    }

    /**
     * Renders one page of a PDF to PNG bytes on demand. Intended for a thin
     * web endpoint (see render_page.php) that lets a user zoom into a page
     * that was left as thumbnail-only (an 'unchanged' page) instead of
     * eagerly rendering full-res for every page up front.
     */
    public static function renderSinglePage(string $pdfPath, int $page, int $dpi = 150): string
    {
        if (!file_exists($pdfPath)) {
            throw new InvalidArgumentException("File not found: {$pdfPath}");
        }
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be >= 1');
        }
        self::assertBinaryExists('pdftocairo');

        $tmpDir = sys_get_temp_dir() . '/pdfpage_' . bin2hex(random_bytes(6));
        if (!mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            throw new RuntimeException("Failed to create temp render dir: {$tmpDir}");
        }

        try {
            $cmd = sprintf(
                'pdftocairo -png -r %d -f %d -l %d %s %s/page 2>&1',
                $dpi,
                $page,
                $page,
                escapeshellarg($pdfPath),
                escapeshellarg($tmpDir)
            );
            exec($cmd, $output, $exitCode);
            if ($exitCode !== 0) {
                throw new RuntimeException("pdftocairo failed for page {$page}: " . implode(' ', $output));
            }

            $files = glob("{$tmpDir}/page*.png") ?: [];
            if (count($files) !== 1) {
                throw new RuntimeException("Expected exactly one rendered file for page {$page}, got " . count($files));
            }

            $bytes = file_get_contents($files[0]);
            if ($bytes === false) {
                throw new RuntimeException("Failed to read rendered page {$page}");
            }
            return $bytes;
        } finally {
            foreach (glob("{$tmpDir}/*") ?: [] as $f) {
                unlink($f);
            }
            rmdir($tmpDir);
        }
    }

    private function getPdfPageCount(string $path): int
    {
        $output = shell_exec('pdfinfo ' . escapeshellarg($path) . ' 2>/dev/null | grep -a Pages:');
        if ($output !== null && preg_match('/Pages:\s+(\d+)/', $output, $matches)) {
            return (int)$matches[1];
        }
        throw new RuntimeException("Could not determine page count for: {$path}");
    }

    /**
     * @param array<int, array{idx: int, type: string, oldPage: ?int, newPage: ?int}> $planSlice
     * @return array<int, array<string, mixed>>
     */
    private function processChunk(array $planSlice, int $workerId): array
    {
        $manifest = [];
        $workDir = "{$this->scratchDir}/w_{$workerId}";
        mkdir($workDir, 0755, true);

        $oldPagesNeeded = array_values(array_filter(array_column($planSlice, 'oldPage')));
        $newPagesNeeded = array_values(array_filter(array_column($planSlice, 'newPage')));

        $oldMin = $oldPagesNeeded ? min($oldPagesNeeded) : null;
        $oldMax = $oldPagesNeeded ? max($oldPagesNeeded) : null;
        $newMin = $newPagesNeeded ? min($newPagesNeeded) : null;
        $newMax = $newPagesNeeded ? max($newPagesNeeded) : null;

        // Bulk-render the [min,max] old/new page ranges this slice actually
        // touches, in one pdftocairo call per side (cheap process-spawn
        // overhead vs. one call per page). Insertions/removals can leave
        // gaps in that range -- we just render (and ignore) a few extra
        // pages rather than issuing many single-page calls.
        $oldFiles = [];
        if ($oldMin !== null) {
            $ok = $this->renderRange($this->pdfPath1, $oldMin, $oldMax, "{$workDir}/old_");
            $oldFiles = $ok ? $this->globSorted("{$workDir}/old_*.png") : [];
        }
        $newFiles = [];
        if ($newMin !== null) {
            $ok = $this->renderRange($this->pdfPath2, $newMin, $newMax, "{$workDir}/new_");
            $newFiles = $ok ? $this->globSorted("{$workDir}/new_*.png") : [];
        }

        foreach ($planSlice as $unit) {
            $idx = $unit['idx'];
            $idxPadded = str_pad((string)$idx, $this->padWidth, '0', STR_PAD_LEFT);

            $oldFile = $unit['oldPage'] !== null && $oldMin !== null
                ? ($oldFiles[$unit['oldPage'] - $oldMin] ?? null)
                : null;
            $newFile = $unit['newPage'] !== null && $newMin !== null
                ? ($newFiles[$unit['newPage'] - $newMin] ?? null)
                : null;

            $entry = [
                'type' => $unit['type'],
                'oldPage' => $unit['oldPage'],
                'newPage' => $unit['newPage'],
                'status' => null,
                'rmse' => null,
            ];

            if ($unit['type'] === 'removed') {
                if ($oldFile === null) {
                    $entry['status'] = 'render_failed';
                } else {
                    $entry['status'] = 'removed';
                    $entry['full']['old'] = $this->publish($oldFile, "full/old-{$idxPadded}.png");
                    $entry['thumb']['old'] = $this->makeThumbnail($oldFile, "thumb/old-{$idxPadded}.png");
                }
                $manifest[$idx] = $entry;
                continue;
            }

            if ($unit['type'] === 'inserted') {
                if ($newFile === null) {
                    $entry['status'] = 'render_failed';
                } else {
                    $entry['status'] = 'inserted';
                    $entry['full']['new'] = $this->publish($newFile, "full/new-{$idxPadded}.png");
                    $entry['thumb']['new'] = $this->makeThumbnail($newFile, "thumb/new-{$idxPadded}.png");
                }
                $manifest[$idx] = $entry;
                continue;
            }

            // type === 'paired'
            if ($oldFile === null || $newFile === null) {
                // Alignment says both sides should exist -- this is a real
                // render failure on one side, not an expected mismatch.
                $entry['status'] = 'render_partial_failure';
                if ($oldFile !== null) {
                    $entry['full']['old'] = $this->publish($oldFile, "full/old-{$idxPadded}.png");
                    $entry['thumb']['old'] = $this->makeThumbnail($oldFile, "thumb/old-{$idxPadded}.png");
                }
                if ($newFile !== null) {
                    $entry['full']['new'] = $this->publish($newFile, "full/new-{$idxPadded}.png");
                    $entry['thumb']['new'] = $this->makeThumbnail($newFile, "thumb/new-{$idxPadded}.png");
                }
                $manifest[$idx] = $entry;
                continue;
            }

            $oldCropPath = "{$workDir}/cmp_old_{$idxPadded}.png";
            $newCropPath = "{$workDir}/cmp_new_{$idxPadded}.png";
            $oldCrop = $this->cropForComparison($oldFile, $unit['oldPage'], $oldCropPath);
            $newCrop = $this->cropForComparison($newFile, $unit['newPage'], $newCropPath);

            if ($oldCrop === null || $newCrop === null) {
                // Margins too large for this page's rendered size, or the
                // crop command failed. Publish full images so a human can
                // still look, but don't claim a comparison was made.
                $entry['status'] = 'crop_failed';
                $entry['full']['old'] = $this->publish($oldFile, "full/old-{$idxPadded}.png");
                $entry['full']['new'] = $this->publish($newFile, "full/new-{$idxPadded}.png");
                $entry['thumb']['old'] = $this->makeThumbnail($oldFile, "thumb/old-{$idxPadded}.png");
                $entry['thumb']['new'] = $this->makeThumbnail($newFile, "thumb/new-{$idxPadded}.png");
                $manifest[$idx] = $entry;
                continue;
            }

            if ($oldCrop['width'] !== $newCrop['width'] || $oldCrop['height'] !== $newCrop['height']) {
                // Different physical page sizes between old/new for this
                // pair -- content-area dimensions won't line up for a pixel
                // compare. Publish full images for manual inspection.
                $entry['status'] = 'size_mismatch';
                $entry['full']['old'] = $this->publish($oldFile, "full/old-{$idxPadded}.png");
                $entry['full']['new'] = $this->publish($newFile, "full/new-{$idxPadded}.png");
                $entry['thumb']['old'] = $this->makeThumbnail($oldFile, "thumb/old-{$idxPadded}.png");
                $entry['thumb']['new'] = $this->makeThumbnail($newFile, "thumb/new-{$idxPadded}.png");
                $manifest[$idx] = $entry;
                continue;
            }

            // A parity flip (old page odd, new page even, or vice versa)
            // means the two crop windows come from different sub-pixel
            // offsets -- anti-aliased text won't hash-match even when the
            // content is identical. Only trust the hash fast-path when
            // both sides share parity; otherwise always fall through to
            // the RMSE/threshold check below.
            $sameParity = ($unit['oldPage'] % 2) === ($unit['newPage'] % 2);

            if ($sameParity && md5_file($oldCrop['path']) === md5_file($newCrop['path'])) {
                // Content area is byte-identical -- treat as unchanged even
                // if the full page differs (e.g. only a renumbered footer
                // inside the margin changed). One thumbnail, no full-res.
                $entry['status'] = 'unchanged';
                $entry['thumb'] = $this->makeThumbnail($oldFile, "thumb/page-{$idxPadded}.png");
                $manifest[$idx] = $entry;
                continue;
            }

            // Either bytes differ, or parity flipped and we can't trust a
            // hash comparison -- publish full pages for context, but
            // compute RMSE/diff from the cropped content area only, so
            // margin content never pollutes the visual diff either.
            $entry['full']['old'] = $this->publish($oldFile, "full/old-{$idxPadded}.png");
            $entry['full']['new'] = $this->publish($newFile, "full/new-{$idxPadded}.png");
            $entry['thumb']['old'] = $this->makeThumbnail($oldFile, "thumb/old-{$idxPadded}.png");
            $entry['thumb']['new'] = $this->makeThumbnail($newFile, "thumb/new-{$idxPadded}.png");

            $rmse = $this->compareRmse($oldCrop['path'], $newCrop['path']);
            $entry['rmse'] = $rmse;

            if ($rmse !== null && $rmse > $this->changeThreshold) {
                $entry['status'] = 'changed';
                $diffFile = "{$workDir}/diff_{$idxPadded}.png";
                if ($this->generateDiffImage($oldCrop['path'], $newCrop['path'], $diffFile)) {
                    $entry['full']['diff'] = $this->publish($diffFile, "full/diff-{$idxPadded}.png");
                    $entry['thumb']['diff'] = $this->makeThumbnail($diffFile, "thumb/diff-{$idxPadded}.png");
                }
            } else {
                $entry['status'] = $rmse === null ? 'compare_failed' : 'unchanged';
            }

            $manifest[$idx] = $entry;
        }

        return $manifest;
    }

    /**
     * Crops a rendered page to its content area for comparison purposes
     * only (the published full-res image is never overwritten). Uses the
     * page's OWN parity to decide which side gets the larger margin, so a
     * page keeps the correct crop even if it changed odd/even position
     * between old and new.
     *
     * @return array{path: string, width: int, height: int}|null
     */
    private function cropForComparison(string $sourcePath, int $pageNumber, string $destPath): ?array
    {
        $dims = @getimagesize($sourcePath);
        if ($dims === false) {
            fwrite(STDERR, "[-] Could not read image dimensions: {$sourcePath}\n");
            return null;
        }
        [$width, $height] = $dims;

        $isOdd = ($pageNumber % 2) === 1;
        $leftCm = $isOdd ? $this->marginLeftCm : $this->marginRightCm;
        $rightCm = $isOdd ? $this->marginRightCm : $this->marginLeftCm;

        $cmToPx = fn(float $cm): int => (int)round($cm / 2.54 * $this->renderDpi);
        $topPx = $cmToPx($this->marginTopCm);
        $bottomPx = $cmToPx($this->marginBottomCm);
        $leftPx = $cmToPx($leftCm);
        $rightPx = $cmToPx($rightCm);

        $cropW = $width - $leftPx - $rightPx;
        $cropH = $height - $topPx - $bottomPx;
        if ($cropW <= 0 || $cropH <= 0) {
            fwrite(STDERR, "[-] Margins leave no content area for page {$pageNumber} "
                . "({$width}x{$height}px, margins L{$leftPx} R{$rightPx} T{$topPx} B{$bottomPx}px)\n");
            return null;
        }

        $cmd = sprintf(
            'convert %s -crop %dx%d+%d+%d +repage %s 2>&1',
            escapeshellarg($sourcePath),
            $cropW,
            $cropH,
            $leftPx,
            $topPx,
            escapeshellarg($destPath)
        );
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0 || !file_exists($destPath)) {
            fwrite(STDERR, "[-] Crop failed for page {$pageNumber}: " . implode(' ', $output) . "\n");
            return null;
        }

        return ['path' => $destPath, 'width' => $cropW, 'height' => $cropH];
    }

    private function renderRange(string $pdfPath, int $startPage, int $endPage, string $outPrefix): bool
    {
        $cmd = sprintf(
            'pdftocairo -png -r %d -f %d -l %d %s %s 2>&1',
            $this->renderDpi,
            $startPage,
            $endPage,
            escapeshellarg($pdfPath),
            escapeshellarg($outPrefix)
        );
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            fwrite(STDERR, "[-] pdftocairo failed ({$startPage}-{$endPage}): " . implode(' ', $output) . "\n");
            return false;
        }
        return true;
    }

    /**
     * @return list<string> Absolute paths, naturally sorted.
     */
    private function globSorted(string $pattern): array
    {
        $files = glob($pattern) ?: [];
        natsort($files);
        return array_values($files);
    }

    private function compareRmse(string $file1, string $file2): ?float
    {
        $cmd = sprintf(
            'compare -metric RMSE %s %s null: 2>&1',
            escapeshellarg($file1),
            escapeshellarg($file2)
        );
        // `compare` exits non-zero when images differ, so don't gate on
        // exit code here -- just parse its output.
        $output = shell_exec($cmd);
        if ($output !== null && preg_match('/\(([\d.]+)\)/', $output, $m)) {
            return (float)$m[1];
        }
        return null;
    }

    private function generateDiffImage(string $file1, string $file2, string $outPath): bool
    {
        $dir = dirname($outPath);
        $rawMask = "{$dir}/" . uniqid('mask_raw_') . '.png';
        $cleanMask = "{$dir}/" . uniqid('mask_clean_') . '.png';
        $dimmed = "{$dir}/" . uniqid('dimmed_') . '.png';

        try {
            // Step 1: a black/white difference mask, with -fuzz giving a
            // color-distance tolerance so near-identical anti-aliased pixels
            // don't register as "different" in the first place.
            $cmd = sprintf(
                'compare -metric AE -fuzz %F%% -highlight-color white -lowlight-color black %s %s %s 2>&1',
                $this->diffFuzzPercent,
                escapeshellarg($file1),
                escapeshellarg($file2),
                escapeshellarg($rawMask)
            );
            exec($cmd, $output, $exitCode);
            // `compare` returns 1 when a difference was found (expected) --
            // only 2 signals a real error.
            if ($exitCode === 2 || !file_exists($rawMask)) {
                fwrite(STDERR, "[-] diff mask generation failed: " . implode(' ', $output) . "\n");
                return false;
            }

            // Step 2: Close morphology (dilate then erode) fills small gaps
            // and merges nearby speckle pixels into solid blobs. Unlike an
            // erosion-based cleanup, Close never deletes an isolated pixel
            // outright, so it can't accidentally erase a small genuine
            // change -- it only ever makes existing differences look more
            // like cohesive regions and less like scattered noise.
            $cmd = sprintf(
                'convert %s -morphology Close Disk:%d %s 2>&1',
                escapeshellarg($rawMask),
                $this->diffSmoothingRadius,
                escapeshellarg($cleanMask)
            );
            exec($cmd, $output, $exitCode);
            if ($exitCode !== 0 || !file_exists($cleanMask)) {
                fwrite(STDERR, "[-] diff mask smoothing failed: " . implode(' ', $output) . "\n");
                return false;
            }

            // Step 3: composite the cleaned mask as a red highlight over a
            // dimmed grayscale version of the new page, for context.
            $cmd = sprintf(
                'convert %s -colorspace Gray -level 25%%,100%% %s 2>&1',
                escapeshellarg($file2),
                escapeshellarg($dimmed)
            );
            exec($cmd, $output, $exitCode);
            if ($exitCode !== 0 || !file_exists($dimmed)) {
                fwrite(STDERR, "[-] diff background generation failed: " . implode(' ', $output) . "\n");
                return false;
            }

            $cmd = sprintf(
                'convert %s \( %s -fill red -opaque white \) -compose over -composite %s 2>&1',
                escapeshellarg($dimmed),
                escapeshellarg($cleanMask),
                escapeshellarg($outPath)
            );
            exec($cmd, $output, $exitCode);
            if ($exitCode !== 0 || !file_exists($outPath)) {
                fwrite(STDERR, "[-] diff composite failed: " . implode(' ', $output) . "\n");
                return false;
            }

            return true;
        } finally {
            foreach ([$rawMask, $cleanMask, $dimmed] as $f) {
                if (file_exists($f)) {
                    unlink($f);
                }
            }
        }
    }

    private function makeThumbnail(string $sourcePath, string $relativeOutPath): ?string
    {
        /*$destPath = "{$this->outputDir}/{$relativeOutPath}";
        $cmd = sprintf(
            'convert %s -thumbnail %dx -background white -alpha remove %s 2>&1',
            escapeshellarg($sourcePath),
            $this->thumbWidth,
            escapeshellarg($destPath)
        );
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            fwrite(STDERR, "[-] thumbnail generation failed for {$sourcePath}: " . implode(' ', $output) . "\n");
            return null;
        }*/
        return $relativeOutPath;
    }

    private function publish(string $sourcePath, string $relativeOutPath): string
    {
        $destPath = "{$this->outputDir}/{$relativeOutPath}";
        if (!copy($sourcePath, $destPath)) {
            throw new RuntimeException("Failed to publish {$sourcePath} -> {$destPath}");
        }
        return $relativeOutPath;
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $entries = array_diff(scandir($dir), ['.', '..']);
        foreach ($entries as $entry) {
            $path = "{$dir}/{$entry}";
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }
}

// --- EXECUTION EXAMPLE (run from CLI, not inside a web request) ---
// $engine = new PdfFastDiffEngine(
//     '/path/to/old.pdf',
//     '/path/to/new.pdf',
//     '/var/www/app/storage/diffs/job-123',
//     insertedPages: [2, 47],     // new.pdf page numbers with no old counterpart
//     removedPages: [10],         // old.pdf page numbers with no new counterpart
//     marginTopCm: 2.0,
//     marginBottomCm: 2.0,
//     marginLeftCm: 2.5,          // odd pages; even pages get L/R swapped automatically
//     marginRightCm: 1.5,
//     cpuCount: 8
// );
// $result = $engine->run();
// // manifest.json (result['meta'] + result['pages']) is also written to the
// // output dir, keyed by plan index (not page number) since old/new page
// // numbers can differ once pages have shifted. Each page entry carries its
// // own oldPage/newPage for display.
// //
// // IMPORTANT: meta.sourceOld/sourceNew store whatever paths were passed in
// // above -- if those point at a tmp upload dir that gets cleaned up after
// // the request, the on-demand render_page.php endpoint won't be able to
// // find them later. Either pass in already-persisted paths, or copy the two
// // source PDFs into the job's outputDir yourself and update meta accordingly.
