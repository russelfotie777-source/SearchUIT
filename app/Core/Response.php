<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @var array<string, string> */
    private array $entetes = [];
    /** @var (callable(): void)|null */
    private $flux = null;

    public function __construct(
        private string $corps = '',
        private int $statut = 200,
    ) {
    }

    public static function html(string $corps, int $statut = 200): self
    {
        return (new self($corps, $statut))->entete('Content-Type', 'text/html; charset=utf-8');
    }

    public static function json(mixed $donnees, int $statut = 200): self
    {
        $corps = json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return (new self($corps, $statut))
            ->entete('Content-Type', 'application/json; charset=utf-8')
            ->entete('Cache-Control', 'no-store');
    }

    public static function redirection(string $adresse, int $statut = 303): self
    {
        return (new self('', $statut))->entete('Location', $adresse);
    }

    /**
     * Envoie un fichier du disque, en ligne (aperçu) ou en téléchargement.
     */
    public static function fichier(string $chemin, string $nom, string $type, bool $enLigne = false): self
    {
        $reponse = new self('', 200);
        $reponse->entete('Content-Type', $type)
            ->entete('Content-Length', (string) filesize($chemin))
            ->entete('Content-Disposition', self::disposition($nom, $enLigne))
            ->entete('X-Content-Type-Options', 'nosniff')
            ->entete('Cache-Control', 'private, max-age=0, must-revalidate');
        $reponse->flux = static function () use ($chemin): void {
            $poignee = fopen($chemin, 'rb');
            if ($poignee === false) {
                return;
            }
            while (!feof($poignee)) {
                echo fread($poignee, 65536);
                flush();
            }
            fclose($poignee);
        };
        return $reponse;
    }

    /**
     * Envoie un contenu généré (un PDF par exemple) comme fichier.
     */
    public static function contenu(string $contenu, string $nom, string $type, bool $enLigne = false): self
    {
        return (new self($contenu, 200))
            ->entete('Content-Type', $type)
            ->entete('Content-Length', (string) strlen($contenu))
            ->entete('Content-Disposition', self::disposition($nom, $enLigne))
            ->entete('Cache-Control', 'private, no-store');
    }

    private static function disposition(string $nom, bool $enLigne): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._ -]/', '_', \App\Domain\Texte::sansAccents($nom)) ?: 'fichier';
        return sprintf(
            '%s; filename="%s"; filename*=UTF-8\'\'%s',
            $enLigne ? 'inline' : 'attachment',
            $ascii,
            rawurlencode($nom),
        );
    }

    public function entete(string $nom, string $valeur): self
    {
        $this->entetes[$nom] = $valeur;
        return $this;
    }

    public function statut(): int
    {
        return $this->statut;
    }

    public function corps(): string
    {
        return $this->corps;
    }

    public function envoyer(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statut);
            foreach ($this->entetes as $nom => $valeur) {
                header($nom . ': ' . $valeur);
            }
        }
        if ($this->flux !== null) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            ($this->flux)();
            return;
        }
        echo $this->corps;
    }
}
