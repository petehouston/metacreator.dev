<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Controllers stay thin: build a payload, call an action, return a resource.
 * An architecture test asserts they never touch models or the query builder directly.
 */
abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;
}
