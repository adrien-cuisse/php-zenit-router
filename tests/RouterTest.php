<?php

declare(strict_types=1);

namespace Zenit\Routing\Tests;

error_reporting(E_ALL);

use Hamcrest\MatcherAssert;
use Hamcrest\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

use Zenit\Routing\DuplicateRouteNameException;
use Zenit\Routing\MalformedSchemaException;
use Zenit\Routing\Method;
use Zenit\Routing\Route;
use Zenit\Routing\Router;

final class RouterTest extends TestCase
{
	private Router $router;

	public static function setUpBeforeClass(): void
	{
		Util::registerGlobalFunctions();
	}

	public function setUp(): void
	{
		$this->router = new Router;
	}

	public function tearDown(): void
	{
		$this->addToAssertionCount(MatcherAssert::getCount());
		MatcherAssert::resetCount();
	}

	public static function methods(): iterable
	{
		foreach (Method::cases() as $method)
			yield $method->name => [$method];
	}

	#[Test]
	#[TestDox('finds no route when none stored')]
	#[DataProvider('methods')]
	public function noRoute_noMatch(Method $method): void
	{
		// given an empty router

		// when requesting any URL
		$matchedRoute = $this->router->match($method, '/any-url');

		// then nothing should be found
		assertThat($matchedRoute, is(null));
	}

	#[Test]
	#[TestDox('finds route from exact URL')]
	#[DataProvider('methods')]
	public function exactUrl_match(Method $method): void
	{
		// given a route with exact URL
		$route = self::route(method: $method, schema: '/exact-url');
		$this->router->register($route, self::noCallback());

		// when requesting that exact URL
		$matchedRoute = $this->router->match($method, $route->schema());

		// then the route should be found
		assertThat($matchedRoute, is(not(null)));
	}

	#[Test]
	#[TestDox("doesn't match URL if not exact")]
	#[DataProvider('methods')]
	public function unknownUrl_noMatch(Method $method): void
	{
		// given a route with an exact URL
		$route = self::route(method: $method, schema: '/exact-url');
		$this->router->register($route, self::noCallback());

		// when requesting another URL
		$matchedRoute = $this->router->match($method, '/another-url');

		// then no route should be found
		assertThat($matchedRoute, is(null));
	}

	#[Test]
	#[TestDox('matched route has correct URL')]
	#[DataProvider('methods')]
	public function severalRoutes_correctOne(Method $method): void
	{
		// given several routes with exact URLs
		$this->router->register(
			self::route($method, '/first', 'wrong n°1'),
			self::noCallback());
		$this->router->register(
			self::route($method, '/second', 'correct'),
			self::noCallback());
		$this->router->register(
			self::route($method, '/third', 'wrong n°2'),
			self::noCallback());

		// when requesting the exact URL of a route
		$matchedRoute = $this->router->match($method, '/second');

		// then the correct route should be returned
		assertThat($matchedRoute->name(), is('correct'));
	}

	public static function wrongMethods(): iterable
	{
		foreach (Method::cases() as $method)
		{
			foreach (Method::cases() as $other)
			{
				if ($method === $other)
					continue;

				$description = "{$other->name} instead of {$method->name}";
				yield $description => [$method, $other];
			}
		}
	}

	#[Test]
	#[TestDox("doesn't match URL if wrong method")]
	#[DataProvider('wrongMethods')]
	public function correctUrl_wrongMethod_noMatch(
		Method $method,
		Method $wrongMethod): void
	{
		// given a route with an exact URL
		$route = self::route(method: $method, schema: '/correct-url');
		$this->router->register($route, self::noCallback());

		// when trying to match with another method
		$matchedRoute = $this->router->match($wrongMethod, $route->schema());

		// then it shouldn't match
		assertThat($matchedRoute, is(null));
	}

	#[Test]
	#[TestDox('routes names are unique')]
	#[DataProvider('methods')]
	public function duplicateRouteName_exception(Method $method): void
	{
		// given a route name already registered
		$name = 'unique';
		$this->router->register(
			self::route(method: $method, schema: '/foo', name: $name),
			self::noCallback());

		// when trying to register it again
		$registerDuplicate = fn () => $this->router->register(
			self::route(method: $method, schema: '/bar', name: $name),
			self::noCallback());

		// then it should have thrown
		$this->expectException(DuplicateRouteNameException::class);
		$registerDuplicate();
	}

	#[Test]
	#[TestDox('duplicate route name exception is explanatory')]
	public function duplicateRouteName_exceptionMessageContainsName(): void
	{
		// given a route name already registered
		$name = 'unique';
		$this->router->register(self::route(name: $name), self::noCallback());

		// when trying to register it again
		$registerDuplicate = fn () => $this->router->register(
			self::route(name: $name),
			self::noCallback());

		// then the error message should contain the route name
		$this->expectExceptionMessage("Route name already in use: '$name'");
		$registerDuplicate();
	}

	#[Test]
	#[TestDox('matches with wildcard parameter')]
	#[DataProvider('methods')]
	public function parameterizedRoute_wildcardEveryUrl_match(Method $method): void
	{
		// given a route with only a wildcard parameter
		$route = self::route(method: $method, schema: '/{wildcard}');
		$this->router->register($route, self::noCallback());

		// when trying to match from any URL
		$matchedRoute = $this->router->match($method, '/any-url');

		// then the route should be returned
		assertThat($matchedRoute, is(not(null)));
	}

	#[Test]
	#[TestDox('matches with starting parameter')]
	public function parameterizedRoute_startsWithParameter_match(): void
	{
		// given a route starting with a parameter
		$route = self::route(schema: '/{map}-hiscores');
		$this->router->register($route, self::noCallback());

		// when requesting an URL that ends correctly
		$matchedRoute = $this->router->match(
			$route->method(),
			'/vq3-cj-hiscores');

		// then the route should match
		assertThat($matchedRoute, is(not(null)));
	}

	public static function malformedSchemas(): iterable
	{
		yield 'missing opening marker' => ['/id}'];
		yield 'missing closing marker' => ['/page-{page-number'];
		yield 'nested parameters' => ['/{article-{comments}}'];
	}

	#[Test]
	#[TestDox('parameters must be correctly delimited')]
	#[DataProvider('malformedSchemas')]
	public function malformedParameters_exception(string $invalidSchema): void
	{
		// given a schema with invalid parameter delimitation

		// when trying to create a route from it
		$createRoute = fn () => self::route(schema: $invalidSchema);

		// then it shouldn't work
		$this->expectException(MalformedSchemaException::class);
		$createRoute();
	}

	#[Test]
	#[TestDox('malformed parameter name in exception message')]
	#[DataProvider('malformedSchemas')]
	public function invalidParameterDelimitation_nameInException(
		string $invalidSchema): void
	{
		// given a schema with invalid parameter delimitation

		// when trying to create a route from it
		$createRoute = fn () => self::route(schema: $invalidSchema);

		// then it shouldn't work
		$this->expectExceptionMessage('Malformed schema: ' . $invalidSchema);
		$createRoute();
	}

	#[Test]
	#[TestDox('parameters are extracted')]
	#[DataProvider('methods')]
	public function parameterizedRoute_parametersExtracted(Method $method): void
	{
		// given a route with parameters
		$schema = '/{article}/{section}/page-{page}';
		$route = self::route(method: $method, schema: $schema);
		$this->router->register($route, self::noCallback());

		// when matching it
		$matchedRoute = $this->router->match(
			$method,
			'/TDD-for-dummies/comments/page-2');

		// then parameters should have been extracted
		assertThat($matchedRoute->parameters(), is([
			'article' => 'TDD-for-dummies',
			'section' => 'comments',
			'page' => '2'
		]));
	}

	public static function invalidParametersName(): iterable
	{
		yield 'empty parameter' => ['/{}', ''];
		yield 'blank parameter' => ['/{ }', ' '];
		yield 'starts with a dash' => ['/{-article}', '-article'];
		yield 'is numeric' => ['/{42}', '42'];
		yield 'starts with a number' => ['/{2be-or-not-2be}', '2be-or-not-2be'];
		yield 'starts with a space' => ['/{ foo}', ' foo'];
		yield 'end with a space' => ['/{bar }', 'bar '];
		yield 'dollar' => ["/{first}/{\x24}", '$'];
	}

	#[Test]
	#[TestDox('parameters name must be valid')]
	#[DataProvider('invalidParametersName')]
	public function invalidParameterName_exception(string $schema): void
	{
		// given a schema with invalid parameter name

		// when trying to create a route from it
		$routeCreation = fn () => self::route(schema: $schema);

		// then it should throw
		$this->expectException(MalformedSchemaException::class);
		$routeCreation();
	}

	#[Test]
	#[TestDox('invalid parameter name is in exception message')]
	#[DataProvider('invalidParametersName')]
	public function invalidParameterName_nameInException(
		string $schema,
		string $invalidName): void
	{
		// given a schema with invalid parameter name

		// when trying to create a route from it
		$routeCreation = fn () => self::route(schema: $schema);

		// then the invalid name should be in the exception
		$expectedMessage = "Invalid parameter name: '$invalidName'";
		$this->expectExceptionMessage($expectedMessage);
		$routeCreation();
	}

	#[Test]
	#[TestDox('trailing slashes are removed')]
	public function trailingSlash_removedFromSchema(): void
	{
		// given a schema with a trailing slash
		$schema = '/foo/bar/';

		// when creating a route from it
		$route = self::route(schema: $schema);

		// then
		assertThat($route->schema(), is('/foo/bar'));
	}

	#[Test]
	#[TestDox('maps route to arrow function')]
	#[DataProvider('methods')]
	public function match_boundCallback_correctOne(Method $method): void
	{
		// given a route mapped to a callback
		$route = self::route(method: $method);
		$boundCallback = fn () => true;
		$this->router->register($route, $boundCallback);

		// when matching the route
		$match = $this->router->match($method, $route->schema());

		// then the bound callback should be the mapped one
		assertThat($match->callback(), is($boundCallback));
	}

	private static function route(
		Method $method = Method::GET,
		string $schema = '/url',
		?string $name = null): Route
	{
		$name ??= uniqid(more_entropy: true);
		return new Route($method, $schema, $name);
	}

	private static function noCallback(): callable
	{
		return fn () => 'noop';
	}
}
