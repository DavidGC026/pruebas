<?php
// Configuración de la base de datos
$host = 'localhost';
$dbname = 'tienda';
$username = 'admin';
$password = 'Imc590923cz4#';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Función para obtener imagen de portada
function getImageUrl($name, $type = 'libro', $archivo_url = null, $imagen_webinar = null)
{
    // Para webinars, usar la ruta de la tabla pero dentro de covers/webinars/
    if ($type === 'webinar' && $imagen_webinar) {
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

    // Para ebooks, si tenemos archivo_url, usamos el método del PDF
    if ($type === 'ebook' && $archivo_url) {
        $filename = str_replace('/var/www/sources/libros/', '', $archivo_url);
        $coverFilename = preg_replace('/\.pdf$/i', '.jpg', $filename);
        $imagePath = 'covers/' . $coverFilename;
        if (file_exists($imagePath)) {
            return $imagePath;
        }
    }

    // Método 1: Intentar con mayúsculas y .JPG
    $imageName1 = str_replace(' ', '_', strtoupper($name)) . '.JPG';
    $imagePath1 = 'covers/' . $imageName1;
    if (file_exists($imagePath1)) {
        return $imagePath1;
    }

    // Método 2: Intentar con formato limpio y minúsculas .jpg
    $cleanName = str_replace(' ', '_', $name);
    $cleanName = preg_replace('/[^A-Za-z0-9_]/', '', $cleanName);
    $imagePath2 = 'covers/' . $cleanName . '.jpg';
    if (file_exists($imagePath2)) {
        return $imagePath2;
    }

    // Método 3: Intentar con el nombre original sin espacios
    $imageName3 = str_replace(' ', '_', $name) . '.jpg';
    $imagePath3 = 'covers/' . $imageName3;
    if (file_exists($imagePath3)) {
        return $imagePath3;
    }

    // Método 4: Intentar con minúsculas
    $imageName4 = str_replace(' ', '_', strtolower($name)) . '.jpg';
    $imagePath4 = 'covers/' . $imageName4;
    if (file_exists($imagePath4)) {
        return $imagePath4;
    }

    // Si no encuentra ninguna imagen, retornar imagen por defecto según el tipo
    switch ($type) {
        case 'libro':
            return 'covers/default.svg';
        case 'ebook':
            return 'covers/default.svg';
        case 'webinar':
            return 'covers/default_webinar.svg';
        case 'producto':
            return 'covers/default_product.svg';
        default:
            return 'covers/default_product.svg';
    }
}

// Obtener libros
$libros_query = "SELECT * FROM libros WHERE stock > 0 ORDER BY libro_id DESC";
$libros_stmt = $pdo->prepare($libros_query);
$libros_stmt->execute();
$libros = $libros_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener ebooks
$ebooks_query = "SELECT * FROM ebooks ORDER BY ebook_id DESC";
$ebooks_stmt = $pdo->prepare($ebooks_query);
$ebooks_stmt->execute();
$ebooks = $ebooks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener webinars
$webinars_query = "SELECT * FROM webinars WHERE activo = 1 ORDER BY fecha ASC";
$webinars_stmt = $pdo->prepare($webinars_query);
$webinars_stmt->execute();
$webinars = $webinars_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos generales
$products_query = "SELECT * FROM products WHERE stock > 0 ORDER BY product_id DESC";
$products_stmt = $pdo->prepare($products_query);
$products_stmt->execute();
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMCYC - Instituto Mexicano del Cemento y del Concreto</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }

        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            gap: 0.5rem;
        }

        .logo img {
            height: 50px;
            width: auto;
            filter: brightness(0) invert(1);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .section {
            display: none;
            animation: fadeIn 0.3s ease-in;
        }

        .section.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-title {
            font-size: 2.5rem;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(45deg, #007bff, #0056b3);
            border-radius: 2px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 500px;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .book-cover-container {
            height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }

        .book-cover-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at center, rgba(255, 255, 255, 0.8) 0%, transparent 70%);
            pointer-events: none;
        }

        .product-image {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-info {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-author {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            font-style: italic;
        }

        .product-description {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }

        .product-meta {
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .webinar-date {
            color: #007bff;
            font-weight: 500;
        }

        .webinar-duration {
            color: #28a745;
        }

        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .product-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #e74c3c;
        }

        .product-category {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .add-to-cart {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 0.8rem 1.2rem;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .add-to-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #ddd;
        }

        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 3rem 0;
            margin-top: 4rem;
        }

        .filter-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.8rem 1.5rem;
            border: 2px solid #007bff;
            background: white;
            color: #007bff;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            text-decoration: none;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: #007bff;
            color: white;
            transform: translateY(-2px);
        }

        .stats-summary {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 2rem;
            margin-top: 1rem;
        }

        .stat-item {
            padding: 1rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
            display: block;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .nav-menu {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }

            .container {
                padding: 1rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
            }

            .product-card {
                height: auto;
                min-height: 450px;
            }

            .filter-buttons {
                flex-direction: column;
                align-items: center;
            }

            .filter-btn {
                width: 200px;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .logo-text {
                font-size: 1.4rem;
            }
        }

        /* Estilos específicos para cada tipo de producto */
        .section[data-category="libros"] .product-card {
            border-left: 4px solid #007bff;
        }

        .section[data-category="ebooks"] .product-card {
            border-left: 4px solid #28a745;
        }

        .section[data-category="webinars"] .product-card {
            border-left: 4px solid #dc3545;
        }

        .section[data-category="productos"] .product-card {
            border-left: 4px solid #ffc107;
        }

        /* Efectos de carga */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="nav-container">
            <a href="#" class="logo" onclick="showSection('libros')">
                <img src="Imagenes/logo_imcyc.svg" alt="IMCYC Logo">
                <span class="logo-text">IMCYC</span>
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="#" onclick="showSection('libros')" id="nav-libros" class="active">Libros</a></li>
                    <li><a href="#" onclick="showSection('ebooks')" id="nav-ebooks">E-books</a></li>
                    <li><a href="#" onclick="showSection('webinars')" id="nav-webinars">Webinars</a></li>
                    <li><a href="#" onclick="showSection('productos')" id="nav-productos">Productos</a></li>
                </ul>
            </nav>
            <div class="auth-buttons">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-user"></i> Iniciar Sesión
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Sección de estadísticas generales -->
        <div class="stats-summary">
            <h2>Catálogo IMCYC</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($libros); ?></span>
                    <div class="stat-label">Libros</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($ebooks); ?></span>
                    <div class="stat-label">E-books</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($webinars); ?></span>
                    <div class="stat-label">Webinars</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($products); ?></span>
                    <div class="stat-label">Productos</div>
                </div>
            </div>
        </div>

        <!-- Sección de Libros -->
        <section id="libros-section" class="section active" data-category="libros">
            <h2 class="section-title">
                <i class="fas fa-book"></i> Libros Especializados
            </h2>

            <?php if (!empty($libros)): ?>
                <div class="products-grid">
                    <?php foreach ($libros as $libro): ?>
                        <div class="product-card">
                            <div class="book-cover-container">
                                <img src="<?php echo getImageUrl($libro['nombre'], 'libro'); ?>"
                                    alt="<?php echo htmlspecialchars($libro['nombre']); ?>" class="product-image"
                                    onerror="this.src='covers/default.svg'">
                            </div>
                            <div class="product-info">
                                <h3 class="product-title"><?php echo htmlspecialchars($libro['nombre']); ?></h3>
                                <?php if (!empty($libro['autor'])): ?>
                                    <p class="product-author">Por: <?php echo htmlspecialchars($libro['autor']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($libro['descripcion'])): ?>
                                    <p class="product-description"><?php echo htmlspecialchars($libro['descripcion']); ?></p>
                                <?php endif; ?>
                                <div class="product-footer">
                                    <span class="product-price">$<?php echo number_format($libro['precio'], 2); ?></span>
                                    <?php if (!empty($libro['categoria'])): ?>
                                        <span class="product-category"><?php echo htmlspecialchars($libro['categoria']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <button class="add-to-cart" onclick="requireLogin('libro', <?php echo $libro['libro_id']; ?>)">
                                    <i class="fas fa-cart-plus"></i> Agregar al Carrito
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No hay libros disponibles</h3>
                    <p>Próximamente agregaremos más libros a nuestro catálogo</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Sección de E-books -->
        <section id="ebooks-section" class="section" data-category="ebooks">
            <h2 class="section-title">
                <i class="fas fa-tablet-alt"></i> E-books Digitales
            </h2>

            <?php if (!empty($ebooks)): ?>
                <div class="products-grid">
                    <?php foreach ($ebooks as $ebook): ?>
                        <div class="product-card">
                            <div class="book-cover-container">
                                <img src="<?php echo getImageUrl($ebook['titulo'], 'ebook', $ebook['archivo_url'] ?? null); ?>"
                                    alt="<?php echo htmlspecialchars($ebook['titulo']); ?>" class="product-image"
                                    onerror="this.src='covers/default.svg'">
                            </div>
                            <div class="product-info">
                                <h3 class="product-title"><?php echo htmlspecialchars($ebook['titulo']); ?></h3>
                                <p class="product-author">Por: <?php echo htmlspecialchars($ebook['autor']); ?></p>
                                <?php if (!empty($ebook['descripcion'])): ?>
                                    <p class="product-description"><?php echo htmlspecialchars($ebook['descripcion']); ?></p>
                                <?php endif; ?>
                                <div class="product-footer">
                                    <span class="product-price">$<?php echo number_format($ebook['precio'], 2); ?></span>
                                    <span class="product-category"><?php echo htmlspecialchars($ebook['categoria']); ?></span>
                                </div>
                                <button class="add-to-cart" onclick="requireLogin('ebook', <?php echo $ebook['ebook_id']; ?>)">
                                    <i class="fas fa-download"></i> Descargar E-book
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tablet-alt"></i>
                    <h3>No hay e-books disponibles</h3>
                    <p>Próximamente agregaremos más e-books a nuestro catálogo digital</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Sección de Webinars -->
        <section id="webinars-section" class="section" data-category="webinars">
            <h2 class="section-title">
                <i class="fas fa-video"></i> Webinars Especializados
            </h2>

            <?php if (!empty($webinars)): ?>
                <div class="products-grid">
                    <?php foreach ($webinars as $webinar): ?>
                        <div class="product-card">
                            <div class="book-cover-container">
                                <img src="<?php echo getImageUrl($webinar['titulo'], 'webinar', null, $webinar['imagen']); ?>"
                                    alt="<?php echo htmlspecialchars($webinar['titulo']); ?>" class="product-image"
                                    onerror="this.src='covers/default_webinar.svg'">
                            </div>
                            <div class="product-info">
                                <h3 class="product-title"><?php echo htmlspecialchars($webinar['titulo']); ?></h3>
                                
                                <?php if (!empty($webinar['fecha']) || !empty($webinar['duracion'])): ?>
                                    <div class="product-meta">
                                        <?php if (!empty($webinar['fecha'])): ?>
                                            <div class="webinar-date">
                                                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($webinar['fecha'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($webinar['duracion'])): ?>
                                            <div class="webinar-duration">
                                                <i class="fas fa-clock"></i> <?php echo htmlspecialchars($webinar['duracion']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($webinar['descripcion'])): ?>
                                    <p class="product-description"><?php echo htmlspecialchars($webinar['descripcion']); ?></p>
                                <?php endif; ?>
                                
                                <div class="product-footer">
                                    <span class="product-price">$<?php echo number_format($webinar['precio'], 2); ?></span>
                                    <?php if (!empty($webinar['categoria'])): ?>
                                        <span class="product-category"><?php echo htmlspecialchars($webinar['categoria']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <button class="add-to-cart" onclick="requireLogin('webinar', <?php echo $webinar['webinar_id']; ?>)">
                                    <i class="fas fa-video"></i> Inscribirse al Webinar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-video"></i>
                    <h3>No hay webinars disponibles</h3>
                    <p>Próximamente programaremos más webinars especializados</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Sección de Productos -->
        <section id="productos-section" class="section" data-category="productos">
            <h2 class="section-title">
                <i class="fas fa-tools"></i> Productos Técnicos
            </h2>

            <?php if (!empty($products)): ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="book-cover-container">
                                <img src="<?php echo htmlspecialchars($product['image'] ?? 'covers/default_product.svg'); ?>"
                                    alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image"
                                    onerror="this.src='covers/default_product.svg'">
                            </div>
                            <div class="product-info">
                                <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <?php if (!empty($product['description'])): ?>
                                    <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                                <?php endif; ?>
                                <div class="product-footer">
                                    <span class="product-price">$<?php echo number_format($product['price'], 2); ?></span>
                                    <?php if (!empty($product['category'])): ?>
                                        <span class="product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <button class="add-to-cart"
                                    onclick="requireLogin('product', <?php echo $product['product_id']; ?>)">
                                    <i class="fas fa-cart-plus"></i> Agregar al Carrito
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tools"></i>
                    <h3>No hay productos disponibles</h3>
                    <p>Próximamente agregaremos más productos técnicos a nuestro catálogo</p>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <footer class="footer">
        <div class="container">
            <h3>Instituto Mexicano del Cemento y del Concreto</h3>
            <p>Promoviendo la excelencia en la industria de la construcción desde 1959</p>
            <p>&copy; 2025 IMCYC. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        // Función para mostrar secciones
        function showSection(sectionName) {
            // Ocultar todas las secciones
            const sections = document.querySelectorAll('.section');
            sections.forEach(section => {
                section.classList.remove('active');
            });

            // Mostrar la sección seleccionada
            const targetSection = document.getElementById(sectionName + '-section');
            if (targetSection) {
                targetSection.classList.add('active');
            }

            // Actualizar navegación activa
            const navItems = document.querySelectorAll('.nav-menu a');
            navItems.forEach(item => {
                item.classList.remove('active');
            });

            const activeNavItem = document.getElementById('nav-' + sectionName);
            if (activeNavItem) {
                activeNavItem.classList.add('active');
            }

            // Scroll suave al inicio de la sección
            document.querySelector('.stats-summary').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        // Función para requerir login
        function requireLogin(type, id) {
            // Guardar información del producto en sessionStorage para después del login
            sessionStorage.setItem('pendingPurchase', JSON.stringify({
                type: type,
                id: id,
                action: 'add_to_cart'
            }));

            // Mostrar mensaje y redirigir al login
            alert('Para realizar compras necesitas iniciar sesión o registrarte');
            window.location.href = 'login.php';
        }

        // Animaciones al cargar la página
        document.addEventListener('DOMContentLoaded', function () {
            // Aplicar efectos de aparición a las tarjetas
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                    }
                });
            }, observerOptions);

            // Aplicar animación a las tarjetas de productos
            document.querySelectorAll('.product-card').forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(card);
            });

            // Efecto de escritura para el título
            const titles = document.querySelectorAll('.section-title');
            titles.forEach(title => {
                title.style.opacity = '0';
                title.style.transform = 'translateY(-20px)';
                title.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
            });

            // Mostrar títulos cuando la sección esté activa
            const sectionObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const title = entry.target.querySelector('.section-title');
                        if (title) {
                            title.style.opacity = '1';
                            title.style.transform = 'translateY(0)';
                        }
                    }
                });
            }, { threshold: 0.3 });

            // Observar todas las secciones
            document.querySelectorAll('.section').forEach(section => {
                sectionObserver.observe(section);
            });

            // Animación inicial para la sección activa
            const activeSection = document.querySelector('.section.active');
            if (activeSection) {
                const activeTitle = activeSection.querySelector('.section-title');
                if (activeTitle) {
                    setTimeout(() => {
                        activeTitle.style.opacity = '1';
                        activeTitle.style.transform = 'translateY(0)';
                    }, 200);
                }
            }
        });

        // Función para manejar errores de imágenes
        function handleImageError(img) {
            img.onerror = null; // Prevenir bucle infinito
            img.src = 'covers/default_product.svg';
            img.alt = 'Imagen no disponible';
        }

        // Aplicar manejo de errores a todas las imágenes
        document.addEventListener('DOMContentLoaded', function () {
            const images = document.querySelectorAll('.product-image');
            images.forEach(img => {
                img.addEventListener('error', () => handleImageError(img));
            });
        });

        // Función para filtrar productos (opcional para futuras mejoras)
        function filterProducts(category, searchTerm = '') {
            const cards = document.querySelectorAll('.product-card');
            const searchLower = searchTerm.toLowerCase();

            cards.forEach(card => {
                const title = card.querySelector('.product-title').textContent.toLowerCase();
                const author = card.querySelector('.product-author')?.textContent.toLowerCase() || '';
                const description = card.querySelector('.product-description')?.textContent.toLowerCase() || '';

                const matchesSearch = !searchTerm ||
                    title.includes(searchLower) ||
                    author.includes(searchLower) ||
                    description.includes(searchLower);

                if (matchesSearch) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Función para scroll suave al hacer clic en enlaces
        function smoothScroll(target) {
            document.querySelector(target).scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        // Lazy loading mejorado para imágenes
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // Función para mostrar/ocultar el botón de scroll to top
        window.addEventListener('scroll', function () {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollButton = document.getElementById('scroll-top');

            if (scrollButton) {
                if (scrollTop > 300) {
                    scrollButton.style.display = 'block';
                } else {
                    scrollButton.style.display = 'none';
                }
            }
        });

        // Función para volver arriba
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Función para manejar el cambio de tamaño de ventana
        window.addEventListener('resize', function () {
            // Reajustar elementos si es necesario
            const cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                card.style.height = 'auto';
            });
        });

        // Función para mostrar mensajes de estado
        function showMessage(message, type = 'info') {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message message-${type}`;
            messageDiv.textContent = message;

            // Estilos para el mensaje
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 5px;
                color: white;
                font-weight: 500;
                z-index: 1000;
                animation: slideIn 0.3s ease;
                max-width: 300px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;

            // Colores según el tipo
            switch (type) {
                case 'success':
                    messageDiv.style.backgroundColor = '#28a745';
                    break;
                case 'error':
                    messageDiv.style.backgroundColor = '#dc3545';
                    break;
                case 'warning':
                    messageDiv.style.backgroundColor = '#ffc107';
                    messageDiv.style.color = '#212529';
                    break;
                default:
                    messageDiv.style.backgroundColor = '#007bff';
            }

            document.body.appendChild(messageDiv);

            // Remover el mensaje después de 3 segundos
            setTimeout(() => {
                messageDiv.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.parentNode.removeChild(messageDiv);
                    }
                }, 300);
            }, 3000);
        }

        // Añadir estilos para las animaciones de mensajes
        const messageStyles = document.createElement('style');
        messageStyles.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(messageStyles);

        // Función mejorada para require login con mejor UX
        function requireLogin(type, id) {
            // Guardar información del producto en sessionStorage
            sessionStorage.setItem('pendingPurchase', JSON.stringify({
                type: type,
                id: id,
                action: 'add_to_cart',
                timestamp: Date.now()
            }));

            // Mostrar mensaje más amigable
            showMessage('Necesitas iniciar sesión para continuar', 'warning');

            // Redirigir después de un breve delay
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 1500);
        }

        // Función para limpiar datos expirados del sessionStorage
        function cleanExpiredData() {
            const pendingPurchase = sessionStorage.getItem('pendingPurchase');
            if (pendingPurchase) {
                try {
                    const data = JSON.parse(pendingPurchase);
                    const now = Date.now();
                    const expireTime = 30 * 60 * 1000; // 30 minutos

                    if (data.timestamp && (now - data.timestamp) > expireTime) {
                        sessionStorage.removeItem('pendingPurchase');
                    }
                } catch (e) {
                    sessionStorage.removeItem('pendingPurchase');
                }
            }
        }

        // Limpiar datos expirados al cargar la página
        document.addEventListener('DOMContentLoaded', cleanExpiredData);
    </script>
</body>

</html>