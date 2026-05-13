<?php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: accueil.php');
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connexion - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-page">
  <form class="login-card" id="loginForm">
    <img class="login-logo" src="../assets/img/logo-onda.png" alt="ONDA" onerror="this.style.display='none';document.querySelector('.logo-fallback').style.display='grid'">
    <div class="logo-fallback" style="display:none">ONDA</div>
    <h1>GMAO - Gestion de Maintenance</h1>
    <input type="email" name="email" placeholder="Email" required value="admin@gmao-onda.local">
    <input type="password" name="password" placeholder="Mot de passe" required value="Admin123!">
    <div class="login-error" id="loginError"></div>
    <button class="btn-primary" type="submit" style="width:100%;justify-content:center">Se connecter</button>
    <div style="text-align:left;font-size:12px;line-height:1.6;margin-top:14px;color:#555">
      <strong>Comptes demo</strong><br>
      admin@gmao-onda.local / Admin123!<br>
      tarik@gmao-onda.local / Tech123!<br>
      responsable@gmao-onda.local / Resp123!<br>
      superviseur@gmao-onda.local / Sup123!<br>
      agent@gmao-onda.local / Agent123!<br>
      <a href="../config/reset_users.php">Reinitialiser les comptes</a>
    </div>
  </form>
  <script>
    document.getElementById('loginForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      const error = document.getElementById('loginError');
      error.textContent = '';
      try {
        const response = await fetch('../api/login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(Object.fromEntries(new FormData(event.target).entries()))
        });
        const text = await response.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch (jsonError) {
          throw new Error(text || 'Reponse serveur invalide');
        }
        if (!response.ok || !data.success) throw new Error(data.message || 'Connexion impossible');
        window.location.href = 'accueil.php';
      } catch (e) {
        error.textContent = e.message;
      }
    });
  </script>
</body>
</html>
