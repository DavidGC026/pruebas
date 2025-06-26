<?php
session_start();
require_once 'connection.php';     // Database para mercancía
require_once 'connectionl.php';    // Database2 para libros
require_once 'connectione.php';    // Database3 para ebooks
require_once 'connectionw.php';    // Database4 para webinars
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Conekta\Api\OrdersApi;
use Conekta\Model\OrderRequest;

// FIXED: Obtener todos los datos del usuario desde la base de datos
$user_id = $_SESSION['user_id'];

// Inicializar conexiones
$db = new Database();    // mercancía
$db2 = new Database2();  // libros
$db3 = new Database3();  // ebooks
$db4 = new DatabaseW();  // webinars

// FIXED: Obtener TODOS los datos del usuario desde la base de datos
$stmt = $db->pdo->prepare("SELECT email, nombre, telefono, calle, colonia, municipio, estado, codigo_postal, direccion_completa FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar que el usuario existe
if (!$userData) {
    die("Error: Usuario no encontrado");
}

// FIXED: Usar datos de la base de datos en lugar de la sesión
$email = $userData['email'];
$nombre = $userData['nombre'];
$telefono = $userData['telefono'] ?? '0000000000';
$calle = $userData['calle'] ?? 'Calle Ficticia 123';
$colonia = $userData['colonia'] ?? 'Colonia';
$municipio = $userData['municipio'] ?? 'Ciudad';
$estado = $userData['estado'] ?? 'Estado';
$codigo_postal = $userData['codigo_postal'] ?? '12345';
$direccion_completa = $userData['direccion_completa'] ?? $calle . ', ' . $colonia . ', ' . $municipio . ', ' . $estado;

// Verificar que el email existe (requerido por Conekta)
if (empty($email)) {
    die("Error: Email del usuario no encontrado. Por favor actualiza tu perfil.");
}

// Debug log
error_log("User data loaded - ID: $user_id, Email: $email, Nombre: $nombre");

try {
    // Iniciar transacciones
    $db->beginTransaction();
    $db2->beginTransaction();
    $db3->beginTransaction();
    $db4->beginTransaction();

    // Obtener IDs de carritos
    $carritoMercancia = $db->getOrCreateUserCart($user_id);
    $carritoLibros = $db2->getOrCreateUserCart($user_id);
    $carritoEbooks = $db3->getOrCreateUserCart($user_id);
    $carritoWebinars = $db4->getOrCreateUserCart($user_id);

    // Obtener ítems
    $itemsMercancia = $db->getCartItems($carritoMercancia);
    $itemsLibros = $db2->getCartItems($user_id);
    $itemsEbooks = $db3->getCartItems($user_id);
    $itemsWebinars = $db4->getCartItems($user_id);

    // Verificar que hay items en el carrito
    if (empty($itemsMercancia) && empty($itemsLibros) && empty($itemsEbooks) && empty($itemsWebinars)) {
        throw new Exception("No hay productos en el carrito.");
    }

    // Procesar ítems con cálculo de IVA
    $procesarItems = function (&$item, $tipo) {
        $item['tipo'] = $tipo;
        $precio = $item['precio'] ?? $item['precio_unitario'];
        
        // Calcular IVA (16%) solo para mercancía y ebooks
        $aplicaIva = in_array($tipo, ['mercancia', 'ebook']);
        $item['aplica_iva'] = $aplicaIva;
        
        if ($aplicaIva) {
            $item['subtotal_sin_iva'] = $precio * $item['cantidad'];
            $item['iva'] = $item['subtotal_sin_iva'] * 0.16;
            $item['subtotal'] = $item['subtotal_sin_iva'] + $item['iva'];
        } else {
            $item['subtotal_sin_iva'] = $precio * $item['cantidad'];
            $item['iva'] = 0;
            $item['subtotal'] = $item['subtotal_sin_iva'];
        }
        
        // Formatear para consistencia
        $item['subtotal'] = number_format($item['subtotal'], 2, '.', '');
        $item['subtotal_sin_iva'] = number_format($item['subtotal_sin_iva'], 2, '.', '');
        $item['iva'] = number_format($item['iva'], 2, '.', '');
        
        return $item;
    };

    array_walk($itemsMercancia, function (&$item) use ($procesarItems) {
        $procesarItems($item, 'mercancia');
    });

    array_walk($itemsLibros, function (&$item) use ($procesarItems) {
        $procesarItems($item, 'libro');
    });

    array_walk($itemsEbooks, function (&$item) use ($procesarItems) {
        $procesarItems($item, 'ebook');
    });

    array_walk($itemsWebinars, function (&$item) use ($procesarItems) {
        $procesarItems($item, 'webinar');
    });

    // Combinar todos los items
    $items = array_merge($itemsMercancia, $itemsLibros, $itemsEbooks, $itemsWebinars);
    
    // Calcular totales
    $subtotal_sin_iva = array_sum(array_column($items, 'subtotal_sin_iva'));
    $total_iva = array_sum(array_column($items, 'iva'));
    $total = array_sum(array_column($items, 'subtotal'));

    // Crear line_items para todos los productos
    $line_items = [];
    foreach ($items as $item) {
        $nombre_producto = $item['nombre'] ?? $item['titulo'];
        $precio_unitario = $item['precio'] ?? $item['precio_unitario'];

        $line_items[] = [
            'name' => $nombre_producto,
            'description' => 'Producto de Tienda IMCYC - ' . ucfirst($item['tipo']),
            'unit_price' => intval($precio_unitario * 100), // Conekta requiere centavos
            'quantity' => intval($item['cantidad'])
        ];
    }

    // Crear orden en Conekta (Efectivo) con TODOS los productos
    $conektaConfig = require __DIR__ . '/config/conekta.php';
    $apiInstance = new OrdersApi(null, $conektaConfig);

    // LOG para verificar datos del usuario
    error_log("DATOS USUARIO: telefono=$telefono, calle=$calle, colonia=$colonia, municipio=$municipio, estado=$estado, cp=$codigo_postal");

    // Asegurar que los datos de dirección estén completos
    $direccion_envio = [
        'street1' => !empty($calle) ? $calle : 'Calle no proporcionada',
        'city' => !empty($municipio) ? $municipio : 'Ciudad no proporcionada',
        'state' => !empty($estado) ? $estado : 'Estado no proporcionado',
        'country' => 'MX',
        'postal_code' => !empty($codigo_postal) ? $codigo_postal : '00000'
    ];

    // Si hay colonia, agregarla a street1
    if (!empty($colonia)) {
        $direccion_envio['street1'] = $calle . ', ' . $colonia;
    }

    //Proper Conekta OrderRequest structure for cash payments
    $orderData = new OrderRequest([
        'line_items' => $line_items,
        'currency' => 'MXN',
        'customer_info' => [
            'name' => $nombre,
            'email' => $email,
            'phone' => $telefono,
            'corporate' => false
        ],
        'shipping_contact' => [
            'phone' => $telefono,
            'receiver' => $nombre,
            'address' => $direccion_envio
        ],
        // Correct structure for cash payments
        'charges' => [
            [
                'payment_method' => [
                    'type' => 'cash',
                    'expires_at' => time() + (3 * 24 * 60 * 60) // 3 days expiration
                ]
            ]
        ],
        // Add metadata for better tracking
        'metadata' => [
            'user_id' => (string) $user_id,
            'payment_type' => 'cash',
            'created_at' => date('Y-m-d H:i:s'),
            'subtotal_sin_iva' => (string) $subtotal_sin_iva,
            'total_iva' => (string) $total_iva,
            'total_con_iva' => (string) $total
        ]
    ]);

    // CORREGIDO: Solo crear la orden UNA vez
    try {
        $conektaOrder = $apiInstance->createOrder($orderData);
        error_log("Orden de Conekta creada exitosamente: " . $conektaOrder->getId());

    } catch (Exception $e) {
        // Enhanced error logging
        error_log("Conekta API Error: " . $e->getMessage());
        error_log("Request data: " . json_encode([
            'customer_info' => [
                'name' => $nombre,
                'email' => $email,
                'phone' => $telefono
            ],
            'total' => $total,
            'line_items_count' => count($line_items)
        ]));

        $db->rollBack();
        $db2->rollBack();
        $db3->rollBack();
        $db4->rollBack();

        die("Error al procesar el pago: " . $e->getMessage());
    }

    // CORREGIDO: Manejo correcto de la respuesta para pagos en efectivo
    $orderResponse = json_decode(json_encode($conektaOrder), true);

    // Log para debug
    error_log("CONEKTA RESPONSE: " . json_encode($orderResponse));

    // Inicializar variables por defecto
    $referencia = 'N/D';
    $vence = date('d/m/Y H:i', time() + (3 * 24 * 60 * 60)); // 3 días por defecto

    // Obtener datos del método de pago correctamente
    if (isset($orderResponse['charges']['data'][0]['payment_method'])) {
        $paymentMethod = $orderResponse['charges']['data'][0]['payment_method'];

        // Para pagos en efectivo
        if ($paymentMethod['type'] === 'cash') {
            // La referencia puede venir en diferentes campos según la configuración
            $referencia = $paymentMethod['reference'] ??
                $orderResponse['charges']['data'][0]['id'] ??
                $conektaOrder->getId();

            // Fecha de expiración
            if (isset($paymentMethod['expires_at'])) {
                $vence = date('d/m/Y H:i', $paymentMethod['expires_at']);
            }
        }
    }

    // Si no se obtuvo referencia, usar el ID de la orden
    if ($referencia === 'N/D') {
        $referencia = $conektaOrder->getId();
    }

    // Registrar pedidos con order_id de Conekta
    $order_id = $conektaOrder->getId();

    if (!empty($itemsMercancia)) {
        $db->registerOrder($user_id, json_encode($itemsMercancia), array_sum(array_column($itemsMercancia, 'subtotal')), $order_id);
    }
    if (!empty($itemsLibros)) {
        $db2->registerOrder($user_id, json_encode($itemsLibros), array_sum(array_column($itemsLibros, 'subtotal')), $order_id);
    }
    if (!empty($itemsEbooks)) {
        $db3->registerOrder($user_id, json_encode($itemsEbooks), array_sum(array_column($itemsEbooks, 'subtotal')), $order_id);
    }
    if (!empty($itemsWebinars)) {
        $db4->registerOrder($user_id, json_encode($itemsWebinars), array_sum(array_column($itemsWebinars, 'subtotal')), $order_id);
    }

    // Vaciar carritos y confirmar
    $db->clearCart($user_id);
    $db2->clearCart($user_id);
    $db3->clearCart($user_id);
    $db4->clearCart($user_id);

    $db->commit();
    $db2->commit();
    $db3->commit();
    $db4->commit();

    // Enviar correos
    enviarConfirmacionCorreo($email, $nombre, $items, $subtotal_sin_iva, $total_iva, $total, $referencia, $vence);
    enviarAlertaVenta("tienda_correo@imcyc.com", $nombre, $items, $subtotal_sin_iva, $total_iva, $total);

    // Log de éxito
    error_log("PAGO EFECTIVO EXITOSO: Order ID: $order_id, Referencia: $referencia, Total: $total");

} catch (Exception $e) {
    $db->rollBack();
    $db2->rollBack();
    $db3->rollBack();
    $db4->rollBack();
    error_log("Error en proceso de pago efectivo: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    die("Ocurrió un error al procesar tu pago. Por favor intenta nuevamente.");
}

function enviarConfirmacionCorreo($para, $nombre, $items, $subtotal_sin_iva, $total_iva, $total, $referencia, $vence)
{
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.imcyc.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tienda_correo';
        $mail->Password = 'imcyc2025*';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        // IMPORTANTE: Configurar codificación UTF-8
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Configuración del remitente y destinatario
        $mail->setFrom('tiendaimcyc@imcyc.com', 'Tienda IMCYC');
        $mail->addAddress($para, $nombre);
        $mail->Subject = 'Confirmación de Pedido - Pago en Efectivo';

        // Construir resumen de productos
        $resumen = '';
        $contador = 1;
        $tipoIconos = [
            'mercancia' => '📦',
            'libro' => '📚',
            'ebook' => '💻',
            'webinar' => '🎥'
        ];

        foreach ($items as $item) {
            $nombreProducto = htmlspecialchars($item['nombre'] ?? $item['titulo'], ENT_QUOTES, 'UTF-8');
            $tipoProducto = ucfirst($item['tipo']);
            $icono = $tipoIconos[$item['tipo']] ?? '🛍️';
            $precio = number_format($item['precio'] ?? $item['precio_unitario'], 2);
            $subtotal = number_format($item['subtotal'], 2);
            $ivaItem = number_format($item['iva'], 2);

            // Información adicional para webinars
            $infoAdicional = '';
            if ($item['tipo'] === 'webinar') {
                if (!empty($item['fecha'])) {
                    $fechaWebinar = date('d/m/Y H:i', strtotime($item['fecha']));
                    $infoAdicional .= "<br><small style='color: #666;'>📅 {$fechaWebinar}</small>";
                }
                if (!empty($item['duracion'])) {
                    $infoAdicional .= "<br><small style='color: #666;'>⏱️ {$item['duracion']}</small>";
                }
            }

            $ivaInfo = $item['aplica_iva'] ? "<br><small style='color: #666;'>IVA: \${$ivaItem}</small>" : "<br><small style='color: #666;'>Exento de IVA</small>";

            $resumen .= "
                <tr style='border-bottom: 1px solid #eee;'>
                    <td style='padding: 10px; text-align: left;'>{$contador}</td>
                    <td style='padding: 10px; text-align: left;'>
                        {$icono} <strong>{$nombreProducto}</strong><br>
                        <small style='color: #666;'>Tipo: {$tipoProducto}</small>
                        {$infoAdicional}
                        {$ivaInfo}
                    </td>
                    <td style='padding: 10px; text-align: center;'>{$item['cantidad']}</td>
                    <td style='padding: 10px; text-align: right;'>\${$precio}</td>
                    <td style='padding: 10px; text-align: right; font-weight: bold;'>\${$subtotal}</td>
                </tr>
            ";
            $contador++;
        }

        $subtotalFormateado = number_format($subtotal_sin_iva, 2);
        $ivaFormateado = number_format($total_iva, 2);
        $totalFormateado = number_format($total, 2);
        $nombreCliente = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Confirmación de Pedido</title>
            </head>
            <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
                <div style='max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                    
                    <!-- Header -->
                    <div style='background: linear-gradient(135deg, #ff6600, #ff8533); color: white; padding: 30px; text-align: center;'>
                        <h1 style='margin: 0; font-size: 28px;'>¡Gracias por tu compra!</h1>
                        <p style='margin: 10px 0 0 0; font-size: 18px;'>Hola {$nombreCliente} 👋</p>
                    </div>
                    
                    <!-- Contenido principal -->
                    <div style='padding: 30px;'>
                        
                        <!-- Información del pedido -->
                        <div style='margin-bottom: 30px;'>
                            <h2 style='color: #333; border-bottom: 2px solid #ff6600; padding-bottom: 10px; margin-bottom: 20px;'>
                                📦 Resumen de tu Pedido
                            </h2>
                            
                            <table style='width: 100%; border-collapse: collapse; margin-bottom: 15px;'>
                                <thead>
                                    <tr style='background-color: #f8f9fa;'>
                                        <th style='padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;'>#</th>
                                        <th style='padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;'>Producto</th>
                                        <th style='padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;'>Cant.</th>
                                        <th style='padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6;'>Precio Unit.</th>
                                        <th style='padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6;'>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {$resumen}
                                </tbody>
                                <tfoot>
                                    <tr style='background-color: #f8f9fa;'>
                                        <td colspan='4' style='padding: 10px; text-align: right; font-weight: bold;'>Subtotal:</td>
                                        <td style='padding: 10px; text-align: right; font-weight: bold;'>\${$subtotalFormateado}</td>
                                    </tr>
                                    <tr style='background-color: #f8f9fa;'>
                                        <td colspan='4' style='padding: 10px; text-align: right; font-weight: bold;'>IVA (16%):</td>
                                        <td style='padding: 10px; text-align: right; font-weight: bold;'>\${$ivaFormateado}</td>
                                    </tr>
                                    <tr style='background-color: #fff3cd; font-weight: bold; font-size: 16px;'>
                                        <td colspan='4' style='padding: 15px; text-align: right; border-top: 2px solid #ff6600;'>TOTAL:</td>
                                        <td style='padding: 15px; text-align: right; border-top: 2px solid #ff6600; color: #ff6600;'>\${$totalFormateado}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <!-- Información de pago en efectivo -->
                        <div style='background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 25px; border-radius: 8px; margin: 25px 0; text-align: center;'>
                            <h3 style='margin: 0 0 15px 0; font-size: 22px;'>💰 Instrucciones de Pago</h3>
                            <div style='background: rgba(255,255,255,0.2); padding: 15px; border-radius: 5px; margin: 15px 0;'>
                                <p style='margin: 0 0 10px 0; font-size: 14px;'>Referencia de pago:</p>
                                <div style='font-size: 24px; font-weight: bold; letter-spacing: 2px; font-family: monospace;'>{$referencia}</div>
                            </div>
                            <div style='display: flex; justify-content: space-between; margin-top: 15px;'>
                                <div style='flex: 1;'>
                                    <strong>Monto a pagar:</strong><br>
                                    <span style='font-size: 18px;'>\${$totalFormateado}</span>
                                </div>
                                <div style='flex: 1;'>
                                    <strong>Fecha límite:</strong><br>
                                    <span style='font-size: 18px;'>{$vence}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pasos para pagar -->
                        <div style='background-color: #e3f2fd; border-left: 4px solid #2196f3; padding: 20px; margin: 25px 0;'>
                            <h4 style='color: #1976d2; margin: 0 0 15px 0;'>📋 ¿Cómo realizar tu pago?</h4>
                            <ol style='margin: 0; padding-left: 20px; line-height: 1.6;'>
                                <li><strong>Acude</strong> a cualquier tienda de conveniencia habilitada (OXXO, 7-Eleven, etc.)</li>
                                <li><strong>Proporciona</strong> la referencia de pago al cajero</li>
                                <li><strong>Realiza</strong> el pago en efectivo por el monto exacto</li>
                                <li><strong>Solicita</strong> y conserva tu comprobante de pago</li>
                                <li><strong>Envía</strong> una foto del comprobante a <a href='mailto:cursos@imcyc.com' style='color: #1976d2;'>cursos@imcyc.com</a></li>
                            </ol>
                        </div>
                        
                        <!-- Información importante -->
                        <div style='background-color: #fff8e1; border: 1px solid #ffc107; padding: 20px; border-radius: 5px; margin: 25px 0;'>
                            <h4 style='color: #f57c00; margin: 0 0 15px 0;'>⚠️ Información Importante</h4>
                            <ul style='margin: 0; padding-left: 20px; line-height: 1.6; color: #e65100;'>
                                <li>Una vez realizado el pago, <strong>envía tu comprobante</strong> a <a href='mailto:cursos@imcyc.com' style='color: #e65100;'>cursos@imcyc.com</a></li>
                                <li>Tu pedido será procesado <strong>después de confirmar</strong> tu pago</li>
                                <li>Conserva este correo como respaldo de tu compra</li>
                                <li>El tiempo de procesamiento es de 1-2 días hábiles después del pago</li>
                                <li><strong>IVA incluido</strong> en mercancía y ebooks según la legislación vigente</li>
                            </ul>
                        </div>
                        
                        <!-- Contacto -->
                        <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                            <p style='margin: 0 0 10px 0; color: #666;'>¿Tienes dudas sobre tu pedido?</p>
                            <p style='margin: 0;'>
                                <a href='mailto:cursos@imcyc.com' style='color: #ff6600; text-decoration: none; font-weight: bold;'>
                                    📧 cursos@imcyc.com
                                </a>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style='background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eee;'>
                        <p style='margin: 0; font-size: 12px; color: #666;'>
                            Este correo fue enviado automáticamente por el sistema de Tienda IMCYC.<br>
                            Por favor no respondas directamente a este mensaje.
                        </p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        error_log("Correo de confirmación efectivo enviado exitosamente a: $para");
        return true;

    } catch (Exception $e) {
        error_log("Error al enviar correo de confirmación: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
        return false;
    }
}

function enviarAlertaVenta($para, $cliente, $items, $subtotal_sin_iva, $total_iva, $total)
{
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.imcyc.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tienda_correo';
        $mail->Password = 'imcyc2025*';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        // IMPORTANTE: Configurar codificación UTF-8
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Configuración del remitente y destinatario
        $mail->setFrom('tiendaimcyc@imcyc.com', 'Sistema de Ventas IMCYC');
        $mail->addAddress($para);
        $mail->Subject = '🛒 Nueva Venta Realizada - Pago en Efectivo';

        // Construir resumen de productos para administrador
        $resumen = '';
        $contador = 1;
        $totalPorTipo = ['mercancia' => 0, 'libro' => 0, 'ebook' => 0, 'webinar' => 0];
        $ivaPorTipo = ['mercancia' => 0, 'libro' => 0, 'ebook' => 0, 'webinar' => 0];

        $tipoIconos = [
            'mercancia' => '📦',
            'libro' => '📚',
            'ebook' => '💻',
            'webinar' => '🎥'
        ];

        foreach ($items as $item) {
            $nombreProducto = htmlspecialchars($item['nombre'] ?? $item['titulo'], ENT_QUOTES, 'UTF-8');
            $tipoProducto = ucfirst($item['tipo']);
            $icono = $tipoIconos[$item['tipo']] ?? '🛍️';
            $precio = number_format($item['precio'] ?? $item['precio_unitario'], 2);
            $subtotal = number_format($item['subtotal'], 2);

            // Acumular por tipo
            $totalPorTipo[$item['tipo']] += floatval($item['subtotal']);
            $ivaPorTipo[$item['tipo']] += floatval($item['iva']);

            $colorTipo = match ($item['tipo']) {
                'mercancia' => '#007bff',
                'libro' => '#28a745',
                'ebook' => '#ffc107',
                'webinar' => '#dc3545',
                default => '#6c757d'
            };

            // Información adicional para webinars
            $infoAdicional = '';
            if ($item['tipo'] === 'webinar' && isset($item['fecha'])) {
                $fechaWebinar = date('d/m/Y H:i', strtotime($item['fecha']));
                $infoAdicional = "<br><small style='color: #666;'>📅 {$fechaWebinar}</small>";
            }

            $ivaInfo = $item['aplica_iva'] ? " (+ IVA)" : " (Exento)";

            $resumen .= "
                <tr style='border-bottom: 1px solid #eee;'>
                    <td style='padding: 8px; text-align: center; font-weight: bold;'>{$contador}</td>
                    <td style='padding: 8px;'>
                        {$icono} <strong>{$nombreProducto}</strong><br>
                        <span style='background-color: {$colorTipo}; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;'>
                            {$tipoProducto}{$ivaInfo}
                        </span>
                        {$infoAdicional}
                    </td>
                    <td style='padding: 8px; text-align: center; font-weight: bold;'>{$item['cantidad']}</td>
                    <td style='padding: 8px; text-align: right;'>\${$precio}</td>
                    <td style='padding: 8px; text-align: right; font-weight: bold; color: #28a745;'>\${$subtotal}</td>
                </tr>
            ";
            $contador++;
        }

        // Crear estadísticas por tipo
        $estadisticas = '';
        foreach ($totalPorTipo as $tipo => $totalTipo) {
            if ($totalTipo > 0) {
                $colorTipo = match ($tipo) {
                    'mercancia' => '#007bff',
                    'libro' => '#28a745',
                    'ebook' => '#ffc107',
                    'webinar' => '#dc3545',
                    default => '#6c757d'
                };

                $ivaInfo = $ivaPorTipo[$tipo] > 0 ? " (IVA: \$" . number_format($ivaPorTipo[$tipo], 2) . ")" : " (Exento)";

                $estadisticas .= "
                    <div style='display: inline-block; margin: 5px; padding: 10px 15px; background-color: {$colorTipo}; color: white; border-radius: 20px;'>
                        <strong>" . ucfirst($tipo) . ":</strong> \$" . number_format($totalTipo, 2) . "{$ivaInfo}
                    </div>
                ";
            }
        }

        $subtotalFormateado = number_format($subtotal_sin_iva, 2);
        $ivaFormateado = number_format($total_iva, 2);
        $totalFormateado = number_format($total, 2);
        $nombreCliente = htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8');
        $fechaActual = date('d/m/Y H:i:s');

        $mail->isHTML(true);
        $mail->Body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Nueva Venta - Pago Efectivo</title>
            </head>
            <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
                <div style='max-width: 700px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                    
                    <!-- Header -->
                    <div style='background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 25px; text-align: center;'>
                        <h1 style='margin: 0; font-size: 24px;'>🛒 Nueva Venta Registrada</h1>
                        <p style='margin: 10px 0 0 0; font-size: 16px;'>Pago en Efectivo - Pendiente de Confirmación</p>
                        <small style='opacity: 0.9;'>{$fechaActual}</small>
                    </div>
                    
                    <!-- Información del cliente -->
                    <div style='padding: 25px; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;'>
                        <div style='display: flex; justify-content: space-between; align-items: center;'>
                            <div>
                                <h3 style='margin: 0; color: #495057;'>👤 Cliente: <span style='color: #007bff;'>{$nombreCliente}</span></h3>
                            </div>
                            <div style='text-align: right;'>
                                <div style='background-color: #f8f9fa; padding: 10px; border-radius: 8px; border: 2px solid #28a745;'>
                                    <div style='font-size: 14px; color: #666;'>Subtotal: \${$subtotalFormateado}</div>
                                    <div style='font-size: 14px; color: #666;'>IVA (16%): \${$ivaFormateado}</div>
                                    <div style='font-size: 18px; font-weight: bold; color: #28a745;'>Total: \${$totalFormateado}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Estadísticas por tipo -->
                    <div style='padding: 20px; text-align: center; background-color: #fff;'>
                        <h4 style='margin: 0 0 15px 0; color: #495057;'>📊 Distribución por Categoría:</h4>
                        {$estadisticas}
                    </div>
                    
                    <!-- Detalles del pedido -->
                    <div style='padding: 25px;'>
                        <h3 style='color: #495057; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px;'>
                            📦 Detalles del Pedido
                        </h3>
                        
                        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                            <thead>
                                <tr style='background-color: #343a40; color: white;'>
                                    <th style='padding: 12px; text-align: center;'>#</th>
                                    <th style='padding: 12px; text-align: left;'>Producto</th>
                                    <th style='padding: 12px; text-align: center;'>Cant.</th>
                                    <th style='padding: 12px; text-align: right;'>P. Unit.</th>
                                    <th style='padding: 12px; text-align: right;'>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$resumen}
                            </tbody>
                            <tfoot>
                                <tr style='background-color: #f8f9fa;'>
                                    <td colspan='4' style='padding: 10px; text-align: right; font-weight: bold;'>SUBTOTAL:</td>
                                    <td style='padding: 10px; text-align: right; font-weight: bold;'>\${$subtotalFormateado}</td>
                                </tr>
                                <tr style='background-color: #f8f9fa;'>
                                    <td colspan='4' style='padding: 10px; text-align: right; font-weight: bold;'>IVA (16%):</td>
                                    <td style='padding: 10px; text-align: right; font-weight: bold;'>\${$ivaFormateado}</td>
                                </tr>
                                <tr style='background-color: #28a745; color: white; font-weight: bold; font-size: 16px;'>
                                    <td colspan='4' style='padding: 15px; text-align: right;'>TOTAL GENERAL:</td>
                                    <td style='padding: 15px; text-align: right;'>\${$totalFormateado}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- Información importante -->
                    <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; margin: 0 25px 25px 25px; border-radius: 5px;'>
                        <h4 style='color: #856404; margin: 0 0 15px 0;'>⚠️ Acción Requerida</h4>
                        <ul style='margin: 0; padding-left: 20px; color: #856404; line-height: 1.6;'>
                            <li><strong>El cliente debe realizar el pago en efectivo</strong> en tienda de conveniencia</li>
                            <li><strong>Debe enviar comprobante</strong> a cursos@imcyc.com</li>
                            <li><strong>Procesar pedido solo después</strong> de confirmar el pago</li>
                            <li><strong>Tiempo límite:</strong> 3 días para realizar el pago</li>
                            <li><strong>IVA aplicado</strong> según normativa fiscal vigente</li>
                        </ul>
                    </div>
                    
                    <!-- Acciones rápidas -->
                    <div style='padding: 20px 25px; background-color: #f8f9fa; text-align: center;'>
                        <h4 style='margin: 0 0 15px 0; color: #495057;'>🔧 Próximos Pasos</h4>
                        <p style='margin: 0; color: #6c757d; line-height: 1.5;'>
                            Mantente atento a los comprobantes de pago que lleguen a 
                            <strong>cursos@imcyc.com</strong> para procesar este pedido.
                        </p>
                    </div>
                    
                    <!-- Footer -->
                    <div style='background-color: #343a40; color: white; padding: 15px; text-align: center;'>
                        <p style='margin: 0; font-size: 12px;'>
                            Sistema Automatizado de Notificaciones - Tienda IMCYC<br>
                            <small style='opacity: 0.7;'>Este correo se genera automáticamente para cada venta registrada</small>
                        </p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        error_log("Alerta de venta efectivo enviada exitosamente a: $para");
        return true;

    } catch (Exception $e) {
        error_log("Error al enviar alerta de venta: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Confirmación de Compra - Efectivo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .comprobante {
            max-width: 800px;
        }

        .producto-tipo {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .badge {
            font-size: 0.95rem;
        }

        .alert ul {
            padding-left: 1.2rem;
        }

        .btn-outline-secondary:hover {
            background-color: #e2e6ea;
            color: #000;
        }

        .referencia-box {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }

        .referencia-numero {
            font-size: 1.8rem;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 10px 0;
            padding: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
        }

        .webinar-info {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .iva-info {
            font-size: 0.8rem;
            color: #28a745;
            font-weight: bold;
        }

        .no-iva-info {
            font-size: 0.8rem;
            color: #6c757d;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .comprobante,
            .comprobante * {
                visibility: visible;
            }

            .comprobante {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
            }

            .btn,
            .d-grid {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="comprobante bg-white p-5 shadow-sm rounded mx-auto mt-5">
        <h2 class="text-center text-primary mb-4">¡Gracias por tu compra, <?= htmlspecialchars($nombre) ?>! 🛒</h2>

        <div class="mb-4">
            <h4 class="mb-3 border-bottom pb-2">📦 Resumen del Pedido</h4>
            <ul class="list-group">
                <?php foreach ($items as $item): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <strong><?= htmlspecialchars($item['nombre'] ?? $item['titulo']) ?></strong>
                                <span class="producto-tipo d-block">(<?= ucfirst($item['tipo']) ?>)</span>
                                
                                <?php if ($item['tipo'] === 'webinar'): ?>
                                    <?php if (!empty($item['fecha'])): ?>
                                        <div class="webinar-info">📅 <?= date('d/m/Y H:i', strtotime($item['fecha'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['duracion'])): ?>
                                        <div class="webinar-info">⏱️ <?= htmlspecialchars($item['duracion']) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($item['aplica_iva']): ?>
                                    <div class="iva-info">✓ IVA incluido</div>
                                <?php else: ?>
                                    <div class="no-iva-info">○ Exento de IVA</div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary rounded-pill mb-1">
                                    <?= $item['cantidad'] ?> x $<?= number_format($item['precio'] ?? $item['precio_unitario'], 2) ?>
                                </span>
                                <div class="fw-bold">$<?= number_format($item['subtotal'], 2) ?></div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <!-- Resumen de totales -->
            <div class="mt-3">
                <div class="row">
                    <div class="col-md-6 offset-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Subtotal:</strong></td>
                                <td class="text-end"><strong>$<?= number_format($subtotal_sin_iva, 2) ?></strong></td>
                            </tr>
                            <tr>
                                <td><strong>IVA (16%):</strong></td>
                                <td class="text-end"><strong>$<?= number_format($total_iva, 2) ?></strong></td>
                            </tr>
                            <tr class="table-success">
                                <td><strong>TOTAL:</strong></td>
                                <td class="text-end"><strong>$<?= number_format($total, 2) ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="referencia-box">
            <h3>💰 Paga en Efectivo</h3>
            <p class="mb-1">Referencia de pago:</p>
            <div class="referencia-numero"><?= $referencia ?></div>
            <p class="mb-1"><strong>Total: $<?= number_format($total, 2) ?></strong></p>
            <p class="mb-0">Vence: <?= $vence ?></p>
        </div>

        <div class="alert alert-info">
            <h5 class="alert-heading">📋 Instrucciones:</h5>
            <ol class="mb-2">
                <li>Ve a cualquier <strong>tienda de conveniencia</strong> habilitada</li>
                <li>Proporciona la referencia al cajero</li>
                <li>Paga el monto exacto en efectivo</li>
                <li>Solicita tu comprobante de pago</li>
            </ol>
            <hr>
            <p class="mb-0">📧 <strong>Importante:</strong> Envía tu comprobante a
                <a href="mailto:cursos@imcyc.com">cursos@imcyc.com</a> para procesar tu pedido.
            </p>
        </div>

        <div class="alert alert-warning">
            <h6 class="alert-heading">📋 Información Fiscal:</h6>
            <ul class="mb-0">
                <li><strong>Mercancía y Ebooks:</strong> IVA del 16% incluido</li>
                <li><strong>Libros y Webinars:</strong> Exentos de IVA según legislación vigente</li>
                <li>El desglose fiscal se muestra en el resumen de compra</li>
            </ul>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
            <a href="dashboard.php" class="btn btn-success px-4">Volver al Inicio</a>
            <button onclick="window.print()" class="btn btn-outline-secondary px-4">Imprimir Comprobante</button>
        </div>
    </div>
</body>

</html>