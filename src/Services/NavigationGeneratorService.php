<?php

namespace PHPNomad\Static\Services;

use PHPNomad\Static\Models\Route;
use PHPNomad\Static\Providers\MarkdownFileProvider;
use PHPNomad\Utils\Helpers\Str;

class NavigationGeneratorService
{
    public function __construct(
      protected MarkdownFileProvider $fileProvider
    ) {
    }

    public function generateItems(?Route $route = null): array
    {
        $currentPath = $route ? $route->endpoint : '';
        $finder = $this->fileProvider->getMarkdownFiles();
        $structure = [];

        // Build the basic structure
        foreach ($finder as $file) {
            $relativePath = $file->getRelativePath();
            $filename = Str::trimTrailing($file->getBasename(), '.md');
            $pathParts = $relativePath ? explode('/', $relativePath) : [];

            if ($filename === 'index') {
                $current = &$structure;
                foreach ($pathParts as $part) {
                    if (!isset($current[$part])) {
                        $current[$part] = [
                          'title' => $this->formatTitle($part),
                          'path' => '/' . $relativePath,
                          'children' => []
                        ];
                    }
                    $current = &$current[$part]['children'];
                }
            } else {
                $current = &$structure;
                foreach ($pathParts as $part) {
                    if (!isset($current[$part])) {
                        $current[$part] = [
                          'title' => $this->formatTitle($part),
                          'children' => []
                        ];
                    }
                    $current = &$current[$part]['children'];
                }

                $current[] = [
                  'title' => $this->formatTitle($filename),
                  'path' => $relativePath
                    ? '/' . $relativePath . '/' . $filename
                    : '/' . $filename
                ];
            }
        }

        // 🔹 Sort: files first, then folders; both A–Z (natural, case-insensitive)
        $this->sortStructure($structure);

        // Process open states
        foreach ($structure as &$section) {
            $this->processOpenStates($section, $currentPath);
        }

        return $this->flattenStructure($structure);
    }

    protected function processOpenStates(array &$item, string $currentPath): bool
    {
        $isOpen = false;

        if (isset($item['path']) && $item['path'] === $currentPath) {
            $isOpen = true;
        }

        if (!empty($item['children'])) {
            foreach ($item['children'] as &$child) {
                if ($this->processOpenStates($child, $currentPath)) {
                    $isOpen = true;
                }
            }
        }

        $item['isOpen'] = $isOpen;
        return $isOpen;
    }

    private function flattenStructure(array $structure): array
    {
        $result = [];
        foreach ($structure as $item) {
            if (isset($item['title'])) {
                $entry = ['title' => $item['title']];
                if (isset($item['path'])) {
                    $entry['path'] = $item['path'];
                }
                if (isset($item['isOpen'])) {
                    $entry['isOpen'] = $item['isOpen'];
                }
                if (!empty($item['children'])) {
                    $entry['children'] = $this->flattenStructure($item['children']);
                }
                $result[] = $entry;
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }

    private function formatTitle(string $path): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', $path));
    }

    /**
     * Recursively sort a nav list:
     * - Files (no 'children') first
     * - Folders ('children' present) last
     * - Then A–Z by 'title' (natural, case-insensitive)
     */
    private function sortStructure(array &$items): void
    {
        // Normalize to a numeric-indexed list (removes string keys from folder names)
        $items = array_values($items);

        usort($items, function (array $a, array $b): int {
            $aIsFolder = isset($a['children']) && is_array($a['children']);
            $bIsFolder = isset($b['children']) && is_array($b['children']);

            // files (false) before folders (true)
            $typeCmp = ((int)$aIsFolder) <=> ((int)$bIsFolder);
            if ($typeCmp !== 0) {
                return $typeCmp;
            }

            $titleA = $a['title'] ?? '';
            $titleB = $b['title'] ?? '';
            $nameCmp = strnatcasecmp($titleA, $titleB);
            if ($nameCmp !== 0) {
                return $nameCmp;
            }

            // Tie-breaker for identical titles
            return strnatcasecmp($a['path'] ?? '', $b['path'] ?? '');
        });

        // Recurse into folders
        foreach ($items as &$item) {
            if (isset($item['children']) && is_array($item['children'])) {
                $this->sortStructure($item['children']);
            }
        }
    }
}
