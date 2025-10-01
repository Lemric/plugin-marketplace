#!/usr/bin/env php
<?php

/**
 * This file is part of the Lemric package.
 * (c) Lemric
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Dominik Labudzinski <dominik@labudzinski.com>
 */
declare(strict_types=1);

/**
 * Plugin Publisher Tool.
 *
 * Helper CLI tool for plugin developers to prepare and publish plugins to marketplace
 *
 * Usage:
 *   php publish-plugin.php init                    # Create plugin template
 *   php publish-plugin.php validate /path/to/plugin # Validate plugin
 *   php publish-plugin.php package /path/to/plugin  # Package plugin to ZIP
 *   php publish-plugin.php prepare /path/to/plugin  # Full preparation for marketplace
 */
final class PluginPublisher
{
    private const array MANIFEST_TEMPLATE = [
        'name' => '',
        'displayName' => '',
        'version' => '1.0.0',
        'description' => '',
        'longDescription' => '',
        'author' => '',
        'authorEmail' => '',
        'authorUrl' => '',
        'license' => 'MIT',
        'homepage' => '',
        'repository' => '',
        'documentation' => '',
        'category' => 'utilities',
        'tags' => [],
        'mainClass' => '',
        'icon' => 'icon.svg',
        'minCompatibility' => '7.3',
        'maxCompatibility' => '7.4',
        'requires' => [
            'php' => '>=8.4',
            'symfony' => '>=7.3',
        ],
        'dependencies' => [],
        'conflicts' => [],
        'autoload' => [
            'psr-4' => [],
        ],
    ];

    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;

        return match ($command) {
            'init' => $this->initPlugin($argv[2] ?? null),
            'validate' => $this->validatePlugin($argv[2] ?? null),
            'package' => $this->packagePlugin($argv[2] ?? null),
            'prepare' => $this->preparePlugin($argv[2] ?? null),
            'help', null => $this->showHelp(),
            default => $this->showError("Unknown command: {$command}"),
        };
    }

    private function confirm(string $message): bool
    {
        $response = mb_strtolower($this->prompt($message.' (y/n)', 'n', false));

        return in_array($response, ['y', 'yes'], true);
    }

    private function createDirectory(string $path): void
    {
        mkdir($path, 0o755, true);
        echo "  ✓ Created: {$path}\n";
    }

    private function createFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
        echo "  ✓ Created: {$path}\n";
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            ++$i;
        }

        return sprintf('%.2f %s', $bytes, $units[$i]);
    }

    private function generateChangelog(string $version): string
    {
        $date = date('Y-m-d');

        return <<<MD
            # Changelog

            ## [{$version}] - {$date}

            ### Added
            - Initial release

            MD;
    }

    private function generateClassName(string $pluginName): string
    {
        $parts = explode('-', $pluginName);
        $parts = array_map('ucfirst', $parts);

        return implode('', $parts).'Plugin';
    }

    private function generateComposer(array $manifest, string $namespace): string
    {
        return json_encode([
            'name' => mb_strtolower(str_replace('\\', '/', $namespace)),
            'description' => $manifest['description'],
            'type' => 'symfony-bundle',
            'license' => $manifest['license'],
            'require' => $manifest['requires'],
            'autoload' => [
                'psr-4' => [
                    $namespace.'\\' => 'src/',
                ],
            ],
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
    }

    private function generateGitignore(): string
    {
        return <<<IGNORE
            /vendor/
            composer.lock
            .env
            .env.local
            .idea/
            .vscode/
            *.swp
            releases/

            IGNORE;
    }

    private function generateNamespace(string $pluginName): string
    {
        $parts = explode('-', $pluginName);
        $parts = array_map('ucfirst', $parts);

        return 'MyCompany\\Plugins\\'.implode('', $parts);
    }

    private function generatePluginClass(string $namespace, string $pluginName): string
    {
        $className = $this->generateClassName($pluginName);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use Lemric\\PluginBundle\\Hook\\PluginHookProviderInterface;
            use Lemric\\PluginBundle\\Hook\\HookRegistrarInterface;
            use Psr\\Log\\LoggerInterface;

            class {$className} implements PluginHookProviderInterface
            {
                public function __construct(
                    private readonly LoggerInterface \$logger,
                ) {
                }
                
                public function registerHooks(HookRegistrarInterface \$registrar): void
                {
                    // Register your hooks here
                    \$this->logger->info('{$className} initialized');
                }
            }

            PHP;
    }

    private function generateReadme(array $manifest): string
    {
        return <<<MD
            # {$manifest['displayName']}

            {$manifest['description']}

            ## Installation

            ```bash
            bin/console lemric:plugin:install {$manifest['name']} --marketplace --activate
            ```

            ## Configuration

            TODO: Document configuration options

            ## Usage

            TODO: Provide usage examples

            ## License

            {$manifest['license']}

            MD;
    }

    private function generateServices(string $namespace): string
    {
        return <<<YAML
            services:
              _defaults:
                autowire: true
                autoconfigure: true
              
              {$namespace}\\:
                resource: '../src/'

            YAML;
    }

    private function getFilesForPackaging(string $pluginPath): array
    {
        $files = [];
        $excludePatterns = [
            '.git', '.github', '.idea', '.vscode', '__MACOSX',
            'tests', 'Tests', 'vendor', 'node_modules',
            '.env', '.env.local', 'releases',
            '.DS_Store', 'Thumbs.db', '*.swp', '*.swo',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pluginPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $relativePath = str_replace($pluginPath.'/', '', $path);

            // Check if file should be excluded
            $shouldExclude = false;
            foreach ($excludePatterns as $pattern) {
                if (str_contains($relativePath, $pattern)) {
                    $shouldExclude = true;
                    break;
                }
            }

            if (!$shouldExclude && $file->isFile()) {
                $files[] = $path;
            }
        }

        return $files;
    }

    private function initPlugin(?string $pluginName): int
    {
        if (!$pluginName) {
            $pluginName = $this->prompt('Plugin name (kebab-case)');
        }

        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $pluginName)) {
            return $this->showError('Invalid plugin name. Use kebab-case (e.g., my-awesome-plugin)');
        }

        $pluginDir = getcwd().'/'.$pluginName;

        if (is_dir($pluginDir)) {
            return $this->showError("Directory already exists: {$pluginDir}");
        }

        echo "🎨 Creating plugin structure for: {$pluginName}\n\n";

        // Create directories
        $this->createDirectory($pluginDir);
        $this->createDirectory($pluginDir.'/src');
        $this->createDirectory($pluginDir.'/config');
        $this->createDirectory($pluginDir.'/templates');

        // Gather information
        $displayName = $this->prompt('Display name', ucwords(str_replace('-', ' ', $pluginName)));
        $description = $this->prompt('Short description');
        $author = $this->prompt('Author name');
        $authorEmail = $this->prompt('Author email', '', false);
        $namespace = $this->prompt('PHP namespace', $this->generateNamespace($pluginName));
        $category = $this->promptChoice('Category', [
            'security', 'utilities', 'integrations', 'admin',
            'content', 'analytics', 'payment', 'communication',
            'development', 'other',
        ], 'utilities');

        // Create manifest
        $manifest = self::MANIFEST_TEMPLATE;
        $manifest['name'] = $pluginName;
        $manifest['displayName'] = $displayName;
        $manifest['description'] = $description;
        $manifest['author'] = $author;
        if ($authorEmail) {
            $manifest['authorEmail'] = $authorEmail;
        }
        $manifest['category'] = $category;
        $manifest['mainClass'] = $namespace.'\\'.$this->generateClassName($pluginName);
        $manifest['autoload']['psr-4'][$namespace.'\\'] = 'src/';

        $this->createFile(
            $pluginDir.'/plugin.json',
            json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
        );

        // Create main plugin class
        $mainClass = $this->generatePluginClass($namespace, $pluginName);
        $this->createFile(
            $pluginDir.'/src/'.$this->generateClassName($pluginName).'.php',
            $mainClass,
        );

        // Create README
        $readme = $this->generateReadme($manifest);
        $this->createFile($pluginDir.'/README.md', $readme);

        // Create CHANGELOG
        $changelog = $this->generateChangelog($manifest['version']);
        $this->createFile($pluginDir.'/CHANGELOG.md', $changelog);

        // Create services.yaml
        $services = $this->generateServices($namespace);
        $this->createFile($pluginDir.'/config/services.yaml', $services);

        // Create composer.json
        $composer = $this->generateComposer($manifest, $namespace);
        $this->createFile($pluginDir.'/composer.json', $composer);

        // Create .gitignore
        $gitignore = $this->generateGitignore();
        $this->createFile($pluginDir.'/.gitignore', $gitignore);

        echo "\nPlugin structure created successfully!\n\n";
        echo "Next steps:\n";
        echo "  1. cd {$pluginName}\n";
        echo "  2. Implement your plugin logic in src/\n";
        echo "  3. Test your plugin\n";
        echo "  4. Run: php ../publish-plugin.php package .\n";
        echo "  5. Submit to marketplace\n\n";

        return 0;
    }

    private function packagePlugin(?string $pluginPath): int
    {
        if (!$pluginPath) {
            return $this->showError('Plugin path is required');
        }

        $pluginPath = realpath($pluginPath);

        if (!$pluginPath || !is_dir($pluginPath)) {
            return $this->showError('Plugin directory not found');
        }

        // Load manifest
        $manifestPath = $pluginPath.'/plugin.json';
        if (!file_exists($manifestPath)) {
            return $this->showError('plugin.json not found');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $name = $manifest['name'] ?? basename($pluginPath);
        $version = $manifest['version'] ?? '1.0.0';

        echo "Packaging plugin: {$name} v{$version}\n\n";

        // Create releases directory
        $releasesDir = $pluginPath.'/releases';
        if (!is_dir($releasesDir)) {
            mkdir($releasesDir, 0o755, true);
        }

        $zipFile = $releasesDir."/{$name}-{$version}.zip";

        if (file_exists($zipFile)) {
            $overwrite = $this->confirm("File {$zipFile} already exists. Overwrite?");
            if (!$overwrite) {
                return $this->showError('Packaging cancelled');
            }
            unlink($zipFile);
        }

        // Create ZIP
        $zip = new ZipArchive();
        if (true !== $zip->open($zipFile, ZipArchive::CREATE)) {
            return $this->showError('Failed to create ZIP file');
        }

        $files = $this->getFilesForPackaging($pluginPath);

        echo "Adding files:\n";
        foreach ($files as $file) {
            $relativePath = str_replace($pluginPath.'/', '', $file);
            echo "  • {$relativePath}\n";
            $zip->addFile($file, $relativePath);
        }

        $zip->close();

        $size = filesize($zipFile);
        $checksum = hash_file('sha256', $zipFile);

        echo "\nPlugin packaged successfully!\n\n";
        echo "Details:\n";
        echo "  File: {$zipFile}\n";
        echo '  Size: '.$this->formatSize($size)."\n";
        echo "  SHA256: {$checksum}\n\n";

        return 0;
    }

    private function preparePlugin(?string $pluginPath): int
    {
        echo "🚀 Preparing plugin for marketplace submission...\n\n";

        // Validate
        $result = $this->validatePlugin($pluginPath);
        if (0 !== $result) {
            return $result;
        }

        echo "\n";

        // Package
        $result = $this->packagePlugin($pluginPath);
        if (0 !== $result) {
            return $result;
        }

        echo "\nMarketplace Submission Checklist:\n\n";
        echo "  Plugin validated\n";
        echo "  Plugin packaged\n";
        echo "\nNext steps:\n";
        echo "  1. Fork the marketplace repository\n";
        echo "  2. Create directory: plugins/{plugin-name}/\n";
        echo "  3. Copy plugin.json, README.md, and releases/*.zip\n";
        echo "  4. Create Pull Request\n";
        echo "  5. Wait for automated validation\n\n";

        return 0;
    }

    // Helper methods

    private function prompt(string $message, string $default = '', bool $required = true): string
    {
        $prompt = $default ? "{$message} [{$default}]: " : "{$message}: ";
        echo $prompt;

        $value = mb_trim(fgets(\STDIN));

        if (empty($value)) {
            $value = $default;
        }

        if ($required && empty($value)) {
            echo "❌ This field is required\n";

            return $this->prompt($message, $default, $required);
        }

        return $value;
    }

    private function promptChoice(string $message, array $choices, string $default): string
    {
        echo "{$message}:\n";
        foreach ($choices as $i => $choice) {
            echo '  '.($i + 1).") {$choice}\n";
        }

        $value = $this->prompt('Choice', $default, false);

        if (is_numeric($value) && isset($choices[$value - 1])) {
            return $choices[$value - 1];
        }

        if (in_array($value, $choices, true)) {
            return $value;
        }

        return $default;
    }

    private function showError(string $message): int
    {
        echo "❌ Error: {$message}\n";

        return 1;
    }

    private function showHelp(): int
    {
        echo <<<HELP
            Plugin Publisher Tool

            Usage:
              php publish-plugin.php <command> [options]

            Commands:
              init [name]              Create a new plugin template
              validate <path>          Validate plugin structure
              package <path>           Package plugin to ZIP
              prepare <path>           Full preparation for marketplace
              help                     Show this help message

            Examples:
              php publish-plugin.php init my-plugin
              php publish-plugin.php validate ./my-plugin
              php publish-plugin.php package ./my-plugin
              php publish-plugin.php prepare ./my-plugin


            HELP;

        return 0;
    }

    private function validateManifest(array $manifest, array &$errors, array &$warnings): void
    {
        $required = ['name', 'version', 'description', 'author', 'license', 'mainClass'];

        foreach ($required as $field) {
            if (empty($manifest[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        $recommended = ['displayName', 'homepage', 'category', 'tags'];

        foreach ($recommended as $field) {
            if (empty($manifest[$field])) {
                $warnings[] = "Missing recommended field: {$field}";
            }
        }
    }

    private function validatePlugin(?string $pluginPath): int
    {
        if (!$pluginPath) {
            return $this->showError('Plugin path is required');
        }

        $pluginPath = realpath($pluginPath);

        if (!$pluginPath || !is_dir($pluginPath)) {
            return $this->showError('Plugin directory not found');
        }

        echo "🔍 Validating plugin: {$pluginPath}\n\n";

        $errors = [];
        $warnings = [];

        // Check plugin.json
        $manifestPath = $pluginPath.'/plugin.json';
        if (!file_exists($manifestPath)) {
            $errors[] = 'Missing plugin.json';
        } else {
            $manifest = json_decode(file_get_contents($manifestPath), true);

            if (\JSON_ERROR_NONE !== json_last_error()) {
                $errors[] = 'Invalid JSON in plugin.json: '.json_last_error_msg();
            } else {
                $this->validateManifest($manifest, $errors, $warnings);
            }
        }

        // Check README
        if (!file_exists($pluginPath.'/README.md')) {
            $warnings[] = 'Missing README.md';
        }

        // Check for source files
        if (!is_dir($pluginPath.'/src')) {
            $errors[] = 'Missing src/ directory';
        }

        // Print results
        if (!empty($errors)) {
            echo "Errors:\n";
            foreach ($errors as $error) {
                echo "  • {$error}\n";
            }
        }

        if (!empty($warnings)) {
            echo "\nWarnings:\n";
            foreach ($warnings as $warning) {
                echo "  • {$warning}\n";
            }
        }

        if (empty($errors) && empty($warnings)) {
            echo "Plugin validation passed!\n";

            return 0;
        } elseif (empty($errors)) {
            echo "\nPlugin validation passed with warnings.\n";

            return 0;
        } else {
            echo "\nPlugin validation failed.\n";

            return 1;
        }
    }
}

// Run
$publisher = new PluginPublisher();
exit($publisher->run($argv));
