<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Schema;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;

/**
 * Installation en deux étapes : base de données, puis compte de la scolarité.
 * Une fois le compte créé, ces pages ne sont plus accessibles.
 */
final class InstallationController
{
    public function afficher(Request $requete): Response
    {
        if (App::installee()) {
            return Response::redirection(Url::vers('admin'), 302);
        }

        $etape = Config::presente() && $this->baseJoignable() ? 'compte' : 'base';

        return $this->page($etape, [
            'verifications' => $this->verifications(),
            'valeurs' => [
                'host' => Config::get('db.host', 'localhost'),
                'port' => Config::get('db.port', 3306),
                'name' => Config::get('db.name', ''),
                'user' => Config::get('db.user', ''),
                'url_base' => Config::get('url_base', ''),
            ],
        ]);
    }

    public function base(Request $requete): Response
    {
        if (App::installee()) {
            return Response::redirection(Url::vers('admin'), 302);
        }

        $valeurs = [
            'host' => $requete->texte('host', 'localhost'),
            'port' => $requete->entier('port', 3306),
            'name' => $requete->texte('name'),
            'user' => $requete->texte('user'),
            'url_base' => rtrim($requete->texte('url_base'), '/'),
        ];
        $motDePasse = (string) $requete->input('pass', '');

        $erreurs = [];
        foreach (['host' => 'le serveur', 'name' => 'le nom de la base', 'user' => "l'utilisateur"] as $cle => $libelle) {
            if ($valeurs[$cle] === '') {
                $erreurs[$cle] = "Indiquez {$libelle}.";
            }
        }
        if ($valeurs['url_base'] !== '' && !preg_match('#^https?://[^\s/]+#', $valeurs['url_base'])) {
            $erreurs['url_base'] = 'Adresse invalide. Exemple : https://effets.iutdouala.cm';
        }

        if ($erreurs === []) {
            try {
                $pdo = Database::connecter($valeurs['host'], $valeurs['port'], $valeurs['name'], $valeurs['user'], $motDePasse);
                Schema::installer($pdo);
            } catch (\PDOException $e) {
                $erreurs['connexion'] = $this->messageConnexion($e);
            }
        }

        if ($erreurs !== []) {
            return $this->page('base', [
                'verifications' => $this->verifications(),
                'valeurs' => $valeurs,
                'erreurs' => $erreurs,
            ], 422);
        }

        $ecrit = Config::ecrire([
            'db' => [
                'host' => $valeurs['host'],
                'port' => $valeurs['port'],
                'name' => $valeurs['name'],
                'user' => $valeurs['user'],
                'pass' => $motDePasse,
            ],
            'url_base' => $valeurs['url_base'],
            'televersement_max_mo' => 50,
            'debug' => false,
            'installe' => false,
        ]);

        if (!$ecrit) {
            return $this->page('base', [
                'verifications' => $this->verifications(),
                'valeurs' => $valeurs,
                'erreurs' => ['connexion' => "Impossible d'écrire config/config.php. Donnez les droits d'écriture au dossier config/ (755) puis recommencez."],
            ], 500);
        }

        return Response::redirection(Url::vers('installation'));
    }

    public function compte(Request $requete): Response
    {
        if (App::installee()) {
            return Response::redirection(Url::vers('admin'), 302);
        }
        if (!Config::presente() || !$this->baseJoignable()) {
            return Response::redirection(Url::vers('installation'));
        }

        $nom = $requete->texte('nom');
        $identifiant = mb_strtolower($requete->texte('identifiant'));
        $motDePasse = (string) $requete->input('mot_de_passe', '');
        $confirmation = (string) $requete->input('confirmation', '');

        $erreurs = [];
        if ($nom === '') {
            $erreurs['nom'] = 'Indiquez le nom affiché dans l\'administration.';
        }
        if (!preg_match('/^[a-z0-9._@-]{3,120}$/', $identifiant)) {
            $erreurs['identifiant'] = 'Au moins 3 caractères : lettres, chiffres, point, tiret ou @.';
        }
        if (mb_strlen($motDePasse) < 10) {
            $erreurs['mot_de_passe'] = 'Au moins 10 caractères.';
        } elseif ($motDePasse !== $confirmation) {
            $erreurs['confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
        }

        if ($erreurs !== []) {
            return $this->page('compte', [
                'valeurs' => ['nom' => $nom, 'identifiant' => $identifiant],
                'erreurs' => $erreurs,
            ], 422);
        }

        Database::requete(
            'INSERT INTO utilisateurs (nom, identifiant, mot_de_passe) VALUES (?, ?, ?)',
            [$nom, $identifiant, password_hash($motDePasse, PASSWORD_DEFAULT)],
        );
        $id = Database::dernierId();

        $config = require Config::chemin();
        $config['installe'] = true;
        Config::ecrire($config);

        Session::regenerer();
        Session::set('utilisateur_id', $id);
        Session::flash('succes', 'Installation terminée. Déposez votre première liste pour la rendre consultable.');

        return Response::redirection(Url::vers('admin'));
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function page(string $etape, array $donnees, int $statut = 200): Response
    {
        $donnees += ['erreurs' => [], 'valeurs' => [], 'verifications' => []];
        return Response::html(View::page('install/' . $etape, array_merge($donnees, [
            'titre' => 'Installation',
            'etape' => $etape,
        ]), 'layouts/installation'), $statut);
    }

    private function baseJoignable(): bool
    {
        try {
            Database::valeur('SELECT COUNT(*) FROM utilisateurs');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function messageConnexion(\PDOException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? $e->getCode());
        return match ($code) {
            1045 => "Identifiant ou mot de passe de la base refusé. Vérifiez-les dans cPanel, rubrique « MySQL Databases ».",
            1049 => "Cette base n'existe pas. Créez-la d'abord dans cPanel, puis recommencez.",
            2002, 2005 => "Serveur introuvable. Sur iFastNet, le serveur est souvent « localhost » ; vérifiez dans cPanel.",
            1044 => "L'utilisateur n'a pas accès à cette base. Dans cPanel, ajoutez-le à la base avec tous les privilèges.",
            default => 'Connexion impossible : ' . $e->getMessage(),
        };
    }

    /**
     * @return list<array{libelle: string, ok: bool, aide: string}>
     */
    private function verifications(): array
    {
        $ecrivable = static fn (string $dossier): bool => is_dir(RACINE . $dossier) && is_writable(RACINE . $dossier);

        return [
            ['libelle' => 'PHP 8.1 ou plus récent', 'ok' => PHP_VERSION_ID >= 80100, 'aide' => 'Version actuelle : ' . PHP_VERSION . '. Changez-la dans cPanel, « Select PHP Version ».'],
            ['libelle' => 'Extension PDO MySQL', 'ok' => extension_loaded('pdo_mysql'), 'aide' => 'Activez « pdo_mysql » dans cPanel, « Select PHP Version ».'],
            ['libelle' => 'Extension Zip (lecture des fichiers Excel)', 'ok' => class_exists(\ZipArchive::class), 'aide' => 'Activez « zip » dans cPanel, « Select PHP Version ».'],
            ['libelle' => 'Extension Mbstring', 'ok' => extension_loaded('mbstring'), 'aide' => 'Activez « mbstring » dans cPanel, « Select PHP Version ».'],
            ['libelle' => 'Extension Fileinfo', 'ok' => extension_loaded('fileinfo'), 'aide' => 'Activez « fileinfo » dans cPanel, « Select PHP Version ».'],
            ['libelle' => 'Dossier config/ modifiable', 'ok' => $ecrivable('/config'), 'aide' => 'Donnez les droits 755 au dossier config/.'],
            ['libelle' => 'Dossier storage/ modifiable', 'ok' => $ecrivable('/storage/uploads') && $ecrivable('/storage/cache'), 'aide' => 'Donnez les droits 755 au dossier storage/ et à ses sous-dossiers.'],
        ];
    }
}
