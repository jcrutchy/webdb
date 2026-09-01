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
 *   Pass $alignment: a list of [oldPage, newPage] pairs, 1-indexed, in the
 *   order they should be walked. Either slot may be null:
 *       [22, 23]    -- old p22 corresponds to new p23 ("paired")
 *       [null, 22]  -- new p22 has no old counterpart ("inserted")
 *       [22, null]  -- old p22 has no new counterpart ("removed")
 *   This is expected to come from a Needleman-Wunsch (or similar) sequence
 *   alignment run by the caller over the two page sequences -- this class
 *   does no alignment itself, it just walks the pairs it's given in order
 *   to build the diff plan. Workers operate on slices of this plan rather
 *   than on raw page ranges, since a paired old/new page number no longer
 *   has to match.
 *
 *   Validated up front: the non-null oldPage values, taken in the order
 *   they appear, must be exactly 1..oldTotal with no gaps/repeats/
 *   out-of-order entries (and likewise for newPage vs newTotal). That
 *   catches a malformed alignment (e.g. built against the wrong page
 *   counts) before any rendering happens.
 *
 * LAYOUT / CONTENT-AREA CROPPING
 *   Pass $layoutOld and $layoutNew: each is an array keyed by page number,
 *   one entry per page, describing that page's content box. Expected shape
 *   per entry (all length values in cm):
 *       [
 *           'page_no'      => int,
 *           'page_width'   => float,  // full physical page width
 *           'page_height'  => float,  // full physical page height
 *           'content_left'   => float,  // offset from left edge to content box
 *           'content_top'    => float,  // offset from top edge to content box
 *           'content_right'  => float,  // offset from left edge to right edge of content box
 *           'content_bottom' => float,  // offset from top edge to bottom edge of content box
 *           'width'        => float,  // content box width (content_right - content_left)
 *           'height'       => float,  // content box height (content_bottom - content_top)
 *           'left_margin'  => float,  // = content_left, kept for readability/validation
 *           'right_margin' => float,  // = page_width - content_right
 *           'binding_side' => 'left'|'right',
 *       ]
 *   Before a paired page is hashed/compared, both sides are cropped to
 *   their OWN page's content box from their OWN side's layout array -- so
 *   if old page 5 becomes new page 6 after an insertion, each side is
 *   cropped using its own measured content area, and a footer/page-number
 *   that only exists because of renumbering never causes a false 'changed'.
 *   The published full-res old/new images remain uncropped for context;
 *   only the comparison itself (and the diff visualization) is confined to
 *   the content area. A page missing from the relevant layout array falls
 *   back to the full rendered page (no crop) and logs a warning.
 *
 *   left_margin/right_margin are expected to be consistent across pages
 *   sharing the same binding_side (that's the "should be a rectangle"
 *   assumption a fixed inner/outer binding margin implies). This class
 *   uses content_left/content_top/width/height directly rather than
 *   recomputing them, but does a soft consistency check across each
 *   layout array grouped by binding_side and logs to STDERR (not a hard
 *   failure) if the margins vary by more than a small tolerance -- useful
 *   as an early signal that the layout data is noisy or binding_side was
 *   misdetected for some pages.
 *
 *   Note: even with per-page measured content boxes, an exact hash match
 *   across old vs new is only reliable when the crop windows line up
 *   pixel-for-pixel; small measurement noise (or a genuine binding_side
 *   flip between old/new for the same logical page) can shift the crop by
 *   a pixel or two, and anti-aliased text won't be byte-identical even
 *   when the content hasn't changed. The comparison always falls through
 *   to an RMSE check against $changeThreshold in that case, which absorbs
 *   that sub-pixel rasterization noise without needing an exact match.
 *   Calibrate $changeThreshold against your own documents/DPI if needed.
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

    /** @var list<array{0: ?int, 1: ?int}> */
    private array $alignment;

    /** @var array<int, array<string, mixed>> keyed by old page number */
    private array $layoutOld;
    /** @var array<int, array<string, mixed>> keyed by new page number */
    private array $layoutNew;

    private float $changeThreshold;

    private int $oldTotal = 0;
    private int $newTotal = 0;
    private int $padWidth = 4;
    private int $ownerPid;

    /**
     * @param list<array{0: ?int, 1: ?int}> $alignment Ordered [oldPage, newPage] pairs
     *        (1-indexed; either slot may be null), as produced by a
     *        Needleman-Wunsch alignment of the two page sequences.
     * @param array<int, array<string, mixed>> $layoutOld Per-page content-box
     *        layout for $pdfPath1, keyed by old page number. See class docblock.
     * @param array<int, array<string, mixed>> $layoutNew Per-page content-box
     *        layout for $pdfPath2, keyed by new page number. See class docblock.
     */
    public function __construct(
        string $pdfPath1,
        string $pdfPath2,
        string $outputDir,
        array $alignment,
        array $layoutOld = [],
        array $layoutNew = [],
        float $changeThreshold = 0.001,
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
        if ($alignment === []) {
            throw new InvalidArgumentException('alignment must not be empty');
        }
        foreach ($alignment as $i => $pair) {
            if (!is_array($pair) || count($pair) !== 2 || !array_key_exists(0, $pair) || !array_key_exists(1, $pair)) {
                throw new InvalidArgumentException("alignment[{$i}] must be a 2-element [oldPage, newPage] pair");
            }
            if ($pair[0] === null && $pair[1] === null) {
                throw new InvalidArgumentException("alignment[{$i}] cannot have both oldPage and newPage null");
            }
        }

        $this->pdfPath1 = $pdfPath1;
        $this->pdfPath2 = $pdfPath2;
        $this->outputDir = rtrim($outputDir, '/');
        $this->alignment = array_map(
            static fn(array $pair): array => [
                $pair[0] !== null ? (int)$pair[0] : null,
                $pair[1] !== null ? (int)$pair[1] : null,
            ],
            array_values($alignment)
        );
        $this->layoutOld = $layoutOld;
        $this->layoutNew = $layoutNew;
        $this->changeThreshold = $changeThreshold;
        $this->cpuCount = $cpuCount;
        $this->renderDpi = $renderDpi;
        $this->thumbWidth = $thumbWidth;

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

        $this->validateLayoutConsistency($this->layoutOld, 'old');
        $this->validateLayoutConsistency($this->layoutNew, 'new');

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

        $insertedCount = count(array_filter($plan, static fn($u) => $u['type'] === 'inserted'));
        $removedCount = count(array_filter($plan, static fn($u) => $u['type'] === 'removed'));
        fwrite(STDERR, "[+] Old: {$this->oldTotal}pp, New: {$this->newTotal}pp, "
            . "{$insertedCount} inserted, {$removedCount} removed, "
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
                'alignment' => $this->alignment,
                'changeThreshold' => $this->changeThreshold,
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

    /**
     * Confirms the caller's alignment (e.g. from a Needleman-Wunsch run)
     * actually accounts for every old and every new page exactly once, in
     * increasing order, before any rendering happens.
     */
    private function validateAlignment(): void
    {
        $oldSeen = [];
        $newSeen = [];
        $lastOld = 0;
        $lastNew = 0;

        foreach ($this->alignment as $i => [$oldP, $newP]) {
            if ($oldP !== null) {
                if ($oldP < 1 || $oldP > $this->oldTotal) {
                    throw new InvalidArgumentException(
                        "alignment[{$i}] oldPage {$oldP} out of range 1..{$this->oldTotal}"
                    );
                }
                if ($oldP <= $lastOld) {
                    throw new InvalidArgumentException(
                        "alignment[{$i}] oldPage {$oldP} is not strictly increasing (previous was {$lastOld})"
                    );
                }
                if (isset($oldSeen[$oldP])) {
                    throw new InvalidArgumentException("alignment contains oldPage {$oldP} more than once");
                }
                $oldSeen[$oldP] = true;
                $lastOld = $oldP;
            }
            if ($newP !== null) {
                if ($newP < 1 || $newP > $this->newTotal) {
                    throw new InvalidArgumentException(
                        "alignment[{$i}] newPage {$newP} out of range 1..{$this->newTotal}"
                    );
                }
                if ($newP <= $lastNew) {
                    throw new InvalidArgumentException(
                        "alignment[{$i}] newPage {$newP} is not strictly increasing (previous was {$lastNew})"
                    );
                }
                if (isset($newSeen[$newP])) {
                    throw new InvalidArgumentException("alignment contains newPage {$newP} more than once");
                }
                $newSeen[$newP] = true;
                $lastNew = $newP;
            }
        }

        if (count($oldSeen) !== $this->oldTotal) {
            throw new InvalidArgumentException(sprintf(
                'alignment covers %d old page(s) but %s has %d pages.',
                count($oldSeen),
                $this->pdfPath1,
                $this->oldTotal
            ));
        }
        if (count($newSeen) !== $this->newTotal) {
            throw new InvalidArgumentException(sprintf(
                'alignment covers %d new page(s) but %s has %d pages.',
                count($newSeen),
                $this->pdfPath2,
                $this->newTotal
            ));
        }
    }

    /**
     * Converts the caller-supplied alignment into the plan shape the rest
     * of the engine expects. Keyed by sequential plan index (not page
     * number), since paired old/new page numbers can differ once pages
     * have shifted. The alignment's own order is trusted as-is (it's the
     * output of the caller's Needleman-Wunsch walk).
     *
     * @return array<int, array{idx: int, type: string, oldPage: ?int, newPage: ?int}>
     */
    private function buildAlignmentPlan(): array
    {
        $plan = [];
        foreach ($this->alignment as $idx => [$oldP, $newP]) {
            $type = match (true) {
                $oldP !== null && $newP !== null => 'paired',
                $oldP === null => 'inserted',
                default => 'removed', // $newP === null
            };
            $plan[$idx] = ['idx' => $idx, 'type' => $type, 'oldPage' => $oldP, 'newPage' => $newP];
        }
        return $plan;
    }

    /**
     * Soft consistency check only -- logs to STDERR, never throws. Groups
     * a layout array by binding_side and flags left_margin/right_margin
     * values that stray more than $toleranceCm from that group's median,
     * since a fixed inner/outer binding margin should otherwise hold
     * steady across every page on the same side.
     *
     * @param array<int, array<string, mixed>> $layout
     */
    private function validateLayoutConsistency(array $layout, string $label, float $toleranceCm = 0.15): void
    {
        if ($layout === []) {
            return;
        }
        $bySide = [];
        foreach ($layout as $pageNo => $entry) {
            $side = $entry['binding_side'] ?? 'unknown';
            $bySide[$side]['left'][$pageNo] = $entry['left_margin'] ?? null;
            $bySide[$side]['right'][$pageNo] = $entry['right_margin'] ?? null;
        }
        foreach ($bySide as $side => $margins) {
            foreach (['left', 'right'] as $edge) {
                $values = array_filter($margins[$edge], static fn($v) => $v !== null);
                if (count($values) < 2) {
                    continue;
                }
                sort($values);
                $median = $values[(int)floor(count($values) / 2)];
                foreach ($margins[$edge] as $pageNo => $v) {
                    if ($v !== null && abs($v - $median) > $toleranceCm) {
                        fwrite(STDERR, sprintf(
                            "[!] %s layout: page %d %s_margin=%.3fcm strays from binding_side=%s median %.3fcm by >%.2fcm\n",
                            $label,
                            $pageNo,
                            $edge,
                            $v,
                            $side,
                            $median,
                            $toleranceCm
                        ));
                    }
                }
            }
        }
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
            $oldCrop = $this->cropForComparison(
                $oldFile,
                $unit['oldPage'],
                $oldCropPath,
                $this->layoutOld[$unit['oldPage']] ?? null
            );
            $newCrop = $this->cropForComparison(
                $newFile,
                $unit['newPage'],
                $newCropPath,
                $this->layoutNew[$unit['newPage']] ?? null
            );

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

            // A binding-side flip (e.g. old page was a left-hand page,
            // new page is a right-hand page) means the two crop windows
            // come from different sub-pixel offsets -- anti-aliased text
            // won't hash-match even when the content is identical. Prefer
            // the measured binding_side from layout when both sides have
            // one; fall back to old/new page-number parity when layout
            // data is missing. Only trust the hash fast-path when both
            // sides agree; otherwise always fall through to the RMSE/
            // threshold check below.
            $oldSide = $this->layoutOld[$unit['oldPage']]['binding_side'] ?? null;
            $newSide = $this->layoutNew[$unit['newPage']]['binding_side'] ?? null;
            $sameSide = ($oldSide !== null && $newSide !== null)
                ? ($oldSide === $newSide)
                : (($unit['oldPage'] % 2) === ($unit['newPage'] % 2));

            if ($sameSide && md5_file($oldCrop['path']) === md5_file($newCrop['path'])) {
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
     * page's OWN measured content box from the caller-supplied layout
     * entry, so a page keeps the correct crop even if its binding side
     * changed between old and new. Falls back to the full rendered page
     * (no crop) when no layout entry is available for this page.
     *
     * @param array<string, mixed>|null $layoutEntry Per-page layout, see class docblock.
     * @return array{path: string, width: int, height: int}|null
     */
    private function cropForComparison(string $sourcePath, int $pageNumber, string $destPath, ?array $layoutEntry): ?array
    {
        $dims = @getimagesize($sourcePath);
        if ($dims === false) {
            fwrite(STDERR, "[-] Could not read image dimensions: {$sourcePath}\n");
            return null;
        }
        [$width, $height] = $dims;

        if ($layoutEntry === null) {
            fwrite(STDERR, "[!] No layout entry for page {$pageNumber} -- using full page as content area\n");
            if (!@copy($sourcePath, $destPath)) {
                fwrite(STDERR, "[-] Fallback copy failed for page {$pageNumber}\n");
                return null;
            }
            return ['path' => $destPath, 'width' => $width, 'height' => $height];
        }

        // renderDpi is pixels-per-inch, so cm -> inch -> px, independent of
        // the layout's own page_width/page_height (those are only used
        // below to sanity-check against the actually-rendered image size).
        $cmToPx = fn(float $cm): int => (int)round($cm / 2.54 * $this->renderDpi);

        $contentLeftCm = (float)($layoutEntry['content_left'] ?? 0.0);
        $contentTopCm = (float)($layoutEntry['content_top'] ?? 0.0);
        $contentWidthCm = isset($layoutEntry['width'])
            ? (float)$layoutEntry['width']
            : (float)($layoutEntry['content_right'] ?? 0.0) - $contentLeftCm;
        $contentHeightCm = isset($layoutEntry['height'])
            ? (float)$layoutEntry['height']
            : (float)($layoutEntry['content_bottom'] ?? 0.0) - $contentTopCm;

        $leftPx = $cmToPx($contentLeftCm);
        $topPx = $cmToPx($contentTopCm);
        $cropW = $cmToPx($contentWidthCm);
        $cropH = $cmToPx($contentHeightCm);

        if (isset($layoutEntry['page_width'], $layoutEntry['page_height'])) {
            $expectedW = $cmToPx((float)$layoutEntry['page_width']);
            $expectedH = $cmToPx((float)$layoutEntry['page_height']);
            // A few px of slack for rounding between the layout's own
            // page_width/page_height and pdftocairo's rendered dimensions.
            if (abs($expectedW - $width) > 3 || abs($expectedH - $height) > 3) {
                fwrite(STDERR, sprintf(
                    "[!] Layout page_size for page %d (%dx%d px @ %ddpi) doesn't match rendered image (%dx%d px) -- "
                        . "crop may be off; check the layout data or renderDpi.\n",
                    $pageNumber,
                    $expectedW,
                    $expectedH,
                    $this->renderDpi,
                    $width,
                    $height
                ));
            }
        }

        if ($cropW <= 0 || $cropH <= 0 || $leftPx + $cropW > $width || $topPx + $cropH > $height) {
            fwrite(STDERR, "[-] Layout content box out of bounds for page {$pageNumber} "
                . "(image {$width}x{$height}px, box L{$leftPx} T{$topPx} W{$cropW} H{$cropH}px)\n");
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
        // Pure per-channel component subtraction: result = abs(file1 - file2)
        // at every pixel. Identical pixels go to black, maximally different
        // pixels (e.g. ink vs no ink) go to white/bright, and partial
        // differences (sub-pixel anti-aliasing, partial ink coverage) land
        // proportionally in between. No thresholding, masking, or blob
        // consolidation -- this is a continuous delta, not a classification.
        //
        // Note this deliberately drops all page context: unchanged content,
        // margins, and background all collapse to black along with it, so
        // the output reads as "just the delta" rather than "a page with a
        // change highlighted on it." If you want the surrounding content
        // faintly visible for orientation, composite a dimmed copy of
        // $file2 underneath first (DstOver at low opacity) before returning.
        $cmd = sprintf(
            'convert %s %s -compose Difference -composite %s 2>&1',
            escapeshellarg($file1),
            escapeshellarg($file2),
            escapeshellarg($outPath)
        );
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0 || !file_exists($outPath)) {
            fwrite(STDERR, "[-] diff image generation failed: " . implode(' ', $output) . "\n");
            return false;
        }
        return true;
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
// $alignment = needlemanWunschAlign($oldPageSequence, $newPageSequence); // caller-supplied
// // e.g. [[1,1], [2,2], [null,3], [3,4], [4,null], [5,5], ...]
//
// $engine = new PdfFastDiffEngine(
//     '/path/to/old.pdf',
//     '/path/to/new.pdf',
//     '/var/www/app/storage/diffs/job-123',
//     alignment: $alignment,
//     layoutOld: $layoutOld,   // [$pageNo => ['content_left' => ..., 'binding_side' => ..., ...], ...]
//     layoutNew: $layoutNew,
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
