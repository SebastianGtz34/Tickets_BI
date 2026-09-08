<?php
/**
 * _diag_correo.php — DIAGNÓSTICO TEMPORAL (BORRAR DESPUÉS DE USAR).
 *
 * Objetivo: descubrir POR QUÉ no llegan los correos de alta de ticket, cuando
 * el ticket sí se crea. tkEnviarCorreo() y tkCorreoNuevoTicket() se tragan
 * TODOS los errores a propósito (un SMTP caído no debe tumbar la creación del
 * ticket), así que el fallo solo vive en el error_log del servidor. Este script
 * repite el mismo camino mostrando lo que allá se silencia.
 *
 * Uso: abrir https://TU-DOMINIO/Tickets/_diag_correo.php estando logueado como BI/TI.
 *      ?enviar=alguien@dominio.com  → además manda un correo de prueba real y
 *                                     muestra el error exacto de SMTP si falla.
 *
 * SEGURIDAD: solo lee (salvo el envío de prueba, que es explícito por URL).
 *            Nunca imprime la contraseña SMTP. Aun así, BÓRRALO al terminar.
 */

header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

function linea(string $k, $v): void { echo str_pad($k, 30) . ': ' . $v . "\n"; }
function titulo(string $t): void { echo "\n== $t ==\n"; }
function si(bool $b): string { return $b ? 'SÍ' : 'NO'; }

echo "DIAGNÓSTICO CORREO DE ALTA — Tickets BI/TI\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/acciones_notificaciones.php';

// Gate: mismo criterio que el resto del sistema (BI o TI activo).
$noEmpSesion = ticketsAuthNoEmpleado();
if (!$noEmpSesion || !tieneAccesoTickets($conn, $noEmpSesion)) {
    echo "\nAcceso denegado: entra a MessBook como BI/TI y recarga esta página.\n";
    exit;
}
linea('Sesión', $noEmpSesion);

// ─────────────────────────────────────────────────────────────────────────────
titulo('1) Entorno PHP y archivos de PHPMailer');

linea('PHP', PHP_VERSION);
linea('Extensión openssl', si(extension_loaded('openssl')) . '  (obligatoria para SSL:465)');
linea('error_log activo',  ini_get('log_errors') ? ini_get('error_log') ?: '(default del servidor)' : 'NO');

foreach ([
    'PHPMailer-master/src/PHPMailer.php',
    'PHPMailer-master/src/SMTP.php',
    'PHPMailer-master/src/Exception.php',
    'includes/correo.php',
] as $rel) {
    linea($rel, is_file(__DIR__ . '/' . $rel) ? 'presente' : '*** FALTA ***');
}

// ─────────────────────────────────────────────────────────────────────────────
titulo('2) config_correo.php (credenciales SMTP)');

$rutaCfg = __DIR__ . '/config_correo.php';
$cfg     = null;  // se usa también en la sección 5
linea('Ruta esperada', $rutaCfg);
linea('¿Existe?', si(is_file($rutaCfg)));

if (!is_file($rutaCfg)) {
    echo "\n>>> AQUÍ ESTÁ EL PROBLEMA (candidato #1): config_correo.php NO existe.\n";
    echo ">>> Está en .gitignore, así que NUNCA viaja con el deploy: hay que crearlo\n";
    echo ">>> a mano en el servidor, copiando config_correo.example.php y poniendo\n";
    echo ">>> las credenciales reales con 'activo' => true.\n";
    echo ">>> Sin él, tkEnviarCorreo() aborta con 'Falta config_correo.php en el\n";
    echo ">>> servidor.' y el ticket se crea igual, en silencio.\n";
} else {
    $cfg = require $rutaCfg;
    linea('activo',      isset($cfg['activo']) ? si((bool)$cfg['activo']) : '(no definido → se asume activo)');
    linea('host',        $cfg['host'] ?? '(falta)');
    linea('port',        $cfg['port'] ?? '(falta)');
    linea('secure',      $cfg['secure'] ?? '(falta)');
    linea('timeout',     $cfg['timeout'] ?? '(10 por defecto)');
    linea('usuario',     $cfg['usuario'] ?? '(falta)');
    linea('from_correo', $cfg['from_correo'] ?? '(usa usuario)');
    linea('password',    isset($cfg['password'])
        ? 'definida (' . strlen((string)$cfg['password']) . ' caracteres)'
        : '*** FALTA ***');

    if (array_key_exists('activo', $cfg) && !$cfg['activo']) {
        echo "\n>>> AQUÍ ESTÁ EL PROBLEMA: 'activo' => false. Es el interruptor de\n";
        echo ">>> entorno para no mandar correos en local. En producción debe ser true.\n";
    }
    // Una app password de Gmail son 16 caracteres; la contraseña normal de la
    // cuenta NO funciona con SMTP y falla con 'Username and Password not accepted'.
    if (isset($cfg['password']) && strlen((string)$cfg['password']) !== 16
        && strpos((string)($cfg['host'] ?? ''), 'gmail') !== false) {
        echo "\n>>> OJO: la contraseña no mide 16 caracteres. Gmail exige App Password.\n";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
titulo('3) Destinatarios: usuarios de BI (27) y TI (39) con correo');

foreach ([DEPT_BI => 'BI', DEPT_TI => 'TI'] as $depto => $nombre) {
    $st = $conn->prepare(
        "SELECT noEmpleado, nombre, correo FROM mess_rrhh.usuarios
         WHERE departamento = ? AND estatus = 1 ORDER BY nombre"
    );
    if (!$st) { linea("Depto $depto ($nombre)", 'ERROR prepare: ' . $conn->error); continue; }
    $st->bind_param('i', $depto);
    $st->execute();
    $rs = $st->get_result();

    $total = 0; $conCorreo = 0; $detalle = [];
    while ($u = $rs->fetch_assoc()) {
        $total++;
        $c = trim((string)$u['correo']);
        $valido = $c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL) !== false;
        if ($valido) $conCorreo++;
        $detalle[] = sprintf('   #%-6s %-32s %s', $u['noEmpleado'], mb_substr($u['nombre'], 0, 32),
            $c === '' ? '(SIN CORREO)' : ($valido ? $c : $c . '  <-- NO PASA FILTER_VALIDATE_EMAIL'));
    }
    $st->close();

    linea("Depto $depto ($nombre)", "$total activos, $conCorreo con correo válido");
    echo implode("\n", $detalle) . "\n";

    if ($total === 0) {
        echo "\n>>> Ningún usuario activo en el depto $depto. Si el equipo $nombre existe,\n";
        echo ">>> revisa el id de departamento en mess_rrhh.usuarios (ya cambió una vez:\n";
        echo ">>> era 32/38, hoy el código usa 27/39). Sin destinos no hay correo NI\n";
        echo ">>> notificación in-app.\n";
    } elseif ($conCorreo === 0) {
        echo "\n>>> AQUÍ ESTÁ EL PROBLEMA para $nombre: hay usuarios activos pero ninguno\n";
        echo ">>> con correo válido en mess_rrhh.usuarios. tkCorreosDeEmpleados() los\n";
        echo ">>> filtra y tkCorreoNuevoTicket() sale con 'sin destinatarios con correo'.\n";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
titulo('4) Últimos 10 tickets: ruteo real y si hubo notificación in-app');

echo "Compara las dos últimas columnas: la notificación in-app y el correo salen\n";
echo "de los MISMOS destinos. Si hay in-app pero no correo, el fallo es de SMTP o\n";
echo "de config. Si tampoco hay in-app, el problema es el ruteo (0 destinos).\n\n";

$rs = $conn->query(
    "SELECT t.id, t.folio, t.fecha_creacion, t.no_empleado_solicitante,
            COALESCE(c.tipo, '(sin categoría)') AS tipo
     FROM tickets t
     LEFT JOIN tickets_categorias c ON c.id = t.id_categoria
     ORDER BY t.id DESC LIMIT 10"
);

printf("%-6s %-16s %-16s %-14s %-8s %-8s %-8s\n",
    'ID', 'FOLIO', 'FECHA', 'TIPO CAT', 'DUEÑO', 'DESTINOS', 'IN-APP');
echo str_repeat('-', 86) . "\n";

while ($t = $rs->fetch_assoc()) {
    $id    = (int)$t['id'];
    $depto = ticketDepartamento($conn, $id);
    // Mismo cálculo que tkNotificarNuevoTicket: receptores menos el solicitante.
    $destinos = tkNotifReceptoresBi($conn, $id, (int)$t['no_empleado_solicitante']);
    $correos  = tkCorreosDeEmpleados($conn, $destinos);

    $chk = $conn->prepare(
        "SELECT COUNT(*) AS n FROM mess_rrhh.notificacion_historial
         WHERE sistema = 'ticketsBI' AND accion = 'NuevoTicket' AND id_registro_referencia = ?"
    );
    $chk->bind_param('i', $id);
    $chk->execute();
    $nInApp = (int)($chk->get_result()->fetch_assoc()['n'] ?? 0);
    $chk->close();

    printf("%-6d %-16s %-16s %-14s %-8s %-8s %-8s\n",
        $id,
        $t['folio'],
        date('d/m/y H:i', strtotime($t['fecha_creacion'])),
        $t['tipo'],
        $depto === DEPT_TI ? 'TI (39)' : 'BI (27)',
        count($destinos) . ' emp',
        $nInApp . ' notif');

    if ($destinos && !$correos) {
        echo "       ^ tiene destinos pero NINGUNO con correo válido → correo no sale.\n";
    }
    if (!$destinos) {
        echo "       ^ 0 destinos → ni correo ni in-app. Ruteo/departamento.\n";
    }
}

echo "\nRecuerda: solo el ALTA manda correo. Cambio de estado, asignación,\n";
echo "comentario y cancelación son solo in-app, por diseño.\n";
echo "Y el solicitante SIEMPRE se excluye: si un ingeniero de BI levanta un\n";
echo "ticket de categoría BI, él no recibe su propio correo.\n";

// ─────────────────────────────────────────────────────────────────────────────
titulo('5) Prueba de envío real por SMTP');

$destinoPrueba = trim((string)($_GET['enviar'] ?? ''));
if ($destinoPrueba === '') {
    echo "Omitida. Para probar el SMTP de verdad, vuelve a abrir esta página con:\n";
    echo "   ?enviar=tu.correo@dominio.com\n";
} elseif (!filter_var($destinoPrueba, FILTER_VALIDATE_EMAIL)) {
    echo "El correo '$destinoPrueba' no es válido.\n";
} else {
    require_once __DIR__ . '/includes/correo.php';
    linea('Enviando a', $destinoPrueba);

    $t0  = microtime(true);
    $res = tkEnviarCorreo(
        [$destinoPrueba],
        'Prueba de correo — Tickets BI/TI',
        'Prueba de Configuración',
        '<p>Si estás leyendo esto, el SMTP de Tickets funciona correctamente.</p>'
        . '<p style="color:#6c757d;font-size:13px;">Enviado desde _diag_correo.php el '
        . date('d/m/Y H:i:s') . '.</p>'
    );
    $ms = round((microtime(true) - $t0) * 1000);

    linea('Resultado', $res['ok'] ? 'ENVIADO' : 'FALLÓ');
    linea('Tiempo', $ms . ' ms');
    linea('Error', $res['error'] ?? '(ninguno)');

    if (!$res['ok']) {
        echo "\n>>> Este es el error exacto que en la creación de tickets se va callado\n";
        echo ">>> al error_log. Traducción de los más comunes:\n";
        echo ">>>   'Could not connect to SMTP host'  → el hosting bloquea la salida al\n";
        echo ">>>       puerto 465, o falta la extensión openssl.\n";
        echo ">>>   'Username and Password not accepted' → hace falta una App Password\n";
        echo ">>>       de Gmail (16 caracteres), no la contraseña de la cuenta.\n";
        echo ">>>   'SMTP connect() failed' tras ~" . (int)($cfg['timeout'] ?? 10) . "s → firewall de salida.\n";
    }
}

echo "\n\nFin del diagnóstico. BORRA ESTE ARCHIVO del servidor al terminar.\n";
