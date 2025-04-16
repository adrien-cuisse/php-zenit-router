<?php

declare(strict_types=1);

namespace Zenit\Routing;

use Closure;

error_reporting(E_ALL);

final readonly class MatchedRoute
{
	private Closure $callback;

	public function __construct(
		private Route $route,
		callable $callback,
		private array $parameters = [])
	{
		$this->callback = $callback(...);
	}

	public function parameters(): array
	{
		return $this->parameters;
	}

	public function name(): string
	{
		return $this->route->name();
	}

	public function callback(): callable
	{
		return $this->callback;
	}
}
