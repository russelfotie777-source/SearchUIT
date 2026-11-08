<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Import\AnalyseurListe;
use App\Import\Importeur;
use App\Repositories\Etudiants;
use App\Repositories\Fichiers;
use App\Storage\Stockage;
use RuntimeException;

/**
 * Publication d'une liste Excel ou CSV dans la recherche des étudiants.
 */
final class ImportsController extends AdminController
{
    /**
     * Analyse un fichier déposé et décrit la liste trouvée, sans rien enregistrer.
     */
    public function analyser(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $fichier = Fichiers::parId($requete->entier('fichier_id'));
        if ($fichier === null) {
            return Response::json(['erreur' => "Ce fichier n'existe plus."], 404);
        }
        $description = self::decrire($fichier);
        if ($description === null) {
            return Response::json(['erreur' => "Aucune liste d'étudiants reconnue dans ce fichier. Il faut au moins une colonne « Noms et prénoms » et une colonne « Effets disponibles »."], 422);
        }
        return Response::json(['ok' => true, 'liste' => $description]);
    }

    public function importer(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $fichier = Fichiers::parId($requete->entier('fichier_id'));
        if ($fichier === null || !Stockage::existe((string) $fichier['nom_stockage'])) {
            return Response::json(['erreur' => "Ce fichier n'existe plus."], 404);
        }
        $mode = $requete->texte('mode') === Importeur::AJOUT ? Importeur::AJOUT : Importeur::REMPLACEMENT;

        try {
            @set_time_limit(120);
            $analyse = AnalyseurListe::depuisFichier(Stockage::chemin((string) $fichier['nom_stockage']), (string) $fichier['extension']);
            $resultat = (new Importeur())->importer(
                $analyse['etudiants'],
                $mode,
                (string) $fichier['nom'],
                (int) $fichier['id'],
                Auth::id(),
                $analyse['ignorees'],
            );
        } catch (RuntimeException $e) {
            return Response::json(['erreur' => $e->getMessage()], 422);
        }

        $total = (int) Database::valeur('SELECT COUNT(*) FROM etudiants');
        if ($mode === Importeur::AJOUT) {
            $message = $resultat['ajoutes'] === 0
                ? 'Aucun nouvel étudiant : toute cette liste était déjà en ligne.'
                : sprintf('%s ajouté%s. La recherche compte maintenant %s.', pluriel($resultat['ajoutes'], 'étudiant'), $resultat['ajoutes'] > 1 ? 's' : '', pluriel($total, 'étudiant'));
        } else {
            $message = sprintf('Liste publiée : %s en ligne.', pluriel($resultat['ajoutes'], 'étudiant'));
            if ($resultat['retires_conserves'] > 0) {
                $message .= sprintf(' %s comme déjà retirée%s.', pluriel($resultat['retires_conserves'], 'fiche reste marquée', 'fiches restent marquées'), $resultat['retires_conserves'] > 1 ? 's' : '');
            }
        }

        return Response::json(['ok' => true, 'message' => $message, 'resultat' => $resultat]);
    }

    public function annuler(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $supprimes = (new Importeur())->annuler((int) $requete->parametre('id'), Auth::id());
        return Response::json([
            'ok' => true,
            'message' => $supprimes === 0 ? 'Cet import ne contenait plus aucun étudiant.' : pluriel($supprimes, 'étudiant') . ' retiré' . ($supprimes > 1 ? 's' : '') . ' de la recherche.',
        ]);
    }

    /**
     * Résumé d'une liste reconnue dans un fichier, ou null si ce n'est pas une liste.
     *
     * @param array<string, mixed> $fichier
     * @return array<string, mixed>|null
     */
    public static function decrire(array $fichier): ?array
    {
        $extension = (string) $fichier['extension'];
        if (!in_array($extension, AnalyseurListe::EXTENSIONS, true) || !Stockage::existe((string) $fichier['nom_stockage'])) {
            return null;
        }
        try {
            @set_time_limit(120);
            $analyse = AnalyseurListe::depuisFichier(Stockage::chemin((string) $fichier['nom_stockage']), $extension);
        } catch (RuntimeException) {
            return null;
        }

        $libellesColonnes = [
            'numero' => 'N°', 'nom' => 'Nom', 'matricule' => 'Matricule', 'effets' => 'Effets',
            'filiere' => 'Filière', 'annee' => 'Année', 'niveau' => 'Niveau',
        ];

        return [
            'fichier_id' => (int) $fichier['id'],
            'nom_fichier' => (string) $fichier['nom'],
            'etudiants' => count($analyse['etudiants']),
            'ignorees' => $analyse['ignorees'],
            'colonnes' => array_values(array_map(static fn (string $c): string => $libellesColonnes[$c] ?? $c, array_keys($analyse['colonnes']))),
            'avertissements' => $analyse['avertissements'],
            'en_ligne' => (int) (Etudiants::statistiques()['total'] ?? 0),
            'apercu' => array_map(static fn (array $e): array => [
                'nom' => $e['nom'],
                'effets' => $e['effets'],
                'filiere' => $e['filiere'] ?? '',
                'annee' => $e['annee'] ?? '',
            ], array_slice($analyse['etudiants'], 0, 5)),
        ];
    }
}
