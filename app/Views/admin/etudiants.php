<?php
/**
 * @var array{nom: string, filiere: string, statut: string, tri: string} $filtres
 * @var array{total: int, lignes: list<array<string, mixed>>, page: int, pages: int} $resultat
 * @var list<array{code: string, nombre: int}> $filieres
 * @var int $total
 * @var int $retires
 * @var list<array<string, mixed>> $imports
 */
use App\Domain\Recherche;
use App\Repositories\Etudiants;

$base = array_filter(['recherche' => $filtres['nom'], 'filiere' => $filtres['filiere']], static fn ($v): bool => $v !== '');
$lienStatut = static fn (string $statut): string => url('admin/etudiants', $base + ['statut' => $statut === 'tous' ? null : $statut]);
$lienPage = static fn (int $page): string => url('admin/etudiants', $base + ['statut' => $filtres['statut'] === 'tous' ? null : $filtres['statut'], 'page' => $page > 1 ? $page : null]);
$mots = Recherche::mots($filtres['nom']);
?>
<div class="admin-titre">
    <h1>Étudiants</h1>
    <div class="admin-titre__actions">
        <button class="btn btn--primaire" type="button" data-action="ajouter-etudiant"><?= icone('plus', 'icone icone--petite') ?><span>Ajouter un étudiant</span></button>
    </div>
</div>

<p class="etudiants-resume">
    <?php if ($total === 0): ?>
        Aucune liste n'est en ligne. Déposez la liste Excel des effets dans <a href="<?= e(url('admin/fichiers')) ?>">Fichiers</a> pour la publier.
    <?php else: ?>
        <?= e(pluriel($total, 'étudiant')) ?> en ligne<?= $retires > 0 ? ', dont ' . e(pluriel($retires, 'marqué comme retiré', 'marqués comme retirés')) : '' ?>.
        Quand un étudiant vient chercher ses documents, marquez-les comme retirés&nbsp;: sa fiche l'indiquera aussitôt.
    <?php endif; ?>
</p>

<div class="etudiants-outils">
    <nav class="segments" aria-label="Statut">
        <?php foreach (['tous' => 'Tous', 'disponible' => 'Disponibles', 'retire' => 'Retirés'] as $cle => $libelle): ?>
            <a href="<?= e($lienStatut($cle)) ?>"<?= $filtres['statut'] === $cle ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="get" action="<?= e(url('admin/etudiants')) ?>" data-filtre-auto>
        <?php if ($filtres['nom'] !== ''): ?><input type="hidden" name="recherche" value="<?= e($filtres['nom']) ?>"><?php endif; ?>
        <?php if ($filtres['statut'] !== 'tous'): ?><input type="hidden" name="statut" value="<?= e($filtres['statut']) ?>"><?php endif; ?>
        <label class="masque-visuel" for="filtre-filiere">Filière</label>
        <select class="selection" id="filtre-filiere" name="filiere">
            <option value="">Toutes les filières</option>
            <?php foreach ($filieres as $filiere): ?>
                <option value="<?= e($filiere['code']) ?>"<?= $filtres['filiere'] === $filiere['code'] ? ' selected' : '' ?>><?= e($filiere['code']) ?> (<?= nombre($filiere['nombre']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn--secondaire btn--petit" type="submit">Filtrer</button></noscript>
    </form>
    <?php if ($filtres['nom'] !== ''): ?>
        <p class="etudiants-outils__recherche">
            <?= e(pluriel($resultat['total'], 'résultat')) ?> pour « <?= e($filtres['nom']) ?> »
            <a href="<?= e(url('admin/etudiants')) ?>">Effacer</a>
        </p>
    <?php endif; ?>
</div>

<?php if ($resultat['lignes'] === []): ?>
    <div class="admin-vide">
        <?= icone('etudiants') ?>
        <h2><?= $filtres['nom'] !== '' ? 'Aucun étudiant ne porte ce nom' : 'Aucun étudiant ici' ?></h2>
        <p><?= $filtres['nom'] !== '' ? 'Vérifiez l\'orthographe ou tapez seulement le nom de famille.' : 'Changez de filtre, ou publiez une liste depuis Fichiers.' ?></p>
    </div>
<?php else: ?>
    <div class="tableau-defilement">
        <table class="tableau tableau--etudiants">
            <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Filière</th>
                    <th scope="col">Année</th>
                    <th scope="col">Documents</th>
                    <th scope="col">Statut</th>
                    <th scope="col"><span class="masque-visuel">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultat['lignes'] as $etudiant): ?>
                    <?php
                    $retire = $etudiant['statut'] === 'retire';
                    $edition = [
                        'nom' => $etudiant['nom'], 'matricule' => $etudiant['matricule'], 'effets' => $etudiant['effets'],
                        'filiere' => $etudiant['filiere'], 'annee' => $etudiant['annee'], 'niveau' => $etudiant['niveau'],
                    ];
                    ?>
                    <tr data-etudiant-id="<?= (int) $etudiant['id'] ?>" data-nom="<?= e($etudiant['nom']) ?>" data-edition="<?= e(json_encode($edition, JSON_UNESCAPED_UNICODE)) ?>" class="<?= $retire ? 'est-retire' : '' ?>">
                        <th scope="row">
                            <span class="tableau__nom"><?php foreach (Recherche::segments((string) $etudiant['nom'], $mots) as [$texte, $surligne]): ?><?= $surligne ? '<mark>' . e($texte) . '</mark>' : e($texte) ?><?php endforeach; ?></span>
                            <small>Réf. <?= e($etudiant['reference']) ?><?= $etudiant['matricule'] ? ', matricule ' . e($etudiant['matricule']) : '' ?></small>
                        </th>
                        <td><?= e($etudiant['filiere'] ?? '—') ?></td>
                        <td><?= e($etudiant['annee'] ?? '—') ?></td>
                        <td>
                            <span class="puces">
                                <?php foreach (Etudiants::effets($etudiant) as $effet): ?>
                                    <span class="puce<?= $retire ? ' puce--neutre' : '' ?>" title="<?= e($effet['complet']) ?>"><?= e($effet['court']) ?></span>
                                <?php endforeach; ?>
                            </span>
                        </td>
                        <td data-statut>
                            <?php if ($retire): ?>
                                <span class="statut statut--retire">Retiré<?= $etudiant['retire_le'] ? ' le ' . e(date('d/m/Y', strtotime((string) $etudiant['retire_le']))) : '' ?></span>
                            <?php else: ?>
                                <span class="statut statut--disponible">Disponible</span>
                            <?php endif; ?>
                        </td>
                        <td class="tableau__actions">
                            <?php if ($retire): ?>
                                <button class="btn btn--discret btn--petit" type="button" data-action="remettre">Annuler le retrait</button>
                            <?php else: ?>
                                <button class="btn btn--secondaire btn--petit" type="button" data-action="retirer"><?= icone('coche', 'icone icone--petite') ?>Marquer comme retiré</button>
                            <?php endif; ?>
                            <details class="menu menu--droite">
                                <summary class="btn btn--discret btn--icone" aria-label="Autres actions pour <?= e($etudiant['nom']) ?>"><?= icone('menu-points') ?></summary>
                                <div class="menu__panneau" role="menu">
                                    <a class="menu__item" role="menuitem" href="<?= e(url('fiche/' . $etudiant['reference'])) ?>" target="_blank" rel="noopener"><?= icone('lien-externe') ?>Voir la fiche publique</a>
                                    <button class="menu__item" role="menuitem" type="button" data-action="modifier-etudiant"><?= icone('modifier') ?>Corriger</button>
                                    <div class="menu__separateur"></div>
                                    <button class="menu__item menu__item--danger" role="menuitem" type="button" data-action="supprimer-etudiant"><?= icone('corbeille') ?>Retirer de la liste</button>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($resultat['pages'] > 1): ?>
        <nav class="pagination" aria-label="Pages">
            <?php if ($resultat['page'] > 1): ?>
                <a class="pagination__fleche" href="<?= e($lienPage($resultat['page'] - 1)) ?>"><?= icone('chevron-gauche', 'icone icone--petite') ?>Précédente</a>
            <?php endif; ?>
            <ol><li><span aria-current="page"><?= $resultat['page'] ?> / <?= $resultat['pages'] ?></span></li></ol>
            <?php if ($resultat['page'] < $resultat['pages']): ?>
                <a class="pagination__fleche" href="<?= e($lienPage($resultat['page'] + 1)) ?>">Suivante<?= icone('chevron-droite', 'icone icone--petite') ?></a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php if ($imports !== []): ?>
    <section class="imports" aria-labelledby="titre-imports">
        <h2 id="titre-imports">Listes publiées</h2>
        <p>Chaque liste publiée depuis Fichiers apparaît ici. Annuler une publication retire ses étudiants de la recherche, sans supprimer le fichier.</p>
        <div class="tableau-defilement">
            <table class="tableau">
                <thead>
                    <tr>
                        <th scope="col">Publiée</th>
                        <th scope="col">Fichier</th>
                        <th scope="col">Type</th>
                        <th scope="col">Étudiants en ligne</th>
                        <th scope="col"><span class="masque-visuel">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($imports as $import): ?>
                        <tr data-import-id="<?= (int) $import['id'] ?>" data-nom="<?= e($import['nom_source']) ?>">
                            <td><?= e(date_relative((string) $import['cree_le'])) ?><?= $import['auteur'] ? '<small>par ' . e($import['auteur']) . '</small>' : '' ?></td>
                            <th scope="row"><?= e($import['nom_source']) ?></th>
                            <td><?= $import['mode'] === 'remplacement' ? 'Remplacement' : 'Ajout' ?></td>
                            <td><?= $import['annule_le'] ? '<span class="statut statut--retire">Annulée</span>' : e(nombre((int) $import['en_ligne'])) ?></td>
                            <td class="tableau__actions">
                                <?php if (!$import['annule_le'] && (int) $import['en_ligne'] > 0): ?>
                                    <button class="btn btn--discret btn--petit" type="button" data-action="annuler-import">Annuler la publication</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
