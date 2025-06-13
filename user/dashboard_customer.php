<?php
session_start();

// ====== DEBUGGING SECTION START ======
// Aktifkan laporan error PHP untuk membantu melacak masalah
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cek isi dari sesi saat ini
// echo "<pre>"; print_r($_SESSION); echo "</pre>"; // Matikan ini setelah debugging selesai
// ====== DEBUGGING SECTION END ======


// Periksa apakah user sudah login dan memiliki role 'user'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    // Tambahkan pesan error yang lebih spesifik untuk debugging
    $redirect_error = "Akses tidak diizinkan.";
    if (!isset($_SESSION['user_id'])) {
        $redirect_error .= " (User ID tidak ditemukan di sesi)";
    }
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'user') {
        $redirect_error .= " (Role: " . htmlspecialchars($_SESSION['user_role']) . " bukan 'user')";
    }
    header("Location: ../login.html?error=" . urlencode($redirect_error));
    exit();
}

require_once '../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$user_id = $_SESSION['user_id'];
// Mengambil nama pengguna dari sesi. Jika tidak ada, gunakan default.
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Pengguna SkinGlow!');
$user_email = htmlspecialchars($_SESSION['user_email'] ?? 'email@example.com');

$message = '';
$error = '';

if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// --- Ambil Data untuk Dashboard Cards Customer ---
$total_items_in_cart = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total_items_in_cart += $item['quantity'];
    }
}

$total_active_orders = 0;
$query_active_orders = "
    SELECT COUNT(id) AS total
    FROM orders
    WHERE user_id = ? AND status IN ('Pending', 'Dikirim', 'Diproses');
";
$stmt_active_orders = $conn->prepare($query_active_orders);
if ($stmt_active_orders) {
    $stmt_active_orders->bind_param("i", $user_id);
    $stmt_active_orders->execute();
    $result_active_orders = $stmt_active_orders->get_result();
    $row_active_orders = $result_active_orders->fetch_assoc();
    $total_active_orders = $row_active_orders['total'];
    $stmt_active_orders->close();
} else {
    $error .= "Gagal mengambil jumlah pesanan aktif: " . $conn->error . "<br>";
}

// --- Ambil data untuk Produk Berdasarkan Kategori (Bagian Utama) ---
$categories_with_products = [];
$categories_query = "SELECT id, name FROM categories ORDER BY name ASC";
$result_categories = $conn->query($categories_query);

if ($result_categories && $result_categories->num_rows > 0) {
    while ($category = $result_categories->fetch_assoc()) {
        $category_id = $category['id'];
        $category_name = $category['name'];

        $products_in_category = [];
        // Ambil 4 produk terbaru atau terlaris dari setiap kategori
        $products_query = "
            SELECT
                id,
                name,
                price,
                image_url
            FROM products
            WHERE category_id = ?
            ORDER BY created_at DESC
            LIMIT 4;
        ";
        $stmt_products = $conn->prepare($products_query);
        if ($stmt_products) {
            $stmt_products->bind_param("i", $category_id);
            $stmt_products->execute();
            $result_products = $stmt_products->get_result();
            while ($product = $result_products->fetch_assoc()) {
                $products_in_category[] = $product;
            }
            $stmt_products->close();
        } else {
            $error .= "Gagal mengambil produk dalam kategori: " . $conn->error . "<br>";
        }

        if (!empty($products_in_category)) {
            $categories_with_products[] = [
                'id' => $category_id,
                'name' => $category_name,
                'products' => $products_in_category
            ];
        }
    }
}

// --- Ambil Data untuk Pesanan Terbaru (Table) ---
$latest_orders_customer = [];
$query_latest_orders_customer = "
    SELECT
        o.id AS order_id,
        o.order_date,
        o.total_amount,
        o.status
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC
    LIMIT 5";

$stmt_latest_orders_customer = $conn->prepare($query_latest_orders_customer);
if ($stmt_latest_orders_customer) {
    $stmt_latest_orders_customer->bind_param("i", $user_id);
    $stmt_latest_orders_customer->execute();
    $result_latest_orders_customer = $stmt_latest_orders_customer->get_result();
    while ($row = $result_latest_orders_customer->fetch_assoc()) {
        $latest_orders_customer[] = $row;
    }
    $stmt_latest_orders_customer->close();
} else {
    $error .= "Gagal mengambil pesanan terbaru Anda: " . $conn->error . "<br>";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Dashboard Pelanggan SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>Dashboard Saya - SkinGlow!</title>

    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../css/index_admin.css" rel="stylesheet">
    <link href="../vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">

    <link href="../css/dashboard_customer.css" rel="stylesheet"> 
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="sidebar-brand-text mx-3">SkinGlow!</div>
            </a>

            <hr class="sidebar-divider my-0">

            <li class="nav-item active">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                Belanja & Akun
            </div>

            <li class="nav-item">
                <a class="nav-link" href="../user/cart.php">
                    <i class="fas fa-fw fa-shopping-cart"></i>
                    <span>Keranjang Belanja</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="my_orders.php">
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
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Halo, <?php echo $user_name; ?>! Selamat Datang di SkinGlow!</h1>

                    <?php if (!empty($message)): ?>
                        <div id="successNotification" class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-12 mb-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Jelajahi Produk Kami Berdasarkan Kategori</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($categories_with_products)): ?>
                                        <?php foreach ($categories_with_products as $category): ?>
                                            <h5 class="category-heading mt-4"><?php echo htmlspecialchars($category['name']); ?></h5>
                                            <div class="row">
                                                <?php if (!empty($category['products'])): ?>
                                                    <?php foreach ($category['products'] as $product): ?>
                                                        <div class="col-xl-3 col-md-4 col-sm-6 mb-4">
                                                            <div class="product-card">
                                                                <a href="../user/detail_product.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="text-decoration-none d-block">
                                                                    <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                                                </a>
                                                                <div class="product-price">
                                                                    <div class="product-price">
                                                                        Rp <?php echo number_format($product['price'], 0, ',', '.'); ?>
                                                                    </div>
                                                                    <button type="button" 
                                                                            class="btn btn-primary btn-sm add-to-cart-btn mt-2" 
                                                                            data-product-id="<?php echo htmlspecialchars($product['id']); ?>"
                                                                            data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                                        <i class="fas fa-cart-plus"></i> 
                                                                    </button>
                                                                    </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="col-12">
                                                        <p class="text-gray-600">Tidak ada produk di kategori ini.</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <hr class="sidebar-divider my-4">
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-gray-600">Belum ada kategori atau produk yang tersedia untuk ditampilkan.</p>
                                    <?php endif; ?>
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

    <div class="modal fade" id="addToCartSuccessModal" tabindex="-1" role="dialog" aria-labelledby="addToCartSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addToCartSuccessModalLabel">Berhasil Ditambahkan ke Keranjang!</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                Produk <strong id="addedProductName"></strong> berhasil ditambahkan ke keranjang Anda.
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Lanjutkan Belanja</button>
                <a href="cart.php" class="btn btn-pink">Lihat Keranjang</a>
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

    <script>
    // Pastikan Anda menempatkan script ini di bagian bawah dashboard_customer.php,
// sebelum penutup </body> tag, dan setelah jQuery dan Bootstrap JS dimuat.

$(document).ready(function() {
    // Fungsi untuk menambahkan produk ke keranjang
    // Event listener sekarang menargetkan tombol langsung, bukan form
    $(document).on('click', '.add-to-cart-btn', function(e) { 
        e.preventDefault(); // Mencegah tindakan default tombol (jika ada, meskipun type="button" tidak akan me-redirect)

        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name'); // Ambil nama produk dari data attribute pada tombol

        // Debugging logs - sangat direkomendasikan untuk tetap ada saat pengembangan
        console.log('Tombol "Tambah ke Keranjang" diklik!');
        console.log('Product ID:', productId);
        console.log('Product Name:', productName);

        $.ajax({
            url: '../api/add_to_cart.php',
            type: 'POST',
            data: {
                product_id: productId
            },
            dataType: 'json',
            success: function(response) {
                console.log('Respons dari server:', response); // Cek respons di console
                if (response.success) {
                    // Update nama produk di modal
                    $('#addedProductName').text(productName);
                    // Tampilkan modal sukses
                    $('#addToCartSuccessModal').modal('show');
                    // Optional: Perbarui jumlah item di ikon keranjang di navbar jika ada
                    // Contoh: $('#cart-item-count').text(response.cartItemCount);
                } else {
                    alert('Gagal menambahkan produk ke keranjang: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Terjadi kesalahan saat berkomunikasi dengan server: ' + error);
                console.error('AJAX Error:', xhr.responseText); // Lihat detail error di console
            }
        });
    });

    // ... sisa JavaScript dashboard_customer.php Anda ...
});
    </script>
</body>
</html>