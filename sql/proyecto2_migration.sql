-- Migración ADITIVA. Primero selecciona la base del Bus (bus_meta por defecto).
-- No borra ni modifica search_sessions, search_cache o catálogos de proveedores.
-- El ZIP original no contiene tabla de usuarios; se agrega una sola aquí.
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'tester') NOT NULL DEFAULT 'tester',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS casos_prueba (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    modulo VARCHAR(150) NOT NULL,
    tecnica ENUM('Caja Negra', 'Caja Blanca') NOT NULL,
    subtecnica VARCHAR(100) NOT NULL,
    objetivo TEXT NOT NULL,
    precondiciones TEXT NOT NULL,
    datos_entrada TEXT NOT NULL,
    pasos_ejecucion TEXT NOT NULL,
    resultado_esperado TEXT NOT NULL,
    resultado_obtenido TEXT NOT NULL,
    estado ENUM('Éxito', 'Fallo') NOT NULL,
    observaciones TEXT NOT NULL,
    evidencia_archivo VARCHAR(80) DEFAULT NULL,
    evidencia_nombre VARCHAR(255) DEFAULT NULL,
    evidencia_tipo VARCHAR(50) DEFAULT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_casos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_casos_usuario_estado (usuario_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
