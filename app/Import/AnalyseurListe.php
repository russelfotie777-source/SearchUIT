<?php

declare(strict_types=1);

namespace App\Import;

use App\Domain\Annee;
use App\Domain\Effets;
use App\Domain\Filiere;
use App\Domain\NomEtudiant;
use App\Domain\Texte;
use RuntimeException;

/**
 * Reconnaît une liste d'effets académiques dans un fichier Excel ou CSV :
 * trouve la ligne d'en-tête, associe chaque colonne à son rôle,
 * puis transforme chaque ligne en étudiant normalisé.
 */
final class AnalyseurListe
{
    public const EXTENSIONS = ['xlsx', 'csv'];

    /** Mots qui identifient chaque colonne dans l'en-tête (sans accents, en majuscules). */
    private const COLONNES = [
        'matricule' => ['MATRICULE', 'MATRICULES', 'MAT', 'NUMERO MATRICULE', 'ID ETUDIANT'],
        'numero' => ['N', 'NO', 'NUM', 'NUMERO', 'N ORDRE', 'ORDRE', '#'],
        'nom' => ['NOM', 'NOMS', 'NOM ET PRENOM', 'NOMS ET PRENOMS', 'NOM ET PRENOMS', 'NOMS PRENOMS', 'ETUDIANT', 'NOM COMPLET'],
        'effets' => ['EFFET', 'EFFETS', 'EFFETS DISPONIBLES', 'DOCUMENTS', 'DOCUMENTS DISPONIBLES', 'PIECES', 'PIECES DISPONIBLES'],
        'filiere' => ['FILIERE', 'FILIERES', 'SPECIALITE', 'DEPARTEMENT', 'PARCOURS', 'OPTION'],
        'annee' => ['ANNEE', 'ANNEES', 'ANNEE ACADEMIQUE', 'PROMOTION', 'SESSION'],
        'niveau' => ['NIVEAU', 'CYCLE', 'DIPLOME', 'CLASSE'],
    ];

    private const NIVEAUX = '/^(DUT|LICENCE|BTS|HND|MASTER|LP|BACHELOR|INGENIEUR)\b/i';

    /**
     * @return array{
     *     etudiants: list<array<string, mixed>>,
     *     ignorees: int,
     *     colonnes: array<string, int>,
     *     ligne_entete: int,
     *     avertissements: list<string>
     * }
     */
    public static function depuisFichier(string $chemin, string $extension): array
    {
        $extension = strtolower($extension);
        $lignes = match ($extension) {
            'xlsx' => (new LecteurXlsx($chemin))->lignes(),
            'csv', 'txt' => (new LecteurCsv($chemin))->lignes(),
            'xls' => throw new RuntimeException("Les anciens fichiers Excel (.xls) ne sont pas lus. Ouvrez-le dans Excel puis « Enregistrer sous » au format .xlsx."),
            default => throw new RuntimeException('Seuls les fichiers Excel (.xlsx) et CSV peuvent être importés comme liste.'),
        };
        return self::analyser($lignes);
    }

    /**
     * @param iterable<int, array<int, mixed>> $lignes
     * @return array{
     *     etudiants: list<array<string, mixed>>,
     *     ignorees: int,
     *     colonnes: array<string, int>,
     *     ligne_entete: int,
     *     avertissements: list<string>
     * }
     */
    public static function analyser(iterable $lignes): array
    {
        $colonnes = null;
        $ligneEntete = 0;
        $etudiants = [];
        $ignorees = 0;
        $avertissements = [];

        foreach ($lignes as $numero => $ligne) {
            if ($colonnes === null) {
                $trouvees = self::colonnesEntete($ligne);
                if ($trouvees !== null) {
                    $colonnes = $trouvees;
                    $ligneEntete = $numero;
                } elseif ($numero > 60) {
                    break;
                }
                continue;
            }

            $etudiants[] = $ligne;
        }

        if ($colonnes === null) {
            throw new RuntimeException("Aucune ligne d'en-tête trouvée. La liste doit avoir au moins les colonnes « Noms et prénoms » et « Effets disponibles ».");
        }

        if (!isset($colonnes['niveau'])) {
            // Colonne sans titre remplie de « DUT 1 », « Licence »… : c'est le niveau.
            $colonneNiveau = self::colonneNiveauSansTitre($etudiants, $colonnes);
            if ($colonneNiveau !== null) {
                $colonnes['niveau'] = $colonneNiveau;
            }
        }

        $resultat = [];
        foreach ($etudiants as $ligne) {
            $etudiant = self::etudiant($ligne, $colonnes);
            if ($etudiant === null) {
                $ignorees++;
                continue;
            }
            $resultat[] = $etudiant;
        }

        if (!isset($colonnes['filiere'])) {
            $avertissements[] = 'Pas de colonne « Filière » : les étudiants seront importés sans filière.';
        }
        if (!isset($colonnes['annee'])) {
            $avertissements[] = 'Pas de colonne « Année » : les étudiants seront importés sans année.';
        }
        if ($resultat === []) {
            throw new RuntimeException("L'en-tête a été trouvé, mais aucune ligne d'étudiant n'a pu être lue en dessous.");
        }

        return [
            'etudiants' => $resultat,
            'ignorees' => $ignorees,
            'colonnes' => $colonnes,
            'ligne_entete' => $ligneEntete,
            'avertissements' => $avertissements,
        ];
    }

    /**
     * @param array<int, mixed> $ligne
     * @return array<string, int>|null
     */
    private static function colonnesEntete(array $ligne): ?array
    {
        $colonnes = [];
        foreach ($ligne as $index => $valeur) {
            if (!is_string($valeur)) {
                continue;
            }
            $texte = Texte::pourRecherche($valeur);
            if ($valeur === 'N°' || $valeur === 'N˚' || $valeur === 'Nº') {
                $texte = 'N';
            }
            foreach (self::COLONNES as $role => $motsCles) {
                if (isset($colonnes[$role])) {
                    continue;
                }
                if (in_array($texte, $motsCles, true) || ($role !== 'numero' && self::commencePar($texte, $motsCles))) {
                    $colonnes[$role] = $index;
                    continue 2;
                }
            }
        }

        return isset($colonnes['nom']) && (isset($colonnes['effets']) || isset($colonnes['filiere']))
            ? $colonnes
            : null;
    }

    /** @param list<string> $motsCles */
    private static function commencePar(string $texte, array $motsCles): bool
    {
        foreach ($motsCles as $mot) {
            if (strlen($mot) >= 4 && str_starts_with($texte, $mot . ' ')) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<array<int, mixed>> $lignes
     * @param array<string, int> $colonnes
     */
    private static function colonneNiveauSansTitre(array $lignes, array $colonnes): ?int
    {
        $prises = array_flip($colonnes);
        $comptes = [];
        $remplies = [];
        foreach ($lignes as $ligne) {
            foreach ($ligne as $index => $valeur) {
                if (isset($prises[$index]) || !is_string($valeur)) {
                    continue;
                }
                $remplies[$index] = ($remplies[$index] ?? 0) + 1;
                if (preg_match(self::NIVEAUX, trim($valeur))) {
                    $comptes[$index] = ($comptes[$index] ?? 0) + 1;
                }
            }
        }
        foreach ($comptes as $index => $n) {
            if ($n >= 5 && $n / max(1, $remplies[$index]) >= 0.6) {
                return $index;
            }
        }
        return null;
    }

    /**
     * @param array<int, mixed> $ligne
     * @param array<string, int> $colonnes
     * @return array<string, mixed>|null
     */
    private static function etudiant(array $ligne, array $colonnes): ?array
    {
        $cellule = static fn (string $role): mixed => isset($colonnes[$role]) ? ($ligne[$colonnes[$role]] ?? null) : null;

        $nomBrut = trim((string) $cellule('nom'));
        if ($nomBrut === '') {
            return null;
        }

        // En-tête répété (liste imprimée sur plusieurs pages puis recopiée).
        if (self::colonnesEntete($ligne) !== null) {
            return null;
        }

        $effetsBrut = Texte::espaces((string) $cellule('effets'));
        $numero = $cellule('numero');
        $filiereBrut = $cellule('filiere');
        $anneeBrut = $cellule('annee');

        // Ligne de titre (« FILIÈRE GEII ») : un texte seul, sans aucune autre information.
        if ($effetsBrut === '' && $numero === null && $filiereBrut === null && $anneeBrut === null) {
            return null;
        }

        $nom = NomEtudiant::analyser($nomBrut);
        if ($nom['nom'] === '') {
            return null;
        }

        $effets = Effets::analyser($effetsBrut);
        $filiere = Filiere::analyser($filiereBrut);
        $annee = Annee::analyser($anneeBrut);

        $annotation = $nom['annotation'];
        if ($annee['incertaine']) {
            $annotation = trim(($annotation ?? '') . ($annotation ? ' ; ' : '') . 'année incertaine');
        }

        $matricule = $cellule('matricule');
        $niveau = $cellule('niveau');

        return [
            'numero' => is_numeric($numero) ? (int) $numero : null,
            'nom' => mb_substr($nom['nom'], 0, 190),
            'nom_recherche' => mb_substr(Texte::pourRecherche($nom['nom']), 0, 190),
            'annotation' => $annotation === null ? null : mb_substr($annotation, 0, 190),
            'matricule' => $matricule === null ? null : mb_substr(mb_strtoupper(Texte::espaces((string) $matricule)), 0, 40),
            'effets' => mb_substr($effetsBrut, 0, 255),
            'codes' => mb_substr(Effets::codesStockes($effets), 0, 255),
            'filiere' => $filiere['libelle'] === null ? null : mb_substr($filiere['libelle'], 0, 60),
            'filiere_code' => $filiere['code'],
            'annee' => $annee['libelle'] === null ? null : mb_substr($annee['libelle'], 0, 60),
            'annee_debut' => $annee['debut'],
            'niveau' => $niveau === null ? null : mb_substr(Texte::espaces((string) $niveau), 0, 40),
        ];
    }
}
