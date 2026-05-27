<?php
$noEmpleado = $_COOKIE['noEmpleadoBI'] ?? null;
if (!$noEmpleado) {
    header('Location: ../loginMaster/index.php');
    exit;
}
header('Location: inicio.php');
exit;
