<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Domain\Effets;
use App\Pdf\Affiche;
use App\Qr\CodeQr;
use App\Repositories\Etudiants;
use App\Repositories\Journal;
use App\Repositories\Legende;
use App\Repositories\Parametres;

/**
 * Réglages : informations de retrait, affiche QR code, légende des codes, comptes.
 */
final class ReglagesController extends AdminController
{
    private const CHAMPS_INFOS = [
        'etablissement' => 120,
        'service' => 120,
        'retrait_lieu' => 200,
        'retrait_horaires' => 400,
        'retrait_pieces' => 300,
        'contact_telephone' => 60,
        'contact_email' => 120,
    ];

    public function index(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $adresse = self::adresseSite();
        return $this->page('admin/reglages', [
            'titre' => 'Réglages',
            'section' => 'reglages',
            'site' => Parametres::site(),
            'adresse' => $adresse,
            'adresseConfiguree' => (string) Config::get('url_base', ''),
            'qr' => CodeQr::generer($adresse)->svg(2, '#0f2a1d', 'QR code vers ' . $adresse),
            'legende' => Legende::complete(),
            'codesPresents' => Etudiants::statistiques()['codes'],
            'comptes' => Database::tous('SELECT id, nom, identifiant, derniere_connexion FROM utilisateurs ORDER BY nom'),
            'scripts' => ['js/reglages.js'],
        ]);
    }

    public function infos(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        foreach (self::CHAMPS_INFOS as $cle => $longueur) {
            $valeur = trim(str_replace("\r\n", "\n", (string) $requete->input($cle, '')));
            if (($cle === 'etablissement' || $cle === 'service') && $valeur === '') {
                $valeur = Parametres::DEFAUTS[$cle];
            }
            Parametres::set($cle, mb_substr($valeur, 0, $longueur));
        }

        $adresse = rtrim($requete->texte('adresse_site'), '/');
        if ($adresse !== (string) Config::get('url_base', '')) {
            if ($adresse !== '' && !preg_match('#^https?://[^\s/]+(/[^\s]*)?$#', $adresse)) {
                Session::flash('erreur', 'Adresse du site invalide. Exemple : https://effets.iutdouala.cm');
                return Response::redirection(Url::vers('admin/reglages#retrait'));
            }
            $config = require Config::chemin();
            $config['url_base'] = $adresse;
            if (!Config::ecrire($config)) {
                Session::flash('erreur', "L'adresse n'a pas pu être enregistrée : le dossier config/ n'est pas modifiable.");
                return Response::redirection(Url::vers('admin/reglages#retrait'));
            }
        }

        Journal::ajouter(Auth::id(), 'Informations de retrait modifiées');
        Session::flash('succes', 'Informations enregistrées. Elles apparaissent sur le site et sur les fiches.');
        return Response::redirection(Url::vers('admin/reglages#retrait'));
    }

    public function legende(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $saisie = $requete->input('legende', []);
        $personnalisee = [];
        foreach (Effets::LEGENDE as $code => [$courtDefaut, $completDefaut]) {
            $court = trim((string) ($saisie[$code]['court'] ?? ''));
            $complet = trim((string) ($saisie[$code]['complet'] ?? ''));
            $court = $court === '' ? $courtDefaut : mb_substr($court, 0, 20);
            $complet = $complet === '' ? $completDefaut : mb_substr($complet, 0, 120);
            // Seuls les libellés modifiés sont enregistrés : les autres suivent les valeurs par défaut.
            if ($court !== $courtDefaut || $complet !== $completDefaut) {
                $personnalisee[$code] = [$court, $complet];
            }
        }
        Parametres::set('legende', $personnalisee === [] ? null : json_encode($personnalisee, JSON_UNESCAPED_UNICODE));
        Etudiants::invalider();
        Journal::ajouter(Auth::id(), 'Légende des codes modifiée');
        Session::flash('succes', 'Légende enregistrée.');
        return Response::redirection(Url::vers('admin/reglages#codes'));
    }

    public function motDePasse(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $compte = Database::un('SELECT * FROM utilisateurs WHERE id = ?', [Auth::id()]);
        $actuel = (string) $requete->input('actuel', '');
        $nouveau = (string) $requete->input('nouveau', '');

        $erreur = match (true) {
            $compte === null || !password_verify($actuel, (string) $compte['mot_de_passe']) => 'Le mot de passe actuel est incorrect.',
            mb_strlen($nouveau) < 10 => 'Le nouveau mot de passe doit faire au moins 10 caractères.',
            $nouveau !== (string) $requete->input('confirmation', '') => 'Les deux nouveaux mots de passe ne sont pas identiques.',
            default => null,
        };
        if ($erreur !== null) {
            Session::flash('erreur', $erreur);
            return Response::redirection(Url::vers('admin/reglages#compte'));
        }

        Database::requete('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?', [password_hash($nouveau, PASSWORD_DEFAULT), Auth::id()]);
        Session::regenerer();
        Journal::ajouter(Auth::id(), 'Mot de passe changé');
        Session::flash('succes', 'Mot de passe changé.');
        return Response::redirection(Url::vers('admin/reglages#compte'));
    }

    public function ajouterCompte(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $nom = $requete->texte('nom');
        $identifiant = mb_strtolower($requete->texte('identifiant'));
        $motDePasse = (string) $requete->input('mot_de_passe', '');

        $erreur = match (true) {
            $nom === '' => 'Indiquez le nom de la personne.',
            !preg_match('/^[a-z0-9._@-]{3,120}$/', $identifiant) => 'Identifiant : au moins 3 caractères, lettres, chiffres, point, tiret ou @.',
            Database::valeur('SELECT COUNT(*) FROM utilisateurs WHERE identifiant = ?', [$identifiant]) > 0 => 'Cet identifiant est déjà pris.',
            mb_strlen($motDePasse) < 10 => 'Le mot de passe doit faire au moins 10 caractères.',
            default => null,
        };
        if ($erreur !== null) {
            Session::flash('erreur', $erreur);
            return Response::redirection(Url::vers('admin/reglages#comptes'));
        }

        Database::requete(
            'INSERT INTO utilisateurs (nom, identifiant, mot_de_passe) VALUES (?, ?, ?)',
            [mb_substr($nom, 0, 120), $identifiant, password_hash($motDePasse, PASSWORD_DEFAULT)],
        );
        Journal::ajouter(Auth::id(), 'Compte créé', $nom . ' (' . $identifiant . ')');
        Session::flash('succes', "Compte créé. Transmettez à {$nom} son identifiant « {$identifiant} » et son mot de passe.");
        return Response::redirection(Url::vers('admin/reglages#comptes'));
    }

    public function supprimerCompte(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $id = (int) $requete->parametre('id');
        if ($id === Auth::id()) {
            Session::flash('erreur', 'Vous ne pouvez pas supprimer votre propre compte.');
            return Response::redirection(Url::vers('admin/reglages#comptes'));
        }
        $compte = Database::un('SELECT nom, identifiant FROM utilisateurs WHERE id = ?', [$id]);
        if ($compte !== null) {
            Database::requete('DELETE FROM utilisateurs WHERE id = ?', [$id]);
            Journal::ajouter(Auth::id(), 'Compte supprimé', $compte['nom'] . ' (' . $compte['identifiant'] . ')');
            Session::flash('succes', 'Compte supprimé.');
        }
        return Response::redirection(Url::vers('admin/reglages#comptes'));
    }

    public function affiche(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        return Response::contenu(Affiche::generer(self::adresseSite(), Parametres::site()), 'affiche-effets-academiques.pdf', 'application/pdf', true);
    }

    public function qrSvg(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $adresse = self::adresseSite();
        return Response::contenu(CodeQr::generer($adresse)->svg(4, '#000000', 'QR code vers ' . $adresse), 'qr-code-effets-academiques.svg', 'image/svg+xml');
    }

    /**
     * Adresse publique de la recherche, celle qu'ouvre le QR code.
     */
    public static function adresseSite(): string
    {
        return rtrim(Url::absolue('/'), '/') . '/';
    }
}
