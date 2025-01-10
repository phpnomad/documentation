<?php

namespace PHPNomad\Static\Controllers;

use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;
use PHPNomad\Static\Interfaces\WebController;
use PHPNomad\Static\Models\Route;
use PHPNomad\Static\Providers\ConfigProvider;
use PHPNomad\Static\Services\MarkdownRenderService;
use PHPNomad\Static\Services\NavigationGeneratorService;
use PHPNomad\Template\Interfaces\CanRender;

class MarkdownController implements WebController
{
    public function __construct(
      private MarkdownRenderService $contentRenderer,
      private CanRender $template,
      private ConfigProvider $configProvider,
      private NavigationGeneratorService $sidebarGeneratorService,
      private Response $response
    ) {}

    public function content(Route $route, Request $request): Response
    {
        $response = clone $this->response;

        // Convert URL path back to file path
        $path = trim($route->endpoint, '/');
        $filePath = $this->configProvider->getDocsRootDirectory() . "/{$path}";

        // If this is a directory path, look for index.md
        if (is_dir($filePath)) {
            $filePath = rtrim($filePath, '/') . '/index.md';
        } else {
            $filePath .= '.md';
        }

        if (!file_exists($filePath)) {
            $response->setStatus(404);
            return $response;
        }

         $response->setBody($this->template->render(
           'doc',
           [
             'content' => $this->contentRenderer->render($filePath),
             'sidebarItems' => $this->sidebarGeneratorService->generateItems($route)
           ]
         ));

        return $response;
    }
}