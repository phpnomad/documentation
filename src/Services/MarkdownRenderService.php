<?php

namespace PHPNomad\Static\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use PHPNomad\Template\Interfaces\CanRender;

class MarkdownRenderService implements CanRender
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        // Configure the Environment with all the extensions we want
        $environment = new Environment([
          'html_input'         => 'strip',
          'allow_unsafe_links' => false,
          'heading_permalink'  => [
            'apply_id_to_heading' => true,
            'insert'              => 'none',
            'id_prefix'           => '',
            'fragment_prefix'     => '',
          ],

          'slug_normalizer' => [
            'unique'     => 'document',
            'max_length' => 255,
          ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new FrontMatterExtension());
        $environment->addExtension(new HeadingPermalinkExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $templatePath, array $data = []) : string
    {
        $markdown = file_get_contents($templatePath);
        return $this->converter->convert($markdown)->getContent();
    }
}