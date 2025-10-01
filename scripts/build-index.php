#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Build marketplace index from plugins directory
 *
 * Skanuje katalog plugins/ i generuje:
 * - dist/index.json - kompletny index wszystkich pluginów
 * - dist/categories.json - pluginy pogrupowane według kategorii
 * - dist/plugins/{name}.json - szczegółowe info każdego pluginu
 * - dist/categories/{category}.json - pluginy w danej kategorii
 */

require_once __DIR__ . '/../vendor/autoload.php';

final class MarketplaceIndexBuilder
{
    private const PLUGINS_DIR = __DIR__ . '/../plugins';
    private const DIST_DIR = __DIR__ . '/../dist';

    private array $plugins = [];
    private array $categories = [];
    private array $errors = [];

    public function build(): void
    {
        echo "🔨 Building marketplace index...\n\n";

        $this->ensureDistDirectory();
        $this->scanPlugins();
        $this->generateIndexFiles();
        $this->generatePluginFiles();
        $this->generateCategoryFiles();
        $this->generateCategoriesIndex();
        $this->printSummary();

        if (!empty($this->errors)) {
            echo "\n⚠️  Warnings:\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }

        echo "\n✅ Marketplace index built successfully!\n";
    }

    private function ensureDistDirectory(): void
    {
        $dirs = [
                self::DIST_DIR,
                self::DIST_DIR . '/plugins',
                self::DIST_DIR . '/categories',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                echo "📁 Created directory: $dir\n";
            }
        }
    }

    private function scanPlugins(): void
    {
        echo "📦 Scanning plugins directory...\n";

        if (!is_dir(self::PLUGINS_DIR)) {
            throw new RuntimeException('Plugins directory not found: ' . self::PLUGINS_DIR);
        }

        $pluginDirs = glob(self::PLUGINS_DIR . '/*', GLOB_ONLYDIR);

        foreach ($pluginDirs as $pluginDir) {
            $pluginName = basename($pluginDir);
            echo "  Processing: $pluginName\n";

            try {
                $plugin = $this->processPlugin($pluginDir, $pluginName);
                $this->plugins[$pluginName] = $plugin;

                // Grupuj według kategorii
                $category = $plugin['category'] ?? 'other';
                if (!isset($this->categories[$category])) {
                    $this->categories[$category] = [];
                }
                $this->categories[$category][] = $pluginName;

            } catch (Exception $e) {
                $this->errors[] = "Plugin '$pluginName': " . $e->getMessage();
            }
        }

        echo "  Found " . count($this->plugins) . " plugins\n\n";
    }

    private function processPlugin(string $pluginDir, string $pluginName): array
    {
        $manifestPath = $pluginDir . '/plugin.json';

        if (!file_exists($manifestPath)) {
            throw new RuntimeException("Missing plugin.json");
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in plugin.json: " . json_last_error_msg());
        }

        // Waliduj wymagane pola
        $required = ['name', 'version', 'description', 'author', 'license', 'mainClass', 'releases'];
        foreach ($required as $field) {
            if (!isset($manifest[$field]) || empty($manifest[$field])) {
                throw new RuntimeException("Missing required field: $field");
            }
        }

        // Waliduj releases
        if (!is_array($manifest['releases']) || empty($manifest['releases'])) {
            throw new RuntimeException("releases must be a non-empty array");
        }

        foreach ($manifest['releases'] as $release) {
            if (!isset($release['version']) || !isset($release['downloadUrl'])) {
                throw new RuntimeException("Each release must have 'version' and 'downloadUrl'");
            }
        }

        // Dodaj dodatkowe metadane
        $plugin = $manifest;

        // Sortuj wersje (najnowsza pierwsza)
        $versions = $manifest['releases'];
        usort($versions, function($a, $b) {
            return version_compare($b['version'], $a['version']);
        });

        $plugin['versions'] = $versions;
        $plugin['latestVersion'] = $versions[0]['version'] ?? $manifest['version'];

        // README
        $readmePath = $pluginDir . '/README.md';
        if (file_exists($readmePath)) {
            $plugin['readme'] = file_get_contents($readmePath);
        }

        // CHANGELOG
        $changelogPath = $pluginDir . '/CHANGELOG.md';
        if (file_exists($changelogPath)) {
            $plugin['changelog'] = file_get_contents($changelogPath);
        }

        // Screenshot
        $screenshotExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg'];
        foreach ($screenshotExtensions as $ext) {
            $screenshotPath = $pluginDir . "/screenshot.$ext";
            if (file_exists($screenshotPath)) {
                $plugin['screenshot'] = $this->getPublicUrl("plugins/$pluginName/screenshot.$ext");
                break;
            }
        }

        // Dodaj metadane marketplace
        $plugin['marketplace'] = [
                'indexed' => date('c'),
                'downloads' => $manifest['extra']['downloads'] ?? 0,
                'rating' => $manifest['extra']['rating'] ?? 0,
                'featured' => $manifest['extra']['featured'] ?? false,
        ];

        return $plugin;
    }

    private function generateIndexFiles(): void
    {
        echo "📝 Generating index.json...\n";

        $index = [
                'version' => '1.0',
                'generated' => date('c'),
                'totalPlugins' => count($this->plugins),
                'plugins' => array_map(function($plugin) {
                    // Index zawiera tylko podstawowe info
                    return [
                            'name' => $plugin['name'],
                            'displayName' => $plugin['displayName'] ?? $plugin['name'],
                            'version' => $plugin['latestVersion'],
                            'description' => $plugin['description'],
                            'author' => $plugin['author'],
                            'category' => $plugin['category'] ?? 'other',
                            'tags' => $plugin['tags'] ?? [],
                            'homepage' => $plugin['homepage'] ?? null,
                            'screenshot' => $plugin['screenshot'] ?? null,
                            'downloads' => $plugin['marketplace']['downloads'] ?? 0,
                            'rating' => $plugin['marketplace']['rating'] ?? 0,
                            'featured' => $plugin['marketplace']['featured'] ?? false,
                            'detailsUrl' => $this->getPublicUrl("plugins/{$plugin['name']}.json"),
                    ];
                }, $this->plugins),
        ];

        $this->writeJson(self::DIST_DIR . '/index.json', $index);
    }

    private function generatePluginFiles(): void
    {
        echo "📝 Generating individual plugin files...\n";

        foreach ($this->plugins as $name => $plugin) {
            $filename = self::DIST_DIR . "/plugins/$name.json";
            $this->writeJson($filename, $plugin);
        }
    }

    private function generateCategoryFiles(): void
    {
        echo "📝 Generating category files...\n";

        foreach ($this->categories as $category => $pluginNames) {
            $plugins = array_map(function($name) {
                return $this->plugins[$name];
            }, $pluginNames);

            $categoryData = [
                    'category' => $category,
                    'count' => count($plugins),
                    'plugins' => array_map(function($plugin) {
                        return [
                                'name' => $plugin['name'],
                                'displayName' => $plugin['displayName'] ?? $plugin['name'],
                                'version' => $plugin['latestVersion'],
                                'description' => $plugin['description'],
                                'author' => $plugin['author'],
                                'detailsUrl' => $this->getPublicUrl("plugins/{$plugin['name']}.json"),
                        ];
                    }, $plugins),
            ];

            $filename = self::DIST_DIR . "/categories/$category.json";
            $this->writeJson($filename, $categoryData);
        }
    }

    private function generateCategoriesIndex(): void
    {
        echo "📝 Generating categories.json...\n";

        $categoriesIndex = [
                'version' => '1.0',
                'generated' => date('c'),
                'categories' => array_map(function($category, $pluginNames) {
                    return [
                            'name' => $category,
                            'displayName' => ucfirst($category),
                            'count' => count($pluginNames),
                            'url' => $this->getPublicUrl("categories/$category.json"),
                    ];
                }, array_keys($this->categories), $this->categories),
        ];

        $this->writeJson(self::DIST_DIR . '/categories.json', $categoriesIndex);
    }

    private function writeJson(string $filename, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Failed to encode JSON for $filename: " . json_last_error_msg());
        }

        if (file_put_contents($filename, $json) === false) {
            throw new RuntimeException("Failed to write file: $filename");
        }
    }

    private function getPublicUrl(string $path): string
    {
        // GitHub Pages URL - zmień na swój
        $baseUrl = getenv('MARKETPLACE_URL') ?: 'https://YOUR-USERNAME.github.io/lemric-plugin-marketplace';
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function printSummary(): void
    {
        echo "\n📊 Summary:\n";
        echo "  Total plugins: " . count($this->plugins) . "\n";
        echo "  Categories: " . count($this->categories) . "\n";

        foreach ($this->categories as $category => $plugins) {
            echo "    - $category: " . count($plugins) . " plugins\n";
        }

        $featured = array_filter($this->plugins, fn($p) => $p['marketplace']['featured'] ?? false);
        echo "  Featured plugins: " . count($featured) . "\n";
    }
}

// Run builder
try {
    $builder = new MarketplaceIndexBuilder();
    $builder->build();
    exit(0);
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}