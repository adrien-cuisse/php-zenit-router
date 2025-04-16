<?php

declare(strict_types=1);

namespace Zenit\Routing;

error_reporting(E_ALL);

final readonly class Route
{
	/**
	 * @var string - the schema, which may contain parameters delimited
	 * 	by markers
	 */
	private string $schema;

	/**
	 * @var string[] - parameters stored in the schema
	 */
	private array $parametersName;

	/**
	 * @var string - the regex to capture parameters value from a requested URL
	 */
	private string $parametersValueCaptureRegex;

	private const PARAMETER_OPEN_MARKER = '{';
	private const PARAMETER_CLOSE_MARKER = '}';

	private const VALID_PHP_IDENTIFIER_CLASS =
		'[a-zA-Z_][a-zA-Z0-9_\-]*';

	/**
	 * @var string - the regex to capture anything between markers
	 */
	private const ANYTHING_BETWEEN_MARKERS =
		'/'
		. self::PARAMETER_OPEN_MARKER
		. '([^'
		. self::PARAMETER_CLOSE_MARKER
		. ']*)'
		. self::PARAMETER_CLOSE_MARKER
		. '/';

	/**
	 * @param Method $method - the HTTP method to bind
	 * @param string $schema - the schema, which may contain parameters
	 * 	delimited by markers
	 * @param string $name - the name of the route
	 *
	 * @throws MalformedSchemaException - if parameters have invalid
	 * 	delimitation, or if they have invalid names
	 */
	public function __construct(
		private Method $method,
		string $schema,
		private string $name)
	{
		self::throwOnInvalidSchema($schema);
		$this->schema = preg_replace('/ \/* $/x', '', $schema);

		$this->parametersName = self::extractParametersName($schema);
		self::throwOnInvalidParametersName($this->parametersName);

		$this->parametersValueCaptureRegex =
			self::replaceMarkersWithCaptures($schema);
	}

	/**
	 * Checks if the requested URL matches the schema
	 *
	 * @param Method $method - the requested method
	 * @param string $requestedUrl - the requested URL to try to match
	 *
	 * @return array<string, string>|false - if the URL matched, returns
	 * 	parameters name as keys and values as values, false if the URL
	 * 	didn't match
	 */
	public function matches(Method $method, string $requestedUrl): array|false
	{
		if ($this->schema === $requestedUrl)
			return [];

		$parametersValue = $this->extractParametersValue($requestedUrl);
		if ($parametersValue === false)
			return false;

		return array_combine(
			keys: $this->parametersName,
			values: $parametersValue);
	}

	public function method(): Method
	{
		return $this->method;
	}

	public function schema(): string
	{
		return $this->schema;
	}

	public function name(): string
	{
		return $this->name;
	}

	/**
	 * @param string $schema - the schema to extract names from
	 *
	 * @return string[] - parameters stored in the schema, empty if none
	 */
	private static function extractParametersName(string $schema): array
	{
		$parametersName = [];

		preg_match_all(
			pattern: self::ANYTHING_BETWEEN_MARKERS,
			subject: $schema,
			matches: $parametersName);

		return $parametersName[1];
	}

	/**
	 * @param string $schema - the schema to check
	 *
	 * @return bool - true if at least 1 parameter has invalid delimitation,
	 * 	false otherwise
	 */
	private static function schemaIsInvalid(string $schema): bool
	{
		$depth = 0;

		foreach (str_split($schema) as $char)
		{
			if ($char === self::PARAMETER_OPEN_MARKER)
				$depth++;
			else if ($char === self::PARAMETER_CLOSE_MARKER)
				$depth--;

			if (($depth < 0) || ($depth > 1))
				return true;
		}

		return $depth !== 0;
	}

	/**
	 * Puts values capture patterns where markers are in the schema
	 *
	 * @param string $schema - the schema to build the regex from
	 *
	 * @return string - a regex to match a requested URL and extract parameters
	 * 	value
	 */
	private static function replaceMarkersWithCaptures(string $schema): string
	{
		$escapedSchema = str_replace('/', '\\/', $schema);

		$pattern = preg_replace(
			pattern: self::ANYTHING_BETWEEN_MARKERS,
			replacement: '(.+)',
			subject: $escapedSchema);

		return '/' . $pattern . '/';
	}

	/**
	 * @param string $requestedUrl - the requested URL to extract values from
	 *
	 * @return string[]|false - if URL matches the schema, returns a value for
	 * 	each parameter, in the same order as they are in the schema, false
	 * 	if the URL didn't match the schema
	 */
	private function extractParametersValue(string $requestedUrl): array|false
	{
		$parametersValue = [];

		$urlMatches = preg_match_all(
			pattern: $this->parametersValueCaptureRegex,
			subject: $requestedUrl,
			matches: $parametersValue,
			flags: PREG_SET_ORDER);

		if ($urlMatches !== 1)
			return false;

		return array_slice($parametersValue[0], 1);
	}

	/**
	 * @param string $schema - the schema to check
	 *
	 * @return void
	 *
	 * @throws MalformedSchemaException - if at least 1 parameter has invalid
	 * 	delimitation
	 */
	private static function throwOnInvalidSchema(string $schema): void
	{
		if (self::schemaIsInvalid($schema))
			throw new MalformedSchemaException("Malformed schema: $schema");
	}

	/**
	 * @param string[] $names - the parameters name to check
	 *
	 * @return void
	 *
	 * @throws MalformedSchemaException - if any name is invalid
	 */
	private static function throwOnInvalidParametersName(array $names): void
	{
		$validNameRegex = '/^' . self::VALID_PHP_IDENTIFIER_CLASS . '$/';

		foreach ($names as $name)
		{
			if (preg_match($validNameRegex, $name) === 1)
				continue;

			$message = "Invalid parameter name: '$name'";
			throw new MalformedSchemaException($message);
		}
	}
}
