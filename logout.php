<?php
// Eliminar cookie de sesión
setcookie('noEmpleado', '', time() - 3600, '/');
header('Location: ../loginMaster/index.php');
exit;
