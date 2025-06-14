<?php
session_start();


require_once '../../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$product = null;
$success_message = "";
$error_message = "";

// Ambil semua kategori untuk dropdown
$categories_sql = "SELECT id, name FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_sql);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];

    $sql = "SELECT id, name, description, price, stock, category_id, image_url, is_active FROM products WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        $error_message = "Produk tidak ditemukan.";
    }
    $stmt->close();

} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $product_name = $conn->real_escape_string($_POST['product_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $category_id = (int)$_POST['category_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $current_image_url = $conn->real_escape_string($_POST['current_image_url'] ?? ''); // Ambil URL gambar saat ini

    $new_image_url = $current_image_url; // Default menggunakan gambar yang sudah ada

    $target_dir = "../img/product_images/"; // Relative path dari admin/ ke folder gambar

    // Proses upload gambar baru jika ada
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $image_name = basename($_FILES["product_image"]["name"]);
        $target_file = $target_dir . $image_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["product_image"]["tmp_name"]);
        if($check !== false) {
            if ($_FILES["product_image"]["size"] > 5000000) {
                $error_message = "Maaf, ukuran gambar terlalu besar. Maksimal 5MB.";
            } else if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
                $error_message = "Maaf, hanya file JPG, JPEG, PNG & GIF yang diizinkan.";
            } else {
                if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                    // Hapus gambar lama jika ada dan berbeda dengan yang baru
                    if (!empty($current_image_url) && $current_image_url !== ("img/product_images/" . $image_name)) {
                        $old_image_path = "../" . $current_image_url; // Path lengkap ke gambar lama
                        if (file_exists($old_image_path) && is_file($old_image_path)) {
                            unlink($old_image_path); // Hapus file lama
                        }
                    }
                    $new_image_url = "img/product_images/" . $image_name;
                } else {
                    $error_message = "Maaf, terjadi kesalahan saat mengupload gambar Anda.";
                }
            }
        } else {
            $error_message = "File bukan gambar.";
        }
    } else if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] != 4) {
        $error_message = "Terjadi kesalahan upload: " . $_FILES['product_image']['error'];
    }

    // Jika tidak ada error upload gambar, lanjutkan update data
    if (empty($error_message)) {
        $update_sql = "UPDATE products SET name=?, description=?, price=?, stock=?, category_id=?, image_url=?, is_active=?, updated_at=NOW() WHERE id=?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssdiisii", $product_name, $description, $price, $stock, $category_id, $new_image_url, $is_active, $product_id);

        if ($stmt->execute()) {
            $success_message = "Produk '" . htmlspecialchars($product_name) . "' berhasil diperbarui!";
            // Ambil ulang data produk setelah update agar form menampilkan data terbaru
            $sql_reget = "SELECT id, name, description, price, stock, category_id, image_url, is_active FROM products WHERE id = ?";
            $stmt_reget = $conn->prepare($sql_reget);
            $stmt_reget->bind_param("i", $product_id);
            $stmt_reget->execute();
            $result_reget = $stmt_reget->get_result();
            if ($result_reget->num_rows > 0) {
                $product = $result_reget->fetch_assoc();
            }
            $stmt_reget->close();
            // Redirect setelah sukses
            header("refresh:2;url=manage_products.php");
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }

} else {
    $error_message = "Akses tidak valid.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Edit Produk SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>SkinGlow! - Edit Produk</title>

    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../css/index_admin.css" rel="stylesheet"> 
    <link rel="stylesheet" href="../../css/custom_admin.css">

    <style>
        .current-product-image {
            max-width: 150px;
            height: auto;
            display: block;
            margin-top: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="sidebar-brand-text mx-3">SkinGlow! Admin</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Manajemen E-commerce</div>
            <li class="nav-item active">
                <a class="nav-link" href="../products/manage_products.php">
                    <i class="fas fa-fw fa-box"></i>
                    <span>Produk</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../categories/manage_categories.php">
                    <i class="fas fa-fw fa-tags"></i>
                    <span>Kategori Produk</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../orders/manage_orders.php">
                    <i class="fas fa-fw fa-shopping-cart"></i>
                    <span>Pesanan</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Manajemen Keuangan</div>
            <li class="nav-item">
                <a class="nav-link" href="../transactions/manage_transactions.php">
                    <i class="fas fa-fw fa-money-bill-alt"></i>
                    <span>Transaksi</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Laporan & Statistik</div>
            <li class="nav-item">
                <a class="nav-link" href="sales_report.php">
                    <i class="fas fa-fw fa-chart-line"></i>
                    <span>Laporan Penjualan</span>
                </a>
            </li>
            <hr class="sidebar-divider d-none d-md-block">
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin Default'); ?>
                                </span>
                                <img class="img-profile rounded-circle" src="../../img/undraw_profile.svg">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Edit Produk</h1>

                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success_message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($product): ?>
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Form Edit Produk: <?php echo htmlspecialchars($product['name']); ?></h6>
                            </div>
                            <div class="card-body">
                                <form action="edit_product.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                    <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($product['image_url']); ?>">

                                    <div class="form-group">
                                        <label for="product_name">Nama Produk:</label>
                                        <input type="text" class="form-control" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="description">Deskripsi Produk:</label>
                                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="price">Harga:</label>
                                        <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required min="0">
                                    </div>
                                    <div class="form-group">
                                        <label for="stock">Stok:</label>
                                        <input type="number" class="form-control" id="stock" name="stock" value="<?php echo htmlspecialchars($product['stock']); ?>" required min="0">
                                    </div>
                                    <div class="form-group">
                                        <label for="category_id">Kategori:</label>
                                        <select class="form-control" id="category_id" name="category_id" required>
                                            <option value="">Pilih Kategori</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo htmlspecialchars($category['id']); ?>" 
                                                    <?php echo ($category['id'] == $product['category_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="product_image">Gambar Produk Saat Ini:</label><br>
                                        <?php if (!empty($product['image_url'])): ?>
                                            <img src="../../<?php echo htmlspecialchars($product['image_url']); ?>" alt="Gambar Produk" class="current-product-image">
                                        <?php else: ?>
                                            <p>Tidak ada gambar saat ini.</p>
                                        <?php endif; ?>
                                        <input type="file" class="form-control-file" id="product_image" name="product_image" accept="image/*">
                                        <small class="form-text text-muted">Biarkan kosong jika tidak ingin mengubah gambar. Maksimal 5MB (JPG, JPEG, PNG, GIF).</small>
                                    </div>
                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox small">
                                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" <?php echo $product['is_active'] ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="is_active">Produk Aktif</label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                    <a href="manage_products.php" class="btn btn-secondary">Batal</a>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            Produk tidak ditemukan atau ID tidak valid.
                        </div>
                        <a href="manage_products.php" class="btn btn-primary">Kembali ke Daftar Produk</a>
                    <?php endif; ?>

                </div>
                </div>
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; SkinGlow! 2025</span>
                    </div>
                </div>
            </footer>
            </div>
        </div>
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Siap untuk Keluar?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Pilih "Logout" di bawah jika Anda siap untuk mengakhiri sesi Anda saat ini.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
                    <a class="btn btn-primary" href="../logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../js/sb-admin-2.min.js"></script>
    <script>
        $(document).ready(function() {
            <?php if (isset($_SESSION['user_name'])): ?>
                $('.navbar-nav .text-gray-600.small').text('<?php echo htmlspecialchars($_SESSION['user_name']); ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>