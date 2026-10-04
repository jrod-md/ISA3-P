-- ADITIVA: ejecutar en la base del Bus, después de formularios_2_4_migration.sql.
-- Conserva los tipos y documentos existentes; admite el Formulario 5.
ALTER TABLE formularios_prueba MODIFY COLUMN tipo ENUM('equivalencia','limites','decision','cobertura') NOT NULL;

CREATE TABLE IF NOT EXISTS cobertura_metricas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT UNSIGNED NOT NULL,
    metrica ENUM('sentencia','decision','condicion','caminos','bucles') NOT NULL,
    total INT UNSIGNED NOT NULL,
    cubiertos INT UNSIGNED NOT NULL,
    porcentaje DECIMAL(5,2) NOT NULL,
    herramienta VARCHAR(150) NOT NULL,
    orden SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT fk_cobertura_formulario FOREIGN KEY (formulario_id) REFERENCES formularios_prueba(id) ON DELETE CASCADE,
    CONSTRAINT ck_cobertura_cubiertos CHECK (cubiertos <= total),
    CONSTRAINT ck_cobertura_porcentaje CHECK (porcentaje BETWEEN 0 AND 100),
    UNIQUE KEY uq_cobertura_metrica (formulario_id, metrica),
    UNIQUE KEY uq_cobertura_orden (formulario_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
