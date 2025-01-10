<?php

namespace PHPNomad\Static\Providers;

use Generator;
use PHPNomad\Config\Interfaces\ConfigStrategy;
use PHPNomad\Static\Controllers\MarkdownController;
use PHPNomad\Static\Models\Route;
use PHPNomad\Utils\Helpers\Str;
use Symfony\Component\Finder\Finder;

class MarkdownRouteProvider
{
    public function __construct(protected MarkdownController $controller, protected ConfigStrategy $config, protected MarkdownFileProvider $fileProvider)
    {
        
    }
    
    /**
     * Fetches the routes.
     *
     * @return Generator<Route>
     */
    public function getRoutes() : Generator
    {
        $finder = $this->fileProvider->getMarkdownFiles();

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePath();
            $filename = Str::trimTrailing($file->getBasename(), '.md');

            if ($filename === 'index') {
                $endpoint = '/'.$relativePath;
            } else {
                $endpoint = $relativePath
                  ? '/'.$relativePath.'/'.$filename
                  : '/'.$filename;
            }

            $endpoint = Str::trimTrailing(Str::trimLeading($endpoint, '/'), '/');
            $endpoint = Str::prepend($endpoint, '/');

            yield new Route(
              endpoint: $endpoint,
              controller: $this->controller
            );
        }
    }
}