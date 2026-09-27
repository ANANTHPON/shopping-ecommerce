<?php
session_start();
ini_set('display_errors', 0); // Secure for production
error_reporting(E_ALL);

if(empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$host_name = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (strpos($host_name, 'infinityfree') !== false || strpos($host_name, 'epizy') !== false) {
    $host = 'sql107.infinityfree.com'; $db = 'if0_43023269_kpshop_dev'; $db_user = 'if0_43023269'; $db_pass = 'Suresh130192';
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
} else {
    $host = '127.0.0.1'; $db = 'drupal10_db_dev'; $db_user = 'root'; $db_pass = ''; 
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    try { $pdo_setup = new PDO("mysql:host=$host;charset=utf8mb4", $db_user, $db_pass, $options); $pdo_setup->exec("CREATE DATABASE IF NOT EXISTS `$db`"); } catch(Exception $e) {}
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
}

$auth_error = ''; $auth_success = '';
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_role = $_SESSION['role'] ?? 'Customer';

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);


    $pdo->exec("CREATE TABLE IF NOT EXISTS ecom_users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, email VARCHAR(100) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) DEFAULT 'Customer')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecom_products (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, price DECIMAL(10,2) NOT NULL, category VARCHAR(50) NOT NULL, stock INT DEFAULT 10, image_url VARCHAR(255) DEFAULT 'https://via.placeholder.com/300')");
    try { $pdo->exec("ALTER TABLE ecom_products ADD COLUMN stock INT DEFAULT 10"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE ecom_products ADD COLUMN mrp DECIMAL(10,2) DEFAULT 0"); } catch(Exception $e) {}
    $pdo->exec("UPDATE ecom_users SET role = 'Admin' WHERE username = 'admin' OR username = 'Admin'");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecom_categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL UNIQUE)");
    if($pdo->query("SELECT COUNT(*) FROM ecom_categories")->fetchColumn() == 0) { $pdo->exec("INSERT INTO ecom_categories (name) VALUES ('Electronics'), ('Fashion'), ('Accessories')"); }
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecom_waitlist (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), email VARCHAR(100) NOT NULL UNIQUE, phone VARCHAR(20), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    
    // VISITOR ANALYTICS TABLE WITH LOCATION
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecom_visitors (id INT AUTO_INCREMENT PRIMARY KEY, ip_address VARCHAR(50), location VARCHAR(100) DEFAULT 'Unknown', visit_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    try { $pdo->exec("ALTER TABLE ecom_visitors ADD COLUMN location VARCHAR(100) DEFAULT 'Unknown'"); } catch(Exception $e) {}
    
    if(!isset($_SESSION['tracked_visit']) && !$is_logged_in) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $location = 'Remote User';
        try { 
            $stmt = $pdo->prepare("INSERT INTO ecom_visitors (ip_address, location) VALUES (?, ?)");
            $stmt->execute([$ip, $location]);
            $_SESSION['tracked_visit'] = true; 
        } catch(Exception $e) {}

    }

    if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { die("CSRF Token Validation Failed. Action aborted for security."); }
        if ($_POST['action'] === 'join_waitlist') {
            $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $phone = trim($_POST['phone'] ?? '');
            if(!empty($email)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO ecom_waitlist (name, email, phone) VALUES (?, ?, ?)"); $stmt->execute([$name, $email, $phone]);
                    $to = "admin@kpshop.com"; $subject = "New VIP Waitlist Sign Up!"; $message = "A new customer joined the VIP Waitlist!\n\nName: $name\nEmail: $email\nPhone: $phone\n\n- KP Shop System"; $headers = "From: no-reply@kpshop.com"; @mail($to, $subject, $message, $headers); 
                    $auth_success = "Thank you $name! You are on the VIP list. We will contact you soon."; $_SESSION['waitlist_joined'] = true;
                } catch (\PDOException $e) { $auth_error = "Looks like this email is already on the waitlist!"; }
            }
        }
        if ($_POST['action'] === 'signup') {
            $user = trim($_POST['username']); $email = trim($_POST['email']); $pass = $_POST['password']; $role_to_assign = $_POST['role'] ?? 'Customer';
            if (!in_array($role_to_assign, ['Admin', 'Customer'])) $role_to_assign = 'Customer';
            $hashed = password_hash($pass, PASSWORD_BCRYPT);
            try { $stmt = $pdo->prepare("INSERT INTO ecom_users (username, email, password_hash, role) VALUES (?, ?, ?, ?)"); $stmt->execute([$user, $email, $hashed, $role_to_assign]); $auth_success = "Account created! You can now log in."; } catch (\PDOException $e) { $auth_error = "Registration failed. Username/Email exists."; }
        }
        if ($_POST['action'] === 'login') {
            $user = trim($_POST['username']); $pass = $_POST['password']; $role_login = $_POST['role'] ?? 'Customer';
            $stmt = $pdo->prepare("SELECT * FROM ecom_users WHERE username = ? AND role = ?"); $stmt->execute([$user, $role_login]); $db_user_data = $stmt->fetch();
            if ($db_user_data && password_verify($pass, $db_user_data['password_hash'])) { session_regenerate_id(true); $_SESSION['logged_in'] = true; $_SESSION['username'] = $db_user_data['username']; $_SESSION['role'] = $db_user_data['role']; header("Location: index.php"); exit; } else { $auth_error = "Invalid username, password, or role mismatch!"; }
        }
        if ($is_logged_in && $user_role === 'Admin') {
            if ($_POST['action'] === 'add_product') {
                $name = $_POST['name'] ?? ''; $mrp = $_POST['mrp'] ?? 0; $price = $_POST['price'] ?? 0; $cat = $_POST['category'] ?? 'General'; $stock = $_POST['stock'] ?? 10;
                $img_url = "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=300&q=80";
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp']; $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
                    $mime = mime_content_type($_FILES['image']['tmp_name']); $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    if(in_array($mime, $allowed_mime) && in_array($ext, $allowed_ext)) { $filename = uniqid('prod_') . '.' . $ext; if(move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $filename)) { $img_url = 'uploads/' . $filename; } } else { die("Security Error: Invalid file type."); }
                }
                if (!empty($name) && $price > 0) { $stmt = $pdo->prepare("INSERT INTO ecom_products (name, mrp, price, category, stock, image_url) VALUES (?, ?, ?, ?, ?, ?)"); $stmt->execute([$name, $mrp, $price, $cat, $stock, $img_url]); header("Location: index.php"); exit; }
            }
            if ($_POST['action'] === 'add_category') { $cat_name = trim($_POST['new_category']); if(!empty($cat_name)){ try { $stmt = $pdo->prepare("INSERT INTO ecom_categories (name) VALUES (?)"); $stmt->execute([$cat_name]); } catch(Exception $e) {} } header("Location: index.php"); exit; }
        }
    }
    if ($is_logged_in && $user_role === 'Admin' && isset($_GET['delete_product'])) { $stmt = $pdo->prepare("DELETE FROM ecom_products WHERE id = ?"); $stmt->execute([$_GET['delete_product']]); header("Location: index.php"); exit; }
    if ($is_logged_in && $user_role === 'Admin' && isset($_GET['delete_category'])) { $stmt = $pdo->prepare("DELETE FROM ecom_categories WHERE id = ?"); $stmt->execute([$_GET['delete_category']]); header("Location: index.php"); exit; }
    if ($is_logged_in && $user_role === 'Admin' && isset($_GET['delete_waitlist'])) { $stmt = $pdo->prepare("DELETE FROM ecom_waitlist WHERE id = ?"); $stmt->execute([$_GET['delete_waitlist']]); header("Location: index.php"); exit; }
    if ($is_logged_in && $user_role === 'Admin' && isset($_GET['clear_visitors'])) { $pdo->exec("TRUNCATE TABLE ecom_visitors"); header("Location: index.php"); exit; }

    $products = $pdo->query("SELECT * FROM ecom_products ORDER BY id DESC")->fetchAll();
    $all_categories = $pdo->query("SELECT * FROM ecom_categories ORDER BY name ASC")->fetchAll();
    $waitlist_users = $pdo->query("SELECT * FROM ecom_waitlist ORDER BY id DESC")->fetchAll();
    $visitor_logs = $pdo->query("SELECT * FROM ecom_visitors ORDER BY id DESC LIMIT 50")->fetchAll();
    
    $total_products = count($products); $low_stock = 0; foreach($products as $p) { if(isset($p['stock']) && $p['stock'] < 5) $low_stock++; }
    $total_users = $pdo->query("SELECT COUNT(*) FROM ecom_users")->fetchColumn(); $total_categories = count($all_categories);
    $total_waitlist = count($waitlist_users);
    $total_unique_visitors = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM ecom_visitors")->fetchColumn();
    $total_page_hits = count($visitor_logs);
    
} catch (\PDOException $e) { die("Database Error: " . $e->getMessage()); }

$banner_imgs = ["https://images.unsplash.com/photo-1550009158-9ebf69173e03?w=600&q=80", "https://images.unsplash.com/photo-1445205170230-053b83016050?w=600&q=80", "https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&q=80", "https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&q=80", "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KP Shop - Premium E-Commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        html { scroll-behavior: smooth; padding-top:0px; } body { background-color: #fcfcfd; font-family: 'Poppins', sans-serif; color: #1a202c; }
        .navbar { background-color: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); box-shadow: 0 4px 30px rgba(0,0,0,0.04); padding: 18px 0; border-bottom: 1px solid #f1f5f9; }
        .navbar-brand { font-weight: 800; color: #ff4757 !important; font-size: 28px; letter-spacing: -1px; }
        .nav-link { font-weight: 600; color: #64748b; margin: 0 12px; transition: all 0.3s; position: relative; }
        .nav-link:hover { color: #ff4757; } .nav-link.active { color: #ff4757; }
        .nav-link.active::after { content: ''; position: absolute; bottom: -5px; left: 0; width: 100%; height: 3px; background: #ff4757; border-radius: 5px; }
        .cart-icon-container { position: relative; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; width: 50px; height: 50px; border-radius: 14px; background: #f8fafc; border: 1.5px solid #e2e8f0; transition: all 0.3s ease; }
        .cart-icon-container:hover { background: #ff4757; border-color: #ff4757; color: white !important; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(255,71,87,0.2); }
        .cart-icon-container:hover .cart-icon { color: white !important; }
        .cart-badge { position: absolute; top: -6px; right: -6px; background-color: #ff4757; color: white; font-size: 0.75rem; padding: 4px 8px; border-radius: 8px; font-weight: 800; border: 2px solid white; }
        .btn-outline-primary { color: #ff4757; border-color: #ff4757; border-radius: 12px; font-weight: 600; border-width: 2px; }
        .btn-outline-primary:hover { background-color: #ff4757; color: #fff; box-shadow: 0 8px 20px rgba(255,71,87,0.25); transform: translateY(-2px); }
        .btn-primary { background-color: #ff4757; border: none; border-radius: 12px; font-weight: 600; box-shadow: 0 8px 20px rgba(255, 71, 87, 0.25); transition: all 0.3s; }
        .btn-primary:hover { background-color: #ff6b81; transform: translateY(-2px); box-shadow: 0 10px 25px rgba(255, 71, 87, 0.35); }
        .form-select { border-radius: 12px; border: 2px solid #e2e8f0; padding: 14px 18px; font-weight: 600; color: #1e293b; background-color: #f8fafc; transition: all 0.3s; cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23ff4757' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E"); background-size: 14px; }
        .form-select:hover { border-color: #cbd5e1; }
        .form-select:focus { border-color: #ff4757; background-color: #ffffff; box-shadow: 0 0 0 4px rgba(255, 71, 87, 0.15); }
        .form-floating > .form-select { padding-top: 1.625rem; padding-bottom: 0.625rem; }
        .hero { background: radial-gradient(circle at 0% 0%, #fff0f2 0%, #ffffff 70%); padding: 100px 0; border-bottom: 1px solid #f1f5f9; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -100px; right: -100px; width: 400px; height: 400px; background: radial-gradient(circle, rgba(255,71,87,0.1) 0%, rgba(255,255,255,0) 70%); border-radius: 50%; }
        .hero h1 { font-weight: 800; color: #0f172a; font-size: 4.5rem; line-height: 1.1; margin-bottom: 25px; letter-spacing: -1.5px; }
        .hero-img-box { position: relative; z-index: 2; }
        .hero-img { width: 480px; height: 480px; object-fit: cover; border-radius: 30px; box-shadow: 0 30px 60px rgba(0,0,0,0.15); animation: float 6s ease-in-out infinite; border: 10px solid white; }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-20px); } 100% { transform: translateY(0px); } }
        .cat-card { border-radius: 30px; overflow: hidden; position: relative; cursor: pointer; border: none; height: 300px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .cat-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease; }
        .cat-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.2) 100%); transition: all 0.4s ease; opacity: 0.8; }
        .cat-card:hover { transform: translateY(-15px); box-shadow: 0 20px 40px rgba(255,71,87,0.2); }
        .cat-card:hover .cat-img { transform: scale(1.1); }
        .cat-card:hover .cat-overlay { opacity: 0.9; background: linear-gradient(to top, rgba(255,71,87,0.9) 0%, rgba(0,0,0,0.2) 100%); }
        .cat-title { position: absolute; bottom: 40px; left: 30px; color: white; font-weight: 800; font-size: 2rem; z-index: 2; }
        .cat-explore { position: absolute; bottom: 15px; left: 30px; color: white; font-weight: 600; font-size: 0.9rem; opacity: 0; transform: translateY(20px); transition: all 0.4s ease; z-index: 2; }
        .cat-card:hover .cat-explore { opacity: 1; transform: translateY(0); }
        .filter-sidebar { background: #ffffff; border-radius: 24px; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.04); border: 1px solid #f1f5f9; }
        .form-check-input { width: 1.2rem; height: 1.2rem; border-radius: 6px; border: 2px solid #cbd5e1; cursor: pointer; }
        .form-check-input:checked { background-color: #ff4757; border-color: #ff4757; }
        .product-card { border: none; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); transition: all 0.4s ease; overflow: hidden; background: #fff; position: relative; border: 1px solid #f8fafc; }
        .product-card:hover { transform: translateY(-10px); box-shadow: 0 20px 50px rgba(0,0,0,0.08); border-color: #e2e8f0; }
        .product-img { width: 100%; height: 260px; object-fit: cover; transition: transform 0.6s ease; border-radius: 24px 24px 0 0; }
        .product-card:hover .product-img { transform: scale(1.05); }
        .product-price { font-weight: 800; color: #0f172a; font-size: 1.4rem; }
        .mrp-price { font-weight: 500; color: #94a3b8; font-size: 0.95rem; text-decoration: line-through; margin-right: 8px; }
        .discount-badge { position: absolute; top: 15px; left: 15px; background: #ff4757; color: white; padding: 6px 12px; border-radius: 10px; font-weight: 800; font-size: 0.8rem; z-index: 2; box-shadow: 0 4px 15px rgba(255, 71, 87, 0.4); letter-spacing: 0.5px; }
        .wishlist-btn { position: absolute; top: 15px; right: 15px; width: 40px; height: 40px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 1.2rem; z-index: 2; box-shadow: 0 4px 10px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.3s; }
        .wishlist-btn:hover { color: #ff4757; transform: scale(1.1); }
        .product-cat { font-size: 0.75rem; color: #ff4757; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 800; }
        .product-title { font-weight: 700; color: #1e293b; font-size: 1.15rem; margin-bottom: 10px; line-height: 1.4; }
        .btn-add-cart { border-radius: 12px; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; border: 2px solid #e2e8f0; color: #64748b; background: white; }
        .btn-add-cart:hover:not(:disabled) { background: #ff4757; border-color: #ff4757; color: white; box-shadow: 0 5px 15px rgba(255,71,87,0.3); }
        .modal-content { border-radius: 28px; border: none; box-shadow: 0 25px 60px rgba(0,0,0,0.2); overflow: hidden; }
        .modal-header-custom { padding: 35px 25px; text-align: center; color: white; position: relative; }
        .modal-header-custom .btn-close { position: absolute; top: 20px; right: 20px; background-color: rgba(255,255,255,0.9); border-radius: 50%; opacity: 1; padding: 10px; }
        .form-floating > .form-control { border-radius: 14px; border: 2px solid #e2e8f0; font-weight: 500; }
        .form-floating > .form-control:focus { border-color: #ff4757; box-shadow: 0 0 0 4px rgba(255, 71, 87, 0.15); }
        .admin-stat-card { border-radius: 24px; padding: 30px; color: white; border: none; box-shadow: 0 15px 35px rgba(0,0,0,0.1); position: relative; overflow: hidden; transition: transform 0.3s; }
        .admin-stat-card:hover { transform: translateY(-5px); }
        .stat-icon { position: absolute; right: -15px; bottom: -25px; font-size: 9rem; opacity: 0.15; transform: rotate(-15deg); }
        .admin-table-card { border-radius: 24px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.04); border: 1px solid #f1f5f9; }
        .stock-badge { font-size: 0.85rem; padding: 6px 14px; border-radius: 50px; font-weight: 700; }
        .product-item-container { transition: all 0.4s ease; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="fa-solid fa-layer-group me-2" style="color:#ff4757;"></i><span style="color:#ff4757;">KP</span> Shop</a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><i class="fa-solid fa-bars fs-3 text-dark"></i></button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <?php if($user_role === 'Admin' && $is_logged_in): ?>
                    <li class="nav-item"><a class="nav-link active" href="#">Dashboard</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link active" id="nav-home" href="#" onclick="switchTab('home', this); return false;">Home</a></li>
                    <li class="nav-item"><a class="nav-link" id="nav-shop" href="#" onclick="resetFilters(); switchTab('shop', this); return false;">Shop</a></li>
                    <li class="nav-item"><a class="nav-link" id="nav-categories" href="#" onclick="switchTab('categories', this); return false;">Categories</a></li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-3 mt-3 mt-lg-0">
                <?php if($is_logged_in): ?>
                    <?php if($user_role !== 'Admin'): ?>
                        <div class="cart-icon-container" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas"><i class="fa-solid fa-bag-shopping fs-5 text-dark cart-icon"></i><span class="cart-badge" id="cartCount">0</span></div>
                    <?php endif; ?>
                    <span class="fw-bold text-dark me-2 d-none d-md-inline"><i class="fa-solid fa-circle-user me-2 text-muted fs-5 align-middle"></i><?= htmlspecialchars($_SESSION['username']) ?> <span class="badge bg-dark rounded-pill ms-1 fw-normal" style="font-size:0.65rem;"><?= $user_role ?></span></span>
                    <a href="?logout=1" class="btn btn-outline-danger px-4 rounded-pill fw-bold" style="border-width:2px;">Logout</a>
                <?php else: ?>
                    <div class="cart-icon-container" data-bs-toggle="modal" data-bs-target="#loginModal"><i class="fa-solid fa-bag-shopping fs-5 text-dark cart-icon"></i></div>
                    <button class="btn btn-outline-primary px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#loginModal">Log In</button>
                    <button class="btn btn-primary px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#signupModal">Sign Up</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php if($is_logged_in && $user_role === 'Admin'): ?>
    <div class="container py-5" style="max-width: 1300px;">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div><h2 class="fw-bold mb-1 text-dark">Store Overview</h2><p class="text-muted">Manage your inventory, categories, and stock.</p></div>
            <div>
                <button class="btn btn-outline-danger px-4 py-3 fw-bold shadow-sm me-2 rounded-pill" onclick="new bootstrap.Modal(document.getElementById('waitlistModal')).show()"><i class="fa-solid fa-eye me-2"></i> Preview VIP Popup</button> 
                <button class="btn btn-outline-dark px-4 py-3 fw-bold shadow-sm me-2 rounded-pill" data-bs-toggle="modal" data-bs-target="#addCategoryModal"><i class="fa-solid fa-tags me-2"></i> Add Category</button>
                <button class="btn btn-primary px-4 py-3 fw-bold shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addProductModal"><i class="fa-solid fa-plus me-2"></i> Add Product</button>
            </div>
        </div>
        
        <div class="row g-4 mb-5">
            <div class="col-md-3"><div class="card admin-stat-card" style="background: linear-gradient(135deg, #1e293b, #334155);"><i class="fa-solid fa-box stat-icon"></i><h6 class="text-white-50 fw-bold text-uppercase letter-spacing-1">Products</h6><h2 class="fw-bold mb-0 display-5"><?= $total_products ?></h2></div></div>
            <div class="col-md-3"><div class="card admin-stat-card" style="background: linear-gradient(135deg, #10b981, #34d399);"><i class="fa-solid fa-users stat-icon"></i><h6 class="text-white-50 fw-bold text-uppercase letter-spacing-1">Unique Visitors</h6><h2 class="fw-bold mb-0 display-5"><?= $total_unique_visitors ?></h2></div></div>
            <div class="col-md-3"><div class="card admin-stat-card" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);"><i class="fa-solid fa-clipboard-list stat-icon"></i><h6 class="text-white-50 fw-bold text-uppercase letter-spacing-1">Waitlist Leads</h6><h2 class="fw-bold mb-0 display-5"><?= $total_waitlist ?></h2></div></div>
            <div class="col-md-3"><div class="card admin-stat-card" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);"><i class="fa-solid fa-eye stat-icon"></i><h6 class="text-white-50 fw-bold text-uppercase letter-spacing-1">Total Page Hits</h6><h2 class="fw-bold mb-0 display-5"><?= $total_page_hits ?></h2></div></div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-lg-8">
                <div class="card admin-table-card h-100">
                    <div class="card-header bg-white py-4 px-4 border-0 d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0"><i class="fa-solid fa-list-check me-2" style="color:#ff4757;"></i>Products Inventory</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle text-nowrap">
                                <thead style="background-color: #f8fafc;"><tr><th class="text-muted fw-bold ps-4 py-3">Product</th><th class="text-muted fw-bold py-3">Pricing</th><th class="text-muted fw-bold text-center py-3">Stock</th><th class="text-end text-muted fw-bold px-4 py-3">Manage</th></tr></thead>
                                <tbody>
                                    <?php foreach($products as $p): $stock = $p['stock'] ?? 0; $mrp = $p['mrp'] ?? 0; $stockClass = $stock > 10 ? 'bg-success-subtle text-success' : ($stock > 0 ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger'); ?>
                                        <tr><td class="ps-4 py-3"><div class="d-flex align-items-center"><img src="<?= htmlspecialchars($p['image_url']) ?>" class="rounded-4 shadow-sm me-3" style="width: 55px; height: 55px; object-fit: cover;"><div><div class="fw-bold text-dark fs-6"><?= htmlspecialchars($p['name']) ?></div><span class="badge bg-light text-secondary border mt-1 px-2"><?= htmlspecialchars($p['category']) ?></span></div></div></td><td class="py-3"><div class="fw-bold text-dark fs-5">$<?= number_format($p['price'], 2) ?></div><?php if($mrp > $p['price']): ?><div class="small text-muted text-decoration-line-through fw-semibold">MRP: $<?= number_format($mrp, 2) ?></div><?php endif; ?></td><td class="text-center py-3"><span class="badge <?= $stockClass ?> stock-badge"><?= $stock ?> units</span></td><td class="text-end px-4 py-3"><a href="?delete_product=<?= $p['id'] ?>" class="btn btn-sm btn-light text-danger rounded-circle shadow-sm" style="width:40px;height:40px;line-height:26px;" onclick="return confirm('Delete this product?');"><i class="fa-solid fa-trash"></i></a></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card admin-table-card h-100">
                    <div class="card-header bg-white py-4 px-4 border-0 d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0"><i class="fa-solid fa-satellite-dish me-2 text-primary"></i>Live Traffic</h5><a href="?clear_visitors=1" class="badge bg-danger text-white text-decoration-none px-3 py-2 rounded-pill">Clear All</a></div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <ul class="list-group list-group-flush">
                            <?php if(count($visitor_logs) == 0): ?><li class="list-group-item text-center py-4 text-muted fw-bold border-0">No visits recorded yet.</li><?php endif; ?>
                            <?php foreach($visitor_logs as $v): ?>
                            <li class="list-group-item py-3 px-4 border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark fs-6"><i class="fa-solid fa-globe text-muted me-2"></i><?= htmlspecialchars($v['ip_address']) ?></span>
                                    <span class="small text-muted fw-semibold"><?= date('M j, g:i A', strtotime($v['visit_time'])) ?></span>
                                </div>
                                <div class="text-primary small fw-bold ms-4"><i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($v['location']) ?></div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card admin-table-card">
            <div class="card-header bg-white py-4 px-4 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-clipboard-list me-2 text-warning"></i>VIP Waitlist Leads</h5>
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Export Coming Soon</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle text-nowrap">
                        <thead style="background-color: #f8fafc;"><tr><th class="text-muted fw-bold ps-4 py-3">Name</th><th class="text-muted fw-bold py-3">Email Address</th><th class="text-muted fw-bold py-3">Phone</th><th class="text-muted fw-bold py-3">Signed Up</th><th class="text-end text-muted fw-bold px-4 py-3">Manage</th></tr></thead>
                        <tbody>
                            <?php if(count($waitlist_users) == 0): ?><tr><td colspan="5" class="text-center py-5 text-muted fw-bold">No waitlist signups yet.</td></tr><?php endif; ?>
                            <?php foreach($waitlist_users as $w): ?>
                                <tr><td class="ps-4 py-3 fw-bold text-dark"><?= htmlspecialchars($w['name']) ?></td><td class="py-3 text-primary"><i class="fa-regular fa-envelope me-2"></i><a href="mailto:<?= htmlspecialchars($w['email']) ?>" class="text-decoration-none"><?= htmlspecialchars($w['email']) ?></a></td><td class="py-3"><i class="fa-solid fa-phone me-2 text-muted"></i><?= htmlspecialchars($w['phone']) ?></td><td class="py-3 text-muted small"><?= date('M j, Y g:i A', strtotime($w['created_at'])) ?></td><td class="text-end px-4 py-3"><a href="?delete_waitlist=<?= $w['id'] ?>" class="btn btn-sm btn-light text-danger rounded-circle shadow-sm" style="width:40px;height:40px;line-height:26px;" onclick="return confirm('Remove lead?');"><i class="fa-solid fa-trash"></i></a></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- CUSTOMER E-COMMERCE VIEW -->
    <div id="hero-section">
        <section class="hero text-center text-md-start">
            <div class="container">
                <div class="row align-items-center g-5">
                    <div class="col-md-6 pe-lg-5">
                        <span class="badge bg-dark px-4 py-2 rounded-pill mb-4 text-uppercase fw-bold shadow-sm" style="letter-spacing:1px;"><i class="fa-solid fa-bolt text-warning me-2"></i>2026 Collection</span>
                        <?php if($is_logged_in): ?>
                            <h1>Welcome back, <br><span style="color:#ff4757;"><?= htmlspecialchars($_SESSION['username']) ?></span>.</h1>
                            <p class="fs-5 text-muted mb-4 mt-3">Ready to upgrade your lifestyle? Explore our newest arrivals handpicked just for you.</p>
                            <a href="#" onclick="switchTab('shop', document.getElementById('nav-shop')); return false;" class="btn btn-primary btn-lg px-5 py-3 rounded-pill fs-5"><i class="fa-solid fa-compass me-2"></i>Explore Store</a>
                        <?php else: ?>
                            <h1>Discover Your <br>New Style.</h1>
                            <p class="fs-5 text-muted mb-4 mt-3">Shop the absolute latest trends in fashion, cutting-edge electronics, and premium accessories.</p>
                            <button class="btn btn-primary btn-lg px-5 py-3 rounded-pill fs-5 shadow-lg" data-bs-toggle="modal" data-bs-target="#signupModal"><i class="fa-solid fa-user-plus me-2"></i>Join Exclusive Club</button>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 d-none d-md-block text-center hero-img-box"><img src="https://images.unsplash.com/photo-1483985988355-763728e1935b?w=800&q=80" class="img-fluid hero-img"></div>
                </div>
            </div>
        </section>
    </div>

    <div id="categories-section">
        <?php if(count($all_categories) > 0): ?>
        <section class="py-5 mt-5">
            <div class="container">
                <div class="text-center mb-5"><h2 class="fw-bold display-6" style="color: #0f172a; letter-spacing: -1px;">Shop by Category</h2><p class="text-muted fs-5">Find exactly what you're looking for</p></div>
                <div class="row g-4 justify-content-center">
                    <?php $count=0; foreach($all_categories as $c): if($count>=3) break; ?>
                    <div class="col-md-4">
                        <div class="card cat-card" onclick="forceFilter('<?= htmlspecialchars(addslashes($c['name'])) ?>')">
                            <img src="<?= $banner_imgs[$count % count($banner_imgs)] ?>" class="cat-img"><div class="cat-overlay"></div><div class="cat-title"><?= htmlspecialchars($c['name']) ?></div><div class="cat-explore">Explore Collection <i class="fa-solid fa-arrow-right ms-2"></i></div>
                        </div>
                    </div>
                    <?php $count++; endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <div id="shop-section">
        <section class="py-5" style="background: #f8fafc;">
            <div class="container">
                <div class="row g-5">
                    <div class="col-lg-3">
                        <div class="filter-sidebar sticky-top" style="top: 110px;">
                            <h4 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-sliders me-2 text-primary"></i>Filters</h4>
                            <h6 class="fw-bold mb-3 text-muted text-uppercase letter-spacing-1 fs-7">Categories</h6>
                            <div class="form-check mb-3 d-flex align-items-center"><input class="form-check-input filter-checkbox me-3" type="checkbox" value="all" id="catAll" checked><label class="form-check-label fw-bold text-dark w-100 cursor-pointer" for="catAll">All Products</label></div>
                            <?php foreach($all_categories as $c): ?><div class="form-check mb-3 d-flex align-items-center"><input class="form-check-input filter-checkbox me-3" type="checkbox" value="<?= htmlspecialchars($c['name']) ?>" id="cat_<?= $c['id'] ?>"><label class="form-check-label fw-semibold text-secondary w-100 cursor-pointer" for="cat_<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></label></div><?php endforeach; ?>
                            <h6 class="fw-bold mb-3 mt-5 text-muted text-uppercase letter-spacing-1 fs-7">Price Range</h6>
                            <div class="d-flex justify-content-between text-dark fw-bold mb-2"><span>Up to</span><span id="priceVal" class="text-danger fs-5">$1000</span></div>
                            <input type="range" class="form-range" min="0" max="1000" step="10" value="1000" id="priceRange" style="accent-color: #ff4757; height: 6px;">
                            <button class="btn btn-dark w-100 mt-5 rounded-pill py-3 fw-bold shadow-sm" onclick="applyFilters()">Apply Filters</button>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div class="d-flex justify-content-between align-items-end mb-4 border-bottom pb-3">
                            <h3 class="fw-bold mb-0 text-dark display-6" style="letter-spacing:-1px;">The Collection</h3>
                            <select class="form-select w-auto shadow-sm py-2 px-4" onchange="sortProducts(this.value)"><option value="featured">Sort by: Featured</option><option value="low">Price: Low to High</option><option value="high">Price: High to Low</option></select>
                        </div>
                        <div class="row g-4" id="productGrid">
                            <?php foreach($products as $p): $mrp = $p['mrp'] ?? 0; $discount = ($mrp > $p['price']) ? round((($mrp - $p['price']) / $mrp) * 100) : 0; ?>
                            <div class="col-md-6 col-lg-4 product-item-container" data-category="<?= htmlspecialchars($p['category']) ?>" data-price="<?= $p['price'] ?>">
                                <div class="card product-card h-100">
                                    <?php if($discount > 0): ?><div class="discount-badge"><?= $discount ?>% OFF</div><?php endif; ?><div class="wishlist-btn"><i class="fa-regular fa-heart"></i></div>
                                    <div style="overflow:hidden; border-radius: 24px 24px 0 0;"><img src="<?= htmlspecialchars($p['image_url']) ?>" class="product-img"></div>
                                    <div class="card-body p-4 d-flex flex-column">
                                        <div class="product-cat mb-2"><?= htmlspecialchars($p['category']) ?></div><h5 class="product-title"><?= htmlspecialchars($p['name']) ?></h5>
                                        <?php if(isset($p['stock']) && $p['stock'] <= 5 && $p['stock'] > 0): ?><div class="text-warning small fw-bold mb-2"><i class="fa-solid fa-fire me-1"></i>Selling fast! Only <?= $p['stock'] ?> left</div><?php elseif(isset($p['stock']) && $p['stock'] == 0): ?><div class="text-danger small fw-bold mb-2"><i class="fa-solid fa-ban me-1"></i>Out of Stock</div><?php endif; ?>
                                        <div class="d-flex justify-content-between align-items-end mt-auto pt-3">
                                            <div><?php if($discount > 0): ?><div class="mrp-price">$<?= number_format($mrp, 2) ?></div><?php endif; ?><div class="product-price">$<?= number_format($p['price'], 2) ?></div></div>
                                            <?php if($is_logged_in): ?><button class="btn btn-add-cart" onclick="addToCart(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', <?= $p['price'] ?>, '<?= addslashes($p['image_url']) ?>', this)" <?= (isset($p['stock']) && $p['stock']==0) ? 'disabled' : '' ?>><i class="fa-solid fa-cart-plus fs-5"></i></button>
                                            <?php else: ?><button class="btn btn-add-cart" data-bs-toggle="modal" data-bs-target="#loginModal"><i class="fa-solid fa-lock fs-5"></i></button><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div id="noProducts" class="col-12 text-center py-5 my-5" style="display:none;"><div class="bg-white p-5 rounded-4 shadow-sm d-inline-block border"><i class="fa-solid fa-magnifying-glass fa-3x text-muted mb-3 opacity-25"></i><h4 class="fw-bold text-dark">No products found</h4><p class="text-muted">Try adjusting your filters.</p><button class="btn btn-outline-dark mt-3 rounded-pill px-4 fw-bold" onclick="resetFilters()">Clear All Filters</button></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
<?php endif; ?>

<!-- MODALS -->
<div class="modal fade" id="addProductModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header-custom" style="background: linear-gradient(135deg, #0f172a, #334155);"><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button><h3 class="fw-bold mb-0"><i class="fa-solid fa-box-open me-2 text-primary"></i>Add Inventory</h3></div><div class="modal-body p-5 bg-white"><form method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="add_product"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><div class="row g-4"><div class="col-md-12"><label class="border rounded-4 p-4 text-center d-block cursor-pointer bg-light" style="border-style:dashed!important; border-width:2px!important;"><input type="file" name="image" accept="image/*" class="d-none" id="imgUpload" onchange="document.getElementById('fileName').innerText = this.files[0].name;"><i class="fa-solid fa-cloud-arrow-up fa-3x text-primary mb-3"></i><h5 class="fw-bold text-dark mb-1">Upload Product Image</h5><p class="text-muted small mb-0" id="fileName">Supports JPG, PNG (Max 2MB)</p></label></div><div class="col-md-12"><div class="form-floating"><input type="text" name="name" class="form-control" required><label>Product Name</label></div></div><div class="col-md-4"><div class="form-floating"><input type="number" step="0.01" name="mrp" class="form-control" required><label>Original MRP ($)</label></div></div><div class="col-md-4"><div class="form-floating"><input type="number" step="0.01" name="price" class="form-control" required><label>Sale Price ($)</label></div></div><div class="col-md-4"><div class="form-floating"><input type="number" name="stock" class="form-control" required value="10"><label>Initial Stock</label></div></div><div class="col-md-12"><div class="form-floating"><select name="category" class="form-select" required><?php foreach($all_categories as $c): ?><option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select><label>Category</label></div></div><div class="col-12 mt-4"><button type="submit" class="btn btn-dark w-100 py-3 rounded-pill fw-bold fs-5">Save to Database</button></div></div></form></div></div></div></div>
<div class="modal fade" id="addCategoryModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header-custom" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);"><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button><h3 class="fw-bold mb-0"><i class="fa-solid fa-tags me-2"></i>New Category</h3></div><div class="modal-body p-5 bg-white"><form method="POST"><input type="hidden" name="action" value="add_category"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><div class="form-floating mb-4"><input type="text" name="new_category" class="form-control" required><label>Category Name</label></div><button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold fs-5" style="background:#3b82f6; border:none;">Create Category</button></form></div></div></div></div>

<?php if($is_logged_in && $user_role === 'Customer'): ?>
<div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="cartOffcanvas" style="border-radius: 24px 0 0 24px; width:450px;"><div class="offcanvas-header border-bottom p-4"><h4 class="offcanvas-title fw-bold text-dark"><i class="fa-solid fa-bag-shopping me-2 text-primary"></i>Your Cart</h4><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div><div class="offcanvas-body d-flex flex-column p-4"><div id="cartItemsContainer" class="flex-grow-1 overflow-auto pe-2"></div><div class="cart-total-box mt-auto pt-4 border-top"><div class="d-flex justify-content-between mb-4"><span class="text-muted fw-bold fs-5">Subtotal</span><span class="fw-bold fs-4 text-dark" id="cartSubtotal">$0.00</span></div><a href="checkout.php" class="btn btn-dark w-100 py-3 rounded-pill fw-bold fs-5 shadow-lg">Secure Checkout <i class="fa-solid fa-arrow-right ms-2"></i></a></div></div></div>
<?php endif; ?>

<!-- Auth Modals -->
<div class="modal fade" id="loginModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 420px;"><div class="modal-content"><div class="modal-header-custom" style="background: linear-gradient(135deg, #ff4757, #ff6b81);"><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button><h3 class="fw-bold mb-0">Welcome Back!</h3></div><div class="modal-body p-5 bg-white"><?php if($auth_error && ($_POST['action']??'')==="login"): ?><div class="alert alert-danger p-3 mb-4 rounded-3 fw-bold border-0 shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= $auth_error ?></div><?php endif; ?><form method="POST"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><div class="form-floating mb-4"><input type="text" name="username" class="form-control" required><label>Username</label></div><div class="form-floating mb-4"><input type="password" name="password" class="form-control" required><label>Password</label></div><div class="form-floating mb-5"><select name="role" class="form-select" required><option value="Customer">Customer (Storefront)</option><option value="Admin">Store Admin (Dashboard)</option></select><label>Account Role</label></div><button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold fs-5">Login to Account</button></form></div></div></div></div>
<div class="modal fade" id="signupModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 420px;"><div class="modal-content"><div class="modal-header-custom" style="background: linear-gradient(135deg, #0f172a, #334155);"><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button><h3 class="fw-bold mb-0">Create Account</h3></div><div class="modal-body p-5 bg-white"><?php if($auth_error && ($_POST['action']??'')==="signup"): ?><div class="alert alert-danger p-3 mb-4 rounded-3 fw-bold border-0 shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= $auth_error ?></div><?php endif; ?><?php if($auth_success && ($_POST['action']??'')==="signup"): ?><div class="alert alert-success p-3 mb-4 rounded-3 fw-bold border-0 shadow-sm"><i class="fa-solid fa-check me-2"></i><?= $auth_success ?></div><?php endif; ?><form method="POST"><input type="hidden" name="action" value="signup"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><div class="form-floating mb-4"><input type="text" name="username" class="form-control" required><label>Username</label></div><div class="form-floating mb-4"><input type="email" name="email" class="form-control" required><label>Email Address</label></div><div class="form-floating mb-4"><input type="password" name="password" class="form-control" required><label>Password</label></div><div class="form-floating mb-5"><select name="role" class="form-select" required><option value="Customer">Customer (Storefront)</option><option value="Admin">Store Admin (Dashboard)</option></select><label>Account Role</label></div><button type="submit" class="btn btn-dark w-100 rounded-pill py-3 fw-bold fs-5">Register Now</button></form></div></div></div></div>

<!-- VIP Waitlist Modal -->
<div class="modal fade" id="waitlistModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content" style="border-radius: 30px; border: none; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.3);">
            <div class="p-5 text-center text-white" style="background: linear-gradient(135deg, #ff4757, #ff6b81); position: relative;">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="position: absolute; top: 25px; right: 25px; z-index: 5;"></button>
                <i class="fa-solid fa-gift fa-4x mb-3 text-white shadow-sm" style="filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));"></i>
                <h2 class="fw-bold mb-1">VIP Launch Alert!</h2><p class="mb-0 text-white-50 fw-bold fs-5">Get 50% OFF on Opening Day 🚀</p>
            </div>
            <div class="p-5 bg-white">
                <?php if($auth_error && ($_POST['action']??'')==="join_waitlist"): ?><div class="alert alert-warning p-3 mb-4 rounded-3 fw-bold border-0 shadow-sm"><i class="fa-solid fa-circle-exclamation me-2"></i><?= $auth_error ?></div><?php endif; ?>
                <?php if($auth_success && ($_POST['action']??'')==="join_waitlist"): ?><div class="alert alert-success p-3 mb-4 rounded-3 fw-bold border-0 shadow-sm"><i class="fa-solid fa-check me-2"></i><?= $auth_success ?></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="join_waitlist"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="form-floating mb-3"><input type="text" name="name" class="form-control" required><label>Full Name</label></div>
                    <div class="form-floating mb-3"><input type="email" name="email" class="form-control" required><label>Email Address</label></div>
                    <div class="form-floating mb-4"><input type="tel" name="phone" class="form-control" required><label>Phone Number</label></div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold fs-5 shadow-lg">Join the VIP List</button>
                    <div class="text-center mt-4"><a href="#" data-bs-dismiss="modal" class="text-muted small text-decoration-none fw-bold">No thanks, I'll pay full price</a></div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    let cart = JSON.parse(localStorage.getItem('kpshopCart')) || [];
    function updateCartUI() { const container = document.getElementById('cartItemsContainer'); const countBadge = document.getElementById('cartCount'); const subtotalEl = document.getElementById('cartSubtotal'); if(!container) return; const totalItems = cart.reduce((sum, item) => sum + item.qty, 0); countBadge.innerText = totalItems; countBadge.classList.remove('cart-bounce'); void countBadge.offsetWidth; countBadge.classList.add('cart-bounce'); container.innerHTML = ''; let subtotal = 0; if(cart.length === 0) { container.innerHTML = `<div class="text-center py-5 text-muted h-100 d-flex flex-column justify-content-center"><i class="fa-solid fa-bag-shopping fa-4x mb-4 text-light"></i><h4 class="fw-bold text-secondary">Your cart is empty</h4></div>`; } else { cart.forEach((item, index) => { subtotal += (item.price * item.qty); container.innerHTML += `<div class="d-flex align-items-center py-4 border-bottom"><img src="${item.img}" style="width:80px;height:80px;border-radius:16px;object-fit:cover;margin-right:20px;box-shadow:0 5px 15px rgba(0,0,0,0.05);"><div class="flex-grow-1"><h6 class="fw-bold mb-1 text-dark fs-5">${item.name}</h6><div class="text-danger fw-bold fs-6">$${item.price.toFixed(2)}</div></div><button class="btn btn-light rounded-circle text-danger p-2 shadow-sm" onclick="removeFromCart(${index})"><i class="fa-solid fa-trash"></i></button></div>`; }); } subtotalEl.innerText = '$' + subtotal.toFixed(2); localStorage.setItem('kpshopCart', JSON.stringify(cart)); }
    function addToCart(id, name, price, img, btn) { const item = cart.find(i => i.id === id); if(item) { item.qty += 1; } else { cart.push({id, name, price, img, qty: 1}); } const oldHTML = btn.innerHTML; btn.style.background = '#10b981'; btn.style.borderColor = '#10b981'; btn.style.color = 'white'; btn.innerHTML = '<i class="fa-solid fa-check fs-5"></i>'; setTimeout(() => { btn.style.background = ''; btn.style.borderColor = ''; btn.style.color = ''; btn.innerHTML = oldHTML; }, 1000); updateCartUI(); }
    function removeFromCart(index) { cart.splice(index, 1); updateCartUI(); }
    document.addEventListener("DOMContentLoaded", updateCartUI);

    function switchTab(tab, element) { var hero = document.getElementById("hero-section"); var cats = document.getElementById("categories-section"); var shop = document.getElementById("shop-section"); if(hero) hero.style.display = "none"; if(cats) cats.style.display = "none"; if(shop) shop.style.display = "none"; if (tab === "home") { if(hero) hero.style.display = "block"; if(cats) cats.style.display = "block"; if(shop) shop.style.display = "block"; } else if (tab === "shop") { if(shop) shop.style.display = "block"; } else if (tab === "categories") { if(cats) cats.style.display = "block"; } if(element) { document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active')); element.classList.add('active'); } }
    document.addEventListener("DOMContentLoaded", function() { const slider = document.getElementById('priceRange'); if(slider) { slider.addEventListener('input', function() { document.getElementById('priceVal').innerText = '$' + this.value; }); document.getElementById('catAll').addEventListener('change', function() { if(this.checked) { document.querySelectorAll('.filter-checkbox').forEach(cb => { if(cb.id !== 'catAll') cb.checked = false; }); } }); document.querySelectorAll('.filter-checkbox').forEach(cb => { if(cb.id !== 'catAll') { cb.addEventListener('change', function() { if(this.checked) document.getElementById('catAll').checked = false; }); } }); } });
    function applyFilters() { let maxPrice = parseFloat(document.getElementById('priceRange').value); let selectedCats = []; document.querySelectorAll('.filter-checkbox:checked').forEach(cb => { if(cb.value !== 'all') selectedCats.push(cb.value.toLowerCase().trim()); }); let showAll = document.getElementById('catAll').checked || selectedCats.length === 0; let visibleCount = 0; document.querySelectorAll('.product-item-container').forEach(item => { let cat = (item.getAttribute('data-category') || '').toLowerCase().trim(); let price = parseFloat(item.getAttribute('data-price')) || 0; if((showAll ? true : selectedCats.includes(cat)) && price <= maxPrice) { item.style.setProperty('display', 'block', 'important'); visibleCount++; } else { item.style.setProperty('display', 'none', 'important'); } }); document.getElementById('noProducts').style.display = (visibleCount === 0) ? 'block' : 'none'; }
    function resetFilters() { document.getElementById('priceRange').value = 1000; document.getElementById('priceVal').innerText = '$1000'; document.getElementById('catAll').checked = true; document.querySelectorAll('.filter-checkbox').forEach(cb => { if(cb.id !== 'catAll') cb.checked = false; }); applyFilters(); }
    function forceFilter(categoryName) { document.getElementById('catAll').checked = false; document.querySelectorAll('.filter-checkbox').forEach(cb => { if(cb.id !== 'catAll') { cb.checked = (cb.value.toLowerCase() === categoryName.toLowerCase()); } }); applyFilters(); switchTab('shop', document.getElementById('nav-shop')); window.scrollTo(0,0); }
    function sortProducts(sortType) { const grid = document.getElementById('productGrid'); let items = Array.from(grid.querySelectorAll('.product-item-container')); if(sortType === 'low') { items.sort((a,b) => parseFloat(a.dataset.price) - parseFloat(b.dataset.price)); } else if (sortType === 'high') { items.sort((a,b) => parseFloat(b.dataset.price) - parseFloat(a.dataset.price)); } items.forEach(item => grid.insertBefore(item, document.getElementById('noProducts'))); }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if($auth_error || $auth_success): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var modalId = "<?= ($_POST['action'] ?? '') === 'join_waitlist' ? 'waitlistModal' : (($_POST['action'] ?? '') === 'signup' ? 'signupModal' : 'loginModal') ?>";
        var myModal = new bootstrap.Modal(document.getElementById(modalId));
        myModal.show();
    });
</script>
<?php endif; ?>

<?php if(!isset($_SESSION['waitlist_joined']) && !$is_logged_in): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        function triggerPopup() {
            if(!document.querySelector('.modal.show')) {
                var waitlistModal = new bootstrap.Modal(document.getElementById('waitlistModal'));
                waitlistModal.show();
            }
        }
        setTimeout(triggerPopup, 3000);
        setInterval(triggerPopup, 60000);
    });
</script>
<?php endif; ?>
</body>
</html>
