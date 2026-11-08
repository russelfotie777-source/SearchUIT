<?php
/**
 * @var array{dossiers: list<array<string, mixed>>, fichiers: list<array<string, mixed>>} $corbeille
 */
use App\Repositories\Drive;
use App\Storage\Stockage;

$vide = $corbeille['dossiers'] === [] && $corbeille['fichiers'] === [];
?>
<div class="admin-titre">
    <h1>Corbeille</h1>
    <?php if (!$vide): ?>
        <div class="admin-titre__actions">
            <button class="btn btn--secondaire" type="button" data-action="vider-corbeille"><?= icone('corbeille', 'icone icone--petite') ?>Vider la corbeille</button>
        </div>
    <?php endif; ?>
</div>

<?php if ($vide): ?>
    <div class="admin-vide">
        <?= icone('corbeille') ?>
        <h2>La corbeille est vide</h2>
        <p>Les fichiers et dossiers supprimés restent ici <?= Drive::JOURS_CORBEILLE ?> jours, le temps de changer d'avis.</p>
    </div>
<?php else: ?>
    <p class="corbeille__note"><?= icone('info', 'icone icone--petite') ?>Les éléments sont supprimés définitivement après <?= Drive::JOURS_CORBEILLE ?> jours. Tant qu'ils sont ici, ils ne sont plus visibles sur le site.</p>
    <ul class="corbeille">
        <?php foreach ($corbeille['dossiers'] as $dossier): ?>
            <li class="corbeille__ligne" data-dossier-id="<?= (int) $dossier['id'] ?>" data-nom="<?= e($dossier['nom']) ?>">
                <span class="type-fichier" aria-hidden="true"><?= icone('dossier') ?></span>
                <span class="corbeille__texte">
                    <strong><?= e($dossier['nom']) ?></strong>
                    <small>Dossier, supprimé <?= e(mb_strtolower(date_relative((string) $dossier['supprime_le']))) ?></small>
                </span>
                <button class="btn btn--secondaire btn--petit" type="button" data-action="restaurer-dossier"><?= icone('restaurer', 'icone icone--petite') ?>Restaurer</button>
                <button class="btn btn--discret btn--petit corbeille__detruire" type="button" data-action="detruire-dossier">Supprimer définitivement</button>
            </li>
        <?php endforeach; ?>
        <?php foreach ($corbeille['fichiers'] as $fichier): ?>
            <?php $extension = (string) $fichier['extension']; ?>
            <li class="corbeille__ligne" data-fichier-id="<?= (int) $fichier['id'] ?>" data-nom="<?= e($fichier['nom']) ?>">
                <span class="type-fichier type-fichier--<?= e(Stockage::famille($extension)) ?>" aria-hidden="true"><?= icone(Stockage::famille($extension) === 'tableur' ? 'fichier-tableur' : 'fichier-texte') ?></span>
                <span class="corbeille__texte">
                    <strong><?= e($fichier['nom']) ?></strong>
                    <small><?= e(Stockage::libelleType($extension)) ?>, <?= e(taille_lisible((int) $fichier['taille'])) ?>, supprimé <?= e(mb_strtolower(date_relative((string) $fichier['supprime_le']))) ?></small>
                </span>
                <button class="btn btn--secondaire btn--petit" type="button" data-action="restaurer-fichier"><?= icone('restaurer', 'icone icone--petite') ?>Restaurer</button>
                <button class="btn btn--discret btn--petit corbeille__detruire" type="button" data-action="detruire-fichier">Supprimer définitivement</button>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
