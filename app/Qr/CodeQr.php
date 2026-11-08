<?php

declare(strict_types=1);

namespace App\Qr;

use InvalidArgumentException;

/**
 * Encodeur de QR code (norme ISO/IEC 18004), mode octet, correction d'erreur M,
 * versions 1 à 10 : assez pour une adresse de plus de 200 caractères.
 *
 * Écrit pour ce projet afin de n'ajouter aucune dépendance. L'algorithme suit
 * la norme : codage des données, Reed-Solomon sur GF(256), entrelacement des
 * blocs, placement en zigzag, choix du masque par pénalités.
 */
final class CodeQr
{
    /** Codes de correction par bloc et nombre de blocs, niveau M, versions 1 à 10. */
    private const ECC_PAR_BLOC = [1 => 10, 16, 26, 18, 24, 16, 18, 22, 22, 26];
    private const NOMBRE_BLOCS = [1 => 1, 1, 1, 2, 2, 4, 4, 4, 5, 5];
    private const POSITIONS_ALIGNEMENT = [
        1 => [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34],
        [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50],
    ];
    private const VERSION_MAX = 10;
    private const BITS_NIVEAU_M = 0;

    private int $version;
    private int $taille;
    /** @var list<list<bool>> */
    private array $modules = [];
    /** @var list<list<bool>> modules réservés aux motifs fixes */
    private array $fonction = [];
    private int $masque = 0;

    private function __construct(string $donnees, ?int $masqueForce)
    {
        $this->version = self::versionMinimale(strlen($donnees));
        $this->taille = $this->version * 4 + 17;

        $ligneVide = array_fill(0, $this->taille, false);
        $this->modules = array_fill(0, $this->taille, $ligneVide);
        $this->fonction = array_fill(0, $this->taille, $ligneVide);

        $this->dessinerMotifsFixes();
        $this->placerDonnees($this->entrelacer($this->codewords($donnees)));

        if ($masqueForce !== null) {
            $this->masque = $masqueForce;
        } else {
            $meilleur = PHP_INT_MAX;
            for ($m = 0; $m < 8; $m++) {
                $this->appliquerMasque($m);
                $this->dessinerFormat($m);
                $penalite = $this->penalite();
                if ($penalite < $meilleur) {
                    $meilleur = $penalite;
                    $this->masque = $m;
                }
                $this->appliquerMasque($m); // Le masque est un XOR : on l'annule.
            }
        }
        $this->appliquerMasque($this->masque);
        $this->dessinerFormat($this->masque);
    }

    public static function generer(string $donnees, ?int $masque = null): self
    {
        if ($masque !== null && ($masque < 0 || $masque > 7)) {
            throw new InvalidArgumentException('Masque invalide.');
        }
        return new self($donnees, $masque);
    }

    public function taille(): int
    {
        return $this->taille;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function masque(): int
    {
        return $this->masque;
    }

    public function estNoir(int $x, int $y): bool
    {
        return $this->modules[$y][$x] ?? false;
    }

    /**
     * Rendu SVG : un seul tracé, net à toutes les tailles et à l'impression.
     */
    public function svg(int $marge = 4, string $couleur = '#0f2a1d', string $titre = 'QR code'): string
    {
        $total = $this->taille + 2 * $marge;
        $chemin = '';
        for ($y = 0; $y < $this->taille; $y++) {
            $x = 0;
            while ($x < $this->taille) {
                if (!$this->modules[$y][$x]) {
                    $x++;
                    continue;
                }
                $debut = $x;
                while ($x < $this->taille && $this->modules[$y][$x]) {
                    $x++;
                }
                $chemin .= sprintf('M%d %dh%dv1h-%dz', $debut + $marge, $y + $marge, $x - $debut, $x - $debut);
            }
        }
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" shape-rendering="crispEdges" role="img" aria-label="%2$s">'
            . '<rect width="%1$d" height="%1$d" fill="#fff"/><path fill="%3$s" d="%4$s"/></svg>',
            $total,
            htmlspecialchars($titre, ENT_QUOTES),
            htmlspecialchars($couleur, ENT_QUOTES),
            $chemin,
        );
    }

    // ---------------------------------------------------------------- Codage

    private static function capaciteOctets(int $version): int
    {
        return intdiv(self::modulesDonnees($version), 8) - self::ECC_PAR_BLOC[$version] * self::NOMBRE_BLOCS[$version];
    }

    private static function versionMinimale(int $longueur): int
    {
        for ($v = 1; $v <= self::VERSION_MAX; $v++) {
            $bitsCompteur = $v < 10 ? 8 : 16;
            if (4 + $bitsCompteur + 8 * $longueur <= self::capaciteOctets($v) * 8) {
                return $v;
            }
        }
        throw new InvalidArgumentException('Texte trop long pour un QR code de version 10.');
    }

    /**
     * Nombre de modules disponibles pour les données et la correction.
     */
    private static function modulesDonnees(int $version): int
    {
        $resultat = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $alignements = intdiv($version, 7) + 2;
            $resultat -= (25 * $alignements - 10) * $alignements - 55;
            if ($version >= 7) {
                $resultat -= 36;
            }
        }
        return $resultat;
    }

    /**
     * Mode octet, compteur, données, terminaison et octets de bourrage.
     *
     * @return list<int>
     */
    private function codewords(string $donnees): array
    {
        $bits = [];
        $ajouter = static function (int $valeur, int $longueur) use (&$bits): void {
            for ($i = $longueur - 1; $i >= 0; $i--) {
                $bits[] = ($valeur >> $i) & 1;
            }
        };

        $ajouter(0b0100, 4);
        $ajouter(strlen($donnees), $this->version < 10 ? 8 : 16);
        for ($i = 0, $n = strlen($donnees); $i < $n; $i++) {
            $ajouter(ord($donnees[$i]), 8);
        }

        $capacite = self::capaciteOctets($this->version) * 8;
        $ajouter(0, min(4, $capacite - count($bits)));
        $ajouter(0, (8 - count($bits) % 8) % 8);
        for ($bourrage = 0xEC; count($bits) < $capacite; $bourrage ^= 0xEC ^ 0x11) {
            $ajouter($bourrage, 8);
        }

        $octets = [];
        foreach (array_chunk($bits, 8) as $groupe) {
            $octet = 0;
            foreach ($groupe as $bit) {
                $octet = ($octet << 1) | $bit;
            }
            $octets[] = $octet;
        }
        return $octets;
    }

    /**
     * Découpe en blocs, ajoute la correction Reed-Solomon, entrelace.
     *
     * @param list<int> $donnees
     * @return list<int>
     */
    private function entrelacer(array $donnees): array
    {
        $nombreBlocs = self::NOMBRE_BLOCS[$this->version];
        $ecc = self::ECC_PAR_BLOC[$this->version];
        $brut = intdiv(self::modulesDonnees($this->version), 8);
        $blocsCourts = $nombreBlocs - $brut % $nombreBlocs;
        $longueurCourte = intdiv($brut, $nombreBlocs);

        $diviseur = self::diviseurReedSolomon($ecc);
        $blocs = [];
        $k = 0;
        for ($i = 0; $i < $nombreBlocs; $i++) {
            $longueur = $longueurCourte - $ecc + ($i < $blocsCourts ? 0 : 1);
            $morceau = array_slice($donnees, $k, $longueur);
            $k += $longueur;
            $correction = self::resteReedSolomon($morceau, $diviseur);
            if ($i < $blocsCourts) {
                $morceau[] = 0;
            }
            $blocs[] = array_merge($morceau, $correction);
        }

        $resultat = [];
        $longueurBloc = count($blocs[0]);
        for ($i = 0; $i < $longueurBloc; $i++) {
            foreach ($blocs as $j => $bloc) {
                // L'octet fictif ajouté aux blocs courts n'est pas émis.
                if ($i !== $longueurCourte - $ecc || $j >= $blocsCourts) {
                    $resultat[] = $bloc[$i];
                }
            }
        }
        return $resultat;
    }

    /** @return list<int> */
    private static function diviseurReedSolomon(int $degre): array
    {
        $resultat = array_fill(0, $degre, 0);
        $resultat[$degre - 1] = 1;
        $racine = 1;
        for ($i = 0; $i < $degre; $i++) {
            for ($j = 0; $j < $degre; $j++) {
                $resultat[$j] = self::multiplier($resultat[$j], $racine);
                if ($j + 1 < $degre) {
                    $resultat[$j] ^= $resultat[$j + 1];
                }
            }
            $racine = self::multiplier($racine, 0x02);
        }
        return $resultat;
    }

    /**
     * @param list<int> $donnees
     * @param list<int> $diviseur
     * @return list<int>
     */
    private static function resteReedSolomon(array $donnees, array $diviseur): array
    {
        $resultat = array_fill(0, count($diviseur), 0);
        foreach ($donnees as $octet) {
            $facteur = $octet ^ array_shift($resultat);
            $resultat[] = 0;
            foreach ($diviseur as $i => $coefficient) {
                $resultat[$i] ^= self::multiplier($coefficient, $facteur);
            }
        }
        return $resultat;
    }

    /** Multiplication dans GF(2^8), polynôme 0x11D. */
    private static function multiplier(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    // ------------------------------------------------------------- Placement

    private function definirFonction(int $x, int $y, bool $noir): void
    {
        $this->modules[$y][$x] = $noir;
        $this->fonction[$y][$x] = true;
    }

    private function dessinerMotifsFixes(): void
    {
        // Lignes de synchronisation.
        for ($i = 0; $i < $this->taille; $i++) {
            $this->definirFonction(6, $i, $i % 2 === 0);
            $this->definirFonction($i, 6, $i % 2 === 0);
        }

        // Repères des trois coins, avec leur séparateur blanc.
        foreach ([[3, 3], [$this->taille - 4, 3], [3, $this->taille - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $this->taille && $y >= 0 && $y < $this->taille) {
                        $distance = max(abs($dx), abs($dy));
                        $this->definirFonction($x, $y, $distance !== 2 && $distance !== 4);
                    }
                }
            }
        }

        // Motifs d'alignement.
        $positions = self::POSITIONS_ALIGNEMENT[$this->version];
        $n = count($positions);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->definirFonction($positions[$i] + $dx, $positions[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }

        // Emplacements du format (remplis plus tard) et de la version.
        $this->dessinerFormat(0);
        if ($this->version >= 7) {
            $reste = $this->version;
            for ($i = 0; $i < 12; $i++) {
                $reste = ($reste << 1) ^ (($reste >> 11) * 0x1F25);
            }
            $bits = ($this->version << 12) | $reste;
            for ($i = 0; $i < 18; $i++) {
                $noir = (($bits >> $i) & 1) === 1;
                $a = $this->taille - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->definirFonction($a, $b, $noir);
                $this->definirFonction($b, $a, $noir);
            }
        }
    }

    private function dessinerFormat(int $masque): void
    {
        $donnees = (self::BITS_NIVEAU_M << 3) | $masque;
        $reste = $donnees;
        for ($i = 0; $i < 10; $i++) {
            $reste = ($reste << 1) ^ (($reste >> 9) * 0x537);
        }
        $bits = (($donnees << 10) | $reste) ^ 0x5412;
        $bit = static fn (int $i): bool => (($bits >> $i) & 1) === 1;

        // Première copie, autour du repère en haut à gauche.
        for ($i = 0; $i <= 5; $i++) {
            $this->definirFonction(8, $i, $bit($i));
        }
        $this->definirFonction(8, 7, $bit(6));
        $this->definirFonction(8, 8, $bit(7));
        $this->definirFonction(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->definirFonction(14 - $i, 8, $bit($i));
        }

        // Seconde copie, répartie entre les deux autres repères.
        for ($i = 0; $i < 8; $i++) {
            $this->definirFonction($this->taille - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->definirFonction(8, $this->taille - 15 + $i, $bit($i));
        }
        $this->definirFonction(8, $this->taille - 8, true); // Module toujours noir.
    }

    /** @param list<int> $octets */
    private function placerDonnees(array $octets): void
    {
        $i = 0;
        $total = count($octets) * 8;
        for ($droite = $this->taille - 1; $droite >= 1; $droite -= 2) {
            if ($droite === 6) {
                $droite = 5;
            }
            for ($vertical = 0; $vertical < $this->taille; $vertical++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $droite - $j;
                    $montant = (($droite + 1) & 2) === 0;
                    $y = $montant ? $this->taille - 1 - $vertical : $vertical;
                    if (!$this->fonction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($octets[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function appliquerMasque(int $masque): void
    {
        for ($y = 0; $y < $this->taille; $y++) {
            for ($x = 0; $x < $this->taille; $x++) {
                if ($this->fonction[$y][$x]) {
                    continue;
                }
                $inverser = match ($masque) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($inverser) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /**
     * Pénalités de la norme : longues séries, carrés de même couleur,
     * faux repères et déséquilibre entre noir et blanc.
     */
    private function penalite(): int
    {
        $total = 0;
        $n = $this->taille;
        $lignes = [];
        $colonnes = array_fill(0, $n, '');

        for ($y = 0; $y < $n; $y++) {
            $ligne = '';
            for ($x = 0; $x < $n; $x++) {
                $c = $this->modules[$y][$x] ? '1' : '0';
                $ligne .= $c;
                $colonnes[$x] .= $c;
            }
            $lignes[] = $ligne;
        }

        foreach (array_merge($lignes, $colonnes) as $suite) {
            if (preg_match_all('/0{5,}|1{5,}/', $suite, $series)) {
                foreach ($series[0] as $serie) {
                    $total += 3 + strlen($serie) - 5;
                }
            }
            $total += 40 * (substr_count($suite, '10111010000') + substr_count($suite, '00001011101'));
        }

        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $c = $this->modules[$y][$x];
                if ($c === $this->modules[$y][$x + 1] && $c === $this->modules[$y + 1][$x] && $c === $this->modules[$y + 1][$x + 1]) {
                    $total += 3;
                }
            }
        }

        $noirs = substr_count(implode('', $lignes), '1');
        $k = (int) ceil(abs($noirs * 20 - $n * $n * 10) / ($n * $n)) - 1;
        return $total + max(0, $k) * 10;
    }
}
