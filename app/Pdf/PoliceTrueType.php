<?php

declare(strict_types=1);

namespace App\Pdf;

use RuntimeException;

/**
 * Lit un fichier de police TrueType (.ttf) pour l'intégrer dans un PDF :
 * métriques, chasse de chaque caractère et nom PostScript.
 * Seuls les caractères Windows-1252 (latin, accents français, « », €, œ…) sont utilisés.
 */
final class PoliceTrueType
{
    /** Caractères 0x80 à 0x9F de Windows-1252 et leur point de code Unicode. */
    private const WINDOWS_1252 = [
        0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E, 0x85 => 0x2026, 0x86 => 0x2020,
        0x87 => 0x2021, 0x88 => 0x02C6, 0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152,
        0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C, 0x94 => 0x201D, 0x95 => 0x2022,
        0x96 => 0x2013, 0x97 => 0x2014, 0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A,
        0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
    ];

    public readonly string $donnees;
    public readonly string $nomPostScript;
    public readonly int $unitesParEm;
    /** @var array{0: int, 1: int, 2: int, 3: int} */
    public readonly array $boite;
    public readonly int $ascendante;
    public readonly int $descendante;
    public readonly int $hauteurCapitales;
    public readonly float $angleItalique;
    /** @var array<int, int> code Windows-1252 (32 à 255) → chasse en millièmes d'em */
    public readonly array $chasses;

    /** @var array<string, array{0: int, 1: int}> */
    private array $tables = [];

    public function __construct(string $chemin)
    {
        $donnees = @file_get_contents($chemin);
        if ($donnees === false || strlen($donnees) < 12) {
            throw new RuntimeException("Police illisible : {$chemin}");
        }
        $this->donnees = $donnees;

        $nombreTables = $this->u16(4);
        for ($i = 0; $i < $nombreTables; $i++) {
            $entree = 12 + 16 * $i;
            $this->tables[substr($donnees, $entree, 4)] = [$this->u32($entree + 8), $this->u32($entree + 12)];
        }
        foreach (['head', 'hhea', 'hmtx', 'cmap', 'name', 'post'] as $requise) {
            if (!isset($this->tables[$requise])) {
                throw new RuntimeException("Police incomplète : table « {$requise} » absente.");
            }
        }

        $head = $this->tables['head'][0];
        $this->unitesParEm = $this->u16($head + 18);
        $echelle = 1000 / $this->unitesParEm;
        $this->boite = [
            (int) round($this->i16($head + 36) * $echelle),
            (int) round($this->i16($head + 38) * $echelle),
            (int) round($this->i16($head + 40) * $echelle),
            (int) round($this->i16($head + 42) * $echelle),
        ];

        $hhea = $this->tables['hhea'][0];
        $ascendante = $this->i16($hhea + 4);
        $descendante = $this->i16($hhea + 6);
        $capitales = (int) round($ascendante * 0.7);
        if (isset($this->tables['OS/2'])) {
            $os2 = $this->tables['OS/2'][0];
            $ascendante = $this->i16($os2 + 68);
            $descendante = $this->i16($os2 + 70);
            if ($this->u16($os2) >= 2) {
                $capitales = $this->i16($os2 + 88);
            }
        }
        $this->ascendante = (int) round($ascendante * $echelle);
        $this->descendante = (int) round($descendante * $echelle);
        $this->hauteurCapitales = (int) round($capitales * $echelle);

        $post = $this->tables['post'][0];
        $this->angleItalique = $this->i16($post + 4) + $this->u16($post + 6) / 65536;

        $this->nomPostScript = $this->lireNomPostScript();

        $avances = $this->lireAvances($this->u16($hhea + 34));
        $glyphes = $this->lireCmap();
        $chasses = [];
        for ($code = 32; $code <= 255; $code++) {
            $unicode = self::WINDOWS_1252[$code] ?? ($code >= 0x80 && $code <= 0x9F ? null : $code);
            $glyphe = $unicode === null ? 0 : ($glyphes[$unicode] ?? 0);
            $avance = $avances[$glyphe] ?? end($avances);
            $chasses[$code] = (int) round($avance * $echelle);
        }
        $this->chasses = $chasses;
    }

    /**
     * Largeur d'un texte déjà converti en Windows-1252, en millièmes d'em.
     */
    public function largeur(string $texte1252): int
    {
        $total = 0;
        for ($i = 0, $n = strlen($texte1252); $i < $n; $i++) {
            $total += $this->chasses[ord($texte1252[$i])] ?? 500;
        }
        return $total;
    }

    /** @return list<int> */
    private function lireAvances(int $nombre): array
    {
        $hmtx = $this->tables['hmtx'][0];
        $avances = [];
        for ($i = 0; $i < $nombre; $i++) {
            $avances[] = $this->u16($hmtx + 4 * $i);
        }
        return $avances;
    }

    /**
     * Table cmap au format 4 (Unicode BMP) : point de code → numéro de glyphe.
     *
     * @return array<int, int>
     */
    private function lireCmap(): array
    {
        $cmap = $this->tables['cmap'][0];
        $nombre = $this->u16($cmap + 2);
        $sousTable = null;
        for ($i = 0; $i < $nombre; $i++) {
            $entree = $cmap + 4 + 8 * $i;
            $plateforme = $this->u16($entree);
            $codage = $this->u16($entree + 2);
            $decalage = $cmap + $this->u32($entree + 4);
            if ($this->u16($decalage) === 4 && (($plateforme === 3 && $codage === 1) || $plateforme === 0)) {
                $sousTable = $decalage;
                break;
            }
        }
        if ($sousTable === null) {
            throw new RuntimeException('Police sans table de caractères Unicode exploitable.');
        }

        $segments = intdiv($this->u16($sousTable + 6), 2);
        $fins = $sousTable + 14;
        $debuts = $fins + 2 * $segments + 2;
        $deltas = $debuts + 2 * $segments;
        $plages = $deltas + 2 * $segments;

        $glyphes = [];
        for ($s = 0; $s < $segments; $s++) {
            $fin = $this->u16($fins + 2 * $s);
            $debut = $this->u16($debuts + 2 * $s);
            $delta = $this->u16($deltas + 2 * $s);
            $plage = $this->u16($plages + 2 * $s);
            // Seuls les caractères utiles au PDF (Windows-1252) nous intéressent.
            for ($c = $debut; $c <= $fin && $c !== 0xFFFF; $c++) {
                if ($c > 0x2122) {
                    break;
                }
                if ($plage === 0) {
                    $glyphe = ($c + $delta) & 0xFFFF;
                } else {
                    $adresse = $plages + 2 * $s + $plage + 2 * ($c - $debut);
                    $glyphe = $this->u16($adresse);
                    if ($glyphe !== 0) {
                        $glyphe = ($glyphe + $delta) & 0xFFFF;
                    }
                }
                $glyphes[$c] = $glyphe;
            }
        }
        return $glyphes;
    }

    private function lireNomPostScript(): string
    {
        $table = $this->tables['name'][0];
        $nombre = $this->u16($table + 2);
        $chaines = $table + $this->u16($table + 4);
        for ($i = 0; $i < $nombre; $i++) {
            $entree = $table + 6 + 12 * $i;
            if ($this->u16($entree + 6) !== 6) {
                continue;
            }
            $plateforme = $this->u16($entree);
            $texte = substr($this->donnees, $chaines + $this->u16($entree + 10), $this->u16($entree + 8));
            if ($plateforme === 3 || $plateforme === 0) {
                $texte = (string) mb_convert_encoding($texte, 'UTF-8', 'UTF-16BE');
            }
            $texte = (string) preg_replace('/[^A-Za-z0-9-]/', '', $texte);
            if ($texte !== '') {
                return $texte;
            }
        }
        return 'Police' . substr(md5($this->donnees), 0, 6);
    }

    private function u16(int $position): int
    {
        return unpack('n', $this->donnees, $position)[1];
    }

    private function i16(int $position): int
    {
        $valeur = $this->u16($position);
        return $valeur >= 0x8000 ? $valeur - 0x10000 : $valeur;
    }

    private function u32(int $position): int
    {
        return unpack('N', $this->donnees, $position)[1];
    }
}
