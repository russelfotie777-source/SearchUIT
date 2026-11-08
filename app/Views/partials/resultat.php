<?php
/**
 * Une ligne de résultat : nom surligné, filière, année, effets, statut.
 *
 * @var array<string, mixed> $etudiant
 * @var list<string> $mots
 * @var int $rang
 */
use App\Domain\Recherche;
use App\Repositories\Etudiants;

$retire = $etudiant['statut'] === 'retire';
$effets = Etudiants::effets($etudiant);
?>
<li>
    <a class="resultat<?= $retire ? ' est-retire' : '' ?>" href="<?= e(url('fiche/' . $etudiant['reference'])) ?>" style="--rang: <?= (int) $rang ?>">
        <span class="resultat__nom"><?php foreach (Recherche::segments((string) $etudiant['nom'], $mots) as [$texte, $surligne]): ?><?= $surligne ? '<mark>' . e($texte) . '</mark>' : e($texte) ?><?php endforeach; ?></span>

        <span class="resultat__details">
            <?php if (!empty($etudiant['filiere'])): ?><span><?= e($etudiant['filiere']) ?></span><?php endif; ?>
            <?php if (!empty($etudiant['annee'])): ?><span><?= e($etudiant['annee']) ?></span><?php endif; ?>
            <?php if (!empty($etudiant['niveau'])): ?><span><?= e($etudiant['niveau']) ?></span><?php endif; ?>
        </span>

        <?php if ($effets !== []): ?>
            <span class="puces resultat__effets">
                <?php foreach ($effets as $effet): ?>
                    <span class="puce<?= $retire ? ' puce--neutre' : '' ?>" title="<?= e($effet['complet']) ?>"><?= e($effet['court']) ?></span>
                <?php endforeach; ?>
            </span>
        <?php endif; ?>

        <span class="statut resultat__statut <?= $retire ? 'statut--retire' : 'statut--disponible' ?>"><?= $retire ? 'Retiré' : 'Disponible' ?></span>
        <?= icone('chevron-droite', 'icone resultat__fleche') ?>
    </a>
</li>
