<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'connection.php';
require_once 'connectionl.php';
require_once 'connectione.php'; // Conexión para ebooks
require_once 'connectionw.php'; // Nueva conexión para webinars

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die("Sesión no válida. Inicia sesión primero.");
}

$user_id = $_SESSION['user_id'];
$db = new Database();     // Mercancía
$db2 = new Database2();   // Libros físicos
$db3 = new Database3();   // Ebooks
$db4 = new DatabaseW();   // Webinars

// Obtener IDs de los carritos
$carrito_id = $db->getOrCreateUserCart($user_id);
$carrito_libros_id = $db2->getOrCreateUserCart($user_id);
$carrito_ebooks_id = $db3->getOrCreateUserCart($user_id);
$carrito_webinars_id = $db4->getOrCreateUserCart($user_id); // Nuevo carrito webinars

// Obtener productos de todos los carritos
$mercancia = $db->getCartItems($carrito_id);
$libros = $db2->getCartItems($user_id);
$ebooks = $db3->getCartItems($user_id);
$webinars = $db4->getCartItems($user_id); // Items de webinars

$total_general = 0;
?>

<!DOCTYPE html>
<html lang="es"
    class="<?php echo isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true' ? 'dark-mode' : ''; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        /* Estilos generales */
        body {
            transition: background-color 0.3s, color 0.3s;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .cart-section {
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }

        .cart-header {
            border-bottom: 2px solid;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .cart-header i {
            margin-right: 10px;
            font-size: 1.8rem;
        }

        .category-header {
            font-weight: 600;
            padding: 10px 0;
            margin: 25px 0 15px 0;
            border-left: 5px solid;
            padding-left: 15px;
        }

        .btn {
            border-radius: 5px;
            font-weight: 500;
            padding: 8px 16px;
            transition: all 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .qty-input {
            max-width: 70px;
            text-align: center;
            border-radius: 4px;
        }

        .total-section {
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .payment-options {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .empty-cart {
            text-align: center;
            padding: 40px 0;
        }

        .empty-cart i {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.6;
        }

        /* Modo claro */
        :root {
            --light-bg: #f8f9fa;
            --light-text: #212529;
            --light-card: #ffffff;
            --light-border: #dee2e6;
            --light-highlight: #007bff;
            --light-header-bg: #e9ecef;
            --light-total-bg: #f1f8ff;
        }

        body {
            background-color: var(--light-bg);
            color: var(--light-text);
        }

        .cart-section {
            background-color: var(--light-card);
            border: 1px solid var(--light-border);
        }

        .cart-header {
            border-color: var(--light-border);
            color: var(--light-text);
        }

        .category-header {
            border-color: var(--light-highlight);
            color: #495057;
        }

        .table {
            color: var(--light-text);
        }

        .table thead {
            background-color: var(--light-header-bg);
        }

        .total-section {
            background-color: var(--light-total-bg);
            border: 1px solid #cce5ff;
        }

        /* Modo oscuro */
        .dark-mode {
            --dark-bg: #121212;
            --dark-text: #ffffff;
            --dark-text-secondary: #cccccc;
            --dark-card: #1e1e1e;
            --dark-border: #444444;
            --dark-highlight: #0d6efd;
            --dark-header-bg: #2c2c2c;
            --dark-total-bg: #252941;
            --dark-table-row-odd: #282828;
            --dark-table-row-even: #1e1e1e;
            --dark-table-header: #333333;
        }

        body.dark-mode {
            background-color: var(--dark-bg);
            color: var(--dark-text);
        }

        .dark-mode .cart-section {
            background-color: var(--dark-card);
            border-color: var(--dark-border);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.4);
        }

        .dark-mode .cart-header {
            border-color: var(--dark-border);
            color: var(--dark-text);
        }

        .dark-mode .category-header {
            border-color: var(--dark-highlight);
            color: #a8b9d0;
        }

        body.dark-mode td {
            color: #f1f1f1 !important;
        }

        body.dark-mode .table {
            color: #f1f1f1;
        }

        .dark-mode .table {
            color: var(--dark-text);
        }

        .dark-mode .table thead th {
            background-color: var(--dark-table-header);
            color: #ffffff;
            border-bottom: 2px solid var(--dark-border);
        }

        .dark-mode .table-striped>tbody>tr:nth-of-type(odd) {
            background-color: var(--dark-table-row-odd);
            color: var(--dark-text);
        }

        .dark-mode .table-striped>tbody>tr:nth-of-type(even) {
            background-color: var(--dark-table-row-even);
            color: var(--dark-text);
        }

        .dark-mode .table td {
            border-color: var(--dark-border);
        }

        .dark-mode .total-section {
            background-color: var(--dark-total-bg);
            border-color: #1a1f3d;
            color: white;
        }

        .dark-mode .form-control {
            background-color: #2c2c2c;
            border-color: #444;
            color: #e0e0e0;
        }

        .dark-mode .empty-cart {
            color: #cccccc;
        }

        /* Botones personalizados para ambos modos */
        .btn-update {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }

        .dark-mode .btn-update {
            background-color: #157347;
            border-color: #146c43;
        }

        .btn-remove {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .dark-mode .btn-remove {
            background-color: #b02a37;
            border-color: #a52834;
        }

        .btn-oxxo {
            background-color: #fbce07;
            border-color: #f1c40f;
            color: #333;
        }

        .dark-mode .btn-oxxo {
            background-color: #e6b800;
            border-color: #d9ae00;
            color: #222;
        }

        .btn-transfer {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }

        .dark-mode .btn-transfer {
            background-color: #0f6674;
            border-color: #0c5460;
        }

        /* Clase para asegurar el color de texto correcto según el modo */
        .product-text {
            color: var(--light-text);
        }

        .dark-mode .product-text {
            color: var(--dark-text);
        }

        /* Estilos específicos para webinars */
        .webinar-date {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .dark-mode .webinar-date {
            color: #adb5bd;
        }

        @media (max-width: 767px) {
            .table-responsive {
                overflow-x: auto;
            }

            .payment-options {
                justify-content: center;
            }

            .total-section h4 {
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="cart-section">
            <div class="cart-header">
                <i class="fas fa-shopping-cart"></i>
                <h2 class="mb-0">Carrito de Compras</h2>
            </div>

            <?php if (empty($mercancia) && empty($libros) && empty($ebooks) && empty($webinars)): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <h4>Tu carrito está vacío</h4>
                    <p>Agrega productos a tu carrito para continuar con la compra.</p>
                </div>
            <?php else: ?>

                <?php if (!empty($mercancia)): ?>
                    <div class="category-header">
                        <i class="fas fa-tshirt me-2"></i>Mercancía
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mercancia as $item): ?>
                                    <tr>
                                        <td class="product-text"><?= htmlspecialchars($item['nombre']) ?></td>
                                        <td class="product-text">$<?= number_format($item['precio'], 2) ?></td>
                                        <td>
                                            <form method="post" action="procesarCarrito.php" class="d-flex align-items-center">
                                                <input type="hidden" name="product_id" value="<?= $item['producto_id'] ?>">
                                                <input type="hidden" name="tipo" value="mercancia">
                                                <input type="number" name="quantity" value="<?= $item['cantidad'] ?>" min="1"
                                                    class="form-control form-control-sm qty-input me-2">
                                                <button type="submit" name="update_cart" class="btn btn-sm btn-update">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="product-text">$<?= number_format($item['precio'] * $item['cantidad'], 2) ?></td>
                                        <td>
                                            <form method="post" action="procesarCarrito.php">
                                                <input type="hidden" name="product_id" value="<?= $item['producto_id'] ?>">
                                                <input type="hidden" name="tipo" value="mercancia">
                                                <button type="submit" name="remove_item" class="btn btn-sm btn-remove">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php $total_general += $item['precio'] * $item['cantidad']; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!empty($libros)): ?>
                    <div class="category-header">
                        <i class="fas fa-book me-2"></i>Libros
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Libro</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($libros as $item): ?>
                                    <tr>
                                        <td class="product-text"><?= htmlspecialchars($item['nombre']) ?></td>
                                        <td class="product-text">$<?= number_format($item['precio'], 2) ?></td>
                                        <td>
                                            <form method="post" action="procesarCarrito.php" class="d-flex align-items-center">
                                                <input type="hidden" name="product_id" value="<?= $item['producto_id'] ?>">
                                                <input type="hidden" name="tipo" value="libros">
                                                <input type="number" name="quantity" value="<?= $item['cantidad'] ?>" min="1"
                                                    class="form-control form-control-sm qty-input me-2">
                                                <button type="submit" name="update_cart" class="btn btn-sm btn-update">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="product-text">$<?= number_format($item['precio'] * $item['cantidad'], 2) ?></td>
                                        <td>
                                            <form method="post" action="procesarCarrito.php">
                                                <input type="hidden" name="product_id" value="<?= $item['producto_id'] ?>">
                                                <input type="hidden" name="tipo" value="libros">
                                                <button type="submit" name="remove_item" class="btn btn-sm btn-remove">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php $total_general += $item['precio'] * $item['cantidad']; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!empty($ebooks)): ?>
                    <div class="category-header">
                        <i class="fas fa-tablet-alt me-2"></i>Ebooks
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Ebook</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ebooks as $item): ?>
                                    <tr>
                                        <td class="product-text"><?= htmlspecialchars($item['titulo']) ?></td>
                                        <td class="product-text">$<?= number_format($item['precio_unitario'], 2) ?></td>
                                        <td class="product-text">
                                            <!-- Mostramos "1" como cantidad fija para ebooks -->
                                            <span class="badge bg-secondary">1</span>
                                            <input type="hidden" name="quantity" value="1">
                                        </td>
                                        <td class="product-text">
                                            $<?= number_format($item['precio_unitario'], 2) ?>
                                        </td>
                                        <td>
                                            <form method="post" action="procesarCarrito.php">
                                                <input type="hidden" name="product_id" value="<?= $item['ebook_id'] ?>">
                                                <input type="hidden" name="tipo" value="ebooks">
                                                <button type="submit" name="remove_item" class="btn btn-sm btn-remove">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php $total_general += $item['precio_unitario']; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!empty($webinars)): ?>
                    <div class="category-header">
                        <i class="fas fa-video me-2"></i>Webinars
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Webinar</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($webinars as $item): ?>
                                    <tr>
                                        <td class="product-text">
                                            <?= htmlspecialchars($item['titulo']) ?>
                                            <?php if (!empty($item['fecha'])): ?>
                                                <div class="webinar-date">
                                                    📅 <?= date('d/m/Y H:i', strtotime($item['fecha'])) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['duracion'])): ?>
                                                <div class="webinar-date">
                                                    ⏱️ <?= htmlspecialchars($item['duracion']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="product-text">$<?= number_format($item['precio_unitario'], 2) ?></td>
                                        <td>
                                            <form method="post" action="procesarCarrito.php" class="d-flex align-items-center">
                                                <input type="hidden" name="product_id" value="<?= $item['webinar_id'] ?>">
                                                <input type="hidden" name="tipo" value="webinars">
                                                <input type="number" name="quantity" value="<?= $item['cantidad'] ?>" min="1"
                                                    class="form-control form-control-sm qty-input me-2">
                                                <button type="submit" name="update_cart" class="btn btn-sm btn-update">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="product-text">$<?= number_format($item['precio_unitario'] * $item['cantidad'], 2) ?></td>
                                        <td>
                                            <form method="post" action="procesarCarrito.php">
                                                <input type="hidden" name="product_id" value="<?= $item['webinar_id'] ?>">
                                                <input type="hidden" name="tipo" value="webinars">
                                                <button type="submit" name="remove_item" class="btn btn-sm btn-remove">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php $total_general += $item['precio_unitario'] * $item['cantidad']; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="total-section">
                    <h4 class="fw-bold">Total: $<?= number_format($total_general, 2) ?></h4>

                    <div class="payment-options">
                        <form action="pago_oxxo.php" method="post">
                            <input type="hidden" name="total" value="<?= $total_general ?>">
                            <input type="hidden" name="has_mercancia" value="<?= empty($mercancia) ? '0' : '1' ?>">
                            <input type="hidden" name="has_libros" value="<?= empty($libros) ? '0' : '1' ?>">
                            <input type="hidden" name="has_ebooks" value="<?= empty($ebooks) ? '0' : '1' ?>">
                            <input type="hidden" name="has_webinars" value="<?= empty($webinars) ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-oxxo">
                                <i class="fas fa-store me-2"></i>Pagar en efectivo
                            </button>
                        </form>

                        <form action="pago_transferencia.php" method="post">
                            <input type="hidden" name="total" value="<?= $total_general ?>">
                            <input type="hidden" name="has_mercancia" value="<?= empty($mercancia) ? '0' : '1' ?>">
                            <input type="hidden" name="has_libros" value="<?= empty($libros) ? '0' : '1' ?>">
                            <input type="hidden" name="has_ebooks" value="<?= empty($ebooks) ? '0' : '1' ?>">
                            <input type="hidden" name="has_webinars" value="<?= empty($webinars) ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-transfer">
                                <i class="fas fa-university me-2"></i>Pagar por Transferencia
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>