<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inicializar variables con valores por defecto
$webinars = $webinars ?? [];
$cartItems = $cartItems ?? [];
$cartItemCount = $cartItemCount ?? 0;
$mensaje = $mensaje ?? '';
$error = $error ?? '';

// Función para obtener imagen de webinar
function getWebinarImageUrl($imagen_webinar = null, $titulo = '')
{
    // Si tenemos la ruta de la imagen en la tabla
    if ($imagen_webinar) {
        // Extraer solo el nombre del archivo de la ruta de la tabla
        $filename = basename($imagen_webinar);
        $imagePath = 'covers/webinars/' . $filename;
        if (file_exists($imagePath)) {
            return $imagePath;
        }
        
        // Si no existe el archivo específico, intentar con diferentes extensiones
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $extensions = ['jpg', 'jpeg', 'png', 'webp', 'JPG', 'JPEG', 'PNG'];
        
        foreach ($extensions as $ext) {
            $testPath = 'covers/webinars/' . $nameWithoutExt . '.' . $ext;
            if (file_exists($testPath)) {
                return $testPath;
            }
        }
    }
    
    // Fallback: intentar generar nombre basado en el título
    if ($titulo) {
        $cleanTitle = str_replace(' ', '_', strtolower($titulo));
        $cleanTitle = preg_replace('/[^A-Za-z0-9_]/', '', $cleanTitle);
        
        $extensions = ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($extensions as $ext) {
            $testPath = 'covers/webinars/' . $cleanTitle . '.' . $ext;
            if (file_exists($testPath)) {
                return $testPath;
            }
        }
    }
    
    // Imagen por defecto para webinars
    return 'covers/default_webinar.svg';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webinars - Tienda IMCYC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-webinar"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<h2 class="webinar-section-title">Catálogo de Webinars</h2>

<!-- Filtros por categoría -->
<?php if (!empty($categories)): ?>
<div class="category-filters">
    <a href="?section=webinars" class="filter-btn <?= !isset($_GET['category']) ? 'active' : '' ?>">
        Todos
    </a>
    <?php foreach ($categories as $category): ?>
        <a href="?section=webinars&category=<?= urlencode($category) ?>" 
           class="filter-btn <?= (isset($_GET['category']) && $_GET['category'] === $category) ? 'active' : '' ?>">
            <?= htmlspecialchars($category) ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="webinar-grid">
    <?php if (!empty($webinars)): ?>
        <?php foreach ($webinars as $webinar): ?>
            <div class="webinar-card">
                <div class="webinar-thumbnail">
                    <?php 
                    $imageUrl = getWebinarImageUrl($webinar['imagen'] ?? null, $webinar['titulo'] ?? '');
                    ?>
                    <img src="<?= htmlspecialchars($imageUrl) ?>"
                         alt="Imagen de <?= htmlspecialchars($webinar['titulo']) ?>"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="thumbnail-placeholder" style="display: none;">
                        <i class="fas fa-video"></i>
                    </div>
                </div>
                <div class="webinar-info">
                    <h3 class="webinar-title"><?= htmlspecialchars($webinar['titulo']) ?></h3>
                    
                    <?php if (!empty($webinar['descripcion'])): ?>
                        <p class="webinar-description"><?= htmlspecialchars(substr($webinar['descripcion'], 0, 100)) ?><?= strlen($webinar['descripcion']) > 100 ? '...' : '' ?></p>
                    <?php endif; ?>

                    <div class="webinar-meta">
                        <?php if (!empty($webinar['duracion'])): ?>
                            <span class="webinar-duration">⏱️ <?= htmlspecialchars($webinar['duracion']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($webinar['fecha'])): ?>
                            <span class="webinar-date">📅 <?= date('d/m/Y H:i', strtotime($webinar['fecha'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($webinar['categoria'])): ?>
                            <span class="webinar-category">🏷️ <?= htmlspecialchars($webinar['categoria']) ?></span>
                        <?php endif; ?>
                        <span class="webinar-price">$<?= number_format($webinar['precio'] ?? 0, 2) ?></span>
                    </div>

                    <?php if (isset($webinar['is_owned']) && $webinar['is_owned']): ?>
                        <!-- Mostrar mensaje de webinar ya adquirido -->
                        <div class="webinar-owned-message">
                            <i class="fas fa-check-circle"></i>
                            Ya tienes acceso a este webinar
                        </div>
                        <div class="webinar-actions">
                            <a href="?section=mis_webinars" class="btn-view-owned">
                                <i class="fas fa-play-circle"></i> Ver en Mis Webinars
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Mostrar formulario de compra normal -->
                        <form method="POST" class="webinar-actions">
                            <input type="hidden" name="product_id" value="<?= $webinar['webinar_id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <!-- Valor fijo de cantidad = 1 -->
                            <input type="hidden" name="quantity" value="1">

                            <button type="submit"
                                    name="add_to_cart"
                                    class="btn-add-to-cart full-width"
                                    aria-label="Añadir al carrito">
                                <i class="fas fa-cart-plus"></i> Añadir al carrito
                            </button>

                            <?php if (!empty($webinar['descripcion'])): ?>
                                <button type="button"
                                        class="btn-details"
                                        onclick="showWebinarDetails(<?= $webinar['webinar_id'] ?>)"
                                        aria-label="Ver detalles">
                                    <i class="fas fa-info-circle"></i> Ver detalles
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-video"></i>
            <p>No hay webinars disponibles en este momento</p>
        </div>
    <?php endif; ?>
</div>

<button class="floating-cart-btn" onclick="toggleCartPopup()" aria-label="Ver carrito">
    🛒<span class="cart-counter"><?= ($cartItemCount > 0) ? $cartItemCount : '' ?></span>
</button>

<div class="cart-popup" id="cartPopup" aria-hidden="true">
    <div class="cart-header">
        <h4>Tu Carrito de Webinars</h4>
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
                        <?php 
                        $cartImageUrl = getWebinarImageUrl($item['imagen'] ?? null, $item['titulo'] ?? '');
                        ?>
                        <img src="<?= htmlspecialchars($cartImageUrl) ?>"
                             alt="<?= htmlspecialchars($item['titulo']) ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="cart-thumbnail-placeholder" style="display: none;">
                            <i class="fas fa-video"></i>
                        </div>
                    </div>
                    <div class="item-info">
                        <h5><?= htmlspecialchars($item['titulo']) ?></h5>
                        <?php if (!empty($item['duracion'])): ?>
                            <p class="item-duration">⏱️ <?= htmlspecialchars($item['duracion']) ?></p>
                        <?php endif; ?>
                        <p class="item-price">$<?= number_format($item['precio_unitario'] ?? 0, 2) ?></p>
                    </div>

                    <form method="POST" class="item-controls">
                        <input type="hidden" name="product_id" value="<?= $item['webinar_id'] ?>">
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

<!-- Modal para detalles del webinar -->
<div class="modal-overlay" id="webinarModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Detalles del Webinar</h3>
            <button class="btn-close-modal" onclick="closeWebinarModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Contenido dinámico -->
        </div>
    </div>
</div>

<style>
    /* Estilos base */
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --success-color: #27ae60;
        --danger-color: #e74c3c;
        --warning-color: #f39c12;
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

    /* Filtros de categoría */
    .category-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 2rem;
        padding: 1rem;
        background: var(--bg-color);
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .filter-btn {
        padding: 0.5rem 1rem;
        background: #f8f9fa;
        color: var(--text-color);
        text-decoration: none;
        border-radius: 20px;
        transition: all 0.2s;
        font-size: 0.9rem;
    }

    .filter-btn:hover {
        background: var(--primary-color);
        color: white;
    }

    .filter-btn.active {
        background: var(--primary-color);
        color: white;
    }

    /* Tarjetas de webinars */
    .webinar-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem;
    }

    .webinar-card {
        background: var(--bg-color);
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: transform 0.2s ease;
    }

    .webinar-card:hover {
        transform: translateY(-5px);
    }

    .webinar-thumbnail {
        background: #f8f9fa;
        height: 200px;
        position: relative;
        overflow: hidden;
    }

    .webinar-thumbnail img {
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

    .webinar-info {
        padding: 1.25rem;
    }

    .webinar-title {
        font-size: 1.1rem;
        margin: 0 0 0.5rem;
        color: var(--text-color);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
    }

    .webinar-description {
        color: #666;
        font-size: 0.85rem;
        margin: 0 0 1rem;
        line-height: 1.4;
    }

    .webinar-meta {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
    }

    .webinar-duration, .webinar-date, .webinar-category {
        font-size: 0.85rem;
        color: #7f8c8d;
    }

    .webinar-price {
        font-weight: 700;
        color: var(--success-color);
        font-size: 1.2rem;
        align-self: flex-end;
    }

    /* Mensaje de webinar ya adquirido */
    .webinar-owned-message {
        background-color: var(--owned-bg);
        color: var(--owned-color);
        padding: 0.75rem;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 1rem;
        font-weight: 600;
        border: 1px solid var(--owned-color);
    }

    .webinar-owned-message i {
        margin-right: 0.5rem;
    }

    /* Formularios */
    .webinar-actions {
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

    .btn-details {
        background: #95a5a6;
        color: white;
        border: none;
        padding: 0.75rem;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.2s;
        text-align: center;
        width: 100%;
    }

    .btn-details:hover {
        background-color: #7f8c8d;
    }

    /* Modal */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }

    .modal-content {
        background: var(--bg-color);
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }

    .modal-header h3 {
        margin: 0;
        color: var(--text-color);
    }

    .btn-close-modal {
        background: none;
        border: none;
        color: var(--text-color);
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.5rem;
    }

    .modal-body {
        padding: 1.5rem;
        color: var(--text-color);
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

    .item-duration {
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

    /* Alertas de mensajes */
    .alert-webinar {
        background-color: #d1ecf1;
        color: #0c5460;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        text-align: center;
        border: 1px solid #bee5eb;
    }

    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        text-align: center;
        border: 1px solid #f5c6cb;
    }

    body.dark-mode .alert-webinar {
        background-color: #1e3d32;
        color: #d4edda;
        border-color: #27ae60;
    }

    body.dark-mode .alert-error {
        background-color: #3d1e1e;
        color: #f8d7da;
        border-color: #e74c3c;
    }

    @media (max-width: 768px) {
        .webinar-grid {
            grid-template-columns: 1fr;
            padding: 0;
        }

        .cart-popup {
            width: 90%;
            right: 5%;
        }

        .modal-content {
            width: 95%;
        }

        .category-filters {
            padding: 0.5rem;
        }

        .filter-btn {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
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

    // Modal de detalles del webinar
    function showWebinarDetails(webinarId) {
        const webinar = <?= json_encode($webinars) ?>.find(w => w.webinar_id == webinarId);
        if (webinar) {
            document.getElementById('modalTitle').textContent = webinar.titulo;
            document.getElementById('modalBody').innerHTML = `
                ${webinar.descripcion ? `<p><strong>Descripción:</strong> ${webinar.descripcion}</p>` : ''}
                ${webinar.duracion ? `<p><strong>Duración:</strong> ${webinar.duracion}</p>` : ''}
                ${webinar.fecha ? `<p><strong>Fecha:</strong> ${new Date(webinar.fecha).toLocaleDateString('es-ES', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                })}</p>` : ''}
                ${webinar.categoria ? `<p><strong>Categoría:</strong> ${webinar.categoria}</p>` : ''}
                <p><strong>Precio:</strong> $${parseFloat(webinar.precio || 0).toLocaleString('es-MX', {minimumFractionDigits: 2})}</p>
            `;
            document.getElementById('webinarModal').style.display = 'flex';
        }
    }

    function closeWebinarModal() {
        document.getElementById('webinarModal').style.display = 'none';
    }

    // Cerrar al hacer clic fuera
    document.addEventListener('click', (event) => {
        const cartPopup = document.getElementById('cartPopup');
        const cartBtn = document.querySelector('.floating-cart-btn');
        const modal = document.getElementById('webinarModal');
        const modalContent = document.querySelector('.modal-content');

        if (!cartPopup.contains(event.target) && !cartBtn.contains(event.target)) {
            cartPopup.style.display = 'none';
            cartPopup.setAttribute('aria-hidden', 'true');
        }

        if (modal.style.display === 'flex' && !modalContent.contains(event.target)) {
            closeWebinarModal();
        }
    });

    // Mejorar accesibilidad del teclado
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.getElementById('cartPopup').style.display = 'none';
            closeWebinarModal();
        }
    });
</script>

</body>
</html>