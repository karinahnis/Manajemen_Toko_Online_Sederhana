<?php
session_start();

// Aktifkan laporan error PHP untuk membantu melacak masalah
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Periksa apakah user sudah login dan memiliki role 'user'
// Atau biarkan tidak login agar bisa melihat detail produk (tergantung kebutuhan)
// Untuk saat ini, kita akan biarkan user yang belum login pun bisa melihat detail produk,
// tetapi fungsi "add to cart" hanya bisa digunakan oleh user yang sudah login.
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
//     header("Location: ../login.html?error=" . urlencode("Akses tidak diizinkan. Silakan login."));
//     exit();
// }

require_once '../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$message = '';
$error = '';
$product = null;

if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// Pastikan product_id diberikan
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = intval($_GET['id']);

    // Query untuk mengambil detail produk
    $query_product = "
        SELECT 
            p.id, 
            p.name, 
            p.description, 
            p.price, 
            p.stock, 
            p.image_url,
            c.name AS category_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?;
    ";
    $stmt_product = $conn->prepare($query_product);

    if ($stmt_product) {
        $stmt_product->bind_param("i", $product_id);
        $stmt_product->execute();
        $result_product = $stmt_product->get_result();

        if ($result_product->num_rows > 0) {
            $product = $result_product->fetch_assoc();
        } else {
            $error = "Produk tidak ditemukan.";
        }
        $stmt_product->close();
    } else {
        $error = "Gagal menyiapkan statement untuk mengambil produk: " . $conn->error;
    }
} else {
    $error = "ID Produk tidak valid atau tidak diberikan.";
}

$conn->close();

// Mengambil nama pengguna dari sesi untuk navbar.
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Guest');
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'user';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Detail Produk SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>Detail Produk - SkinGlow!</title>

    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../css/index_admin.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/custom_admin.css">
    <link href="../vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="../css/dashboard_customer.css" rel="stylesheet">

    <style>
        .product-detail-container {
            display: flex;
            flex-wrap: wrap; /* Mengizinkan wrap pada layar kecil */
            gap: 30px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .product-image-area {
            flex: 1;
            min-width: 300px; /* Lebar minimum gambar */
            max-width: 500px;
            text-align: center;
        }
        .product-image-area img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .product-info-area {
            flex: 2;
            min-width: 400px; /* Lebar minimum info produk */
        }
        .product-info-area h1 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 10px;
        }
        .product-info-area .category-tag {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 20px;
            display: block;
        }
        .product-info-area .product-price-detail {
            font-size: 2rem;
            color: #e74a3b; /* Warna merah muda */
            font-weight: bold;
            margin-bottom: 20px;
        }
        .product-info-area .stock-info {
            font-size: 1rem;
            color: #5a5c69;
            margin-bottom: 15px;
        }
        .product-info-area .stock-info.out-of-stock {
            color: #e74a3b;
            font-weight: bold;
        }
        .product-info-area .description-heading {
            font-size: 1.2rem;
            color: #333;
            margin-top: 25px;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .product-info-area .product-description {
            font-size: 1rem;
            line-height: 1.6;
            color: #555;
            margin-bottom: 20px;
        }
        .btn-add-to-cart {
            background-color: #8c52ff; /* Warna ungu */
            border-color: #8c52ff;
            color: white;
            padding: 12px 25px;
            font-size: 1.1rem;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .btn-add-to-cart:hover {
            background-color: #6b3cd1; /* Ungu lebih gelap */
            border-color: #6b3cd1;
        }

        /* Modal custom styling */
        .btn-pink {
            background-color: #FF69B4; /* Warna pink */
            border-color: #FF69B4;
            color: white;
        }
        .btn-pink:hover {
            background-color: #FF1493; /* Pink lebih gelap */
            border-color: #FF1493;
        }
    </style>
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
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Belanja & Akun</div>
            <li class="nav-item">
                <a class="nav-link" href="cart.php">
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
                    <h1 class="h3 mb-4 text-gray-800">Detail Produk</h1>

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

                    <?php if ($product): ?>
                        <div class="product-detail-container card shadow mb-4 p-4">
                            <div class="product-image-area">
                                <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                            <div class="product-info-area">
                                <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                                <span class="category-tag">Kategori: <?php echo htmlspecialchars($product['category_name']); ?></span>
                                <div class="product-price-detail">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></div>
                                <div class="stock-info <?php echo ($product['stock'] == 0) ? 'out-of-stock' : ''; ?>">
                                    Stok: <?php echo ($product['stock'] > 0) ? htmlspecialchars($product['stock']) . ' tersedia' : 'Habis'; ?>
                                </div>

                                <div class="description-heading">Deskripsi Produk</div>
                                <p class="product-description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

                                <?php if ($is_logged_in): // Tampilkan tombol jika user login ?>
                                    <button type="button" 
                                            class="btn btn-add-to-cart mt-3" 
                                            data-product-id="<?php echo htmlspecialchars($product['id']); ?>"
                                            data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                            <?php echo ($product['stock'] == 0) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-cart-plus"></i> Tambah ke Keranjang
                                    </button>
                                <?php else: // Tampilkan pesan jika user belum login ?>
                                    <div class="alert alert-info mt-3" role="alert">
                                        Anda harus <a href="../login.html" class="alert-link">login</a> untuk menambahkan produk ini ke keranjang.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            Silakan pilih produk dari dashboard untuk melihat detailnya.
                        </div>
                        <a href="index.php" class="btn btn-primary">Kembali ke Dashboard</a>
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
        $(document).ready(function() {
            // Script untuk notifikasi alert menghilang otomatis
            setTimeout(function() {
                $('#successNotification').alert('close');
            }, 5000); // Notifikasi akan hilang setelah 5 detik

            // Fungsi untuk menambahkan produk ke keranjang via AJAX
            $(document).on('click', '.btn-add-to-cart', function(e) {
                e.preventDefault();

                const productId = $(this).data('product-id');
                const productName = $(this).data('product-name');

                console.log('Adding product to cart:', productId, productName); // Debugging

                $.ajax({
                    url: '../api/add_to_cart.php',
                    type: 'POST',
                    data: {
                        product_id: productId
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Server response:', response); // Debugging
                        if (response.success) {
                            $('#addedProductName').text(productName);
                            $('#addToCartSuccessModal').modal('show');
                            // Anda bisa menambahkan logika untuk memperbarui jumlah item di keranjang di navbar di sini jika ada
                        } else {
                            alert('Gagal menambahkan produk ke keranjang: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Terjadi kesalahan saat berkomunikasi dengan server: ' + error);
                        console.error('AJAX Error:', xhr.responseText); // Detail error
                    }
                });
            });
        });
    </script>
</body>
</html>