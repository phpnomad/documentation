<?php

namespace PHPNomad\Static\Handlers;

use PHPNomad\Events\Interfaces\CanHandle;
use PHPNomad\Events\Interfaces\Event;
use PHPNomad\Static\Events\RequestInitiated;
use PHPNomad\Static\Events\StaticCompileRequested;
use PHPNomad\Static\Providers\ConfigProvider;
use PHPNomad\Static\Providers\MarkdownRouteProvider;
use PHPNomad\Events\Interfaces\EventStrategy;

/**
 * @extends CanHandle<StaticCompileRequested>
 */
class GenerateStaticSite implements CanHandle
{
    public function __construct(
      protected MarkdownRouteProvider $routeProvider,
      protected ConfigProvider $configProvider,
      protected EventStrategy $eventStrategy
    ) {}

    public function handle(Event $event): void
    {
        $outputDir = 'dist';

        // Ensure output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        echo "Generating static site...\n";

        // Generate static files for each route
        foreach ($this->routeProvider->getRoutes() as $route) {
            $requestEvent = new RequestInitiated($route->endpoint);
            $this->eventStrategy->broadcast($requestEvent);

            $response = $requestEvent->getResponse();

            // Create the file path
            $filePath = $this->createFilePath($outputDir, $route->endpoint);

            // Create directory if it doesn't exist
            $dirPath = dirname($filePath);
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0777, true);
            }

            // Write the response to a file
            file_put_contents($filePath, $response->getBody());

            echo sprintf(
              "Generated: %s -> %s\n",
              $route->endpoint,
              $filePath
            );
        }

        echo "Static site generation complete!\n";
    }

    protected function createFilePath(string $outputDir, string $endpoint): string
    {
        $path = trim($endpoint, '/');

        // If empty path, it's the index
        if (empty($path)) {
            return $outputDir . '/index.html';
        }

        // Create clean path and append index.html for directories
        return $outputDir . '/' . $path . (str_contains($path, '.') ? '' : '/index.html');
    }
}