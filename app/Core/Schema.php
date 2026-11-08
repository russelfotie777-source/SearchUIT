<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Crée les tables et les dossiers de départ.
 */
final class Schema
{
    /** Dossiers proposés à la première installation. */
    public const DOSSIERS_DE_DEPART = [
        'Listes des effets disponibles',
        'Procès-verbaux',
        'Communiqués',
    ];

    public static function installer(PDO $pdo): void
    {
        $sql = (string) file_get_contents(RACINE . '/database/schema.sql');
        foreach (self::instructions($sql) as $instruction) {
            $pdo->exec($instruction);
        }

        $existe = (int) $pdo->query('SELECT COUNT(*) FROM dossiers')->fetchColumn();
        if ($existe === 0) {
            $ajout = $pdo->prepare('INSERT INTO dossiers (nom) VALUES (?)');
            foreach (self::DOSSIERS_DE_DEPART as $nom) {
                $ajout->execute([$nom]);
            }
        }
    }

    /**
     * Découpe le fichier SQL en instructions, en ignorant les commentaires.
     *
     * @return list<string>
     */
    public static function instructions(string $sql): array
    {
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        $morceaux = array_map('trim', explode(';', $sql));
        return array_values(array_filter($morceaux, static fn (string $m): bool => $m !== ''));
    }

    public static function estInstallee(): bool
    {
        if (!Config::presente()) {
            return false;
        }
        try {
            return (int) Database::valeur('SELECT COUNT(*) FROM utilisateurs') > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
