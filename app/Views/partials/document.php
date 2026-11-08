<?php
/**
 * Une ligne de document publié.
 *
 * @var array<string, mixed> $fichier
 */
use App\Repositories\Fichiers;
use App\Storage\Stockage;

$extension = (string) $fichier['extension'];
$adresse = url('documents/' . $fichier['id'] . '/' . Fichiers::slug((string) $fichier['nom']));
$apercu = in_array($extension, Stockage::APERCU, true);
$nomAffiche = preg_replace('/\.' . preg_quote($extension, '/') . '$/i', '', (string) $fichier['nom']);
?>
<li class="document" data-nom="<?= e(App\Domain\Texte::pourRecherche((string) $fichier['nom'])) ?>">
    <span class="type-fichier type-fichier--<?= e(Stockage::famille($extension)) ?>" aria-hidden="true">
        <?= icone(Stockage::famille($extension) === 'tableur' ? 'fichier-tableur' : 'fichier-texte') ?>
    </span>
    <div class="document__texte">
        <a class="document__nom" href="<?= e($adresse) ?>"<?= $apercu ? ' target="_blank" rel="noopener"' : '' ?>><?= e($nomAffiche) ?></a>
        <span class="document__meta">
            <span><?= e(Stockage::libelleType($extension)) ?></span>
            <span><?= e(taille_lisible((int) $fichier['taille'])) ?></span>
            <span>Publié le <?= e(date_fr((string) $fichier['cree_le'])) ?></span>
            <?php if (!empty($fichier['sous_dossier'])): ?><span><?= e($fichier['sous_dossier']) ?></span><?php endif; ?>
        </span>
    </div>
    <a class="btn btn--secondaire btn--petit document__telecharger" href="<?= e($adresse . '?telecharger=1') ?>" download aria-label="Télécharger <?= e($nomAffiche) ?>">
        <?= icone('telecharger', 'icone icone--petite') ?><span>Télécharger</span>
    </a>
</li>
