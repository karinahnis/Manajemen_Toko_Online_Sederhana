<?php
session_start();

// Cek apakah user sudah login DAN role-nya adalah 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.html?error=Akses tidak diizinkan untuk role ini.");
    exit();
}

require_once '../../config/database.php';

$conn = get_db_connection();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order_details = null;
$order_items = [];
$error_message = '';

if ($order_id > 0) {
    // Ambil detail pesanan
    $query_order = "SELECT
                        o.id AS order_id,
                        u.name AS customer_name,
                        u.email AS customer_email,
                        o.total_amount,
                        o.status,
                        o.order_date,
                        o.shipping_address,
                        o.payment_method
                    FROM orders o
                    JOIN users u ON o.user_id = u.id
                    WHERE o.id = ?";
    $stmt_order = $conn->prepare($query_order);

    if ($stmt_order) {
        $stmt_order->bind_param("i", $order_id);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();
        if ($result_order->num_rows > 0) {
            $order_details = $result_order->fetch_assoc();
        } else {
            $error_message = "Pesanan tidak ditemukan.";
        }
        $stmt_order->close();
    } else {
        $error_message = "Gagal mengambil detail pesanan: " . $conn->error;
    }

    // Ambil item-item dalam pesanan
    if ($order_details) {
        $query_items = "SELECT
                            oi.quantity,
                            oi.price AS item_price,
                            p.name AS product_name,
                            p.image_url -- Asumsi ada kolom ini di tabel products
                        FROM order_items oi
                        JOIN products p ON oi.product_id = p.id
                        WHERE oi.order_id = ?";
        $stmt_items = $conn->prepare($query_items);
        if ($stmt_items) {
            $stmt_items->bind_param("i", $order_id);
            $stmt_items->execute();
            $result_items = $stmt_items->get_result();
            while ($row = $result_items->fetch_assoc()) {
                $order_items[] = $row;
            }
            $stmt_items->close();
        } else {
            $error_message .= " Gagal mengambil item pesanan: " . $conn->error;
        }
    }

} else {
    $error_message = "ID Pesanan tidak valid.";
}

$conn->close();

// Fungsi helper untuk status badge (bisa diambil dari manage_orders.php)
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
    <meta name="description" content="Detail Pesanan Admin SkinGlow">
    <meta name="author" content="Tim SkinGlow">
    <title>Detail Pesanan - SkinGlow! Admin</title>

    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../custom_admin.css">
    <link href="../../css/index_admin.css" rel="stylesheet">
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
            <li class="nav-item"> <a class="nav-link" href="../index.php"> <i class="fas fa-fw fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading"> Manajemen E-commerce </div>
            <li class="nav-item"> <a class="nav-link" href="../products/manage_products.php"> <i class="fas fa-fw fa-box"></i> <span>Produk</span></a></li>
            <li class="nav-item"> <a class="nav-link" href="../categories/manage_categories.php"> <i class="fas fa-fw fa-tags"></i> <span>Kategori Produk</span></a></li>
            <li class="nav-item active"> <a class="nav-link" href="../orders/manage_orders.php"> <i class="fas fa-fw fa-shopping-cart"></i> <span>Pesanan</span></a></li>
            <li class="nav-item"> <a class="nav-link" href="../users/manage_users.php"> <i class="fas fa-fw fa-users"></i> <span>Pelanggan</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading"> Manajemen Keuangan </div>
            <li class="nav-item"> <a class="nav-link" href="../transactions/manage_transactions.php"> <i class="fas fa-fw fa-money-bill-alt"></i> <span>Transaksi</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading"> Laporan & Statistik </div>
            <li class="nav-item"> <a class="nav-link" href="../sales_report.php"> <i class="fas fa-fw fa-chart-line"></i> <span>Laporan Penjualan</span></a></li>
            <hr class="sidebar-divider d-none d-md-block">
            <div class="text-center d-none d-md-inline"> <button class="rounded-circle border-0" id="sidebarToggle"></button> </div>
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
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal"> <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Detail Pesanan #<?php echo htmlspecialchars($order_id); ?></h1>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($order_details): ?>
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Informasi Pesanan</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>ID Pesanan:</strong> #<?php echo htmlspecialchars($order_details['order_id']); ?></p>
                                        <p><strong>Tanggal Pesanan:</strong> <?php echo date('d M Y H:i', strtotime($order_details['order_date'])); ?></p>
                                        <p><strong>Total Jumlah:</strong> Rp <?php echo number_format($order_details['total_amount'], 0, ',', '.'); ?></p>
                                        <p><strong>Status:</strong> <?php echo get_status_badge($order_details['status']); ?></p>
                                        <p><strong>Metode Pembayaran:</strong> <?php echo htmlspecialchars($order_details['payment_method']); ?></p>
                                        <?php if (!empty($order_details['payment_details'])): ?>
                                            <p><strong>Detail Pembayaran:</strong> <?php echo htmlspecialchars($order_details['payment_details']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Nama Pelanggan:</strong> <?php echo htmlspecialchars($order_details['customer_name']); ?></p>
                                        <p><strong>Email Pelanggan:</strong> <?php echo htmlspecialchars($order_details['customer_email']); ?></p>
                                        <p><strong>Alamat Pengiriman:</strong> <?php echo nl2br(htmlspecialchars($order_details['shipping_address'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Item Pesanan</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($order_items)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>Produk</th>
                                                    <th>Harga Satuan</th>
                                                    <th>Kuantitas</th>
                                                    <th>Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($order_items as $item): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if (!empty($item['image_url'])): ?>
                                                                <img src="../../<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" width="50" class="img-thumbnail mr-2">
                                                            <?php endif; ?>
                                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                                        </td>
                                                        <td>Rp <?php echo number_format($item['item_price'], 0, ',', '.'); ?></td>
                                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                                        <td>Rp <?php echo number_format($item['item_price'] * $item['quantity'], 0, ',', '.'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center">Tidak ada item produk dalam pesanan ini.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <a href="manage_orders.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan</a>
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
    <a class="scroll-to-top rounded" href="#page-top"> <i class="fas fa-angle-up"></i> </a>

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

            $('#deleteOrderModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var orderId = button.data('order-id');
                var modal = $(this);
                // Penting: link ini harus mengarah kembali ke manage_orders.php untuk proses hapus
                modal.find('#confirmDeleteOrderBtn').attr('href', 'manage_orders.php?action=delete&id=' + orderId);
            });
        });
    </script>
</body>
</html>