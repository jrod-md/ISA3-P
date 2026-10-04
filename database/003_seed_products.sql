USE market_alpha;
DELETE FROM products;
INSERT INTO products (id, sku, name, description, category, brand, price, currency, stock, active) VALUES
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
DELETE FROM products;
INSERT INTO products (id, sku, name, description, category, brand, price, currency, stock, active) VALUES
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
DELETE FROM products;
INSERT INTO products (id, sku, name, description, category, brand, price, currency, stock, active) VALUES
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

