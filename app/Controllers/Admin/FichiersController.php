<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\Drive;
use App\Repositories\Fichiers;
use App\Repositories\Journal;
use App\Storage\Stockage;
use RuntimeException;

/**
 * Espace fichiers de la scolarité : dossiers, dépôt, publication, corbeille.
 */
final class FichiersController extends AdminController
{
    public function index(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }

        $dossierId = $requete->entier('dossier') ?: null;
        $dossier = $dossierId === null ? null : Drive::dossier($dossierId);
        if ($dossierId !== null && ($dossier === null || !Drive::dossierActif($dossierId))) {
            return $this->page('admin/fichiers', $this->donnees(null, '') + ['introuvable' => true], 404);
        }

        $recherche = mb_substr($requete->texte('recherche'), 0, 100);
        $donnees = $this->donnees($dossierId, $recherche);

        if ($requete->texte('fragment') === '1') {
            return Response::html(View::rendre('admin/partials/drive', $donnees));
        }
        return $this->page('admin/fichiers', $donnees);
    }

    public function televerser(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }

        $envoi = $requete->fichiers['fichier'] ?? null;
        if (!is_array($envoi) || is_array($envoi['name'] ?? null)) {
            return Response::json(['erreur' => 'Aucun fichier reçu.'], 400);
        }

        $nom = trim((string) $envoi['name']);
        $erreur = $this->erreurEnvoi((int) $envoi['error'], (int) $envoi['size']);
        $extension = Stockage::extension($nom);
        if ($erreur === null && !Stockage::accepte($extension)) {
            $erreur = 'Type de fichier non accepté. Déposez un PDF, un fichier Excel, CSV, Word ou une image.';
        }
        if ($erreur === null && !Stockage::contenuValide((string) $envoi['tmp_name'], $extension)) {
            $erreur = "Le contenu de « {$nom} » ne correspond pas à un fichier ." . $extension . '.';
        }
        if ($erreur !== null) {
            return Response::json(['erreur' => $erreur], 422);
        }

        $dossierId = $requete->entier('dossier_id') ?: null;
        if ($dossierId !== null && Drive::dossier($dossierId) === null) {
            return Response::json(['erreur' => "Le dossier de destination n'existe plus."], 404);
        }

        try {
            $resultat = Drive::ajouterFichier((string) $envoi['tmp_name'], $nom, $dossierId, Auth::id());
        } catch (RuntimeException $e) {
            return Response::json(['erreur' => $e->getMessage()], 500);
        }

        $fichier = Fichiers::parId($resultat['id']);
        if (!$resultat['doublon']) {
            Journal::ajouter(Auth::id(), 'Fichier déposé', $nom);
        }

        return Response::json([
            'ok' => true,
            'doublon' => $resultat['doublon'],
            'liste' => ImportsController::decrire($fichier),
            'message' => $resultat['doublon'] ? "« {$fichier['nom']} » est déjà dans ce dossier." : null,
            'fichier' => [
                'id' => (int) $fichier['id'],
                'nom' => $fichier['nom'],
                'extension' => $fichier['extension'],
            ],
        ]);
    }

    /**
     * Ouvre un fichier pour la scolarité, même s'il n'est pas publié.
     */
    public function ouvrir(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $fichier = Fichiers::parId((int) $requete->parametre('id'));
        if ($fichier === null || !Stockage::existe((string) $fichier['nom_stockage'])) {
            return Response::html(View::page('erreurs/404', ['titre' => 'Fichier introuvable']), 404);
        }
        $extension = (string) $fichier['extension'];
        return Response::fichier(
            Stockage::chemin((string) $fichier['nom_stockage']),
            (string) $fichier['nom'],
            Stockage::TYPES_SERVIS[$extension] ?? 'application/octet-stream',
            in_array($extension, Stockage::APERCU, true) && $requete->texte('telecharger') !== '1',
        );
    }

    /**
     * Actions sur un fichier : renommer, deplacer, publier, masquer, supprimer, restaurer, detruire.
     */
    public function actionFichier(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $id = (int) $requete->parametre('id');
        $fichier = Fichiers::parId($id);
        if ($fichier === null) {
            return Response::json(['erreur' => "Ce fichier n'existe plus."], 404);
        }
        $nom = (string) $fichier['nom'];

        try {
            switch ($requete->parametre('action')) {
                case 'renommer':
                    Drive::renommerFichier($id, $requete->texte('nom'));
                    $nouveau = (string) Fichiers::parId($id)['nom'];
                    Journal::ajouter(Auth::id(), 'Fichier renommé', "{$nom} → {$nouveau}");
                    return Response::json(['ok' => true, 'message' => 'Fichier renommé.']);

                case 'deplacer':
                    $destination = $requete->entier('dossier_id') ?: null;
                    Drive::deplacerFichier($id, $destination);
                    $libelle = $destination === null ? 'Fichiers' : (string) (Drive::dossier($destination)['nom'] ?? '');
                    Journal::ajouter(Auth::id(), 'Fichier déplacé', "{$nom} → {$libelle}");
                    return Response::json(['ok' => true, 'message' => "Déplacé dans « {$libelle} »."]);

                case 'publier':
                case 'masquer':
                    $public = $requete->parametre('action') === 'publier';
                    Drive::changerVisibilite($id, $public);
                    Journal::ajouter(Auth::id(), $public ? 'Fichier publié' : 'Fichier masqué', $nom);
                    return Response::json([
                        'ok' => true,
                        'public' => $public,
                        'message' => $public ? 'Visible sur le site.' : 'Masqué : le fichier n\'est plus visible sur le site.',
                    ]);

                case 'supprimer':
                    Drive::mettreFichierCorbeille($id);
                    Journal::ajouter(Auth::id(), 'Fichier mis à la corbeille', $nom);
                    return Response::json(['ok' => true, 'message' => 'Fichier placé dans la corbeille.', 'annuler' => 'restaurer']);

                case 'restaurer':
                    Drive::restaurerFichier($id);
                    Journal::ajouter(Auth::id(), 'Fichier restauré', $nom);
                    return Response::json(['ok' => true, 'message' => 'Fichier restauré.']);

                case 'detruire':
                    Drive::detruireFichier($id);
                    Journal::ajouter(Auth::id(), 'Fichier supprimé définitivement', $nom);
                    return Response::json(['ok' => true, 'message' => 'Fichier supprimé définitivement.']);
            }
        } catch (RuntimeException $e) {
            return Response::json(['erreur' => $e->getMessage()], 422);
        }

        return Response::json(['erreur' => 'Action inconnue.'], 400);
    }

    public function creerDossier(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        try {
            $id = Drive::creerDossier($requete->texte('nom'), $requete->entier('parent_id') ?: null);
        } catch (RuntimeException $e) {
            return Response::json(['erreur' => $e->getMessage()], 422);
        }
        Journal::ajouter(Auth::id(), 'Dossier créé', $requete->texte('nom'));
        return Response::json(['ok' => true, 'id' => $id, 'message' => 'Dossier créé.']);
    }

    /**
     * Actions sur un dossier : renommer, deplacer, supprimer, restaurer, detruire.
     */
    public function actionDossier(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $id = (int) $requete->parametre('id');
        $dossier = Drive::dossier($id);
        if ($dossier === null) {
            return Response::json(['erreur' => "Ce dossier n'existe plus."], 404);
        }
        $nom = (string) $dossier['nom'];

        try {
            switch ($requete->parametre('action')) {
                case 'renommer':
                    Drive::renommerDossier($id, $requete->texte('nom'));
                    Journal::ajouter(Auth::id(), 'Dossier renommé', $nom . ' → ' . $requete->texte('nom'));
                    return Response::json(['ok' => true, 'message' => 'Dossier renommé.']);

                case 'deplacer':
                    $destination = $requete->entier('dossier_id') ?: null;
                    Drive::deplacerDossier($id, $destination);
                    Journal::ajouter(Auth::id(), 'Dossier déplacé', $nom);
                    return Response::json(['ok' => true, 'message' => 'Dossier déplacé.']);

                case 'supprimer':
                    Drive::mettreDossierCorbeille($id);
                    Journal::ajouter(Auth::id(), 'Dossier mis à la corbeille', $nom);
                    return Response::json(['ok' => true, 'message' => 'Dossier placé dans la corbeille, avec son contenu.', 'annuler' => 'restaurer']);

                case 'restaurer':
                    Drive::restaurerDossier($id);
                    Journal::ajouter(Auth::id(), 'Dossier restauré', $nom);
                    return Response::json(['ok' => true, 'message' => 'Dossier restauré.']);

                case 'detruire':
                    Drive::detruireDossier($id);
                    Journal::ajouter(Auth::id(), 'Dossier supprimé définitivement', $nom);
                    return Response::json(['ok' => true, 'message' => 'Dossier supprimé définitivement.']);
            }
        } catch (RuntimeException $e) {
            return Response::json(['erreur' => $e->getMessage()], 422);
        }

        return Response::json(['erreur' => 'Action inconnue.'], 400);
    }

    public function corbeille(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        Drive::viderCorbeille();
        return $this->page('admin/corbeille', [
            'titre' => 'Corbeille',
            'section' => 'corbeille',
            'corbeille' => Drive::corbeille(),
            'scripts' => ['js/drive.js'],
        ]);
    }

    public function viderCorbeille(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $nombre = Drive::viderCorbeille(true);
        Journal::ajouter(Auth::id(), 'Corbeille vidée', $nombre . ' élément(s)');
        return Response::json(['ok' => true, 'message' => 'Corbeille vidée.']);
    }

    /**
     * Taille maximale d'un dépôt : la plus petite entre la configuration du site et celle de PHP.
     */
    public static function tailleMax(): int
    {
        $config = max(1, (int) Config::get('televersement_max_mo', 50)) * 1048576;
        $limites = array_filter([self::octets((string) ini_get('upload_max_filesize')), self::octets((string) ini_get('post_max_size'))]);
        return min($config, ...($limites ?: [$config]));
    }

    private static function octets(string $valeur): int
    {
        $valeur = trim($valeur);
        if ($valeur === '') {
            return 0;
        }
        $nombre = (int) $valeur;
        return match (strtolower(substr($valeur, -1))) {
            'g' => $nombre * 1073741824,
            'm' => $nombre * 1048576,
            'k' => $nombre * 1024,
            default => $nombre,
        };
    }

    private function erreurEnvoi(int $code, int $taille): ?string
    {
        $max = taille_lisible(self::tailleMax());
        return match (true) {
            $code === UPLOAD_ERR_INI_SIZE, $code === UPLOAD_ERR_FORM_SIZE, $taille > self::tailleMax() => "Fichier trop lourd : {$max} au maximum.",
            $code === UPLOAD_ERR_PARTIAL => "L'envoi a été interrompu. Vérifiez la connexion et recommencez.",
            $code === UPLOAD_ERR_NO_FILE => 'Aucun fichier reçu.',
            $code !== UPLOAD_ERR_OK => "Le serveur n'a pas pu recevoir le fichier (erreur {$code}).",
            $taille === 0 => 'Ce fichier est vide.',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(?int $dossierId, string $recherche): array
    {
        $chemin = Fichiers::cheminDossiers($dossierId, Fichiers::tousLesDossiers(true));
        if ($recherche !== '') {
            $resultats = Drive::rechercher($recherche);
            $dossiers = $resultats['dossiers'];
            $fichiers = $resultats['fichiers'];
        } else {
            $dossiers = Drive::sousDossiers($dossierId);
            $fichiers = Drive::fichiersDe($dossierId);
        }

        return [
            'titre' => $recherche !== '' ? 'Recherche' : (end($chemin)['nom'] ?? 'Fichiers'),
            'section' => 'fichiers',
            'dossierId' => $dossierId,
            'chemin' => $chemin,
            'recherche' => $recherche,
            'rechercheValeur' => $recherche,
            'dossiers' => $dossiers,
            'fichiers' => $fichiers,
            'arborescence' => Drive::arborescence(),
            'tailleMax' => self::tailleMax(),
            'scripts' => ['js/drive.js'],
        ];
    }
}
