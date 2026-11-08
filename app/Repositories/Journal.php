<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Historique des actions de la scolarité (imports, retraits, suppressions…).
 */
final class Journal
{
    public static function ajouter(?int $utilisateurId, string $action, ?string $details = null): void
    {
        Database::requete(
            'INSERT INTO journal (utilisateur_id, action, details) VALUES (?, ?, ?)',
            [$utilisateurId, mb_substr($action, 0, 60), $details === null ? null : mb_substr($details, 0, 255)],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recents(int $limite = 50): array
    {
        return Database::tous(
            'SELECT j.*, u.nom AS auteur FROM journal j
             LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id
             ORDER BY j.cree_le DESC, j.id DESC LIMIT ' . max(1, min(500, $limite)),
        );
    }
}
