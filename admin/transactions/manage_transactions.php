<?php

require_once '../../config/database.php'; // Pastikan path ini benar (dari admin/transactions/ ke root/config)

$conn = get_db_connection();

$message = '';
$error = '';

// --- Ambil daftar kategori untuk dropdown ---
$categories = [];
$categories_query = "SELECT id, name FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_query);
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    error_log("Failed to load categories: " . $conn->error); // Log error
}

// Logika POST untuk memproses checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_checkout') {
    $total_bill = (float)$_POST['total_bill'];
    $customer_payment_amount = (float)$_POST['customer_payment_amount'];
    $payment_method = $_POST['payment_method'] ?? 'Cash'; // Ambil metode pembayaran dari form
    $cart_items_json = $_POST['cart_items'];
    $cart_items = json_decode($cart_items_json, true);

    if (empty($cart_items)) {
        $error = "Keranjang belanja kosong. Harap tambahkan produk.";
    } elseif ($customer_payment_amount < $total_bill) {
        $error = "Jumlah uang dari pelanggan kurang dari total tagihan.";
    } else {
        $conn->begin_transaction();
        try {
            $user_id_for_order = null; 
            
            // Query INSERT sudah benar tanpa customer_name_manual
            $stmt_order = $conn->prepare("INSERT INTO orders (user_id, order_date, total_amount, status, payment_method) VALUES (?, NOW(), ?, ?, ?)");
            $status_completed = 'completed'; 
            // bind_param juga sudah benar (idss)
            if (!$stmt_order->bind_param("idss", $user_id_for_order, $total_bill, $status_completed, $payment_method)) {
                 throw new Exception("Gagal bind parameter pesanan: " . $stmt_order->error);
            }

            if (!$stmt_order->execute()) {
                throw new Exception("Gagal menyimpan pesanan: " . $stmt_order->error);
            }
            $order_id = $conn->insert_id;

            $stmt_order_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_order) VALUES (?, ?, ?, ?)");
            $stmt_update_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

            foreach ($cart_items as $item) {
                $product_id = (int)$item['id'];
                $quantity = (int)$item['quantity'];
                $price_at_order = (float)$item['price'];

                if (!$stmt_order_item->bind_param("iiid", $order_id, $product_id, $quantity, $price_at_order)) {
                    throw new Exception("Gagal bind parameter item pesanan: " . $stmt_order_item->error);
                }
                if (!$stmt_order_item->execute()) {
                    throw new Exception("Gagal menyimpan item pesanan: " . $stmt_order_item->error);
                }

                if (!$stmt_update_stock->bind_param("iii", $quantity, $product_id, $quantity)) {
                    throw new Exception("Gagal bind parameter update stok: " . $stmt_update_stock->error);
                }
                if (!$stmt_update_stock->execute()) {
                    throw new Exception("Gagal memperbarui stok produk: " . $stmt_update_stock->error);
                }
                if ($stmt_update_stock->affected_rows === 0) {
                     throw new Exception("Stok tidak cukup untuk produk: " . htmlspecialchars($item['name']) . " atau produk tidak ditemukan.");
                }
            }

            $conn->commit();
            $change_amount = $customer_payment_amount - $total_bill; 
            $message = "Transaksi berhasil diproses! Kembalian: Rp " . number_format($change_amount, 0, ',', '.');
            
            // --- Perubahan di sini: Redirect ke manage_orders.php ---
            header("Location: ../orders/manage_orders.php?message=" . urlencode($message) . "&status=success");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Gagal memproses transaksi: " . $e->getMessage();
            // --- Perubahan di sini: Redirect ke manage_orders.php dengan pesan error ---
            header("Location: ../orders/manage_orders.php?error=" . urlencode($error) . "&status=error");
            exit();
        } finally {
            if (isset($stmt_order)) $stmt_order->close();
            if (isset($stmt_order_item)) $stmt_order_item->close();
            if (isset($stmt_update_stock)) $stmt_update_stock->close();
        }
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
    <meta name="description" content="Proses Transaksi/Checkout SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>SkinGlow! - Proses Checkout</title>

    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../css/index_admin.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/custom_admin.css">

    <style>
        .product-item {
            cursor: pointer;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #fff;
            transition: background-color 0.2s;
        }
        .product-item:hover {
            background-color: #f8f9fc;
        }
        .product-item.selected {
            background-color: #e0f2f7; /* Light blue */
            border-color: #007bff;
        }
        .product-item.out-of-stock {
            background-color: #fdd; /* Light red */
            color: #666;
            cursor: not-allowed;
        }
        .product-item.out-of-stock .add-to-cart-btn {
            background-color: #ccc;
            border-color: #ccc;
            cursor: not-allowed;
        }
        #cartTable tbody tr td {
            vertical-align: middle;
        }
        #cartTable tbody tr td input[type="number"] {
            width: 70px;
            text-align: center;
        }
        .form-control-static {
            font-weight: bold;
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="../index.php">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="sidebar-brand-text mx-3">SkinGlow! Admin</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Manajemen E-commerce</div>
            <li class="nav-item">
                <a class="nav-link" href="../products/manage_products.php">
                    <i class="fas fa-fw fa-box"></i>
                    <span>Produk</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../categories/manage_categories.php">
                    <i class="fas fa-fw fa-tags"></i>
                    <span>Kategori Produk</span>
                </a>
            </li>
             <li class="nav-item">
                <a class="nav-link" href="../orders/manage_orders.php">
                    <i class="fas fa-fw fa-shopping-cart"></i>
                    <span>Pesanan</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Manajemen Keuangan</div>
            <li class="nav-item active">
                <a class="nav-link" href="manage_transactions.php">
                    <i class="fas fa-fw fa-cash-register"></i>
                    <span>Transaksi</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Laporan & Statistik</div>
            <li class="nav-item">
                <a class="nav-link" href="../sales_report.php">
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
                    <h1 class="h3 mb-4 text-gray-800">Proses Checkout (Kasir)</h1>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Pilih Produk</h6>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="categoryFilter">Filter Berdasarkan Kategori:</label>
                                        <select class="form-control" id="categoryFilter">
                                            <option value="">Semua Kategori</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat['id']); ?>">
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="productSearch">Cari Produk (Nama):</label>
                                        <input type="text" id="productSearch" class="form-control" placeholder="Cari nama produk...">
                                    </div>
                                    <div id="productList" style="max-height: 400px; overflow-y: auto; border: 1px solid #eee; padding: 10px;">
                                        <p class="text-center text-muted">Pilih kategori atau ketik nama produk...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Keranjang Belanja</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="cartTable" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>Produk</th>
                                                    <th>Harga</th>
                                                    <th>Qty</th>
                                                    <th>Subtotal</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="cartItems">
                                                <tr><td colspan="5" class="text-center text-muted">Keranjang kosong.</td></tr>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3" class="text-right"><strong>Total Belanja:</strong></td>
                                                    <td colspan="2"><strong id="totalBill">Rp 0</strong></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <hr>

                                    <form id="checkoutForm" action="manage_transactions.php" method="POST">
                                        <input type="hidden" name="action" value="process_checkout">
                                        <input type="hidden" name="total_bill" id="hiddenTotalBill">
                                        <input type="hidden" name="cart_items" id="hiddenCartItems">
                                        
                                        <div class="form-group">
                                            <label>Total Belanja:</label>
                                            <p class="form-control-static"><strong id="displayTotalBill">Rp 0</strong></p>
                                        </div>

                                        <div class="form-group">
                                            <label for="paymentMethod">Metode Pembayaran:</label>
                                            <select class="form-control" id="paymentMethod" name="payment_method" disabled>
                                                <option value="Cash" selected>Cash</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="customerPaymentAmount">Jumlah Uang dari Pelanggan (Rp):</label>
                                            <input type="number" class="form-control" id="customerPaymentAmount" name="customer_payment_amount" step="any" min="0" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Kembalian:</label>
                                            <p class="form-control-static"><strong id="changeAmount">Rp 0</strong></p>
                                        </div>

                                        <button type="submit" class="btn btn-primary btn-block" id="processCheckoutBtn" disabled>Proses Checkout</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; SkinGlow! 2024</span>
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
            let cart = [];
            let products = [];

            function loadProducts() {
                const searchQuery = $('#productSearch').val();
                const categoryId = $('#categoryFilter').val();

                $.ajax({
                    url: '../../api/get_products.php',
                    method: 'GET',
                    data: {
                        search: searchQuery,
                        category_id: categoryId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            products = response.data;
                            displayProducts(products);
                        } else {
                            $('#productList').html('<p class="text-danger">Gagal memuat produk: ' + response.message + '</p>');
                            console.error("API Error: ", response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMessage = 'Terjadi kesalahan saat memuat produk.';
                        if (xhr.status === 404) {
                            errorMessage += ' File API (get_products.php) tidak ditemukan. Periksa path.';
                        } else if (xhr.status === 403) {
                            errorMessage += ' Akses ditolak. Pastikan Anda login sebagai admin.';
                        } else {
                            errorMessage += ' Respons: ' + xhr.responseText;
                        }
                        $('#productList').html('<p class="text-danger">' + errorMessage + '</p>');
                        console.error("AJAX Error: ", status, error, xhr.responseText);
                    }
                });
            }

            function displayProducts(productsToDisplay) {
                let html = '';
                if (productsToDisplay.length > 0) {
                    productsToDisplay.forEach(product => {
                        const inCart = cart.some(item => item.id === product.id);
                        const selectedClass = inCart ? 'selected' : '';
                        const outOfStockClass = product.stock <= 0 ? 'out-of-stock' : '';
                        const isDisabled = product.stock <= 0 ? 'disabled' : '';

                        html += `
                            <div class="product-item d-flex justify-content-between align-items-center ${selectedClass} ${outOfStockClass}" data-id="${product.id}" data-name="${product.name}" data-price="${product.price}" data-stock="${product.stock}">
                                <div>
                                    <strong>${product.name}</strong><br>
                                    Harga: Rp ${number_format(product.price)} | Stok: ${product.stock}
                                </div>
                                <button class="btn btn-sm btn-primary add-to-cart-btn" data-id="${product.id}" ${isDisabled}>
                                    ${product.stock <= 0 ? 'Stok Habis' : '<i class="fas fa-plus"></i> Tambah'}
                                </button>
                            </div>
                        `;
                    });
                } else {
                    html = '<p class="text-center text-muted">Tidak ada produk ditemukan untuk kriteria ini.</p>';
                }
                $('#productList').html(html);
            }

            function addToCart(productId) {
                const product = products.find(p => p.id == productId);
                if (!product) return;

                const existingItem = cart.find(item => item.id === productId);

                if (existingItem) {
                    if (existingItem.quantity < product.stock) {
                        existingItem.quantity++;
                    } else {
                        alert('Stok produk "' + product.name + '" tidak mencukupi.');
                        return;
                    }
                } else {
                    if (product.stock > 0) {
                        cart.push({
                            id: product.id,
                            name: product.name,
                            price: parseFloat(product.price),
                            quantity: 1,
                            stock: product.stock
                        });
                    } else {
                        alert('Stok produk "' + product.name + '" sudah habis.');
                        return;
                    }
                }
                updateCartDisplay();
            }

            function updateCartDisplay() {
                let html = '';
                let totalBill = 0;

                if (cart.length > 0) {
                    cart.forEach(item => {
                        const subtotal = item.price * item.quantity;
                        totalBill += subtotal;
                        html += `
                            <tr id="cart-item-${item.id}">
                                <td>${item.name}</td>
                                <td>Rp ${number_format(item.price)}</td>
                                <td>
                                    <input type="number" class="form-control form-control-sm item-qty" data-id="${item.id}" value="${item.quantity}" min="1" max="${item.stock}">
                                </td>
                                <td>Rp ${number_format(subtotal)}</td>
                                <td>
                                    <button class="btn btn-danger btn-sm remove-from-cart-btn" data-id="${item.id}"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = '<tr><td colspan="5" class="text-center text-muted">Keranjang kosong.</td></tr>';
                }
                $('#cartItems').html(html);
                $('#totalBill').text('Rp ' + number_format(totalBill));
                $('#displayTotalBill').text('Rp ' + number_format(totalBill));
                $('#hiddenTotalBill').val(totalBill);
                $('#hiddenCartItems').val(JSON.stringify(cart));

                // Otomatis isi Jumlah Uang dari Pelanggan dengan Total Belanja
                $('#customerPaymentAmount').val(totalBill); 
                calculateChange();
                updateCheckoutButtonState(totalBill);
                updateProductListSelectedState();
            }

            function updateProductListSelectedState() {
                $('.product-item').removeClass('selected');
                cart.forEach(item => {
                    $(`.product-item[data-id="${item.id}"]`).addClass('selected');
                });
            }

            function removeFromCart(productId) {
                cart = cart.filter(item => item.id !== productId);
                updateCartDisplay();
            }

            function calculateChange() {
                const totalBill = parseFloat($('#hiddenTotalBill').val() || 0);
                const customerPaymentAmount = parseFloat($('#customerPaymentAmount').val() || 0);
                const change = customerPaymentAmount - totalBill;
                $('#changeAmount').text('Rp ' + number_format(change > 0 ? change : 0));
                updateCheckoutButtonState(totalBill);
            }

            function updateCheckoutButtonState(totalBill) {
                const customerPaymentAmount = parseFloat($('#customerPaymentAmount').val() || 0);
                if (totalBill > 0 && customerPaymentAmount >= totalBill) {
                    $('#processCheckoutBtn').prop('disabled', false);
                } else {
                    $('#processCheckoutBtn').prop('disabled', true);
                }
            }

            function number_format(number, decimals = 0, dec_point = ',', thousands_sep = '.') {
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

            $('#productSearch').on('keyup', function() {
                loadProducts();
            });

            $('#categoryFilter').on('change', function() {
                loadProducts();
            });

            $(document).on('click', '.product-item .add-to-cart-btn', function() {
                if ($(this).is(':disabled')) {
                    return;
                }
                const productId = $(this).data('id');
                addToCart(productId);
            });

            $(document).on('change', '.item-qty', function() {
                const productId = $(this).data('id');
                let newQty = parseInt($(this).val());
                const productStock = parseInt($(this).attr('max'));

                if (isNaN(newQty) || newQty < 1) {
                    newQty = 1;
                    $(this).val(newQty);
                }
                if (newQty > productStock) {
                    alert('Kuantitas tidak boleh melebihi stok yang tersedia (' + productStock + ').');
                    newQty = productStock;
                    $(this).val(newQty);
                }

                const itemIndex = cart.findIndex(item => item.id === productId);
                if (itemIndex > -1) {
                    cart[itemIndex].quantity = newQty;
                    updateCartDisplay();
                }
            });

            $(document).on('click', '.remove-from-cart-btn', function() {
                const productId = $(this).data('id');
                removeFromCart(productId);
            });

            $('#customerPaymentAmount').on('input', calculateChange);

            loadProducts();
            updateCartDisplay();
        });
    </script>
</body>
</html>