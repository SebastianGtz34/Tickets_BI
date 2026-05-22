<?php
$noEmpleado = $_COOKIE['noEmpleadoL'] ?? null;
if (!$noEmpleado) {
    header('Location: ../loginMaster/index.php');
    exit;
}
header('Location: inicio.php');
exit;
