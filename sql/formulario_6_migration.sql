-- ADITIVA: Formulario 6 a nivel proyecto, sin relación con casos individuales.
CREATE TABLE IF NOT EXISTS planes_prueba (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    nombre_proyecto VARCHAR(150) NOT NULL,
    version VARCHAR(50) NOT NULL,
    responsable VARCHAR(150) NOT NULL,
    fecha DATE NOT NULL,
    alcance TEXT NOT NULL,
    objetivos TEXT NOT NULL,
    estrategia TEXT NOT NULL,
    recursos TEXT NOT NULL,
    criterios_aceptacion TEXT NOT NULL,
    riesgos TEXT NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_plan_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_plan_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_cronograma (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id INT UNSIGNED NOT NULL,
    actividad VARCHAR(250) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    orden SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT fk_cronograma_plan FOREIGN KEY (plan_id) REFERENCES planes_prueba(id) ON DELETE CASCADE,
    CONSTRAINT ck_cronograma_fechas CHECK (fecha_fin >= fecha_inicio),
    UNIQUE KEY uq_cronograma_orden (plan_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
