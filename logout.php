<?php
// Salir de Tickets BI y regresar al panel de loginMaster.
// La sesión MESS (cookies con sufijo L) se conserva — solo se borran las
// cookies de desarrollo que dev_login.php pudiera haber dejado.
setcookie('noEmpleado',     '', time() - 3600, '/');
setcookie('nombreEmpleado', '', time() - 3600, '/');
header('Location: ../loginMaster/inicio.php');
exit;
