<?php
session_start();

// Cek apakah user sudah login DAN role-nya adalah 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.html?error=Akses tidak diizinkan untuk role ini.");
    exit();
}

require_once '../../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$message = '';
$error = '';

// Ambil pesan dari URL jika ada (setelah redirect dari operasi lain)
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// --- Logika untuk Update Status Transaksi (Opsional, jika admin bisa mengubah status) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $transaction_id = (int)$_POST['transaction_id'];
    $new_status = trim($_POST['new_status']);

    // Validasi status yang diizinkan (sesuai ENUM di tabel)
    $allowed_statuses = ['pending', 'processed', 'completed', 'cancelled', 'refunded'];
    if (!in_array($new_status, $allowed_statuses)) {
        $error = "Status tidak valid.";
    } else {
        $stmt_update = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("si", $new_status, $transaction_id);
            if ($stmt_update->execute()) {
                $message = "Status transaksi #" . $transaction_id . " berhasil diperbarui menjadi '" . htmlspecialchars($new_status) . "'.";
            } else {
                $error = "Gagal memperbarui status transaksi: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $error = "Gagal menyiapkan statement update: " . $conn->error;
        }
    }
}


// --- Ambil semua data transaksi dari tabel 'orders' ---
// Kita akan melakukan JOIN dengan tabel 'users' untuk mendapatkan nama user
$transactions_query = "
    SELECT 
        o.id, 
        o.user_id, 
        u.name AS user_name, 
        o.order_date,  
        o.status,
        o.total_amount
    FROM 
        orders AS o
    JOIN 
        users AS u ON o.user_id = u.id
    ORDER BY 
        o.order_date DESC
";
$transactions_result = $conn->query($transactions_query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Manajemen Transaksi SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>SkinGlow! - Manajemen Transaksi</title>

    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../css/index_admin.css" rel="stylesheet">
    <link href="../../vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/custom_admin.css">

    <style>
        /* Gaya untuk status */
        .badge-status-pending { background-color: #ffc107; color: #343a40; } /* Yellow */
        .badge-status-processed { background-color: #17a2b8; color: #fff; } /* Info Blue */
        .badge-status-completed { background-color: #28a745; color: #fff; } /* Green */
        .badge-status-cancelled { background-color: #dc3545; color: #fff; } /* Red */
        .badge-status-refunded { background-color: #6c757d; color: #fff; } /* Gray */
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
                <a class="nav-link" href="index.php">
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
            <li class="nav-item">
                <a class="nav-link" href="../categories/manage_categories.php">
                    <i class="fas fa-fw fa-tags"></i>
                    <span>Kategori Produk</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../orders/manage_orders.php">
                    <i class="fas fa-fw fa-shopping-cart"></i>
                    <span>Pelanggan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../users/manage_users.php">
                    <i class="fas fa-fw fa-users"></i>
                    <span>User</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Manajemen Keuangan</div>
            <li class="nav-item active">
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
                    <h1 class="h3 mb-4 text-gray-800">Manajemen Transaksi</h1>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Daftar Transaksi Pelanggan</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID Transaksi</th>
                                            <th>ID Pelanggan</th>
                                            <th>Nama Pelanggan</th>
                                            <th>Tanggal Pesanan</th>
                                            <th>Total Harga</th>
                                            <th>Total Jumlah Dibayar</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($transactions_result && $transactions_result->num_rows > 0) {
                                            while ($row = $transactions_result->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['order_date']) . "</td>";
                                                echo "<td>Rp " . number_format($row['total_amount'], 0, ',', '.') . "</td>";
                                                echo "<td><span class='badge badge-status-" . htmlspecialchars($row['status']) . "'>" . ucfirst(htmlspecialchars($row['status'])) . "</span></td>";
                                                echo "<td class='action-buttons'>";
                                                // Tombol untuk melihat detail (opsional, jika ada halaman view_transaction_details.php)
                                                // echo "<a href='view_transaction_details.php?id=" . $row['id'] . "' class='btn btn-info btn-sm mr-1' title='Lihat Detail Transaksi'><i class='fas fa-eye'></i></a>";
                                                
                                                // Form untuk update status
                                                echo "<form action='manage_transactions.php' method='POST' style='display:inline-block;'>";
                                                echo "<input type='hidden' name='transaction_id' value='" . $row['id'] . "'>";
                                                echo "<select name='new_status' class='form-control form-control-sm d-inline w-auto mr-1'>";
                                                $statuses = ['pending', 'processed', 'completed', 'cancelled', 'refunded'];
                                                foreach ($statuses as $status_option) {
                                                    $selected = ($status_option == $row['status']) ? 'selected' : '';
                                                    echo "<option value='" . $status_option . "' " . $selected . ">" . ucfirst($status_option) . "</option>";
                                                }
                                                echo "</select>";
                                                echo "<button type='submit' name='update_status' class='btn btn-success btn-sm' title='Update Status'><i class='fas fa-sync'></i> Update</button>";
                                                echo "</form>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='8'>Tidak ada transaksi ditemukan.</td></tr>";
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
            <?php if (isset($_SESSION['user_name'])): ?>
                $('.navbar-nav .text-gray-600.small').text('<?php echo htmlspecialchars($_SESSION['user_name']); ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>