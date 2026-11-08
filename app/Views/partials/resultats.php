<?php
/**
 * @var string $saisie
 * @var array{total: int, resultats: list<array<string, mixed>>, mots: list<string>, proches: bool} $recherche
 */
use App\Domain\Recherche;

$mots = $recherche['mots'];
$total = $recherche['total'];
$lignes = $recherche['resultats'];
$tropCourt = $mots === [] || mb_strlen(implode('', $mots)) < Recherche::LONGUEUR_MIN;
?>
<?php if ($tropCourt): ?>
    <p class="resultats__consigne">Tapez au moins deux lettres de votre nom.</p>
<?php elseif ($total > 0): ?>
    <div class="resultats__entete">
        <h2 class="resultats__compte"><?= e(pluriel($total, 'ligne')) ?> pour « <?= e($saisie) ?> »</h2>
        <?php if ($total > count($lignes)): ?>
            <p>Seules les <?= count($lignes) ?> premières sont affichées. Ajoutez votre prénom pour affiner.</p>
        <?php elseif ($total > 1): ?>
            <p>Ouvrez votre ligne pour voir le détail et télécharger la fiche.</p>
        <?php endif; ?>
    </div>
    <ol class="resultats__liste">
        <?php foreach ($lignes as $i => $etudiant): ?>
            <?= App\Core\View::rendre('partials/resultat', ['etudiant' => $etudiant, 'mots' => $mots, 'rang' => $i]) ?>
        <?php endforeach; ?>
    </ol>
<?php else: ?>
    <div class="resultats__vide">
        <h2 class="resultats__compte">Aucun nom ne correspond à « <?= e($saisie) ?> »</h2>
        <p>Vérifiez l'orthographe, ou tapez seulement votre nom de famille.</p>

        <?php if ($recherche['proches']): ?>
            <h3 class="resultats__proches">Noms proches sur la liste</h3>
            <ol class="resultats__liste">
                <?php foreach ($lignes as $i => $etudiant): ?>
                    <?= App\Core\View::rendre('partials/resultat', ['etudiant' => $etudiant, 'mots' => [], 'rang' => $i]) ?>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <p class="resultats__note">
            <?= icone('info', 'icone icone--petite') ?>
            Si votre nom n'apparaît pas, aucun document à votre nom n'est enregistré pour le moment. Renseignez-vous au service de la scolarité.
        </p>
    </div>
<?php endif; ?>
