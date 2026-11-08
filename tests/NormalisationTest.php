<?php

declare(strict_types=1);

use App\Domain\Annee;
use App\Domain\Filiere;
use App\Domain\NomEtudiant;
use App\Domain\Reference;
use App\Domain\Texte;

test('prépare les noms pour la recherche', function (): void {
    egal('NGO NDJOCK EPEE', Texte::pourRecherche('Ngo Ndjock-Épée'));
    egal('ATANGANA LEA', Texte::pourRecherche('ATANGANA LÉA'));
    egal('MBARGA ESTHER JULIE', Texte::pourRecherche('  MBARGA  ESTHER  JULIE '));
    egal('MEKONGO CHANTAL E', Texte::pourRecherche('MEKONGO CHANTAL E.'));
});

test('sépare le nom des annotations de la liste', function (): void {
    egal(['nom' => 'FOUDA ARMEL', 'annotation' => 'nom partiellement barré'], NomEtudiant::analyser('FOUDA ARMEL (nom partiellement barré)'));
    egal(['nom' => 'NJIKE ELODIE', 'annotation' => '?'], NomEtudiant::analyser('NJIKE ELODIE (?)'));
    egal(['nom' => 'EYENGA M.', 'annotation' => null], NomEtudiant::analyser('EYENGA M. ('));
    egal(['nom' => 'TAMKO DAVID', 'annotation' => null], NomEtudiant::analyser('TAMKO DAVID '));
    egal(['nom' => 'NDEDI CLAIRE', 'annotation' => null], NomEtudiant::analyser('Ndedi Claire'));
});

test('explique les annotations à l\'étudiant', function (): void {
    vrai(str_contains((string) NomEtudiant::annotationLisible('?'), 'incertaine'));
    egal(null, NomEtudiant::annotationLisible(null));
});

test('normalise les années', function (): void {
    egal(['libelle' => '2015-2016', 'debut' => 2015, 'incertaine' => false], Annee::analyser('2015-2016 '));
    egal(['libelle' => '2025-2026', 'debut' => 2025, 'incertaine' => false], Annee::analyser('2025/2026'));
    egal(['libelle' => '2023-2024', 'debut' => 2023, 'incertaine' => false], Annee::analyser('2023- 2024'));
    egal(['libelle' => '2014-2015', 'debut' => 2014, 'incertaine' => true], Annee::analyser('2014-2015 (?)'));
    egal(['libelle' => '2002', 'debut' => 2002, 'incertaine' => false], Annee::analyser(2002));
    egal(['libelle' => 'Juillet 2015', 'debut' => 2015, 'incertaine' => false], Annee::analyser(new DateTimeImmutable('2015-07-01')));
    egal(['libelle' => 'Août 2021', 'debut' => 2021, 'incertaine' => false], Annee::analyser('Août 2021'));
    egal(['libelle' => null, 'debut' => null, 'incertaine' => false], Annee::analyser('—'));
    egal(['libelle' => null, 'debut' => null, 'incertaine' => false], Annee::analyser(null));
});

test('extrait le code de la filière', function (): void {
    egal(['libelle' => 'GI2 FA', 'code' => 'GI'], Filiere::analyser('GI2 FA'));
    egal(['libelle' => 'GAPMO1 FI', 'code' => 'GAPMO'], Filiere::analyser('GAPMO1 FI'));
    egal(['libelle' => 'CM/FM', 'code' => 'CM/FM'], Filiere::analyser('CM/FM'));
    egal(['libelle' => 'GEII', 'code' => 'GEII'], Filiere::analyser(' geii '));
    egal(['libelle' => null, 'code' => null], Filiere::analyser('—'));
});

test('génère des références de fiche uniques et lisibles', function (): void {
    $prises = [];
    for ($i = 0; $i < 500; $i++) {
        $reference = Reference::nouvelle($prises);
        vrai(Reference::valide($reference), "référence invalide : {$reference}");
        vrai(!preg_match('/[IO01]/', $reference), 'caractère ambigu');
    }
    egal(500, count($prises));
    vrai(!Reference::valide('abc'));
});
