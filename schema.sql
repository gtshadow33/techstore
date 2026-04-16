-- 1. Categorias (sin dependencias)
CREATE TABLE IF NOT EXISTS categorias (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
);

-- 2. Usuarios (sin dependencias)
CREATE TABLE IF NOT EXISTS usuarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- 3. Products (depende de categorias)
CREATE TABLE IF NOT EXISTS products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255)  NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    category_id INT           NOT NULL,
    description TEXT,
    image       VARCHAR(500)  DEFAULT 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=400&q=80',
    stock       INT           NOT NULL DEFAULT 0,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categorias(id)
);

-- 4. Pedidos (depende de usuarios y products)
CREATE TABLE IF NOT EXISTS pedidos (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT           NOT NULL,
    product_id INT           NOT NULL,
    quantity   INT           NOT NULL DEFAULT 1,
    price      DECIMAL(10,2) NOT NULL,
    direccion  VARCHAR(255)  NOT NULL,
    estado     ENUM('pendiente','procesando','enviado','entregado','cancelado','stock') DEFAULT 'pendiente',
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 5. Admins (sin dependencias)
CREATE TABLE IF NOT EXISTS admins (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Triggers
CREATE TRIGGER after_pedido_insert
AFTER INSERT ON pedidos
FOR EACH ROW
UPDATE products SET stock = stock - NEW.quantity WHERE id = NEW.product_id;

CREATE TRIGGER before_pedido_delete
BEFORE DELETE ON pedidos
FOR EACH ROW
UPDATE products SET stock = stock + OLD.quantity WHERE id = OLD.product_id;

-- Datos iniciales
INSERT INTO categorias (nombre) VALUES
    ('Laptops'),
    ('Smartphones'),
    ('Periféricos'),
    ('Monitores'),
    ('Componentes');

INSERT INTO admins (username, password)
VALUES ('admin', '$2a$12$bZ.CFXfB.uWx5P6qf8bZT.3v2QsWUKaxARO6WYlLSSQBfCxJEx5gG');

INSERT INTO products (name, price, category_id, description, stock)
VALUES ('Laptop Pro X1', 1299.99, 1, 'Una laptop potente para desarrollo y diseño con procesador de última generación.', 15);

CREATE TRIGGER after_pedido_insert
AFTER INSERT ON pedidos
FOR EACH ROW
UPDATE products SET stock = stock - NEW.quantity WHERE id = NEW.product_id;

CREATE TRIGGER before_pedido_delete
BEFORE DELETE ON pedidos
FOR EACH ROW
UPDATE products SET stock = stock + OLD.quantity WHERE id = OLD.product_id;