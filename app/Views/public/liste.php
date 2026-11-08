<?php
/**
 * @var array{nom: string, filiere: string, annee: string, document: string, statut: string, tri: string} $filtres
 * @var array{total: int, lignes: list<array<string, mixed>>, page: int, pages: int} $resultat
 * @var int $parPage
 * @var list<array{code: string, nombre: int}> $filieres
 * @var list<array{annee: int, nombre: int}> $annees
 * @var list<array{code: string, court: string, complet: string}> $documents
 * @var int $filtresActifs
 */
use App\Domain\Recherche;
use App\Repositories\Etudiants;

$parametres = array_filter([
    'nom' => $filtres['nom'],
    'filiere' => $filtres['filiere'],
    'annee' => $filtres['annee'],
    'document' => $filtres['document'],
    'statut' => $filtres['statut'] !== 'disponible' ? $filtres['statut'] : '',
    'tri' => $filtres['tri'] !== 'nom' ? $filtres['tri'] : '',
], static fn ($v): bool => $v !== '');

$lienPage = static fn (int $page): string => url('liste', array_merge($parametres, ['page' => $page > 1 ? $page : null]));
$mots = Recherche::mots($filtres['nom']);
$debut = ($resultat['page'] - 1) * $parPage + 1;
$fin = $debut + count($resultat['lignes']) - 1;
?>
<section class="conteneur titre-page">
    <h1>Liste des effets disponibles</h1>
    <p>Tous les relevés, attestations et certificats en attente au service de la scolarité. Filtrez par filière, par année ou par document.</p>
</section>

<div class="conteneur">
    <form class="filtres" method="get" action="<?= e(url('liste')) ?>" data-filtres>
        <div class="filtres__nom">
            <label class="masque-visuel" for="filtre-nom">Nom</label>
            <?= icone('recherche', 'icone icone--petite') ?>
            <input class="saisie" id="filtre-nom" name="nom" type="search" value="<?= e($filtres['nom']) ?>" placeholder="Filtrer par nom" autocomplete="off" spellcheck="false">
        </div>

        <div class="champ">
            <label for="filtre-filiere">Filière</label>
            <select class="selection" id="filtre-filiere" name="filiere">
                <option value="">Toutes</option>
                <?php foreach ($filieres as $filiere): ?>
                    <option value="<?= e($filiere['code']) ?>"<?= $filtres['filiere'] === $filiere['code'] ? ' selected' : '' ?>><?= e($filiere['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label for="filtre-annee">Année</label>
            <select class="selection" id="filtre-annee" name="annee">
                <option value="">Toutes</option>
                <?php foreach ($annees as $annee): ?>
                    <option value="<?= $annee['annee'] ?>"<?= $filtres['annee'] === (string) $annee['annee'] ? ' selected' : '' ?>><?= $annee['annee'] ?>-<?= $annee['annee'] + 1 ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label for="filtre-document">Document</label>
            <select class="selection" id="filtre-document" name="document">
                <option value="">Tous</option>
                <?php foreach ($documents as $document): ?>
                    <option value="<?= e($document['code']) ?>"<?= $filtres['document'] === $document['code'] ? ' selected' : '' ?>>
                        <?= e($document['complet'] !== $document['court'] ? $document['complet'] : $document['court']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label for="filtre-statut">Statut</label>
            <select class="selection" id="filtre-statut" name="statut">
                <option value="disponible"<?= $filtres['statut'] === 'disponible' ? ' selected' : '' ?>>Disponibles</option>
                <option value="retire"<?= $filtres['statut'] === 'retire' ? ' selected' : '' ?>>Déjà retirés</option>
                <option value="tous"<?= $filtres['statut'] === 'tous' ? ' selected' : '' ?>>Tous</option>
            </select>
        </div>

        <?php if ($filtres['tri'] !== 'nom'): ?><input type="hidden" name="tri" value="<?= e($filtres['tri']) ?>"><?php endif; ?>
        <button class="btn btn--primaire filtres__valider" type="submit">Filtrer</button>
    </form>

    <div class="liste-barre">
        <p class="liste-barre__compte" role="status">
            <?php if ($resultat['total'] === 0): ?>
                Aucun étudiant
            <?php elseif ($resultat['pages'] > 1): ?>
                <?= nombre($debut) ?> à <?= nombre($fin) ?> sur <?= e(pluriel($resultat['total'], 'étudiant')) ?>
            <?php else: ?>
                <?= e(pluriel($resultat['total'], 'étudiant')) ?>
            <?php endif; ?>
            <?php if ($filtresActifs > 0): ?>
                <a href="<?= e(url('liste')) ?>">Effacer les filtres</a>
            <?php endif; ?>
        </p>
        <form method="get" action="<?= e(url('liste')) ?>" class="liste-barre__tri" data-filtres>
            <?php foreach ($parametres as $cle => $valeur): ?>
                <?php if ($cle !== 'tri'): ?><input type="hidden" name="<?= e($cle) ?>" value="<?= e($valeur) ?>"><?php endif; ?>
            <?php endforeach; ?>
            <label for="tri">Trier par</label>
            <select class="selection" id="tri" name="tri">
                <?php foreach (Etudiants::TRIS as $cle => [$libelle]): ?>
                    <option value="<?= e($cle) ?>"<?= $filtres['tri'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn--secondaire btn--petit" type="submit">Trier</button></noscript>
        </form>
    </div>

    <?php if ($resultat['lignes'] === []): ?>
        <div class="liste-vide">
            <h2>Aucun étudiant ne correspond à ces filtres</h2>
            <p>Retirez un filtre, ou cherchez directement votre nom depuis l'accueil.</p>
            <a class="btn btn--secondaire" href="<?= e(url('liste')) ?>">Effacer les filtres</a>
        </div>
    <?php else: ?>
        <table class="liste">
            <caption class="masque-visuel">Effets académiques, page <?= $resultat['page'] ?> sur <?= $resultat['pages'] ?></caption>
            <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Filière</th>
                    <th scope="col">Année</th>
                    <th scope="col">Niveau</th>
                    <th scope="col">Documents</th>
                    <th scope="col"><span class="masque-visuel">Statut</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultat['lignes'] as $etudiant): ?>
                    <?php $retire = $etudiant['statut'] === 'retire'; ?>
                    <tr class="<?= $retire ? 'est-retire' : '' ?>">
                        <th scope="row">
                            <a href="<?= e(url('fiche/' . $etudiant['reference'])) ?>"><?php foreach (Recherche::segments((string) $etudiant['nom'], $mots) as [$texte, $surligne]): ?><?= $surligne ? '<mark>' . e($texte) . '</mark>' : e($texte) ?><?php endforeach; ?></a>
                        </th>
                        <td data-libelle="Filière"><?= e($etudiant['filiere'] ?? '—') ?></td>
                        <td data-libelle="Année"><?= e($etudiant['annee'] ?? '—') ?></td>
                        <td data-libelle="Niveau"><?= e($etudiant['niveau'] ?? '—') ?></td>
                        <td>
                            <span class="puces">
                                <?php foreach (Etudiants::effets($etudiant) as $effet): ?>
                                    <span class="puce<?= $retire ? ' puce--neutre' : '' ?>" title="<?= e($effet['complet']) ?>"><?= e($effet['court']) ?></span>
                                <?php endforeach; ?>
                            </span>
                        </td>
                        <td class="liste__statut">
                            <span class="statut <?= $retire ? 'statut--retire' : 'statut--disponible' ?>"><?= $retire ? 'Retiré' : 'Disponible' ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($resultat['pages'] > 1): ?>
            <?php
            $page = $resultat['page'];
            $pages = $resultat['pages'];
            $numeros = array_unique(array_filter(
                [1, 2, $page - 1, $page, $page + 1, $pages - 1, $pages],
                static fn (int $n): bool => $n >= 1 && $n <= $pages,
            ));
            sort($numeros);
            ?>
            <nav class="pagination" aria-label="Pages de la liste">
                <?php if ($page > 1): ?>
                    <a class="pagination__fleche" href="<?= e($lienPage($page - 1)) ?>" rel="prev"><?= icone('chevron-gauche', 'icone icone--petite') ?>Précédente</a>
                <?php endif; ?>
                <ol>
                    <?php $precedent = 0; ?>
                    <?php foreach ($numeros as $numero): ?>
                        <?php if ($numero - $precedent > 1): ?><li class="pagination__ellipse" aria-hidden="true">…</li><?php endif; ?>
                        <li>
                            <?php if ($numero === $page): ?>
                                <span aria-current="page"><?= $numero ?></span>
                            <?php else: ?>
                                <a href="<?= e($lienPage($numero)) ?>" aria-label="Page <?= $numero ?>"><?= $numero ?></a>
                            <?php endif; ?>
                        </li>
                        <?php $precedent = $numero; ?>
                    <?php endforeach; ?>
                </ol>
                <?php if ($page < $pages): ?>
                    <a class="pagination__fleche" href="<?= e($lienPage($page + 1)) ?>" rel="next">Suivante<?= icone('chevron-droite', 'icone icone--petite') ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
