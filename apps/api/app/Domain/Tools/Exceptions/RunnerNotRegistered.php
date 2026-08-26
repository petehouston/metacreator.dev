<?php

declare(strict_types=1);

namespace App\Domain\Tools\Exceptions;

use RuntimeException;

final class RunnerNotRegistered extends RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct(
            "No runner is registered for tool key [{$key}]. "
            .'Register it in ToolServiceProvider::$runners.'
        );
    }
}
