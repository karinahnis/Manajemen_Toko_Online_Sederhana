<?php

session_start();
require_once '../config/database.php';

// Cek apakah pengguna sudah login. Jika belum, arahkan ke halaman login.
// Ini adalah implementasi keamanan dasar. Anda mungkin perlu logika otorisasi yang lebih kompleks
// untuk memastikan hanya admin yang bisa mengakses halaman ini.
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php'); // Sesuaikan dengan path halaman login Anda
    exit();
}

// Inisialisasi koneksi database
$conn = get_db_connection();

// Set error reporting untuk pengembangan
// Hapus baris ini di lingkungan produksi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- Ambil data untuk Dashboard Cards ---

// 1. Total Produk Aktif (stok > 0)
$total_active_products = 0;
$query_products = "SELECT COUNT(id) AS total FROM products WHERE stock > 0";
$result_products = $conn->query($query_products);
if ($result_products) {
    $row_products = $result_products->fetch_assoc();
    $total_active_products = $row_products['total'];
} else {
    // Log error daripada menampilkannya langsung di produksi
    error_log("Error fetching total active products: " . $conn->error);
}

// 2. Pendapatan Bulanan (bulan dan tahun saat ini, status 'completed')
$monthly_revenue = 0;
$query_monthly_revenue = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE MONTH(order_date) = MONTH(CURRENT_DATE()) AND YEAR(order_date) = YEAR(CURRENT_DATE()) AND status = 'completed'";
$result_monthly_revenue = $conn->query($query_monthly_revenue);
if ($result_monthly_revenue) {
    $row_monthly_revenue = $result_monthly_revenue->fetch_assoc();
    $monthly_revenue = (float)($row_monthly_revenue['total'] ?? 0); // Menggunakan null coalescing operator untuk PHP 7+
} else {
    error_log("Error fetching monthly revenue: " . $conn->error);
}

// 3. Produk Stok Habis (stok = 0)
$total_out_of_stock_products = 0;
$query_out_of_stock = "SELECT COUNT(id) AS total FROM products WHERE stock = 0";
$result_out_of_stock = $conn->query($query_out_of_stock);
if ($result_out_of_stock) {
    $row_out_of_stock = $result_out_of_stock->fetch_assoc();
    $total_out_of_stock_products = $row_out_of_stock['total'];
} else {
    error_log("Error fetching out of stock products: " . $conn->error);
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
    $month_label = date('M Y', strtotime($month_year));
    $query_sales_per_month = "SELECT COALESCE(SUM(total_amount), 0) AS total_revenue
                              FROM orders
                              WHERE DATE_FORMAT(order_date, '%Y-%m') = ?
                              AND status = 'completed'";
    $stmt_sales = $conn->prepare($query_sales_per_month);

    if ($stmt_sales) {
        $stmt_sales->bind_param("s", $month_year);
        $stmt_sales->execute();
        $result_sales = $stmt_sales->get_result();
        $row_sales = $result_sales->fetch_assoc();

        $sales_data_labels[] = $month_label;
        $sales_data_values[] = (float)$row_sales['total_revenue'];
        $stmt_sales->close();
    } else {
        error_log("Error preparing sales per month query: " . $conn->error);
        $sales_data_labels[] = $month_label;
        $sales_data_values[] = 0;
    }
}

// --- Ambil data untuk Produk Terlaris (Pie Chart) ---
$top_products_labels = [];
$top_products_values = [];

$query_top_products = "SELECT
                            p.name AS product_name,
                            SUM(oi.quantity) AS total_sold
                        FROM order_items oi
                        JOIN products p ON oi.product_id = p.id
                        JOIN orders o ON oi.order_id = o.id
                        WHERE o.status = 'completed' -- Hanya produk dari pesanan yang selesai
                        GROUP BY p.name
                        ORDER BY total_sold DESC
                        LIMIT 5";

$result_top_products = $conn->query($query_top_products);

if ($result_top_products) {
    if ($result_top_products->num_rows > 0) {
        while ($row = $result_top_products->fetch_assoc()) {
            $top_products_labels[] = $row['product_name'];
            $top_products_values[] = (int)$row['total_sold'];
        }
    } else {
        $top_products_labels = ["Tidak Ada Data"];
        $top_products_values = [1];
    }
} else {
    error_log("Error fetching top products: " . $conn->error);
    $top_products_labels = ["Error Data"];
    $top_products_values = [1];
}

// --- Ambil data untuk Produk Stok Rendah (Table dan Peringatan) ---
$low_stock_products = [];
$stock_threshold = 10; // Anda bisa mengatur ambang batas stok rendah di sini
// Hanya produk dengan stok > 0 tapi <= ambang batas
$query_low_stock = "SELECT id, name, stock FROM products WHERE stock > 0 AND stock <= ? ORDER BY stock ASC";
$stmt_low_stock = $conn->prepare($query_low_stock);

if ($stmt_low_stock) {
    $stmt_low_stock->bind_param("i", $stock_threshold);
    $stmt_low_stock->execute();
    $result_low_stock = $stmt_low_stock->get_result();

    if ($result_low_stock->num_rows > 0) {
        while ($row = $result_low_stock->fetch_assoc()) {
            $low_stock_products[] = $row;
        }
    }
    $stmt_low_stock->close();
} else {
    error_log("Error preparing low stock query: " . $conn->error);
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

        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

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
                                        // Pastikan session user_name di-set sebelum mencoba mengaksesnya
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
                            <div class="card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                Produk Stok Habis</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_out_of_stock_products; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
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
                                        <?php if (!empty($top_products_labels) && $top_products_labels[0] !== "Tidak Ada Data" && $top_products_labels[0] !== "Error Data"): ?>
                                            <?php
                                                $default_colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'];
                                            ?>
                                            <?php foreach ($top_products_labels as $index => $label): ?>
                                                <span class="mr-2">
                                                    <i class="fas fa-circle" style="color: <?php echo $default_colors[$index % count($default_colors)]; ?>;"></i> <?php echo htmlspecialchars($label); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span><?php echo htmlspecialchars($top_products_labels[0] ?? 'Tidak Ada Data Penjualan'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12 mb-4"> <div class="card shadow mb-4">
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
        // Fungsi number_format untuk Chart.js tooltips
        function number_format(number, decimals, dec_point, thousands_sep) {
            // * example: number_format(1234.56, 2, ',', ' ');
            // * returns: '1 234,56'
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

            // Pie Chart - Top Selling Products
            var ctxPie = document.getElementById("myPieChart");
            var myPieChart = new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($top_products_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($top_products_values); ?>,
                        backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                        hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#d4a52d', '#c23c31'],
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
                            label: function(tooltipItem, chart) {
                                var dataLabel = chart.labels[tooltipItem.index];
                                var value = chart.datasets[0].data[tooltipItem.index];
                                // Menampilkan nilai sebagai integer (kuantitas)
                                return dataLabel + ': ' + number_format(value, 0) + ' unit';
                            }
                        }
                    },
                    legend: {
                        display: false
                    },
                    cutoutPercentage: 80,
                },
            });

            // DataTables Initialization (for Low Stock Products)
            $('#lowStockProductsTable').DataTable({
                "paging": true,
                "lengthChange": false,
                "searching": false,
                "ordering": true,
                "info": false,
                "autoWidth": false,
                "responsive": true,
                "pageLength": 5 // Menampilkan hanya 5 baris
            });
        });
    </script>
</body>

</html>