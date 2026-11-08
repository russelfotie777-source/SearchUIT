<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimal : chemins avec paramètres ({ref}, {id}) et méthodes HTTP.
 */
final class Router
{
    /** @var list<array{methodes: list<string>, motif: string, cles: list<string>, action: callable|array{class-string, string}}> */
    private array $routes = [];

    public function get(string $chemin, callable|array $action): self
    {
        return $this->ajouter(['GET', 'HEAD'], $chemin, $action);
    }

    public function post(string $chemin, callable|array $action): self
    {
        return $this->ajouter(['POST'], $chemin, $action);
    }

    /** @param list<string> $methodes */
    private function ajouter(array $methodes, string $chemin, callable|array $action): self
    {
        $cles = [];
        $motif = preg_replace_callback('#\{([a-z_]+)\}#', static function (array $m) use (&$cles): string {
            $cles[] = $m[1];
            return $m[1] === 'id' ? '(\d+)' : '([A-Za-z0-9_-]+)';
        }, rtrim($chemin, '/') ?: '/');

        $this->routes[] = [
            'methodes' => $methodes,
            'motif' => '#^' . $motif . '$#',
            'cles' => $cles,
            'action' => $action,
        ];
        return $this;
    }

    public function traiter(Request $requete): Response
    {
        $methodeAutorisee = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['motif'], $requete->chemin, $correspondances)) {
                continue;
            }
            if (!in_array($requete->methode, $route['methodes'], true)) {
                $methodeAutorisee = true;
                continue;
            }

            array_shift($correspondances);
            $parametres = $route['cles'] === [] ? [] : array_combine($route['cles'], $correspondances);
            $requete = $requete->avecParametres($parametres);

            $action = $route['action'];
            if (is_array($action)) {
                [$classe, $methode] = $action;
                $action = [new $classe(), $methode];
            }
            return $action($requete);
        }

        if ($methodeAutorisee) {
            return Response::html(View::page('erreurs/405', ['titre' => 'Action non autorisée']), 405);
        }
        return Response::html(View::page('erreurs/404', ['titre' => 'Page introuvable']), 404);
    }
}
