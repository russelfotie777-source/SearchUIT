<?php
/**
 * @var string $saisie
 * @var array<string, mixed>|null $recherche
 * @var array{total: int, disponibles: int, filieres: int, codes: array<string, int>} $statistiques
 * @var list<array{code: string, court: string, complet: string}> $legende
 * @var array<string, string> $site
 */

// Les relevés de semestre (S1 à S6) et de niveau se lisent mieux regroupés.
$groupes = [];
foreach ($legende as $ligne) {
    if (preg_match('/^S[1-6]$/', $ligne['code'])) {
        $groupes['S'][] = $ligne;
    } elseif (preg_match('/^N[1-3]$/', $ligne['code'])) {
        $groupes['N'][] = $ligne;
    } else {
        $groupes[$ligne['code']] = [$ligne];
    }
}
$miseAJour = $site['liste_mise_a_jour'] ?? '';
?>
<section class="accueil">
    <div class="conteneur accueil__grille">
        <div class="accueil__principal">
            <h1 class="accueil__titre">Vos effets académiques sont-ils prêts&nbsp;?</h1>
            <p class="accueil__intro">Relevés de notes, attestations de réussite, certificats&nbsp;: tapez votre nom pour savoir ce qui vous attend au service de la scolarité.</p>

            <form class="champ-recherche" role="search" action="<?= e(url('/')) ?>" method="get" data-recherche data-api="<?= e(url('api/recherche')) ?>">
                <label class="masque-visuel" for="q">Nom et prénom, ou matricule</label>
                <?= icone('recherche', 'icone champ-recherche__loupe') ?>
                <input class="champ-recherche__saisie" id="q" name="q" type="search" value="<?= e($saisie) ?>"
                       placeholder="Nom et prénom, ou matricule" autocomplete="off" autocapitalize="characters"
                       spellcheck="false" enterkeyhint="search" aria-controls="resultats" aria-describedby="etat-liste"
                       <?= $saisie === '' ? 'autofocus' : '' ?>>
                <button class="champ-recherche__effacer" type="button" aria-label="Effacer la recherche" data-effacer<?= $saisie === '' ? ' hidden' : '' ?>>
                    <?= icone('fermer') ?>
                </button>
                <button class="btn btn--primaire champ-recherche__bouton" type="submit">Rechercher</button>
                <span class="champ-recherche__progression" aria-hidden="true"></span>
            </form>

            <p class="accueil__etat" id="etat-liste">
                <?php if ($statistiques['total'] > 0): ?>
                    La liste compte <?= e(pluriel($statistiques['total'], 'nom')) ?><?php if ($miseAJour !== ''): ?>, mise à jour le <?= e(date_fr($miseAJour)) ?><?php endif; ?>.
                <?php else: ?>
                    La liste n'a pas encore été publiée par la scolarité.
                <?php endif; ?>
            </p>

            <div class="resultats" id="resultats" aria-live="polite">
                <?php if ($recherche !== null): ?>
                    <?= App\Core\View::rendre('partials/resultats', ['saisie' => $saisie, 'recherche' => $recherche]) ?>
                <?php endif; ?>
            </div>
        </div>

        <aside class="accueil__aparte" aria-labelledby="titre-retrait">
            <h2 id="titre-retrait">Récupérer vos documents</h2>
            <ol class="etapes-retrait">
                <li>
                    <strong>Trouvez votre nom</strong>
                    <span>Tapez votre nom de famille, puis votre prénom si plusieurs lignes s'affichent.</span>
                </li>
                <li>
                    <strong>Téléchargez votre fiche d'état</strong>
                    <span>Elle récapitule les documents disponibles à votre nom.</span>
                </li>
                <li>
                    <strong>Passez au service de la scolarité</strong>
                    <span><?= $site['retrait_pieces'] !== '' ? e('Munissez-vous de : ' . $site['retrait_pieces']) : 'Présentez votre fiche d\'état au guichet.' ?></span>
                </li>
            </ol>

            <?php if ($site['retrait_lieu'] !== '' || $site['retrait_horaires'] !== ''): ?>
                <dl class="pratique">
                    <?php if ($site['retrait_lieu'] !== ''): ?>
                        <div>
                            <dt><?= icone('lieu', 'icone icone--petite') ?>Lieu</dt>
                            <dd><?= e($site['retrait_lieu']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($site['retrait_horaires'] !== ''): ?>
                        <div>
                            <dt><?= icone('horloge', 'icone icone--petite') ?>Horaires</dt>
                            <dd><?= nl2br(e($site['retrait_horaires'])) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php if ($groupes !== []): ?>
    <section class="legende" aria-labelledby="titre-legende">
        <div class="conteneur">
            <div class="legende__entete">
                <h2 id="titre-legende">Les codes de la liste</h2>
                <p>La scolarité note les documents en abrégé. Voici ce que chaque code désigne.</p>
            </div>
            <dl class="legende__grille">
                <?php foreach ($groupes as $cle => $lignes): ?>
                    <div class="legende__ligne">
                        <dt>
                            <?php if (($cle === 'S' || $cle === 'N') && count($lignes) > 1): ?>
                                <span class="puce"><?= e($lignes[0]['court']) ?></span>
                                <span class="legende__a">à</span>
                                <span class="puce"><?= e(end($lignes)['court']) ?></span>
                            <?php else: ?>
                                <span class="puce"><?= e($lignes[0]['court']) ?></span>
                            <?php endif; ?>
                        </dt>
                        <dd>
                            <?php if ($cle === 'S' && count($lignes) > 1): ?>
                                Relevé de notes du semestre indiqué
                            <?php elseif ($cle === 'N' && count($lignes) > 1): ?>
                                Relevé de notes du niveau indiqué (ancien système)
                            <?php else: ?>
                                <?= e($lignes[0]['complet'] === $lignes[0]['court'] ? 'À préciser au guichet de la scolarité' : $lignes[0]['complet']) ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>
<?php endif; ?>
