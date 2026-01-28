-- MySQL Database Schema for Mori Cakes Online Ordering System

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS mori_cakes;

USE mori_cakes;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    role ENUM('user', 'admin') DEFAULT 'user',
    reset_token VARCHAR(255),
    reset_token_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Menu Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Menu Items table
CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category_id INT,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    rating DECIMAL(3, 1),
    review_count INT DEFAULT 0,
    stock INT DEFAULT 0,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    total_amount DECIMAL(10, 2) NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_address TEXT NOT NULL,
    delivery_date DATE NOT NULL,
    delivery_time VARCHAR(50) NOT NULL,
    special_instructions TEXT,
    status ENUM('pending', 'processing', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Order Items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
);

-- Contact messages table
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Initial data for categories
INSERT INTO categories (name, description) VALUES
('cheese', 'Delicious cheesecakes'),
('strawberry', 'Fresh strawberry cakes'),
('chocolate', 'Rich chocolate cakes'),
('matcha', 'Japanese matcha cakes'),
('coffee', 'Coffee flavored cakes'),
('vanilla', 'Classic vanilla cakes');

-- Default admin users
INSERT INTO users (username, password, email, full_name, role) VALUES
('user', 'user123', 'user@moricakes.com', 'Test User', 'user'),
('admin', 'admin123', 'admin@moricakes.com', 'Test Admin', 'admin'),
('242UT2449E', 'pw0001', 'admin1@moricakes.com', 'Yap Shi Tong', 'admin'),
('242UT2449F', 'pw0002', 'admin2@moricakes.com', 'Jamie Lim Shi Ting', 'admin'),
('243UT246XG', 'pw0003', 'admin3@moricakes.com', 'Ong Yong Quan', 'admin'),
('1201302385', 'pw0004', 'admin4@moricakes.com', 'Mohamed Abdelgabar Mohamed Awad', 'admin');

-- Initial data for menu items (cakes)
INSERT INTO menu_items (name, category_id, price, description, image_url, rating, review_count, stock, is_available) VALUES
-- Cheese Cakes
('Classic New York Cheesecake', 1, 39.90, 'Creamy New York style cheesecake with graham cracker crust, topped with fresh mint leaves', 'https://p3-doubao-search-sign.byteimg.com/labis/5e6145318923fee7193bc6f84ffd0900~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060523&x-signature=%2Fy1FqF%2BUQ5rAbNDFSw3uWQupKHw%3D', 4.8, 156, 50, TRUE),
('Baked Cheesecake', 1, 42.90, 'Rich baked cheesecake with a smooth texture and caramelized top', 'https://p11-doubao-search-sign.byteimg.com/tos-cn-i-be4g95zd3a/2313347519743393804~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060523&x-signature=z5BKb%2FVflTPJG6njr2ekEUrWmPE%3D', 4.7, 142, 50, TRUE),

-- Strawberry Cakes
('Strawberry Shortcake', 2, 36.90, 'Light sponge cake layered with fresh strawberries and whipped cream', 'https://p11-doubao-search-sign.byteimg.com/labis/dcb052c92380d41db963e4f27f84de61~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060523&x-signature=WwEi93itH3uE0%2BFewQcC3zRrFKE%3D', 4.9, 178, 50, TRUE),
('Strawberry Square Cake', 2, 38.90, 'Square-shaped strawberry cake with layers of fresh strawberries and vanilla cream', 'https://p11-doubao-search-sign.byteimg.com/labis/da55c109bb9847ec513b8e32f22edf35~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060523&x-signature=uligxdhjRy9uo%2FuYrC9cR%2Bb2uqg%3D', 4.8, 165, 50, TRUE),

-- Chocolate Cakes
('Chocolate Indulgence Cake', 3, 45.90, 'Rich chocolate cake with chocolate ganache frosting and decorative chocolate roses', 'https://p26-doubao-search-sign.byteimg.com/tos-cn-i-xv4ileqgde/b65e08dc939d4298a831a002b6bfd950~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060523&x-signature=pBEqzjTA%2Fu9fhOh7SjtWqOva45g%3D', 4.9, 203, 50, TRUE),
('Chocolate Truffle Cake', 3, 48.90, 'Decadent chocolate truffle cake with a smooth, velvety texture', 'https://p3-doubao-search-sign.byteimg.com/labis/a6ab8a2f2d0ff3912003c08a9d78ac73~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060523&x-signature=pcyJ4hCLgA0oq1%2Fk03pllCt8BZU%3D', 4.9, 189, 50, TRUE),

-- Matcha Cakes
('Matcha Green Tea Cake', 4, 41.90, 'Japanese matcha cake with fresh strawberries and sweet red bean paste filling', 'https://p3-doubao-search-sign.byteimg.com/labis/cfaa9222aafd352ce37390291b06012f~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060534&x-signature=DqWywH4uo2Z03tUa9irJuHnWY9I%3D', 4.5, 123, 50, TRUE),
('Matcha Mousse Cake', 4, 43.90, 'Light and fluffy matcha mousse cake with a smooth green tea flavor', 'https://p11-doubao-search-sign.byteimg.com/labis/7e19951608c2a4c763aff2e6e52a64fb~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060534&x-signature=jRqSBOCreruYKKa8UM%2BreVYI8%2FE%3D', 4.6, 135, 50, TRUE),

-- Coffee Cakes
('Coffee Layer Cake', 5, 39.90, 'Rich coffee cake with layers of coffee-infused buttercream and chocolate ganache', 'https://p26-doubao-search-sign.byteimg.com/tos-cn-i-be4g95zd3a/1024821038558740503~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060534&x-signature=EKFyqmaKetNKuxH0OXEIiJV1rZY%3D', 4.7, 148, 50, TRUE),
('Hazelnut Coffee Cake', 5, 42.90, 'Delicious coffee cake with hazelnuts and cranberries, drizzled with vanilla glaze', 'https://p3-doubao-search-sign.byteimg.com/tos-cn-i-be4g95zd3a/1018781790562025500~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060534&x-signature=PepHoZADYB8hT%2FssVQe%2Fjy1B5Qc%3D', 4.8, 156, 50, TRUE),

-- Vanilla Cakes
('Vanilla Bean Cake', 6, 34.90, 'Classic vanilla cake with vanilla bean buttercream and fresh strawberries', 'https://p26-doubao-search-sign.byteimg.com/tos-cn-i-be4g95zd3a/981088101005787235~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060534&x-signature=x3H6d%2FAeTJM0syEI5r%2BXplwJ580%3D', 4.6, 155, 50, TRUE),
('Vanilla Sponge Cake', 6, 32.90, 'Light and fluffy vanilla sponge cake served with vanilla pods and fresh mint', 'https://p3-doubao-search-sign.byteimg.com/labis/f725f3a7dcda035ed535135fa6c8e22a~tplv-be4g95zd3a-image.jpeg?lk3s=feb11e32&x-expires=1784060534&x-signature=9lB4nLoq7tNlydp9XhDm8RvHTIo%3D', 4.5, 142, 50, TRUE);
