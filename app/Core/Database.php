<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Connexion MySQL / MariaDB unique, requêtes préparées partout.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connecter(
                (string) Config::get('db.host', 'localhost'),
                (int) Config::get('db.port', 3306),
                (string) Config::get('db.name', ''),
                (string) Config::get('db.user', ''),
                (string) Config::get('db.pass', ''),
            );
        }
        return self::$pdo;
    }

    public static function connecter(string $hote, int $port, string $base, string $utilisateur, string $motDePasse): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $hote, $port, $base);
        $pdo = new PDO($dsn, $utilisateur, $motDePasse, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET time_zone = '+01:00'");
        return $pdo;
    }

    /** @param array<int|string, mixed> $parametres */
    public static function requete(string $sql, array $parametres = []): PDOStatement
    {
        $instruction = self::pdo()->prepare($sql);
        $instruction->execute($parametres);
        return $instruction;
    }

    /**
     * @param array<int|string, mixed> $parametres
     * @return list<array<string, mixed>>
     */
    public static function tous(string $sql, array $parametres = []): array
    {
        return self::requete($sql, $parametres)->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $parametres
     * @return array<string, mixed>|null
     */
    public static function un(string $sql, array $parametres = []): ?array
    {
        $ligne = self::requete($sql, $parametres)->fetch();
        return $ligne === false ? null : $ligne;
    }

    /** @param array<int|string, mixed> $parametres */
    public static function valeur(string $sql, array $parametres = []): mixed
    {
        $valeur = self::requete($sql, $parametres)->fetchColumn();
        return $valeur === false ? null : $valeur;
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public static function transaction(callable $operation): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $resultat = $operation();
            $pdo->commit();
            return $resultat;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function dernierId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }
}
