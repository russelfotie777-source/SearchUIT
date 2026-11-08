<?php

declare(strict_types=1);

use App\Controllers\PagesController;
use App\Core\Router;

return static function (Router $routes): void {
    // Site public.
    $routes->get('/', [PagesController::class, 'accueil']);
};
