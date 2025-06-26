<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inicializar variables con valores por defecto
$ebooks = $ebooks ?? [];
$cartItems = $cartItems ?? [];
$cartItemCount = $cartItemCount ?? 0;
$mensaje = $mensaje ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ebooks - Tienda IMCYC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-ebook"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<h2 class="ebook-section-title">Catálogo de Ebooks</h2>

<div class="ebook-grid">
    <?php if (!empty($ebooks)): ?>
        <?php foreach ($ebooks as $ebook): ?>
            <div class="ebook-card">
                <div class="ebook-thumbnail">
                    <?php if (!empty($ebook['portada_url'])): ?>
                        <img src="<?= htmlspecialchars($ebook['portada_url']) ?>" 
                             alt="Portada de <?= htmlspecialchars($ebook['titulo']) ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="thumbnail-placeholder" style="display: none;">
                            <i class="fas fa-book-open"></i>
                        </div>
                    <?php else: ?>
                        <div class="thumbnail-placeholder">
                            <i class="fas fa-book-open"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="ebook-info">
                    <h3 class="ebook-title"><?= htmlspecialchars($ebook['titulo']) ?></h3>
                    <p class="ebook-author"><?= htmlspecialchars($ebook['autor'] ?? 'IMCYC') ?></p>
                    <div class="ebook-meta">
                        <?php if (!empty($ebook['paginas'])): ?>
                            <span class="ebook-pages">📖 <?= $ebook['paginas'] ?> páginas</span>
                        <?php endif; ?>
                        <span class="ebook-price">$<?= number_format($ebook['precio'] ?? 0, 2) ?></span>
                    </div>

                    <?php if (isset($ebook['is_owned']) && $ebook['is_owned']): ?>
                        <!-- Mostrar mensaje de ebook ya adquirido -->
                        <div class="ebook-owned-message">
                            <i class="fas fa-check-circle"></i> 
                            Ya posees este ebook
                        </div>
                        <div class="ebook-actions">
                            <a href="?section=tus_ebooks" class="btn-view-owned">
                                <i class="fas fa-book-reader"></i> Ver en Mi Biblioteca
                            </a>
                            <?php if (!empty($ebook['muestra_url'])): ?>
                                <a href="<?= htmlspecialchars($ebook['muestra_url']) ?>" 
                                   class="btn-sample" 
                                   download
                                   aria-label="Descargar muestra">
                                    <i class="fas fa-file-pdf"></i> Ver muestra
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Mostrar formulario de compra normal -->
                        <form method="POST" class="ebook-actions">
                            <input type="hidden" name="product_id" value="<?= $ebook['ebook_id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <!-- Valor fijo de cantidad = 1 -->
                            <input type="hidden" name="quantity" value="1">
                            
                            <button type="submit" 
                                    name="add_to_cart" 
                                    class="btn-add-to-cart full-width"
                                    aria-label="Añadir al carrito">
                                <i class="fas fa-cart-plus"></i> Añadir al carrito
                            </button>

                            <?php if (!empty($ebook['muestra_url'])): ?>
                                <a href="<?= htmlspecialchars($ebook['muestra_url']) ?>" 
                                   class="btn-sample" 
                                   download
                                   aria-label="Descargar muestra">
                                    <i class="fas fa-file-pdf"></i> Ver muestra
                                </a>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-book"></i>
            <p>No hay ebooks disponibles en este momento</p>
        </div>
    <?php endif; ?>
</div>

<button class="floating-cart-btn" onclick="toggleCartPopup()" aria-label="Ver carrito">
    🛒<span class="cart-counter"><?= ($cartItemCount > 0) ? $cartItemCount : '' ?></span>
</button>

<div class="cart-popup" id="cartPopup" aria-hidden="true">
    <div class="cart-header">
        <h4>Tu Carrito de Ebooks</h4>
        <button class="btn-close-cart" 
                onclick="toggleCartPopup()" 
                aria-label="Cerrar carrito">
            &times;
        </button>
    </div>

    <?php if (!empty($cartItems)): ?>
        <ul class="cart-items">
            <?php foreach ($cartItems as $item): ?>
                <li class="cart-item">
                    <div class="cart-item-image">
                        <?php if (!empty($item['portada_url'])): ?>
                            <img src="<?= htmlspecialchars($item['portada_url']) ?>" 
                                 alt="<?= htmlspecialchars($item['titulo']) ?>"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="cart-thumbnail-placeholder" style="display: none;">
                                <i class="fas fa-book"></i>
                            </div>
                        <?php else: ?>
                            <div class="cart-thumbnail-placeholder">
                                <i class="fas fa-book"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="item-info">
                        <h5><?= htmlspecialchars($item['titulo']) ?></h5>
                        <p class="item-author"><?= htmlspecialchars($item['autor'] ?? 'IMCYC') ?></p>
                        <p class="item-price">$<?= number_format($item['precio_unitario'] ?? 0, 2) ?></p>
                    </div>

                    <form method="POST" class="item-controls">
                        <input type="hidden" name="product_id" value="<?= $item['ebook_id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        
                        <button type="submit" 
                                name="remove_item" 
                                class="btn-remove"
                                aria-label="Eliminar del carrito">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <p>Tu carrito está vacío</p>
        </div>
    <?php endif; ?>

    <div class="cart-footer">
        <p class="cart-total">Total: $<?= number_format(array_sum(array_map(fn($item) => $item['precio_unitario'], $cartItems)), 2) ?></p>
        <a href="?section=carrito" class="btn-checkout">
            <i class="fas fa-credit-card"></i> Proceder al pago
        </a>
    </div>
</div>

<style>
    /* Estilos base */
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --success-color: #27ae60;
        --danger-color: #e74c3c;
        --text-color: #2c3e50;
        --bg-color: #ffffff;
        --border-color: #ecf0f1;
        --owned-color: #27ae60;
        --owned-bg: #d4edda;
    }

    body {
        font-family: 'Segoe UI', system-ui, sans-serif;
        margin: 0;
        padding: 20px;
        background-color: var(--bg-color);
        color: var(--text-color);
        transition: background-color 0.3s, color 0.3s;
    }

    /* Modo oscuro */
    body.dark-mode {
        --text-color: #ecf0f1;
        --bg-color: #2c3e50;
        --border-color: #34495e;
        --owned-bg: #1e3d32;
    }

    /* Tarjetas de ebooks */
    .ebook-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem;
    }

    .ebook-card {
        background: var(--bg-color);
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: transform 0.2s ease;
    }

    .ebook-card:hover {
        transform: translateY(-5px);
    }

    .ebook-thumbnail {
        background: #f8f9fa;
        height: 200px;
        position: relative;
        overflow: hidden;
    }

    .ebook-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: opacity 0.3s;
    }

    .thumbnail-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #7f8c8d;
        font-size: 3rem;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .ebook-info {
        padding: 1.25rem;
    }

    .ebook-title {
        font-size: 1.1rem;
        margin: 0 0 0.5rem;
        color: var(--text-color);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
    }

    .ebook-author {
        color: #7f8c8d;
        font-size: 0.9rem;
        margin: 0 0 1rem;
    }

    .ebook-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
    }

    .ebook-price {
        font-weight: 700;
        color: var(--success-color);
        font-size: 1.2rem;
    }

    /* Mensaje de ebook ya adquirido */
    .ebook-owned-message {
        background-color: var(--owned-bg);
        color: var(--owned-color);
        padding: 0.75rem;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 1rem;
        font-weight: 600;
        border: 1px solid var(--owned-color);
    }

    .ebook-owned-message i {
        margin-right: 0.5rem;
    }

    /* Formularios */
    .ebook-actions {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .btn-add-to-cart {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 0.75rem;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.2s;
        text-align: center;
    }

    .full-width {
        width: 100%;
    }

    .btn-add-to-cart:hover {
        background-color: #2980b9;
    }

    .btn-view-owned {
        display: inline-block;
        text-align: center;
        padding: 0.75rem;
        background: var(--owned-color);
        color: white;
        border-radius: 8px;
        text-decoration: none;
        transition: background-color 0.2s;
    }

    .btn-view-owned:hover {
        background-color: #219a52;
    }

    .btn-sample {
        display: inline-block;
        text-align: center;
        padding: 0.75rem;
        background: var(--secondary-color);
        color: white;
        border-radius: 8px;
        text-decoration: none;
        transition: background-color 0.2s;
    }

    .btn-sample:hover {
        background-color: #1e2b38;
    }

    /* Carrito flotante */
    .floating-cart-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: var(--primary-color);
        color: white;
        width: 60px;
        height: 60px;
        border: none;
        border-radius: 50%;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        transition: transform 0.2s;
    }

    .floating-cart-btn:hover {
        transform: scale(1.1);
    }

    .cart-counter {
        position: absolute;
        top: -8px;
        right: -8px;
        background: var(--danger-color);
        color: white;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Popup del carrito */
    .cart-popup {
        position: fixed;
        bottom: 100px;
        right: 30px;
        width: 350px;
        max-height: 70vh;
        background: var(--bg-color);
        border-radius: 12px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        padding: 1.25rem;
        display: none;
        z-index: 999;
        border: 1px solid var(--border-color);
    }

    .cart-popup.active {
        display: block;
    }

    .cart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
    }

    .btn-close-cart {
        background: none;
        border: none;
        color: var(--text-color);
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.5rem;
    }

    /* Items del carrito */
    .cart-items {
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 50vh;
        overflow-y: auto;
    }

    .cart-item {
        display: flex;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border-color);
        gap: 0.75rem;
    }

    .cart-item-image {
        flex-shrink: 0;
        width: 50px;
        height: 60px;
        position: relative;
        overflow: hidden;
        border-radius: 4px;
        background: #f8f9fa;
    }

    .cart-item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .cart-thumbnail-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #7f8c8d;
        font-size: 1.2rem;
    }

    .item-info {
        flex-grow: 1;
        margin-right: 1rem;
    }

    .item-info h5 {
        margin: 0 0 0.25rem;
        font-size: 0.9rem;
        line-height: 1.2;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .item-author {
        margin: 0;
        font-size: 0.8rem;
        color: #7f8c8d;
    }

    .item-price {
        color: var(--success-color);
        font-weight: 500;
        margin: 0.25rem 0 0;
        font-size: 0.9rem;
    }

    .item-controls {
        display: flex;
        align-items: center;
    }

    .btn-remove {
        background: var(--danger-color);
        color: white;
        border: none;
        padding: 0.5rem 0.8rem;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .btn-remove:hover {
        background-color: #c0392b;
    }

    /* Estados vacíos */
    .empty-state, .empty-cart {
        text-align: center;
        padding: 2rem;
        grid-column: 1 / -1;
        color: #7f8c8d;
    }

    .empty-state i, .empty-cart i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #bdc3c7;
    }

    /* Footer del carrito */
    .cart-footer {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border-color);
    }

    .cart-total {
        text-align: right;
        font-weight: 700;
        margin: 0 0 1rem;
        color: var(--text-color);
    }

    .btn-checkout {
        display: block;
        width: 100%;
        background: var(--success-color);
        color: white;
        text-align: center;
        padding: 1rem;
        border-radius: 8px;
        text-decoration: none;
        transition: background-color 0.2s;
    }

    .btn-checkout:hover {
        background-color: #219a52;
    }

    /* Alerta de mensajes */
    .alert-ebook {
        background-color: #d1ecf1;
        color: #0c5460;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        text-align: center;
        border: 1px
    }

    body.dark-mode .alert-ebook {
        background-color: #1e3d32;
        color: #d4edda;
        border-color: #27ae60;
    }

    @media (max-width: 768px) {
        .ebook-grid {
            grid-template-columns: 1fr;
            padding: 0;
        }

        .cart-popup {
            width: 90%;
            right: 5%;
        }
    }
</style>

<script>
    // Toggle del carrito
    function toggleCartPopup() {
        const popup = document.getElementById('cartPopup');
        popup.style.display = popup.style.display === 'block' ? 'none' : 'block';
        popup.setAttribute('aria-hidden', popup.style.display === 'none');
    }

    // Cerrar al hacer clic fuera
    document.addEventListener('click', (event) => {
        const cartPopup = document.getElementById('cartPopup');
        const cartBtn = document.querySelector('.floating-cart-btn');
        
        if (!cartPopup.contains(event.target) && !cartBtn.contains(event.target)) {
            cartPopup.style.display = 'none';
            cartPopup.setAttribute('aria-hidden', 'true');
        }
    });

    // Mejorar accesibilidad del teclado
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.getElementById('cartPopup').style.display = 'none';
        }
    });
</script>

</body>
</html>