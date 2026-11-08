<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Url;

/**
 * Échappe une valeur pour l'afficher dans le HTML.
 */
function e(mixed $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $chemin = '/', array $query = []): string
{
    return Url::vers($chemin, $query);
}

function asset(string $chemin): string
{
    return Url::asset($chemin);
}

function csrf_champ(): string
{
    return Csrf::champ();
}

/**
 * Icône du sprite SVG (public/assets/img/icones.svg).
 */
function icone(string $nom, string $classe = 'icone'): string
{
    return sprintf(
        '<svg class="%s" aria-hidden="true" focusable="false"><use href="%s#%s"></use></svg>',
        e($classe),
        e(Url::asset('img/icones.svg')),
        e($nom),
    );
}

/**
 * 8981 → « 8 981 » avec une espace fine insécable, comme en typographie française.
 */
function nombre(int|float $valeur, int $decimales = 0): string
{
    return number_format((float) $valeur, $decimales, ',', "\u{202F}");
}

/**
 * Accorde un mot selon le nombre : pluriel(3, 'fichier') → « 3 fichiers ».
 */
function pluriel(int $n, string $singulier, ?string $pluriel = null): string
{
    return nombre($n) . "\u{00A0}" . ($n > 1 ? ($pluriel ?? $singulier . 's') : $singulier);
}

function taille_lisible(int $octets): string
{
    if ($octets < 1024) {
        return $octets . "\u{00A0}o";
    }
    $unites = ['Ko', 'Mo', 'Go'];
    $valeur = $octets / 1024;
    $i = 0;
    while ($valeur >= 1024 && $i < count($unites) - 1) {
        $valeur /= 1024;
        $i++;
    }
    return nombre($valeur, $valeur < 10 ? 1 : 0) . "\u{00A0}" . $unites[$i];
}

const MOIS_FR = [
    1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
    'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
];

/**
 * « 8 novembre 2026 », avec l'heure si demandé.
 */
function date_fr(DateTimeInterface|string|null $date, bool $heure = false): string
{
    if ($date === null || $date === '') {
        return '';
    }
    if (is_string($date)) {
        $date = new DateTimeImmutable($date);
    }
    $texte = (int) $date->format('j') . ($date->format('j') === '1' ? 'er' : '') . ' '
        . MOIS_FR[(int) $date->format('n')] . ' ' . $date->format('Y');
    if ($heure) {
        $texte .= ' à ' . $date->format('H\hi');
    }
    return $texte;
}

/**
 * « aujourd'hui », « hier », sinon la date courte.
 */
function date_relative(DateTimeInterface|string|null $date): string
{
    if ($date === null || $date === '') {
        return '';
    }
    if (is_string($date)) {
        $date = new DateTimeImmutable($date);
    }
    $jour = $date->format('Y-m-d');
    if ($jour === date('Y-m-d')) {
        return "Aujourd'hui, " . $date->format('H\hi');
    }
    if ($jour === date('Y-m-d', strtotime('-1 day'))) {
        return 'Hier, ' . $date->format('H\hi');
    }
    return (int) $date->format('j') . ' ' . mb_substr(MOIS_FR[(int) $date->format('n')], 0, 4)
        . (mb_strlen(MOIS_FR[(int) $date->format('n')]) > 4 ? '.' : '')
        . ($date->format('Y') !== date('Y') ? ' ' . $date->format('Y') : '');
}
