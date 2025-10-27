<?php
// admin/accounts.php - Trang quản lý tài khoản
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userRole = $_SESSION['role'] ?? 'Nhân viên';

// Chỉ cho phép Quản lý truy cập trang này
if ($userRole != 'Quản lý') {
    header("Location: ../index.php");
    exit();
}

// Hàm tạo mã tài khoản tự động
function generateMaTK($pdo) {
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(MaTK, 3) AS UNSIGNED)) as max_id FROM TAIKHOAN");
    $result = $stmt->fetch();
    $next_id = ($result['max_id'] ?? 0) + 1;
    return 'TK' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
}

// Lấy flash message (nếu có) và xóa khỏi session
$flash = $_SESSION['flash'] ?? null;
if (isset($_SESSION['flash'])) {
    unset($_SESSION['flash']);
}

// ===============================
// LẤY THÔNG TIN TÀI KHOẢN ĐỂ SỬA
// ===============================
$editAccount = null;
if (isset($_GET['edit'])) {
    $maTK = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM TAIKHOAN WHERE MaTK = ?");
    $stmt->execute([$maTK]);
    $editAccount = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================
// XỬ LÝ THÊM / SỬA / XÓA
// ============================
if ($_POST['action'] ?? '') {
    $action = $_POST['action'];
    try {
        if ($action == 'add' || $action == 'edit') {
            $tenTK = trim($_POST['TenTK'] ?? '');
            $email = trim($_POST['Email'] ?? '');
            $matKhau = trim($_POST['MatKhau'] ?? '');
            $role = trim($_POST['Role'] ?? '');

            // Kiểm tra các trường bắt buộc
            if (empty($tenTK) || empty($email) || empty($role)) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Vui lòng điền đầy đủ tất cả các trường bắt buộc!'];
                header("Location: accounts.php");
                exit();
            }

            // Kiểm tra định dạng email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Định dạng email không hợp lệ!'];
                header("Location: accounts.php");
                exit();
            }

            if ($action == 'add') {
                // Kiểm tra mật khẩu bắt buộc khi thêm mới
                if (empty($matKhau)) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Mật khẩu là bắt buộc khi tạo tài khoản mới!'];
                    header("Location: accounts.php");
                    exit();
                }

                // Kiểm tra email đã tồn tại
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM TAIKHOAN WHERE Email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Email đã tồn tại trong hệ thống!'];
                    header("Location: accounts.php");
                    exit();
                }

                $maTK = generateMaTK($pdo);
                $hashedPassword = password_hash($matKhau, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO TAIKHOAN (MaTK, TenTK, Email, MatKhau, Role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$maTK, $tenTK, $email, $hashedPassword, $role]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Thêm tài khoản thành công!'];
            } else {
                $maTK = $_POST['MaTK'] ?? '';
                
                // Kiểm tra email đã tồn tại (trừ tài khoản hiện tại)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM TAIKHOAN WHERE Email = ? AND MaTK != ?");
                $stmt->execute([$email, $maTK]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Email đã tồn tại trong hệ thống!'];
                    header("Location: accounts.php");
                    exit();
                }

                if (!empty($matKhau)) {
                    // Cập nhật với mật khẩu mới
                    $hashedPassword = password_hash($matKhau, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE TAIKHOAN SET TenTK=?, Email=?, MatKhau=?, Role=? WHERE MaTK=?");
                    $stmt->execute([$tenTK, $email, $hashedPassword, $role, $maTK]);
                } else {
                    // Cập nhật không thay đổi mật khẩu
                    $stmt = $pdo->prepare("UPDATE TAIKHOAN SET TenTK=?, Email=?, Role=? WHERE MaTK=?");
                    $stmt->execute([$tenTK, $email, $role, $maTK]);
                }
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Cập nhật tài khoản thành công!'];
            }
        } elseif ($action == 'delete') {
            $maTK = $_POST['MaTK'];
            
            // Không cho phép xóa tài khoản của chính mình
            if ($maTK == $_SESSION['user_id']) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Không thể xóa tài khoản của chính mình!'];
                header("Location: accounts.php");
                exit();
            }
            
            // Kiểm tra xem tài khoản có phiếu nhập/xuất không
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM PHIEUNHAP WHERE MaTK = ?");
            $stmt->execute([$maTK]);
            $hasImports = $stmt->fetchColumn() > 0;
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM PHIEUXUAT WHERE MaTK = ?");
            $stmt->execute([$maTK]);
            $hasExports = $stmt->fetchColumn() > 0;
            
            if ($hasImports || $hasExports) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Đã có phiếu nhập/xuất về tài khoản này, nên bạn không thể xóa.'];
                header("Location: accounts.php");
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM TAIKHOAN WHERE MaTK=?");
            $stmt->execute([$maTK]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Xóa tài khoản thành công!'];
        }
    } catch (Exception $e) {
        // Kiểm tra nếu là lỗi foreign key constraint
        if (strpos($e->getMessage(), 'foreign key constraint') !== false || strpos($e->getMessage(), '1451') !== false) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Không thể xóa tài khoản này vì đã có dữ liệu liên quan trong hệ thống.'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Lỗi khi xử lý: ' . $e->getMessage()];
        }
    }

    header("Location: accounts.php"); // Reload trang
    exit();
}

// ============================
// LẤY DANH SÁCH TÀI KHOẢN
// ============================
$search = $_GET['search'] ?? '';
$where = '';
$searchMessage = '';

if ($search) {
    $where = "WHERE TenTK LIKE '%$search%' OR MaTK LIKE '%$search%' OR Email LIKE '%$search%'";
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM TAIKHOAN $where");
    $totalResults = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    if ($totalResults == 0) {
        $searchMessage = "Không tìm thấy tài khoản nào với từ khóa: '$search'";
    } else {
        $searchMessage = "Tìm thấy $totalResults tài khoản với từ khóa: '$search'";
    }
}

$stmt = $pdo->query("SELECT * FROM TAIKHOAN $where ORDER BY MaTK");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <?php if ($userRole == 'Quản lý'): ?>
                <li><a href="#" class="active">Quản Lý Tài Khoản</a></li>
            <?php endif; ?>
            <li><a href="stores.php">Quản Lý Cửa Hàng</a></li>
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
        <h1 style="text-align: center; margin-bottom: 20px; color: #d4af37;">Quản Lý Tài Khoản</h1>

        <!-- Thanh tìm kiếm -->
        <form method="GET" class="search-form" style="display: inline;">
            <input type="text" class="search-box" placeholder="Tìm kiếm..." name="search" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-search">Tìm</button>
        </form>
        <button class="btn btn-add" onclick="openModal('addModal')">Thêm Tài Khoản</button>

        <!-- Thông báo kết quả tìm kiếm -->
        <?php if ($searchMessage): ?>
            <div style="margin: 15px 0; padding: 12px; background: <?php echo strpos($searchMessage, 'Không tìm thấy') !== false ? '#ffebee' : '#e8f5e8'; ?>; 
                        border: 1px solid <?php echo strpos($searchMessage, 'Không tìm thấy') !== false ? '#f44336' : '#4caf50'; ?>; 
                        border-radius: 8px; color: <?php echo strpos($searchMessage, 'Không tìm thấy') !== false ? '#c62828' : '#2e7d32'; ?>;">
                <?php echo htmlspecialchars($searchMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Flash message sau khi thêm/sửa/xóa -->
        <?php if ($flash): ?>
            <div id="flashMessage" style="margin: 15px 0; padding: 12px; background: <?php echo ($flash['type'] ?? '') === 'error' ? '#ffebee' : '#e8f5e8'; ?>; 
                        border: 1px solid <?php echo ($flash['type'] ?? '') === 'error' ? '#f44336' : '#4caf50'; ?>; 
                        border-radius: 8px; color: <?php echo ($flash['type'] ?? '') === 'error' ? '#c62828' : '#2e7d32'; ?>; transition: opacity 0.4s ease;">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Hiển thị thông báo khi không có tài khoản -->
        <?php if (empty($accounts) && !$search): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <p style="font-size: 18px; margin-bottom: 10px;">Chưa có tài khoản nào trong hệ thống</p>
                <button class="btn btn-add" onclick="openModal('addModal')">Thêm Tài Khoản Đầu Tiên</button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <!-- Bảng danh sách tài khoản -->
                <table>
                    <thead>
                        <tr>
                            <th>Mã TK</th>
                            <th>Tên Tài Khoản</th>
                            <th>Email</th>
                            <th>Vai Trò</th>
                            <th class="actions-column">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($account['MaTK']); ?></td>
                            <td><?php echo htmlspecialchars($account['TenTK']); ?></td>
                            <td><?php echo htmlspecialchars($account['Email']); ?></td>
                            <td><?php echo htmlspecialchars($account['Role']); ?></td>
                            <td class="actions-column">
                                <button class="btn btn-edit" onclick="editAccount('<?php echo $account['MaTK']; ?>')">Sửa</button>
                                <?php if ($account['MaTK'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-delete" onclick="deleteAccount('<?php echo $account['MaTK']; ?>')">Xóa</button>
                                <?php else: ?>
                                    <button class="btn btn-delete" disabled title="Không thể xóa tài khoản của chính mình">Xóa</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

    <!-- Modal Thêm -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2>Thêm Tài Khoản</h2>
            <form method="POST" onsubmit="return validateAccountForm()">
                <input type="hidden" name="action" value="add">
                
                <label>Mã Tài Khoản:</label>
                <?php 
                $nextMaTK = generateMaTK($pdo);
                ?>
                <input type="text" value="<?php echo $nextMaTK; ?>" disabled 
                       style="background-color: #f0f0f0;">
                
                <label>Tên Tài Khoản: <span style="color: red;">*</span></label>
                <input type="text" name="TenTK" placeholder="Tên Tài Khoản" required>
                
                <label>Email: <span style="color: red;">*</span></label>
                <input type="email" name="Email" placeholder="Email" required>
                
                <label>Mật Khẩu: <span style="color: red;">*</span></label>
                <input type="password" name="MatKhau" placeholder="Mật Khẩu" required>
                
                <label>Vai Trò: <span style="color: red;">*</span></label>
                <select name="Role" required>
                    <option value="">Chọn Vai Trò</option>
                    <option value="Quản lý">Quản lý</option>
                    <option value="Nhân viên">Nhân viên</option>
                </select>
                
                <button type="submit" class="btn btn-add">Lưu</button>
            </form>
        </div>
    </div>

    <!-- Modal Sửa -->
    <?php if ($editAccount): ?>
    <div id="editModal" class="modal" style="display:block;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Sửa Tài Khoản</h2>
            <form method="POST" onsubmit="return validateAccountForm()">
                <input type="hidden" name="action" value="edit">
                
                <label>Mã Tài Khoản:</label>
                <input type="text" name="MaTK" value="<?php echo htmlspecialchars($editAccount['MaTK']); ?>" readonly 
                       style="background-color: #f0f0f0;">
                
                <label>Tên Tài Khoản: <span style="color: red;">*</span></label>
                <input type="text" name="TenTK" value="<?php echo htmlspecialchars($editAccount['TenTK']); ?>" required>
                
                <label>Email: <span style="color: red;">*</span></label>
                <input type="email" name="Email" value="<?php echo htmlspecialchars($editAccount['Email']); ?>" required>
                
                <label>Mật Khẩu: <small>(Để trống nếu không muốn thay đổi)</small></label>
                <input type="password" name="MatKhau" placeholder="Mật Khẩu Mới">
                
                <label>Vai Trò: <span style="color: red;">*</span></label>
                <select name="Role" required>
                    <option value="">Chọn Vai Trò</option>
                    <option value="Quản lý" <?php echo $editAccount['Role'] == 'Quản lý' ? 'selected' : ''; ?>>Quản lý</option>
                    <option value="Nhân viên" <?php echo $editAccount['Role'] == 'Nhân viên' ? 'selected' : ''; ?>>Nhân viên</option>
                </select>
                
                <button type="submit" class="btn btn-edit">Cập nhật</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal xác nhận xóa -->
    <div id="confirmDeleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <h2>Xác Nhận Xóa</h2>
            <p id="confirmDeleteMessage" style="margin: 20px 0; font-size: 16px;">Bạn có chắc chắn muốn xóa tài khoản này?</p>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn btn-cancel" onclick="closeModal('confirmDeleteModal')" style="background-color: #999;">Hủy</button>
                <button class="btn btn-delete" id="confirmDeleteBtn" onclick="confirmDelete()" style="background-color: #d32f2f;">Xóa</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        function editAccount(maTK) {
            location.href = `accounts.php?edit=${maTK}`;
        }

        let deleteConfirmMaTK = null;

        function deleteAccount(maTK) {
            deleteConfirmMaTK = maTK;
            document.getElementById('confirmDeleteMessage').innerText = `Bạn có chắc chắn muốn xóa tài khoản "${maTK}"?`;
            openModal('confirmDeleteModal');
        }

        function confirmDelete() {
            if (deleteConfirmMaTK) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="MaTK" value="${deleteConfirmMaTK}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function validateAccountForm() {
            const tenTK = document.querySelector('input[name="TenTK"]').value.trim();
            const email = document.querySelector('input[name="Email"]').value.trim();
            const role = document.querySelector('select[name="Role"]').value;
            
            if (!tenTK || !email || !role) {
                alert('Vui lòng điền đầy đủ tất cả các trường bắt buộc!');
                return false;
            }
            
            // Kiểm tra định dạng email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Định dạng email không hợp lệ!');
                return false;
            }
            
            return true;
        }

        // Tự ẩn flash message sau 4s (nếu có)
        document.addEventListener('DOMContentLoaded', function() {
            const flash = document.getElementById('flashMessage');
            if (!flash) return;
            setTimeout(function() {
                flash.style.opacity = '0';
                setTimeout(function() {
                    if (flash.parentNode) flash.parentNode.removeChild(flash);
                }, 500);
            }, 4000);
        });
    </script>
</body>
</html>