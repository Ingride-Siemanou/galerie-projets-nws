
<?php
require_once __DIR__ . "/connexion.php";

function e($valeur): string
{
    return htmlspecialchars((string) ($valeur ?? ""), ENT_QUOTES, "UTF-8");
}

$erreur = "";
$succes = false;

$champs = [
    "titre" => "",
    "description" => "",
    "etudiant" => "",
    "annee" => (string) date("Y"),
    "promotion" => "",
    "specialite" => "",
    "type_livrable" => "",
    "contexte" => "",
    "objectifs" => "",
    "outils_technologies" => "",
    "lien_projet" => ""
];


$specialites = [
    "développement web",
    "communication graphique",
    "communication digital",
    "Marketing digital"
];


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($champs as $nom => $valeur) {
        $champs[$nom] = trim($_POST[$nom] ?? "");
    }

    if (
        $champs["titre"] === "" ||
        $champs["description"] === "" ||
        $champs["etudiant"] === "" ||
        $champs["specialite"] === "" ||
        $champs["type_livrable"] === "" ||
        !ctype_digit($champs["annee"]) ||
        (int) $champs["annee"] < 2000 ||
        (int) $champs["annee"] > 2100 ||
        !ctype_digit($champs["promotion"]) ||
        (int) $champs["promotion"] < 1 ||
        (int) $champs["promotion"] > 5
    ) {
        $erreur = "Merci de remplir correctement tous les champs obligatoires.";
    }

    if (
        $erreur === "" &&
        !in_array($champs["specialite"], $specialites, true)
    ) {
        $erreur = "La spécialité sélectionnée est invalide.";
    }

    if ($erreur === "" && $champs["lien_projet"] !== "") {
        $url = $champs["lien_projet"];
        $schema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (
            !filter_var($url, FILTER_VALIDATE_URL) ||
            !in_array($schema, ["http", "https"], true)
        ) {
            $erreur = "Le lien doit commencer par http:// ou https://.";
        }
    }

    if ($erreur === "") {
        try {
            $requete = $connexion->prepare(
                "INSERT INTO projets (
                    titre,
                    description,
                    etudiant,
                    annee,
                    promotion,
                    specialite,
                    type_livrable,
                    contexte,
                    objectifs,
                    outils_technologies,
                    lien_projet,
                    image_couverture,
                    statut
                ) VALUES (
                    :titre,
                    :description,
                    :etudiant,
                    :annee,
                    :promotion,
                    :specialite,
                    :type_livrable,
                    :contexte,
                    :objectifs,
                    :outils_technologies,
                    :lien_projet,
                    NULL,
                    'en_attente'
                )"
            );

            $requete->execute([
                "titre" => $champs["titre"],
                "description" => $champs["description"],
                "etudiant" => $champs["etudiant"],
                "annee" => (int) $champs["annee"],
                "promotion" => (int) $champs["promotion"],
                "specialite" => $champs["specialite"],
                "type_livrable" => $champs["type_livrable"],
                "contexte" => $champs["contexte"],
                "objectifs" => $champs["objectifs"],
                "outils_technologies" => $champs["outils_technologies"],
                "lien_projet" => $champs["lien_projet"]
            ]);

            $succes = true;

            foreach ($champs as $nom => $valeur) {
                $champs[$nom] = "";
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $erreur = "Impossible d'enregistrer la proposition. Réessaie plus tard.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Proposer un projet étudiant | NWS</title>

   <link rel="stylesheet" href="style.css?v=19">
<link rel="stylesheet" href="style-proposition.css?v=1">
    
</head>

<body>

<header class="site-header">
    <a href="index.php" aria-label="Retour à la galerie">
        <img
            src="images/logo-nws.jpeg"
            alt="Logo Normandie Web School"
        >
    </a>

    <a class="lien-retour-haut" href="index.php">
        ← Retour à la galerie
    </a>
</header>

<main class="proposition-conteneur">

    <h1>Proposer un projet étudiant</h1>

    <p class="proposition-intro">
        Tu as réalisé un projet pendant ta formation à la Normandie Web School ?
        Présente-le ici pour proposer son ajout à la galerie.
    </p>

    <p class="proposition-note">
        Chaque proposition doit être vérifiée par l'équipe administratrice
        avant sa publication.
    </p>

    <?php if ($succes): ?>
        <div class="proposition-message proposition-succes" role="status">
            <strong>Proposition envoyée !</strong>
            <p>
                Merci pour ta contribution. Ton projet est en attente de validation
                et n'est pas encore visible dans la galerie publique.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($erreur !== ""): ?>
        <div class="proposition-message proposition-erreur" role="alert">
            <?= e($erreur) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="proposition-formulaire">

        <label for="titre">Titre du projet *</label>
        <input
            type="text"
            id="titre"
            name="titre"
            maxlength="255"
            value="<?= e($champs["titre"]) ?>"
            required
        >

        <label for="description">Description du projet *</label>
        <textarea
            id="description"
            name="description"
            rows="5"
            required
        ><?= e($champs["description"]) ?></textarea>

        <label for="etudiant">Nom de l'étudiant ou du groupe *</label>
        <input
            type="text"
            id="etudiant"
            name="etudiant"
            value="<?= e($champs["etudiant"]) ?>"
            required
        >

        <label for="annee">Année de réalisation *</label>
        <input
            type="number"
            id="annee"
            name="annee"
            min="2000"
            max="2100"
            value="<?= e($champs["annee"]) ?>"
            required
        >

        <label for="promotion">Promotion *</label>
        <select id="promotion" name="promotion" required>
            <option value="">Choisir une année</option>

            <?php for ($numero = 1; $numero <= 5; $numero++): ?>
                <option
                    value="<?= $numero ?>"
                    <?= $champs["promotion"] === (string) $numero ? "selected" : "" ?>
                >
                    <?= $numero ?>e année
                </option>
            <?php endfor; ?>
        </select>

        <label for="specialite">Spécialité *</label>
        <select id="specialite" name="specialite" required>
            <option value="">Choisir une spécialité</option>

            <?php foreach ($specialites as $specialite): ?>
                <option
                    value="<?= e($specialite) ?>"
                    <?= $champs["specialite"] === $specialite ? "selected" : "" ?>
                >
                    <?= e($specialite) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="type_livrable">Type de livrable *</label>
        <input
            type="text"
            id="type_livrable"
            name="type_livrable"
            value="<?= e($champs["type_livrable"]) ?>"
            placeholder="Site web, affiche, vidéo..."
            required
        >

        <label for="contexte">Contexte du projet</label>
        <textarea
            id="contexte"
            name="contexte"
            rows="4"
        ><?= e($champs["contexte"]) ?></textarea>

        <label for="objectifs">Objectifs du projet</label>
        <textarea
            id="objectifs"
            name="objectifs"
            rows="4"
        ><?= e($champs["objectifs"]) ?></textarea>

        <label for="outils_technologies">Outils et technologies utilisés</label>
        <textarea
            id="outils_technologies"
            name="outils_technologies"
            rows="3"
            placeholder="HTML, CSS, JavaScript, Figma..."
        ><?= e($champs["outils_technologies"]) ?></textarea>

        <label for="lien_projet">Lien vers le projet ou le portfolio</label>
        <input
            type="url"
            id="lien_projet"
            name="lien_projet"
            value="<?= e($champs["lien_projet"]) ?>"
            placeholder="https://..."
        >

        <button type="submit">
            Envoyer ma proposition
        </button>

    </form>

    <a class="proposition-retour" href="index.php">
        ← Retour à la galerie
    </a>

</main>

</body>
</html>
