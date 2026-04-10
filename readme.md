# phpnomad/documentation

Source for the PHPNomad documentation site at [phpnomad.com](https://phpnomad.com). The docs site is itself a PHPNomad application, so the same framework the documentation describes is the framework rendering it. Markdown files in `public/docs/` are rendered through a Twig-backed MVC pipeline, served live during development or compiled to a static site for deployment.

## What's in the repo

- `index.php` is the dev server entry point. It boots the application in `dev()` mode and broadcasts a `RequestInitiated` event that flows through the router and controllers.
- `generate.php` is the static compile CLI. It boots in `cli()` mode and broadcasts `StaticCompileInitiated` and `StaticCompileRequested` events that walk the docs tree and write HTML to disk.
- `src/` holds the MVC source: the `Application` class, initializers, events, the `MarkdownController`, and the services that render Markdown and build navigation.
- `public/` holds the Twig templates (`doc.twig`, `404.twig`, `layouts/`, `components/`), the site assets, and the `docs/` tree that holds the actual documentation content.

## Running it locally

Install dependencies and start the dev server:

```bash
composer install
php -S localhost:8080 index.php
```

Changes to Markdown files are reflected on the next request. To compile the static site for deployment:

```bash
php generate.php
```

## Configuration

Site configuration lives in `configs/app.json`. Two keys matter: `docsRoot` (the directory holding your Markdown files, defaults to `public/docs`) and `templateRoot` (the directory holding the Twig templates, defaults to `public`).

## Writing docs

The directory layout under `public/docs/` mirrors the URL structure. Subdirectories become path segments, and an `index.md` inside a directory becomes that directory's landing page. Links between documents work like regular Markdown links.

## Contributing

This repository is the canonical place to propose changes to the PHPNomad documentation. The rendered output lives at [phpnomad.com](https://phpnomad.com).

## License

MIT. See [LICENSE](LICENSE).
