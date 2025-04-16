<?php

namespace Zenit\Routing;

class DuplicateRouteNameException extends \InvalidArgumentException
{

	public function __construct(string $name)
	{
		parent::__construct("Route name already in use: '$name'");
	}
}
