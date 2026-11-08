<?php

declare(strict_types=1);

use App\Controllers\Admin\AccueilController as AdminAccueil;
use App\Controllers\Admin\ConnexionController;
use App\Controllers\Admin\EtudiantsController;
use App\Controllers\Admin\FichiersController;
use App\Controllers\Admin\ImportsController;
use App\Controllers\DocumentsController;
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
    $routes->get('/documents', [DocumentsController::class, 'index']);
    $routes->get('/documents/{id}', [DocumentsController::class, 'fichier']);
    $routes->get('/documents/{id}/{nom}', [DocumentsController::class, 'fichier']);

    // Administration.
    $routes->get('/admin', [AdminAccueil::class, 'index']);
    $routes->get('/admin/connexion', [ConnexionController::class, 'afficher']);
    $routes->post('/admin/connexion', [ConnexionController::class, 'connecter']);
    $routes->post('/admin/deconnexion', [ConnexionController::class, 'deconnecter']);

    // Espace fichiers.
    $routes->get('/admin/fichiers', [FichiersController::class, 'index']);
    $routes->post('/admin/fichiers/televerser', [FichiersController::class, 'televerser']);
    $routes->get('/admin/fichiers/{id}/ouvrir', [FichiersController::class, 'ouvrir']);
    $routes->post('/admin/fichiers/{id}/{action}', [FichiersController::class, 'actionFichier']);
    $routes->post('/admin/dossiers', [FichiersController::class, 'creerDossier']);
    $routes->post('/admin/dossiers/{id}/{action}', [FichiersController::class, 'actionDossier']);
    $routes->get('/admin/corbeille', [FichiersController::class, 'corbeille']);
    $routes->post('/admin/corbeille/vider', [FichiersController::class, 'viderCorbeille']);

    // Publication des listes dans la recherche.
    $routes->post('/admin/imports/analyser', [ImportsController::class, 'analyser']);
    $routes->post('/admin/imports', [ImportsController::class, 'importer']);
    $routes->post('/admin/imports/{id}/annuler', [ImportsController::class, 'annuler']);

    // Étudiants en ligne et historique.
    $routes->get('/admin/etudiants', [EtudiantsController::class, 'index']);
    $routes->post('/admin/etudiants', [EtudiantsController::class, 'creer']);
    $routes->post('/admin/etudiants/{id}/{action}', [EtudiantsController::class, 'action']);
    $routes->get('/admin/historique', [EtudiantsController::class, 'historique']);
};
