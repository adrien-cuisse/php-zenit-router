

# 📚 Yet another PHP router!

All is said, it's just a basic router, the Route class is responsible of parameters handling (defining syntax and extracting values)


## 📦 Running tests

```
composer install
composer test && composer mutate
```


## 👇 Usage example, basic routing and callback calling

```php
<?php

error_reporting(E_ALL);

require_once 'vendor/autoload.php';

use Zenit\Routing\Method;
use Zenit\Routing\Router;
use Zenit\Routing\Route;


/* Define your controllers */
function indexCallback(): void
{
	echo 'api root' . PHP_EOL;
}

function notFoundCallback(): void
{
	die('Error 404: URL not found' . PHP_EOL);
}


/* Store your routes */
$router = new Router;
$router->register(
	new Route(Method::GET, '/api', 'api.root'),
	indexCallback(...));


/* The front controller is in charge of finding to which controller forward the request */
function resolveUrl(string $requestedUrl): ?callable
{
	global $router;

	$controller = $router->match(Method::GET, $requestedUrl);
	return $controller?->callback() ?? notFoundCallback(...);
}

resolveUrl('/api')();
resolveUrl('/what-a-nice-404-page')();
```


## 😨 Found a bug ?

Create a pull request with a failing test, I'll make it pass
