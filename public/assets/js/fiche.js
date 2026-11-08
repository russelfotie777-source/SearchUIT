/* Page fiche : retour aux résultats et partage du lien. */
(function () {
  "use strict";

  // « Retour à la recherche » revient aux résultats si l'on vient de la recherche.
  var retour = document.querySelector("[data-retour]");
  if (retour && document.referrer) {
    try {
      var precedente = new URL(document.referrer);
      if (precedente.origin === window.location.origin && precedente.searchParams.get("q")) {
        retour.href = precedente.href;
        retour.lastChild.textContent = "Retour aux résultats";
      }
    } catch (e) {
      /* adresse précédente illisible : on garde le lien par défaut */
    }
  }

  // Partage : feuille de partage du téléphone (WhatsApp…), sinon copie du lien.
  var bouton = document.querySelector("[data-partager]");
  if (!bouton) {
    return;
  }
  var libelle = bouton.querySelector("[data-partager-libelle]");
  var texteInitial = libelle.textContent;

  function confirmer(message) {
    libelle.textContent = message;
    setTimeout(function () {
      libelle.textContent = texteInitial;
    }, 2200);
  }

  function copier() {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(window.location.href).then(
        function () { confirmer("Lien copié"); },
        function () { window.prompt("Copiez ce lien :", window.location.href); }
      );
    } else {
      window.prompt("Copiez ce lien :", window.location.href);
    }
  }

  if (!navigator.share) {
    libelle.textContent = texteInitial = "Copier le lien";
  }

  bouton.addEventListener("click", function () {
    if (navigator.share) {
      navigator.share({ title: bouton.getAttribute("data-titre"), url: window.location.href }).catch(function () {});
    } else {
      copier();
    }
  });
})();
