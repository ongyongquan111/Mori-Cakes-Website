<?php
session_start();

$host = 'localhost';
$dbname = 'mori_cakes';
$username = 'root';
$password = '';

$pdo = null;
$ordersData = [];
$productsData = [];
$usersData = [];
$adminsData = [];
$categoriesData = [];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $ordersStmt = $pdo->query(
        "SELECT o.*, 
                u.full_name AS customer_name,
                u.email AS customer_email,
                u.phone AS customer_phone,
                u.address AS customer_address
         FROM orders o
         LEFT JOIN users u ON o.user_id = u.id
         ORDER BY o.created_at DESC"
    );
    $ordersData = $ordersStmt->fetchAll();

    $itemsStmt = $pdo->query(
        "SELECT oi.*, mi.name AS item_name
         FROM order_items oi
         LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id"
    );
    $orderItems = $itemsStmt->fetchAll();
    $itemsByOrder = [];
    foreach ($orderItems as $item) {
        $itemsByOrder[$item['order_id']][] = [
            'id' => $item['id'],
            'menu_item_id' => $item['menu_item_id'],
            'quantity' => (int) $item['quantity'],
            'price' => (float) $item['price'],
            'name' => $item['item_name']
        ];
    }

    foreach ($ordersData as &$order) {
        $orderId = $order['id'];
        $order['order_id'] = 'ORD-' . date('Ymd', strtotime($order['created_at'])) . '-' . str_pad($orderId, 4, '0', STR_PAD_LEFT);
        $order['total'] = (float) $order['total_amount'];
        $order['items'] = $itemsByOrder[$orderId] ?? [];
        $order['customer_name'] = $order['customer_name'] ?: $order['recipient_name'];
        $order['customer_email'] = $order['customer_email'] ?: '';
        $order['customer_phone'] = $order['customer_phone'] ?: $order['recipient_phone'];
        $order['customer_address'] = $order['customer_address'] ?: $order['recipient_address'];
    }
    unset($order);

    $productsStmt = $pdo->query(
        "SELECT mi.*, c.name AS category
         FROM menu_items mi
         LEFT JOIN categories c ON mi.category_id = c.id
         ORDER BY mi.id ASC"
    );
    $productsData = $productsStmt->fetchAll();

    $usersStmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $usersData = $usersStmt->fetchAll();

    $adminsStmt = $pdo->query("SELECT * FROM users WHERE role = 'admin' ORDER BY created_at DESC");
    $adminsData = $adminsStmt->fetchAll();

    $categoriesStmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $categoriesData = $categoriesStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mori Cakes - Admin Panel</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#D23B61',
                        secondary: '#3B82F6',
                        dark: '#1F2937',
                        light: '#F9FAFB'
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
        }
    </style>
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: #f8fafc;
        }
        .sidebar {
            background: linear-gradient(135deg, #D23B61 0%, #E11D48 100%);
        }
        .card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .btn-primary {
            background: linear-gradient(135deg, #D23B61 0%, #E11D48 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(210, 59, 97, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.4);
        }
        .btn-danger {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.4);
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .status-processing {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        .status-delivered {
            background-color: #D1FAE5;
            color: #065F46;
        }
        .status-cancelled {
            background-color: #FEE2E2;
            color: #991B1B;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <aside class="sidebar w-64 text-white flex-shrink-0 hidden md:block">
            <div class="p-6">
                <h1 class="text-2xl font-bold flex items-center">
                    <i class="fa fa-birthday-cake mr-3"></i>
                    Mori Cakes
                </h1>
                <p class="text-sm text-pink-100 mt-1">Admin Panel</p>
            </div>
            <nav class="mt-8">
                <a href="#dashboard" class="flex items-center px-6 py-3 text-white bg-white bg-opacity-20" onclick="showSection('dashboard-section')">
                    <i class="fa fa-tachometer w-6"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#orders" class="flex items-center px-6 py-3 text-white hover:bg-white hover:bg-opacity-10 transition-colors" onclick="showSection('orders-section')">
                    <i class="fa fa-shopping-bag w-6"></i>
                    <span>Orders</span>
                </a>
                <a href="#products" class="flex items-center px-6 py-3 text-white hover:bg-white hover:bg-opacity-10 transition-colors" onclick="showSection('products-section')">
                    <i class="fa fa-cubes w-6"></i>
                    <span>Products</span>
                </a>
                <a href="#users" class="flex items-center px-6 py-3 text-white hover:bg-white hover:bg-opacity-10 transition-colors" onclick="showSection('users-section')">
                    <i class="fa fa-users w-6"></i>
                    <span>Users</span>
                </a>
                <a href="#admins" class="flex items-center px-6 py-3 text-white hover:bg-white hover:bg-opacity-10 transition-colors" onclick="showSection('admins-section')">
                    <i class="fa fa-user-circle w-6"></i>
                    <span>Admins</span>
                </a>
                <a href="#settings" class="flex items-center px-6 py-3 text-white hover:bg-white hover:bg-opacity-10 transition-colors" onclick="showSection('settings-section')">
                    <i class="fa fa-cog w-6"></i>
                    <span>Settings</span>
                </a>
            </nav>
            <div class="absolute bottom-0 left-0 w-64 p-6">
                <a href="index.php" class="flex items-center text-white hover:text-pink-100 transition-colors">
                    <i class="fa fa-sign-out w-6"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Mobile sidebar toggle -->
        <div class="md:hidden fixed top-4 left-4 z-50">
            <button id="mobile-menu-button" class="bg-primary text-white p-2 rounded-lg shadow-lg">
                <i class="fa fa-bars"></i>
            </button>
        </div>

        <!-- Mobile sidebar -->
        <div id="mobile-sidebar" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden">
            <div class="sidebar w-64 h-full">
                <div class="p-6 flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-white flex items-center">
                        <i class="fa fa-birthday-cake mr-3"></i>
                        Mori Cakes
                    </h1>
                    <button id="close-mobile-menu" class="text-white">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>
                <nav class="mt-8">
                    <a href="#dashboard" class="flex items-center px-6 py-3 text-white bg-white bg-opacity-20" onclick="showSection('dashboard-section'); closeMobileMenu()">
                        <i class="fa fa-tachometer w-6"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="#orders" class="flex items-center px-6 py-3 text-white hover:bg-white hover:bg-opacity-10 transition-colors" onclick="showSection('orders-section'); closeMobileMenu()">
                        <i class="fa fa-shopping-bag w-6"></i>
                        <span>Orders</span>
                    </a>
                    <a href="#products" class="flex items-center px-6 py-3 text-white hover:bg-white hover:bg-opacity-10 transition-colors" onclick="showSection('products-section'); closeMobileMenu()">
                        <i class="fa fa-cubes w-6"></i>
                        <span>Products</span>
                    </a>
                    <a href="#users" class="flex items-center px-6 py-3 text-white hover:bg-white hover:bg-opacity-10 transition-colors" onclick="showSection('users-section'); closeMobileMenu()">
                        <i class="fa fa-users w-6"></i>
                        <span>Users</span>
                    </a>
                    <a href="#admins" class="flex items-center px-6 py-3 text-white hover:bg-white hover:bg-opacity-10 transition-colors" onclick="showSection('admins-section'); closeMobileMenu()">
                        <i class="fa fa-user-circle w-6"></i>
                        <span>Admins</span>
                    </a>
                    <a href="#settings" class="flex items-center px-6 py-3 text-white hover:bg-white hover:bg-opacity-10 transition-colors" onclick="showSection('settings-section'); closeMobileMenu()">
                        <i class="fa fa-cog w-6"></i>
                        <span>Settings</span>
                    </a>
                    <a href="index.php" class="flex items-center px-6 py-3 text-white hover:bg-white hover:bg-opacity-10 transition-colors" onclick="closeMobileMenu()">
                        <i class="fa fa-sign-out w-6"></i>
                        <span>Logout</span>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main content -->
        <main class="flex-1 p-6 md:p-8">
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-dark">Admin Dashboard</h1>
                <p class="text-gray-600">Welcome to Mori Cakes Admin Panel</p>
            </header>

            <!-- Dashboard Section -->
            <section id="dashboard-section" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Stats cards -->
                    <div class="card p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Orders</p>
                                <h3 class="text-3xl font-bold text-dark mt-2" id="total-orders">0</h3>
                            </div>
                            <div class="bg-pink-100 p-3 rounded-full">
                                <i class="fa fa-shopping-bag text-primary text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Revenue</p>
                                <h3 class="text-3xl font-bold text-dark mt-2" id="total-revenue">RM0.00</h3>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fa fa-money text-secondary text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Products</p>
                                <h3 class="text-3xl font-bold text-dark mt-2" id="total-products">0</h3>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fa fa-cubes text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Users</p>
                                <h3 class="text-3xl font-bold text-dark mt-2" id="total-users">0</h3>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fa fa-users text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Recent orders -->
                    <div class="card p-6 lg:col-span-2">
                        <h3 class="text-xl font-bold text-dark mb-4">Recent Orders</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Order #</th>
                                        <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Customer</th>
                                        <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Date</th>
                                        <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Total</th>
                                        <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="recent-orders">
                                    <tr>
                                        <td colspan="5" class="text-center py-8 text-gray-500">
                                            <i class="fa fa-spinner fa-spin text-primary text-2xl mb-4"></i>
                                            <p>Loading recent orders...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Order status chart -->
                    <div class="card p-6">
                        <h3 class="text-xl font-bold text-dark mb-4">Order Status</h3>
                        <div id="order-status-chart" class="h-64">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Orders Section -->
            <section id="orders-section" class="hidden space-y-6">
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-dark">All Orders</h3>
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <input type="text" id="order-search" placeholder="Search orders..." class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <i class="fa fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                            <select id="order-status-filter" class="border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="all">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Order #</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Customer</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Date</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Total</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Status</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="all-orders">
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-gray-500">
                                        <i class="fa fa-spinner fa-spin text-primary text-2xl mb-4"></i>
                                        <p>Loading orders...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6 flex justify-between items-center">
                        <p class="text-sm text-gray-500">Showing <span id="orders-showing">0</span> of <span id="orders-total">0</span> orders</p>
                        <div class="flex space-x-2">
                            <button id="prev-page" class="btn-secondary" disabled>
                                <i class="fa fa-chevron-left"></i>
                            </button>
                            <button id="next-page" class="btn-secondary" disabled>
                                <i class="fa fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Products Section -->
            <section id="products-section" class="hidden space-y-6">
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-dark">Products</h3>
                        <button class="btn-primary" onclick="openAddProductModal()">
                            <i class="fa fa-plus mr-2"></i> Add Product
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="products-grid">
                        <div class="text-center py-8 text-gray-500 col-span-full">
                            <i class="fa fa-spinner fa-spin text-primary text-2xl mb-4"></i>
                            <p>Loading products...</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Users Section -->
            <section id="users-section" class="hidden space-y-6">
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-dark">Users</h3>
                        <div class="relative">
                            <input type="text" id="user-search" placeholder="Search users..." class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            <i class="fa fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">User ID</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Name</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Email</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Role</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Joined Date</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="all-users">
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-gray-500">
                                        <i class="fa fa-spinner fa-spin text-primary text-2xl mb-4"></i>
                                        <p>Loading users...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Admins Section -->
            <section id="admins-section" class="hidden space-y-6">
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-dark">Admin Accounts</h3>
                        <button class="btn-primary" onclick="openAddAdminModal()">
                            <i class="fa fa-plus mr-2"></i> Add Admin
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">No.</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Full Name</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Email</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Username</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Created At</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="all-admins">
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-gray-500">
                                        <i class="fa fa-spinner fa-spin text-primary text-2xl mb-4"></i>
                                        <p>Loading admin accounts...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Settings Section -->
            <section id="settings-section" class="hidden space-y-6">
                <div class="card p-6">
                    <h3 class="text-xl font-bold text-dark mb-6">System Settings</h3>
                    <div class="space-y-6">
                        <div>
                            <h4 class="font-medium text-dark mb-3">General Settings</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Site Name</label>
                                    <input type="text" value="Mori Cakes" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
                                    <select class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="RM">Malaysian Ringgit (RM)</option>
                                        <option value="USD">US Dollar ($)</option>
                                        <option value="EUR">Euro (€)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium text-dark mb-3">Delivery Settings</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Fee</label>
                                    <input type="number" value="12" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Free Delivery Threshold</label>
                                    <input type="number" value="100" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                            </div>
                        </div>
                        <div class="pt-4">
                            <button class="btn-primary">
                                <i class="fa fa-save mr-2"></i> Save Settings
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Add Admin Modal -->
    <div id="add-admin-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeAddAdminModal()"></div>
        <div class="bg-white rounded-lg p-8 max-w-md w-full relative z-10">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-dark">Add Admin Account</h3>
                <button class="text-gray-500 hover:text-gray-700" onclick="closeAddAdminModal()">
                    <i class="fa fa-times text-xl"></i>
                </button>
            </div>
            <form id="add-admin-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <input type="text" id="admin-fullname" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" id="admin-email" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" id="admin-username" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" id="admin-password" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div class="pt-4">
                    <button type="submit" class="btn-primary w-full">
                        <i class="fa fa-plus mr-2"></i> Add Admin
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="add-product-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeAddProductModal()"></div>
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full relative z-10">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-dark">Add Product</h3>
                <button class="text-gray-500 hover:text-gray-700" onclick="closeAddProductModal()">
                    <i class="fa fa-times text-xl"></i>
                </button>
            </div>
            <form id="add-product-form" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Product Name</label>
                        <input type="text" id="product-name" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select id="product-category" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Category</option>
                            <option value="Cheese Cake">Cheese Cake</option>
                            <option value="Strawberry Cake">Strawberry Cake</option>
                            <option value="Chocolate Cake">Chocolate Cake</option>
                            <option value="Matcha Cake">Matcha Cake</option>
                            <option value="Coffee Cake">Coffee Cake</option>
                            <option value="Vanilla Cake">Vanilla Cake</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Price (RM)</label>
                        <input type="number" id="product-price" step="0.1" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Stock</label>
                        <input type="number" id="product-stock" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="product-description" rows="3" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Image URL</label>
                    <input type="url" id="product-image" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div class="pt-4">
                    <button type="submit" class="btn-primary">
                        <i class="fa fa-plus mr-2"></i> Add Product
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div id="order-detail-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeOrderDetailModal()"></div>
        <div class="bg-white rounded-lg p-8 max-w-4xl w-full relative z-10 max-h-[80vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-dark">Order Details</h3>
                <button class="text-gray-500 hover:text-gray-700" onclick="closeOrderDetailModal()">
                    <i class="fa fa-times text-xl"></i>
                </button>
            </div>
            <div id="order-detail-content">
                <!-- Order details will be dynamically inserted here -->
            </div>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>

    <script>
        // Data storage for retrieved data
        let orders = <?php echo json_encode($ordersData); ?>;
        let products = <?php echo json_encode($productsData); ?>;
        let users = <?php echo json_encode($usersData); ?>;
        let admins = <?php echo json_encode($adminsData); ?>;
        let categories = <?php echo json_encode($categoriesData); ?>;

        // DOM elements
        const elements = {
            mobileMenuButton: document.getElementById('mobile-menu-button'),
            mobileSidebar: document.getElementById('mobile-sidebar'),
            closeMobileMenu: document.getElementById('close-mobile-menu'),
            sections: {
                dashboard: document.getElementById('dashboard-section'),
                orders: document.getElementById('orders-section'),
                products: document.getElementById('products-section'),
                users: document.getElementById('users-section'),
                admins: document.getElementById('admins-section'),
                settings: document.getElementById('settings-section')
            }
        };

        // Initialize the admin panel
        document.addEventListener('DOMContentLoaded', function() {
            // Check if user is admin
            const isAdmin = checkAdminLogin();
            if (!isAdmin) {
                window.location.href = 'index.php';
                return;
            }

            // Initialize dashboard with loading states
            initializeDashboard();
            
            // Setup event listeners
            setupEventListeners();
        });

        // Initialize dashboard with loading states
        function initializeDashboard() {
            // Show loading states for all sections
            showLoadingStates();
            
            // Initialize dashboard with real data
            loadDashboardStats();
            loadRecentOrders();
            initOrderStatusChart();
            
            // Initialize other sections
            loadAllOrders();
            loadProducts();
            loadUsers();
            loadAdmins();
        }

        // Show loading states for all sections
        function showLoadingStates() {
            // Dashboard stats
            document.getElementById('total-orders').textContent = 'Loading...';
            document.getElementById('total-revenue').textContent = 'Loading...';
            document.getElementById('total-products').textContent = 'Loading...';
            document.getElementById('total-users').textContent = 'Loading...';
            
            // Recent orders
            document.getElementById('recent-orders').innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-8 text-gray-500">
                        <i class="fa fa-spinner fa-spin text-primary text-2xl mb-4"></i>
                        <p>Loading recent orders...</p>
                    </td>
                </tr>
            `;
            
            // All orders
            document.getElementById('all-orders').innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-8 text-gray-500">
                        <i class="fa fa-spinner fa-spin text-primary text-2xl mb-4"></i>
                        <p>Loading orders...</p>
                    </td>
                </tr>
            `;
            
            // Products
            document.getElementById('products-grid').innerHTML = `
                <div class="text-center py-8 text-gray-500 col-span-full">
                    <i class="fa fa-spinner fa-spin text-primary text-2xl mb-4"></i>
                    <p>Loading products...</p>
                </div>
            `;
            
            // Users
            document.getElementById('all-users').innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-8 text-gray-500">
                        <i class="fa fa-spinner fa-spin text-primary text-2xl mb-4"></i>
                        <p>Loading users...</p>
                    </td>
                </tr>
            `;
            
            // Admins
            document.getElementById('all-admins').innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-8 text-gray-500">
                        <i class="fa fa-spinner fa-spin text-primary text-2xl mb-4"></i>
                        <p>Loading admin accounts...</p>
                    </td>
                </tr>
            `;
        }

        // Load data from database
        async function loadDataFromDatabase() {
            try {
                // Simulate API calls to get data from database
                // In a real application, these would be actual AJAX calls to PHP endpoints
                
                // Load orders
                await loadOrdersFromDatabase();
                
                // Load products
                await loadProductsFromDatabase();
                
                // Load users
                await loadUsersFromDatabase();
                
                // Load admins
                await loadAdminsFromDatabase();
                
                // Load categories
                await loadCategoriesFromDatabase();
                
                console.log('All data loaded successfully');
            } catch (error) {
                console.error('Error loading data from database:', error);
                throw error;
            }
        }

        // Load orders from database
        async function loadOrdersFromDatabase() {
            return new Promise((resolve, reject) => {
                // Simulate API call delay
                setTimeout(() => {
                    try {
                        // In a real application, this would be an AJAX call to get orders from database
                        // For now, we'll use sample data structure that matches the database schema
                        
                        orders = [
                            {
                                id: 1,
                                order_id: 'ORD-2024-001',
                                user_id: 1,
                                total_amount: 128.50,
                                recipient_name: 'John Doe',
                                recipient_phone: '+6012-345-6789',
                                recipient_address: '123 Main St, Kuala Lumpur',
                                delivery_date: '2024-01-20',
                                delivery_time: '14:30 - 15:30',
                                special_instructions: 'Please deliver to the main entrance',
                                status: 'delivered',
                                payment_method: 'Credit Card',
                                payment_status: 'paid',
                                created_at: '2024-01-19 10:25:30',
                                updated_at: '2024-01-20 15:00:00',
                                customer_name: 'John Doe',
                                customer_email: 'john@example.com',
                                customer_phone: '+6012-345-6789',
                                customer_address: '123 Main St, Kuala Lumpur',
                                items: [
                                    { id: 1, menu_item_id: 1, quantity: 1, price: 45.00, name: 'Classic Cheesecake' },
                                    { id: 2, menu_item_id: 3, quantity: 1, price: 58.50, name: 'Chocolate Truffle Cake' },
                                    { id: 3, menu_item_id: 5, quantity: 1, price: 25.00, name: 'Strawberry Shortcake' }
                                ]
                            },
                            {
                                id: 2,
                                order_id: 'ORD-2024-002',
                                user_id: 2,
                                total_amount: 85.00,
                                recipient_name: 'Jane Smith',
                                recipient_phone: '+6019-876-5432',
                                recipient_address: '456 Oak Ave, Petaling Jaya',
                                delivery_date: '2024-01-21',
                                delivery_time: '10:00 - 11:00',
                                special_instructions: '',
                                status: 'processing',
                                payment_method: 'Online Banking',
                                payment_status: 'paid',
                                created_at: '2024-01-20 09:15:45',
                                updated_at: '2024-01-20 09:15:45',
                                customer_name: 'Jane Smith',
                                customer_email: 'jane@example.com',
                                customer_phone: '+6019-876-5432',
                                customer_address: '456 Oak Ave, Petaling Jaya',
                                items: [
                                    { id: 4, menu_item_id: 4, quantity: 2, price: 42.50, name: 'Matcha Green Tea Cake' }
                                ]
                            }
                        ].map(order => ({
                            ...order,
                            total: order.total_amount
                        }));
                        
                        console.log('Orders loaded:', orders.length);
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                }, 500);
            });
        }

        // Load products from database
        async function loadProductsFromDatabase() {
            return new Promise((resolve, reject) => {
                // Simulate API call delay
                setTimeout(() => {
                    try {
                        // In a real application, this would be an AJAX call to get products from database
                        products = [
                            { id: 1, name: 'Classic Cheesecake', category_id: 1, price: 45.00, description: 'Creamy New York style cheesecake with a graham cracker crust', image_url: 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?q=80&w=200&h=200&auto=format&fit=crop', rating: 4.8, review_count: 156, is_available: true, category: 'Cheese Cake', stock: 20 },
                            { id: 2, name: 'Strawberry Shortcake', category_id: 2, price: 25.00, description: 'Fresh strawberries layered with vanilla sponge cake and whipped cream', image_url: 'https://images.unsplash.com/photo-1563379926898-05f4575a45d8?q=80&w=200&h=200&auto=format&fit=crop', rating: 4.6, review_count: 98, is_available: true, category: 'Strawberry Cake', stock: 25 },
                            { id: 3, name: 'Chocolate Truffle Cake', category_id: 3, price: 58.50, description: 'Rich chocolate cake with truffle ganache filling and chocolate shavings', image_url: 'https://images.unsplash.com/photo-1583121274602-3e2820c69888?q=80&w=200&h=200&auto=format&fit=crop', rating: 4.9, review_count: 203, is_available: true, category: 'Chocolate Cake', stock: 15 },
                            { id: 4, name: 'Matcha Green Tea Cake', category_id: 4, price: 42.50, description: 'Japanese matcha cake with red bean paste and green tea cream', image_url: 'https://images.unsplash.com/photo-1543363136-010fb91c6448?q=80&w=200&h=200&auto=format&fit=crop', rating: 4.7, review_count: 87, is_available: true, category: 'Matcha Cake', stock: 18 },
                            { id: 5, name: 'Coffee Cake', category_id: 5, price: 45.00, description: 'Moist coffee cake with walnuts and cinnamon streusel', image_url: 'https://images.unsplash.com/photo-1558326567-9883f6a32916?q=80&w=200&h=200&auto=format&fit=crop', rating: 4.5, review_count: 112, is_available: true, category: 'Coffee Cake', stock: 22 },
                            { id: 6, name: 'Vanilla Cake', category_id: 6, price: 40.00, description: 'Classic vanilla cake with buttercream frosting and sprinkles', image_url: 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?q=80&w=200&h=200&auto=format&fit=crop', rating: 4.4, review_count: 76, is_available: true, category: 'Vanilla Cake', stock: 30 }
                        ];
                        
                        console.log('Products loaded:', products.length);
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                }, 300);
            });
        }

        // Load users from database
        async function loadUsersFromDatabase() {
            return new Promise((resolve, reject) => {
                // Simulate API call delay
                setTimeout(() => {
                    try {
                        // In a real application, this would be an AJAX call to get users from database
                        users = [
                            { id: 1, username: 'user', password: 'user123', email: 'user@moricakes.com', full_name: 'Test User', phone: '+6012-345-6789', address: '123 Test St, Kuala Lumpur', role: 'user', created_at: '2024-01-01 00:00:00', updated_at: '2024-01-01 00:00:00' },
                            { id: 2, username: 'john_doe', password: 'password123', email: 'john@example.com', full_name: 'John Doe', phone: '+6012-345-6789', address: '123 Main St, Kuala Lumpur', role: 'user', created_at: '2024-01-15 10:30:00', updated_at: '2024-01-15 10:30:00' },
                            { id: 3, username: 'jane_smith', password: 'password123', email: 'jane@example.com', full_name: 'Jane Smith', phone: '+6019-876-5432', address: '456 Oak Ave, Petaling Jaya', role: 'user', created_at: '2024-01-18 14:20:00', updated_at: '2024-01-18 14:20:00' }
                        ];
                        
                        console.log('Users loaded:', users.length);
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                }, 200);
            });
        }

        // Load admins from database
        async function loadAdminsFromDatabase() {
            return new Promise((resolve, reject) => {
                // Simulate API call delay
                setTimeout(() => {
                    try {
                        // In a real application, this would be an AJAX call to get admins from database
                        admins = [
                            { id: 1, username: 'admin', password: 'admin123', email: 'admin@moricakes.com', full_name: 'Admin User', phone: '', address: '', role: 'admin', created_at: '2023-12-31 00:00:00', updated_at: '2023-12-31 00:00:00' },
                            { id: 4, username: '242UT2449E', password: 'pw0001', email: 'admin1@moricakes.com', full_name: 'Yap Shi Tong', phone: '', address: '', role: 'admin', created_at: '2024-01-20 00:00:00', updated_at: '2024-01-20 00:00:00' },
                            { id: 5, username: '242UT2449F', password: 'pw0002', email: 'admin2@moricakes.com', full_name: 'Jamie Lim Shi Ting', phone: '', address: '', role: 'admin', created_at: '2024-01-20 00:00:00', updated_at: '2024-01-20 00:00:00' },
                            { id: 6, username: '243UT246XG', password: 'pw0003', email: 'admin3@moricakes.com', full_name: 'Ong Yong Quan', phone: '', address: '', role: 'admin', created_at: '2024-01-20 00:00:00', updated_at: '2024-01-20 00:00:00' },
                            { id: 7, username: '1201302385', password: 'pw0004', email: 'admin4@moricakes.com', full_name: 'Mohamed Abdelgabar Mohamed Awad', phone: '', address: '', role: 'admin', created_at: '2024-01-20 00:00:00', updated_at: '2024-01-20 00:00:00' }
                        ];
                        
                        console.log('Admins loaded:', admins.length);
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                }, 200);
            });
        }

        // Load categories from database
        async function loadCategoriesFromDatabase() {
            return new Promise((resolve, reject) => {
                // Simulate API call delay
                setTimeout(() => {
                    try {
                        // In a real application, this would be an AJAX call to get categories from database
                        categories = [
                            { id: 1, name: 'cheese', description: 'Delicious cheesecakes', created_at: '2023-12-31 00:00:00' },
                            { id: 2, name: 'strawberry', description: 'Fresh strawberry cakes', created_at: '2023-12-31 00:00:00' },
                            { id: 3, name: 'chocolate', description: 'Rich chocolate cakes', created_at: '2023-12-31 00:00:00' },
                            { id: 4, name: 'matcha', description: 'Japanese matcha cakes', created_at: '2023-12-31 00:00:00' },
                            { id: 5, name: 'coffee', description: 'Coffee flavored cakes', created_at: '2023-12-31 00:00:00' },
                            { id: 6, name: 'vanilla', description: 'Classic vanilla cakes', created_at: '2023-12-31 00:00:00' }
                        ];
                        
                        console.log('Categories loaded:', categories.length);
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                }, 100);
            });
        }

        // Check if user is logged in as admin
        function checkAdminLogin() {
            // In a real application, this would check a session or token
            // For this demo, we'll just check if the user came from the login page with admin credentials
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('admin') === 'true';
        }

        // Show specific section
        function showSection(sectionId) {
            // Hide all sections
            Object.values(elements.sections).forEach(section => {
                if (section) section.classList.add('hidden');
            });

            // Show the selected section
            const section = document.getElementById(sectionId);
            if (section) {
                section.classList.remove('hidden');
            }
        }

        // Mobile menu functions
        function openMobileMenu() {
            elements.mobileSidebar.classList.remove('hidden');
        }

        function closeMobileMenu() {
            elements.mobileSidebar.classList.add('hidden');
        }

        // Setup event listeners
        function setupEventListeners() {
            // Mobile menu toggle
            if (elements.mobileMenuButton) {
                elements.mobileMenuButton.addEventListener('click', openMobileMenu);
            }

            if (elements.closeMobileMenu) {
                elements.closeMobileMenu.addEventListener('click', closeMobileMenu);
            }

            // Add admin form submission
            const addAdminForm = document.getElementById('add-admin-form');
            if (addAdminForm) {
                addAdminForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    addAdminAccount();
                });
            }

            // Add product form submission
            const addProductForm = document.getElementById('add-product-form');
            if (addProductForm) {
                addProductForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    addProduct();
                });
            }

            // Search and filter
            const orderSearch = document.getElementById('order-search');
            if (orderSearch) {
                orderSearch.addEventListener('input', filterOrders);
            }

            const orderStatusFilter = document.getElementById('order-status-filter');
            if (orderStatusFilter) {
                orderStatusFilter.addEventListener('change', filterOrders);
            }

            const userSearch = document.getElementById('user-search');
            if (userSearch) {
                userSearch.addEventListener('input', filterUsers);
            }
        }

        // Load dashboard statistics
        function loadDashboardStats() {
            const totalOrders = orders.length;
            const totalRevenue = orders.reduce((sum, order) => sum + (order.total || order.total_amount || 0), 0);
            const totalProducts = products.length;
            const totalUsers = users.length;

            document.getElementById('total-orders').textContent = totalOrders;
            document.getElementById('total-revenue').textContent = `RM${totalRevenue.toFixed(2)}`;
            document.getElementById('total-products').textContent = totalProducts;
            document.getElementById('total-users').textContent = totalUsers;
        }

        // Load recent orders for dashboard
        function loadRecentOrders() {
            const recentOrders = orders.slice(0, 5);
            const ordersHtml = recentOrders.map(order => `
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4">${order.order_id}</td>
                    <td class="py-3 px-4">${order.customer_name}</td>
                    <td class="py-3 px-4">${formatDate(order.created_at)}</td>
                    <td class="py-3 px-4">RM${Number(order.total || order.total_amount || 0).toFixed(2)}</td>
                    <td class="py-3 px-4">
                        <span class="status-badge status-${order.status}">${capitalizeFirstLetter(order.status)}</span>
                    </td>
                </tr>
            `).join('');

            document.getElementById('recent-orders').innerHTML = ordersHtml;
        }

        // Initialize order status chart
        function initOrderStatusChart() {
            const ctx = document.getElementById('statusChart').getContext('2d');
            
            // Count orders by status
            const statusCounts = {
                pending: orders.filter(order => order.status === 'pending').length,
                processing: orders.filter(order => order.status === 'processing').length,
                delivered: orders.filter(order => order.status === 'delivered').length,
                cancelled: orders.filter(order => order.status === 'cancelled').length
            };

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Processing', 'Delivered', 'Cancelled'],
                    datasets: [{
                        data: [statusCounts.pending, statusCounts.processing, statusCounts.delivered, statusCounts.cancelled],
                        backgroundColor: [
                            '#FEF3C7',
                            '#DBEAFE',
                            '#D1FAE5',
                            '#FEE2E2'
                        ],
                        borderColor: [
                            '#F59E0B',
                            '#3B82F6',
                            '#10B981',
                            '#EF4444'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Load all orders
        function loadAllOrders() {
            const ordersHtml = orders.map(order => `
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4">${order.order_id}</td>
                    <td class="py-3 px-4">${order.customer_name}</td>
                    <td class="py-3 px-4">${formatDate(order.created_at)}</td>
                    <td class="py-3 px-4">RM${Number(order.total || order.total_amount || 0).toFixed(2)}</td>
                    <td class="py-3 px-4">
                        <span class="status-badge status-${order.status}">${capitalizeFirstLetter(order.status)}</span>
                    </td>
                    <td class="py-3 px-4">
                        <button class="btn-secondary text-sm" onclick="viewOrderDetail(${order.id})">
                            <i class="fa fa-eye mr-1"></i> View
                        </button>
                        <button class="btn-primary text-sm ml-2" onclick="updateOrderStatus(${order.id})">
                            <i class="fa fa-edit mr-1"></i> Update
                        </button>
                    </td>
                </tr>
            `).join('');

            document.getElementById('all-orders').innerHTML = ordersHtml;
            document.getElementById('orders-showing').textContent = orders.length;
            document.getElementById('orders-total').textContent = orders.length;
        }

        // Filter orders
        function filterOrders() {
            const searchTerm = document.getElementById('order-search').value.toLowerCase();
            const statusFilter = document.getElementById('order-status-filter').value;

            const filteredOrders = orders.filter(order => {
                const matchesSearch = order.order_id.toLowerCase().includes(searchTerm) ||
                                     order.customer_name.toLowerCase().includes(searchTerm) ||
                                     order.customer_email.toLowerCase().includes(searchTerm);
                const matchesStatus = statusFilter === 'all' || order.status === statusFilter;
                
                return matchesSearch && matchesStatus;
            });

            const ordersHtml = filteredOrders.map(order => `
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4">${order.order_id}</td>
                    <td class="py-3 px-4">${order.customer_name}</td>
                    <td class="py-3 px-4">${formatDate(order.created_at)}</td>
                    <td class="py-3 px-4">RM${Number(order.total || order.total_amount || 0).toFixed(2)}</td>
                    <td class="py-3 px-4">
                        <span class="status-badge status-${order.status}">${capitalizeFirstLetter(order.status)}</span>
                    </td>
                    <td class="py-3 px-4">
                        <button class="btn-secondary text-sm" onclick="viewOrderDetail(${order.id})">
                            <i class="fa fa-eye mr-1"></i> View
                        </button>
                        <button class="btn-primary text-sm ml-2" onclick="updateOrderStatus(${order.id})">
                            <i class="fa fa-edit mr-1"></i> Update
                        </button>
                    </td>
                </tr>
            `).join('');

            document.getElementById('all-orders').innerHTML = ordersHtml;
            document.getElementById('orders-showing').textContent = filteredOrders.length;
        }

        // View order detail
        function viewOrderDetail(orderId) {
            const order = orders.find(o => o.id === orderId);
            if (!order) return;

            const orderDetailHtml = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-bold text-dark mb-3">Order Information</h4>
                        <div class="space-y-2">
                            <p><strong>Order ID:</strong> ${order.order_id}</p>
                            <p><strong>Date:</strong> ${formatDate(order.created_at)}</p>
                            <p><strong>Status:</strong> <span class="status-badge status-${order.status}">${capitalizeFirstLetter(order.status)}</span></p>
                            <p><strong>Payment Method:</strong> ${order.payment_method}</p>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-bold text-dark mb-3">Customer Information</h4>
                        <div class="space-y-2">
                            <p><strong>Name:</strong> ${order.customer_name}</p>
                            <p><strong>Email:</strong> ${order.customer_email}</p>
                            <p><strong>Phone:</strong> ${order.customer_phone}</p>
                            <p><strong>Address:</strong> ${order.customer_address}</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h4 class="font-bold text-dark mb-3">Delivery Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <p><strong>Delivery Date:</strong> ${order.delivery_date}</p>
                        <p><strong>Delivery Time:</strong> ${order.delivery_time}</p>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h4 class="font-bold text-dark mb-3">Order Items</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2 px-4 text-sm font-medium text-gray-500">Product</th>
                                    <th class="text-left py-2 px-4 text-sm font-medium text-gray-500">Price</th>
                                    <th class="text-left py-2 px-4 text-sm font-medium text-gray-500">Quantity</th>
                                    <th class="text-left py-2 px-4 text-sm font-medium text-gray-500">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${order.items.map(item => `
                                    <tr class="border-b">
                                        <td class="py-2 px-4">${item.name}</td>
                                        <td class="py-2 px-4">RM${item.price.toFixed(2)}</td>
                                        <td class="py-2 px-4">${item.quantity}</td>
                                        <td class="py-2 px-4">RM${(item.price * item.quantity).toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                                <tr>
                                    <td colspan="3" class="py-2 px-4 text-right font-medium">Total:</td>
                                <td class="py-2 px-4 font-medium">RM${Number(order.total || order.total_amount || 0).toFixed(2)}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
                
                <div class="mt-6 flex justify-end space-x-4">
                    <button class="btn-primary" onclick="updateOrderStatus(${order.id})">
                        <i class="fa fa-edit mr-2"></i> Update Status
                    </button>
                    <button class="btn-secondary" onclick="printOrder(${order.id})">
                        <i class="fa fa-print mr-2"></i> Print Order
                    </button>
                </div>
            `;

            document.getElementById('order-detail-content').innerHTML = orderDetailHtml;
            document.getElementById('order-detail-modal').classList.remove('hidden');
        }

        // Close order detail modal
        function closeOrderDetailModal() {
            document.getElementById('order-detail-modal').classList.add('hidden');
        }

        // Update order status
        function updateOrderStatus(orderId) {
            const order = orders.find(o => o.id === orderId);
            if (!order) return;

            const newStatus = prompt('Enter new status (pending, processing, delivered, cancelled):', order.status);
            if (newStatus && ['pending', 'processing', 'delivered', 'cancelled'].includes(newStatus)) {
                order.status = newStatus;
                alert('Order status updated successfully!');
                // Refresh order lists
                loadRecentOrders();
                loadAllOrders();
                // If order detail modal is open, refresh it
                if (!document.getElementById('order-detail-modal').classList.contains('hidden')) {
                    viewOrderDetail(orderId);
                }
            } else {
                alert('Invalid status. Please use one of: pending, processing, delivered, cancelled');
            }
        }

        // Print order
        function printOrder(orderId) {
            alert('Printing order... This would open the print dialog in a real application.');
        }

        // Load products
        function loadProducts() {
            const productsHtml = products.map(product => `
                <div class="card overflow-hidden">
                    <img src="${product.image_url}" alt="${product.name}" class="w-full h-48 object-cover">
                    <div class="p-4">
                        <h4 class="font-bold text-dark mb-2">${product.name}</h4>
                        <p class="text-sm text-gray-600 mb-2">${product.category}</p>
                        <div class="flex justify-between items-center mb-4">
                            <span class="font-bold text-primary">RM${Number(product.price || 0).toFixed(2)}</span>
                            <span class="text-sm text-gray-500">Stock: ${product.stock ?? 0}</span>
                        </div>
                        <div class="flex space-x-2">
                            <button class="btn-primary text-sm flex-1" onclick="editProduct(${product.id})">
                                <i class="fa fa-edit mr-1"></i> Edit
                            </button>
                            <button class="btn-danger text-sm" onclick="deleteProduct(${product.id})">
                                <i class="fa fa-trash mr-1"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');

            document.getElementById('products-grid').innerHTML = productsHtml;
        }

        // Edit product
        function editProduct(productId) {
            alert(`Edit product ${productId}. This would open an edit form in a real application.`);
        }

        // Delete product
        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product?')) {
                alert(`Product ${productId} deleted. This would remove the product from the database in a real application.`);
            }
        }

        // Open add product modal
        function openAddProductModal() {
            document.getElementById('add-product-modal').classList.remove('hidden');
        }

        // Close add product modal
        function closeAddProductModal() {
            document.getElementById('add-product-modal').classList.add('hidden');
            document.getElementById('add-product-form').reset();
        }

        // Add product
        function addProduct() {
            const name = document.getElementById('product-name').value;
            const category = document.getElementById('product-category').value;
            const price = parseFloat(document.getElementById('product-price').value);
            const stock = parseInt(document.getElementById('product-stock').value);
            const description = document.getElementById('product-description').value;
            const image = document.getElementById('product-image').value;

            const newProduct = {
                id: products.length + 1,
                name,
                category,
                price,
                stock,
                image_url: image,
                description
            };

            products.push(newProduct);
            alert('Product added successfully!');
            closeAddProductModal();
            loadProducts();
            loadDashboardStats();
        }

        // Load users
        function loadUsers() {
            const usersHtml = users.map(user => `
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4">${user.id}</td>
                    <td class="py-3 px-4">${user.full_name}</td>
                    <td class="py-3 px-4">${user.email}</td>
                    <td class="py-3 px-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium ${user.role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}">
                            ${capitalizeFirstLetter(user.role)}
                        </span>
                    </td>
                    <td class="py-3 px-4">${user.created_at}</td>
                    <td class="py-3 px-4">
                        <button class="btn-secondary text-sm" onclick="viewUserDetail(${user.id})">
                            <i class="fa fa-eye mr-1"></i> View
                        </button>
                        <button class="btn-primary text-sm ml-2" onclick="editUser(${user.id})">
                            <i class="fa fa-edit mr-1"></i> Edit
                        </button>
                    </td>
                </tr>
            `).join('');

            document.getElementById('all-users').innerHTML = usersHtml;
        }

        // Filter users
        function filterUsers() {
            const searchTerm = document.getElementById('user-search').value.toLowerCase();

            const filteredUsers = users.filter(user => 
                user.full_name.toLowerCase().includes(searchTerm) ||
                user.email.toLowerCase().includes(searchTerm) ||
                user.username.toLowerCase().includes(searchTerm)
            );

            const usersHtml = filteredUsers.map(user => `
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4">${user.id}</td>
                    <td class="py-3 px-4">${user.full_name}</td>
                    <td class="py-3 px-4">${user.email}</td>
                    <td class="py-3 px-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium ${user.role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}">
                            ${capitalizeFirstLetter(user.role)}
                        </span>
                    </td>
                    <td class="py-3 px-4">${user.created_at}</td>
                    <td class="py-3 px-4">
                        <button class="btn-secondary text-sm" onclick="viewUserDetail(${user.id})">
                            <i class="fa fa-eye mr-1"></i> View
                        </button>
                        <button class="btn-primary text-sm ml-2" onclick="editUser(${user.id})">
                            <i class="fa fa-edit mr-1"></i> Edit
                        </button>
                    </td>
                </tr>
            `).join('');

            document.getElementById('all-users').innerHTML = usersHtml;
        }

        // View user detail
        function viewUserDetail(userId) {
            alert(`View user ${userId}. This would show detailed user information in a real application.`);
        }

        // Edit user
        function editUser(userId) {
            alert(`Edit user ${userId}. This would open an edit form in a real application.`);
        }

        // Load admins
        function loadAdmins() {
            const adminsHtml = admins.map(admin => `
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4">${index + 1}</td>
                    <td class="py-3 px-4">${admin.full_name}</td>
                    <td class="py-3 px-4">${admin.email}</td>
                    <td class="py-3 px-4">${admin.username}</td>
                    <td class="py-3 px-4">${admin.created_at}</td>
                    <td class="py-3 px-4">
                        <button class="btn-primary text-sm" onclick="editAdmin(${admin.id})">
                            <i class="fa fa-edit mr-1"></i> Edit
                        </button>
                        <button class="btn-danger text-sm ml-2" onclick="deleteAdmin(${admin.id})">
                            <i class="fa fa-trash mr-1"></i> Delete
                        </button>
                    </td>
                </tr>
            `).join('');

            document.getElementById('all-admins').innerHTML = adminsHtml;
        }

        // Open add admin modal
        function openAddAdminModal() {
            document.getElementById('add-admin-modal').classList.remove('hidden');
        }

        // Close add admin modal
        function closeAddAdminModal() {
            document.getElementById('add-admin-modal').classList.add('hidden');
            document.getElementById('add-admin-form').reset();
        }

        // Add admin account
        function addAdminAccount() {
            const fullName = document.getElementById('admin-fullname').value;
            const email = document.getElementById('admin-email').value;
            const username = document.getElementById('admin-username').value;
            const password = document.getElementById('admin-password').value;

            // Check if admin already exists
            if (admins.some(admin => admin.email === email || admin.username === username)) {
                alert('Admin with this email or username already exists!');
                return;
            }

            const newAdmin = {
                id: admins.length + 1,
                full_name: fullName,
                email: email,
                username: username,
                // In a real application, password would be hashed
                password: password,
                created_at: new Date().toISOString().split('T')[0]
            };

            admins.push(newAdmin);
            users.push({
                id: users.length + 1,
                full_name: fullName,
                email: email,
                username: username,
                role: 'admin',
                created_at: newAdmin.created_at
            });

            alert('Admin account added successfully!');
            closeAddAdminModal();
            loadAdmins();
        }

        // Edit admin
        function editAdmin(adminId) {
            alert(`Edit admin ${adminId}. This would open an edit form in a real application.`);
        }

        // Delete admin
        function deleteAdmin(adminId) {
            if (admins.length <= 1) {
                alert('Cannot delete the last admin account!');
                return;
            }

            if (confirm('Are you sure you want to delete this admin account?')) {
                const admin = admins.find(a => a.id === adminId);
                if (admin) {
                    // Remove from admins array
                    admins = admins.filter(a => a.id !== adminId);
                    // Remove from users array
                    users = users.filter(u => !(u.email === admin.email && u.role === 'admin'));
                    
                    alert('Admin account deleted successfully!');
                    loadAdmins();
                }
            }
        }

        // Helper functions
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-MY', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function capitalizeFirstLetter(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }
    </script>
</body>
</html>
