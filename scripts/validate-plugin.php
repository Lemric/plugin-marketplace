#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Validate plugin structure and manifest
 * Usage: php validate-plugin.php plugins/plugin-name
 */

final class PluginValidator
{
    private const REQUIRED_FIELDS = [
            'name', 'version', 'description', 'author', 'license', 'mainClass'
    ];

    private const RECOMMENDED_FIELDS = [
            'displayName', 'homepage', 'category', 'tags'
    ];

    private const VALID_CATEGORIES = [
            'security', 'utilities', 'integrations', 'admin', 'content',
            'analytics', 'payment', 'communication', 'development', 'other'
    ];

    private const DANGEROUS_FUNCTIONS = [
            'eval', 'exec', 'system', 'shell_exec', 'passthru',
            'proc_open', 'popen', 'pcntl_exec', 'assert'
    ];

    private array $errors = [];
    private array $warnings = [];
    private string $pluginDir;

    public function __construct(string $pluginDir)
    {
        $this->pluginDir = rtrim($pluginDir, '/');
    }

    public function validate(): bool
    {
        echo "🔍 Validating plugin: {$this->pluginDir}\n\n";

        $this->validateDirectory();
        $manifest = $this->validateManifest();

        if ($manifest) {
            $this->validateVersionFormat($manifest['version']);
            $this->validateCategory($manifest);
            $this->validateAutoload($manifest);
            $this->validateRequirements($manifest);
        }

        $this->validateReleases();
        $this->validateDocumentation();
        $this->printResults();

        return empty($this->errors);
    }

    private function validateDirectory(): void
    {
        if (!is_dir($this->pluginDir)) {
            $this->errors[] = "Plugin directory does not exist: {$this->pluginDir}";
            return;
        }

        // Sprawdź czy nazwa folderu jest prawidłowa (kebab-case)
        $dirName = basename($this->pluginDir);
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $dirName)) {
            $this->errors[] = "Invalid directory name. Use kebab-case (lowercase with hyphens): $dirName";
        }
    }

    private function validateManifest(): ?array
    {
        $manifestPath = $this->pluginDir . '/plugin.json';

        if (!file_exists($manifestPath)) {
            $this->errors[] = "Missing plugin.json file";
            return null;
        }

        $content = file_get_contents($manifestPath);
        $manifest = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = "Invalid JSON in plugin.json: " . json_last_error_msg();
            return null;
        }

        // Sprawdź wymagane pola
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($manifest[$field]) || empty($manifest[$field])) {
                $this->errors[] = "Missing required field: $field";
            }
        }

        // Sprawdź zalecane pola
        foreach (self::RECOMMENDED_FIELDS as $field) {
            if (!isset($manifest[$field]) || empty($manifest[$field])) {
                $this->warnings[] = "Missing recommended field: $field";
            }
        }

        // Walidacja pól
        if (isset($manifest['name']) && !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $manifest['name'])) {
            $this->errors[] = "Invalid plugin name format. Use kebab-case: {$manifest['name']}";
        }

        if (isset($manifest['displayName']) && strlen($manifest['displayName']) > 50) {
            $this->warnings[] = "Display name too long (max 50 chars): {$manifest['displayName']}";
        }

        if (isset($manifest['description']) && strlen($manifest['description']) > 200) {
            $this->warnings[] = "Description too long (max 200 chars recommended)";
        }

        // Walidacja email
        if (isset($manifest['authorEmail']) && !filter_var($manifest['authorEmail'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "Invalid author email: {$manifest['authorEmail']}";
        }

        // Walidacja URL
        $urlFields = ['homepage', 'repository', 'documentation', 'authorUrl'];
        foreach ($urlFields as $field) {
            if (isset($manifest[$field]) && !filter_var($manifest[$field], FILTER_VALIDATE_URL)) {
                $this->errors[] = "Invalid URL in field '$field': {$manifest[$field]}";
            }
        }

        return $manifest;
    }

    private function validateVersionFormat(string $version): void
    {
        if (!preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9.-]+)?$/', $version)) {
            $this->errors[] = "Invalid version format: $version (use semantic versioning: X.Y.Z)";
        }
    }

    private function validateCategory(array $manifest): void
    {
        if (!isset($manifest['category'])) {
            return;
        }

        if (!in_array($manifest['category'], self::VALID_CATEGORIES, true)) {
            $this->errors[] = "Invalid category: {$manifest['category']}. Valid: " . implode(', ', self::VALID_CATEGORIES);
        }
    }

    private function validateAutoload(array $manifest): void
    {
        if (!isset($manifest['autoload']['psr-4'])) {
            $this->errors[] = "Missing PSR-4 autoload configuration";
            return;
        }

        $psr4 = $manifest['autoload']['psr-4'];

        if (empty($psr4)) {
            $this->errors[] = "Empty PSR-4 autoload configuration";
            return;
        }

        // Sprawdź czy namespace kończy się na backslash
        foreach (array_keys($psr4) as $namespace) {
            if (!str_ends_with($namespace, '\\')) {
                $this->errors[] = "PSR-4 namespace must end with backslash: $namespace";
            }
        }
    }

    private function validateRequirements(array $manifest): void
    {
        if (!isset($manifest['requires'])) {
            $this->warnings[] = "Missing 'requires' section (recommended to specify PHP/Symfony version)";
            return;
        }

        // Sprawdź PHP version
        if (isset($manifest['requires']['php'])) {
            $phpVersion = $manifest['requires']['php'];
            if (!preg_match('/^[><=^~\s\d\.]+$/', $phpVersion)) {
                $this->errors[] = "Invalid PHP version constraint: $phpVersion";
            }
        } else {
            $this->warnings[] = "Missing PHP version requirement";
        }

        // Sprawdź Symfony version
        if (isset($manifest['requires']['symfony'])) {
            $symfonyVersion = $manifest['requires']['symfony'];
            if (!preg_match('/^[><=^~\s\d\.]+$/', $symfonyVersion)) {
                $this->errors[] = "Invalid Symfony version constraint: $symfonyVersion";
            }
        } else {
            $this->warnings[] = "Missing Symfony version requirement";
        }
    }

    private function validateReleases(): void
    {
        $manifestPath = $this->pluginDir . '/plugin.json';

        if (!file_exists($manifestPath)) {
            return; // Already checked in validateManifest
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (!isset($manifest['releases'])) {
            $this->errors[] = "Missing 'releases' array in plugin.json";
            return;
        }

        if (!is_array($manifest['releases']) || empty($manifest['releases'])) {
            $this->errors[] = "'releases' must be a non-empty array";
            return;
        }

        foreach ($manifest['releases'] as $index => $release) {
            $this->validateRelease($release, $index);
        }
    }

    private function validateRelease(array $release, int $index): void
    {
        // Check required fields
        if (!isset($release['version'])) {
            $this->errors[] = "Release #{$index}: missing 'version' field";
        } elseif (!preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9.-]+)?$/', $release['version'])) {
            $this->errors[] = "Release #{$index}: invalid version format: {$release['version']}";
        }

        if (!isset($release['downloadUrl'])) {
            $this->errors[] = "Release #{$index}: missing 'downloadUrl' field";
        } elseif (!filter_var($release['downloadUrl'], FILTER_VALIDATE_URL)) {
            $this->errors[] = "Release #{$index}: invalid downloadUrl: {$release['downloadUrl']}";
        } elseif (!str_starts_with($release['downloadUrl'], 'https://')) {
            $this->errors[] = "Release #{$index}: downloadUrl must use HTTPS";
        } else {
            // Test if URL is accessible
            $this->testDownloadUrl($release['downloadUrl'], $index);
        }

        if (!isset($release['releaseDate'])) {
            $this->warnings[] = "Release #{$index}: missing 'releaseDate' field";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $release['releaseDate'])) {
            $this->errors[] = "Release #{$index}: invalid releaseDate format (use YYYY-MM-DD)";
        }

        // Optional but recommended
        if (!isset($release['checksum'])) {
            $this->warnings[] = "Release #{$index}: missing 'checksum' (recommended for integrity verification)";
        } elseif (!preg_match('/^sha256:[a-f0-9]{64}$/i', $release['checksum'])) {
            $this->errors[] = "Release #{$index}: invalid checksum format (use 'sha256:...')";
        }

        if (!isset($release['size'])) {
            $this->warnings[] = "Release #{$index}: missing 'size' field";
        } elseif (!is_int($release['size']) || $release['size'] <= 0) {
            $this->errors[] = "Release #{$index}: 'size' must be a positive integer";
        }
    }

    private function testDownloadUrl(string $url, int $index): void
    {
        // Use HEAD request to check if URL is accessible
        $context = stream_context_create([
                'http' => [
                        'method' => 'HEAD',
                        'timeout' => 5,
                        'follow_location' => true,
                ],
                'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                ],
        ]);

        $headers = @get_headers($url, true, $context);

        if ($headers === false) {
            $this->warnings[] = "Release #{$index}: Could not verify downloadUrl accessibility: {$url}";
            return;
        }

        $statusCode = null;
        if (isset($headers[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headers[0], $matches);
            $statusCode = isset($matches[1]) ? (int)$matches[1] : null;
        }

        if ($statusCode !== 200) {
            $this->errors[] = "Release #{$index}: downloadUrl returned HTTP {$statusCode}: {$url}";
        }

        // Check content type
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
        if (is_array($contentType)) {
            $contentType = end($contentType);
        }

        if (!str_contains($contentType, 'application/zip') &&
                !str_contains($contentType, 'application/octet-stream')) {
            $this->warnings[] = "Release #{$index}: downloadUrl Content-Type is not application/zip (got: {$contentType})";
        }
    }

    private function validateDocumentation(): void
    {
        // README
        $readmePath = $this->pluginDir . '/README.md';
        if (!file_exists($readmePath)) {
            $this->warnings[] = "Missing README.md file";
        } else {
            $content = file_get_contents($readmePath);
            if (strlen($content) < 100) {
                $this->warnings[] = "README.md is too short (less than 100 characters)";
            }
        }

        // CHANGELOG
        $changelogPath = $this->pluginDir . '/CHANGELOG.md';
        if (!file_exists($changelogPath)) {
            $this->warnings[] = "Missing CHANGELOG.md file (recommended)";
        }
    }

    private function printResults(): void
    {
        echo "\n";

        if (!empty($this->errors)) {
            echo "❌ Errors:\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }

        if (!empty($this->warnings)) {
            echo "\n⚠️  Warnings:\n";
            foreach ($this->warnings as $warning) {
                echo "  - $warning\n";
            }
        }

        if (empty($this->errors) && empty($this->warnings)) {
            echo "✅ Plugin validation passed!\n";
        } elseif (empty($this->errors)) {
            echo "\n✅ Plugin validation passed with warnings.\n";
        } else {
            echo "\n❌ Plugin validation failed.\n";
        }
    }

    private function recursiveRemove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemove($path) : unlink($path);
        }
        rmdir($dir);
    }
}