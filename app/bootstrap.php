<?php

declare(strict_types=1);

/*
 * Démarrage commun au site, à l'administration et aux scripts en ligne de commande.
 * Aucune dépendance externe : le projet se dépose tel quel sur un hébergement PHP.
 */

define('RACINE', dirname(__DIR__));

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Ce site nécessite PHP 8.1 ou plus récent. Version actuelle : ' . PHP_VERSION);
}

spl_autoload_register(static function (string $classe): void {
    $prefixe = 'App\\';
    if (!str_starts_with($classe, $prefixe)) {
        return;
    }
    $chemin = RACINE . '/app/' . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
    if (is_file($chemin)) {
        require $chemin;
    }
});

require RACINE . '/app/helpers.php';

date_default_timezone_set('Africa/Douala');
mb_internal_encoding('UTF-8');

App\Core\Config::charger(RACINE . '/config/config.php');

$debug = (bool) App\Core\Config::get('debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', RACINE . '/storage/logs/php-' . date('Y-m') . '.log');
