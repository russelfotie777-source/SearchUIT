<?php

declare(strict_types=1);

namespace App\Import;

use RuntimeException;

/**
 * Lit un fichier CSV exporté depuis Excel, LibreOffice ou Google Sheets.
 * Détecte seul le séparateur (point-virgule, virgule ou tabulation)
 * et l'encodage (UTF-8 ou Windows-1252, celui d'Excel en français).
 */
final class LecteurCsv
{
    private string $contenu;
    private string $separateur;

    public function __construct(string $chemin)
    {
        $contenu = @file_get_contents($chemin);
        if ($contenu === false) {
            throw new RuntimeException('Impossible de lire le fichier CSV.');
        }

        // Marque d'ordre des octets UTF-8.
        if (str_starts_with($contenu, "\xEF\xBB\xBF")) {
            $contenu = substr($contenu, 3);
        } elseif (str_starts_with($contenu, "\xFF\xFE") || str_starts_with($contenu, "\xFE\xFF")) {
            $contenu = (string) mb_convert_encoding(substr($contenu, 2), 'UTF-8', 'UTF-16');
        }

        if (!mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = (string) mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }

        $this->contenu = str_replace(["\r\n", "\r"], "\n", $contenu);
        $this->separateur = self::detecterSeparateur($this->contenu);
    }

    public function separateur(): string
    {
        return $this->separateur;
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    public function lignes(): \Generator
    {
        $flux = fopen('php://memory', 'r+');
        if ($flux === false) {
            throw new RuntimeException('Mémoire insuffisante pour lire le CSV.');
        }
        fwrite($flux, $this->contenu);
        rewind($flux);

        $numero = 0;
        while (($cellules = fgetcsv($flux, 0, $this->separateur, '"', '')) !== false) {
            $numero++;
            $ligne = [];
            foreach ($cellules as $index => $valeur) {
                $valeur = trim((string) $valeur);
                if ($valeur !== '') {
                    $ligne[$index] = $valeur;
                }
            }
            yield $numero => $ligne;
        }
        fclose($flux);
    }

    public static function detecterSeparateur(string $contenu): string
    {
        $echantillon = array_slice(explode("\n", $contenu), 0, 30);
        $meilleur = ';';
        $score = -1;
        foreach ([';', ',', "\t", '|'] as $candidat) {
            $total = 0;
            foreach ($echantillon as $ligne) {
                // On ignore les séparateurs à l'intérieur des guillemets.
                $total += substr_count((string) preg_replace('/"[^"]*"/', '', $ligne), $candidat);
            }
            if ($total > $score) {
                $score = $total;
                $meilleur = $candidat;
            }
        }
        return $meilleur;
    }
}
