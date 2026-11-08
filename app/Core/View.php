<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gabarits PHP simples. Les vues vivent dans app/Views ;
 * chaque page s'insère dans un gabarit de mise en page.
 */
final class View
{
    /** @var array<string, mixed> Données partagées par toutes les vues. */
    private static array $partage = [];

    public static function partager(string $cle, mixed $valeur): void
    {
        self::$partage[$cle] = $valeur;
    }

    /**
     * @param array<string, mixed> $donnees
     */
    public static function rendre(string $vue, array $donnees = []): string
    {
        $fichier = RACINE . '/app/Views/' . $vue . '.php';
        if (!is_file($fichier)) {
            throw new \RuntimeException("Vue introuvable : {$vue}");
        }

        $donnees = array_merge(self::$partage, $donnees);
        return (static function (string $__fichier, array $__donnees): string {
            extract($__donnees, EXTR_SKIP);
            ob_start();
            try {
                require $__fichier;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        })($fichier, $donnees);
    }

    /**
     * Page publique : la vue est insérée dans layouts/public.
     *
     * @param array<string, mixed> $donnees
     */
    public static function page(string $vue, array $donnees = [], string $gabarit = 'layouts/public'): string
    {
        $contenu = self::rendre($vue, $donnees);
        return self::rendre($gabarit, array_merge($donnees, ['contenu' => $contenu]));
    }
}
