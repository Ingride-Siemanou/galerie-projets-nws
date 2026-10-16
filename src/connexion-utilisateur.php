<?php
session_start();

require_once __DIR__ . "/connexion.php";

$messageErreur = "";
$email = "";

// Si un visiteur est déjà connecté, il ne peut pas utiliser
// cette page pour accéder à l'espace administrateur.
if (
    isset($_SESSION["utilisateur_id"]) &&
    ($_SESSION["utilisateur_role"] ?? "") === "admin"
) {
    header("Location: admin/index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $motDePasse = $_POST["mot_de_passe"] ?? "";

    if ($email === "" || $motDePasse === "") {
        $messageErreur = "Renseigne ton e-mail et ton mot de passe.";
    } else {
        $requete = $connexion->prepare(
            "SELECT id, nom, prenom, email, mot_de_passe, role
             FROM utilisateurs
             WHERE email = :email
             LIMIT 1"
        );

        $requete->execute(["email" => $email]);
        $utilisateur = $requete->fetch(PDO::FETCH_ASSOC);

        if (
            $utilisateur &&
            $utilisateur["role"] === "admin" &&
            password_verify(
                $motDePasse,
                $utilisateur["mot_de_passe"]
            )
        ) {
            session_regenerate_id(true);

            $_SESSION["utilisateur_id"] = (int) $utilisateur["id"];
            $_SESSION["utilisateur_nom"] = $utilisateur["nom"];
            $_SESSION["utilisateur_prenom"] = $utilisateur["prenom"];
            $_SESSION["utilisateur_email"] = $utilisateur["email"];
            $_SESSION["utilisateur_role"] = $utilisateur["role"];

            header("Location: admin/index.php");
            exit;
        }

        $messageErreur = "E-mail ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion administrateur - Galerie NWS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<a href="index.php" class="lien-logo">
    <img
        src="images/logo-nws.jpeg"
        alt="Normandie Web School"
        class="logo-nws"
    >
</a>

<main class="formulaire-inscription">
    <h1>Connexion administrateur</h1>

    <p>
        Connecte-toi pour gérer la galerie des projets étudiants NWS.
    </p>

    <?php if ($messageErreur !== ""): ?>
        <p role="alert">
            <?= htmlspecialchars(
                $messageErreur,
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </p>
    <?php endif; ?>

    <form method="post" action="connexion-utilisateur.php">
        <label for="email">Adresse e-mail</label>
        <input
            type="email"
            id="email"
            name="email"
            autocomplete="username"
            required
            value="<?= htmlspecialchars(
                $email,
                ENT_QUOTES,
                "UTF-8"
            ) ?>"
        >

        <label for="mot_de_passe">Mot de passe</label>
        <input
            type="password"
            id="mot_de_passe"
            name="mot_de_passe"
            autocomplete="current-password"
            required
        >

        <button type="submit">Se connecter</button>
    </form>

    <p>
        <a href="mot-de-passe-oublie.php">
            Mot de passe oublié ?
        </a>
    </p>
    <p>
    Pas encore de compte administrateur ?
    <a href="creation-admin.php">Créer un compte</a>
</p>

    <p>
        <a href="index.php">Retour à la galerie</a>
    </p>
</main>

</body>
</html>
