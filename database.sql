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
    id     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100)  NOT NULL,
    activo TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cat_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos iniciales de categorías
INSERT INTO tickets_categorias (nombre, activo) VALUES
    ('Control Vehicular',  1),
    ('Control SGC',        1),
    ('Cotizador IA',       1),
    ('Forecast',           1),
    ('Horas Extra',        1),
    ('Incidencias',        1),
    ('Planeación',         1),
    ('Vacaciones',         1),
    ('Capacitación',       1),
    ('Activos',            1),
    ('Entrada de Equipos', 1),
    ('Otro / General',     1),
    ('Facturación',        1),
    ('Messen Academy',     1),
    ('Sitio Web',          1),
    ('KPI',                1);

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
    fecha_creacion            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fecha_cierre              DATETIME,
    PRIMARY KEY (id),
    UNIQUE KEY uq_folio (folio),
    KEY idx_solicitante  (no_empleado_solicitante),
    KEY idx_estado       (estado),
    KEY idx_prioridad    (prioridad),
    KEY idx_fecha        (fecha_creacion),
    CONSTRAINT fk_ticket_cat FOREIGN KEY (id_categoria) REFERENCES tickets_categorias (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────
--  TABLA: tickets_asignados  (N ingenieros BI por ticket, máx. 3 enforced server-side)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tickets_asignados (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_ticket         INT UNSIGNED NOT NULL,
    no_empleado       VARCHAR(50)  NOT NULL,
    fecha_asignacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ticket_emp (id_ticket, no_empleado),
    KEY idx_asg_ticket (id_ticket),
    KEY idx_asg_emp    (no_empleado),
    CONSTRAINT fk_asg_ticket FOREIGN KEY (id_ticket) REFERENCES tickets (id) ON DELETE CASCADE
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
