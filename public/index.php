<?php

/**
 * The single front controller. Every request the web server cannot serve as a
 * static file is rewritten here (see the deploy docs); this builds the kernel,
 * resolves the request, and sends the response. All application logic lives in
 * src/ so this file stays a two-line bootstrap.
 */

declare(strict_types=1);

use Directorium\Api\Http\Request;
use Directorium\Api\Kernel;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Kernel())->handle(Request::fromGlobals())->send();
