<?php
session_start();

require_once '../../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$product = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];

    $sql = "SELECT p.id, p.name AS product_name, p.description, p.price, p.stock, p.image_url, p.is_active, c.name AS category_name,
                   p.created_at, p.updated_at
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        // Produk tidak ditemukan
        $error_message = "Produk tidak ditemukan.";
    }
    $stmt->close();
} else {
    $error_message = "ID Produk tidak valid.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Detail Produk SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>SkinGlow! - Detail Produk</title>

    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../css/index_admin.css" rel="stylesheet"> 
    <link rel="stylesheet" href="../../css/custom_admin.css">
    
    <style>
        .product-detail-img {
            max-width: 300px; /* Ukuran maksimal gambar detail */
            height: auto;
            display: block;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
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
                <a class="nav-link" href="../#index.php">
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
                <a class="nav-link" href="../sales_report.php">
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
                    <h1 class="h3 mb-4 text-gray-800">Detail Produk</h1>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error_message; ?>
                        </div>
                        <a href="manage_products.php" class="btn btn-primary">Kembali ke Daftar Produk</a>
                    <?php elseif ($product): ?>
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-4 text-center">
                                        <?php if (!empty($product['image_url'])): ?>
                                            <img src="../../<?php echo htmlspecialchars($product['image_url']); ?>" alt="Gambar Produk" class="product-detail-img img-fluid">
                                        <?php else: ?>
                                            <p>Tidak ada gambar</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-lg-8">
                                        <h5 class="card-title"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                                        <p><strong>ID Produk:</strong> <?php echo htmlspecialchars($product['id']); ?></p>
                                        <p><strong>Deskripsi:</strong><br><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                                        <p><strong>Harga:</strong> Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></p>
                                        <p><strong>Stok:</strong> <?php echo htmlspecialchars($product['stock']); ?></p>
                                        <p><strong>Kategori:</strong> <?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></p>
                                        <p><strong>Status Aktif:</strong> <?php echo $product['is_active'] ? 'Aktif' : 'Tidak Aktif'; ?></p>
                                        <p><strong>Ditambahkan Pada:</strong> <?php echo htmlspecialchars($product['created_at']); ?></p>
                                        <p><strong>Terakhir Diperbarui:</strong> <?php echo htmlspecialchars($product['updated_at']); ?></p>
                                        
                                        <a href="edit_product.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="btn btn-warning mr-2"><i class="fas fa-edit"></i> Edit Produk</a>
                                        <a href="manage_products.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            Silakan pilih produk yang ingin dilihat detailnya dari daftar produk.
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
                    <a class="btn btn-primary" href="../../api/logout.php">Logout</a>
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