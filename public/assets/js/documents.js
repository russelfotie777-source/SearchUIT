/* Documents : filtre instantané par nom, sans accent ni majuscule à respecter. */
(function () {
  "use strict";

  var champ = document.querySelector("[data-filtre-documents]");
  if (!champ) {
    return;
  }
  var aucun = document.querySelector(".documents__aucun");
  var groupes = document.querySelectorAll(".documents__groupe");

  function normaliser(texte) {
    return texte
      .normalize("NFD")
      .replace(/[̀-ͯ]/g, "")
      .toUpperCase()
      .replace(/[^A-Z0-9]+/g, " ")
      .trim();
  }

  champ.addEventListener("input", function () {
    var mots = normaliser(champ.value).split(" ").filter(Boolean);
    var visibles = 0;
    Array.prototype.forEach.call(groupes, function (groupe) {
      var dansGroupe = 0;
      Array.prototype.forEach.call(groupe.querySelectorAll(".document"), function (ligne) {
        var nom = " " + ligne.getAttribute("data-nom");
        var correspond = mots.every(function (mot) {
          return nom.indexOf(" " + mot) !== -1;
        });
        ligne.hidden = !correspond;
        if (correspond) {
          dansGroupe++;
        }
      });
      groupe.hidden = dansGroupe === 0;
      visibles += dansGroupe;
    });
    aucun.hidden = visibles > 0;
  });
})();
