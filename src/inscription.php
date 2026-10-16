<?php
session_start();

require_once __DIR__ . "/connexion.php";

$messageErreur = "";
$nom = "";
$prenom = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $motDePasse = $_POST["mot_de_passe"] ?? "";
    $confirmation = $_POST["confirmation"] ?? "";

    if (
        $nom === "" ||
        $prenom === "" ||
        $email === "" ||
        $motDePasse === "" ||
        $confirmation === ""
    ) {
        $messageErreur = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messageErreur = "L'adresse e-mail est invalide.";
    } elseif (strlen($motDePasse) < 12) {
        $messageErreur = "Le mot de passe doit contenir au moins 12 caractères.";
    } elseif ($motDePasse !== $confirmation) {
        $messageErreur = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            $verification = $connexion->prepare(
                "SELECT id FROM utilisateurs WHERE email = :email"
            );
            $verification->execute(["email" => $email]);

            if ($verification->fetch()) {
                $messageErreur = "Cette adresse e-mail possède déjà un compte.";
            } else {
                $motDePasseHache = password_hash(
                    $motDePasse,
                    PASSWORD_DEFAULT
                );

                $requete = $connexion->prepare(
                    "INSERT INTO utilisateurs
                    (nom, prenom, email, mot_de_passe, role)
                    VALUES
                    (:nom, :prenom, :email, :mot_de_passe, 'visiteur')"
                );

                $requete->execute([
                    "nom" => $nom,
                    "prenom" => $prenom,
                    "email" => $email,
                    "mot_de_passe" => $motDePasseHache
                ]);

                header("Location: connexion-utilisateur.php?inscription=ok");
                exit;
            }
        } catch (PDOException $e) {
            $messageErreur = "Une erreur est survenue. Réessaie plus tard.";
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Créer un compte - Galerie NWS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<main class="formulaire-inscription">
    <h1>Créer un compte</h1>
    <p>Rejoins la galerie des projets étudiants NWS.</p>

    <?php if ($messageErreur !== ""): ?>
        <p role="alert">
            <?= htmlspecialchars($messageErreur, ENT_QUOTES, "UTF-8") ?>
        </p>
    <?php endif; ?>

    <form method="post" action="inscription.php">
        <label for="nom">Nom</label>
        <input
            type="text"
            id="nom"
            name="nom"
            maxlength="100"
            autocomplete="family-name"
            required
            value="<?= htmlspecialchars($nom, ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="prenom">Prénom</label>
        <input
            type="text"
            id="prenom"
            name="prenom"
            maxlength="100"
            autocomplete="given-name"
            required
            value="<?= htmlspecialchars($prenom, ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="email">Adresse e-mail</label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="190"
            autocomplete="email"
            required
            value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="mot_de_passe">Mot de passe (12 caractères minimum)</label>
        <input
            type="password"
            id="mot_de_passe"
            name="mot_de_passe"
            minlength="12"
            autocomplete="new-password"
            required
        >

        <label for="confirmation">Confirmer le mot de passe</label>
        <input
            type="password"
            id="confirmation"
            name="confirmation"
            minlength="12"
            autocomplete="new-password"
            required
        >

        <button type="submit">Créer mon compte</button>
    </form>

    <p>
        Déjà inscrit ?
        <a href="connexion-utilisateur.php">Se connecter</a>
    </p>

    <p><a href="index.php">Retour à la galerie</a></p>
</main>

</body>
</html>
