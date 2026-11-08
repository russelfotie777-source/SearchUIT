<?php

declare(strict_types=1);

use App\Domain\Effets;

$codes = static fn (string $brut): array => array_column(Effets::analyser($brut), 'code');

test('lit les semestres écrits de différentes façons', function () use ($codes): void {
    egal(['S2', 'S3'], $codes('Sem2 + Sem3'));
    egal(['S1', 'S2', 'S3'], $codes('Sem2 + Sem3 + Sem1'), 'trié dans l\'ordre des semestres');
    egal(['S2'], $codes('Sem 2'));
    egal(['S2'], $codes('SeM2'));
    egal(['S4', 'S6'], $codes('Sem4 Sem6'));
    egal(['S1', 'S2'], $codes('Sem 2 et Sem 1'));
    egal(['S3', 'S4'], $codes('S3 + S4'));
});

test('reconnaît les attestations', function () use ($codes): void {
    egal(['ATT-DUT'], $codes('ATT-DUT'));
    egal(['ATT-LIC'], $codes('ATT-Lic'));
    egal(['ATT'], $codes('ATT'));
    egal(['S6', 'ATT-LIC'], $codes('SEM 6 ATT-Lic'));
    egal(['S6', 'ATT-LIC'], $codes('ATT-Lic sem6'));
    egal(['S3', 'S4', 'ATT', 'CS'], $codes('S3 + S4 + ATT + CS'));
});

test('reconnaît les relevés de niveau et du BTS', function () use ($codes): void {
    egal(['N3'], $codes('Niveau III'));
    egal(['N2'], $codes('Niveau 2'));
    egal(['N1'], $codes('Relevé Niveau 1'));
    egal(['RBTS'], $codes('Relevé BTS'));
    egal(['BTS'], $codes('BTS'));
    egal(['RL'], $codes('Relevé Licence'));
});

test('garde la note entre parenthèses', function (): void {
    $effets = Effets::analyser('Sem6 (?)');
    egal('S6', $effets[0]['code']);
    egal('?', $effets[0]['note']);

    $effets = Effets::analyser('RN (absence de relevé)');
    egal('RN', $effets[0]['code']);
    egal('absence de relevé', $effets[0]['note']);

    $effets = Effets::analyser('CE (S1)');
    egal('CE', $effets[0]['code']);
    egal('S1', $effets[0]['note']);
});

test('traite les cases vides et les colonnes sans titre', function () use ($codes): void {
    egal([], $codes('—'));
    egal([], $codes('— (?)'));
    egal([], $codes(''));
    egal(['NP'], $codes('Col. 1 (sans titre) + Col. 3 (sans titre)'));
});

test('conserve un effet inconnu tel quel', function (): void {
    $effets = Effets::analyser('S.C');
    egal('AUTRE', $effets[0]['code']);
    egal('S.C', $effets[0]['libelle']);
});

test('ne confond pas CS avec un semestre', function () use ($codes): void {
    egal(['CS'], $codes('CS'));
    egal(['CS'], $codes('C.S'));
    egal(['RN', 'CS'], $codes('CS + RN'));
});

test('produit la forme stockée pour les filtres', function (): void {
    egal(',S2,S3,ATT-DUT,', Effets::codesStockes(Effets::analyser('ATT-DUT + Sem3 + Sem2')));
    egal('', Effets::codesStockes([]));
});

test('donne des libellés lisibles, personnalisables', function (): void {
    egal(['S2', 'Relevé de notes du semestre 2'], Effets::libelles(['code' => 'S2', 'libelle' => null, 'note' => null]));
    egal(['RM', 'Relevé modifié'], Effets::libelles(['code' => 'RM', 'libelle' => null, 'note' => null], ['RM' => ['RM', 'Relevé modifié']]));
    egal(['S.C', 'S.C'], Effets::libelles(['code' => 'AUTRE', 'libelle' => 'S.C', 'note' => null]));
});
