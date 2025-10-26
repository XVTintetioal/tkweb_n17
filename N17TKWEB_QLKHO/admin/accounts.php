<?php
// admin/accounts.php - Trang quản lý tài khoản
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userRole = $_SESSION['role'] ?? 'Nhân viên';

// Chỉ Quản lý mới được truy cập
if ($userRole != 'Quản lý') {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Tài Khoản - Hệ Thống Quản Lý Kho Tink</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="logo">Tink Jewelry</div>
        <ul class="nav-menu">
            <li><a href="../index.php">Trang chủ</a></li>
            <li><a href="accounts.php" class="active">Quản Lý Tài Khoản</a></li>
            <li><a href="stores.php">Quản Lý Cửa Hàng</a></li>
            <li><a href="products.php">Quản Lý Sản Phẩm</a></li>
            <li><a href="imports.php">Quản Lý Nhập Kho</a></li>
            <li><a href="exports.php">Quản Lý Xuất Kho</a></li>
            <li><a href="reports.php">Quản Lý Báo Cáo</a></li>
        </ul>
        <button class="logout-btn" onclick="location.href='../logout.php'">Đăng Xuất</button>
    </header>

    <div class="container">
        <h1 style="text-align: center; margin-bottom: 20px; color: #d4af37;">Quản Lý Tài Khoản</h1>
        <p style="text-align: center; color: #666;">Chức năng đang được phát triển...</p>
    </div>
</body>
</html>
