<?php
/**
 * @var list<array<string, mixed>> $entrees
 */
$jours = [];
foreach ($entrees as $entree) {
    $jours[substr((string) $entree['cree_le'], 0, 10)][] = $entree;
}
$aujourdhui = date('Y-m-d');
$hier = date('Y-m-d', strtotime('-1 day'));
?>
<div class="admin-titre">
    <h1>Historique</h1>
</div>

<?php if ($entrees === []): ?>
    <div class="admin-vide">
        <?= icone('historique') ?>
        <h2>Rien pour l'instant</h2>
        <p>Chaque dépôt, publication de liste ou remise de documents sera noté ici, avec son auteur.</p>
    </div>
<?php else: ?>
    <?php foreach ($jours as $jour => $lignes): ?>
        <section class="historique">
            <h2><?= $jour === $aujourdhui ? 'Aujourd\'hui' : ($jour === $hier ? 'Hier' : e(ucfirst(date_fr($jour)))) ?></h2>
            <ol>
                <?php foreach ($lignes as $ligne): ?>
                    <li>
                        <time datetime="<?= e((string) $ligne['cree_le']) ?>"><?= e(date('H\hi', strtotime((string) $ligne['cree_le']))) ?></time>
                        <span class="historique__texte">
                            <strong><?= e($ligne['action']) ?></strong>
                            <?php if ($ligne['details']): ?><span><?= e($ligne['details']) ?></span><?php endif; ?>
                        </span>
                        <span class="historique__auteur"><?= e($ligne['auteur'] ?? 'Ligne de commande') ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
