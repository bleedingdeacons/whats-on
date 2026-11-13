#!/usr/bin/env php
<?php

/**
 * Build Script for the What's On WordPress Plugin
 *
 * The suite's standard build script (see lifelines/build.php), adapted for a
 * plugin whose Visual Builder and front-end assets are compiled by npm:
 *
 *   - the npm asset build runs before the archive is assembled, so the zip
 *     always carries assets/build/ compiled from the source being shipped;
 *   - excludes are anchored at the plugin root. The shared script matches any
 *     path containing "/build", which here would drop assets/build/ — the
 *     compiled bundles the plugin cannot run without;
 *   - Composer is invoked through the binary that is running this script
 *     (COMPOSER_BINARY) when there is one, because it is not on PATH everywhere.
 *
 * Usage: php build.php [target] [options]
 *
 * Targets:
 *   build:production   Create production archive (default)
 *   build:dev          Create development archive (includes tests)
 *   clean              Clean build directory only
 *
 * Options:
 *   --version=X.X      Override version number
 *   --clean            Clean build directory before building
 *   --skip-assets      Do not run the npm asset build (use assets/build as committed)
 *   --help             Show this help message
 */

declare(strict_types=1);

class PluginBuilder
{
    private string $pluginDir;
    private string $buildDir;
    private string $version;
    private string $pluginName = 'whats-on';
    private bool $isWindows;

    /**
     * Excluded from production builds. Each entry is a path relative to the
     * plugin root (a directory excludes everything under it), or a glob such as
     * "*.md", which is matched against the file's basename at any depth.
     */
    private array $productionExcludes = [
        // Version control
        '.git',
        '.gitignore',
        '.gitattributes',
        '.github',

        // IDE/Editor
        '.idea',
        '.vscode',
        '.editorconfig',

        // Build artifacts (the root build/ directory only — assets/build ships)
        'build',
        'dist',

        // Tests
        'tests',
        'phpunit.xml',
        'phpunit.xml.dist',
        '.phpunit.result.cache',
        '.phpunit.cache',

        // Asset sources and their toolchain (assets/build is what ships)
        'assets/src',
        'node_modules',
        'build.mjs',
        'package.json',
        'package-lock.json',

        // Development helpers (Local site wiring, sample data)
        'bin',

        // Composer files (vendor/ is staged separately, no-dev)
        'composer.json',
        'composer.lock',
        'vendor',

        // Code style / static analysis
        '.phpcs.xml',
        '.phpcs.xml.dist',
        'phpstan.neon',
        'phpstan.neon.dist',
        'phpstan-baseline.neon',

        // Documentation
        '*.md',

        // Build script
        'build.php',

        // Never ship
        '.claude',
        '.DS_Store',
        'Thumbs.db',
    ];

    /** Excluded from dev builds. */
    private array $devExcludes = [
        '.git',
        '.idea',
        '.vscode',
        'build',
        'dist',
        'node_modules',
        '.DS_Store',
        'Thumbs.db',
        'vendor/bin',
    ];

    public function __construct()
    {
        $this->isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $this->pluginDir = $this->normalizePath(__DIR__);
        $this->buildDir = $this->pluginDir . DIRECTORY_SEPARATOR . 'build';
        $this->version = $this->getVersionFromPlugin();

        $this->checkRequirements();
    }

    private function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function checkRequirements(): void
    {
        if (!extension_loaded('zip')) {
            $this->error("PHP ZIP extension is required but not installed.");
            $this->error($this->isWindows ? "  - Enable extension=zip in php.ini" : "  - Install php-zip");
            exit(1);
        }

        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $this->error("PHP 8.0 or higher is required. Current version: " . PHP_VERSION);
            exit(1);
        }
    }

    /**
     * The Version: line in the plugin header is the single source of truth.
     */
    private function getVersionFromPlugin(): string
    {
        $mainFile = $this->pluginDir . DIRECTORY_SEPARATOR . 'whats-on.php';
        if (file_exists($mainFile)) {
            $content = (string) file_get_contents($mainFile);
            if (preg_match('/^\s*\*\s*Version:\s*([0-9][0-9.]*)/m', $content, $matches)) {
                return trim($matches[1]);
            }
        }
        return '0.0.1';
    }

    public function clean(): void
    {
        $this->log("Cleaning build directory...");
        if (is_dir($this->buildDir)) {
            $this->deleteDirectory($this->buildDir);
        }
        $this->log("Build directory cleaned");
    }

    public function build(string $type = 'production', ?string $customVersion = null, bool $skipAssets = false): void
    {
        if ($customVersion) {
            $this->version = $customVersion;
        }

        $this->log("Building {$type} archive for version {$this->version}...");
        $this->log("Platform: " . PHP_OS . " (" . ($this->isWindows ? "Windows" : "Unix-like") . ")");

        if (!is_dir($this->pluginDir . DIRECTORY_SEPARATOR . 'vendor')) {
            $this->log("vendor/ not found. Running composer install...");
            $this->runCommand($this->composerCommand('install --no-dev --optimize-autoloader --no-interaction'), $this->pluginDir, 'Composer install');
        }

        if ($skipAssets) {
            $this->log("Skipping the npm asset build (--skip-assets); shipping assets/build as it is");
        } else {
            $this->buildAssets();
        }
        $this->assertAssetsPresent();

        if (!is_dir($this->buildDir) && !mkdir($this->buildDir, 0755, true)) {
            $this->error("Failed to create build directory: {$this->buildDir}");
            exit(1);
        }

        $archiveName = $this->buildDir . DIRECTORY_SEPARATOR . $this->pluginName;
        if ($type === 'dev') {
            $archiveName .= '-dev';
            $excludes = $this->devExcludes;
        } else {
            $archiveName .= '-production';
            $excludes = $this->productionExcludes;
        }

        $safeVersion = preg_replace('/[^a-zA-Z0-9._-]/', '-', $this->version);
        $archiveName .= '-' . $safeVersion . '.zip';

        $this->syncReadmeVersion();
        $this->syncReadmeMarkdownVersion();
        $this->syncBuildDate();

        $stagedVendor = $type === 'dev' ? null : $this->stageProductionVendor();

        $this->createZip($archiveName, $excludes, $stagedVendor);
        $this->cleanVendorStaging();

        $this->log("Archive created successfully: " . basename($archiveName));
        $fileSize = filesize($archiveName);
        if ($fileSize !== false) {
            $this->log("File size: " . $this->formatBytes($fileSize));
        }
        $this->log("Location: {$archiveName}");
    }

    /**
     * Compile assets/src into assets/build with the project's own esbuild
     * config (production, minified). `npm ci` first when node_modules is
     * missing, so a clean checkout builds without a manual step.
     */
    private function buildAssets(): void
    {
        if (!file_exists($this->pluginDir . DIRECTORY_SEPARATOR . 'package.json')) {
            return;
        }

        if (!is_dir($this->pluginDir . DIRECTORY_SEPARATOR . 'node_modules')) {
            $this->log("node_modules/ not found. Running npm ci...");
            $this->runCommand('npm ci --no-audit --no-fund', $this->pluginDir, 'npm ci');
        }

        $this->log("Building assets (npm run build)...");
        $this->runCommand('npm run build', $this->pluginDir, 'npm run build');
    }

    private function assertAssetsPresent(): void
    {
        foreach (['builder.js', 'carousel.js', 'carousel.css'] as $asset) {
            $path = $this->pluginDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . $asset;
            if (!is_file($path) || filesize($path) === 0) {
                $this->error("assets/build/{$asset} is missing or empty; the plugin cannot run without it.");
                exit(1);
            }
        }
    }

    /**
     * Composer as a shell command: the binary running this script when Composer
     * started us (it exports COMPOSER_BINARY to scripts), otherwise `composer`.
     */
    private function composerCommand(string $args): string
    {
        $binary = getenv('COMPOSER_BINARY');
        if (is_string($binary) && $binary !== '' && is_file($binary)) {
            return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($binary) . ' ' . $args;
        }
        return 'composer ' . $args;
    }

    private function runCommand(string $command, string $cwd, string $label): void
    {
        $previous = getcwd();
        chdir($cwd);
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        if ($previous !== false) {
            chdir($previous);
        }

        if ($returnCode !== 0) {
            $this->error("{$label} failed with code {$returnCode}");
            foreach ($output as $line) {
                $this->error("  {$line}");
            }
            exit(1);
        }
    }

    /**
     * Stage a clean production vendor/ (composer install --no-dev) in build/,
     * leaving the working vendor/ — which holds the test tooling — untouched.
     */
    private function stageProductionVendor(): ?string
    {
        $composerFile = $this->pluginDir . DIRECTORY_SEPARATOR . 'composer.json';
        if (!file_exists($composerFile)) {
            $this->log("No composer.json - production archive will ship no vendor/");
            return null;
        }

        $stagingDir = $this->buildDir . DIRECTORY_SEPARATOR . '.vendor-staging';
        if (is_dir($stagingDir)) {
            $this->deleteDirectory($stagingDir);
        }
        if (!mkdir($stagingDir, 0755, true) && !is_dir($stagingDir)) {
            $this->error("Failed to create vendor staging directory: {$stagingDir}");
            exit(1);
        }

        copy($composerFile, $stagingDir . DIRECTORY_SEPARATOR . 'composer.json');
        $lockFile = $this->pluginDir . DIRECTORY_SEPARATOR . 'composer.lock';
        if (file_exists($lockFile)) {
            copy($lockFile, $stagingDir . DIRECTORY_SEPARATOR . 'composer.lock');
        }

        // Not --optimize-autoloader: the staging directory has no src/ to scan.
        // The plain PSR-4 map resolves WhatsOn\ to <plugin>/src at runtime,
        // because Composer writes it relative to vendor/'s parent directory.
        $this->log("Staging production vendor (no-dev)...");
        $this->runCommand(
            $this->composerCommand('install --no-dev --no-interaction --no-scripts --working-dir=' . escapeshellarg($stagingDir)),
            $this->pluginDir,
            'Composer (no-dev) install'
        );

        $stagedVendor = $stagingDir . DIRECTORY_SEPARATOR . 'vendor';
        if (!is_dir($stagedVendor)) {
            $this->log("No production dependencies - archive will ship no vendor/");
            return null;
        }

        $this->log("Staged production vendor/ (no-dev) ready");
        return $stagedVendor;
    }

    private function cleanVendorStaging(): void
    {
        $stagingDir = $this->buildDir . DIRECTORY_SEPARATOR . '.vendor-staging';
        if (is_dir($stagingDir)) {
            $this->deleteDirectory($stagingDir);
        }
    }

    private function createZip(string $archiveName, array $excludes, ?string $stagedVendor = null): void
    {
        $this->log("Creating archive: " . basename($archiveName));

        if (file_exists($archiveName)) {
            unlink($archiveName);
        }

        $zip = new ZipArchive();
        $result = $zip->open($archiveName, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            $this->error("Failed to create ZIP archive. Error code: {$result}");
            exit(1);
        }

        $fileCount = 0;
        foreach ($this->getFiles($this->pluginDir, $excludes) as $file) {
            $relativePath = $this->pluginName . '/' . str_replace('\\', '/', substr($file, strlen($this->pluginDir) + 1));

            if (is_file($file)) {
                // Added as a string so no file handles stay open (Windows fails with many).
                $contents = file_get_contents($file);
                if ($contents === false) {
                    $this->error("Warning: Could not read file: {$file}");
                    continue;
                }
                $zip->addFromString($relativePath, $contents);
                $fileCount++;
            } elseif (is_dir($file)) {
                $zip->addEmptyDir($relativePath);
            }
        }

        if ($stagedVendor !== null && is_dir($stagedVendor)) {
            foreach ($this->getFiles($stagedVendor, []) as $stagedFile) {
                if (!is_file($stagedFile)) {
                    continue;
                }
                $stagedRelative = str_replace('\\', '/', substr($stagedFile, strlen($stagedVendor) + 1));
                $contents = file_get_contents($stagedFile);
                if ($contents === false) {
                    continue;
                }
                $zip->addFromString($this->pluginName . '/vendor/' . $stagedRelative, $contents);
                $fileCount++;
            }
        }

        if (!$zip->close()) {
            $this->error("Failed to write ZIP archive to: {$archiveName}");
            exit(1);
        }

        $this->log("Added {$fileCount} files to archive");
    }

    /**
     * All files under $dir, skipping excluded paths without descending into them.
     */
    private function getFiles(string $dir, array $excludes): array
    {
        $directory = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            function (SplFileInfo $file) use ($dir, $excludes): bool {
                $relative = substr($file->getPathname(), strlen($dir) + 1);
                return !$this->shouldExclude($relative, $excludes);
            }
        );

        $files = [];
        foreach (new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST) as $file) {
            $files[] = $file->getPathname();
        }
        return $files;
    }

    /**
     * Root-anchored matching: "assets/src" excludes that directory and
     * everything under it, never a same-named directory deeper down; a glob
     * ("*.md") is matched against the basename at any depth.
     */
    private function shouldExclude(string $path, array $excludes): bool
    {
        $path = str_replace('\\', '/', $path);

        foreach ($excludes as $exclude) {
            $exclude = str_replace('\\', '/', $exclude);

            if (str_contains($exclude, '*')) {
                $pattern = '/^' . str_replace('\*', '.*', preg_quote($exclude, '/')) . '$/i';
                if (preg_match($pattern, basename($path)) || preg_match($pattern, $path)) {
                    return true;
                }
                continue;
            }

            if ($path === $exclude || str_starts_with($path, $exclude . '/')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Update the Stable tag in readme.txt to match the plugin version.
     */
    private function syncReadmeVersion(): void
    {
        $readmeFile = $this->pluginDir . DIRECTORY_SEPARATOR . 'readme.txt';
        if (!file_exists($readmeFile)) {
            $this->log("No readme.txt found — skipping version sync");
            return;
        }

        $content = (string) file_get_contents($readmeFile);
        $updated = preg_replace('/^Stable tag:[ \t]*[^\r\n]*(?=\r?$)/mi', 'Stable tag: ' . $this->version, $content, -1, $count);

        if ($count > 0 && $updated !== null) {
            file_put_contents($readmeFile, $updated);
            $this->log("Updated readme.txt Stable tag to {$this->version}");
        } else {
            $this->log("No Stable tag found in readme.txt — skipping version sync");
        }
    }

    /**
     * Update the version badge (and any legacy **Version:** line) in README.md.
     */
    private function syncReadmeMarkdownVersion(): void
    {
        $readmeFile = $this->pluginDir . DIRECTORY_SEPARATOR . 'README.md';
        if (!file_exists($readmeFile)) {
            $this->log("No README.md found — skipping version sync");
            return;
        }

        $content = (string) file_get_contents($readmeFile);
        $updated = preg_replace('~(img\.shields\.io/badge/version-)[^-\s)]+(-blue)~', '${1}' . $this->version . '${2}', $content, -1, $badgeCount);
        if ($updated === null) {
            $this->error("Failed to rewrite the version badge in README.md");
            return;
        }
        $updated = preg_replace('/^\*\*Version:\*\*[ \t]*[^\r\n]*(?=\r?$)/m', '**Version:** ' . $this->version, $updated, -1, $lineCount);
        if ($updated === null) {
            $this->error("Failed to rewrite the **Version:** line in README.md");
            return;
        }

        if ($badgeCount + $lineCount > 0) {
            file_put_contents($readmeFile, $updated);
            $this->log("Updated README.md version to {$this->version} ({$badgeCount} badge, {$lineCount} line)");
        } else {
            $this->log("No version badge or **Version:** line in README.md — skipping version sync");
        }
    }

    /**
     * Update (or insert after Stable tag) the Build date in readme.txt,
     * preserving the file's line endings.
     */
    private function syncBuildDate(): void
    {
        $readmeFile = $this->pluginDir . DIRECTORY_SEPARATOR . 'readme.txt';
        if (!file_exists($readmeFile)) {
            $this->log("No readme.txt found — skipping build date sync");
            return;
        }

        $content = (string) file_get_contents($readmeFile);

        // The build machine's wall clock, stated explicitly rather than left to ini config.
        $buildDate = (new DateTime('now', new DateTimeZone('Europe/London')))->format('Y/m/d H:i:s');

        $updated = preg_replace('/^Build date:[ \t]*[^\r\n]*(?=\r?$)/mi', 'Build date: ' . $buildDate, $content, 1, $count);
        if ($count > 0 && $updated !== null) {
            file_put_contents($readmeFile, $updated);
            $this->log("Updated readme.txt Build date to {$buildDate}");
            return;
        }

        $updated = preg_replace_callback(
            '/^(Stable tag:[ \t]*[^\r\n]*)(\r?\n)/mi',
            static fn (array $m): string => $m[1] . $m[2] . 'Build date: ' . $buildDate . $m[2],
            $content,
            1,
            $count
        );
        if ($count > 0 && $updated !== null) {
            file_put_contents($readmeFile, $updated);
            $this->log("Inserted Build date {$buildDate} into readme.txt");
        } else {
            $this->log("No Stable tag line found in readme.txt — skipping build date sync");
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff((array) scandir($dir), ['.', '..']) as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if ($this->isWindows && file_exists($path)) {
                chmod($path, 0777);
            }
            if (is_dir($path) && !is_link($path)) {
                $this->deleteDirectory($path);
            } elseif (!unlink($path)) {
                $this->error("Failed to delete file: {$path}");
            }
        }

        if (!rmdir($dir)) {
            $this->error("Failed to remove directory: {$dir}");
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function log(string $message): void
    {
        echo "[BUILD] {$message}\n";
    }

    private function error(string $message): void
    {
        echo "[ERROR] {$message}\n";
    }

    public function showHelp(): void
    {
        echo <<<HELP

What's On WordPress Plugin Build Script
=======================================
Platform: {$this->platform()}

Usage: php build.php [target] [options]

Targets:
  build:production    Create production archive (default)
  build:dev           Create development archive (includes tests)
  clean               Clean build directory only

Options:
  --type=production   Create production archive (alternative syntax)
  --type=dev          Create development archive (alternative syntax)
  --version=X.X       Override version number (default: from plugin file)
  --clean             Clean build directory before building
  --skip-assets       Do not run the npm asset build
  --help              Show this help message

Composer scripts:
  composer build:production   ->  build/whats-on-production-<version>.zip
  composer build:dev          ->  build/whats-on-dev-<version>.zip
  composer build:clean        ->  remove build/

The npm asset build (npm run build) runs first, so Node.js and npm must be on
PATH. node_modules/ is installed with `npm ci` when it is missing.

HELP;
    }

    private function platform(): string
    {
        return PHP_OS . ', PHP ' . PHP_VERSION;
    }
}

$options = getopt('', ['type:', 'version:', 'clean', 'clean-only', 'skip-assets', 'help']);

$target = null;
foreach ($argv as $arg) {
    if ($arg === 'build:production' || $arg === 'production') {
        $target = 'production';
    } elseif ($arg === 'build:dev' || $arg === 'dev') {
        $target = 'dev';
    }
}

$builder = new PluginBuilder();

if (isset($options['help']) || in_array('--help', $argv, true) || in_array('-h', $argv, true) || in_array('help', $argv, true)) {
    $builder->showHelp();
    exit(0);
}

if (isset($options['clean-only']) || in_array('clean', $argv, true)) {
    $builder->clean();
    if ($target === null && !isset($options['type'])) {
        exit(0);
    }
}

if (isset($options['clean'])) {
    $builder->clean();
}

$type = $target ?? ($options['type'] ?? 'production');
$builder->build($type, $options['version'] ?? null, isset($options['skip-assets']));
