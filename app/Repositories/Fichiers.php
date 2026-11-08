<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Fichiers et dossiers de l'espace de dépôt.
 */
final class Fichiers
{
    /**
     * @return array<string, mixed>|null
     */
    public static function parId(int $id): ?array
    {
        return Database::un('SELECT * FROM fichiers WHERE id = ?', [$id]);
    }

    /**
     * Un fichier est visible du public s'il est publié, hors corbeille,
     * et qu'aucun de ses dossiers n'est dans la corbeille.
     *
     * @param array<string, mixed> $fichier
     */
    public static function estPublic(array $fichier): bool
    {
        if ((int) $fichier['public'] !== 1 || $fichier['supprime_le'] !== null) {
            return false;
        }
        $dossiers = self::tousLesDossiers();
        $id = $fichier['dossier_id'] === null ? null : (int) $fichier['dossier_id'];
        $garde = 0;
        while ($id !== null && $garde++ < 50) {
            $dossier = $dossiers[$id] ?? null;
            if ($dossier === null || $dossier['supprime_le'] !== null) {
                return false;
            }
            $id = $dossier['parent_id'] === null ? null : (int) $dossier['parent_id'];
        }
        return true;
    }

    /**
     * Documents publiés, regroupés par dossier de premier niveau.
     *
     * @return list<array{dossier: ?array<string, mixed>, fichiers: list<array<string, mixed>>}>
     */
    public static function publicsParDossier(): array
    {
        $dossiers = self::tousLesDossiers();
        $fichiers = Database::tous(
            'SELECT * FROM fichiers WHERE public = 1 AND supprime_le IS NULL ORDER BY cree_le DESC, id DESC',
        );

        $groupes = [];
        foreach ($fichiers as $fichier) {
            if (!self::estPublic($fichier)) {
                continue;
            }
            $chemin = self::cheminDossiers($fichier['dossier_id'] === null ? null : (int) $fichier['dossier_id'], $dossiers);
            $racine = $chemin[0] ?? null;
            $cle = $racine === null ? 0 : (int) $racine['id'];
            $groupes[$cle] ??= ['dossier' => $racine, 'fichiers' => []];
            $fichier['sous_dossier'] = implode(' / ', array_map(static fn (array $d): string => (string) $d['nom'], array_slice($chemin, 1)));
            $groupes[$cle]['fichiers'][] = $fichier;
        }

        // Dossiers dans l'ordre alphabétique, fichiers sans dossier à la fin.
        uasort($groupes, static function (array $a, array $b): int {
            if ($a['dossier'] === null || $b['dossier'] === null) {
                return $a['dossier'] === null ? 1 : -1;
            }
            return strcoll((string) $a['dossier']['nom'], (string) $b['dossier']['nom']);
        });
        return array_values($groupes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function derniersPublics(int $nombre = 3): array
    {
        $resultat = [];
        foreach (Database::tous('SELECT * FROM fichiers WHERE public = 1 AND supprime_le IS NULL ORDER BY cree_le DESC, id DESC LIMIT 30') as $fichier) {
            if (self::estPublic($fichier)) {
                $resultat[] = $fichier;
                if (count($resultat) >= $nombre) {
                    break;
                }
            }
        }
        return $resultat;
    }

    /**
     * Du dossier racine jusqu'au dossier donné.
     *
     * @param array<int, array<string, mixed>>|null $dossiers
     * @return list<array<string, mixed>>
     */
    public static function cheminDossiers(?int $id, ?array $dossiers = null): array
    {
        $dossiers ??= self::tousLesDossiers();
        $chemin = [];
        $garde = 0;
        while ($id !== null && isset($dossiers[$id]) && $garde++ < 50) {
            array_unshift($chemin, $dossiers[$id]);
            $id = $dossiers[$id]['parent_id'] === null ? null : (int) $dossiers[$id]['parent_id'];
        }
        return $chemin;
    }

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $dossiers = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function tousLesDossiers(bool $recharger = false): array
    {
        if (self::$dossiers === null || $recharger) {
            self::$dossiers = [];
            foreach (Database::tous('SELECT * FROM dossiers') as $dossier) {
                self::$dossiers[(int) $dossier['id']] = $dossier;
            }
        }
        return self::$dossiers;
    }

    /**
     * Nom de fichier lisible dans une adresse : « PV délibération S1.pdf » → « pv-deliberation-s1 ».
     */
    public static function slug(string $nom): string
    {
        $base = pathinfo($nom, PATHINFO_FILENAME);
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', \App\Domain\Texte::sansAccents($base)), '-'));
        return $slug === '' ? 'document' : mb_substr($slug, 0, 80);
    }
}
