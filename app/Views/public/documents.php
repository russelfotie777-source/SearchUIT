<?php
/**
 * @var list<array{dossier: ?array<string, mixed>, fichiers: list<array<string, mixed>>}> $groupes
 */
$total = array_sum(array_map(static fn (array $g): int => count($g['fichiers']), $groupes));
?>
<section class="conteneur titre-page">
    <h1>Documents</h1>
    <p>Listes officielles, procès-verbaux et communiqués publiés par le service de la scolarité.</p>
</section>

<div class="conteneur documents">
    <?php if ($groupes === []): ?>
        <div class="liste-vide">
            <h2>Aucun document publié pour le moment</h2>
            <p>Les procès-verbaux et les listes officielles apparaîtront ici dès leur publication.</p>
            <a class="btn btn--secondaire" href="<?= e(url('/')) ?>">Rechercher mon nom</a>
        </div>
    <?php else: ?>
        <div class="documents__outils">
            <div class="filtres__nom">
                <label class="masque-visuel" for="filtre-documents">Rechercher un document</label>
                <?= icone('recherche', 'icone icone--petite') ?>
                <input class="saisie" id="filtre-documents" type="search" placeholder="Rechercher parmi <?= e(pluriel($total, 'document')) ?>" autocomplete="off" data-filtre-documents>
            </div>
            <?php if (count($groupes) > 1): ?>
                <nav class="documents__dossiers" aria-label="Dossiers">
                    <?php foreach ($groupes as $groupe): ?>
                        <?php $id = $groupe['dossier'] === null ? 'autres' : (string) $groupe['dossier']['id']; ?>
                        <a href="#dossier-<?= e($id) ?>"><?= icone('dossier', 'icone icone--petite') ?><?= e($groupe['dossier']['nom'] ?? 'Autres documents') ?><span><?= count($groupe['fichiers']) ?></span></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>

        <?php foreach ($groupes as $groupe): ?>
            <?php $id = $groupe['dossier'] === null ? 'autres' : (string) $groupe['dossier']['id']; ?>
            <section class="documents__groupe" id="dossier-<?= e($id) ?>" aria-labelledby="titre-dossier-<?= e($id) ?>">
                <h2 id="titre-dossier-<?= e($id) ?>">
                    <?= e($groupe['dossier']['nom'] ?? 'Autres documents') ?>
                    <span><?= e(pluriel(count($groupe['fichiers']), 'document')) ?></span>
                </h2>
                <ul class="documents__liste">
                    <?php foreach ($groupe['fichiers'] as $fichier): ?>
                        <?= App\Core\View::rendre('partials/document', ['fichier' => $fichier]) ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>

        <p class="documents__aucun" role="status" hidden>Aucun document ne porte ce nom.</p>
    <?php endif; ?>
</div>
