/*
 * Recherche en direct : les résultats s'affichent pendant la saisie.
 * Sans JavaScript, le formulaire fonctionne normalement (page rechargée).
 */
(function () {
  "use strict";

  var formulaire = document.querySelector("[data-recherche]");
  if (!formulaire || !window.fetch) {
    return;
  }

  var saisie = formulaire.querySelector("input[name=q]");
  var effacer = formulaire.querySelector("[data-effacer]");
  var zone = document.getElementById("resultats");
  var api = formulaire.getAttribute("data-api");
  var titreInitial = document.title.replace(/^Recherche « .* » \| /, "");

  var minuterie = null;
  var minuterieChargement = null;
  var controleur = null;
  var derniere = saisie.value.trim();

  function mettreAJourAdresse(texte) {
    var adresse = new URL(window.location.href);
    if (texte) {
      adresse.searchParams.set("q", texte);
    } else {
      adresse.searchParams.delete("q");
    }
    history.replaceState(null, "", adresse);
    document.title = texte ? "Recherche « " + texte + " » | " + titreInitial : titreInitial;
  }

  function finChargement() {
    clearTimeout(minuterieChargement);
    formulaire.removeAttribute("data-chargement");
  }

  function chercher(texte) {
    if (controleur) {
      controleur.abort();
    }
    if (texte.replace(/[^\p{L}\p{N}]/gu, "").length < 2) {
      finChargement();
      zone.innerHTML = texte
        ? '<p class="resultats__consigne">Tapez au moins deux lettres de votre nom.</p>'
        : "";
      mettreAJourAdresse(texte);
      return;
    }

    controleur = new AbortController();
    clearTimeout(minuterieChargement);
    // L'indicateur n'apparaît que si la réponse tarde (connexion mobile lente).
    minuterieChargement = setTimeout(function () {
      formulaire.setAttribute("data-chargement", "true");
    }, 160);

    fetch(api + "?q=" + encodeURIComponent(texte), {
      headers: { "X-Requested-With": "fetch", Accept: "application/json" },
      signal: controleur.signal,
    })
      .then(function (reponse) {
        if (!reponse.ok) {
          throw new Error("HTTP " + reponse.status);
        }
        return reponse.json();
      })
      .then(function (donnees) {
        finChargement();
        zone.innerHTML = donnees.html;
        mettreAJourAdresse(texte);
      })
      .catch(function (erreur) {
        if (erreur.name === "AbortError") {
          return;
        }
        finChargement();
        zone.innerHTML =
          '<p class="resultats__consigne">La recherche n\'a pas abouti. Vérifiez votre connexion, puis réessayez.</p>';
      });
  }

  saisie.addEventListener("input", function () {
    effacer.hidden = saisie.value === "";
    var texte = saisie.value.trim();
    if (texte === derniere) {
      return;
    }
    derniere = texte;
    clearTimeout(minuterie);
    minuterie = setTimeout(function () {
      chercher(texte);
    }, 180);
  });

  formulaire.addEventListener("submit", function (evenement) {
    evenement.preventDefault();
    clearTimeout(minuterie);
    derniere = saisie.value.trim();
    chercher(derniere);
    // Sur téléphone, on ferme le clavier pour laisser voir les résultats.
    if (window.matchMedia("(max-width: 640px)").matches) {
      saisie.blur();
    }
  });

  effacer.addEventListener("click", function () {
    saisie.value = "";
    effacer.hidden = true;
    derniere = "";
    chercher("");
    saisie.focus();
  });

  // Flèches du clavier : de la saisie aux résultats, puis d'un résultat à l'autre.
  saisie.addEventListener("keydown", function (evenement) {
    if (evenement.key === "ArrowDown") {
      var premier = zone.querySelector(".resultat");
      if (premier) {
        evenement.preventDefault();
        premier.focus();
      }
    } else if (evenement.key === "Escape" && saisie.value) {
      evenement.preventDefault();
      effacer.click();
    }
  });

  zone.addEventListener("keydown", function (evenement) {
    if (evenement.key !== "ArrowDown" && evenement.key !== "ArrowUp") {
      return;
    }
    var liens = Array.prototype.slice.call(zone.querySelectorAll(".resultat"));
    var index = liens.indexOf(document.activeElement);
    if (index === -1) {
      return;
    }
    evenement.preventDefault();
    if (evenement.key === "ArrowDown" && index < liens.length - 1) {
      liens[index + 1].focus();
    } else if (evenement.key === "ArrowUp") {
      (index === 0 ? saisie : liens[index - 1]).focus();
    }
  });
})();
