<?php
session_start();

require_once __DIR__ . "/../connexion.php";

$erreur = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $motDePasse = $_POST["password"] ?? "";

    $requete = $connexion->prepare(
        "SELECT id, mot_de_passe
         FROM administrateurs
         WHERE email = :email"
    );

    $requete->execute(["email" => $email]);
    $administrateur = $requete->fetch(PDO::FETCH_ASSOC);

    if (
        $administrateur &&
        password_verify($motDePasse, $administrateur["mot_de_passe"])
    ) {
        session_regenerate_id(true);

        $_SESSION["admin_connecte"] = true;
        $_SESSION["admin_id"] = $administrateur["id"];

        header("Location: index.php");
        exit;
    }

    $erreur = "Adresse e-mail ou mot de passe incorrect.";
}
?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion administrateur - NWS</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <main class="login-container">
        <div class="login-card">
            <img
                src="../images/logo-nws.jpeg"
                alt="Logo Normandie Web School"
                class="login-logo"
            >

            <h1>Administration NWS</h1>

            <p class="login-subtitle">
                Connectez-vous pour gérer les projets étudiants.
            </p>

            <?php if ($erreur !== ""): ?>
                <p class="login-error">
                    <?= htmlspecialchars($erreur, ENT_QUOTES, "UTF-8") ?>
                </p>
            <?php endif; ?>

            <form method="post">
                <label for="email">Adresse e-mail</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                >

                <label for="password">Mot de passe</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >

                <button type="submit">Se connecter</button>
            </form>

            <a href="../index.php" class="back-link">
                ← Retour à la galerie
            </a>
        </div>
    </main>
</body>
</html>
