<?php
/**
 * @var array<string, string> $site
 * @var string $adresse
 * @var string $adresseConfiguree
 * @var string $qr
 * @var array<string, array{0: string, 1: string}> $legende
 * @var array<string, int> $codesPresents
 * @var list<array<string, mixed>> $comptes
 * @var array<string, mixed> $utilisateur
 */
use App\Domain\Effets;

$champ = static function (string $nom, string $libelle, string $valeur, string $aide = '', string $type = 'text', string $exemple = ''): string {
    $id = 'reglage-' . $nom;
    $saisie = $type === 'textarea'
        ? '<textarea class="saisie" id="' . $id . '" name="' . e($nom) . '" rows="3" placeholder="' . e($exemple) . '">' . e($valeur) . '</textarea>'
        : '<input class="saisie" id="' . $id . '" name="' . e($nom) . '" type="' . e($type) . '" value="' . e($valeur) . '" placeholder="' . e($exemple) . '">';
    return '<div class="champ"><label for="' . $id . '">' . e($libelle) . '</label>' . $saisie
        . ($aide !== '' ? '<p class="champ__aide">' . e($aide) . '</p>' : '') . '</div>';
};
?>
<div class="admin-titre">
    <h1>Réglages</h1>
</div>

<div class="reglages">
    <nav class="reglages__nav" aria-label="Sections des réglages">
        <a href="#retrait">Retrait des documents</a>
        <a href="#affiche">Affiche QR code</a>
        <a href="#codes">Codes des documents</a>
        <a href="#compte">Mon mot de passe</a>
        <a href="#comptes">Comptes de la scolarité</a>
    </nav>

    <div class="reglages__sections">
        <section class="reglage" id="retrait" aria-labelledby="titre-retrait">
            <header>
                <h2 id="titre-retrait">Retrait des documents</h2>
                <p>Ces informations s'affichent sur la page d'accueil, sur chaque fiche d'état et sur l'affiche. Laissez vide ce que vous ne souhaitez pas afficher.</p>
            </header>
            <form class="reglage__formulaire" method="post" action="<?= e(url('admin/reglages/infos')) ?>">
                <?= csrf_champ() ?>
                <div class="reglage__grille">
                    <?= $champ('retrait_lieu', 'Lieu de retrait', $site['retrait_lieu'], '', 'text', 'Par exemple : bâtiment administratif, bureau de la scolarité') ?>
                    <?= $champ('retrait_pieces', 'À présenter au guichet', $site['retrait_pieces'], '', 'text', 'Par exemple : la fiche d\'état et une pièce d\'identité') ?>
                    <?= $champ('retrait_horaires', 'Horaires', $site['retrait_horaires'], 'Une ligne par plage horaire.', 'textarea', "Par exemple :\nlundi au vendredi, 8h à 15h") ?>
                    <div class="reglage__pile">
                        <?= $champ('contact_telephone', 'Téléphone', $site['contact_telephone'], '', 'tel') ?>
                        <?= $champ('contact_email', 'Adresse e-mail', $site['contact_email'], '', 'email') ?>
                    </div>
                    <?= $champ('etablissement', 'Établissement', $site['etablissement']) ?>
                    <?= $champ('service', 'Service', $site['service']) ?>
                    <div class="reglage__large">
                        <?= $champ('adresse_site', 'Adresse du site', $adresseConfiguree, 'Utilisée par le QR code et les fiches PDF. Laissez vide pour la détecter automatiquement (actuellement ' . $adresse . ').', 'url', 'https://effets.iutdouala.cm') ?>
                    </div>
                </div>
                <button class="btn btn--primaire" type="submit">Enregistrer</button>
            </form>
        </section>

        <section class="reglage" id="affiche" aria-labelledby="titre-affiche">
            <header>
                <h2 id="titre-affiche">Affiche QR code</h2>
                <p>Imprimez l'affiche et posez-la au tableau d'affichage : les étudiants scannent le code avec leur téléphone et arrivent directement sur la recherche.</p>
            </header>
            <div class="reglage__affiche">
                <div class="reglage__qr"><?= $qr ?></div>
                <div>
                    <p>Ce code ouvre <a href="<?= e($adresse) ?>" target="_blank" rel="noopener"><?= e($adresse) ?></a></p>
                    <div class="reglage__boutons">
                        <a class="btn btn--primaire" href="<?= e(url('admin/reglages/affiche')) ?>" target="_blank" rel="noopener"><?= icone('imprimer', 'icone icone--petite') ?>Ouvrir l'affiche à imprimer</a>
                        <a class="btn btn--secondaire" href="<?= e(url('admin/reglages/qr.svg')) ?>" download><?= icone('telecharger', 'icone icone--petite') ?>QR code seul</a>
                    </div>
                    <p class="champ__aide">L'affiche est un PDF au format A4. Le QR code seul (SVG) s'agrandit sans perte pour une banderole.</p>
                </div>
            </div>
        </section>

        <section class="reglage" id="codes" aria-labelledby="titre-codes">
            <header>
                <h2 id="titre-codes">Codes des documents</h2>
                <p>Sur les listes, les documents sont notés en abrégé. Le libellé court apparaît dans les résultats, le libellé complet sur les fiches. Complétez les codes dont le sens n'est pas encore connu.</p>
            </header>
            <form class="reglage__formulaire" method="post" action="<?= e(url('admin/reglages/legende')) ?>">
                <?= csrf_champ() ?>
                <div class="tableau-defilement">
                    <table class="tableau tableau--legende">
                        <thead>
                            <tr>
                                <th scope="col">Code</th>
                                <th scope="col">Libellé court</th>
                                <th scope="col">Libellé complet</th>
                                <th scope="col">Sur la liste</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (Effets::LEGENDE as $code => [$courtDefaut, $completDefaut]): ?>
                                <?php [$court, $complet] = $legende[$code]; $aCompleter = $complet === $court; ?>
                                <tr class="<?= $aCompleter ? 'est-a-completer' : '' ?>">
                                    <th scope="row"><span class="puce puce--neutre"><?= e($code) ?></span></th>
                                    <td><input class="saisie saisie--compacte" name="legende[<?= e($code) ?>][court]" value="<?= e($court) ?>" aria-label="Libellé court de <?= e($code) ?>" maxlength="20"></td>
                                    <td><input class="saisie saisie--compacte" name="legende[<?= e($code) ?>][complet]" value="<?= e($aCompleter ? '' : $complet) ?>" placeholder="<?= $aCompleter ? 'Sens à préciser' : '' ?>" aria-label="Libellé complet de <?= e($code) ?>" maxlength="120"></td>
                                    <td><?= ($codesPresents[$code] ?? 0) > 0 ? e(pluriel($codesPresents[$code], 'étudiant')) : '<span class="tableau__absent">Aucun</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button class="btn btn--primaire" type="submit">Enregistrer la légende</button>
            </form>
        </section>

        <section class="reglage" id="compte" aria-labelledby="titre-compte">
            <header>
                <h2 id="titre-compte">Mon mot de passe</h2>
                <p>Connecté en tant que <?= e($utilisateur['nom'] ?? '') ?> (identifiant « <?= e($utilisateur['identifiant'] ?? '') ?> »).</p>
            </header>
            <form class="reglage__formulaire reglage__formulaire--etroit" method="post" action="<?= e(url('admin/reglages/mot-de-passe')) ?>">
                <?= csrf_champ() ?>
                <div class="champ"><label for="mdp-actuel">Mot de passe actuel</label><input class="saisie" id="mdp-actuel" name="actuel" type="password" autocomplete="current-password" required></div>
                <div class="champ"><label for="mdp-nouveau">Nouveau mot de passe</label><input class="saisie" id="mdp-nouveau" name="nouveau" type="password" autocomplete="new-password" minlength="10" required><p class="champ__aide">Au moins 10 caractères.</p></div>
                <div class="champ"><label for="mdp-confirmation">Confirmer le nouveau mot de passe</label><input class="saisie" id="mdp-confirmation" name="confirmation" type="password" autocomplete="new-password" required></div>
                <button class="btn btn--primaire" type="submit">Changer le mot de passe</button>
            </form>
        </section>

        <section class="reglage" id="comptes" aria-labelledby="titre-comptes">
            <header>
                <h2 id="titre-comptes">Comptes de la scolarité</h2>
                <p>Chaque personne qui dépose des listes ou remet des documents peut avoir son propre compte : l'historique indique alors qui a fait quoi.</p>
            </header>
            <ul class="comptes">
                <?php foreach ($comptes as $compte): ?>
                    <li>
                        <span class="admin-compte__avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $compte['nom'], 0, 1))) ?></span>
                        <span class="comptes__texte">
                            <strong><?= e($compte['nom']) ?><?= (int) $compte['id'] === (int) ($utilisateur['id'] ?? 0) ? ' (vous)' : '' ?></strong>
                            <small><?= e($compte['identifiant']) ?><?= $compte['derniere_connexion'] ? ', dernière connexion ' . e(mb_strtolower(date_relative((string) $compte['derniere_connexion']))) : ', jamais connecté' ?></small>
                        </span>
                        <?php if ((int) $compte['id'] !== (int) ($utilisateur['id'] ?? 0)): ?>
                            <form method="post" action="<?= e(url('admin/reglages/comptes/' . $compte['id'] . '/supprimer')) ?>" data-confirmer="Supprimer le compte de <?= e($compte['nom']) ?> ? Cette personne ne pourra plus se connecter.">
                                <?= csrf_champ() ?>
                                <button class="btn btn--discret btn--petit comptes__supprimer" type="submit">Supprimer</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <details class="reglage__ajout">
                <summary class="btn btn--secondaire"><?= icone('plus', 'icone icone--petite') ?>Ajouter un compte</summary>
                <form class="reglage__formulaire reglage__formulaire--etroit" method="post" action="<?= e(url('admin/reglages/comptes')) ?>">
                    <?= csrf_champ() ?>
                    <div class="champ"><label for="compte-nom">Nom de la personne</label><input class="saisie" id="compte-nom" name="nom" required></div>
                    <div class="champ"><label for="compte-identifiant">Identifiant</label><input class="saisie" id="compte-identifiant" name="identifiant" autocapitalize="none" spellcheck="false" required></div>
                    <div class="champ"><label for="compte-mdp">Mot de passe provisoire</label><input class="saisie" id="compte-mdp" name="mot_de_passe" type="text" minlength="10" autocomplete="off" required><p class="champ__aide">Au moins 10 caractères. La personne pourra le changer ensuite.</p></div>
                    <button class="btn btn--primaire" type="submit">Créer le compte</button>
                </form>
            </details>
        </section>
    </div>
</div>
