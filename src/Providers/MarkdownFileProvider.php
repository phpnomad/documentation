<?php

namespace PHPNomad\Static\Providers;

use Symfony\Component\Finder\Finder;

class MarkdownFileProvider
{
    public function __construct(protected ConfigProvider $configProvider)
    {
    }

    /**
     * @return Finder
     */
    public function getMarkdownFiles() : Finder
    {
        return (new Finder())
          ->files()
          ->in($this->configProvider->getDocsRootDirectory())
          ->name('*.md');
    }
}