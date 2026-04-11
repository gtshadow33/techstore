-- TechStore — Schema MySQL
-- Ejecutar en Railway MySQL o cualquier MySQL 8+

CREATE TABLE IF NOT EXISTS products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255)      NOT NULL,
    price       DECIMAL(10,2)     NOT NULL,
    category    VARCHAR(100)      NOT NULL,
    description TEXT,
    image       VARCHAR(500)      DEFAULT 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=400&q=80',
    stock       INT               NOT NULL DEFAULT 0,
    created_at  TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS usuarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255)      NOT NULL,
    email       VARCHAR(255)      NOTNULL,
   
);

CREATE TABLE IF NOT EXISTS pedidos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT NOT NULL,
    product_id    INT NOT NULL,
    quantity      INT NOT NULL DEFAULT 1,
    price         DECIMAL(10,2) NOT NULL,
    direccion     VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
CREATE TABLE IF NOT EXISTS admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)      NOT NULL UNIQUE,
    password    VARCHAR(255)      NOT NULL  -- bcrypt hash
);

-- Admin por defecto: admin / admin123
-- Reemplaza el hash si cambias la contraseña
INSERT INTO admins (username, password)
VALUES ('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')

INSERT INTO products (name, price, category, description, stock)
VALUES (
    'Laptop Pro X1', 
    1299.99, 
    'Laptops', 
    'Una laptop potente para desarrollo y diseño con procesador de última generación.', 
    15
)
ON DUPLICATE KEY UPDATE username = username;

