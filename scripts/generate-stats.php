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
 * Generate marketplace statistics.
 */
final class StatsGenerator
{
    private const string DIST_DIR = __DIR__.'/../dist';

    public function generate(): void
    {
        echo "Generating marketplace statistics...\n";

        $indexPath = self::DIST_DIR.'/index.json';

        if (!file_exists($indexPath)) {
            throw new RuntimeException('Index file not found. Run build-index.php first.');
        }

        $index = json_decode(file_get_contents($indexPath), true);
        $plugins = $index['plugins'] ?? [];

        $stats = [
            'generated' => date('c'),
            'lastUpdate' => date('c'),
            'totalPlugins' => count($plugins),
            'totalCategories' => count(array_unique(array_column($plugins, 'category'))),
            'totalDownloads' => array_sum(array_column($plugins, 'downloads')),
            'averageRating' => $this->calculateAverageRating($plugins),
            'featuredPlugins' => count(array_filter($plugins, fn ($p) => $p['featured'] ?? false)),
            'mostDownloaded' => $this->getMostDownloaded($plugins, 10),
            'topRated' => $this->getTopRated($plugins, 10),
            'recentlyUpdated' => $this->getRecentlyUpdated($plugins, 10),
            'categoriesBreakdown' => $this->getCategoriesBreakdown($plugins),
            'tagsCloud' => $this->getTagsCloud($plugins),
        ];

        $this->writeJson(self::DIST_DIR.'/stats.json', $stats);

        echo "Statistics generated successfully!\n";
        $this->printStats($stats);
    }

    private function calculateAverageRating(array $plugins): float
    {
        $ratings = array_filter(array_column($plugins, 'rating'));

        return count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 2) : 0.0;
    }

    private function getCategoriesBreakdown(array $plugins): array
    {
        $breakdown = [];

        foreach ($plugins as $plugin) {
            $category = $plugin['category'] ?? 'other';
            if (!isset($breakdown[$category])) {
                $breakdown[$category] = 0;
            }
            ++$breakdown[$category];
        }

        arsort($breakdown);

        return $breakdown;
    }

    private function getMostDownloaded(array $plugins, int $limit): array
    {
        usort($plugins, fn ($a, $b) => ($b['downloads'] ?? 0) <=> ($a['downloads'] ?? 0));

        return array_slice(array_map(fn ($p) => [
            'name' => $p['name'],
            'displayName' => $p['displayName'] ?? $p['name'],
            'downloads' => $p['downloads'] ?? 0,
        ], $plugins), 0, $limit);
    }

    private function getRecentlyUpdated(array $plugins, int $limit): array
    {
        // W realnym scenariuszu czytamy z pelnych danych pluginu
        $detailedPlugins = [];
        foreach ($plugins as $plugin) {
            $detailPath = self::DIST_DIR."/plugins/{$plugin['name']}.json";
            if (file_exists($detailPath)) {
                $details = json_decode(file_get_contents($detailPath), true);
                if (isset($details['marketplace']['indexed'])) {
                    $detailedPlugins[] = [
                        'name' => $details['name'],
                        'displayName' => $details['displayName'] ?? $details['name'],
                        'version' => $details['latestVersion'],
                        'updated' => $details['marketplace']['indexed'],
                    ];
                }
            }
        }

        usort($detailedPlugins, fn ($a, $b) => strtotime($b['updated']) <=> strtotime($a['updated']));

        return array_slice($detailedPlugins, 0, $limit);
    }

    private function getTagsCloud(array $plugins): array
    {
        $tags = [];

        foreach ($plugins as $plugin) {
            foreach ($plugin['tags'] ?? [] as $tag) {
                if (!isset($tags[$tag])) {
                    $tags[$tag] = 0;
                }
                ++$tags[$tag];
            }
        }

        arsort($tags);

        return array_slice($tags, 0, 50); // Top 50 tags
    }

    private function getTopRated(array $plugins, int $limit): array
    {
        usort($plugins, fn ($a, $b) => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0));

        return array_slice(array_map(fn ($p) => [
            'name' => $p['name'],
            'displayName' => $p['displayName'] ?? $p['name'],
            'rating' => $p['rating'] ?? 0,
        ], $plugins), 0, $limit);
    }

    private function printStats(array $stats): void
    {
        echo "\nMarketplace Statistics:\n";
        echo "  Total plugins: {$stats['totalPlugins']}\n";
        echo "  Total categories: {$stats['totalCategories']}\n";
        echo "  Total downloads: {$stats['totalDownloads']}\n";
        echo "  Average rating: {$stats['averageRating']}\n";
        echo "  Featured plugins: {$stats['featuredPlugins']}\n";
        echo "\n";
    }

    private function writeJson(string $filename, array $data): void
    {
        $json = json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('Failed to encode JSON: '.json_last_error_msg());
        }

        if (false === file_put_contents($filename, $json)) {
            throw new RuntimeException("Failed to write file: $filename");
        }
    }
}

try {
    $generator = new StatsGenerator();
    $generator->generate();
    exit(0);
} catch (Exception $e) {
    echo "\nError: ".$e->getMessage()."\n";
    exit(1);
}
