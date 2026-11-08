<?php

declare(strict_types=1);

use App\Import\AnalyseurListe;
use App\Import\LecteurCsv;
use App\Import\LecteurXlsx;

test('lit un classeur Excel : textes, nombres et dates', function (): void {
    $chemin = classeur_test([
        ['N°', 'Noms et prénoms', 'Année'],
        [1, 'TAMKO DAVID', '2001-2002'],
        [2, 'BILONG S.', 42186],
    ], ['C3' => 1]);

    $lignes = iterator_to_array((new LecteurXlsx($chemin))->lignes());
    unlink($chemin);

    egal('Noms et prénoms', $lignes[1][1]);
    egal(1, $lignes[2][0]);
    egal('TAMKO DAVID', $lignes[2][1]);
    vrai($lignes[3][2] instanceof DateTimeImmutable, 'la date Excel doit être convertie');
    egal('2015-07-01', $lignes[3][2]->format('Y-m-d'));
});

test('convertit les références de colonnes', function (): void {
    egal(0, LecteurXlsx::indexColonne('A1'));
    egal(25, LecteurXlsx::indexColonne('Z9'));
    egal(26, LecteurXlsx::indexColonne('AA3'));
});

test('reconnaît les formats de date personnalisés', function (): void {
    vrai(LecteurXlsx::formatEstDate('dd/mm/yyyy'));
    vrai(LecteurXlsx::formatEstDate('[$-40C]mmm yyyy'));
    vrai(!LecteurXlsx::formatEstDate('0.00'));
    vrai(!LecteurXlsx::formatEstDate('"Sem "0'));
    vrai(!LecteurXlsx::formatEstDate('General'));
});

test('refuse un fichier qui n\'est pas un classeur', function (): void {
    $chemin = tempnam(sys_get_temp_dir(), 'faux');
    file_put_contents($chemin, 'pas un zip');
    leve(RuntimeException::class, static fn () => new LecteurXlsx($chemin));
    unlink($chemin);
});

test('lit un CSV Excel en point-virgule et Windows-1252', function (): void {
    $chemin = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($chemin, mb_convert_encoding("N°;Noms et prénoms;Effets disponibles;Filière;Année\r\n1;ATANGANA LÉA;S6 + ATT;PG;2025/2026\r\n", 'Windows-1252', 'UTF-8'));

    $lecteur = new LecteurCsv($chemin);
    $lignes = iterator_to_array($lecteur->lignes());
    unlink($chemin);

    egal(';', $lecteur->separateur());
    egal('Noms et prénoms', $lignes[1][1]);
    egal('ATANGANA LÉA', $lignes[2][1]);
});

test('détecte la virgule et ignore celles entre guillemets', function (): void {
    egal(',', LecteurCsv::detecterSeparateur("Nom,Effets,Filière\n\"NGO, MARIE\",Sem1,GEII\n"));
    egal("\t", LecteurCsv::detecterSeparateur("Nom\tEffets\nA\tB\n"));
});

test('analyse une liste au format de la scolarité', function (): void {
    $analyse = AnalyseurListe::analyser([
        4 => [1 => 'ATTESTATIONS DE REUSSITE ET RELEVES DE NOTES EN SOUFFRANCE A SCOLARITE'],
        6 => [1 => 'ATT-Lic = Attestion de réussite de Licence'],
        9 => [1 => 'N°', 2 => 'Noms et prénoms', 3 => 'Effets disponibles', 4 => 'Filière', 5 => 'Année'],
        10 => [1 => 1, 2 => 'TAMKO DAVID ', 3 => 'Sem2 + Sem3', 4 => 'ACO', 5 => '2001-2002'],
        11 => [1 => 2],
        12 => [1 => 3, 2 => 'FOUDA ARMEL (nom partiellement barré)', 3 => 'Sem3 + Sem4', 4 => 'ACO', 5 => '2010-2011 (?)'],
    ]);

    egal(2, count($analyse['etudiants']));
    egal(1, $analyse['ignorees']);
    egal(9, $analyse['ligne_entete']);
    egal(['numero' => 1, 'nom' => 2, 'effets' => 3, 'filiere' => 4, 'annee' => 5], $analyse['colonnes']);

    $premier = $analyse['etudiants'][0];
    egal('TAMKO DAVID', $premier['nom']);
    egal(',S2,S3,', $premier['codes']);
    egal(2001, $premier['annee_debut']);

    $second = $analyse['etudiants'][1];
    egal('FOUDA ARMEL', $second['nom']);
    egal('nom partiellement barré ; année incertaine', $second['annotation']);
});

test('repère une colonne de niveau sans titre', function (): void {
    $lignes = [1 => ['N°', 'Noms et prénoms', 'Effets disponibles', 'Filière', 'Année']];
    for ($i = 0; $i < 10; $i++) {
        $lignes[] = [$i + 1, 'ETUDIANT ' . $i, 'CS', 'GI1 FI', '2025/2026', $i % 2 ? 'DUT 1' : 'Licence'];
    }
    $analyse = AnalyseurListe::analyser($lignes);
    egal(5, $analyse['colonnes']['niveau'] ?? null);
    egal('Licence', $analyse['etudiants'][0]['niveau']);
});

test('accepte une colonne matricule', function (): void {
    $analyse = AnalyseurListe::analyser([
        1 => ['Matricule', 'Nom', 'Effets'],
        2 => ['21g00123', 'ABENA PAUL', 'Sem1'],
    ]);
    egal('21G00123', $analyse['etudiants'][0]['matricule']);
});

test('explique l\'absence d\'en-tête', function (): void {
    leve(RuntimeException::class, static fn () => AnalyseurListe::analyser([1 => ['a', 'b'], 2 => ['c', 'd']]));
});

test('refuse les anciens fichiers .xls avec une consigne claire', function (): void {
    try {
        AnalyseurListe::depuisFichier('/inexistant.xls', 'xls');
    } catch (RuntimeException $e) {
        vrai(str_contains($e->getMessage(), '.xlsx'));
        return;
    }
    throw new EchecTest('exception attendue');
});
