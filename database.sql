-- ============================================================
--  Sistema de Tickets BI — Script de base de datos completo
--  Base de datos: mess_tickets_bi
-- ============================================================

CREATE DATABASE IF NOT EXISTS mess_tickets_bi
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE mess_tickets_bi;

-- ──────────────────────────────────────────────────────────
--  TABLA: tickets_categorias
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tickets_categorias (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(100)     NOT NULL,
    descripcion TEXT,
    activo      TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cat_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos iniciales de categorías
INSERT INTO tickets_categorias (nombre, descripcion, activo) VALUES
    ('Control Vehicular',  'Solicitudes relacionadas con el módulo de control vehicular.',      1),
    ('Control SGC',        'Incidencias o mejoras en el sistema de gestión de calidad.',        1),
    ('Cotizador IA',       'Soporte y mejoras en el cotizador con inteligencia artificial.',    1),
    ('Forecast',           'Solicitudes de pronóstico y planificación de demanda.',             1),
    ('Horas Extra',        'Reportes y cálculos de horas extra.',                               1),
    ('Incidencias',        'Reporte de fallos o errores en sistemas BI.',                       1),
    ('Planeación',         'Solicitudes relacionadas con planeación de recursos y proyectos.',  1),
    ('Vacaciones',         'Solicitudes y trámites relacionados con vacaciones.',               1),
    ('Capacitación',       'Solicitudes de capacitación, cursos y entrenamientos.',             1),
    ('Activos',            'Solicitudes relacionadas con el control y registro de activos.',    1),
    ('Entrada de Equipos', 'Registro de entrada y alta de equipos nuevos.',                     1),
    ('Otro / General',     'Solicitudes que no encajan en las categorías anteriores.',          1);

-- ──────────────────────────────────────────────────────────
--  TABLA: tickets
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tickets (
    id                        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    folio                     VARCHAR(20)   NOT NULL,
    titulo                    VARCHAR(200)  NOT NULL,
    descripcion               TEXT          NOT NULL,
    link                      VARCHAR(500)  NULL,
    id_categoria              INT UNSIGNED,
    prioridad                 ENUM('baja','media','alta','urgente') NOT NULL DEFAULT 'media',
    estado                    ENUM('nuevo','en_proceso','pendiente','resuelto','cerrado') NOT NULL DEFAULT 'nuevo',
    no_empleado_solicitante   VARCHAR(50)   NOT NULL,
    no_empleado_asignado      VARCHAR(50),
    fecha_creacion            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fecha_cierre              DATETIME,
    PRIMARY KEY (id),
    UNIQUE KEY uq_folio (folio),
    KEY idx_solicitante  (no_empleado_solicitante),
    KEY idx_asignado     (no_empleado_asignado),
    KEY idx_estado       (estado),
    KEY idx_prioridad    (prioridad),
    KEY idx_fecha        (fecha_creacion),
    CONSTRAINT fk_ticket_cat FOREIGN KEY (id_categoria) REFERENCES tickets_categorias (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────
--  TABLA: tickets_comentarios
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tickets_comentarios (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_ticket   INT UNSIGNED NOT NULL,
    no_empleado VARCHAR(50)  NOT NULL,
    comentario  TEXT         NOT NULL,
    es_interno  TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '0=público, 1=nota interna solo BI',
    fecha       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_coment_ticket (id_ticket),
    CONSTRAINT fk_coment_ticket FOREIGN KEY (id_ticket) REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────
--  TABLA: tickets_adjuntos
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tickets_adjuntos (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_ticket      INT UNSIGNED NOT NULL,
    id_comentario  INT UNSIGNED              COMMENT 'NULL = adjunto del ticket; NOT NULL = adjunto de comentario',
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta           VARCHAR(255) NOT NULL     COMMENT 'Nombre único en la carpeta uploads/',
    tipo           VARCHAR(100),
    tamano         INT UNSIGNED,
    fecha          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_adj_ticket    (id_ticket),
    KEY idx_adj_comentario (id_comentario),
    CONSTRAINT fk_adj_ticket  FOREIGN KEY (id_ticket)     REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_adj_coment  FOREIGN KEY (id_comentario) REFERENCES tickets_comentarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────
--  DATOS DE EJEMPLO (opcionales, comentar si no se desean)
-- ──────────────────────────────────────────────────────────

-- Ticket de ejemplo
INSERT INTO tickets (folio, titulo, descripcion, id_categoria, prioridad, estado, no_empleado_solicitante) VALUES
    ('TKT-2026-001',
     'Error en reporte de horas extra semana 18',
     'Al generar el reporte de horas extra de la semana 18 el sistema devuelve un error 500. Revisé los permisos y todo parece estar bien. Adjunto captura de pantalla del error.',
     5, 'alta', 'nuevo', '10001'),
    ('TKT-2026-002',
     'Actualizar catálogo de vehículos con nuevas unidades',
     'Se compraron 5 camiones nuevos (placas: ABC-001 a ABC-005). Favor de agregarlos al catálogo de Control Vehicular.',
     1, 'media', 'en_proceso', '10002'),
    ('TKT-2026-003',
     'Solicitud de acceso al Cotizador IA',
     'El área de ventas necesita acceso al módulo Cotizador IA para 3 usuarios nuevos: 20010, 20011, 20012.',
     3, 'baja', 'resuelto', '10001');

-- Comentario de ejemplo
INSERT INTO tickets_comentarios (id_ticket, no_empleado, comentario, es_interno) VALUES
    (1, '99001', 'Revisando el error. Parece ser un problema con la conexión a la base de datos de nómina.', 1),
    (1, '10001', 'Gracias por la respuesta. Quedo en espera.', 0),
    (2, '99001', 'Se ha iniciado el proceso de alta de las nuevas unidades en el sistema.', 0);
