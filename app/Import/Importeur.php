<?php

declare(strict_types=1);

namespace App\Import;

use App\Core\Database;
use App\Domain\Reference;
use App\Repositories\Journal;
use App\Repositories\Parametres;

/**
 * Enregistre une liste analysée dans la base.
 *
 * Deux modes :
 *  - « ajout » : les étudiants s'ajoutent à ceux déjà en ligne ; une ligne
 *    strictement identique déjà présente n'est pas dupliquée.
 *  - « remplacement » : la nouvelle liste remplace toute la liste en ligne.
 *    Un étudiant déjà présent garde sa référence de fiche et, s'il a été
 *    marqué comme retiré, reste retiré.
 */
final class Importeur
{
    public const AJOUT = 'ajout';
    public const REMPLACEMENT = 'remplacement';

    private const COLONNES = [
        'reference', 'import_id', 'numero', 'nom', 'nom_recherche', 'annotation', 'matricule',
        'effets', 'codes', 'filiere', 'filiere_code', 'annee', 'annee_debut', 'niveau',
        'statut', 'retire_le', 'retire_par',
    ];

    /**
     * @param list<array<string, mixed>> $etudiants lignes produites par AnalyseurListe
     * @return array{import_id: int, ajoutes: int, doublons: int, conserves: int, retires_conserves: int, retires_avant: int}
     */
    public function importer(array $etudiants, string $mode, string $nomSource, ?int $fichierId = null, ?int $utilisateurId = null, int $ignorees = 0): array
    {
        if (!in_array($mode, [self::AJOUT, self::REMPLACEMENT], true)) {
            throw new \InvalidArgumentException("Mode d'import inconnu : {$mode}");
        }

        return Database::transaction(function () use ($etudiants, $mode, $nomSource, $fichierId, $utilisateurId, $ignorees): array {
            // Lignes déjà en ligne, regroupées par clé (deux homonymes parfaits restent deux lignes).
            $existants = [];
            $references = [];
            $retiresAvant = 0;
            foreach (Database::tous('SELECT reference, nom_recherche, filiere, annee, codes, statut, retire_le, retire_par FROM etudiants') as $ligne) {
                $references[(string) $ligne['reference']] = true;
                $existants[self::cle($ligne)][] = $ligne;
                if ($ligne['statut'] === 'retire') {
                    $retiresAvant++;
                }
            }

            if ($mode === self::REMPLACEMENT) {
                Database::requete('DELETE FROM etudiants');
            }

            Database::requete(
                'INSERT INTO imports (fichier_id, nom_source, mode, lignes, ignorees, fait_par) VALUES (?, ?, ?, 0, ?, ?)',
                [$fichierId, mb_substr($nomSource, 0, 255), $mode, $ignorees, $utilisateurId],
            );
            $importId = Database::dernierId();

            $aInserer = [];
            $doublons = 0;
            $conserves = 0;
            $retiresConserves = 0;

            foreach ($etudiants as $etudiant) {
                $cle = self::cle($etudiant);

                // En mode ajout, une ligne déjà en ligne n'est pas recopiée.
                if ($mode === self::AJOUT && !empty($existants[$cle])) {
                    array_shift($existants[$cle]);
                    $doublons++;
                    continue;
                }

                $ancien = $mode === self::REMPLACEMENT && !empty($existants[$cle]) ? array_shift($existants[$cle]) : null;
                if ($ancien !== null) {
                    $conserves++;
                    if ($ancien['statut'] === 'retire') {
                        $retiresConserves++;
                    }
                    $reference = (string) $ancien['reference'];
                } else {
                    $reference = Reference::nouvelle($references);
                }

                $aInserer[] = [
                    'reference' => $reference,
                    'import_id' => $importId,
                    'numero' => $etudiant['numero'],
                    'nom' => $etudiant['nom'],
                    'nom_recherche' => $etudiant['nom_recherche'],
                    'annotation' => $etudiant['annotation'],
                    'matricule' => $etudiant['matricule'],
                    'effets' => $etudiant['effets'],
                    'codes' => $etudiant['codes'],
                    'filiere' => $etudiant['filiere'],
                    'filiere_code' => $etudiant['filiere_code'],
                    'annee' => $etudiant['annee'],
                    'annee_debut' => $etudiant['annee_debut'],
                    'niveau' => $etudiant['niveau'],
                    'statut' => $ancien['statut'] ?? 'disponible',
                    'retire_le' => $ancien['retire_le'] ?? null,
                    'retire_par' => $ancien['retire_par'] ?? null,
                ];

                if (count($aInserer) >= 400) {
                    self::insererLot($aInserer);
                    $aInserer = [];
                }
            }
            if ($aInserer !== []) {
                self::insererLot($aInserer);
            }

            $ajoutes = (int) Database::valeur('SELECT COUNT(*) FROM etudiants WHERE import_id = ?', [$importId]);
            Database::requete('UPDATE imports SET lignes = ? WHERE id = ?', [$ajoutes, $importId]);

            Parametres::set('liste_mise_a_jour', date('Y-m-d H:i:s'));
            Journal::ajouter(
                $utilisateurId,
                $mode === self::REMPLACEMENT ? 'Liste remplacée' : 'Liste ajoutée',
                sprintf('%s : %d étudiants', $nomSource, $ajoutes),
            );

            return [
                'import_id' => $importId,
                'ajoutes' => $ajoutes,
                'doublons' => $doublons,
                'conserves' => $conserves,
                'retires_conserves' => $retiresConserves,
                'retires_avant' => $retiresAvant,
            ];
        });
    }

    /**
     * Retire de la recherche tous les étudiants apportés par un import.
     */
    public function annuler(int $importId, ?int $utilisateurId = null): int
    {
        return Database::transaction(function () use ($importId, $utilisateurId): int {
            $import = Database::un('SELECT * FROM imports WHERE id = ? AND annule_le IS NULL', [$importId]);
            if ($import === null) {
                return 0;
            }
            $supprimes = Database::requete('DELETE FROM etudiants WHERE import_id = ?', [$importId])->rowCount();
            Database::requete('UPDATE imports SET annule_le = NOW() WHERE id = ?', [$importId]);
            Parametres::set('liste_mise_a_jour', date('Y-m-d H:i:s'));
            Journal::ajouter($utilisateurId, 'Import annulé', sprintf('%s : %d étudiants retirés de la recherche', $import['nom_source'], $supprimes));
            return $supprimes;
        });
    }

    /**
     * Identifie une même ligne d'une liste à l'autre.
     *
     * @param array<string, mixed> $ligne
     */
    public static function cle(array $ligne): string
    {
        return implode('|', [
            $ligne['nom_recherche'] ?? '',
            mb_strtoupper((string) ($ligne['filiere'] ?? '')),
            (string) ($ligne['annee'] ?? ''),
            (string) ($ligne['codes'] ?? ''),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $lignes
     */
    private static function insererLot(array $lignes): void
    {
        $marqueurs = '(' . implode(', ', array_fill(0, count(self::COLONNES), '?')) . ')';
        $sql = 'INSERT INTO etudiants (' . implode(', ', self::COLONNES) . ') VALUES '
            . implode(', ', array_fill(0, count($lignes), $marqueurs));

        $valeurs = [];
        foreach ($lignes as $ligne) {
            foreach (self::COLONNES as $colonne) {
                $valeurs[] = $ligne[$colonne];
            }
        }
        Database::requete($sql, $valeurs);
    }
}
