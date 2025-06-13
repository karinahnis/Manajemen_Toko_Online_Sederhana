<?php

session_start();

// Cek apakah user sudah login DAN role-nya adalah 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.html?error=Akses tidak diizinkan untuk role ini.");
    exit();
}

require_once '../config/database.php';

// Inisialisasi koneksi database
$conn = get_db_connection(); // Fungsi ini harus ada di database.php

// --- Ambil data untuk Dashboard Cards ---
$total_active_products = 0;
$query_products = "SELECT COUNT(id) AS total FROM products WHERE is_active = 1"; // Asumsi ada kolom is_active
$result_products = $conn->query($query_products);
if ($result_products && $result_products->num_rows > 0) {
    $row_products = $result_products->fetch_assoc();
    $total_active_products = $row_products['total'];
}

$monthly_revenue = 0;
// Menggunakan MONTH(CURRENT_DATE()) dan YEAR(CURRENT_DATE()) untuk bulan dan tahun saat ini
$query_monthly_revenue = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE MONTH(order_date) = MONTH(CURRENT_DATE()) AND YEAR(order_date) = YEAR(CURRENT_DATE()) AND status = 'completed'"; // Asumsi status 'Selesai' berarti pendapatan
$result_monthly_revenue = $conn->query($query_monthly_revenue);
if ($result_monthly_revenue && $result_monthly_revenue->num_rows > 0) {
    $row_monthly_revenue = $result_monthly_revenue->fetch_assoc();
    $monthly_revenue = $row_monthly_revenue['total'] ? $row_monthly_revenue['total'] : 0;
}

$total_new_customers = 0;
$query_new_customers = "SELECT COUNT(id) AS total FROM users WHERE role = 'user'"; // Asumsi ada kolom role 'customer'
$result_new_customers = $conn->query($query_new_customers);
if ($result_new_customers && $result_new_customers->num_rows > 0) {
    $row_new_customers = $result_new_customers->fetch_assoc();
    $total_new_customers = $row_new_customers['total'];
}


// --- Ambil data untuk Grafik Penjualan Bulanan (Area Chart) ---
$sales_data_labels = [];
$sales_data_values = [];

// Mendapatkan 12 bulan terakhir (termasuk bulan saat ini)
$months = [];
for ($i = 0; $i < 12; $i++) {
    $months[] = date('Y-m', strtotime("-$i months"));
}
$months = array_reverse($months); // Urutkan dari bulan terlama ke terbaru

foreach ($months as $month_year) {
    $month_label = date('M Y', strtotime($month_year)); // Contoh: "Jan 2023"
    $query_sales_per_month = "SELECT COALESCE(SUM(total_amount), 0) AS total_revenue
                              FROM orders
                              WHERE DATE_FORMAT(order_date, '%Y-%m') = ?
                              AND status = 'completed'"; // Hanya pesanan selesai
    $stmt_sales = $conn->prepare($query_sales_per_month);
    $stmt_sales->bind_param("s", $month_year);
    $stmt_sales->execute();
    $result_sales = $stmt_sales->get_result();
    $row_sales = $result_sales->fetch_assoc();

    $sales_data_labels[] = $month_label;
    $sales_data_values[] = (float)$row_sales['total_revenue'];
    $stmt_sales->close();
}


// --- Ambil data untuk Produk Terlaris (Pie Chart) ---
$top_products_labels = [];
$top_products_values = [];

$query_top_products = "SELECT
                            p.name AS product_name,
                            SUM(oi.quantity) AS total_sold
                        FROM order_items oi
                        JOIN products p ON oi.product_id = p.id
                        GROUP BY p.name
                        ORDER BY total_sold DESC
                        LIMIT 5"; // Ambil 5 produk terlaris

$result_top_products = $conn->query($query_top_products);

if ($result_top_products && $result_top_products->num_rows > 0) {
    while ($row = $result_top_products->fetch_assoc()) {
        $top_products_labels[] = $row['product_name'];
        $top_products_values[] = (int)$row['total_sold'];
    }
} else {
    // Data dummy jika tidak ada produk terjual, agar chart tidak error
    $top_products_labels = ["Tidak Ada Data"];
    $top_products_values = [1]; // Beri nilai minimal agar pie chart bisa dirender
}


// --- Ambil data untuk Pesanan Terbaru (Table) ---
$latest_orders = [];
// Perbaikan di sini: Mengganti u.username dengan u.name jika kolom di tabel users adalah 'name'
$query_latest_orders = "SELECT
                            o.id AS order_id,
                            u.name AS customer_name,
                            o.total_amount,
                            o.status,
                            o.order_date
                        FROM orders o
                        JOIN users u ON o.user_id = u.id
                        ORDER BY o.order_date DESC
                        LIMIT 5"; // Ambil 5 pesanan terbaru

$result_latest_orders = $conn->query($query_latest_orders);
if ($result_latest_orders && $result_latest_orders->num_rows > 0) {
    while ($row = $result_latest_orders->fetch_assoc()) {
        $latest_orders[] = $row;
    }
}


// --- Ambil data untuk Produk Stok Rendah (Table) ---
$low_stock_products = [];
// Asumsi Anda memiliki kolom 'stock' dan 'min_stock_threshold' di tabel products
$query_low_stock = "SELECT id, name, stock FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 5"; // Produk dengan stok <= 10
$result_low_stock = $conn->query($query_low_stock);
if ($result_low_stock && $result_low_stock->num_rows > 0) {
    while ($row = $result_low_stock->fetch_assoc()) {
        $low_stock_products[] = $row;
    }
}

// Tutup koneksi database
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Dashboard Admin SkinGlow - Ringkasan Penjualan dan Manajemen">
    <meta name="author" content="Tim SkinGlow">

    <title>SkinGlow! - Dashboard Admin</title>

    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link href="../css/index_admin.css" rel="stylesheet">
    <link href="../css/sb-admin-2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/custom_admin.css">

    <link href="../vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
</head>

<body id="page-top">

    <div id="wrapper">

        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar" >

            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="sidebar-brand-text mx-3">SkinGlow! Admin</div>
            </a>

            <hr class="sidebar-divider my-0">

            <li class="nav-item active">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span></a>
            </li>

            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                Manajemen E-commerce
            </div>

            <li class="nav-item">
                <a class="nav-link" href="products/manage_products.php"> <i class="fas fa-fw fa-box"></i>
                    <span>Produk</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="categories/manage_categories.php"> <i class="fas fa-fw fa-tags"></i>
                    <span>Kategori Produk</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="orders/manage_orders.php"> <i class="fas fa-fw fa-shopping-cart"></i>
                    <span>Pesanan</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="users/manage_users.php"> <i class="fas fa-fw fa-users"></i>
                    <span>Pengguna</span></a>
            </li>
            
            <hr class="sidebar-divider">
            <div class="sidebar-heading">
                Manajemen Keuangan
            </div>

            <li class="nav-item">
                <a class="nav-link" href="transactions/manage_transactions.php"> <i class="fas fa-fw fa-money-bill-alt"></i>
                    <span>Transaksi</span></a>
            </li>
            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                Laporan & Statistik
            </div>

            <li class="nav-item">
                <a class="nav-link" href="sales_report.php"> <i class="fas fa-fw fa-chart-line"></i>
                    <span>Laporan Penjualan</span></a>
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
                                    src="../img/undraw_profile.svg">
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

                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Dashboard Admin</h1>
                    </div>

                    <div class="row">

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Produk Aktif</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_active_products; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pendapatan Bulanan</div>
                                            <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">Rp <?php echo number_format($monthly_revenue, 0, ',', '.'); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Total Pengguna Terdaftar</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_new_customers; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="row">

                        <div class="col-xl-8 col-lg-7">
                            <div class="card shadow mb-4">
                                <div
                                    class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Grafik Penjualan Bulanan (Total Pendapatan)</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-area">
                                        <canvas id="myAreaChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-lg-5">
                            <div class="card shadow mb-4">
                                <div
                                    class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Produk Terlaris (Berdasarkan Kuantitas)</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-pie pt-4 pb-2">
                                        <canvas id="myPieChart"></canvas>
                                    </div>
                                    <div class="mt-4 text-center small">
                                        <?php if (!empty($top_products_labels)): ?>
                                            <?php
                                                // Warna default SB Admin 2 untuk pie chart
                                                $default_colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'];
                                            ?>
                                            <?php foreach ($top_products_labels as $index => $label): ?>
                                                <span class="mr-2">
                                                    <i class="fas fa-circle" style="color: <?php echo $default_colors[$index % count($default_colors)]; ?>;"></i> <?php echo htmlspecialchars($label); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span>Tidak ada data produk terlaris.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">

                        <div class="col-lg-7 mb-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Pesanan Terbaru</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="latestOrdersTable" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>ID Pesanan</th>
                                                    <th>Pelanggan</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                    <th>Tanggal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($latest_orders)): ?>
                                                    <?php foreach ($latest_orders as $order): ?>
                                                        <tr>
                                                            <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                            <td>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                                            <td>
                                                                <?php
                                                                    $status_badge = '';
                                                                    switch ($order['status']) {
                                                                        case 'Pending': $status_badge = 'badge-warning'; break;
                                                                        case 'completed': $status_badge = 'badge-success'; break; // Changed from 'Selesai' to 'completed' as per query
                                                                        case 'Shipped': $status_badge = 'badge-info'; break; // Assuming 'Dikirim' means 'Shipped'
                                                                        case 'Cancelled': $status_badge = 'badge-danger'; break; // Assuming 'Dibatalkan' means 'Cancelled'
                                                                        default: $status_badge = 'badge-secondary'; break;
                                                                    }
                                                                ?>
                                                                <span class="badge <?php echo $status_badge; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                                                            </td>
                                                            <td><?php echo date('Y-m-d', strtotime($order['order_date'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center">Tidak ada pesanan terbaru.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="orders/manage_orders.php" class="btn btn-sm btn-outline-primary">Lihat Semua Pesanan &rarr;</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5 mb-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Produk Stok Rendah</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="lowStockProductsTable" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>Nama Produk</th>
                                                    <th>Stok</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($low_stock_products)): ?>
                                                    <?php foreach ($low_stock_products as $product): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                            <td><span class="badge badge-danger"><?php echo htmlspecialchars($product['stock']); ?></span></td>
                                                            <td><a href="products/manage_products.php?action=edit&id=<?php echo htmlspecialchars($product['id']); ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center">Tidak ada produk dengan stok rendah.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="products/manage_products.php" class="btn btn-sm btn-outline-primary">Kelola Produk &rarr;</a>
                                    </div>
                                </div>
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
                    <a class="btn btn-primary" href="../api/logout.php">Logout</a> </div>
            </div>
        </div>
    </div>

    <script src="../vendor/jquery/jquery.min.js"></script>
    <script src="../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script src="../vendor/jquery-easing/jquery.easing.min.js"></script>

    <script src="../js/sb-admin-2.min.js"></script>

    <script src="../vendor/chart.js/Chart.min.js"></script>
    <script src="../vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="../vendor/datatables/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            // Area Chart - Monthly Sales
            var ctxArea = document.getElementById("myAreaChart");
            var myLineChart = new Chart(ctxArea, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($sales_data_labels); ?>,
                    datasets: [{
                        label: "Penjualan",
                        lineTension: 0.3,
                        backgroundColor: "rgba(78, 115, 223, 0.05)",
                        borderColor: "rgba(78, 115, 223, 1)",
                        pointRadius: 3,
                        pointBackgroundColor: "rgba(78, 115, 223, 1)",
                        pointBorderColor: "rgba(78, 115, 223, 1)",
                        pointHoverRadius: 3,
                        pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                        pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        data: <?php echo json_encode($sales_data_values); ?>,
                    }],
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
                        xAxes: [{
                            time: {
                                unit: 'date'
                            },
                            gridLines: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                maxTicksLimit: 12
                            }
                        }],
                        yAxes: [{
                            ticks: {
                                maxTicksLimit: 5,
                                padding: 10,
                                // Include a dollar sign in the ticks
                                callback: function(value, index, values) {
                                    return 'Rp ' + number_format(value);
                                }
                            },
                            gridLines: {
                                color: "rgb(234, 236, 244)",
                                zeroLineColor: "rgb(234, 236, 244)",
                                drawBorder: false,
                                borderDash: [2],
                                zeroLineBorderDash: [2]
                            }
                        }],
                    },
                    legend: {
                        display: false
                    },
                    tooltips: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyFontColor: "#858796",
                        titleMarginBottom: 10,
                        titleFontColor: '#6e707e',
                        titleFontSize: 14,
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        intersect: false,
                        mode: 'index',
                        caretPadding: 10,
                        callbacks: {
                            label: function(tooltipItem, chart) {
                                var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                                return datasetLabel + ': Rp ' + number_format(tooltipItem.yLabel);
                            }
                        }
                    }
                }
            });

            // Pie Chart - Top Products
            var ctxPie = document.getElementById("myPieChart");
            var myPieChart = new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($top_products_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($top_products_values); ?>,
                        backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                        hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#f4b619', '#d43f30'],
                        hoverBorderColor: "rgba(234, 236, 244, 1)",
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    tooltips: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyFontColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        caretPadding: 10,
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var dataset = data.datasets[tooltipItem.datasetIndex];
                                var total = dataset.data.reduce(function(previousValue, currentValue, currentIndex, array) {
                                    return previousValue + currentValue;
                                });
                                var currentValue = dataset.data[tooltipItem.index];
                                var percentage = Math.floor(((currentValue/total)*100)+0.5);
                                return data.labels[tooltipItem.index] + ': ' + currentValue + ' (' + percentage + '%)';
                            }
                        }
                    },
                    legend: {
                        display: false
                    },
                    cutoutPercentage: 80,
                },
            });

            // Function to format numbers for tooltips and y-axis labels
            function number_format(number, decimals, dec_point, thousands_sep) {
                // * example: number_format(1234.56, 2, ',', ' ');
                // * return: '1 234,56'
                number = (number + '').replace(',', '').replace(' ', '');
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

            // Inisialisasi DataTables untuk tabel pesanan terbaru
            $('#latestOrdersTable').DataTable({
                "paging": false, 
                "searching": false, 
                "info": false, 
                "order": [[4, "desc"]] // Mengurutkan berdasarkan kolom Tanggal (indeks 4) secara descending
            });

            // Inisialisasi DataTables untuk tabel produk stok rendah
            $('#lowStockProductsTable').DataTable({
                "paging": false, 
                "searching": false, 
                "info": false, 
                "order": [[1, "asc"]] // Mengurutkan berdasarkan kolom Stok (indeks 1) secara ascending
            });

            // Update admin name in topbar (jika user_name ada di session)
            <?php if (isset($_SESSION['user_name'])): ?>
                $('.navbar-nav .text-gray-600.small').text('<?php echo htmlspecialchars($_SESSION['user_name']); ?>');
            <?php endif; ?>
        });
    </script>

</body>

</html>