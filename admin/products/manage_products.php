<?php
session_start();

// Cek apakah user sudah login DAN role-nya adalah 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.html?error=Akses tidak diizinkan untuk role ini.");
    exit();
}

// Path yg menghubungkan ke database
require_once '../../config/database.php'; 

$conn = get_db_connection();

// Inisialisasi pesan
$success_message = "";
$error_message = "";

// Ambil semua produk
$products_sql = "SELECT p.id, p.name AS product_name, p.description, p.price, p.stock, p.image_url, c.name AS category_name 
                 FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 ORDER BY p.id DESC"; // Mengambil produk terbaru duluan
$products_result = $conn->query($products_sql);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Manajemen Produk SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>SkinGlow! - Produk</title>

    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../css/index_admin.css" rel="stylesheet"> 
    <link rel="stylesheet" href="../../css/custom_admin.css">
    
    <link href="../../vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">

    <style>
        .product-thumbnail-in-name {
            width: 40px; 
            height: 40px;
            object-fit: cover;
            border-radius: 3px;
            margin-right: 8px; 
            vertical-align: middle; 
        }
        .product-name-cell {
            display: flex; 
            align-items: center; 
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 350px; 
        }
        
        .description-cell {
            max-width: 500px; 
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
      
        #dataTable th:nth-child(4), 
        #dataTable td:nth-child(4) {
            min-width: 120px; 
        }
      
        #dataTable th:last-child, 
        #dataTable td:last-child {
            width: 120px; 
            text-align: center;
        }
    
        .action-buttons .btn {
            padding: .25rem .5rem; 
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
                <a class="nav-link" href="../sales_report.php">
                    <i class="fas fa-fw fa-chart-line"></i>
                    <span>Laporan Penjualan</span>
                </a>
            </li>
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
                    <h1 class="h3 mb-2 text-gray-800">Produk</h1>
                    <p class="mb-4">Tabel ini menampilkan daftar semua produk yang tersedia di toko online SkinGlow!.</p>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">List Products</h6>
                            <a href="add_product.php" class="btn btn-success btn-sm float-right">
                                <i class="fas fa-plus"></i> Tambah Produk Baru
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama Produk</th>
                                            <th>Deskripsi</th>
                                            <th>Harga</th>
                                            <th>Stok</th>
                                            <th>Kategori</th>
                                            <th>Aksi</th> </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($products_result && $products_result->num_rows > 0) {
                                            while ($row = $products_result->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                              
                                                echo "<td class='product-name-cell'>";
                                                if (!empty($row['image_url'])) {
                                                    echo "<img src='../../" . htmlspecialchars($row['image_url']) . "'alt='Produk' class='product-thumbnail-in-name'>";
                                                }
                                                echo htmlspecialchars($row['product_name']);
                                                echo "<td class='description-cell'>" . stripslashes($row['description']) . "</td>"; 
                                                echo "<td>Rp " . number_format($row['price'], 0, ',', '.') . "</td>"; 
                                                echo "<td>" . htmlspecialchars($row['stock']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['category_name']) . "</td>";
                                                echo "<td class='action-buttons'>";
                                                echo "<a href='view_product.php?id=" . $row['id'] . "'class='btn btn-info btn-sm mr-1'><i class='fas fa-eye'></i></a>"; // View
                                                echo "<a href='delete_product.php?id=" . $row['id'] . "' class='btn btn-danger btn-sm' onclick=\"return confirm('Anda yakin ingin menghapus produk ini?');\"><i class='fas fa-trash'></i></a>"; // Delete
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='7'>Tidak ada produk yang ditemukan.</td></tr>"; 
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
                    <a class="btn btn-primary" href="../../api/logout.php">Logout</a>
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
            // Update admin name in topbar (jika user_name ada di session)
            <?php if (isset($_SESSION['user_name'])): ?>
                $('.navbar-nav .text-gray-600.small').text('<?php echo htmlspecialchars($_SESSION['user_name']); ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>