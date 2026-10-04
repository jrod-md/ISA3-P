-- ADITIVA: Formularios 7–9, nivel proyecto; no altera tablas ni datos existentes.
CREATE TABLE IF NOT EXISTS rubricas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(250) NOT NULL,
    evaluado VARCHAR(250) NOT NULL,
    fecha DATE NOT NULL,
    observaciones TEXT NOT NULL,
    total TINYINT UNSIGNED NOT NULL,
    CONSTRAINT ck_rubrica_total CHECK (total BETWEEN 6 AND 30),
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rubricas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_rubricas_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rubrica_criterios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rubrica_id INT UNSIGNED NOT NULL,
    criterio ENUM('diseno','tecnicas','cobertura','herramientas','documentacion','presentacion') NOT NULL,
    puntuacion TINYINT UNSIGNED NOT NULL,
    observacion TEXT NOT NULL,
    CONSTRAINT ck_rubrica_puntuacion CHECK (puntuacion BETWEEN 1 AND 5),
    UNIQUE KEY uq_rubrica_criterio (rubrica_id, criterio),
    orden SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT fk_rubrica_criterios_padre FOREIGN KEY (rubrica_id) REFERENCES rubricas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_rubrica_criterios_orden (rubrica_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluaciones_pares (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    evaluado VARCHAR(250) NOT NULL,
    evaluador VARCHAR(250) NOT NULL,
    fecha DATE NOT NULL,
    promedio_auto DECIMAL(3,2) NOT NULL,
    promedio_co DECIMAL(3,2) NOT NULL,
    CONSTRAINT ck_pares_auto CHECK (promedio_auto BETWEEN 1 AND 5),
    CONSTRAINT ck_pares_co CHECK (promedio_co BETWEEN 1 AND 5),
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_evaluaciones_pares_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_evaluaciones_pares_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluacion_aspectos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evaluacion_id INT UNSIGNED NOT NULL,
    aspecto ENUM('conceptos','tecnicas','equipo','herramientas','documentacion','plazos') NOT NULL,
    autoevaluacion TINYINT UNSIGNED NOT NULL,
    coevaluacion TINYINT UNSIGNED NOT NULL,
    comentarios TEXT NOT NULL,
    CONSTRAINT ck_aspecto_auto CHECK (autoevaluacion BETWEEN 1 AND 5),
    CONSTRAINT ck_aspecto_co CHECK (coevaluacion BETWEEN 1 AND 5),
    UNIQUE KEY uq_evaluacion_aspecto (evaluacion_id, aspecto),
    orden SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT fk_evaluacion_aspectos_padre FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones_pares(id) ON DELETE CASCADE,
    UNIQUE KEY uq_evaluacion_aspectos_orden (evaluacion_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portafolios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(250) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_portafolios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_portafolios_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portafolio_evidencias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    portafolio_id INT UNSIGNED NOT NULL,
    semana TINYINT UNSIGNED NOT NULL,
    evidencia VARCHAR(250) NOT NULL,
    tipo VARCHAR(250) NOT NULL,
    fecha DATE NOT NULL,
    observaciones TEXT NOT NULL,
    CONSTRAINT ck_evidencia_semana CHECK (semana BETWEEN 1 AND 52),
    orden SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT fk_portafolio_evidencias_padre FOREIGN KEY (portafolio_id) REFERENCES portafolios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_portafolio_evidencias_orden (portafolio_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
