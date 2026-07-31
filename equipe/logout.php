<?php
require __DIR__ . '/../includes/csrf.php';
csrf_ensure_session();
unset($_SESSION['staff_tipo'], $_SESSION['staff_id'], $_SESSION['staff_nivel'], $_SESSION['staff_nome']);
session_regenerate_id(true);
header('Location: login.php');
exit;
