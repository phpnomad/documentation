<?php

namespace PHPNomad\Static\Commands;

use PHPNomad\Events\Interfaces\EventStrategy;
use PHPNomad\Static\Events\RequestInitiated;
use PHPNomad\Static\Providers\MarkdownRouteProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateStaticSiteCommand extends Command
{
    protected static $defaultName = 'site:generate';

    public function __construct(
      protected MarkdownRouteProvider $routeProvider,
      protected EventStrategy $event
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
          ->setDescription('Generates a static version of the website')
          ->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'Output directory for the static site',
            'dist'
          );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputDir = $input->getOption('output');

        // Ensure output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $output->writeln('<info>Generating static site...</info>');

        // Generate static files for each route
        foreach ($this->routeProvider->getRoutes() as $route) {
            $event = new RequestInitiated($route->endpoint);
            $this->event->broadcast($event);

            $response = $event->getResponse();

            // Create the file path
            $filePath = $this->createFilePath($outputDir, $route->endpoint);

            // Create directory if it doesn't exist
            $dirPath = dirname($filePath);
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0777, true);
            }

            // Write the response to a file
            file_put_contents($filePath, $response->getBody());

            $output->writeln(sprintf(
              'Generated: <comment>%s</comment> -> <info>%s</info>',
              $route->endpoint,
              $filePath
            ));
        }

        $output->writeln('<info>Static site generation complete!</info>');
        return Command::SUCCESS;
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