<?php

declare(strict_types=1);

namespace Zenit\Routing;

error_reporting(E_ALL);

final class Router
{
	/**
	 * @var array<Method, array<Route>> - HTTP methods as key, inner array
	 * 	contains routes in the same order as they were added
	 */
	private array $routes = [];

	/**
	 * @var array<string, true> - routes name as key, true if set, unset otherwise
	 */
	private array $namesLookup = [];

	/**
	 * Adds a route in the router
	 *
	 * @param Route $route - the route to add
	 *
	 * @return void
	 *
	 * @throws DuplicateRouteNameException - if a route with the same name is
	 * 	already stored
	 */
	public function register(Route $route, callable $callback): void
	{
		$name = $route->name();
		if ($this->namesLookup[$name] ?? false)
			throw new DuplicateRouteNameException($name);

		$this->namesLookup[$name] = true;
		$this->routes[$route->method()->name][] = [$route, $callback];
	}

	/**
	 * Finds the first matching route and returns it
	 * If several routes match, the first added is returned
	 *
	 * @param Method $method - the requested method
	 * @param string $requestedUrl - the requested URL
	 *
	 * @return MatchedRoute|null - the first matching route, null if none
	 */
	public function match(Method $method, string $requestedUrl): ?MatchedRoute
	{
		foreach ($this->routes[$method->name] ?? [] as [$route, $callback])
		{
			$parameters = $route->matches($method, $requestedUrl);
			if ($parameters !== false)
				return new MatchedRoute($route, $callback, $parameters);
		}

		return null;
	}
}
