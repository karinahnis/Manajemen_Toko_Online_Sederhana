<?php
session_start();

// Cek apakah user sudah login DAN role-nya adalah 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.html?error=Akses tidak diizinkan untuk role ini.");
    exit();
}

require_once '../../config/database.php';

// Inisialisasi koneksi database
$conn = get_db_connection();

$message = ''; // Untuk pesan sukses
$error = '';   // Untuk pesan error

// --- Bagian ini dihapus: Logika untuk Hapus Pesanan ---
// if (isset($_GET['action']) && $_GET['action'] == 'delete') {
//     $order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

//     if ($order_id > 0) {
//         $conn->begin_transaction(); // Mulai transaksi
//         try {
//             // Hapus item pesanan terkait terlebih dahulu (karena foreign key constraint)
//             $stmt_delete_items = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
//             $stmt_delete_items->bind_param("i", $order_id);
//             $stmt_delete_items->execute();
//             $stmt_delete_items->close();

//             // Hapus pesanan itu sendiri
//             $stmt_delete_order = $conn->prepare("DELETE FROM orders WHERE id = ?");
//             $stmt_delete_order->bind_param("i", $order_id);
//             $stmt_delete_order->execute();
//             $stmt_delete_order->close();

//             $conn->commit(); // Commit transaksi jika semua berhasil
//             $_SESSION['message'] = "Pesanan #{$order_id} berhasil dihapus!";
//             header("Location: manage_orders.php"); // Redirect untuk menghilangkan parameter GET
//             exit();
//         } catch (mysqli_sql_exception $e) {
//             $conn->rollback(); // Rollback jika ada error
//             $_SESSION['error'] = "Gagal menghapus pesanan #{$order_id}: " . $e->getMessage();
//             header("Location: manage_orders.php"); // Redirect untuk menghilangkan parameter GET
//             exit();
//         }
//     } else {
//         $_SESSION['error'] = "ID Pesanan tidak valid untuk dihapus.";
//         header("Location: manage_orders.php"); // Redirect untuk menghilangkan parameter GET
//         exit();
//     }
// }

// Ambil pesan dari URL jika ada (setelah redirect dari edit_order.php atau operasi sebelumnya)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Hapus pesan setelah ditampilkan
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']); // Hapus error setelah ditampilkan
}

// --- Ambil data pesanan untuk ditampilkan ---
$orders = [];
$query_orders = "SELECT
                            o.id AS order_id,
                            u.name AS customer_name,
                            o.total_amount,
                            o.status,
                            o.order_date,
                            o.shipping_address,
                            o.payment_method
                        FROM orders o
                        JOIN users u ON o.user_id = u.id
                        ORDER BY o.order_date DESC";

$result_orders = $conn->query($query_orders);
if ($result_orders && $result_orders->num_rows > 0) {
    while ($row = $result_orders->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Tutup koneksi database
$conn->close();

// Fungsi helper untuk status badge (sama seperti di my_orders.php)
function get_status_badge($status) {
    switch (strtolower($status)) {
        case 'pending':
            return '<span class="badge badge-warning">Menunggu Pembayaran</span>';
        case 'paid':
            return '<span class="badge badge-info">Dibayar</span>';
        case 'processing':
            return '<span class="badge badge-primary">Diproses</span>';
        case 'shipped':
            return '<span class="badge badge-secondary">Dikirim</span>';
        case 'completed':
            return '<span class="badge badge-success">Selesai</span>';
        case 'cancelled':
            return '<span class="badge badge-danger">Dibatalkan</span>';
        default:
            return '<span class="badge badge-light">' . htmlspecialchars(ucfirst($status)) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Manajemen Pesanan Admin SkinGlow">
    <meta name="author" content="Tim SkinGlow">

    <title>SkinGlow! - Manajemen Pesanan Admin</title>

    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link href="../../css/index_admin.css" rel="stylesheet">
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/custom_admin.css"> 
    <link rel="stylesheet" href="../../css/orders/admin_orders.css">
    
    
</head>

<body id="page-top">

    <div id="wrapper">

        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="../index.php"> <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="sidebar-brand-text mx-3">SkinGlow! Admin</div>
            </a>

            <hr class="sidebar-divider my-0">

            <li class="nav-item"> <a class="nav-link" href="../index.php">
                        <i class="fas fa-fw fa-tachometer-alt"></i>
                        <span>Dashboard</span></a>
                </li>

            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                Manajemen E-commerce
            </div>

            <li class="nav-item">
                <a class="nav-link" href="../products/manage_products.php"> <i class="fas fa-fw fa-box"></i>
                        <span>Produk</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="../categories/manage_categories.php"> <i class="fas fa-fw fa-tags"></i>
                        <span>Kategori Produk</span></a>
            </li>

            <li class="nav-item active"> <a class="nav-link" href="manage_orders.php"> <i class="fas fa-fw fa-shopping-cart"></i>
                        <span>Pesanan</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="../users/manage_users.php"> <i class="fas fa-fw fa-users"></i>
                        <span>Pelanggan</span></a>
            </li>
            
            <hr class="sidebar-divider">
            <div class="sidebar-heading">
                Manajemen Keuangan
            </div>

            <li class="nav-item">
                <a class="nav-link" href="../transactions/manage_transactions.php"> <i class="fas fa-fw fa-money-bill-alt"></i>
                        <span>Transaksi</span></a>
            </li>
            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                Laporan & Statistik
            </div>

            <li class="nav-item">
                <a class="nav-link" href="../sales_report.php"> <i class="fas fa-fw fa-chart-line"></i>
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
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?php 
                                        echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin Default'); 
                                    ?>
                                </span>
                                <img class="img-profile rounded-circle"
                                    src="../../img/undraw_profile.svg">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown"> 
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <div class="container-fluid">

                    <h1 class="h3 mb-2 text-gray-800">Manajemen Pesanan</h1>
                    <p class="mb-4">Kelola semua pesanan pelanggan di sini.</p>

                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($orders)): ?>
                        <div class="alert alert-info text-center" role="alert">
                            Tidak ada pesanan ditemukan.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($orders as $order): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card shadow h-100 py-2 order-card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="m-0 font-weight-bold">Pesanan #<?php echo htmlspecialchars($order['order_id']); ?></h6>
                                            <?php echo get_status_badge($order['status']); ?>
                                        </div>
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                                        Pelanggan: <?php echo htmlspecialchars($order['customer_name']); ?></div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800">Total: Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></div>
                                                    <div class="text-xs text-muted mt-2">
                                                        Tanggal Pesanan: <?php echo date('d M Y H:i', strtotime($order['order_date'])); ?><br>
                                                        Metode Pembayaran: <?php echo htmlspecialchars($order['payment_method']); ?>
                                                    </div>
                                                </div>
                                                <div class="col-auto order-actions">
                                                    <a href="view_order_detail.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-detail">
                                                        <i class="fas fa-info-circle mr-1"></i> Detail
                                                    </a>
                                                    </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
                </div>
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; SkinGlow! 2025</span> </div>
                </div>
            </footer>
            </div>
        </div>
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
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
                    <a class="btn btn-primary" href="../../api/logout.php">Logout</a> </div>
            </div>
        </div>
    </div>

    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>

    <script src="../../js/sb-admin-2.min.js"></script>

    <script>
        $(document).ready(function() {
            // Update admin name in topbar (jika user_name ada di session)
            <?php if (isset($_SESSION['user_name'])): ?>
                $('.navbar-nav .text-gray-600.small').text('<?php echo htmlspecialchars($_SESSION['user_name']); ?>');
            <?php endif; ?>

            // Logika untuk mengisi link hapus di modal konfirmasi (dihapus karena tombol hapus tidak ada)
            // $('#deleteOrderModal').on('show.bs.modal', function (event) {
            //     var button = $(event.relatedTarget); // Tombol yang memicu modal
            //     var orderId = button.data('order-id'); // Ambil nilai dari data-order-id
            //     var modal = $(this);
            //     // Atur action form atau link hapus di modal
            //     modal.find('#confirmDeleteOrderBtn').attr('href', 'manage_orders.php?action=delete&id=' + orderId);
            // });
        });
    </script>

</body>

</html>