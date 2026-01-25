<?php
// Mori Cakes - Complete Version
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'mori_cakes';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    $pdo = null;
}

// Get current user session data
$currentUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;

// Get menu items for display
$allMenuItems = [];
if (function_exists('getMenuItems')) {
    $allMenuItems = getMenuItems();
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'register':
            $response = handleRegister($_POST);
            break;
        case 'login':
            $response = handleLogin($_POST);
            break;
        case 'logout':
            $response = handleLogout();
            break;
        case 'checkSession':
            $response = checkUserSession();
            break;
        case 'placeOrder':
            $response = processOrder($_POST);
            break;
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_order_history':
            $response = getOrderHistory();
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            break;
    }
}



// Process user authentication
function processUserAuth() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    try {
        switch ($action) {
            case 'login':
                $response = handleLogin($_POST);
                break;
            case 'register':
                $response = handleRegister($_POST);
                break;
            case 'logout':
                $response = handleLogout();
                break;
            case 'check_session':
                $response = checkUserSession();
                break;
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

// Handle user login
function handleLogin($postData) {
    global $pdo;
    
    $username = $postData['username'] ?? '';
    $password = $postData['password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username and password are required'];
    }
    
    // Check users from database
    if ($pdo) {
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && $password === $user['password']) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ];
            return ['success' => true, 'user' => $_SESSION['user']];
        }
    }
    
    return ['success' => false, 'message' => 'Invalid username or password'];
}

// Handle user registration
function handleRegister($postData) {
    global $pdo;
    
    $username = $postData['username'] ?? '';
    $password = $postData['password'] ?? '';
    $email = $postData['email'] ?? '';
    $name = $postData['name'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password) || empty($email) || empty($name)) {
        return ['success' => false, 'message' => 'All fields are required'];
    }
    
    if (!$pdo) {
        return ['success' => false, 'message' => 'Registration is currently unavailable'];
    }
    
    // Check if username exists
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username already exists'];
    }
    
    // Check if email exists
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already exists'];
    }
    
    // Create new user (plaintext password for university project)
    $sql = "INSERT INTO users (username, password, email, full_name) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$username, $password, $email, $name]);
    
    if ($result) {
        return ['success' => true, 'message' => 'Registration successful'];
    } else {
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

// Handle user logout
function handleLogout() {
    session_destroy();
    return ['success' => true, 'message' => 'Logged out successfully'];
}

// Check user session
function checkUserSession() {
    if (isset($_SESSION['user'])) {
        return ['success' => true, 'user' => $_SESSION['user']];
    }
    return ['success' => false, 'message' => 'Not logged in'];
}

// Get order history
function getOrderHistory() {
    global $pdo;
    
    if (!isset($_SESSION['user'])) {
        return ['success' => false, 'message' => 'User not logged in'];
    }
    
    try {
        $userId = $_SESSION['user']['id'];
        
        // Get orders with their items
        $sql = "SELECT o.*, 
                       oi.id as item_id, 
                       oi.menu_item_id, 
                       oi.quantity, 
                       oi.price,
                       mi.name as item_name
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
                WHERE o.user_id = ?
                ORDER BY o.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll();
        
        // Group items by order
        $orders = [];
        foreach ($results as $row) {
            $orderId = $row['id'];
            
            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'order_id' => 'ORD-' . date('Ymd', strtotime($row['created_at'])) . '-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
                    'total_amount' => $row['total_amount'],
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'items' => []
                ];
            }
            
            if ($row['item_id']) {
                $orders[$orderId]['items'][] = [
                    'item_name' => $row['item_name'],
                    'quantity' => $row['quantity'],
                    'price' => $row['price']
                ];
            }
        }
        
        return ['success' => true, 'orders' => array_values($orders)];
        
    } catch (PDOException $e) {
        error_log("Database error in getOrderHistory: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to load order history'];
    }
}

// Process order
function processOrder($postData) {
    global $pdo;
    
    if (!isset($_SESSION['user'])) {
        return ['success' => false, 'message' => 'User not logged in'];
    }

    if (!$pdo) {
        return ['success' => false, 'message' => 'Order processing is currently unavailable'];
    }
    
    try {
        $orderData = json_decode($postData['orderData'], true);
        
        // Validate required fields
        if (!isset($orderData['items'], $orderData['totalAmount'], 
                  $orderData['recipientName'], $orderData['recipientPhone'], 
                  $orderData['recipientAddress'], $orderData['deliveryDate'])) {
            throw new Exception('Missing required order information');
        }
        
        $userId = $_SESSION['user']['id'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert order
        $sql = "INSERT INTO orders (user_id, total_amount, recipient_name, recipient_phone, 
                                   recipient_address, delivery_date, delivery_time, 
                                   special_instructions, status, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $orderData['totalAmount'],
            $orderData['recipientName'],
            $orderData['recipientPhone'],
            $orderData['recipientAddress'],
            $orderData['deliveryDate'],
            $orderData['deliveryTime'] ?? 'Standard',
            $orderData['specialInstructions'] ?? '',
            'pending',
            'unpaid'
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // Insert order items
        foreach ($orderData['items'] as $item) {
            $sql = "INSERT INTO order_items (order_id, menu_item_id, quantity, price) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $orderId,
                $item['id'],
                $item['quantity'],
                $item['price']
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        $formattedOrderId = 'ORD-' . date('Ymd') . '-' . str_pad($orderId, 4, '0', STR_PAD_LEFT);
        
        return [
            'success' => true,
            'orderId' => $formattedOrderId,
            'message' => 'Order placed successfully',
            'orderDetails' => [
                'orderId' => $formattedOrderId,
                'totalAmount' => $orderData['totalAmount'],
                'items' => $orderData['items'],
                'deliveryInfo' => [
                    'name' => $orderData['recipientName'],
                    'phone' => $orderData['recipientPhone'],
                    'address' => $orderData['recipientAddress'],
                    'date' => $orderData['deliveryDate']
                ],
                'status' => 'pending',
                'placedAt' => date('Y-m-d H:i:s')
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error placing order: ' . $e->getMessage()
        ];
    }
}



// Function to get menu items from database
function getMenuItems($category = null) {
    global $pdo;
    try {
        if (!$pdo) {
            error_log("Database connection not available");
            return [];
        }
        
        $sql = "SELECT mi.*, c.name AS category_name 
                FROM menu_items mi
                LEFT JOIN categories c ON mi.category_id = c.id
                WHERE mi.is_available = TRUE";
        $params = [];
        
        if ($category) {
            $sql .= " AND c.name = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY mi.id ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        
        // Format the items to match the expected structure
        $formattedItems = [];
        foreach ($items as $item) {
            $formattedItems[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'category' => $item['category_name'],
                'price' => (float) $item['price'],
                'image' => $item['image_url'],
                'description' => $item['description'],
                'rating' => (float) $item['rating'],
                'reviewCount' => (int) $item['review_count']
            ];
        }
        
        return $formattedItems;
    } catch (PDOException $e) {
        error_log("Database error in getMenuItems: " . $e->getMessage());
        return [];
    }
}

// Get current user from session
$currentUser = $_SESSION['user'] ?? null;

// Get all menu items
$allMenuItems = getMenuItems();
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
    <script>
        // Initialize menu items from PHP data
        window.menuItems = <?php echo json_encode($allMenuItems); ?> || [];
        window.currentUserData = <?php echo json_encode($currentUser); ?> || null;
    </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mori Cakes - Online Ordering</title>
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome -->
  <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
  
  <!-- Tailwind Configuration -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#ff6b6b',
            secondary: '#FFA149',
            dark: '#373B2F',
            light: '#F5F5F5',
            accent: '#FFD166',
            'bg-gray-200': '#557937'
          },
          fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif'],
          }
        }
      }
    }
  </script>
  
  <style type="text/tailwindcss">
    @layer utilities {
      .btn-primary {
        @apply bg-primary text-white py-2 px-4 rounded-lg hover:bg-primary/90 transition-colors duration-200;
      }
      .btn-secondary {
        @apply bg-secondary text-white py-2 px-4 rounded-lg hover:bg-secondary/90 transition-colors duration-200;
      }
      .btn-outline {
        @apply border border-primary text-primary py-2 px-4 rounded-lg hover:bg-primary hover:text-white transition-colors duration-200;
      }
      .input-primary {
        @apply border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent;
      }
      .card-hover {
        @apply hover:shadow-lg transition-shadow duration-300;
      }
      .modal-content {
        @apply bg-white rounded-xl p-6 max-w-md w-full mx-4 transform transition-all duration-300 relative z-10;
      }
      .status-badge {
        @apply px-2 py-1 rounded-full text-xs font-semibold;
      }
      .status-delivered {
        @apply bg-green-100 text-green-800;
      }
      .status-processing {
        @apply bg-yellow-100 text-yellow-800;
      }
      .status-cancelled {
        @apply bg-red-100 text-red-800;
      }
    }
  </style>
</head>
<body class="bg-gray-50 font-sans">
  <!-- Header -->
  <header class="bg-white shadow-sm sticky top-0 z-50">
    <div class="container mx-auto px-4 py-4">
      <div class="flex items-center justify-between">
        <!-- Logo -->
        <div class="flex items-center space-x-2">
          <i class="fa fa-birthday-cake text-primary text-2xl"></i>
          <h1 class="text-xl font-bold text-dark">Mori Cakes</h1>
        </div>
        
        <!-- Navigation -->
        <nav class="hidden md:flex items-center space-x-6">
          <a href="#home" class="text-gray-600 hover:text-primary transition-colors">Home</a>
          <a href="#menu" class="text-gray-600 hover:text-primary transition-colors">Menu</a>
          <a href="#about" class="text-gray-600 hover:text-primary transition-colors">About</a>
          <a href="#contact" class="text-gray-600 hover:text-primary transition-colors">Contact</a>
        </nav>
        
        <!-- User Actions -->
        <div class="flex items-center space-x-4">
          <!-- Cart Button -->
          <button id="cart-button" class="relative p-2 hover:text-primary transition-colors">
            <i class="fa fa-shopping-cart text-xl"></i>
            <span id="cart-count" class="absolute -top-1 -right-1 bg-primary text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">0</span>
          </button>
          
          <!-- User Menu -->
          <div class="relative">
            <button id="user-menu-button" class="flex items-center space-x-2 hover:text-primary transition-colors">
              <i class="fa fa-user text-xl"></i>
              <span class="hidden md:inline text-sm font-medium">
                <?php echo $currentUser ? $currentUser['full_name'] : 'Account'; ?>
              </span>
            </button>
            
            <!-- User Dropdown Menu -->
            <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-50">
              <?php if ($currentUser): ?>
                <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="showNotification('You are logged in as <?php echo $currentUser['username']; ?>')">
                  <i class="fa fa-user-circle mr-2"></i>Profile
                </a>
                <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="openOrderHistoryModal()">
                  <i class="fa fa-history mr-2"></i>Order History
                </a>
                <hr class="my-1">
                <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="logout()">
                  <i class="fa fa-sign-out mr-2"></i>Logout
                </a>
              <?php else: ?>
                <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="openLoginModal()">
                  <i class="fa fa-sign-in mr-2"></i>Login
                </a>
                <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="openRegisterModal()">
                  <i class="fa fa-user-plus mr-2"></i>Register
                </a>
              <?php endif; ?>
            </div>
          </div>
          
          <!-- Mobile Menu Button -->
          <button id="mobile-menu-button" class="md:hidden p-2 hover:text-primary transition-colors">
            <i class="fa fa-bars text-xl"></i>
          </button>
        </div>
      </div>
      
      <!-- Mobile Navigation -->
      <div id="mobile-nav" class="hidden md:hidden mt-4 pb-2">
        <nav class="flex flex-col space-y-3">
          <a href="#home" class="text-gray-600 hover:text-primary transition-colors">Home</a>
          <a href="#menu" class="text-gray-600 hover:text-primary transition-colors">Menu</a>
          <a href="#about" class="text-gray-600 hover:text-primary transition-colors">About</a>
          <a href="#contact" class="text-gray-600 hover:text-primary transition-colors">Contact</a>
        </nav>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main>
    <!-- Hero Section -->
    <section id="home" class="bg-gradient-to-r from-primary/10 to-secondary/10 py-16 md:py-24">
      <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl md:text-5xl font-bold text-dark mb-4" data-aos="fade-up">
          Delicious Cakes for Every Occasion
        </h2>
        <p class="text-lg text-gray-600 mb-8 max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="100">
          Handcrafted with love using the finest ingredients. Perfect for birthdays, celebrations, or simply treating yourself.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center" data-aos="fade-up" data-aos-delay="200">
          <a href="#menu" class="btn-primary">
            <i class="fa fa-birthday-cake mr-2"></i>Explore Menu
          </a>
          <a href="#contact" class="btn-outline">
            <i class="fa fa-phone mr-2"></i>Contact Us
          </a>
        </div>
      </div>
    </section>

    <!-- Menu Section -->
    <section id="menu" class="py-16">
      <div class="container mx-auto px-4">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-bold text-dark mb-4">Our Cake Collection</h2>
          <p class="text-gray-600 max-w-2xl mx-auto">
            Choose from our delicious selection of cakes made fresh daily
          </p>
        </div>
        
        <!-- Category Filters -->
        <div class="flex flex-wrap justify-center gap-2 mb-8">
          <button class="category-btn active bg-primary text-white px-4 py-2 rounded-full text-sm font-medium" data-category="all">
            Signature Cakes
          </button>
          <button class="category-btn bg-gray-200 px-4 py-2 rounded-full text-sm font-medium" data-category="cheese">
            Cheese Cakes
          </button>
          <button class="category-btn bg-gray-200 px-4 py-2 rounded-full text-sm font-medium" data-category="strawberry">
            Strawberry Cakes
          </button>
          <button class="category-btn bg-gray-200 px-4 py-2 rounded-full text-sm font-medium" data-category="chocolate">
            Chocolate Cakes
          </button>
          <button class="category-btn bg-gray-200 px-4 py-2 rounded-full text-sm font-medium" data-category="matcha">
            Matcha Cakes
          </button>
          <button class="category-btn bg-gray-200 px-4 py-2 rounded-full text-sm font-medium" data-category="vanilla">
            Vanilla Cakes
          </button>
        </div>
        
        <!-- Menu Items -->
        <div id="menu-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Menu items will be dynamically inserted here -->
          <div class="text-center py-8 text-gray-500 col-span-full">
            <i class="fa fa-spinner fa-spin text-primary text-4xl mb-4"></i>
            <p>Loading delicious cakes...</p>
          </div>
        </div>
      </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-16 bg-gray-100">
      <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
          <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-dark mb-4">About Mori Cakes</h2>
            <p class="text-gray-600">Crafting sweet memories since 2020</p>
          </div>
          
          <div class="bg-white rounded-xl p-6 shadow-sm">
            <p class="text-gray-600 mb-4">
              At Mori Cakes, we believe that every celebration deserves a delicious cake. Our journey began in 2020 with a passion for baking and a commitment to quality.
            </p>
            <p class="text-gray-600 mb-4">
              All our cakes are handcrafted using premium ingredients, with no preservatives or artificial flavors. We take pride in creating beautiful, delicious cakes that bring joy to every occasion.
            </p>
            <p class="text-gray-600">
              Whether you're celebrating a birthday, anniversary, or simply want to treat yourself, we have the perfect cake for you.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-16">
      <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
          <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-dark mb-4">Contact Us</h2>
            <p class="text-gray-600">Have questions or special requests? We'd love to hear from you!</p>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Contact Information -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
              <h3 class="text-xl font-bold text-dark mb-4">Contact Information</h3>
              
              <div class="space-y-4">
                <div class="flex items-start space-x-3">
                  <i class="fa fa-map-marker text-primary mt-1"></i>
                  <div>
                    <h4 class="font-medium text-dark">Location</h4>
                    <p class="text-gray-600">123 Cake Street, Sweet City, Malaysia</p>
                  </div>
                </div>
                
                <div class="flex items-center space-x-3">
                  <i class="fa fa-phone text-primary"></i>
                  <div>
                    <h4 class="font-medium text-dark">Phone</h4>
                    <p class="text-gray-600">+60 123 456 789</p>
                  </div>
                </div>
                
                <div class="flex items-center space-x-3">
                  <i class="fa fa-envelope text-primary"></i>
                  <div>
                    <h4 class="font-medium text-dark">Email</h4>
                    <p class="text-gray-600">info@moricakes.com</p>
                  </div>
                </div>
                
                <div class="flex items-start space-x-3">
                  <i class="fa fa-clock-o text-primary mt-1"></i>
                  <div>
                    <h4 class="font-medium text-dark">Business Hours</h4>
                    <p class="text-gray-600">Monday - Friday: 9:00 AM - 6:00 PM</p>
                    <p class="text-gray-600">Saturday - Sunday: 10:00 AM - 4:00 PM</p>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Contact Form -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
              <h3 class="text-xl font-bold text-dark mb-4">Send us a Message</h3>
              
              <form id="contact-form" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
                  <input type="text" class="input-primary w-full" placeholder="Enter your name" required>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Your Email</label>
                  <input type="email" class="input-primary w-full" placeholder="Enter your email" required>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                  <input type="text" class="input-primary w-full" placeholder="Enter subject" required>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                  <textarea class="input-primary w-full" rows="4" placeholder="Enter your message" required></textarea>
                </div>
                
                <button type="submit" class="btn-primary w-full">
                  <i class="fa fa-paper-plane mr-2"></i>Send Message
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="bg-dark text-white py-8">
    <div class="container mx-auto px-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Company Info -->
        <div>
          <div class="flex items-center space-x-2 mb-4">
            <i class="fa fa-birthday-cake text-primary text-xl"></i>
            <h3 class="text-lg font-bold">Mori Cakes</h3>
          </div>
          <p class="text-gray-400 text-sm">
            Crafting delicious cakes for every occasion. Made with love and the finest ingredients.
          </p>
        </div>
        
        <!-- Quick Links -->
        <div>
          <h3 class="text-lg font-bold mb-4">Quick Links</h3>
          <ul class="space-y-2 text-sm">
            <li><a href="#home" class="text-gray-400 hover:text-primary transition-colors">Home</a></li>
            <li><a href="#menu" class="text-gray-400 hover:text-primary transition-colors">Menu</a></li>
            <li><a href="#about" class="text-gray-400 hover:text-primary transition-colors">About Us</a></li>
            <li><a href="#contact" class="text-gray-400 hover:text-primary transition-colors">Contact</a></li>
          </ul>
        </div>
        
        <!-- Contact Info -->
        <div>
          <h3 class="text-lg font-bold mb-4">Contact Us</h3>
          <ul class="space-y-2 text-sm">
            <li class="flex items-center space-x-2 text-gray-400">
              <i class="fa fa-phone text-primary"></i>
              <span>+60 123 456 789</span>
            </li>
            <li class="flex items-center space-x-2 text-gray-400">
              <i class="fa fa-envelope text-primary"></i>
              <span>info@moricakes.com</span>
            </li>
            <li class="flex items-center space-x-2 text-gray-400">
              <i class="fa fa-map-marker text-primary"></i>
              <span>123 Cake Street, Sweet City</span>
            </li>
          </ul>
        </div>
      </div>
      
      <div class="border-t border-gray-700 mt-8 pt-6 text-center text-sm text-gray-400">
        <p>&copy; 2024 Mori Cakes. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <!-- Cart Drawer -->
  <div id="cart-drawer" class="fixed top-0 right-0 h-full w-full md:w-96 bg-white shadow-lg transform translate-x-full transition-transform duration-300 z-50">
    <div class="flex flex-col h-full">
      <!-- Cart Header -->
      <div class="p-4 border-b">
        <div class="flex items-center justify-between">
          <h3 class="text-xl font-bold text-dark">Your Cart</h3>
          <button id="close-cart-btn" class="text-gray-500 hover:text-primary">
            <i class="fa fa-times text-xl"></i>
          </button>
        </div>
      </div>
      
      <!-- Cart Items -->
      <div id="cart-items" class="flex-1 overflow-y-auto p-4">
        <!-- Cart items will be dynamically inserted here -->
        <div class="text-center py-8 text-gray-500">
          <i class="fa fa-shopping-cart text-4xl mb-4"></i>
          <p>Your cart is empty</p>
          <p class="text-sm">Add some delicious cakes to get started!</p>
        </div>
      </div>
      
      <!-- Cart Footer -->
      <div class="p-4 border-t">
        <div id="cart-summary" class="hidden space-y-3 mb-4">
          <div class="flex justify-between items-center">
            <span class="text-gray-600">Subtotal:</span>
            <span class="font-medium text-dark">RM<span id="cart-subtotal">0.00</span></span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-600">Delivery Fee:</span>
            <span class="font-medium text-dark">RM<span id="delivery-fee">12.00</span></span>
          </div>
          <div class="flex justify-between items-center border-t pt-3">
            <span class="font-bold text-dark">Total:</span>
            <span class="font-bold text-primary text-lg">RM<span id="cart-total">0.00</span></span>
          </div>
        </div>
        
        <button id="checkout-btn" class="btn-primary w-full" disabled>
          <i class="fa fa-arrow-right mr-2"></i>Checkout
        </button>
      </div>
    </div>
  </div>

  <!-- Modals -->
  <!-- Login Modal -->
  <div id="login-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-6">
        <div class="text-center flex-1">
          <h3 class="text-xl font-bold text-dark mb-2">Login to Your Account</h3>
          <p class="text-gray-600 text-sm">Access your account to place orders</p>
        </div>
        <button class="text-gray-400 hover:text-gray-600" onclick="closeModal('login-modal')">
          <i class="fa fa-times text-xl"></i>
        </button>
      </div>
      
      <form id="login-form" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
          <input type="text" id="login-username" class="input-primary w-full" placeholder="Enter your username" required>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input type="password" id="login-password" class="input-primary w-full" placeholder="Enter your password" required>
        </div>
        
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <input type="checkbox" id="remember-me" class="mr-2">
            <label for="remember-me" class="text-sm text-gray-600">Remember me</label>
          </div>
          <a href="#" class="text-sm text-primary hover:underline" onclick="openForgotPasswordModal()">Forgot password?</a>
        </div>
        
        <button type="submit" class="btn-primary w-full">
          <i class="fa fa-sign-in mr-2"></i>Login
        </button>
      </form>
      
      <div class="mt-4 text-center text-sm">
        <p class="text-gray-600">Don't have an account?</p>
        <a href="#" class="text-primary hover:underline" onclick="openRegisterModal()">Create account</a>
      </div>
      
      <!-- Test User Info -->
      <div class="mt-6 p-3 bg-gray-50 rounded-lg text-sm">
        <p class="text-gray-600 font-medium mb-1">Test Accounts:</p>
        <p class="text-gray-500">Admin: admin / admin123</p>
        <p class="text-gray-500">User: user / user123</p>
      </div>
    </div>
  </div>

  <!-- Register Modal -->
  <div id="register-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-6">
        <div class="text-center flex-1">
          <h3 class="text-xl font-bold text-dark mb-2">Create Your Account</h3>
          <p class="text-gray-600 text-sm">Join us to place orders and track your history</p>
        </div>
        <button class="text-gray-400 hover:text-gray-600" onclick="closeModal('register-modal')">
          <i class="fa fa-times text-xl"></i>
        </button>
      </div>
      
      <form id="register-form" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
          <input type="text" id="register-name" class="input-primary w-full" placeholder="Enter your full name" required>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
          <input type="text" id="register-username" class="input-primary w-full" placeholder="Choose a username" required>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input type="email" id="register-email" class="input-primary w-full" placeholder="Enter your email" required>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input type="password" id="register-password" class="input-primary w-full" placeholder="Choose a password" required>
        </div>
        
        <button type="submit" class="btn-primary w-full">
          <i class="fa fa-user-plus mr-2"></i>Register
        </button>
      </form>
      
      <div class="mt-4 text-center text-sm">
        <p class="text-gray-600">Already have an account?</p>
        <a href="#" class="text-primary hover:underline" onclick="openLoginModal()">Login</a>
      </div>
    </div>
  </div>

  <!-- Checkout Modal -->
  <div id="checkout-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-content max-w-lg">
      <div class="flex justify-between items-center mb-6">
        <div class="text-center flex-1">
          <h3 class="text-xl font-bold text-dark mb-2">Complete Your Order</h3>
          <p class="text-gray-600 text-sm">Fill in your delivery information</p>
        </div>
        <button class="text-gray-400 hover:text-gray-600" onclick="closeModal('checkout-modal')">
          <i class="fa fa-times text-xl"></i>
        </button>
      </div>
      
      <form id="checkout-form" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
          <input type="text" id="checkout-name" class="input-primary w-full" placeholder="Enter your full name" required>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
          <input type="tel" id="checkout-phone" class="input-primary w-full" placeholder="Enter your phone number" required>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
          <textarea id="checkout-address" class="input-primary w-full" rows="3" placeholder="Enter your delivery address" required></textarea>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Date</label>
          <input type="date" id="checkout-date" class="input-primary w-full" required>
        </div>
        
        <div class="bg-gray-50 p-4 rounded-lg">
          <h4 class="font-medium text-dark mb-2">Order Summary</h4>
          <div id="checkout-summary" class="space-y-2 text-sm">
            <!-- Summary will be dynamically inserted here -->
          </div>
        </div>
        
        <button type="submit" class="btn-primary w-full">
          <i class="fa fa-check mr-2"></i>Place Order
        </button>
      </form>
    </div>
  </div>

  <!-- Order History Modal -->
  <div id="order-history-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeModal('order-history-modal')"></div>
    <div class="modal-content max-w-2xl max-h-[80vh] overflow-y-auto relative z-10">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h3 class="text-xl font-bold text-dark mb-2">Your Order History</h3>
          <p class="text-gray-600 text-sm">Track your previous orders</p>
        </div>
        <button class="text-gray-500 hover:text-gray-700 transition-colors" onclick="closeModal('order-history-modal')">
          <i class="fa fa-times text-xl"></i>
        </button>
      </div>
      
      <div id="order-history-container" class="space-y-4">
        <!-- Order history will be dynamically inserted here -->
        <div class="text-center py-8 text-gray-500">
          <i class="fa fa-spinner fa-spin text-primary text-4xl mb-4"></i>
          <p>Loading order history...</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Overlay -->
  <div id="overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 cursor-pointer hover:bg-opacity-50 transition-none"></div>

  <!-- Notification Container -->
  <div id="notification-container" class="fixed top-20 right-4 z-1000 space-y-2"></div>

  <!-- JavaScript -->
  <script>
    // Menu items from PHP
    window.menuItems = <?php echo json_encode($allMenuItems); ?>;
    
    // User data from PHP
    window.currentUserData = <?php echo json_encode($currentUser); ?>;
    
    // Cart data
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    
    // DOM Elements
    const elements = {
      menuContainer: document.getElementById('menu-container'),
      cartButton: document.getElementById('cart-button'),
      cartDrawer: document.getElementById('cart-drawer'),
      closeCartBtn: document.getElementById('close-cart-btn'),
      cartItems: document.getElementById('cart-items'),
      cartSummary: document.getElementById('cart-summary'),
      checkoutBtn: document.getElementById('checkout-btn'),
      cartCount: document.getElementById('cart-count'),
      overlay: document.getElementById('overlay'),
      userMenuButton: document.getElementById('user-menu-button'),
      userDropdown: document.getElementById('user-dropdown'),
      mobileMenuButton: document.getElementById('mobile-menu-button'),
      mobileNav: document.getElementById('mobile-nav'),
      notificationContainer: document.getElementById('notification-container')
    };
    
    // Initialize the application
    function init() {
      initializeMenu();
      initializeEventListeners();
      updateCartUI();
      setMinDeliveryDate();
      updateUserUI();
    }
    
    // Initialize menu
    function initializeMenu() {
      if (!elements.menuContainer) return;
      
      elements.menuContainer.innerHTML = '';
      
      if (!window.menuItems || window.menuItems.length === 0) {
        elements.menuContainer.innerHTML = '<div class="text-center py-8 text-gray-500 col-span-full"><i class="fa fa-birthday-cake text-4xl mb-2"></i><p>No menu items available</p><p class="text-sm">Please try again later</p></div>';
        return;
      }
      
      // For initial load, show 6 signature cakes (one from each category)
      const signatureCakes = [];
      const categories = ['cheese', 'strawberry', 'chocolate', 'matcha', 'coffee', 'vanilla'];
      
      categories.forEach(cat => {
        const categoryCakes = window.menuItems.filter(item => item.category === cat);
        if (categoryCakes.length > 0) {
          signatureCakes.push(categoryCakes[0]); // Take the first cake from each category
        }
      });
      
      signatureCakes.forEach(item => {
        const menuItem = createMenuItem(item);
        elements.menuContainer.appendChild(menuItem);
      });
    }
    
    // Create menu item
    function createMenuItem(item) {
      const menuItem = document.createElement('div');
      menuItem.className = `menu-item card-hover bg-white rounded-xl shadow overflow-hidden ${item.category}`;
      menuItem.setAttribute('data-category', item.category);
      
      const ratingHTML = createRatingStars(item.rating);
      
      menuItem.innerHTML = `
        <div class="relative">
          <img src="${item.image}" alt="${item.name}" class="w-full h-48 object-cover">
          <div class="absolute top-2 right-2 bg-primary text-white text-sm font-bold px-2 py-1 rounded">
            RM${item.price.toFixed(1)}
          </div>
        </div>
        <div class="p-4">
          <h3 class="text-lg font-bold mb-2">${item.name}</h3>
          <p class="text-gray-600 text-sm mb-3">${item.description}</p>
          <div class="flex items-center mb-3">
            <div class="flex mr-2">
              ${ratingHTML}
            </div>
            <span class="text-sm text-gray-500">(${item.reviewCount})</span>
          </div>
          <button class="add-to-cart-btn btn-primary w-full" data-id="${item.id}">
            <i class="fa fa-shopping-cart mr-2"></i>Add to Cart
          </button>
        </div>
      `;
      
      return menuItem;
    }
    
    // Create star rating HTML
    function createRatingStars(rating) {
      const fullStars = Math.floor(rating);
      const halfStar = rating % 1 >= 0.5;
      const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
      
      let starsHTML = '';
      
      // Add full stars
      for (let i = 0; i < fullStars; i++) {
        starsHTML += '<i class="fa fa-star text-yellow-400"></i>';
      }
      
      // Add half star
      if (halfStar) {
        starsHTML += '<i class="fa fa-star-half-o text-yellow-400"></i>';
      }
      
      // Add empty stars
      for (let i = 0; i < emptyStars; i++) {
        starsHTML += '<i class="fa fa-star-o text-yellow-400"></i>';
      }
      
      return starsHTML;
    }
    
    // Initialize event listeners
    function initializeEventListeners() {
      // Category filter buttons
      document.querySelectorAll('.category-btn').forEach(button => {
        button.addEventListener('click', function() {
          const category = this.getAttribute('data-category');
          filterMenuItems(category);
          
          // Update active button
          document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('active', 'bg-primary', 'text-white');
            btn.classList.add('bg-gray-200');
          });
          this.classList.add('active', 'bg-primary', 'text-white');
          this.classList.remove('bg-gray-200');
        });
      });
      
      // Cart button click event
      if (elements.cartButton) {
        elements.cartButton.addEventListener('click', function() {
          toggleCartDrawer();
        });
      }
      
      // Close cart button
      if (elements.closeCartBtn) {
        elements.closeCartBtn.addEventListener('click', function() {
          toggleCartDrawer();
        });
      }
      
      // Add to cart buttons (delegated event)
      document.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-to-cart-btn') || e.target.closest('.add-to-cart-btn')) {
          const button = e.target.classList.contains('add-to-cart-btn') ? e.target : e.target.closest('.add-to-cart-btn');
          const itemId = parseInt(button.getAttribute('data-id'));
          addToCart(itemId, e);
        }
      });
      
      // Checkout button
      if (elements.checkoutBtn) {
        elements.checkoutBtn.addEventListener('click', function() {
          if (window.currentUserData) {
            openCheckoutModal();
          } else {
            showNotification('Please login to checkout', 'warning');
            openLoginModal();
          }
        });
      }
      
      // User menu button
      if (elements.userMenuButton) {
        elements.userMenuButton.addEventListener('click', function() {
          elements.userDropdown.classList.toggle('hidden');
        });
      }
      
      // Mobile menu button
      if (elements.mobileMenuButton) {
        elements.mobileMenuButton.addEventListener('click', function() {
          elements.mobileNav.classList.toggle('hidden');
        });
      }
      
      // Overlay click
      if (elements.overlay) {
        elements.overlay.addEventListener('click', function() {
          closeAllModals();
          toggleCartDrawer(false);
        });
      }
      
      // Close dropdowns when clicking outside
      document.addEventListener('click', function(e) {
        if (!elements.userMenuButton.contains(e.target) && !elements.userDropdown.contains(e.target)) {
          elements.userDropdown.classList.add('hidden');
        }
      });
      
      // Form submissions
      handleFormSubmissions();
      
      // Checkout form
      const checkoutForm = document.getElementById('checkout-form');
      if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
          e.preventDefault();
          placeOrder();
        });
      }
      
      // Contact form
      const contactForm = document.getElementById('contact-form');
      if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
          e.preventDefault();
          showNotification('Thank you for your message! We will get back to you soon.', 'success');
          contactForm.reset();
        });
      }
    }
    
    // Handle form submissions
    function handleFormSubmissions() {
      // Login form
      const loginForm = document.getElementById('login-form');
      if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
          e.preventDefault();
          login();
        });
      }
      
      // Register form
      const registerForm = document.getElementById('register-form');
      if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
          e.preventDefault();
          register();
        });
      }
    }
    
    // Filter menu items by category
    function filterMenuItems(category) {
      if (!elements.menuContainer) return;
      
      elements.menuContainer.innerHTML = '';
      
      if (!window.menuItems || window.menuItems.length === 0) {
        elements.menuContainer.innerHTML = '<div class="text-center py-8 text-gray-500 col-span-full"><i class="fa fa-birthday-cake text-4xl mb-2"></i><p>No menu items available</p><p class="text-sm">Please try again later</p></div>';
        return;
      }
      
      let cakesToShow = [];
      
      if (category === 'all') {
        // For "Signature Cakes", show one cake from each category
        const categories = ['cheese', 'strawberry', 'chocolate', 'matcha', 'coffee', 'vanilla'];
        categories.forEach(cat => {
          const categoryCakes = window.menuItems.filter(item => item.category === cat);
          if (categoryCakes.length > 0) {
            cakesToShow.push(categoryCakes[0]);
          }
        });
      } else {
        // For specific categories, show all cakes in that category
        cakesToShow = window.menuItems.filter(item => item.category === category);
      }
      
      if (cakesToShow.length === 0) {
        elements.menuContainer.innerHTML = `<div class="text-center py-8 text-gray-500 col-span-full"><i class="fa fa-birthday-cake text-4xl mb-2"></i><p>No ${category} cakes available</p><p class="text-sm">Please try another category</p></div>`;
        return;
      }
      
      cakesToShow.forEach(item => {
        const menuItem = createMenuItem(item);
        elements.menuContainer.appendChild(menuItem);
      });
    }
    
    // Add item to cart
    function addToCart(itemId, event) {
      const item = window.menuItems.find(item => item.id === itemId);
      if (!item) return;
      
      const existingItem = cart.find(cartItem => cartItem.id === itemId);
      
      if (existingItem) {
        existingItem.quantity += 1;
      } else {
        cart.push({
          id: item.id,
          name: item.name,
          price: item.price,
          image: item.image,
          quantity: 1
        });
      }
      
      // Save to localStorage
      localStorage.setItem('cart', JSON.stringify(cart));
      
      // Update UI
      updateCartUI();
      
      // Show notification
      showNotification(`${item.name} has been added to your cart!`, 'success');
      
      // Animate item flying to cart
      if (event) {
        animateAddToCart(event.target.closest('.add-to-cart-btn'));
      }
    }
    
    // Animate item flying to cart
    function animateAddToCart(button) {
      if (!button || !elements.cartButton) return;
      
      // Create a flying element
      const flyingElement = document.createElement('div');
      flyingElement.className = 'fixed z-50 bg-primary text-white rounded-full w-8 h-8 flex items-center justify-center';
      flyingElement.innerHTML = '<i class="fa fa-shopping-cart"></i>';
      document.body.appendChild(flyingElement);
      
      // Get positions
      const buttonRect = button.getBoundingClientRect();
      const cartRect = elements.cartButton.getBoundingClientRect();
      
      // Set initial position
      flyingElement.style.left = `${buttonRect.left + buttonRect.width / 2 - 16}px`;
      flyingElement.style.top = `${buttonRect.top + buttonRect.height / 2 - 16}px`;
      flyingElement.style.transition = 'all 0.6s cubic-bezier(0.2, 0.8, 0.2, 1)';
      
      // Force reflow
      void flyingElement.offsetWidth;
      
      // Set final position
      flyingElement.style.left = `${cartRect.left + cartRect.width / 2 - 16}px`;
      flyingElement.style.top = `${cartRect.top + cartRect.height / 2 - 16}px`;
      flyingElement.style.transform = 'scale(0.5)';
      flyingElement.style.opacity = '0';
      
      // Remove element after animation
      setTimeout(() => {
        document.body.removeChild(flyingElement);
        
        // Animate cart icon
        if (elements.cartButton) {
          elements.cartButton.style.transform = 'scale(1.2)';
          setTimeout(() => {
            elements.cartButton.style.transform = 'scale(1)';
          }, 300);
        }
      }, 600);
    }

    // Remove item from cart
    function removeFromCart(itemId) {
      cart = cart.filter(item => item.id !== itemId);
      
      // Save to localStorage
      localStorage.setItem('cart', JSON.stringify(cart));
      
      // Update UI
      updateCartUI();
      
      showNotification('Item removed from cart', 'info');
    }
    
    // Update cart item quantity
    function updateCartItemQuantity(itemId, quantity) {
      const item = cart.find(item => item.id === itemId);
      if (item) {
        item.quantity = Math.max(1, quantity);
        
        // Save to localStorage
        localStorage.setItem('cart', JSON.stringify(cart));
        
        // Update UI
        updateCartUI();
      }
    }
    
    // Update cart UI
    function updateCartUI() {
      // Update cart count
      const cartCount = cart.reduce((total, item) => total + item.quantity, 0);
      elements.cartCount.textContent = cartCount;
      
      // Update cart items
      if (cart.length === 0) {
        elements.cartItems.innerHTML = `
          <div class="text-center py-8 text-gray-500">
            <i class="fa fa-shopping-cart text-4xl mb-4"></i>
            <p>Your cart is empty</p>
            <p class="text-sm">Add some delicious cakes to get started!</p>
          </div>
        `;
        elements.cartSummary.classList.add('hidden');
        elements.checkoutBtn.disabled = true;
        elements.checkoutBtn.classList.add('opacity-50', 'cursor-not-allowed');
        return;
      }
      
      // Show cart items
      elements.cartItems.innerHTML = cart.map(item => `
        <div class="flex items-center border-b pb-4 mb-4">
          <img src="${item.image}" alt="${item.name}" class="w-16 h-16 object-cover rounded-lg mr-4">
          <div class="flex-1">
            <h4 class="font-medium text-dark">${item.name}</h4>
            <p class="text-sm text-gray-600">RM${item.price.toFixed(1)}</p>
            <div class="flex items-center mt-2">
              <button class="quantity-btn px-2 py-1 border rounded-l" onclick="updateCartItemQuantity(${item.id}, ${item.quantity - 1})">
                <i class="fa fa-minus text-xs"></i>
              </button>
              <input type="number" value="${item.quantity}" class="w-10 text-center border-t border-b" 
                     onchange="updateCartItemQuantity(${item.id}, parseInt(this.value) || 0)">
              <button class="quantity-btn px-2 py-1 border rounded-r" onclick="updateCartItemQuantity(${item.id}, ${item.quantity + 1})">
                <i class="fa fa-plus text-xs"></i>
              </button>
              <button class="ml-auto text-red-500 hover:text-red-700" onclick="removeFromCart(${item.id})">
                <i class="fa fa-trash"></i>
              </button>
            </div>
          </div>
        </div>
      `).join('');
      
      // Show cart summary
      elements.cartSummary.classList.remove('hidden');
      elements.checkoutBtn.disabled = false;
      elements.checkoutBtn.classList.remove('opacity-50', 'cursor-not-allowed');
      
      // Calculate totals
      const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
      const deliveryFee = subtotal >= 100 ? 0 : 12;
      const total = subtotal + deliveryFee;
      
      document.getElementById('cart-subtotal').textContent = subtotal.toFixed(2);
      document.getElementById('delivery-fee').textContent = deliveryFee.toFixed(2);
      document.getElementById('cart-total').textContent = total.toFixed(2);
    }
    
    // Toggle cart drawer
    function toggleCartDrawer(show = null) {
      const isOpen = !elements.cartDrawer.classList.contains('translate-x-full');
      
      if (show === null) {
        show = !isOpen;
      }
      
      if (show) {
        elements.cartDrawer.classList.remove('translate-x-full');
        elements.overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      } else {
        elements.cartDrawer.classList.add('translate-x-full');
        elements.overlay.classList.add('hidden');
        document.body.style.overflow = '';
      }
    }
    
    // Show notification
    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `notification bg-white rounded-lg shadow-lg p-3 border-l-4 ${getNotificationClass(type)} transform transition-all duration-300 translate-x-full opacity-0`;
      
      notification.innerHTML = `
        <div class="flex items-center">
          <div class="flex-shrink-0 w-6 text-center">
            <i class="fa ${getNotificationIcon(type)} text-lg"></i>
          </div>
          <div class="ml-3 flex-1">
            <p class="text-sm font-medium text-gray-800 leading-relaxed">${message}</p>
          </div>
          <button class="ml-3 text-gray-400 hover:text-gray-600 transition-colors duration-200 p-1" onclick="this.parentElement.parentElement.remove()">
            <i class="fa fa-times"></i>
          </button>
        </div>
      `;
      
      // Add to container (new notifications appear at the bottom)
      elements.notificationContainer.appendChild(notification);
      
      // Animate in with bounce effect
      setTimeout(() => {
        notification.classList.remove('translate-x-full', 'opacity-0');
        notification.style.transform = 'translateX(-10px)';
        setTimeout(() => {
          notification.style.transform = 'translateX(0)';
          notification.style.transition = 'transform 0.2s ease-out';
        }, 200);
      }, 10);
      
      // Auto remove after 5 seconds
      setTimeout(() => {
        if (notification.parentNode) {
          notification.style.transform = 'translateX(100%)';
          notification.style.opacity = '0';
          setTimeout(() => {
            if (notification.parentNode) {
              notification.parentNode.removeChild(notification);
            }
          }, 300);
        }
      }, 5000);
      
      // Auto remove after 5 seconds
      setTimeout(() => {
        notification.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
          if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
          }
        }, 300);
      }, 5000);
    }
    
    // Get notification class based on type
    function getNotificationClass(type) {
      switch (type) {
        case 'success':
          return 'border-green-500';
        case 'error':
          return 'border-red-500';
        case 'warning':
          return 'border-yellow-500';
        default:
          return 'border-blue-500';
      }
    }
    
    // Get notification icon based on type
    function getNotificationIcon(type) {
      switch (type) {
        case 'success':
          return 'fa-check-circle text-green-500';
        case 'error':
          return 'fa-exclamation-circle text-red-500';
        case 'warning':
          return 'fa-exclamation-triangle text-yellow-500';
        default:
          return 'fa-info-circle text-blue-500';
      }
    }
    
    // Modal functions
    function openModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.remove('hidden');
        elements.overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Add animation
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
          modalContent.style.transform = 'scale(0.9) translateY(-20px)';
          modalContent.style.opacity = '0';
          setTimeout(() => {
            modalContent.style.transform = 'scale(1) translateY(0)';
            modalContent.style.opacity = '1';
          }, 10);
        }
      }
    }
    
    function closeModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
          modalContent.style.transform = 'scale(0.9) translateY(-20px)';
          modalContent.style.opacity = '0';
          setTimeout(() => {
            modal.classList.add('hidden');
            elements.overlay.classList.add('hidden');
            document.body.style.overflow = '';
          }, 300);
        } else {
          modal.classList.add('hidden');
          elements.overlay.classList.add('hidden');
          document.body.style.overflow = '';
        }
      }
    }
    
    function closeAllModals() {
      const modals = ['login-modal', 'register-modal', 'checkout-modal', 'order-history-modal'];
      modals.forEach(modalId => closeModal(modalId));
    }
    
    // Specific modal functions
    function openLoginModal() {
      closeModal('register-modal');
      openModal('login-modal');
    }
    
    function openRegisterModal() {
      closeModal('login-modal');
      openModal('register-modal');
    }
    
    function openCheckoutModal() {
      const checkoutForm = document.getElementById('checkout-form');
      if (checkoutForm && window.currentUserData) {
        // Pre-fill user information
        document.getElementById('checkout-name').value = window.currentUserData.full_name || '';
        document.getElementById('checkout-phone').value = '';
        document.getElementById('checkout-address').value = '';
      }
      
      // Update checkout summary
      updateCheckoutSummary();
      
      openModal('checkout-modal');
    }
    
    function openOrderHistoryModal() {
      loadOrderHistory();
      openModal('order-history-modal');
    }
    
    // Update checkout summary
    function updateCheckoutSummary() {
      const summaryContainer = document.getElementById('checkout-summary');
      if (!summaryContainer) return;
      
      const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
      const deliveryFee = subtotal >= 100 ? 0 : 12;
      const total = subtotal + deliveryFee;
      
      summaryContainer.innerHTML = `
        ${cart.map(item => `
          <div class="flex justify-between">
            <span>${item.name} x ${item.quantity}</span>
            <span>RM${(item.price * item.quantity).toFixed(2)}</span>
          </div>
        `).join('')}
        <div class="border-t my-2 pt-2"></div>
        <div class="flex justify-between">
          <span>Subtotal:</span>
          <span>RM${subtotal.toFixed(2)}</span>
        </div>
        <div class="flex justify-between">
          <span>Delivery:</span>
          <span>RM${deliveryFee.toFixed(2)}</span>
        </div>
        <div class="border-t my-2 pt-2 font-medium">
          <div class="flex justify-between">
            <span>Total:</span>
            <span>RM${total.toFixed(2)}</span>
          </div>
        </div>
      `;
    }
    
    // Set minimum delivery date to tomorrow
    function setMinDeliveryDate() {
      const checkoutDate = document.getElementById('checkout-date');
      if (checkoutDate) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        checkoutDate.min = tomorrow.toISOString().split('T')[0];
      }
    }
    
    // User authentication functions
    function login() {
      const username = document.getElementById('login-username').value;
      const password = document.getElementById('login-password').value;
      
      if (!username || !password) {
        showNotification('Please enter both username and password', 'error');
        return;
      }
      
      fetch('index.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          action: 'login',
          username: username,
          password: password
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success && data.user) {
          window.currentUserData = data.user;
          updateUserUI();
          closeModal('login-modal');
          showNotification('Login successful!', 'success');
          
          if (window.currentUserData.role === 'admin') {
            setTimeout(() => {
              window.location.href = 'admin.php?admin=true';
            }, 1000);
          }
        } else {
          showNotification(data.message || 'Invalid username or password', 'error');
        }
      })
      .catch(error => {
        console.error('Login error:', error);
        showNotification('Login failed. Please try again.', 'error');
      });
    }
    
    function register() {
      const name = document.getElementById('register-name').value;
      const username = document.getElementById('register-username').value;
      const email = document.getElementById('register-email').value;
      const password = document.getElementById('register-password').value;
      
      if (!name || !username || !email || !password) {
        showNotification('Please fill in all fields', 'error');
        return;
      }
      
      fetch('index.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          action: 'register',
          name: name,
          username: username,
          email: email,
          password: password
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Registration successful! Please login.', 'success');
          closeModal('register-modal');
          openLoginModal();
        } else {
          showNotification(data.message || 'Registration failed. Please try again.', 'error');
        }
      })
      .catch(error => {
        console.error('Registration error:', error);
        showNotification('Registration failed. Please try again.', 'error');
      });
    }
    
    function logout() {
      fetch('index.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          action: 'logout'
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          window.currentUserData = null;
          updateUserUI();
          showNotification('Logged out successfully', 'info');
        } else {
          showNotification(data.message || 'Logout failed. Please try again.', 'error');
        }
      })
      .catch(error => {
        console.error('Logout error:', error);
        showNotification('Logout failed. Please try again.', 'error');
      });
    }
    
    // Update user UI
    function updateUserUI() {
      const userMenuButton = document.getElementById('user-menu-button');
      const userDropdown = document.getElementById('user-dropdown');
      
      if (userMenuButton && window.currentUserData) {
        const userText = userMenuButton.querySelector('span');
        if (userText) {
          userText.textContent = window.currentUserData.full_name;
        }
      }
      
      // Update dropdown menu
      if (userDropdown) {
        userDropdown.innerHTML = window.currentUserData ? `
          <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="showNotification('You are logged in as ${window.currentUserData.username}')">
            <i class="fa fa-user-circle mr-2"></i>Profile
          </a>
          <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="openOrderHistoryModal()">
            <i class="fa fa-history mr-2"></i>Order History
          </a>
          <hr class="my-1">
          <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="logout()">
            <i class="fa fa-sign-out mr-2"></i>Logout
          </a>
        ` : `
          <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="openLoginModal()">
            <i class="fa fa-sign-in mr-2"></i>Login
          </a>
          <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="openRegisterModal()">
            <i class="fa fa-user-plus mr-2"></i>Register
          </a>
        `;
      }
    }
    
    // Place order
    function placeOrder() {
      const name = document.getElementById('checkout-name').value;
      const phone = document.getElementById('checkout-phone').value;
      const address = document.getElementById('checkout-address').value;
      const date = document.getElementById('checkout-date').value;
      
      if (!name || !phone || !address || !date) {
        showNotification('Please fill in all delivery information', 'error');
        return;
      }
      
      if (cart.length === 0) {
        showNotification('Your cart is empty', 'error');
        return;
      }
      
      // Calculate totals
      const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
      const deliveryFee = subtotal >= 100 ? 0 : 12;
      const total = subtotal + deliveryFee;
      
      const orderData = {
        items: cart,
        totalAmount: total,
        recipientName: name,
        recipientPhone: phone,
        recipientAddress: address,
        deliveryDate: date
      };
      
      fetch('index.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          action: 'placeOrder',
          orderData: JSON.stringify(orderData)
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const orderId = data.orderId || 'ORD-' + date.replace(/-/g, '') + '-' + Math.floor(Math.random() * 10000).toString().padStart(4, '0');
          cart = [];
          localStorage.removeItem('cart');
          updateCartUI();
          closeModal('checkout-modal');
          toggleCartDrawer(false);
          showNotification(`Order #${orderId} placed successfully!`, 'success');
          
          setTimeout(() => {
            alert(`Order Confirmation\n\nOrder ID: ${orderId}\nTotal: RM${total.toFixed(2)}\nDelivery Date: ${date}\n\nThank you for your order!`);
          }, 500);
        } else {
          showNotification(data.message || 'Failed to place order. Please try again.', 'error');
        }
      })
      .catch(error => {
        console.error('Order error:', error);
        showNotification('Failed to place order. Please try again.', 'error');
      });
    }
    
    // Load order history
    function loadOrderHistory() {
      const orderHistoryContainer = document.getElementById('order-history-container');
      if (!orderHistoryContainer) return;
      
      orderHistoryContainer.innerHTML = '<div class="text-center py-8"><i class="fa fa-spinner fa-spin text-primary text-2xl"></i><p class="mt-2">Loading order history...</p></div>';
      
      fetch('index.php?action=get_order_history')
        .then(response => response.json())
        .then(data => {
          if (!data.success) {
            orderHistoryContainer.innerHTML = `<div class="text-center py-8 text-gray-500"><i class="fa fa-shopping-basket text-4xl mb-2"></i><p>${data.message || 'Unable to load order history'}</p></div>`;
            return;
          }
          
          const orders = data.orders || [];
          if (orders.length === 0) {
            orderHistoryContainer.innerHTML = '<div class="text-center py-8 text-gray-500"><i class="fa fa-shopping-basket text-4xl mb-2"></i><p>No orders found</p><p class="text-sm">Start shopping to place your first order!</p></div>';
            return;
          }
          
          orderHistoryContainer.innerHTML = orders.map(order => `
            <div class="order-card border rounded-lg p-4">
              <div class="order-header">
                <div class="flex justify-between items-center">
                  <div>
                    <h4 class="font-bold">Order #${order.order_id}</h4>
                    <p class="text-sm text-gray-600">Date: ${order.created_at}</p>
                  </div>
                  <span class="status-badge ${
                    order.status === 'delivered' ? 'status-delivered' : 
                    order.status === 'processing' ? 'status-processing' : 
                    order.status === 'cancelled' ? 'status-cancelled' : 
                    'status-processing'
                  }">${order.status}</span>
                </div>
              </div>
              <div class="order-items mt-3 space-y-2">
                ${(order.items || []).map(item => `
                  <div class="order-item flex justify-between">
                    <div>
                      <p class="font-medium">${item.item_name}</p>
                      <p class="text-sm text-gray-600">Quantity: ${item.quantity}</p>
                    </div>
                    <span class="font-medium">RM${(item.price * item.quantity).toFixed(2)}</span>
                  </div>
                `).join('')}
                <div class="order-total border-t pt-3 mt-3 flex justify-between font-bold">
                  <span>Total:</span>
                  <span class="text-primary">RM${Number(order.total_amount).toFixed(2)}</span>
                </div>
              </div>
            </div>
          `).join('');
        })
        .catch(error => {
          console.error('Order history error:', error);
          orderHistoryContainer.innerHTML = '<div class="text-center py-8 text-gray-500"><i class="fa fa-shopping-basket text-4xl mb-2"></i><p>Failed to load order history</p></div>';
        });
    }
    
    // Initialize the application when DOM is loaded
    document.addEventListener('DOMContentLoaded', init);
  </script>
</body>
</html>
