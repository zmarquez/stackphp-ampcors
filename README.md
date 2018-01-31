AMP CORS middleware for StackPHP
==============================

This package contains a [StackPHP](http://stackphp.com/) middleware that manages the AMP security CORS requests.

Options
-------

The `AmpCorsMiddleware` accepts an array of options:

- **publisherOrigin**: contains the value of the source origin.
- **queryString**: array with parameters to add of the request.

Example
-------

```php
<?php

use Zmc\Stack\AmpCorsMiddleware;

require_once __DIR__ . '../vendor/autoload.php';

$app = new Silex\Application();

$stack = (new Stack\Builder())
    ->push(
        AmpCorsMiddleware::class,
        [
            'publisherOrigin' => 'https://example.com',
            'queryString' => [
                'lang' => 'es'
            ]
        ]);

$app = $stack->resolve($app);

$request = Request::createFromGlobals();
$response = $app->handle($request)->send();

$app->terminate($request, $response);
```

Installation
------------

The recommended way to install `AmpCorsMiddleware` is through [Composer](http://getcomposer.org/):

``` json
{
    "require": {
        "zmarquez/stackphp-ampcors": "dev-master"
    }
}
```
