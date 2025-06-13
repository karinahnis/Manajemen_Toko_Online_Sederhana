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

$cart_items = [];
$total_cart_price = 0;

$query_cart = "
    SELECT
        ci.id AS cart_item_id,
        ci.product_id,
        ci.quantity,
        p.name AS product_name,
        p.price,
        p.image_url,
        p.stock -- Tambahkan stock untuk validasi
    FROM
        cart_items ci
    JOIN
        products p ON ci.product_id = p.id
    WHERE
        ci.user_id = ?
    ORDER BY
        ci.created_at DESC";

$stmt_cart = $conn->prepare($query_cart);
if ($stmt_cart) {
    $stmt_cart->bind_param("i", $user_id);
    $stmt_cart->execute();
    $result_cart = $stmt_cart->get_result();
    while ($row = $result_cart->fetch_assoc()) {
        $cart_items[] = $row;
        $total_cart_price += ($row['quantity'] * $row['price']);
    }
    $stmt_cart->close();
} else {
    // Handle error jika query gagal
    error_log("Error preparing cart query: " . $conn->error);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Keranjang Belanja SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>Keranjang Belanja - SkinGlow!</title>

    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../css/index_admin.css" rel="stylesheet">
    <link href="../css/dashboard_customer.css" rel="stylesheet">
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
            <li class="nav-item active">
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
                    <h1 class="h3 mb-4 text-gray-800">Keranjang Belanja Anda</h1>

                    <?php if (isset($_GET['message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_GET['message']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_GET['error']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 cart-header-custom">
                            <h6 class="m-0 font-weight-bold">Item di Keranjang Anda</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($cart_items)): ?>
                                <p class="text-center text-gray-600">Keranjang belanja Anda kosong.</p>
                            <?php else: ?>
                                <div id="cartItemsContainer">
                                    <?php foreach ($cart_items as $item): ?>
                                        <div class="cart-item" data-product-id="<?php echo htmlspecialchars($item['product_id']); ?>" data-product-stock="<?php echo htmlspecialchars($item['stock']); ?>">
                                            <input type="checkbox" class="cart-item-checkbox" checked data-price="<?php echo htmlspecialchars($item['price']); ?>">
                                            <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="cart-item-image">
                                            <div class="cart-item-details">
                                                <div class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                <div class="cart-item-price">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></div>
                                            </div>
                                            <div class="cart-item-actions">
                                                <div class="quantity-control">
                                                    <button type="button" class="btn btn-sm decrease-quantity">-</button>
                                                    <input type="number" class="form-control form-control-sm quantity-input" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="1" data-product-id="<?php echo htmlspecialchars($item['product_id']); ?>">
                                                    <button type="button" class="btn btn-sm increase-quantity">+</button>
                                                </div>
                                                <div class="cart-item-total-price">
                                                    Rp <span class="item-total" data-raw-total="<?php echo ($item['quantity'] * $item['price']); ?>"><?php echo number_format(($item['quantity'] * $item['price']), 0, ',', '.'); ?></span>
                                                </div>
                                                <button type="button" class="btn btn-danger btn-sm remove-item" data-product-id="<?php echo htmlspecialchars($item['product_id']); ?>">Hapus</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="cart-action-buttons">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="selectAllItems">
                                        <label class="form-check-label" for="selectAllItems">Pilih Semua (<span id="selectedItemCount">0</span>)</label>
                                    </div>
                                    <button type="button" class="btn btn-danger btn-pink" id="deleteSelectedBtn">Hapus</button>
                                    <div class="cart-summary">
                                        Total (<span id="totalProductCount"><?php echo count($cart_items); ?> produk</span>): <span class="total-amount" id="grandTotalPrice" data-raw-total="<?php echo $total_cart_price; ?>">Rp <?php echo number_format($total_cart_price, 0, ',', '.'); ?></span>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-pink" id="checkoutBtn">Checkout</button>
                                </div>
                            <?php endif; ?>
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
                    <a class="btn btn-primary" href="../logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmModalLabel"
    aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Konfirmasi Penghapusan</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Apakah Anda yakin ingin menghapus produk ini dari keranjang?</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
                    <button class="btn btn-danger" id="confirmDeleteBtn">Hapus</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteAllConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteAllConfirmModalLabel"
    aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteAllConfirmModalLabel">Konfirmasi Penghapusan Item Terpilih</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Apakah Anda yakin ingin menghapus semua item yang dipilih dari keranjang?</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
                    <button class="btn btn-danger" id="confirmDeleteAllBtn">Hapus Semua</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../vendor/jquery/jquery.min.js"></script>
    <script src="../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../js/sb-admin-2.min.js"></script>

    <script>
    $(document).ready(function() {
        let itemToRemove = null; // Variabel untuk menyimpan item yang akan dihapus
        let selectedItemsToRemove = []; // Variabel untuk menyimpan item yang akan dihapus semua

        function formatRupiah(number) {
            return 'Rp ' + number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function updateCartSummary() {
            let totalProducts = 0;
            let grandTotal = 0;
            $('.cart-item-checkbox:checked').each(function() {
                const $item = $(this).closest('.cart-item');
                const quantity = parseInt($item.find('.quantity-input').val());
                const unitPrice = parseFloat($(this).data('price'));
                grandTotal += (quantity * unitPrice);
                totalProducts++;
            });

            $('#selectedItemCount').text(totalProducts);
            $('#totalProductCount').text(totalProducts + ' produk');
            $('#grandTotalPrice').text(formatRupiah(grandTotal));

            const totalCheckboxes = $('.cart-item-checkbox').length;
            const checkedCheckboxes = $('.cart-item-checkbox:checked').length;
            $('#selectAllItems').prop('checked', totalCheckboxes === checkedCheckboxes && totalCheckboxes > 0);

            if (checkedCheckboxes > 0) {
                $('#deleteSelectedBtn, #checkoutBtn').prop('disabled', false);
            } else {
                $('#deleteSelectedBtn, #checkoutBtn').prop('disabled', true);
            }
        }

        // Initial update on page load
        updateCartSummary();

        // Quantity Control
        $(document).on('click', '.increase-quantity', function() {
            const $input = $(this).siblings('.quantity-input');
            let quantity = parseInt($input.val());
            const $item = $(this).closest('.cart-item');
            const productStock = parseInt($item.data('product-stock'));

            if (quantity < productStock) { // Cek apakah kuantitas masih di bawah stok
                quantity++;
                $input.val(quantity);
                updateItemAndCartTotal($item, quantity);
            } else {
                alert('Kuantitas maksimum telah tercapai (stok tersedia: ' + productStock + ').');
            }
        });

        $(document).on('click', '.decrease-quantity', function() {
            const $input = $(this).siblings('.quantity-input');
            let quantity = parseInt($input.val());
            const $item = $(this).closest('.cart-item');

            if (quantity > 1) {
                quantity--;
                $input.val(quantity);
                updateItemAndCartTotal($item, quantity);
            } else {
                itemToRemove = $(this).closest('.cart-item');
                $('#deleteConfirmModal').modal('show');
            }
        });

        $(document).on('change', '.quantity-input', function() {
            let quantity = parseInt($(this).val());
            const $item = $(this).closest('.cart-item');
            const productStock = parseInt($item.data('product-stock'));

            if (isNaN(quantity) || quantity < 1) {
                quantity = 1;
                $(this).val(quantity);
            } else if (quantity > productStock) {
                alert('Kuantitas melebihi stok yang tersedia (' + productStock + '). Mengatur kuantitas ke stok maksimal.');
                quantity = productStock;
                $(this).val(quantity);
            }
            updateItemAndCartTotal($item, quantity);
        });

        function updateItemAndCartTotal($item, newQuantity) {
            const productId = $item.data('product-id');
            const unitPrice = parseFloat($item.find('.cart-item-checkbox').data('price'));
            const $itemTotalPriceSpan = $item.find('.item-total');

            $.ajax({
                url: '../api/update_cart_quantity.php',
                type: 'POST',
                data: {
                    product_id: productId,
                    quantity: newQuantity
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $itemTotalPriceSpan.text(formatRupiah(newQuantity * unitPrice));
                        updateCartSummary();
                    } else {
                        alert('Gagal memperbarui kuantitas: ' + response.message);
                        // Revert quantity if update failed
                        // You might need to fetch the current quantity from the server
                        // or store the initial quantity on page load and revert to that.
                        // For simplicity, for now, we'll just alert the user.
                    }
                },
                error: function(xhr, status, error) {
                    alert('Terjadi kesalahan saat berkomunikasi dengan server: ' + error);
                }
            });
        }

        $(document).on('click', '.remove-item', function() {
            itemToRemove = $(this).closest('.cart-item');
            $('#deleteConfirmModal').modal('show');
        });

        $('#confirmDeleteBtn').on('click', function() {
            if (itemToRemove) {
                removeItemFromCart(itemToRemove);
                $('#deleteConfirmModal').modal('hide');
                itemToRemove = null;
            }
        });

        function removeItemFromCart($item) {
            const productId = $item.data('product-id');
            $.ajax({
                url: '../api/remove_from_cart.php',
                type: 'POST',
                data: {
                    product_ids: [productId]
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $item.fadeOut(300, function() {
                            $(this).remove();
                            updateCartSummary();
                            if ($('.cart-item').length === 0) {
                                $('#cartItemsContainer').html('<div class="alert alert-info text-center" role="alert">Keranjang Anda kosong. Yuk, mulai belanja!<a href="dashboard_customer.php" class="alert-link">Lihat Produk</a></div>');
                            }
                        });
                    } else {
                        alert('Gagal menghapus produk: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Terjadi kesalahan saat berkomunikasi dengan server: ' + error);
                }
            });
        }

        $('#selectAllItems').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('.cart-item-checkbox').prop('checked', isChecked);
            updateCartSummary();
        });

        $(document).on('change', '.cart-item-checkbox', function() {
            updateCartSummary();
        });

        $('#deleteSelectedBtn').on('click', function() {
            selectedItemsToRemove = [];
            $('.cart-item-checkbox:checked').each(function() {
                selectedItemsToRemove.push($(this).closest('.cart-item').data('product-id'));
            });

            if (selectedItemsToRemove.length === 0) {
                alert('Tidak ada item yang dipilih untuk dihapus.');
                return;
            }

            $('#deleteAllConfirmModal').modal('show');
        });

        $('#confirmDeleteAllBtn').on('click', function() {
            if (selectedItemsToRemove.length > 0) {
                $.ajax({
                    url: '../api/remove_from_cart.php',
                    type: 'POST',
                    data: {
                        product_ids: selectedItemsToRemove
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            selectedItemsToRemove.forEach(function(productId) {
                                $(`.cart-item[data-product-id="${productId}"]`).fadeOut(300, function() {
                                    $(this).remove();
                                });
                            });
                            setTimeout(function() {
                                updateCartSummary();
                                if ($('.cart-item').length === 0) {
                                    $('#cartItemsContainer').html('<div class="alert alert-info text-center" role="alert">Keranjang Anda kosong. Yuk, mulai belanja!<a href="dashboard_customer.php" class="alert-link">Lihat Produk</a></div>');
                                }
                            }, 350);
                            $('#deleteAllConfirmModal').modal('hide');
                        } else {
                            alert('Gagal menghapus item: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Terjadi kesalahan saat berkomunikasi dengan server: ' + error);
                    }
                });
            }
        });

        $('#checkoutBtn').on('click', function() {
            const selectedCartItems = [];
            $('.cart-item-checkbox:checked').each(function() {
                const $item = $(this).closest('.cart-item');
                selectedCartItems.push({
                    product_id: $item.data('product-id'),
                    quantity: parseInt($item.find('.quantity-input').val()),
                    price: parseFloat($(this).data('price'))
                });
            });

            if (selectedCartItems.length === 0) {
                alert('Silakan pilih setidaknya satu item untuk checkout.');
                return;
            }

            const checkoutData = JSON.stringify(selectedCartItems);
            // Redirect ke halaman checkout.php dengan data item yang dipilih
            window.location.href = 'checkout.php?items=' + encodeURIComponent(checkoutData);
        });
    });
    </script>
</body>
</html>

<?php
// PHP code for update_cart_quantity.php (as an internal API handler)
if (isset($_POST['action']) && $_POST['action'] === 'update_quantity') {
    // This block would ideally be in a separate file like ../api/update_cart_quantity.php
    // For demonstration, it's included here.
    
    // Re-establish database connection if needed (if this block runs independently)
    // This is typically not how you'd structure an API call from the same file.
    // The AJAX call goes to a *separate* PHP file.
    
    // For now, let's assume this block is purely illustrative of what the separate API file contains.
    // In a real scenario, the AJAX call in the <script> above would target ../api/update_cart_quantity.php
    
    // This section is commented out because it's meant to be in a separate file.
    /*
    $conn_api = get_db_connection(); // Ensure connection is established for API logic
    
    $user_id_api = $_SESSION['user_id'];
    $product_id_api = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity_api = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

    if ($product_id_api <= 0 || $quantity_api <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
        exit();
    }

    $stmt_check_api = $conn_api->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt_check_api->bind_param("i", $product_id_api);
    $stmt_check_api->execute();
    $result_check_api = $stmt_check_api->get_result();
    $product_api = $result_check_api->fetch_assoc();
    $stmt_check_api->close();

    if (!$product_api) {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan.']);
        $conn_api->close();
        exit();
    }

    if ($quantity_api > $product_api['stock']) {
        echo json_encode(['success' => false, 'message' => 'Kuantitas melebihi stok yang tersedia.']);
        $conn_api->close();
        exit();
    }

    $query_api = "UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?";
    $stmt_api = $conn_api->prepare($query_api);

    if ($stmt_api) {
        $stmt_api->bind_param("iii", $quantity_api, $user_id_api, $product_id_api);
        if ($stmt_api->execute()) {
            echo json_encode(['success' => true, 'message' => 'Kuantitas berhasil diperbarui.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui kuantitas: ' . $stmt_api->error]);
        }
        $stmt_api->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan statement: ' . $conn_api->error]);
    }

    $conn_api->close();
    exit(); // Exit after JSON output
    */
}

// PHP code for remove_from_cart.php (as an internal API handler)
if (isset($_POST['action']) && $_POST['action'] === 'remove_items') {
    // This block would also be in a separate file like ../api/remove_from_cart.php
    
    // For now, it's commented out for the same reasons as above.
    /*
    $conn_api = get_db_connection(); // Ensure connection is established for API logic
    
    $user_id_api = $_SESSION['user_id'];
    $product_ids_api = isset($_POST['product_ids']) ? $_POST['product_ids'] : [];

    if (empty($product_ids_api)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada produk yang dipilih untuk dihapus.']);
        exit();
    }

    $product_ids_cleaned_api = array_map('intval', $product_ids_api);
    $placeholders_api = implode(',', array_fill(0, count($product_ids_cleaned_api), '?'));

    $query_api = "DELETE FROM cart_items WHERE user_id = ? AND product_id IN ($placeholders_api)";
    $stmt_api = $conn_api->prepare($query_api);

    if ($stmt_api) {
        $types_api = str_repeat('i', count($product_ids_cleaned_api) + 1);
        $params_api = array_merge([$user_id_api], $product_ids_cleaned_api);

        $stmt_api->bind_param($types_api, ...$params_api);

        if ($stmt_api->execute()) {
            echo json_encode(['success' => true, 'message' => 'Produk berhasil dihapus dari keranjang.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus produk: ' . $stmt_api->error]);
        }
        $stmt_api->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan statement: ' . $conn_api->error]);
    }

    $conn_api->close();
    exit(); // Exit after JSON output
    */
}
?>