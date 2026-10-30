CREATE DATABASE IF NOT EXISTS plataforma_restaurantes;
USE plataforma_restaurantes;

-- 1. Tabla Principal: Los Restaurantes (Dueños)
CREATE TABLE restaurantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Aquí guardaremos el hash de la contraseña
    telefono VARCHAR(20),
    direccion VARCHAR(150),
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Categorías del Menú (Dependen de cada restaurante)
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_restaurante INT NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    FOREIGN KEY (id_restaurante) REFERENCES restaurantes(id) ON DELETE CASCADE
);

-- 3. Productos (Dependen de cada categoría)
CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) NOT NULL,
    url_imagen VARCHAR(255), -- Ruta donde se guardará la foto subida
    disponible BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_categoria) REFERENCES categorias(id) ON DELETE CASCADE
);

-- 4. Mesas (Dependen de cada restaurante)
CREATE TABLE mesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_restaurante INT NOT NULL,
    numero_mesa INT NOT NULL,
    hash_qr VARCHAR(64) NOT NULL UNIQUE, -- Identificador único para el QR
    FOREIGN KEY (id_restaurante) REFERENCES restaurantes(id) ON DELETE CASCADE
);

-- 5. Pedidos (Cabecera)
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_restaurante INT NOT NULL,
    id_mesa INT NOT NULL,
    estado ENUM('pendiente', 'preparando', 'servido', 'cancelado') DEFAULT 'pendiente',
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_restaurante) REFERENCES restaurantes(id) ON DELETE CASCADE,
    FOREIGN KEY (id_mesa) REFERENCES mesas(id) ON DELETE CASCADE
);

-- 6. Detalle del Pedido (Items)
CREATE TABLE pedidos_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id) ON DELETE CASCADE
);



--------nuevas cosas -------


-- 1. Crear tabla para el registro de cierres
CREATE TABLE cierres_caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_restaurante INT NOT NULL,
    fecha_cierre TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_recaudado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cantidad_pedidos INT NOT NULL DEFAULT 0,
    FOREIGN KEY (id_restaurante) REFERENCES restaurantes(id) ON DELETE CASCADE
);

-- 2. Agregar la columna id_cierre a los pedidos
ALTER TABLE pedidos 
ADD COLUMN id_cierre INT NULL AFTER id_mesa;

-- 3. Crear la relación
ALTER TABLE pedidos 
ADD CONSTRAINT fk_pedido_cierre 
FOREIGN KEY (id_cierre) REFERENCES cierres_caja(id) ON DELETE SET NULL;

----------

ALTER TABLE restaurantes 
ADD COLUMN alias VARCHAR(50) UNIQUE AFTER nombre,
ADD COLUMN logo VARCHAR(255) AFTER direccion;


-------- usuarios demo -----

-- 1. Crear el Restaurante de Prueba
-- La contraseña está encriptada y es: 123456
INSERT INTO restaurantes (id, nombre, alias, email, telefono, direccion, password) 
VALUES (1, 'Burger Joe', 'burger-joe', 'admin@burgerjoe.com', '+54 9 11 1234-5678', 'Av. Principal 123', '$2y$10$7rLSvRVyMCUgw.2G283iN.hQW/.HMyD7.v64x/vA0H5YQOtz.wKue')
ON DUPLICATE KEY UPDATE id=1;

-- 2. Crear Categorías
INSERT INTO categorias (id_restaurante, nombre) VALUES 
(1, 'Hamburguesas Smash'),
(1, 'Papas y Acompañamientos'),
(1, 'Bebidas')
ON DUPLICATE KEY UPDATE id=id;

-- 3. Crear Productos
-- Obtenemos los IDs de las categorías para insertar los productos correctamente
INSERT INTO productos (id_categoria, nombre, descripcion, precio, disponible) VALUES 
((SELECT id FROM categorias WHERE nombre = 'Hamburguesas Smash' LIMIT 1), 'Joe Clásica', 'Doble medallón smash 100g, doble cheddar, salsa de la casa en pan brioche.', 8500.00, 1),
((SELECT id FROM categorias WHERE nombre = 'Hamburguesas Smash' LIMIT 1), 'Bacon Crispy Joe', 'Doble medallón smash 100g, triple cheddar, bacon crocante y barbacoa.', 9800.00, 1),
((SELECT id FROM categorias WHERE nombre = 'Papas y Acompañamientos' LIMIT 1), 'Papas Cheddar', 'Porción abundante de papas fritas bañadas en queso cheddar fundido.', 5500.00, 1),
((SELECT id FROM categorias WHERE nombre = 'Bebidas' LIMIT 1), 'Coca-Cola 500ml', 'Línea Coca-Cola regular bien helada.', 1800.00, 1),
((SELECT id FROM categorias WHERE nombre = 'Bebidas' LIMIT 1), 'Cerveza IPA Artesanal', 'Pinta de 500ml de IPA tirada.', 3200.00, 0); -- Viene pausada por defecto para que pruebes el botón

-- 4. Crear Mesas de prueba con hashes ya definidos
INSERT INTO mesas (id_restaurante, numero_mesa, hash_qr) VALUES 
(1, 1, 'hash_prueba_mesa_1'),
(1, 2, 'hash_prueba_mesa_2')
ON DUPLICATE KEY UPDATE id=id;


-- ==========================================================
-- FASE 1: ÍNDICES DE RENDIMIENTO MULTI-TENANT Y RATE LIMITING
-- ==========================================================

-- 1. Índice compuesto para Panel de Cocina (api_pedidos.php), SSE y Historial
CREATE INDEX idx_pedidos_rest_estado_fecha 
ON pedidos (id_restaurante, estado, fecha_creacion);

-- 2. Índice compuesto para Control de Caja (caja.php / procesar_cierre.php)
CREATE INDEX idx_pedidos_rest_cierre_estado 
ON pedidos (id_restaurante, id_cierre, estado);

-- 3. Índice para filtrado rápido de productos disponibles por categoría (cliente/menu.php)
CREATE INDEX idx_productos_cat_disponible 
ON productos (id_categoria, disponible);

-- 4. Índice para historial de cierres de caja por restaurante y fecha
CREATE INDEX idx_cierres_rest_fecha 
ON cierres_caja (id_restaurante, fecha_cierre);

-- 5. Tabla ligera para Rate Limiting por IP y Endpoint
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(60) NOT NULL,
    intentos INT NOT NULL DEFAULT 1,
    ventana_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ip_endpoint (ip_address, endpoint),
    INDEX idx_ventana (ventana_inicio)
) ENGINE=InnoDB;

-- ==========================================================
-- FASE 4: SOPORTE DE API TOKEN PARA APP ANDROID (RETROFIT)
-- ==========================================================
ALTER TABLE restaurantes 
ADD COLUMN api_token VARCHAR(64) NULL UNIQUE AFTER password;

-- ==========================================================
-- FASE 5: CUENTA ACUMULADA POR MESA Y SERVICIO DE MESA
-- ==========================================================
ALTER TABLE restaurantes
ADD COLUMN cobra_servicio_mesa TINYINT(1) NOT NULL DEFAULT 0 AFTER logo,
ADD COLUMN precio_servicio_mesa DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER cobra_servicio_mesa;

ALTER TABLE pedidos
ADD COLUMN cargo_servicio_mesa DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total,
ADD COLUMN pidio_cuenta TINYINT(1) NOT NULL DEFAULT 0 AFTER cargo_servicio_mesa,
ADD COLUMN cuenta_cerrada TINYINT(1) NOT NULL DEFAULT 0 AFTER pidio_cuenta;

CREATE INDEX idx_pedidos_mesa_abierta ON pedidos (id_restaurante, id_mesa, cuenta_cerrada, estado);

-- ==========================================================
-- FASE 6: REGISTRO DE MOZO QUE ACEPTA/TOMA EL PEDIDO
-- ==========================================================
ALTER TABLE pedidos
ADD COLUMN nombre_mozo VARCHAR(100) NULL DEFAULT NULL AFTER estado;
