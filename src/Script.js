
document.addEventListener("DOMContentLoaded", () => {
    const formulaire = document.querySelector("#form-filtres-projets");

    if (!formulaire) {
        return;
    }

    // Appliquer les filtres quand on clique sur Rechercher.
    formulaire.addEventListener("submit", (event) => {
        event.preventDefault();

        const donnees = new FormData(formulaire);
        const parametres = new URLSearchParams(donnees);

        window.location.href =
            formulaire.action + "?" + parametres.toString();
    });

    // Appliquer automatiquement les filtres quand on les change.
    formulaire.querySelectorAll("select").forEach((select) => {
        select.addEventListener("change", () => {
            formulaire.requestSubmit();
        });
    });

    // Réinitialiser les filtres.
    const reset = formulaire.querySelector(".bouton-reset");

    if (reset) {
        reset.addEventListener("click", (event) => {
            event.preventDefault();
            window.location.href = formulaire.action;
        });
    }
});
