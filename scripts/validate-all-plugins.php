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
 * Validate all plugins in the marketplace.
 */

require_once __DIR__.'/validate-plugin.php';

final class AllPluginsValidator
{
    private const string PLUGINS_DIR = __DIR__.'/../plugins';

    private int $failedPlugins = 0;

    private int $passedPlugins = 0;

    private array $results = [];

    private int $totalPlugins = 0;

    public function validate(): bool
    {
        echo "Validating all plugins in marketplace...\n\n";

        if (!is_dir(self::PLUGINS_DIR)) {
            echo 'Plugins directory not found: '.self::PLUGINS_DIR."\n";

            return false;
        }

        $pluginDirs = glob(self::PLUGINS_DIR.'/*', \GLOB_ONLYDIR);
        $this->totalPlugins = count($pluginDirs);

        if (0 === $this->totalPlugins) {
            echo "No plugins found to validate\n";

            return true;
        }

        echo "Found {$this->totalPlugins} plugins to validate\n\n";

        foreach ($pluginDirs as $pluginDir) {
            $pluginName = basename($pluginDir);
            $this->validatePlugin($pluginDir, $pluginName);
        }

        $this->printSummary();

        return 0 === $this->failedPlugins;
    }

    private function printSummary(): void
    {
        echo str_repeat('=', 80)."\n";
        echo "VALIDATION SUMMARY\n";
        echo str_repeat('=', 80)."\n\n";

        echo "Total plugins: {$this->totalPlugins}\n";
        echo "Passed: {$this->passedPlugins}\n";
        echo "Failed: {$this->failedPlugins}\n\n";

        if ($this->failedPlugins > 0) {
            echo "Failed plugins:\n";
            foreach ($this->results as $plugin => $result) {
                if ('FAILED' === $result) {
                    echo "$plugin\n";
                }
            }
            echo "\n";
        }

        if (0 === $this->failedPlugins) {
            echo "All plugins passed validation!\n";
        } else {
            echo "Some plugins failed validation. Please fix the issues above.\n";
        }
    }

    private function validatePlugin(string $pluginDir, string $pluginName): void
    {
        echo str_repeat('-', 80)."\n";
        echo "Validating: $pluginName\n";
        echo str_repeat('-', 80)."\n";

        $validator = new PluginValidator($pluginDir);
        $success = $validator->validate();

        if ($success) {
            ++$this->passedPlugins;
            $this->results[$pluginName] = 'PASSED';
        } else {
            ++$this->failedPlugins;
            $this->results[$pluginName] = 'FAILED';
        }

        echo "\n";
    }
}

// Main
try {
    $validator = new AllPluginsValidator();
    $success = $validator->validate();
    exit($success ? 0 : 1);
} catch (Exception $e) {
    echo "\nFatal error: ".$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
    exit(1);
}
