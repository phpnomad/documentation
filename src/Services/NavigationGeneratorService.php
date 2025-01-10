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
        $currentPath = $route ? $route->endpoint : ''; // Use the endpoint as the current path
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

        // Process the open states
        foreach ($structure as &$section) {
            $this->processOpenStates($section, $currentPath);
        }

        return $this->flattenStructure($structure);
    }

    protected function processOpenStates(array &$item, string $currentPath): bool
    {
        $isOpen = false;

        // Check if the current item matches the current path
        if (isset($item['path'])) {
            if ($item['path'] === $currentPath) {
                $isOpen = true;
            }
        }

        // Process children and check if any child is open
        if (isset($item['children']) && !empty($item['children'])) {
            foreach ($item['children'] as &$child) {
                if ($this->processOpenStates($child, $currentPath)) {
                    $isOpen = true;
                }
            }
        }

        // Set the isOpen state for the current item
        $item['isOpen'] = $isOpen;

        return $isOpen;
    }

    private function flattenStructure(array $structure): array
    {
        $result = [];
        foreach ($structure as $key => $item) {
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
}
