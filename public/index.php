<?php
require_once '../config/config.php';

// Redirection vers login si pas connecté
if (!isLoggedIn()) {
    redirect('login.php');
}

// Sinon redirection vers dashboard
redirect('dashboard.php');
?>
