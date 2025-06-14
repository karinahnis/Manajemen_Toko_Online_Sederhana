<?php


require_once '../../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = null;
$order_items = [];

if ($order_id > 0) {
    // Ambil detail pesanan
    $stmt_order = $conn->prepare("
        SELECT o.id, u.name AS customer_name, o.total_amount, o.status, o.order_date, o.payment_method
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt_order->bind_param("i", $order_id);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();
    if ($result_order->num_rows > 0) {
        $order = $result_order->fetch_assoc();
    }
    $stmt_order->close();

    // Ambil item-item dalam pesanan
    $stmt_items = $conn->prepare("
        SELECT oi.quantity, oi.price_at_order, p.name AS product_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    if ($result_items->num_rows > 0) {
        while ($row = $result_items->fetch_assoc()) {
            $order_items[] = $row;
        }
    }
    $stmt_items->close();
}

$conn->close();

// Fungsi helper untuk status badge (copy dari manage_orders.php)
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
    <title>Detail Pesanan - Admin SkinGlow!</title>
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../css/index_admin.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/custom_admin.css">
    <link rel="stylesheet" href="../../css/orders/admin_orders.css">
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="../index.php">
                <div class="sidebar-brand-icon rotate-n-15"><i class="fas fa-cubes"></i></div>
                <div class="sidebar-brand-text mx-3">SkinGlow! Admin</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item"><a class="nav-link" href="../index.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Manajemen E-commerce</div>
            <li class="nav-item"><a class="nav-link" href="../products/manage_products.php"><i class="fas fa-fw fa-box"></i><span>Produk</span></a></li>
            <li class="nav-item"><a class="nav-link" href="../categories/manage_categories.php"><i class="fas fa-fw fa-tags"></i><span>Kategori Produk</span></a></li>
            <li class="nav-item active"><a class="nav-link" href="manage_orders.php"><i class="fas fa-fw fa-shopping-cart"></i><span>Pesanan Pelanggan</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Manajemen Keuangan</div>
            <li class="nav-item"><a class="nav-link" href="../transactions/manage_transactions.php"><i class="fas fa-fw fa-cash-register"></i><span>Transaksi</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Laporan & Statistik</div>
            <li class="nav-item"><a class="nav-link" href="../sales_report.php"><i class="fas fa-fw fa-chart-line"></i><span>Laporan Penjualan</span></a></li>
            <hr class="sidebar-divider d-none d-md-block">
            <div class="text-center d-none d-md-inline"><button class="rounded-circle border-0" id="sidebarToggle"></button></div>
        </ul>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars"></i></button>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin Default'); ?></span>
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
                    <h1 class="h3 mb-4 text-gray-800">Detail Pesanan #<?php echo htmlspecialchars($order_id); ?></h1>

                    <?php if (!$order): ?>
                        <div class="alert alert-danger" role="alert">
                            Pesanan dengan ID #<?php echo htmlspecialchars($order_id); ?> tidak ditemukan.
                        </div>
                    <?php else: ?>
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Informasi Pesanan</h6>
                                <?php echo get_status_badge($order['status']); ?>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>ID Pesanan:</strong> <?php echo htmlspecialchars($order['id']); ?></p>
                                        <p><strong>Tanggal Pesanan:</strong> <?php echo date('d M Y H:i', strtotime($order['order_date'])); ?></p>
                                        <p><strong>Metode Pembayaran:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Total Jumlah:</strong> Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></p>
                                        <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($order['status'])); ?></p>
                                    </div>
                                </div>
                                <hr>
                                <h5>Item Pesanan</h5>
                                <?php if (empty($order_items)): ?>
                                    <p class="text-muted">Tidak ada item dalam pesanan ini.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>Produk</th>
                                                    <th>Jumlah</th>
                                                    <th>Harga Satuan (saat beli)</th>
                                                    <th>Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($order_items as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                                        <td>Rp <?php echo number_format($item['price_at_order'], 0, ',', '.'); ?></td>
                                                        <td>Rp <?php echo number_format($item['quantity'] * $item['price_at_order'], 0, ',', '.'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <a href="manage_orders.php" class="btn btn-secondary mt-3">Kembali ke Daftar Pesanan</a>
                                </div>
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
    <a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Siap untuk Keluar?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
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
</body>
</html>