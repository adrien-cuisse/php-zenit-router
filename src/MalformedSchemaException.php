<?php

namespace Zenit\Routing;

use InvalidArgumentException;

class MalformedSchemaException extends InvalidArgumentException
{
	public function __construct(string $message)
	{
		parent::__construct($message);
	}
}
