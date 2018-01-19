AMP CORS middleware for StackPHP
==============================

This package contains a [StackPHP](http://stackphp.com/) middleware that manages the AMP security CORS requests.

Example
-------

```php
<?php

use Zmc\Stack\AmpCorsMiddleware;

require_once __DIR__ . '../vendor/autoload.php';

$app = new Silex\Application();

$stack = (new Stack\Builder())
    ->push(AmpCorsMiddleware::class, 'https://example.com');

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
