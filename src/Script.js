console.log("JavaScript NWS chargé !");
document.addEventListener("DOMContentLoaded", () => {
    const menus = document.querySelectorAll(
        ".menu-specialite select, .menu-annee select"
    );

    menus.forEach((select) => {
        const conteneur = select.parentElement;
        const bouton = document.createElement("button");
        const liste = document.createElement("div");

        bouton.type = "button";
        bouton.className = "menu-personnalise-bouton";
        bouton.textContent = select.options[select.selectedIndex].text;

        liste.className = "menu-personnalise-liste";
        liste.hidden = true;

        Array.from(select.options).forEach((option) => {
            const choix = document.createElement("button");

            choix.type = "button";
            choix.className = "menu-personnalise-option";
            choix.textContent = option.text;

            choix.addEventListener("click", () => {
                select.value = option.value;
                bouton.textContent = option.text;
                liste.hidden = true;
                bouton.setAttribute("aria-expanded", "false");
            });

            liste.appendChild(choix);
        });

        bouton.setAttribute("aria-expanded", "false");

        bouton.addEventListener("click", () => {
            const ouvert = !liste.hidden;
            liste.hidden = ouvert;
            bouton.setAttribute("aria-expanded", String(!ouvert));
        });

        conteneur.appendChild(bouton);
        conteneur.appendChild(liste);
        conteneur.classList.add("menu-personnalise-actif");
    });

    document.addEventListener("click", (event) => {
        document.querySelectorAll(".menu-personnalise-liste").forEach((liste) => {
            if (!liste.parentElement.contains(event.target)) {
                liste.hidden = true;
                liste.parentElement
                    .querySelector(".menu-personnalise-bouton")
                    .setAttribute("aria-expanded", "false");
            }
        });
    });
});