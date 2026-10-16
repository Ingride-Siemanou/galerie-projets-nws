<?php
session_start();

require_once __DIR__ . "/connexion.php";

$messageErreur = "";
$nom = "";
$prenom = "";
$email = "";

// Vérifie si un compte administrateur existe déjà.
$requeteAdmin = $connexion->query(
    "SELECT COUNT(*) FROM utilisateurs WHERE role = 'admin'"
);
$adminExiste = (int) $requeteAdmin->fetchColumn() > 0;

// Si un administrateur existe, seul un administrateur connecté
// peut accéder à cette page.
if (
    $adminExiste &&
    (
        empty($_SESSION["utilisateur_id"]) ||
        ($_SESSION["utilisateur_role"] ?? "") !== "admin"
    )
) {
    http_response_code(403);
    exit("La création du compte administrateur est désactivée.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $motDePasse = $_POST["mot_de_passe"] ?? "";
    $confirmation = $_POST["confirmation"] ?? "";

    if ($nom === "" || $prenom === "" || $email === "") {
        $messageErreur = "Remplis tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messageErreur = "L'adresse e-mail n'est pas valide.";
    } elseif (strlen($motDePasse) < 12) {
        $messageErreur = "Le mot de passe doit contenir au moins 12 caractères.";
    } elseif ($motDePasse !== $confirmation) {
        $messageErreur = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);

            $requete = $connexion->prepare(
                "INSERT INTO utilisateurs
                    (nom, prenom, email, mot_de_passe, role)
                 VALUES
                    (:nom, :prenom, :email, :mot_de_passe, 'admin')"
            );

            $requete->execute([
                "nom" => $nom,
                "prenom" => $prenom,
                "email" => $email,
                "mot_de_passe" => $hash
            ]);

            if ($adminExiste) {
                $messageErreur = "Compte administrateur créé.";
            } else {
                header("Location: connexion-utilisateur.php");
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $messageErreur = "Cette adresse e-mail est déjà utilisée.";
            } else {
                $messageErreur = "Une erreur est survenue lors de la création.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Création du compte administrateur - NWS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<a href="index.php" class="lien-logo">
    <img src="images/logo-nws.jpeg"
         alt="Normandie Web School"
         class="logo-nws">
</a>

<main class="formulaire-inscription">
    <h1>Créer un compte administrateur</h1>

    <?php if ($messageErreur !== ""): ?>
        <p role="status">
            <?= htmlspecialchars($messageErreur, ENT_QUOTES, "UTF-8") ?>
        </p>
    <?php endif; ?>

    <?php if (!$adminExiste || (
        !empty($_SESSION["utilisateur_id"]) &&
        ($_SESSION["utilisateur_role"] ?? "") === "admin"
    )): ?>
        <form method="post" action="creation-admin.php">
            <label for="nom">Nom</label>
            <input id="nom" name="nom" required
                   value="<?= htmlspecialchars($nom, ENT_QUOTES, "UTF-8") ?>">

            <label for="prenom">Prénom</label>
            <input id="prenom" name="prenom" required
                   value="<?= htmlspecialchars($prenom, ENT_QUOTES, "UTF-8") ?>">

            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email"
                   autocomplete="email" required
                   value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>">

            <label for="mot_de_passe">Mot de passe (12 caractères minimum)</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe"
                   autocomplete="new-password" minlength="12" required>

            <label for="confirmation">Confirmer le mot de passe</label>
            <input type="password" id="confirmation" name="confirmation"
                   autocomplete="new-password" minlength="12" required>

            <button type="submit">Créer le compte administrateur</button>
        </form>
    <?php endif; ?>

    <p><a href="connexion-utilisateur.php">Se connecter</a></p>
    <p><a href="index.php">Retour à la galerie</a></p>
</main>

</body>
</html>
