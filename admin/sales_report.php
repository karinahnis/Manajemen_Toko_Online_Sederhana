<?php
session_start();

// Cek apakah user sudah login DAN role-nya adalah 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.html?error=Akses tidak diizinkan untuk role ini.");
    exit();
}

require_once '../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$message = '';
$error = '';

// Inisialisasi filter tanggal
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // Default: awal bulan ini
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');     // Default: hari ini

// --- Logika Pengambilan Data Laporan Penjualan ---

// 1. Total Pendapatan & Jumlah Pesanan
$total_revenue = 0;
$total_orders = 0;
$total_items_sold = 0; // Untuk total kuantitas produk terjual

$revenue_query = "
    SELECT
        SUM(total_amount) AS total_revenue,
        COUNT(id) AS total_orders
    FROM orders
    WHERE status IN ('completed', 'processed')
    AND order_date BETWEEN ? AND ? + INTERVAL 1 DAY;
"; // + INTERVAL 1 DAY agar tanggal akhir inklusif
$stmt_revenue = $conn->prepare($revenue_query);
if ($stmt_revenue) {
    $stmt_revenue->bind_param("ss", $start_date, $end_date);
    $stmt_revenue->execute();
    $result_revenue = $stmt_revenue->get_result();
    $data_revenue = $result_revenue->fetch_assoc();
    $total_revenue = $data_revenue['total_revenue'] ?? 0;
    $total_orders = $data_revenue['total_orders'] ?? 0;
    $stmt_revenue->close();
} else {
    $error .= "Error getting revenue data: " . $conn->error . "<br>";
}

// 2. Total Kuantitas Produk Terjual (untuk item dari pesanan yang sudah completed/processed)
$items_sold_query = "
    SELECT
        SUM(oi.quantity) AS total_items_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status IN ('completed', 'processed')
    AND o.order_date BETWEEN ? AND ? + INTERVAL 1 DAY;
";
$stmt_items_sold = $conn->prepare($items_sold_query);
if ($stmt_items_sold) {
    $stmt_items_sold->bind_param("ss", $start_date, $end_date);
    $stmt_items_sold->execute();
    $result_items_sold = $stmt_items_sold->get_result();
    $data_items_sold = $result_items_sold->fetch_assoc();
    $total_items_sold = $data_items_sold['total_items_sold'] ?? 0;
    $stmt_items_sold->close();
} else {
    $error .= "Error getting total items sold: " . $conn->error . "<br>";
}

// 3. Data untuk Laporan Produk Terlaris (berdasarkan kuantitas terjual)
$top_products_query = "
    SELECT
        p.name AS product_name,
        SUM(oi.quantity) AS total_quantity_sold,
        SUM(oi.quantity * oi.price) AS revenue_from_product
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status IN ('completed', 'processed')
    AND o.order_date BETWEEN ? AND ? + INTERVAL 1 DAY
    GROUP BY p.id, p.name
    ORDER BY total_quantity_sold DESC
    LIMIT 10;
";
$stmt_top_products = $conn->prepare($top_products_query);
if ($stmt_top_products) {
    $stmt_top_products->bind_param("ss", $start_date, $end_date);
    $stmt_top_products->execute();
    $top_products_result = $stmt_top_products->get_result();
    $stmt_top_products->close();
} else {
    $error .= "Error getting top products data: " . $conn->error . "<br>";
}

// 4. Data untuk Laporan Kategori Terlaris (berdasarkan total pendapatan)
$top_categories_query = "
    SELECT
        c.name AS category_name,
        SUM(oi.quantity * oi.price) AS total_category_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status IN ('completed', 'processed')
    AND o.order_date BETWEEN ? AND ? + INTERVAL 1 DAY
    GROUP BY c.id, c.name
    ORDER BY total_category_revenue DESC
    LIMIT 5;
";
$stmt_top_categories = $conn->prepare($top_categories_query);
if ($stmt_top_categories) {
    $stmt_top_categories->bind_param("ss", $start_date, $end_date);
    $stmt_top_categories->execute();
    $top_categories_result = $stmt_top_categories->get_result();
    $stmt_top_categories->close();
} else {
    $error .= "Error getting top categories data: " . $conn->error . "<br>";
}

// --- Data untuk Tabel Penjualan Bulanan (menggantikan grafik 12 bulan) ---
$monthly_sales_data = [];
$query_monthly_sales = "
    SELECT
        DATE_FORMAT(order_date, '%Y-%m') AS sales_month,
        SUM(total_amount) AS monthly_revenue,
        COUNT(id) AS monthly_orders
    FROM orders
    WHERE status IN ('completed', 'processed')
    GROUP BY sales_month
    ORDER BY sales_month DESC
    LIMIT 12; -- Ambil 12 bulan terakhir
";
$stmt_monthly_sales = $conn->prepare($query_monthly_sales);
if ($stmt_monthly_sales) {
    $stmt_monthly_sales->execute();
    $result_monthly_sales = $stmt_monthly_sales->get_result();
    while ($row = $result_monthly_sales->fetch_assoc()) {
        $monthly_sales_data[] = $row;
    }
    $stmt_monthly_sales->close();
} else {
    $error .= "Error getting monthly sales data for table: " . $conn->error . "<br>";
}

// --- Data untuk Grafik Waktu (Chart.js) ---
// Penjualan per Hari dalam Seminggu
$daily_sales_labels = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
$daily_sales_data = array_fill(0, 7, 0); // Inisialisasi dengan 0

$query_daily_weekday = "
    SELECT
        DAYOFWEEK(order_date) AS day_of_week,
        SUM(total_amount) AS daily_revenue
    FROM orders
    WHERE status IN ('completed', 'processed')
    AND order_date BETWEEN ? AND ? + INTERVAL 1 DAY
    GROUP BY DAYOFWEEK(order_date)
    ORDER BY DAYOFWEEK(order_date);
";
$stmt_daily_weekday = $conn->prepare($query_daily_weekday);
if ($stmt_daily_weekday) {
    $stmt_daily_weekday->bind_param("ss", $start_date, $end_date);
    $stmt_daily_weekday->execute();
    $result_daily_weekday = $stmt_daily_weekday->get_result();
    while ($row = $result_daily_weekday->fetch_assoc()) {
        $day_index = $row['day_of_week'] - 1; // MySQL DAYOFWEEK returns 1 (Sunday) to 7 (Saturday)
        $daily_sales_data[$day_index] = $row['daily_revenue'];
    }
    $stmt_daily_weekday->close();
} else {
    $error .= "Error getting daily weekday sales data: " . $conn->error . "<br>";
}

// Penjualan per Jam (Hanya untuk hari ini, jika start_date dan end_date sama)
$hourly_sales_labels = [];
$hourly_sales_data = [];

// Jika filter tanggal adalah untuk satu hari saja
if ($start_date == $end_date) {
    for ($h = 0; $h < 24; $h++) {
        $hourly_sales_labels[] = sprintf('%02d:00', $h); // Format 00:00, 01:00, ...
        $hourly_sales_data[] = 0; // Inisialisasi
    }

    $query_hourly = "
        SELECT
            HOUR(order_date) AS hour_of_day,
            SUM(total_amount) AS hourly_revenue
        FROM orders
        WHERE status IN ('completed', 'processed')
        AND DATE(order_date) = ?
        GROUP BY HOUR(order_date)
        ORDER BY HOUR(order_date);
    ";
    $stmt_hourly = $conn->prepare($query_hourly);
    if ($stmt_hourly) {
        $stmt_hourly->bind_param("s", $start_date);
        $stmt_hourly->execute();
        $result_hourly = $stmt_hourly->get_result();
        while ($row = $result_hourly->fetch_assoc()) {
            $hourly_sales_data[$row['hour_of_day']] = $row['hourly_revenue'];
        }
        $stmt_hourly->close();
    } else {
        $error .= "Error getting hourly sales data: " . $conn->error . "<br>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Laporan Penjualan SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>SkinGlow! - Laporan Penjualan</title>

    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../css/index_admin.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/custom_admin.css">
    <link href="../vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
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
                <a class="nav-link" href="products/manage_products.php">
                    <i class="fas fa-fw fa-box"></i>
                    <span>Produk</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="categories/manage_categories.php">
                    <i class="fas fa-fw fa-tags"></i>
                    <span>Kategori Produk</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="orders/manage_orders.php">
                    <i class="fas fa-fw fa-shopping-cart"></i>
                    <span>Pesanan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users/manage_users.php">
                    <i class="fas fa-fw fa-users"></i>
                    <span>Pelanggan</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Manajemen Keuangan</div>
            <li class="nav-item">
                <a class="nav-link" href="transactions/manage_transactions.php">
                    <i class="fas fa-fw fa-money-bill-alt"></i>
                    <span>Transaksi</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Laporan & Statistik</div>
            <li class="nav-item active">
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
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Laporan Penjualan</h1>

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
                            <h6 class="m-0 font-weight-bold text-primary">Filter Laporan</h6>
                        </div>
                        <div class="card-body">
                            <form action="sales_report.php" method="GET" class="form-inline">
                                <div class="form-group mr-3 mb-2">
                                    <label for="start_date" class="mr-2">Dari Tanggal:</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                                </div>
                                <div class="form-group mr-3 mb-2">
                                    <label for="end_date" class="mr-2">Sampai Tanggal:</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-2"><i class="fas fa-filter"></i> Terapkan Filter</button>
                            </form>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Pendapatan</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Jumlah Pesanan</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_orders, 0, ',', '.'); ?></div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Produk Terjual</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_items_sold, 0, ',', '.'); ?></div>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Data Penjualan Bulanan (12 Bulan Terakhir)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Bulan</th>
                                            <th>Total Pendapatan</th>
                                            <th>Jumlah Pesanan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if (!empty($monthly_sales_data)) {
                                            foreach ($monthly_sales_data as $row) {
                                                echo "<tr>";
                                                echo "<td>" . date('F Y', strtotime($row['sales_month'] . '-01')) . "</td>";
                                                echo "<td>Rp " . number_format($row['monthly_revenue'], 0, ',', '.') . "</td>";
                                                echo "<td>" . number_format($row['monthly_orders'], 0, ',', '.') . "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='3'>Tidak ada data penjualan bulanan.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Penjualan per Hari dalam Seminggu</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-bar">
                                        <canvas id="dailyWeekdaySalesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($start_date == $end_date): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Penjualan per Jam (Tanggal <?php echo htmlspecialchars($start_date); ?>)</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-bar">
                                        <canvas id="hourlySalesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>


                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Top 10 Produk Terlaris (Kuantitas)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Produk</th>
                                            <th>Total Kuantitas Terjual</th>
                                            <th>Pendapatan dari Produk</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($top_products_result && $top_products_result->num_rows > 0) {
                                            while ($row = $top_products_result->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['product_name']) . "</td>";
                                                echo "<td>" . number_format($row['total_quantity_sold'], 0, ',', '.') . "</td>";
                                                echo "<td>Rp " . number_format($row['revenue_from_product'], 0, ',', '.') . "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='3'>Tidak ada data produk terlaris dalam periode ini.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Top 5 Kategori Terlaris (Pendapatan)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Kategori</th>
                                            <th>Total Pendapatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($top_categories_result && $top_categories_result->num_rows > 0) {
                                            while ($row = $top_categories_result->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['category_name']) . "</td>";
                                                echo "<td>Rp " . number_format($row['total_category_revenue'], 0, ',', '.') . "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='2'>Tidak ada data kategori terlaris dalam periode ini.</td></tr>";
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
                    <a class="btn btn-primary" href="../api/logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="../vendor/jquery/jquery.min.js"></script>
    <script src="../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../js/sb-admin-2.min.js"></script>
    <script src="../vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="../vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="../js/demo/datatables-demo.js"></script>

    <script>
        $(document).ready(function() {
            <?php if (isset($_SESSION['user_name'])): ?>
                $('.navbar-nav .text-gray-600.small').text('<?php echo htmlspecialchars($_SESSION['user_name']); ?>');
            <?php endif; ?>

            // Fungsi pembantu untuk format angka (tetap diperlukan untuk tabel)
            function number_format(number, decimals, dec_point, thousands_sep) {
                number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
                var n = !isFinite(+number) ? 0 : +number,
                    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
                    sep = (typeof thousands_sep === 'undefined') ? '.' : thousands_sep,
                    dec = (typeof dec_point === 'undefined') ? ',' : dec_point,
                    s = '',
                    toFixedFix = function(n, prec) {
                        var k = Math.pow(10, prec);
                        return '' + Math.round(n * k) / k;
                    };
                s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
                if (s[0].length > 3) {
                    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
                }
                if ((s[1] || '').length < prec) {
                    s[1] = s[1] || '';
                    s[1] += new Array(prec - s[1].length + 1).join('0');
                }
                return s.join(dec);
            }

            // --- Chart.js Konfigurasi (Hanya untuk grafik yang tersisa) ---

            // Grafik Penjualan per Hari dalam Seminggu
            var dailyWeekdaySalesCtx = document.getElementById('dailyWeekdaySalesChart').getContext('2d');
            var dailyWeekdaySalesChart = new Chart(dailyWeekdaySalesCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($daily_sales_labels); ?>,
                    datasets: [{
                        label: 'Pendapatan',
                        backgroundColor: '#4e73df',
                        hoverBackgroundColor: '#2e59d9',
                        borderColor: '#4e73df',
                        data: <?php echo json_encode($daily_sales_data); ?>,
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            left: 10,
                            right: 25,
                            top: 25,
                            bottom: 0
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                maxRotation: 0,
                                minRotation: 0
                            }
                        },
                        y: {
                            ticks: {
                                min: 0,
                                maxTicksLimit: 5,
                                padding: 10,
                                callback: function(value, index, values) {
                                    return 'Rp ' + number_format(value);
                                }
                            },
                            grid: {
                                color: "rgb(234, 236, 244)",
                                zeroLineColor: "rgb(234, 236, 244)",
                                drawBorder: false,
                                borderDash: [2],
                                zeroLineBorderDash: [2]
                            }
                        },
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: "rgb(255,255,255)",
                            bodyColor: "#858796",
                            titleMarginBottom: 10,
                            titleColor: '#6e707e',
                            titleFont: {
                                weight: 'bold'
                            },
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            xPadding: 15,
                            yPadding: 15,
                            displayColors: false,
                            intersect: false,
                            mode: 'index',
                            caretPadding: 10,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': Rp ' + number_format(context.raw);
                                }
                            }
                        }
                    }
                }
            });

            // Grafik Penjualan per Jam (Hanya tampil jika start_date == end_date)
            <?php if ($start_date == $end_date): ?>
            var hourlySalesCtx = document.getElementById('hourlySalesChart').getContext('2d');
            var hourlySalesChart = new Chart(hourlySalesCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($hourly_sales_labels); ?>,
                    datasets: [{
                        label: 'Pendapatan',
                        backgroundColor: '#1cc88a', // Warna hijau
                        hoverBackgroundColor: '#17a673',
                        borderColor: '#1cc88a',
                        data: <?php echo json_encode($hourly_sales_data); ?>,
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            left: 10,
                            right: 25,
                            top: 25,
                            bottom: 0
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                maxRotation: 45, // Rotasi label agar tidak tumpang tindih
                                minRotation: 45
                            }
                        },
                        y: {
                            ticks: {
                                min: 0,
                                maxTicksLimit: 5,
                                padding: 10,
                                callback: function(value, index, values) {
                                    return 'Rp ' + number_format(value);
                                }
                            },
                            grid: {
                                color: "rgb(234, 236, 244)",
                                zeroLineColor: "rgb(234, 236, 244)",
                                drawBorder: false,
                                borderDash: [2],
                                zeroLineBorderDash: [2]
                            }
                        },
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: "rgb(255,255,255)",
                            bodyColor: "#858796",
                            titleMarginBottom: 10,
                            titleColor: '#6e707e',
                            titleFont: {
                                weight: 'bold'
                            },
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            xPadding: 15,
                            yPadding: 15,
                            displayColors: false,
                            intersect: false,
                            mode: 'index',
                            caretPadding: 10,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': Rp ' + number_format(context.raw);
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>

        });
    </script>
</body>
</html>