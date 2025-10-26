<?php
// admin/stores.php - Trang quản lý cửa hàng
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userRole = $_SESSION['role'] ?? 'Nhân viên';

// Hàm tạo mã cửa hàng tự động
function generateMaCH($pdo) {
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(MaCH, 3) AS UNSIGNED)) as max_id FROM CUAHANG");
    $result = $stmt->fetch();
    $next_id = ($result['max_id'] ?? 0) + 1;
    return 'CH' . str_pad($next_id, 5, '0', STR_PAD_LEFT);
}

// ============================
//  XỬ LÝ THÊM / SỬA / XÓA
// ============================
$message = '';
$messageType = '';

if ($_POST['action'] ?? '') {
    $action = $_POST['action'];
    
    try {
        if ($action == 'add' || $action == 'edit') {
            $maCH = $_POST['MaCH'] ?? '';
            $tenCH = trim($_POST['TenCH'] ?? '');
            $diaChi = trim($_POST['DiaChi'] ?? '');
            $soDienThoai = trim($_POST['SoDienThoai'] ?? '');
            
            // Validate
            if (empty($tenCH) || empty($diaChi) || empty($soDienThoai)) {
                throw new Exception('Vui lòng điền đầy đủ tất cả các trường!');
            }
            
            // Validate số điện thoại (10-11 số)
            if (!preg_match('/^0[0-9]{9,10}$/', $soDienThoai)) {
                throw new Exception('Số điện thoại không hợp lệ! (Phải bắt đầu bằng 0 và có 10-11 số)');
            }
            
            if ($action == 'add') {
                $maCH = generateMaCH($pdo);
                $stmt = $pdo->prepare("INSERT INTO CUAHANG (MaCH, TenCH, DiaChi, SoDienThoai) VALUES (?, ?, ?, ?)");
                $stmt->execute([$maCH, $tenCH, $diaChi, $soDienThoai]);
                $message = 'Thêm cửa hàng thành công!';
                $messageType = 'success';
            } else {
                $stmt = $pdo->prepare("UPDATE CUAHANG SET TenCH=?, DiaChi=?, SoDienThoai=? WHERE MaCH=?");
                $stmt->execute([$tenCH, $diaChi, $soDienThoai, $maCH]);
                $message = 'Cập nhật cửa hàng thành công!';
                $messageType = 'success';
            }
        } elseif ($action == 'delete') {
            $maCH = $_POST['MaCH'];
            $stmt = $pdo->prepare("DELETE FROM CUAHANG WHERE MaCH=?");
            $stmt->execute([$maCH]);
            $message = 'Xóa cửa hàng thành công!';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = 'Lỗi: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ============================
//  LẤY DANH SÁCH CỬA HÀNG
// ============================
$search = $_GET['search'] ?? '';
$where = $search ? "WHERE TenCH LIKE '%$search%' OR MaCH LIKE '%$search%' OR DiaChi LIKE '%$search%'" : '';
$stmt = $pdo->query("SELECT * FROM CUAHANG $where ORDER BY MaCH DESC");
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Cửa Hàng - Hệ Thống Quản Lý Kho Tink</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="logo">Tink Jewelry</div>
        <ul class="nav-menu">
            <li><a href="../index.php">Trang chủ</a></li>
            <?php if ($userRole == 'Quản lý'): ?>
                <li><a href="accounts.php">Quản Lý Tài Khoản</a></li>
            <?php endif; ?>
            <li><a href="stores.php" class="active">Quản Lý Cửa Hàng</a></li>
            <li><a href="products.php">Quản Lý Sản Phẩm</a></li>
            <?php if ($userRole == 'Quản lý'): ?>
                <li><a href="imports.php">Quản Lý Nhập Kho</a></li>
                <li><a href="exports.php">Quản Lý Xuất Kho</a></li>
                <li><a href="reports.php">Quản Lý Báo Cáo</a></li>
            <?php else: ?>
                <li><a href="imports.php">Quản Lý Nhập Kho</a></li>
                <li><a href="exports.php">Quản Lý Xuất Kho</a></li>
            <?php endif; ?>
        </ul>
        <button class="logout-btn" onclick="location.href='../logout.php'">Đăng Xuất</button>
    </header>

    <div class="container">
        <h1 style="text-align: center; margin-bottom: 20px; color: #d4af37;">Quản Lý Cửa Hàng</h1>
        
        <!-- Thông báo -->
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>" style="margin-bottom: 20px; padding: 12px 20px; border-radius: 5px; text-align: center; font-weight: 500; <?php echo $messageType == 'success' ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Thanh tìm kiếm và nút thêm -->
        <div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: center;">
            <form method="GET" style="display: flex; gap: 10px; flex: 1;">
                <input type="text" class="search-box" placeholder="Tìm kiếm cửa hàng..." name="search" value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
                <button type="submit" class="btn btn-search">Tìm</button>
            </form>
            <button class="btn btn-add" onclick="openModal('addModal')">Thêm Cửa Hàng</button>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 100px">Mã CH</th>
                        <th>Tên Cửa Hàng</th>
                        <th>Địa Chỉ</th>
                        <th style="width: 150px">Số Điện Thoại</th>
                        <th class="actions-column">Hành Động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stores as $store): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($store['MaCH']); ?></td>
                            <td><?php echo htmlspecialchars($store['TenCH']); ?></td>
                            <td><?php echo htmlspecialchars($store['DiaChi']); ?></td>
                            <td><?php echo htmlspecialchars($store['SoDienThoai']); ?></td>
                            <td class="actions-column">
                                <button class="btn btn-edit" onclick='editStore(<?php echo json_encode($store); ?>)'>Sửa</button>
                                <button class="btn btn-delete" onclick="deleteStore('<?php echo $store['MaCH']; ?>', '<?php echo htmlspecialchars($store['TenCH']); ?>')">Xóa</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Thêm/Sửa -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2 id="modalTitle">Thêm Cửa Hàng</h2>
            <form method="POST">
                <input type="hidden" name="action" id="modalAction" value="add">
                <input type="hidden" name="MaCH" id="MaCH">
                
                <label>Mã Cửa Hàng:</label>
                <input type="text" id="displayMaCH" value="<?php echo generateMaCH($pdo); ?>" disabled style="background-color: #f0f0f0;">
                
                <label>Tên Cửa Hàng: <span style="color: red;">*</span></label>
                <input type="text" name="TenCH" id="TenCH" placeholder="Nhập tên cửa hàng" required>
                
                <label>Địa Chỉ: <span style="color: red;">*</span></label>
                <input type="text" name="DiaChi" id="DiaChi" placeholder="Nhập địa chỉ" required>
                
                <label>Số Điện Thoại: <span style="color: red;">*</span></label>
                <input type="text" name="SoDienThoai" id="SoDienThoai" placeholder="Nhập số điện thoại (VD: 0987654321)" required pattern="0[0-9]{9,10}" title="Số điện thoại phải bắt đầu bằng 0 và có 10-11 số">
                
                <button type="submit" class="btn btn-add">Lưu</button>
            </form>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        function editStore(store) {
            document.getElementById('modalTitle').innerText = 'Sửa Cửa Hàng';
            document.getElementById('modalAction').value = 'edit';
            document.getElementById('MaCH').value = store.MaCH;
            document.getElementById('displayMaCH').value = store.MaCH;
            document.getElementById('TenCH').value = store.TenCH;
            document.getElementById('DiaChi').value = store.DiaChi;
            document.getElementById('SoDienThoai').value = store.SoDienThoai;
            openModal('addModal');
        }

        function deleteStore(maCH, tenCH) {
            if (confirm('Bạn có chắc chắn muốn xóa cửa hàng "' + tenCH + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="MaCH" value="${maCH}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function openModal(modalId) {
            if (modalId === 'addModal' && document.getElementById('modalAction').value === 'add') {
                document.getElementById('modalTitle').innerText = 'Thêm Cửa Hàng';
                document.getElementById('modalAction').value = 'add';
                document.getElementById('MaCH').value = '';
                document.getElementById('displayMaCH').value = '<?php echo generateMaCH($pdo); ?>';
                document.getElementById('TenCH').value = '';
                document.getElementById('DiaChi').value = '';
                document.getElementById('SoDienThoai').value = '';
            }
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>
</body>
</html>
