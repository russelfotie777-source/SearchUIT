<?php
/**
 * @var int|null $dossierId
 * @var list<array<string, mixed>> $chemin
 * @var list<array{id: int, nom: string, niveau: int}> $arborescence
 * @var int $tailleMax
 */
use App\Storage\Stockage;

$nomDossier = $chemin === [] ? 'Fichiers' : (string) end($chemin)['nom'];
$accepte = implode(',', array_map(static fn (string $ext): string => '.' . $ext, array_keys(Stockage::TYPES)));
?>
<?php if (!empty($introuvable)): ?>
    <div class="admin-vide">
        <?= icone('dossier') ?>
        <h2>Ce dossier n'existe plus</h2>
        <p>Il a peut-être été supprimé ou placé dans la corbeille.</p>
        <a class="btn btn--secondaire" href="<?= e(url('admin/fichiers')) ?>">Revenir aux fichiers</a>
    </div>
<?php else: ?>
    <div class="drive" data-drive
         data-dossier="<?= e((string) ($dossierId ?? '')) ?>"
         data-nom-dossier="<?= e($nomDossier) ?>"
         data-taille-max="<?= (int) $tailleMax ?>"
         data-extensions="<?= e(implode(',', array_keys(Stockage::TYPES))) ?>"
         data-recherche="<?= e($recherche) ?>">
        <div data-drive-contenu>
            <?= App\Core\View::rendre('admin/partials/drive', get_defined_vars()) ?>
        </div>
        <input type="file" multiple hidden accept="<?= e($accepte) ?>" data-selecteur>
        <script type="application/json" data-arborescence><?= json_encode($arborescence, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    </div>

    <div class="depot-voile" data-depot-voile hidden>
        <div class="depot-voile__cadre">
            <?= icone('nuage-depot') ?>
            <p>Déposez vos fichiers pour les ajouter à <strong data-depot-cible><?= e($nomDossier) ?></strong></p>
        </div>
    </div>

    <section class="televersements" data-televersements hidden aria-labelledby="titre-televersements">
        <header class="televersements__entete">
            <h2 id="titre-televersements" data-televersements-titre>Importation</h2>
            <button class="btn btn--discret btn--icone" type="button" aria-label="Réduire" aria-expanded="true" data-televersements-replier><?= icone('chevron-bas') ?></button>
            <button class="btn btn--discret btn--icone" type="button" aria-label="Fermer" data-televersements-fermer><?= icone('fermer') ?></button>
        </header>
        <ul class="televersements__liste" data-televersements-liste></ul>
    </section>
<?php endif; ?>
