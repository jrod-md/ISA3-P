-- Instalación completa y no destructiva. Para actualizar una BD usa proyecto2_migration.sql.
CREATE DATABASE IF NOT EXISTS bus_meta
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS market_alpha
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS market_beta
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS market_gamma
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


USE bus_meta;

CREATE TABLE IF NOT EXISTS search_sessions (
    id CHAR(36) PRIMARY KEY,
    criteria_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    ttl_seconds INT UNSIGNED NOT NULL,
    requested_providers VARCHAR(100) NOT NULL,
    result_count INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_search_sessions_expires_at (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS search_cache (
    search_id CHAR(36) PRIMARY KEY,
    results_json JSON NOT NULL,
    providers_json JSON NOT NULL,
    warnings_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    CONSTRAINT fk_search_cache_session
      FOREIGN KEY (search_id) REFERENCES search_sessions(id)
      ON DELETE CASCADE
) ENGINE=InnoDB;

USE market_alpha;
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    category VARCHAR(80) NOT NULL,
    brand VARCHAR(80) NULL,
    price DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_products_name (name),
    INDEX idx_products_category (category),
    INDEX idx_products_price (price),
    INDEX idx_products_brand (brand)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS market_beta.products LIKE market_alpha.products;
CREATE TABLE IF NOT EXISTS market_gamma.products LIKE market_alpha.products;


USE market_alpha;

INSERT IGNORE INTO products (id, sku, name, description, category, brand, price, currency, stock, active) VALUES
(1,'A-LAP-001','AlphaBook Air 14','Laptop portatil para estudio y oficina','computers','AlphaWorks',749.99,'USD',7,1),
(2,'A-LAP-002','AlphaBook Studio 15','Laptop de productividad con pantalla amplia','computers','AlphaWorks',899.00,'USD',4,1),
(3,'A-MON-001','AlphaView 24','Monitor IPS para oficina en casa','home-office','ViewForge',189.50,'USD',12,1),
(4,'A-MOU-001','AlphaClick Wireless','Mouse inalambrico ergonomico','accessories','ClickLab',29.90,'USD',30,1),
(5,'A-AUD-001','AlphaSound Headphones','Headphones cerrados para musica','audio','SonicSeed',79.00,'USD',16,1),
(6,'A-PHN-001','AlphaPhone Nova','Phone ficticio de gama media','phones','NovaCell',429.00,'USD',9,1),
(7,'A-GAM-001','AlphaPad Controller','Control para gaming multiplataforma','gaming','PlayFoundry',54.00,'USD',18,1),
(8,'A-KEY-001','AlphaType Mini','Teclado mecanico compacto','accessories','KeyFoundry',68.75,'USD',14,1),
(9,'A-DOC-001','AlphaDock 8','Estacion USB-C de ocho puertos','accessories','AlphaWorks',92.00,'USD',11,1),
(10,'A-WEB-001','AlphaCam Clear','Camara web para videollamadas','home-office','ViewForge',61.25,'USD',20,1),
(11,'A-AUD-002','AlphaPods Lite','Audifonos compactos inalambricos','audio','SonicSeed',49.50,'USD',25,1),
(12,'A-MON-002','AlphaView Gaming 27','Monitor rapido para gaming','gaming','ViewForge',329.99,'USD',6,1),
(13,'A-OLD-001','Alpha Legacy Laptop','Producto inactivo para comprobar filtro','computers','AlphaWorks',199.00,'USD',1,0);

USE market_beta;

INSERT IGNORE INTO products (id, sku, name, description, category, brand, price, currency, stock, active) VALUES
(1,'B-LAP-001','BetaBook 15','Laptop ficticia para productividad','computers','Beta Digital',799.00,'USD',5,1),
(2,'B-LAP-002','BetaBook Flex 13','Laptop convertible para clases','computers','Beta Digital',859.50,'USD',6,1),
(3,'B-MON-001','BetaPanel 27','Monitor QHD para escritorio','home-office','PixelHarbor',279.00,'USD',8,1),
(4,'B-MOU-001','BetaGlide Mouse','Mouse silencioso para oficina','accessories','Beta Digital',24.50,'USD',42,1),
(5,'B-AUD-001','BetaWave Headphones','Headphones con microfono desmontable','audio','WaveMint',94.00,'USD',10,1),
(6,'B-PHN-001','BetaPhone Orbit','Phone ficticio con gran bateria','phones','Orbit Mobile',389.99,'USD',15,1),
(7,'B-GAM-001','BetaArcade Keys','Teclado para gaming','gaming','ArcadeSmith',72.00,'USD',13,1),
(8,'B-KEY-001','BetaBoard Office','Teclado de perfil bajo','accessories','Beta Digital',45.00,'USD',21,1),
(9,'B-DOC-001','BetaHub Pro','Hub USB-C compacto','accessories','PortCraft',57.90,'USD',17,1),
(10,'B-WEB-001','BetaLens 1080','Camara web para reuniones','home-office','PixelHarbor',52.40,'USD',19,1),
(11,'B-AUD-002','BetaBuds Air','Audifonos inalambricos','audio','WaveMint',63.00,'USD',22,1),
(12,'B-MON-002','BetaPanel Play 24','Monitor de entrada para gaming','gaming','PixelHarbor',219.00,'USD',9,1);

USE market_gamma;

INSERT IGNORE INTO products (id, sku, name, description, category, brand, price, currency, stock, active) VALUES
(1,'G-LAP-001','Gamma Portable 13','Laptop compacta ficticia para movilidad','computers','Gamma Labs',689.50,'USD',5,1),
(2,'G-LAP-002','Gamma Creator 16','Laptop para contenido y desarrollo','computers','Gamma Labs',879.00,'USD',3,1),
(3,'G-MON-001','GammaCanvas 25','Monitor de color equilibrado','home-office','CanvasWorks',239.00,'USD',7,1),
(4,'G-MOU-001','GammaTrack Mouse','Mouse liviano de precision','accessories','Input Grove',34.00,'USD',28,1),
(5,'G-AUD-001','GammaTone Headphones','Headphones comodos para jornadas largas','audio','ToneGarden',88.80,'USD',12,1),
(6,'G-PHN-001','GammaPhone Pulse','Phone ficticio compacto','phones','Pulse Mobile',459.00,'USD',8,1),
(7,'G-GAM-001','GammaStick Controller','Control alambrico para gaming','gaming','GameGrove',39.95,'USD',24,1),
(8,'G-KEY-001','GammaKeys TKL','Teclado mecanico sin pad numerico','accessories','Input Grove',76.25,'USD',11,1),
(9,'G-DOC-001','GammaPort 6','Adaptador multipuerto de viaje','accessories','Gamma Labs',71.00,'USD',18,1),
(10,'G-WEB-001','GammaMeet Cam','Camara web gran angular','home-office','CanvasWorks',66.00,'USD',14,1),
(11,'G-AUD-002','GammaMini Buds','Audifonos de bolsillo','audio','ToneGarden',58.50,'USD',20,1),
(12,'G-MON-002','GammaArena 27','Monitor para gaming fluido','gaming','CanvasWorks',349.00,'USD',4,1);


USE bus_meta;
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

-- Opcional, después de la migración, sobre bus_meta.
-- Credenciales por defecto del ZIP: admin / demo-isa3-2026.
-- Para ADMIN_USERNAME/PASSWORD personalizados usa scripts/migrate-proyecto2.php.
INSERT INTO usuarios (username, nombre, correo, password, rol)
SELECT 'admin', 'Administrador', 'admin@isa3.local', '$2y$10$VkEY5VD/pGNVZwGGopnpsOrusl5OsvEAz7r260osUc/GF9HnmRV66', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE username = 'admin' OR correo = 'admin@isa3.local');
INSERT INTO usuarios (username, nombre, correo, password, rol)
SELECT 'tester', 'Tester Demo', 'tester@isa3.local', '$2y$10$GZoGfd7jIeaPT0MyTLQD0OuDXbRxc9Bs0kVubkx..J/Fhghx9vNwa', 'tester'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE username = 'tester' OR correo = 'tester@isa3.local');


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

-- Formulario 5: cobertura de Caja Blanca.
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

-- Formulario 6: plan de pruebas a nivel proyecto.
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
