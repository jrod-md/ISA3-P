-- Opcional, después de la migración, sobre bus_meta.
-- Credenciales por defecto del ZIP: admin / demo-isa3-2026.
-- Para ADMIN_USERNAME/PASSWORD personalizados usa scripts/migrate-proyecto2.php.
INSERT INTO usuarios (username, nombre, correo, password, rol)
SELECT 'admin', 'Administrador', 'admin@isa3.local', '$2y$10$VkEY5VD/pGNVZwGGopnpsOrusl5OsvEAz7r260osUc/GF9HnmRV66', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE username = 'admin' OR correo = 'admin@isa3.local');
INSERT INTO usuarios (username, nombre, correo, password, rol)
SELECT 'tester', 'Tester Demo', 'tester@isa3.local', '$2y$10$GZoGfd7jIeaPT0MyTLQD0OuDXbRxc9Bs0kVubkx..J/Fhghx9vNwa', 'tester'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE username = 'tester' OR correo = 'tester@isa3.local');
