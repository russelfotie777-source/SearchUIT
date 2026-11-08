<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, string> */
    private array $parametres = [];

    public function __construct(
        public readonly string $methode,
        public readonly string $chemin,
        /** @var array<string, mixed> */
        public readonly array $query,
        /** @var array<string, mixed> */
        public readonly array $post,
        /** @var array<string, mixed> */
        public readonly array $fichiers,
        /** @var array<string, mixed> */
        public readonly array $serveur,
    ) {
    }

    public static function depuisGlobales(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $chemin = rawurldecode((string) parse_url($uri, PHP_URL_PATH));

        $base = Url::base();
        if ($base !== '' && str_starts_with($chemin, $base)) {
            $chemin = substr($chemin, strlen($base));
        }
        $chemin = '/' . trim($chemin, '/');

        $post = $_POST;
        $type = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if ($post === [] && str_contains($type, 'application/json')) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            $post = is_array($json) ? $json : [];
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $chemin,
            $_GET,
            $post,
            $_FILES,
            $_SERVER,
        );
    }

    public function input(string $cle, mixed $defaut = null): mixed
    {
        return $this->post[$cle] ?? $this->query[$cle] ?? $defaut;
    }

    public function texte(string $cle, string $defaut = ''): string
    {
        $valeur = $this->input($cle, $defaut);
        return is_scalar($valeur) ? trim((string) $valeur) : $defaut;
    }

    public function entier(string $cle, int $defaut = 0): int
    {
        $valeur = $this->input($cle);
        return is_numeric($valeur) ? (int) $valeur : $defaut;
    }

    /** @param array<string, string> $parametres */
    public function avecParametres(array $parametres): self
    {
        $copie = clone $this;
        $copie->parametres = $parametres;
        return $copie;
    }

    public function parametre(string $cle): string
    {
        return $this->parametres[$cle] ?? '';
    }

    public function attendJson(): bool
    {
        $accept = (string) ($this->serveur['HTTP_ACCEPT'] ?? '');
        return str_contains($accept, 'application/json')
            || ($this->serveur['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }

    public function entete(string $nom): string
    {
        $cle = 'HTTP_' . strtoupper(str_replace('-', '_', $nom));
        return (string) ($this->serveur[$cle] ?? '');
    }

    public function ip(): string
    {
        return (string) ($this->serveur['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
