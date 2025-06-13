<?php
session_start();

// Cek apakah user sudah login DAN role-nya adalah 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.html?error=Akses tidak diizinkan untuk role ini.");
    exit();
}

require_once '../../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$category_id = $_GET['id'] ?? null;

if (!$category_id) {
    // Redirect atau tampilkan error jika ID tidak ada
    header("Location: manage_categories.php");
    exit();
}

// Ambil detail kategori
$stmt_category = $conn->prepare("SELECT id, name FROM categories WHERE id = ?");
$stmt_category->bind_param("i", $category_id);
$stmt_category->execute();
$result_category = $stmt_category->get_result();
$category = $result_category->fetch_assoc();

if (!$category) {
    echo "Kategori tidak ditemukan.";
    exit();
}
$stmt_category->close();

// Ambil produk-produk yang termasuk dalam kategori ini
$stmt_products = $conn->prepare("SELECT id, name, image_url FROM products WHERE category_id = ? ORDER BY name ASC");
$stmt_products->bind_param("i", $category_id);
$stmt_products->execute();
$products_result = $stmt_products->get_result();
$stmt_products->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Detail Kategori Produk SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>SkinGlow! - Detail Kategori: <?php echo htmlspecialchars($category['name']); ?></title>

    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../css/index_admin.css" rel="stylesheet"> 
    <link href="../../vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/custom_admin.css">
    <style>
        .product-list-img {
            max-width: 50px; /* Ukuran gambar produk di tabel */
            height: auto;
            border-radius: 4px;
        }
        .product-image-thumbnail {
            width: 70px; /* Lebar gambar thumbnail */
            height: 70px; /* Tinggi gambar thumbnail */
            object-fit: cover; /* Memastikan gambar mengisi area tanpa terdistorsi */
            border-radius: 4px;
            display: block; /* Agar margin auto bisa bekerja */
            margin: 0 auto; /* Pusatkan gambar */
        }
        .product-name-column {
            white-space: normal; /* Izinkan teks melipat */
            word-wrap: break-word; /* Pastikan kata panjang dipotong */
            max-width: 250px; /* Batasi lebar kolom agar nama produk tidak terlalu panjang */
            text-align: left; /* Teks nama produk tetap rata kiri */
        }
        .table-bordered th, .table-bordered td {
            vertical-align: middle; /* Pusatkan konten sel secara vertikal */
        }
        #dataTable th:first-child, /* No. */
        #dataTable td:first-child {
            width: 50px;
            text-align: center;
        }
        #dataTable th:nth-child(2), /* Gambar */
        #dataTable td:nth-child(2) {
            width: 100px; /* Beri ruang yang cukup untuk gambar */
            text-align: center;
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
            <li class="nav-item">
                <a class="nav-link" href="../products/manage_products.php">
                    <i class="fas fa-fw fa-box"></i>
                    <span>Produk</span>
                </a>
            </li>
            <li class="nav-item active"> <a class="nav-link" href="../categories/manage_categories.php">
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
            <li class="nav-item">
                <a class="nav-link" href="../users/manage_users.php">
                    <i class="fas fa-fw fa-users"></i>
                    <span>Pelanggan</span>
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
                                <img class="img-profile rounded-circle" src="../img/undraw_profile.svg">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i> Profil
                                </a>
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i> Pengaturan
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <div class="container-fluid">

                    <h1 class="h3 mb-4 text-gray-800">Detail Kategori: <?php echo htmlspecialchars($category['name']); ?></h1>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Informasi Kategori</h6>
                        </div>
                        <div class="card-body">
                            <a href="manage_categories.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali ke Kategori</a>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Produk dalam Kategori ini</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Gambar</th>
                                            <th>Nama Produk</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        if ($products_result && $products_result->num_rows > 0) {
                                            while ($product_row = $products_result->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . $no++ . "</td>";
                                                echo "<td>";
                                                if (!empty($product_row['image_url'])) {
                                                    echo "<img src='../../" . htmlspecialchars($product_row['image_url']) . "' alt='Produk' class='product-list-img'>";
                                                } else {
                                                    echo "Tidak ada gambar";
                                                }
                                                echo "</td>";
                                                echo "<td>" . htmlspecialchars($product_row['name']) . "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='3'>Tidak ada produk dalam kategori ini.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

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
    <script src="../../vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="../../vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="../../js/demo/datatables-demo.js"></script>
    <script>
        $(document).ready(function() {
            <?php if (isset($_SESSION['user_name'])): ?>
                $('.navbar-nav .text-gray-600.small').text('<?php echo htmlspecialchars($_SESSION['user_name']); ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>