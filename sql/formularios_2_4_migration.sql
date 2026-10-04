-- ADITIVA: ejecutar en la base del Bus. No altera las cuatro tablas existentes.
CREATE TABLE IF NOT EXISTS formularios_prueba (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caso_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    tipo ENUM('equivalencia','limites','decision') NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_formulario_caso FOREIGN KEY (caso_id) REFERENCES casos_prueba(id) ON DELETE CASCADE,
    CONSTRAINT fk_formulario_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_formulario_caso_tipo (caso_id, tipo),
    INDEX idx_formulario_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS equivalencia_filas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT UNSIGNED NOT NULL,
    orden SMALLINT UNSIGNED NOT NULL,
    campo VARCHAR(150) NOT NULL,
    clase_valida TEXT NOT NULL,
    clases_invalidas TEXT NOT NULL,
    valores_representativos TEXT NOT NULL,
    resultado_esperado TEXT NOT NULL,
    CONSTRAINT fk_equivalencia_formulario FOREIGN KEY (formulario_id) REFERENCES formularios_prueba(id) ON DELETE CASCADE,
    UNIQUE KEY uq_equivalencia_orden (formulario_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS limite_filas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT UNSIGNED NOT NULL,
    orden SMALLINT UNSIGNED NOT NULL,
    campo VARCHAR(150) NOT NULL,
    rango_valido TEXT NOT NULL,
    valor_minimo VARCHAR(150) NOT NULL,
    valor_maximo VARCHAR(150) NOT NULL,
    valores_limite TEXT NOT NULL,
    resultado_esperado TEXT NOT NULL,
    CONSTRAINT fk_limite_formulario FOREIGN KEY (formulario_id) REFERENCES formularios_prueba(id) ON DELETE CASCADE,
    UNIQUE KEY uq_limite_orden (formulario_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS decision_reglas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT UNSIGNED NOT NULL,
    orden SMALLINT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    CONSTRAINT fk_regla_formulario FOREIGN KEY (formulario_id) REFERENCES formularios_prueba(id) ON DELETE CASCADE,
    UNIQUE KEY uq_regla_orden (formulario_id, orden),
    UNIQUE KEY uq_regla_formulario_id (formulario_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS decision_elementos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT UNSIGNED NOT NULL,
    tipo ENUM('condicion','accion') NOT NULL,
    orden SMALLINT UNSIGNED NOT NULL,
    descripcion VARCHAR(250) NOT NULL,
    CONSTRAINT fk_elemento_formulario FOREIGN KEY (formulario_id) REFERENCES formularios_prueba(id) ON DELETE CASCADE,
    UNIQUE KEY uq_elemento_orden (formulario_id, tipo, orden),
    UNIQUE KEY uq_elemento_formulario_id (formulario_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS decision_valores (
    formulario_id INT UNSIGNED NOT NULL,
    elemento_id INT UNSIGNED NOT NULL,
    regla_id INT UNSIGNED NOT NULL,
    valor VARCHAR(1) NOT NULL,
    PRIMARY KEY (elemento_id, regla_id),
    CONSTRAINT fk_valor_elemento FOREIGN KEY (formulario_id, elemento_id) REFERENCES decision_elementos(formulario_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_valor_regla FOREIGN KEY (formulario_id, regla_id) REFERENCES decision_reglas(formulario_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
