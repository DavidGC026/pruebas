<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';

// 1. PRIMERO CREAMOS LA INSTANCIA DE DATABASE3
require_once 'connectione.php'; // Asegurar que está incluido
$database = new Database3();

// 2. LUEGO CREAMOS EL MODELO CON LA DEPENDENCIA
require_once 'ebooks/models.php';
$model = new EbookModel($database); // ¡Aquí está el cambio clave!

$user_id = $_SESSION['user_id'];
$mensaje = '';
$error = '';

// Generación de CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    // Manejo de solicitudes POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validación CSRF
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Token de seguridad inválido");
        }

        // Sanitización de inputs
        $ebook_id = filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);

        // Validación básica
        if (!$ebook_id || $quantity < 1) {
            throw new Exception("Parámetros inválidos");
        }

        // Obtener información del ebook
        $ebook = $model->getEbookById($ebook_id);
        if (!$ebook || !isset($ebook['precio'])) {
            throw new Exception("El ebook solicitado no existe");
        }

        // Verificar si el usuario ya posee este ebook antes de cualquier acción
        if ($model->isEbookOwnedByUser($user_id, $ebook_id)) {
            throw new Exception("Ya posees este ebook. Puedes encontrarlo en tu biblioteca personal.");
        }

        // Inicio de transacción
        $model->beginTransaction();
        $cart_id = $model->getOrCreateUserCart($user_id);

        // Lógica de acciones
        if (isset($_POST['add_to_cart'])) {
            if ($model->isEbookInCart($user_id, $ebook_id)) {
                $mensaje = "⚠️ Ya tienes este ebook en el carrito";
            } else {
                $model->addToCart($user_id, $ebook_id, $quantity, $ebook['precio']);
                $mensaje = "✅ Ebook agregado al carrito";
            }
        } elseif (isset($_POST['update_quantity'])) {
            $model->updateCartItem($user_id, $ebook_id, $quantity);
            $mensaje = "🔄 Cantidad actualizada";
        } elseif (isset($_POST['remove_item'])) {
            $model->removeCartItem($user_id, $ebook_id);
            $mensaje = "🗑️ Ebook eliminado del carrito";
        } elseif (isset($_POST['checkout'])) {
            // Lógica de pago
            $cartItems = $model->getCartItems($user_id);
            $total = $model->getCartTotal($cart_id);

            // Verificar nuevamente que no posea ningún ebook del carrito
            foreach ($cartItems as $item) {
                if ($model->isEbookOwnedByUser($user_id, $item['ebook_id'])) {
                    throw new Exception("Uno o más ebooks en tu carrito ya los posees. Por favor, revisa tu biblioteca.");
                }
            }

            // Registrar pedido
            $model->registerOrder(
                $user_id,
                json_encode($cartItems),
                $total
            );

            // Limpiar carrito
            $model->clearCart($user_id);

            header("Location: pedidos.php");
            exit;
        }

        // Actualizar totales y confirmar cambios
        $model->commit();

    }
    // Manejo de solicitudes GET
    elseif (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'download_sample':
                $ebook_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
                $ebook = $model->getEbookById($ebook_id);

                if ($ebook && file_exists($ebook['muestra_path'])) {
                    header("Content-Type: application/pdf");
                    header("Content-Disposition: attachment; filename=" . basename($ebook['muestra_path']));
                    readfile($ebook['muestra_path']);
                    exit;
                } else {
                    throw new Exception("Archivo no disponible");
                }
                break;
        }
    }
} catch (Exception $e) {
    // Manejo de errores
    $model->rollBack();
    error_log("Error en controlador ebooks: " . $e->getMessage());
    $error = $e->getMessage();
}

// Obtener datos para la vista
try {
    // CAMBIO CLAVE: Usar el método que incluye el estado de propiedad
    $ebooks = $model->getAllEbooksWithOwnershipStatus($user_id);
    $cartItems = $model->getCartItems($user_id);
    $cartItemCount = $model->getCartItemCount($user_id);
} catch (Exception $e) {
    error_log("Error cargando datos: " . $e->getMessage());
    $error = "Error al cargar los datos";
}

// Cargar vista
require 'ebooks/view.php';

// Limpiar mensajes después de mostrarlos
unset($_SESSION['error']);
unset($_SESSION['mensaje']);