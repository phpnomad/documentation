# Introduction to PHPNomad Bootstrapping

The bootstrapper is where everything begins in a PHPNomad application. It's responsible for:

- Setting up your application
- Loading all the pieces in the right order
- Making sure everything can talk to each other

Here's what it looks like in its simplest form:

```php
$container = new Container();
$bootstrapper = new Bootstrapper(
    $container,
    new MyAppSetup(), // A custom initializer created for your application
    new DatabaseSetup(), // An initializer that ensures the system knows how to query data
    // Other initializers loaded here as needed, such as a WordPress integration, or various Symfony implementations.
);
$bootstrapper->load();
```

## Key Ideas Behind PHPNomad

### Your Code Comes First

Instead of building your code to fit into a specific platform or system, PHPNomad lets you build your application the
way you want. Then, the platform adapts to work with your code. This makes it much easier if you ever need to move your
code to a different system.

### Breaking Things into Pieces

Rather than putting all your setup code in one place, PHPNomad encourages you to break it into smaller, focused pieces
called initializers. Each initializer has one job, making your code easier to understand and change.

### Keeping Things Separate

Your application's core logic stays separate from platform-specific code. This means you can change how your application
works with different platforms without having to rewrite your main application code.

## How Setup Works

When you start your application:

1. First, PHPNomad creates a central hub (we call it a container) that helps different parts of your code find each
   other
2. Then, it runs through your setup steps one by one
3. Finally, it locks everything down so nothing can accidentally change how things are connected

This process helps keep your application stable and predictable.

## Built for Change

PHPNomad is designed to make your code flexible:

- Your core application code doesn't need to know about the platform it's running on
- Platform-specific code is kept separate and can be easily changed
- You can move your application between different systems without rewriting everything

This means you can start building for one platform today, but keep your options open for the future. Most of your code
will work just fine if you decide to move it somewhere else later.

You're not locked into one way of doing things - and that's the whole point of PHPNomad.