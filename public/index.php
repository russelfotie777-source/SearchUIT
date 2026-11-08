<?php

declare(strict_types=1);

// Serveur de développement PHP (php -S) : les fichiers statiques sont servis directement.
if (PHP_SAPI === 'cli-server') {
    $fichier = __DIR__ . (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($fichier) && $fichier !== __FILE__) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\App::lancer();
