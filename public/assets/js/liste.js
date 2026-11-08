/* Liste : les filtres s'appliquent dès qu'ils changent, sans bouton à cliquer. */
(function () {
  "use strict";

  // Les champs vides n'encombrent pas l'adresse de la page.
  function envoyer(formulaire) {
    Array.prototype.forEach.call(formulaire.elements, function (champ) {
      if (champ.name && champ.value === "") {
        champ.disabled = true;
      }
    });
    formulaire.submit();
  }

  var formulaires = document.querySelectorAll("[data-filtres]");
  Array.prototype.forEach.call(formulaires, function (formulaire) {
    var bouton = formulaire.querySelector(".filtres__valider");
    if (bouton) {
      bouton.hidden = true;
    }

    Array.prototype.forEach.call(formulaire.querySelectorAll("select"), function (liste) {
      liste.addEventListener("change", function () {
        envoyer(formulaire);
      });
    });

    var nom = formulaire.querySelector("input[type=search]");
    if (nom) {
      var minuterie = null;
      var initial = nom.value;
      nom.addEventListener("input", function () {
        clearTimeout(minuterie);
        minuterie = setTimeout(function () {
          if (nom.value.trim() !== initial.trim()) {
            envoyer(formulaire);
          }
        }, 650);
      });
      // Après rechargement, le curseur revient à la fin du texte tapé.
      if (initial) {
        nom.focus();
        nom.setSelectionRange(nom.value.length, nom.value.length);
      }
    }

    formulaire.addEventListener("submit", function (evenement) {
      evenement.preventDefault();
      envoyer(formulaire);
    });
  });
})();
