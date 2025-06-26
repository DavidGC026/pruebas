<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';

// Usar connectionw.php para webinars
require_once 'connectionw.php';
$database = new DatabaseW();

// Crear el modelo con la dependencia
require_once 'webinars/models.php';
$model = new WebinarModel($database);

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
        $webinar_id = filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);

        // Validación básica
        if (!$webinar_id || $quantity < 1) {
            throw new Exception("Parámetros inválidos");
        }

        // Obtener información del webinar
        $webinar = $model->getWebinarById($webinar_id);
        if (!$webinar || !isset($webinar['precio'])) {
            throw new Exception("El webinar solicitado no existe");
        }

        // Verificar si el usuario ya tiene acceso a este webinar
        if ($model->hasUserAccessToWebinar($user_id, $webinar_id)) {
            throw new Exception("Ya tienes acceso a este webinar. Puedes encontrarlo en tu biblioteca personal.");
        }

        // Lógica de acciones
        if (isset($_POST['add_to_cart'])) {
            if ($model->isWebinarInCart($user_id, $webinar_id)) {
                $mensaje = "⚠️ Ya tienes este webinar en el carrito";
            } else {
                $model->addToCart($user_id, $webinar_id, $quantity);
                $mensaje = "✅ Webinar agregado al carrito";
            }
        } elseif (isset($_POST['update_quantity'])) {
            $model->updateCartItem($user_id, $webinar_id, $quantity);
            $mensaje = "🔄 Cantidad actualizada";
        } elseif (isset($_POST['remove_item'])) {
            $model->removeCartItem($user_id, $webinar_id);
            $mensaje = "🗑️ Webinar eliminado del carrito";
        } elseif (isset($_POST['checkout'])) {
            // Lógica de pago
            $cartItems = $model->getCartItems($user_id);

            if (empty($cartItems)) {
                throw new Exception("El carrito está vacío");
            }

            // Calcular total
            $total = 0;
            foreach ($cartItems as $item) {
                $total += $item['cantidad'] * $item['precio_unitario'];
            }

            // Verificar nuevamente que no tenga acceso a ningún webinar del carrito
            foreach ($cartItems as $item) {
                if ($model->hasUserAccessToWebinar($user_id, $item['webinar_id'])) {
                    throw new Exception("Uno o más webinars en tu carrito ya tienes acceso. Por favor, revisa tu biblioteca.");
                }
            }

            // Preparar items para el pedido
            $orderItems = [];
            foreach ($cartItems as $item) {
                $orderItems[] = [
                    'webinar_id' => $item['webinar_id'],
                    'titulo' => $item['titulo'],
                    'cantidad' => $item['cantidad'],
                    'precio' => $item['precio_unitario'],
                    'tipo' => 'webinar'
                ];
            }

            // Generar order_id único
            $order_id = 'WEB_' . time() . '_' . $user_id;

            // Registrar pedido
            $model->registerOrder($user_id, $order_id);

            // Limpiar carrito
            $model->clearCart($user_id);

            header("Location: pedidos.php");
            exit;
        }

    }
    // Manejo de solicitudes GET
    elseif (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'view_webinar':
                $webinar_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

                // Verificar acceso
                if (!$model->hasUserAccessToWebinar($user_id, $webinar_id)) {
                    throw new Exception("No tienes acceso a este webinar");
                }

                $webinar = $model->getWebinarById($webinar_id);
                if (!$webinar) {
                    throw new Exception("Webinar no encontrado");
                }

                // Redirigir a la URL del webinar
                if (!empty($webinar['url_acceso'])) {
                    header("Location: " . $webinar['url_acceso']);
                    exit;
                } else {
                    throw new Exception("URL de acceso no disponible");
                }
                break;

            case 'filter_category':
                $category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING);
                // Esta lógica se maneja en la vista
                break;
        }
    }
} catch (Exception $e) {
    // Manejo de errores
    if (method_exists($model, 'rollBack')) {
        $model->rollBack();
    }
    error_log("Error en controlador webinars: " . $e->getMessage());
    $error = $e->getMessage();
}

// Obtener datos para la vista
try {
    // Obtener todos los webinars activos
    $webinars = $model->getActiveWebinars();

    // Agregar información de estado para cada webinar
    foreach ($webinars as &$webinar) {
        $webinar['user_has_access'] = $model->hasUserAccessToWebinar($user_id, $webinar['webinar_id']);
        $webinar['in_cart'] = $model->isWebinarInCart($user_id, $webinar['webinar_id']);
        $webinar['is_owned'] = $webinar['user_has_access']; // Para compatibilidad con la vista
    }

    $cartItems = $model->getCartItems($user_id);
    $cartItemCount = $model->getCartItemCount($user_id);
    $categories = $model->getCategories();

    // Filtrar por categoría si se especifica
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $selected_category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING);
        $webinars = $model->getWebinarsByCategory($selected_category);

        // Agregar información de estado para webinars filtrados
        foreach ($webinars as &$webinar) {
            $webinar['user_has_access'] = $model->hasUserAccessToWebinar($user_id, $webinar['webinar_id']);
            $webinar['in_cart'] = $model->isWebinarInCart($user_id, $webinar['webinar_id']);
            $webinar['is_owned'] = $webinar['user_has_access']; // Para compatibilidad con la vista
        }
    }

} catch (Exception $e) {
    error_log("Error cargando datos: " . $e->getMessage());
    $error = "Error al cargar los datos";

    // Valores por defecto en caso de error
    $webinars = [];
    $cartItems = [];
    $cartItemCount = 0;
    $categories = [];
}

// Cargar vista
require 'webinars/view.php';

// Limpiar mensajes después de mostrarlos
unset($_SESSION['error']);
unset($_SESSION['mensaje']);
?>