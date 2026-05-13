<?php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: pages/accueil.php');
} else {
    header('Location: pages/login.php');
}
exit;
