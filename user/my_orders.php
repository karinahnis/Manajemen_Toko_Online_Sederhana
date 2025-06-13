<?php
session_start();

// Periksa apakah user sudah login dan memiliki role 'user'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    header("Location: ../login.html?error=" . urlencode("Akses tidak diizinkan. Silakan login sebagai user."));
    exit();
}

require_once '../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Pengguna SkinGlow!');
$user_email = htmlspecialchars($_SESSION['user_email'] ?? 'email@example.com');

$orders = [];
$error_message = '';

// Ambil semua pesanan user dari database
$query_orders = "SELECT id, order_date, total_amount, status FROM orders WHERE user_id = ? ORDER BY order_date DESC";
$stmt_orders = $conn->prepare($query_orders);

if ($stmt_orders) {
    $stmt_orders->bind_param("i", $user_id);
    $stmt_orders->execute();
    $result_orders = $stmt_orders->get_result();

    while ($row = $result_orders->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt_orders->close();
} else {
    $error_message = "Gagal mengambil daftar pesanan: " . $conn->error;
}

$conn->close();

// Fungsi helper untuk status badge (optional, bisa juga inline if-else)
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
    <meta name="description" content="Daftar Pesanan Saya - SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>Pesanan Saya - SkinGlow!</title>

    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../css/index_admin.css" rel="stylesheet">
    <link href="../css/dashboard_customer.css" rel="stylesheet">
    <link href="css/checkout.css" rel="stylesheet">
    <style>
        .order-card {
            border-left: 0.25rem solid #e74a3b !important; /* Contoh border warna pink */
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .order-card .card-header {
            background-color: #f8c0d1; /* Latar belakang header card */
            color: #fff;
            font-weight: bold;
        }
        .order-card .card-body strong {
            color: #333;
        }
        .order-actions .btn-detail {
            background-color: #e74a3b;
            border-color: #e74a3b;
            color: #fff;
        }
        .order-actions .btn-detail:hover {
            background-color: #c73a2b;
            border-color: #c73a2b;
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="dashboard_customer.php">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="sidebar-brand-text mx-3">SkinGlow!</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item">
                <a class="nav-link" href="dashboard_customer.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">
                Belanja & Akun
            </div>
            <li class="nav-item">
                <a class="nav-link" href="cart.php">
                    <i class="fas fa-fw fa-shopping-cart"></i>
                    <span>Keranjang Belanja</span>
                </a>
            </li>
            <li class="nav-item active"> <a class="nav-link" href="my_orders.php">
                    <i class="fas fa-fw fa-receipt"></i>
                    <span>Pesanan Saya</span>
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
                                    <?php echo $user_name; ?>
                                </span>
                                <img class="img-profile rounded-circle" src="../img/undraw_profile.svg">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i> Profil
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
                    <h1 class="h3 mb-4 text-gray-800">Daftar Pesanan Saya</h1>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($orders)): ?>
                        <div class="alert alert-info text-center" role="alert">
                            Anda belum memiliki pesanan. Silakan <a href="dashboard_customer.php" class="alert-link">mulai berbelanja</a>!
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($orders as $order): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card shadow h-100 py-2 order-card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="m-0 font-weight-bold">Pesanan #<?php echo htmlspecialchars($order['id']); ?></h6>
                                            <?php echo get_status_badge($order['status']); ?>
                                        </div>
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                                        Total Pesanan</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></div>
                                                    <div class="text-xs text-muted mt-2">
                                                        Tanggal: <?php echo date('d M Y H:i', strtotime($order['order_date'])); ?>
                                                    </div>
                                                </div>
                                                <div class="col-auto order-actions">
                                                    <a href="detail_order   .php?order_id=<?php echo htmlspecialchars($order['id']); ?>" class="btn btn-sm btn-detail">
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

    <script src="../vendor/jquery/jquery.min.js"></script>
    <script src="../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../js/sb-admin-2.min.js"></script>
</body>
</html>