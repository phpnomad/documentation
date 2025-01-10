<?php

namespace PHPNomad\Static\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use PHPNomad\Template\Interfaces\CanRender;

class MarkdownRenderService implements CanRender
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        // Configure the Environment with all the extensions we want
        $environment = new Environment([
          'html_input' => 'strip',
          'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new FrontMatterExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $templatePath, array $data = []): string
    {
        $markdown = file_get_contents($templatePath);
        return $this->converter->convert($markdown)->getContent();
    }
}