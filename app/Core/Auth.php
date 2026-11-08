<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Connexion des comptes de la scolarité.
 */
final class Auth
{
    /** Au-delà, la session expire sans activité. */
    private const INACTIVITE_MAX = 8 * 3600;
    private const ESSAIS_MAX = 8;
    private const FENETRE_MINUTES = 15;

    /** @var array<string, mixed>|null|false false = pas encore chargé */
    private static array|null|false $utilisateur = false;

    /**
     * @return array<string, mixed>|null
     */
    public static function utilisateur(): ?array
    {
        if (self::$utilisateur !== false) {
            return self::$utilisateur;
        }

        $id = Session::get('utilisateur_id');
        $activite = (int) Session::get('derniere_activite', 0);
        if (!is_int($id) || ($activite > 0 && time() - $activite > self::INACTIVITE_MAX)) {
            if ($id !== null) {
                Session::oublier('utilisateur_id');
            }
            return self::$utilisateur = null;
        }

        Session::set('derniere_activite', time());
        return self::$utilisateur = Database::un('SELECT id, nom, identifiant, derniere_connexion FROM utilisateurs WHERE id = ?', [$id]);
    }

    public static function id(): ?int
    {
        $utilisateur = self::utilisateur();
        return $utilisateur === null ? null : (int) $utilisateur['id'];
    }

    /**
     * @return string|null message d'erreur, ou null si la connexion a réussi
     */
    public static function connecter(string $identifiant, string $motDePasse, string $ip): ?string
    {
        Database::requete(
            'DELETE FROM tentatives_connexion WHERE cree_le < NOW() - INTERVAL 1 DAY',
        );
        $essais = (int) Database::valeur(
            'SELECT COUNT(*) FROM tentatives_connexion WHERE ip = ? AND cree_le > NOW() - INTERVAL ' . self::FENETRE_MINUTES . ' MINUTE',
            [$ip],
        );
        if ($essais >= self::ESSAIS_MAX) {
            return 'Trop d\'essais à la suite. Réessayez dans ' . self::FENETRE_MINUTES . ' minutes.';
        }

        $compte = Database::un('SELECT * FROM utilisateurs WHERE identifiant = ?', [mb_strtolower(trim($identifiant))]);
        // Même sans compte, on vérifie un mot de passe : le temps de réponse ne trahit rien.
        $empreinte = $compte['mot_de_passe'] ?? '$2y$12$abcdefghijklmnopqrstuuJ0fqCvtJ1o1pUwrTSPUGvYk/kZbG7Ae';
        if (!password_verify($motDePasse, (string) $empreinte) || $compte === null) {
            Database::requete('INSERT INTO tentatives_connexion (ip, identifiant) VALUES (?, ?)', [$ip, mb_substr($identifiant, 0, 120)]);
            return 'Identifiant ou mot de passe incorrect.';
        }

        if (password_needs_rehash((string) $compte['mot_de_passe'], PASSWORD_DEFAULT)) {
            Database::requete('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?', [password_hash($motDePasse, PASSWORD_DEFAULT), $compte['id']]);
        }

        Database::requete('DELETE FROM tentatives_connexion WHERE ip = ?', [$ip]);
        Database::requete('UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?', [$compte['id']]);

        Session::regenerer();
        Session::set('utilisateur_id', (int) $compte['id']);
        Session::set('derniere_activite', time());
        self::$utilisateur = false;
        return null;
    }

    public static function deconnecter(): void
    {
        Session::detruire();
        self::$utilisateur = null;
    }
}
