/* Réglages : confirmation des actions sensibles et section courante dans le sommaire. */
(function () {
  "use strict";

  var Admin = window.Admin;

  Array.prototype.forEach.call(document.querySelectorAll("form[data-confirmer]"), function (formulaire) {
    formulaire.addEventListener("submit", function (evenement) {
      if (formulaire.dataset.confirme === "1") {
        return;
      }
      evenement.preventDefault();
      Admin.dialogue({
        titre: "Confirmer",
        texte: formulaire.getAttribute("data-confirmer"),
        confirmer: "Supprimer",
        danger: true,
      }).then(function (ok) {
        if (ok) {
          formulaire.dataset.confirme = "1";
          formulaire.submit();
        }
      });
    });
  });

  var liens = document.querySelectorAll(".reglages__nav a");
  if (!("IntersectionObserver" in window) || !liens.length) {
    return;
  }
  var observateur = new IntersectionObserver(
    function (entrees) {
      entrees.forEach(function (entree) {
        if (!entree.isIntersecting) {
          return;
        }
        Array.prototype.forEach.call(liens, function (lien) {
          lien.toggleAttribute("aria-current", lien.getAttribute("href") === "#" + entree.target.id);
        });
      });
    },
    { rootMargin: "-20% 0px -70% 0px" }
  );
  Array.prototype.forEach.call(document.querySelectorAll(".reglage"), function (section) {
    observateur.observe(section);
  });
})();
