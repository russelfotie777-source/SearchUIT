<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Cache;
use App\Core\Database;
use App\Domain\Effets;
use App\Domain\Recherche;
use App\Domain\Texte;

/**
 * Accès aux étudiants inscrits sur les listes d'effets.
 */
final class Etudiants
{
    public const LIMITE_RESULTATS = 50;

    /**
     * Recherche par nom (mots dans n'importe quel ordre, début de mot)
     * ou par matricule.
     *
     * @return array{total: int, resultats: list<array<string, mixed>>, mots: list<string>, proches: bool}
     */
    public static function rechercher(string $saisie, int $limite = self::LIMITE_RESULTATS): array
    {
        $mots = Recherche::mots($saisie);
        $vide = ['total' => 0, 'resultats' => [], 'mots' => $mots, 'proches' => false];
        if ($mots === [] || mb_strlen(implode('', $mots)) < Recherche::LONGUEUR_MIN) {
            return $vide;
        }

        $conditions = [];
        $parametres = [];
        foreach ($mots as $mot) {
            $conditions[] = "CONCAT(' ', nom_recherche) LIKE ?";
            $parametres[] = '% ' . self::echapperLike($mot) . '%';
        }
        $where = '(' . implode(' AND ', $conditions) . ')';

        if (Recherche::estMatricule($saisie)) {
            $where = "({$where} OR matricule = ? OR matricule LIKE ?)";
            $matricule = mb_strtoupper(trim($saisie));
            $parametres[] = $matricule;
            $parametres[] = self::echapperLike($matricule) . '%';
        }

        $total = (int) Database::valeur("SELECT COUNT(*) FROM etudiants WHERE {$where}", $parametres);
        if ($total === 0) {
            $proches = self::nomsProches($mots);
            return ['total' => 0, 'resultats' => $proches, 'mots' => $mots, 'proches' => $proches !== []];
        }

        // Les noms qui commencent par le premier mot saisi passent devant.
        $ordre = 'CASE WHEN nom_recherche = ? THEN 0 WHEN nom_recherche LIKE ? THEN 1 ELSE 2 END, nom_recherche, annee_debut DESC';
        $parametresOrdre = [implode(' ', $mots), self::echapperLike($mots[0]) . '%'];

        $resultats = Database::tous(
            "SELECT * FROM etudiants WHERE {$where} ORDER BY {$ordre} LIMIT " . max(1, min(200, $limite)),
            array_merge($parametres, $parametresOrdre),
        );

        return ['total' => $total, 'resultats' => $resultats, 'mots' => $mots, 'proches' => false];
    }

    /**
     * Noms ressemblants quand la recherche exacte ne donne rien
     * (faute de frappe, lettre oubliée, nom mal orthographié sur la liste).
     *
     * @param list<string> $mots
     * @return list<array<string, mixed>>
     */
    public static function nomsProches(array $mots, int $limite = 8): array
    {
        $noms = Cache::souvenir('noms-distincts', self::version(), static fn (): array => array_map(
            'strval',
            Database::requete('SELECT DISTINCT nom_recherche FROM etudiants')->fetchAll(\PDO::FETCH_COLUMN),
        ));

        $scores = [];
        foreach ($noms as $nom) {
            $score = Recherche::ressemblance($mots, $nom);
            if ($score >= 0.7) {
                $scores[$nom] = $score;
            }
        }
        if ($scores === []) {
            return [];
        }
        arsort($scores);
        $retenus = array_slice(array_keys($scores), 0, $limite);

        $marqueurs = implode(', ', array_fill(0, count($retenus), '?'));
        $lignes = Database::tous("SELECT * FROM etudiants WHERE nom_recherche IN ({$marqueurs}) LIMIT 30", $retenus);
        usort($lignes, static fn (array $a, array $b): int => ($scores[$b['nom_recherche']] ?? 0) <=> ($scores[$a['nom_recherche']] ?? 0));
        return $lignes;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function parReference(string $reference): ?array
    {
        return Database::un('SELECT * FROM etudiants WHERE reference = ?', [$reference]);
    }

    /** Tris proposés sur la liste complète. */
    public const TRIS = [
        'nom' => ['Nom (A à Z)', 'nom_recherche ASC, annee_debut DESC'],
        'recent' => ['Année la plus récente', 'annee_debut IS NULL, annee_debut DESC, nom_recherche ASC'],
        'ancien' => ['Année la plus ancienne', 'annee_debut IS NULL, annee_debut ASC, nom_recherche ASC'],
        'filiere' => ['Filière', 'filiere_code IS NULL, filiere_code ASC, filiere ASC, nom_recherche ASC'],
    ];

    /**
     * Liste filtrée et paginée.
     *
     * @param array{nom?: string, filiere?: string, annee?: int|string, document?: string, statut?: string, tri?: string} $filtres
     * @return array{total: int, lignes: list<array<string, mixed>>, page: int, pages: int}
     */
    public static function lister(array $filtres, int $page = 1, int $parPage = 50): array
    {
        $conditions = [];
        $parametres = [];

        foreach (Recherche::mots((string) ($filtres['nom'] ?? '')) as $mot) {
            $conditions[] = "CONCAT(' ', nom_recherche) LIKE ?";
            $parametres[] = '% ' . self::echapperLike($mot) . '%';
        }
        if (($filtres['filiere'] ?? '') !== '') {
            $conditions[] = 'filiere_code = ?';
            $parametres[] = $filtres['filiere'];
        }
        if (($filtres['annee'] ?? '') !== '' && is_numeric($filtres['annee'])) {
            $conditions[] = 'annee_debut = ?';
            $parametres[] = (int) $filtres['annee'];
        }
        if (($filtres['document'] ?? '') !== '') {
            $conditions[] = 'codes LIKE ?';
            $parametres[] = '%,' . self::echapperLike((string) $filtres['document']) . ',%';
        }
        $statut = $filtres['statut'] ?? 'disponible';
        if ($statut === 'disponible' || $statut === 'retire') {
            $conditions[] = 'statut = ?';
            $parametres[] = $statut;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $total = (int) Database::valeur("SELECT COUNT(*) FROM etudiants {$where}", $parametres);

        $pages = max(1, (int) ceil($total / $parPage));
        $page = max(1, min($page, $pages));
        $ordre = self::TRIS[$filtres['tri'] ?? 'nom'][1] ?? self::TRIS['nom'][1];

        $lignes = Database::tous(
            "SELECT * FROM etudiants {$where} ORDER BY {$ordre} LIMIT " . (int) $parPage . ' OFFSET ' . (int) (($page - 1) * $parPage),
            $parametres,
        );

        return ['total' => $total, 'lignes' => $lignes, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Filières présentes sur la liste, avec leur nombre d'étudiants.
     *
     * @return list<array{code: string, nombre: int}>
     */
    public static function filieres(): array
    {
        return Cache::souvenir('filieres', self::version(), static fn (): array => array_map(
            static fn (array $l): array => ['code' => (string) $l['code'], 'nombre' => (int) $l['nombre']],
            Database::tous(
                "SELECT filiere_code AS code, COUNT(*) AS nombre FROM etudiants
                 WHERE filiere_code IS NOT NULL AND filiere_code <> '' GROUP BY filiere_code ORDER BY filiere_code",
            ),
        ));
    }

    /**
     * Années de début présentes sur la liste, de la plus récente à la plus ancienne.
     *
     * @return list<array{annee: int, nombre: int}>
     */
    public static function annees(): array
    {
        return Cache::souvenir('annees', self::version(), static fn (): array => array_map(
            static fn (array $l): array => ['annee' => (int) $l['annee'], 'nombre' => (int) $l['nombre']],
            Database::tous(
                'SELECT annee_debut AS annee, COUNT(*) AS nombre FROM etudiants
                 WHERE annee_debut IS NOT NULL GROUP BY annee_debut ORDER BY annee_debut DESC',
            ),
        ));
    }

    /**
     * Chiffres affichés sur l'accueil.
     *
     * @return array{total: int, disponibles: int, filieres: int, codes: array<string, int>}
     */
    public static function statistiques(): array
    {
        return Cache::souvenir('statistiques', self::version(), static function (): array {
            $ligne = Database::un(
                "SELECT COUNT(*) AS total, SUM(statut = 'disponible') AS disponibles,
                        COUNT(DISTINCT filiere_code) AS filieres FROM etudiants",
            ) ?? [];
            $codes = [];
            foreach (Database::requete('SELECT codes FROM etudiants')->fetchAll(\PDO::FETCH_COLUMN) as $liste) {
                foreach (explode(',', trim((string) $liste, ',')) as $code) {
                    if ($code !== '') {
                        $codes[$code] = ($codes[$code] ?? 0) + 1;
                    }
                }
            }
            return [
                'total' => (int) ($ligne['total'] ?? 0),
                'disponibles' => (int) ($ligne['disponibles'] ?? 0),
                'filieres' => (int) ($ligne['filieres'] ?? 0),
                'codes' => $codes,
            ];
        });
    }

    /**
     * Effets d'une ligne, avec libellés courts et complets.
     *
     * @param array<string, mixed> $etudiant
     * @return list<array{code: string, court: string, complet: string, note: ?string}>
     */
    public static function effets(array $etudiant): array
    {
        $legende = Legende::complete();
        $effets = [];
        foreach (Effets::analyser((string) $etudiant['effets']) as $effet) {
            [$court, $complet] = Effets::libelles($effet, $legende);
            $effets[] = ['code' => $effet['code'], 'court' => $court, 'complet' => $complet, 'note' => $effet['note']];
        }
        return $effets;
    }

    /**
     * Change à chaque import ou modification : sert à invalider les caches.
     */
    public static function version(): string
    {
        return Parametres::get('liste_mise_a_jour', '0') . '|' . Parametres::get('version_donnees', '0');
    }

    /**
     * À appeler après toute modification d'un étudiant (retrait, correction…).
     */
    public static function invalider(): void
    {
        Parametres::set('version_donnees', bin2hex(random_bytes(4)));
    }

    public static function echapperLike(string $texte): string
    {
        return addcslashes(Texte::espaces($texte), '%_\\');
    }
}
