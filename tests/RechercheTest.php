<?php

declare(strict_types=1);

use App\Domain\Recherche;

test('découpe la saisie en mots sans accents', function (): void {
    egal(['ESSOMBA', 'RUTH'], Recherche::mots('  essomba   Ruth '));
    egal(['NGO', 'NDJOCK', 'EPEE'], Recherche::mots('Ngo Ndjock-Épée'));
    egal([], Recherche::mots(' - '));
    egal(['ESSOMBA'], Recherche::mots('essomba ESSOMBA'), 'les doublons sont retirés');
});

test('reconnaît un matricule', function (): void {
    vrai(Recherche::estMatricule('21G00123'));
    vrai(Recherche::estMatricule('IUT-2019/0457'));
    vrai(!Recherche::estMatricule('ESSOMBA'));
    vrai(!Recherche::estMatricule('12345'), 'des chiffres seuls ne suffisent pas');
    vrai(!Recherche::estMatricule('ESSOMBA RUTH 2'));
});

test('surligne les débuts de mots trouvés, accents compris', function (): void {
    egal([['ESSO', true], ['MBA ', false], ['RU', true], ['TH', false]], Recherche::segments('ESSOMBA RUTH', ['ESSO', 'RU']));
    egal([['ATANGANA ', false], ['LÉ', true], ['A', false]], Recherche::segments('ATANGANA LÉA', ['LE']));
    egal([['ATANGANA LÉA', false]], Recherche::segments('ATANGANA LÉA', ['ANGA']), 'pas au milieu d\'un mot');
    egal([['NDEDI CLAIRE', false]], Recherche::segments('NDEDI CLAIRE', []));
});

test('trouve des noms proches malgré une faute de frappe', function (): void {
    vrai(Recherche::ressemblance(['ESOMBA'], 'ESSOMBA RUTH') >= 0.7, 'lettre oubliée');
    vrai(Recherche::ressemblance(['ESSOMBAH', 'RUT'], 'ESSOMBA RUTH') >= 0.7, 'lettre en trop');
    vrai(Recherche::ressemblance(['MEKONGO'], 'ESSOMBA RUTH') < 0.5, 'nom sans rapport');
    egal(1.0, Recherche::ressemblance(['ESSO'], 'ESSOMBA RUTH'), 'début de mot exact');
});
