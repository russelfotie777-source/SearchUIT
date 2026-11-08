<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Storage\Stockage;
use RuntimeException;

/**
 * Opérations de l'espace fichiers : dossiers, fichiers, corbeille.
 */
final class Drive
{
    /** Délai avant suppression définitive des éléments de la corbeille. */
    public const JOURS_CORBEILLE = 30;

    // ----------------------------------------------------------- Lecture

    /**
     * @return array<string, mixed>|null
     */
    public static function dossier(int $id): ?array
    {
        return Database::un('SELECT * FROM dossiers WHERE id = ?', [$id]);
    }

    /**
     * Sous-dossiers actifs d'un dossier (null = racine), avec leur nombre d'éléments.
     *
     * @return list<array<string, mixed>>
     */
    public static function sousDossiers(?int $parent): array
    {
        return Database::tous(
            'SELECT d.*,
                (SELECT COUNT(*) FROM fichiers f WHERE f.dossier_id = d.id AND f.supprime_le IS NULL)
              + (SELECT COUNT(*) FROM dossiers s WHERE s.parent_id = d.id AND s.supprime_le IS NULL) AS elements
             FROM dossiers d
             WHERE d.supprime_le IS NULL AND ' . ($parent === null ? 'd.parent_id IS NULL' : 'd.parent_id = ?') . '
             ORDER BY d.nom',
            $parent === null ? [] : [$parent],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fichiersDe(?int $dossier): array
    {
        return Database::tous(
            'SELECT f.*, u.nom AS auteur FROM fichiers f LEFT JOIN utilisateurs u ON u.id = f.ajoute_par
             WHERE f.supprime_le IS NULL AND ' . ($dossier === null ? 'f.dossier_id IS NULL' : 'f.dossier_id = ?') . '
             ORDER BY f.cree_le DESC, f.id DESC',
            $dossier === null ? [] : [$dossier],
        );
    }

    /**
     * Recherche dans tous les dossiers actifs, par nom de fichier ou de dossier.
     *
     * @return array{dossiers: list<array<string, mixed>>, fichiers: list<array<string, mixed>>}
     */
    public static function rechercher(string $texte): array
    {
        $motif = '%' . addcslashes($texte, '%_\\') . '%';
        $tous = Fichiers::tousLesDossiers(true);
        $fichiers = array_values(array_filter(
            Database::tous(
                'SELECT f.*, u.nom AS auteur FROM fichiers f LEFT JOIN utilisateurs u ON u.id = f.ajoute_par
                 WHERE f.supprime_le IS NULL AND f.nom LIKE ? ORDER BY f.cree_le DESC LIMIT 200',
                [$motif],
            ),
            static fn (array $f): bool => self::dossierActif($f['dossier_id'] === null ? null : (int) $f['dossier_id'], $tous),
        ));
        $dossiers = array_values(array_filter(
            Database::tous(
                "SELECT d.*, 0 AS elements FROM dossiers d WHERE d.supprime_le IS NULL AND d.nom LIKE ? ORDER BY d.nom LIMIT 50",
                [$motif],
            ),
            static fn (array $d): bool => self::dossierActif($d['parent_id'] === null ? null : (int) $d['parent_id'], $tous),
        ));
        return ['dossiers' => $dossiers, 'fichiers' => $fichiers];
    }

    /**
     * Vrai si le dossier et tous ses parents sont hors corbeille.
     *
     * @param array<int, array<string, mixed>>|null $dossiers
     */
    public static function dossierActif(?int $id, ?array $dossiers = null): bool
    {
        if ($id === null) {
            return true;
        }
        $dossiers ??= Fichiers::tousLesDossiers(true);
        if (!isset($dossiers[$id])) {
            return false;
        }
        foreach (Fichiers::cheminDossiers($id, $dossiers) as $dossier) {
            if ($dossier['supprime_le'] !== null) {
                return false;
            }
        }
        return true;
    }

    /**
     * Arborescence des dossiers actifs, pour choisir une destination.
     *
     * @return list<array{id: int, nom: string, niveau: int}>
     */
    public static function arborescence(): array
    {
        $parEnfant = [];
        foreach (Database::tous('SELECT id, parent_id, nom FROM dossiers WHERE supprime_le IS NULL ORDER BY nom') as $d) {
            $parEnfant[$d['parent_id'] === null ? 0 : (int) $d['parent_id']][] = $d;
        }
        $liste = [];
        $parcourir = static function (int $parent, int $niveau) use (&$parcourir, &$liste, $parEnfant): void {
            foreach ($parEnfant[$parent] ?? [] as $d) {
                $liste[] = ['id' => (int) $d['id'], 'nom' => (string) $d['nom'], 'niveau' => $niveau];
                $parcourir((int) $d['id'], $niveau + 1);
            }
        };
        $parcourir(0, 0);
        return $liste;
    }

    // ----------------------------------------------------------- Dossiers

    public static function creerDossier(string $nom, ?int $parent): int
    {
        $nom = self::nomValide($nom);
        if ($parent !== null && self::dossier($parent) === null) {
            throw new RuntimeException("Le dossier parent n'existe plus.");
        }
        Database::requete('INSERT INTO dossiers (parent_id, nom) VALUES (?, ?)', [$parent, $nom]);
        return Database::dernierId();
    }

    public static function renommerDossier(int $id, string $nom): void
    {
        Database::requete('UPDATE dossiers SET nom = ? WHERE id = ?', [self::nomValide($nom), $id]);
    }

    public static function deplacerDossier(int $id, ?int $parent): void
    {
        // Un dossier ne peut pas entrer dans lui-même ni dans un de ses sous-dossiers.
        foreach (Fichiers::cheminDossiers($parent, Fichiers::tousLesDossiers(true)) as $ancetre) {
            if ((int) $ancetre['id'] === $id) {
                throw new RuntimeException('Un dossier ne peut pas être déplacé dans lui-même.');
            }
        }
        Database::requete('UPDATE dossiers SET parent_id = ? WHERE id = ?', [$parent, $id]);
    }

    public static function mettreDossierCorbeille(int $id): void
    {
        Database::requete('UPDATE dossiers SET supprime_le = NOW() WHERE id = ?', [$id]);
    }

    public static function restaurerDossier(int $id): void
    {
        Database::requete('UPDATE dossiers SET supprime_le = NULL WHERE id = ?', [$id]);
    }

    /**
     * Supprime un dossier, ses sous-dossiers et leurs fichiers, disque compris.
     */
    public static function detruireDossier(int $id): void
    {
        $ids = self::descendants($id);
        $marqueurs = implode(', ', array_fill(0, count($ids), '?'));
        foreach (Database::tous("SELECT id FROM fichiers WHERE dossier_id IN ({$marqueurs})", $ids) as $fichier) {
            self::detruireFichier((int) $fichier['id']);
        }
        Database::requete('DELETE FROM dossiers WHERE id = ?', [$id]);
    }

    /**
     * @return list<int> le dossier et tous ses sous-dossiers
     */
    public static function descendants(int $id): array
    {
        $tous = [$id];
        $aParcourir = [$id];
        while ($aParcourir !== []) {
            $marqueurs = implode(', ', array_fill(0, count($aParcourir), '?'));
            $enfants = array_map('intval', array_column(Database::tous("SELECT id FROM dossiers WHERE parent_id IN ({$marqueurs})", $aParcourir), 'id'));
            $aParcourir = array_values(array_diff($enfants, $tous));
            $tous = array_merge($tous, $aParcourir);
        }
        return $tous;
    }

    // ----------------------------------------------------------- Fichiers

    /**
     * Enregistre un fichier téléversé. Renvoie [id, doublon] : si le même contenu
     * est déjà dans ce dossier, il n'est pas ajouté une seconde fois.
     *
     * @return array{id: int, doublon: bool}
     */
    public static function ajouterFichier(string $cheminTemporaire, string $nomOriginal, ?int $dossier, ?int $utilisateur, bool $televerse = true): array
    {
        $extension = Stockage::extension($nomOriginal);
        $empreinte = (string) hash_file('sha256', $cheminTemporaire);

        $existant = Database::un(
            'SELECT id FROM fichiers WHERE empreinte = ? AND supprime_le IS NULL AND ' . ($dossier === null ? 'dossier_id IS NULL' : 'dossier_id = ?'),
            $dossier === null ? [$empreinte] : [$empreinte, $dossier],
        );
        if ($existant !== null) {
            return ['id' => (int) $existant['id'], 'doublon' => true];
        }

        $taille = (int) filesize($cheminTemporaire);
        $nomStockage = Stockage::ranger($cheminTemporaire, $extension, $televerse);
        Database::requete(
            'INSERT INTO fichiers (dossier_id, nom, nom_stockage, extension, type_mime, taille, empreinte, public, ajoute_par)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
            [$dossier, self::nomValide($nomOriginal), $nomStockage, $extension, Stockage::TYPES_SERVIS[$extension] ?? 'application/octet-stream', $taille, $empreinte, $utilisateur],
        );
        return ['id' => Database::dernierId(), 'doublon' => false];
    }

    public static function renommerFichier(int $id, string $nom): void
    {
        $fichier = Fichiers::parId($id) ?? throw new RuntimeException("Ce fichier n'existe plus.");
        $nom = self::nomValide($nom);
        // L'extension est conservée : elle détermine comment le fichier s'ouvre.
        $extension = '.' . $fichier['extension'];
        if (!str_ends_with(mb_strtolower($nom), $extension)) {
            $nom .= $extension;
        }
        Database::requete('UPDATE fichiers SET nom = ?, modifie_le = NOW() WHERE id = ?', [$nom, $id]);
    }

    public static function deplacerFichier(int $id, ?int $dossier): void
    {
        if ($dossier !== null && self::dossier($dossier) === null) {
            throw new RuntimeException("Le dossier de destination n'existe plus.");
        }
        Database::requete('UPDATE fichiers SET dossier_id = ?, modifie_le = NOW() WHERE id = ?', [$dossier, $id]);
    }

    public static function changerVisibilite(int $id, bool $public): void
    {
        Database::requete('UPDATE fichiers SET public = ?, modifie_le = NOW() WHERE id = ?', [$public ? 1 : 0, $id]);
    }

    public static function mettreFichierCorbeille(int $id): void
    {
        Database::requete('UPDATE fichiers SET supprime_le = NOW() WHERE id = ?', [$id]);
    }

    public static function restaurerFichier(int $id): void
    {
        Database::requete('UPDATE fichiers SET supprime_le = NULL WHERE id = ?', [$id]);
    }

    public static function detruireFichier(int $id): void
    {
        $fichier = Fichiers::parId($id);
        if ($fichier === null) {
            return;
        }
        Database::requete('DELETE FROM fichiers WHERE id = ?', [$id]);
        Stockage::supprimer((string) $fichier['nom_stockage']);
    }

    // ----------------------------------------------------------- Corbeille

    /**
     * @return array{dossiers: list<array<string, mixed>>, fichiers: list<array<string, mixed>>}
     */
    public static function corbeille(): array
    {
        return [
            'dossiers' => Database::tous('SELECT * FROM dossiers WHERE supprime_le IS NOT NULL ORDER BY supprime_le DESC'),
            'fichiers' => Database::tous('SELECT * FROM fichiers WHERE supprime_le IS NOT NULL ORDER BY supprime_le DESC'),
        ];
    }

    /**
     * Supprime définitivement ce qui est dans la corbeille depuis trop longtemps
     * (ou tout, si $tout est vrai). Renvoie le nombre d'éléments supprimés.
     */
    public static function viderCorbeille(bool $tout = false): int
    {
        $condition = $tout ? 'supprime_le IS NOT NULL' : 'supprime_le < NOW() - INTERVAL ' . self::JOURS_CORBEILLE . ' DAY';
        $nombre = 0;
        foreach (Database::tous("SELECT id FROM dossiers WHERE {$condition}") as $dossier) {
            if (self::dossier((int) $dossier['id']) !== null) {
                self::detruireDossier((int) $dossier['id']);
                $nombre++;
            }
        }
        foreach (Database::tous("SELECT id FROM fichiers WHERE {$condition}") as $fichier) {
            self::detruireFichier((int) $fichier['id']);
            $nombre++;
        }
        return $nombre;
    }

    // ----------------------------------------------------------- Outils

    private static function nomValide(string $nom): string
    {
        // Pas de caractères de contrôle ni de séparateurs de chemin dans un nom.
        $nom = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $nom));
        $nom = (string) preg_replace('/\s+/u', ' ', $nom);
        if ($nom === '' || $nom === '.' || $nom === '..') {
            throw new RuntimeException('Donnez un nom.');
        }
        return mb_substr($nom, 0, 200);
    }
}
