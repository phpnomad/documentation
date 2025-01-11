# PHPNomad Documentation System

This is the documentation system for PHPNomad. It converts Markdown documentation files into a static website, providing both a development environment for writing documentation and a static site generator for production deployment.

## Setup

1. Install dependencies:
```bash
composer install
```

2. Create your configuration file at `configs/app.json`:
```json
{
    "templateRoot": "./templates",
    "docsRoot": "./docs"
}
```

3. Ensure your documentation directory structure is in place:
```
.
├── configs/
│   └── app.json
├── docs/          # Your PHPNomad documentation in Markdown
├── templates/     # Documentation site templates
│   └── assets/   # CSS, JS, and other assets
└── dist/         # Generated static documentation (created automatically)
```

## Writing Documentation

Place your Markdown documentation files in the `docs` directory. The file structure determines the URL structure of your documentation:

```
docs/
├── index.md                    # Landing page at /
├── getting-started.md         # /getting-started
└── core/
    ├── index.md              # /core
    ├── container.md         # /core/container
    └── events.md           # /core/events
```

## Development Server

While writing documentation, you can run a local development server that provides live updates:

```bash
php -S localhost:8080 index.php
```

This will serve your documentation at `http://localhost:8080`. Changes to your Markdown files will be reflected immediately upon page refresh.

## Generating Static Documentation

To generate a static version of the documentation for deployment:

```bash
php generate.php
```

This process will:
1. Create or clean the `dist` directory
2. Convert all Markdown documentation to HTML
3. Copy the template assets to `dist/public/assets`
4. Generate the documentation navigation structure

## Testing Static Generation

After generating the static documentation, you can verify it:

```bash
cd dist
php -S localhost:8081
```

Visit `http://localhost:8081` to review the generated static documentation.

## Template Structure

The documentation system requires two main templates in your `templates` directory:

- `doc.twig` - Template for documentation pages
- `404.twig` - Documentation not found page

## Assets

Place your documentation site's assets (CSS, JavaScript, images) in `templates/assets`. These will be automatically copied to `dist/public/assets` during static site generation.

## Features

- Markdown to HTML conversion with GitHub Flavored Markdown support
- Automatic documentation navigation generation based on file structure
- Development server with live updates
- Static site generation for production deployment
- Asset management