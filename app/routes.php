<?php

declare(strict_types=1);

use App\Controllers\FicheController;
use App\Controllers\InstallationController;
use App\Controllers\ListeController;
use App\Controllers\PagesController;
use App\Controllers\RechercheController;
use App\Core\Router;

return static function (Router $routes): void {
    // Installation (accessible seulement tant que le site n'est pas installé).
    $routes->get('/installation', [InstallationController::class, 'afficher']);
    $routes->post('/installation/base', [InstallationController::class, 'base']);
    $routes->post('/installation/compte', [InstallationController::class, 'compte']);

    // Site public.
    $routes->get('/', [PagesController::class, 'accueil']);
    $routes->get('/api/recherche', [RechercheController::class, 'api']);
    $routes->get('/fiche/{ref}', [FicheController::class, 'afficher']);
    $routes->get('/fiche/{ref}/pdf', [FicheController::class, 'pdf']);
    $routes->get('/liste', [ListeController::class, 'afficher']);
};
