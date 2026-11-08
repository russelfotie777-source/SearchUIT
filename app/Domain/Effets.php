<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Lit la colonne « Effets disponibles » des listes de la scolarité
 * et la ramène à des codes stables : « Sem2 + Sem3 + ATT-DUT » → S2, S3, ATT-DUT.
 *
 * Les listes sont saisies à la main depuis des années : on y trouve « Sem 2 »,
 * « SeM2 », « S2 », « Sem2 (?) », « Sem 2 et Sem 1 », « SEM 6 ATT-Lic »…
 */
final class Effets
{
    /**
     * Libellés par défaut : [court, complet].
     * Les codes sans définition sur les listes (CE, RM…) gardent leur code ;
     * l'administration peut compléter la légende.
     */
    public const LEGENDE = [
        'S1' => ['S1', 'Relevé de notes du semestre 1'],
        'S2' => ['S2', 'Relevé de notes du semestre 2'],
        'S3' => ['S3', 'Relevé de notes du semestre 3'],
        'S4' => ['S4', 'Relevé de notes du semestre 4'],
        'S5' => ['S5', 'Relevé de notes du semestre 5'],
        'S6' => ['S6', 'Relevé de notes du semestre 6'],
        'N1' => ['Niveau 1', 'Relevé de notes de niveau 1'],
        'N2' => ['Niveau 2', 'Relevé de notes de niveau 2'],
        'N3' => ['Niveau 3', 'Relevé de notes de niveau 3'],
        'RN' => ['RN', 'Relevé de notes'],
        'RL' => ['Relevé Licence', 'Relevé de notes de la Licence'],
        'RBTS' => ['Relevé BTS', 'Relevé de notes du BTS'],
        'BTS' => ['BTS', 'Document du BTS'],
        'ATT-DUT' => ['Att. DUT', 'Attestation de réussite au DUT'],
        'ATT-LIC' => ['Att. Licence', 'Attestation de réussite à la Licence'],
        'ATT-BTS' => ['Att. BTS', 'Attestation de réussite au BTS'],
        'ATT' => ['Attestation', 'Attestation de réussite'],
        'CS' => ['CS', 'Certificat de scolarité'],
        'CE' => ['CE', 'CE'],
        'RM' => ['RM', 'RM'],
        'NP' => ['À préciser', 'Document à préciser au guichet'],
    ];

    /** Ordre d'affichage : relevés, attestations, puis le reste. */
    private const ORDRE = ['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'N1', 'N2', 'N3', 'RN', 'RL', 'RBTS', 'BTS', 'ATT-DUT', 'ATT-LIC', 'ATT-BTS', 'ATT', 'CS', 'CE', 'RM', 'NP'];

    private const MOTIF = '/
        \bATT(?:ESTATION)?(?:\s*[-_.]?\s*(?<att>DUT|LIC(?:ENCE)?|BTS))?\b
      | \bS(?:EM(?:ESTRE)?)?\s*(?<sem>[1-6])\b
      | \b(?:RELEVE\s+(?:DE\s+)?)?NIVEAU\s*(?<niv>III|II|I|[1-3])\b
      | \bRELEVE\s+(?:DE\s+)?(?<rel>BTS|LICENCE|DUT)\b
      | \b(?<bts>BTS)\b
      | \b(?<cs>C\.?\s?S)\b\.?
      | \b(?<autre>RN|RM|CE)\b
    /x';

    /**
     * @return list<array{code: string, libelle: ?string, note: ?string}>
     *         libelle n'est rempli que pour un effet non reconnu (code « AUTRE »).
     */
    public static function analyser(string $brut): array
    {
        $texte = Texte::espaces($brut);
        if ($texte === '' || preg_match('/^[—–-]+\s*(\(\?\))?$/u', $texte)) {
            return [];
        }

        $effets = [];
        foreach (preg_split('/\s*(?:\+|,|;|&|\bet\b)\s*/iu', $texte) ?: [] as $partie) {
            $partie = trim($partie);
            if ($partie === '' || preg_match('/^[—–-]+$/u', $partie)) {
                continue;
            }

            $note = null;
            if (preg_match('/\(([^()]*)\)\s*$/u', $partie, $m)) {
                $note = trim($m[1]) === '' ? null : trim($m[1]);
                $partie = trim(substr($partie, 0, -strlen($m[0])));
            }
            if ($partie === '' || preg_match('/^[—–-]+$/u', $partie)) {
                continue;
            }

            $codes = self::codesDans($partie);
            if ($codes === []) {
                $effets[] = preg_match('/^COL\.?\s*\d/i', $partie)
                    ? ['code' => 'NP', 'libelle' => null, 'note' => null]
                    : ['code' => 'AUTRE', 'libelle' => $partie, 'note' => $note];
                continue;
            }
            foreach ($codes as $code) {
                $effets[] = ['code' => $code, 'libelle' => null, 'note' => $note];
            }
        }

        return self::trier(self::dedoublonner($effets));
    }

    /**
     * @return list<string>
     */
    public static function codesDans(string $partie): array
    {
        $majuscules = mb_strtoupper(Texte::sansAccents($partie));

        if (!preg_match_all(self::MOTIF, $majuscules, $resultats, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL)) {
            return [];
        }

        $codes = [];
        foreach ($resultats as $r) {
            if (($r['sem'] ?? null) !== null) {
                $codes[] = 'S' . $r['sem'];
            } elseif (($r['niv'] ?? null) !== null) {
                $niveau = ['I' => 1, 'II' => 2, 'III' => 3][$r['niv']] ?? (int) $r['niv'];
                $codes[] = 'N' . $niveau;
            } elseif (($r['rel'] ?? null) !== null) {
                $codes[] = ['BTS' => 'RBTS', 'LICENCE' => 'RL', 'DUT' => 'RN'][$r['rel']];
            } elseif (($r['bts'] ?? null) !== null) {
                $codes[] = 'BTS';
            } elseif (($r['cs'] ?? null) !== null) {
                $codes[] = 'CS';
            } elseif (($r['autre'] ?? null) !== null) {
                $codes[] = $r['autre'];
            } elseif (str_starts_with($r[0], 'ATT')) {
                $codes[] = match (true) {
                    ($r['att'] ?? null) === null => 'ATT',
                    str_starts_with((string) $r['att'], 'LIC') => 'ATT-LIC',
                    default => 'ATT-' . $r['att'],
                };
            }
        }
        return $codes;
    }

    /**
     * Forme compacte stockée en base pour filtrer : « ,S2,S3,ATT-DUT, ».
     *
     * @param list<array{code: string, libelle: ?string, note: ?string}> $effets
     */
    public static function codesStockes(array $effets): string
    {
        $codes = array_values(array_unique(array_column($effets, 'code')));
        return $codes === [] ? '' : ',' . implode(',', $codes) . ',';
    }

    /**
     * Libellés [court, complet] d'un effet, en tenant compte de la légende
     * personnalisée par la scolarité.
     *
     * @param array{code: string, libelle: ?string, note: ?string} $effet
     * @param array<string, array{0: string, 1: string}> $legende
     * @return array{0: string, 1: string}
     */
    public static function libelles(array $effet, array $legende = []): array
    {
        if ($effet['code'] === 'AUTRE') {
            $libelle = (string) $effet['libelle'];
            return [$libelle, $libelle];
        }
        return $legende[$effet['code']] ?? self::LEGENDE[$effet['code']] ?? [$effet['code'], $effet['code']];
    }

    /**
     * @param list<array{code: string, libelle: ?string, note: ?string}> $effets
     * @return list<array{code: string, libelle: ?string, note: ?string}>
     */
    private static function dedoublonner(array $effets): array
    {
        $vus = [];
        $resultat = [];
        foreach ($effets as $effet) {
            $cle = $effet['code'] . '|' . ($effet['libelle'] ?? '');
            if (isset($vus[$cle])) {
                continue;
            }
            $vus[$cle] = true;
            $resultat[] = $effet;
        }
        return $resultat;
    }

    /**
     * @param list<array{code: string, libelle: ?string, note: ?string}> $effets
     * @return list<array{code: string, libelle: ?string, note: ?string}>
     */
    private static function trier(array $effets): array
    {
        $rang = array_flip(self::ORDRE);
        usort($effets, static fn (array $a, array $b): int => ($rang[$a['code']] ?? 99) <=> ($rang[$b['code']] ?? 99));
        return $effets;
    }
}
