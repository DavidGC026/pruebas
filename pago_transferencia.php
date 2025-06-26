<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Configuración inicial - con verificación de variables de sesión
$user_id = $_SESSION['user_id'];

// Inicializar conexiones
$db = new Database();    // mercancía
$db2 = new Database2();  // libros
$db3 = new Database3();  // ebooks
$db4 = new DatabaseW();  // webinars

// Obtener información del usuario desde la base de datos (incluyendo email y nombre)
$stmt = $db->pdo->prepare("SELECT nombre, email, telefono, calle, colonia, municipio, estado, codigo_postal, direccion_completa FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    die("Error: Usuario no encontrado en la base de datos.");
}

// Usar datos del usuario de la base de datos (no de la sesión)
$email = $userData['email'];
$nombre = $userData['nombre'];
$telefono = $userData['telefono'] ?? '0000000000';
$calle = $userData['calle'] ?? 'Calle Ficticia 123';
$colonia = $userData['colonia'] ?? 'Colonia';
$municipio = $userData['municipio'] ?? 'Ciudad';
$estado = $userData['estado'] ?? 'Estado';
$codigo_postal = $userData['codigo_postal'] ?? '12345';
$direccion_completa = $userData['direccion_completa'] ?? $calle . ', ' . $colonia . ', ' . $municipio . ', ' . $estado;

// Validación adicional de campos requeridos
if (empty($email) || empty($nombre)) {
    die("Error: Faltan datos del usuario (email o nombre). Por favor, completa tu perfil.");
}

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

    // Crear orden en Conekta (SPEI) con TODOS los productos
    $conektaConfig = require __DIR__ . '/config/conekta.php';
    $apiInstance = new OrdersApi(null, $conektaConfig);

    // LOG para verificar datos del usuario
    error_log("DATOS USUARIO: email=$email, nombre=$nombre, telefono=$telefono, calle=$calle, colonia=$colonia, municipio=$municipio, estado=$estado, cp=$codigo_postal");

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

    // Validar que el teléfono tenga formato correcto (10 dígitos)
    if (!preg_match('/^\d{10}$/', $telefono)) {
        $telefono = '5555555555'; // Teléfono por defecto si no es válido
        error_log("Teléfono inválido, usando teléfono por defecto");
    }

    $orderData = new OrderRequest([
        'line_items' => $line_items,
        'currency' => 'MXN',
        'customer_info' => [
            'name' => $nombre,
            'email' => $email,
            'phone' => $telefono
        ],
        'shipping_contact' => [
            'phone' => $telefono,
            'receiver' => $nombre,
            'address' => $direccion_envio
        ],
        'charges' => [
            [
                'payment_method' => [
                    'type' => 'spei'
                ]
            ]
        ],
        // Add metadata for better tracking
        'metadata' => [
            'user_id' => (string) $user_id,
            'payment_type' => 'spei',
            'created_at' => date('Y-m-d H:i:s'),
            'subtotal_sin_iva' => (string) $subtotal_sin_iva,
            'total_iva' => (string) $total_iva,
            'total_con_iva' => (string) $total
        ]
    ]);

    $conektaOrder = $apiInstance->createOrder($orderData);
    $speiData = json_decode(json_encode($conektaOrder), true)['charges']['data'][0]['payment_method'];

    $clabe = $speiData['clabe'] ?? 'N/D';
    $banco = $speiData['bank'] ?? $speiData['receiving_account_bank'] ?? 'N/D';
    $cuenta = $speiData['receiving_account_number'] ?? 'N/D';
    $beneficiario = $speiData['receiving_account_holder_name'] ?? 'N/D';
    $referencia = $speiData['reference_number'] ?? 'N/D';
    $vence = date('d/m/Y H:i', $speiData['expires_at'] ?? time());

    // Registrar pedidos con order_id de Conekta
    $order_id = $conektaOrder->getId(); // Obtener el ID de la orden generada por Conekta

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
    enviarConfirmacionCorreo($email, $nombre, $items, $subtotal_sin_iva, $total_iva, $total, $banco, $clabe, $cuenta, $beneficiario, $referencia, $vence);
    enviarAlertaVenta("tienda_correo@imcyc.com", $nombre, $items, $subtotal_sin_iva, $total_iva, $total);

} catch (Exception $e) {
    $db->rollBack();
    $db2->rollBack();
    $db3->rollBack();
    $db4->rollBack();
    error_log("Error en proceso de pago: " . $e->getMessage());
    die("Ocurrió un error al procesar tu pago: " . $e->getMessage());
}

function enviarConfirmacionCorreo($para, $nombre, $items, $subtotal_sin_iva, $total_iva, $total, $banco, $clabe, $cuenta, $beneficiario, $referencia, $vence)
{
    $mail = new PHPMailer(true);
    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.imcyc.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tienda_correo';
        $mail->Password = 'imcyc2025*';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Configuración de codificación UTF-8
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Configuración del remitente y destinatario
        $mail->setFrom('tiendaimcyc@imcyc.com', 'Tienda IMCYC');
        $mail->addAddress($para, $nombre);
        $mail->Subject = 'Confirmación de Pedido - Transferencia SPEI';

        // Generar resumen de productos
        $resumen = '';
        $tipoIconos = [
            'mercancia' => '📦',
            'libro' => '📚',
            'ebook' => '💻',
            'webinar' => '🎥'
        ];

        foreach ($items as $item) {
            $nombreProducto = htmlspecialchars($item['nombre'] ?? $item['titulo'], ENT_QUOTES, 'UTF-8');
            $tipo = $item['tipo'];
            $icono = $tipoIconos[$tipo] ?? '🛍️';
            $subtotal = number_format($item['subtotal'], 2);
            $ivaItem = number_format($item['iva'], 2);

            // Información adicional para webinars
            $infoAdicional = '';
            if ($tipo === 'webinar') {
                if (!empty($item['fecha'])) {
                    $fechaWebinar = date('d/m/Y H:i', strtotime($item['fecha']));
                    $infoAdicional .= "<br><small style='color: #666;'>📅 {$fechaWebinar}</small>";
                }
                if (!empty($item['duracion'])) {
                    $infoAdicional .= "<br><small style='color: #666;'>⏱️ {$item['duracion']}</small>";
                }
            }

            $ivaInfo = $item['aplica_iva'] ? 
                "<br><small style='color: #28a745;'>✓ IVA: \${$ivaItem}</small>" : 
                "<br><small style='color: #6c757d;'>○ Exento de IVA</small>";

            $resumen .= "<tr>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'>
                    {$icono} {$nombreProducto}
                    <br><small style='color: #666;'>" . ucfirst($tipo) . "</small>
                    {$infoAdicional}
                    {$ivaInfo}
                </td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>
                    {$item['cantidad']}
                </td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;'>
                    \${$subtotal}
                </td>
            </tr>";
        }

        $subtotalFormateado = number_format($subtotal_sin_iva, 2);
        $ivaFormateado = number_format($total_iva, 2);
        $totalFormateado = number_format($total, 2);
        $nombreSeguro = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $bancoSeguro = htmlspecialchars($banco, ENT_QUOTES, 'UTF-8');
        $clabeSeguro = htmlspecialchars($clabe, ENT_QUOTES, 'UTF-8');
        $cuentaSeguro = htmlspecialchars($cuenta, ENT_QUOTES, 'UTF-8');
        $beneficiarioSeguro = htmlspecialchars($beneficiario, ENT_QUOTES, 'UTF-8');
        $referenciaSeguro = htmlspecialchars($referencia, ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Body = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Confirmación de Pedido</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff;'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #2c5aa0 0%, #1e3f73 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 28px;'>¡Gracias por tu compra! 🎉</h1>
                    <p style='margin: 10px 0 0 0; font-size: 18px;'>Hola, {$nombreSeguro}</p>
                </div>

                <!-- Contenido principal -->
                <div style='padding: 30px;'>
                    <!-- Total destacado -->
                    <div style='background-color: #f8f9fa; border: 2px solid #28a745; border-radius: 10px; padding: 20px; text-align: center; margin-bottom: 30px;'>
                        <div style='margin-bottom: 10px;'>
                            <div style='font-size: 16px; color: #666;'>Subtotal: \${$subtotalFormateado}</div>
                            <div style='font-size: 16px; color: #666;'>IVA (16%): \${$ivaFormateado}</div>
                        </div>
                        <h2 style='color: #28a745; margin: 0; font-size: 24px;'>Total del pedido: \${$totalFormateado}</h2>
                    </div>

                    <!-- Resumen de productos -->
                    <div style='margin-bottom: 30px;'>
                        <h3 style='color: #2c5aa0; border-bottom: 2px solid #2c5aa0; padding-bottom: 10px;'>
                            📋 Resumen de productos
                        </h3>
                        <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                            <thead>
                                <tr style='background-color: #f8f9fa;'>
                                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #ddd;'>Producto</th>
                                    <th style='padding: 12px; text-align: center; border-bottom: 2px solid #ddd;'>Cantidad</th>
                                    <th style='padding: 12px; text-align: right; border-bottom: 2px solid #ddd;'>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$resumen}
                            </tbody>
                            <tfoot>
                                <tr style='background-color: #f8f9fa;'>
                                    <td colspan='2' style='padding: 10px; text-align: right; font-weight: bold;'>Subtotal:</td>
                                    <td style='padding: 10px; text-align: right; font-weight: bold;'>\${$subtotalFormateado}</td>
                                </tr>
                                <tr style='background-color: #f8f9fa;'>
                                    <td colspan='2' style='padding: 10px; text-align: right; font-weight: bold;'>IVA (16%):</td>
                                    <td style='padding: 10px; text-align: right; font-weight: bold;'>\${$ivaFormateado}</td>
                                </tr>
                                <tr style='background-color: #28a745; color: white;'>
                                    <td colspan='2' style='padding: 15px; text-align: right; font-weight: bold; font-size: 16px;'>TOTAL:</td>
                                    <td style='padding: 15px; text-align: right; font-weight: bold; font-size: 16px;'>\${$totalFormateado}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Información de pago SPEI -->
                    <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 2px solid #ffc107; border-radius: 10px; padding: 25px; margin-bottom: 25px;'>
                        <h3 style='color: #856404; margin-top: 0; font-size: 20px;'>
                            💳 Instrucciones de pago SPEI
                        </h3>
                        
                        <div style='background-color: rgba(255,255,255,0.7); border-radius: 8px; padding: 20px; margin: 15px 0;'>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 8px 0; font-weight: bold; color: #495057; width: 30%;'>🏦 Banco:</td>
                                    <td style='padding: 8px 0; color: #212529;'>{$bancoSeguro}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; font-weight: bold; color: #495057;'>🔢 CLABE:</td>
                                    <td style='padding: 8px 0; font-family: monospace; font-size: 16px; color: #212529; background-color: #e9ecef; padding: 5px; border-radius: 4px;'>{$clabeSeguro}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; font-weight: bold; color: #495057;'>💰 Cuenta:</td>
                                    <td style='padding: 8px 0; color: #212529;'>{$cuentaSeguro}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; font-weight: bold; color: #495057;'>👤 Beneficiario:</td>
                                    <td style='padding: 8px 0; color: #212529;'>{$beneficiarioSeguro}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; font-weight: bold; color: #495057;'>🔖 Referencia:</td>
                                    <td style='padding: 8px 0; font-family: monospace; font-size: 16px; color: #212529; background-color: #e9ecef; padding: 5px; border-radius: 4px;'>{$referenciaSeguro}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; font-weight: bold; color: #495057;'>⏰ Vence:</td>
                                    <td style='padding: 8px 0; color: #dc3545; font-weight: bold;'>{$vence}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Instrucciones importantes -->
                    <div style='background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); border: 2px solid #17a2b8; border-radius: 10px; padding: 20px; margin-bottom: 25px;'>
                        <h4 style='color: #0c5460; margin-top: 0;'>📧 Instrucciones importantes:</h4>
                        <ul style='color: #0c5460; margin: 10px 0; padding-left: 20px;'>
                            <li style='margin-bottom: 8px;'>Realiza tu pago SPEI con los datos proporcionados</li>
                            <li style='margin-bottom: 8px;'><strong>Envía tu comprobante de pago a:</strong> 
                                <a href='mailto:cursos@imcyc.com' style='color: #0c5460; text-decoration: none; font-weight: bold;'>cursos@imcyc.com</a>
                            </li>
                            <li style='margin-bottom: 8px;'>Incluye tu nombre completo en el asunto del correo</li>
                            <li style='margin-bottom: 8px;'>Una vez verificado el pago, procesaremos tu pedido</li>
                            <li><strong>IVA incluido</strong> en mercancía y ebooks según legislación vigente</li>
                        </ul>
                    </div>

                    <!-- Información de contacto -->
                    <div style='text-align: center; padding: 20px; background-color: #f8f9fa; border-radius: 8px;'>
                        <p style='margin: 0; color: #6c757d; font-size: 14px;'>
                            ¿Tienes dudas? Contáctanos en: 
                            <a href='mailto:cursos@imcyc.com' style='color: #2c5aa0;'>cursos@imcyc.com</a>
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div style='background-color: #2c5aa0; color: white; padding: 20px; text-align: center;'>
                    <p style='margin: 0; font-size: 14px;'>
                        Este correo fue enviado automáticamente por la Tienda IMCYC<br>
                        <strong>Instituto Mexicano del Cemento y del Concreto</strong><br>
                        " . date('d/m/Y H:i:s') . "
                    </p>
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
        error_log("Correo de confirmación enviado exitosamente a: " . $para);
        return true;

    } catch (Exception $e) {
        error_log("Error al enviar correo de confirmación: " . $e->getMessage());
        return false;
    }
}

function enviarAlertaVenta($para, $cliente, $items, $subtotal_sin_iva, $total_iva, $total)
{
    $mail = new PHPMailer(true);
    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.imcyc.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'tienda_correo';
        $mail->Password = 'imcyc2025*';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Configuración de codificación UTF-8
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Configuración del remitente y destinatario
        $mail->setFrom('tiendaimcyc@imcyc.com', 'Notificaciones Tienda IMCYC');
        $mail->addAddress($para);
        $mail->Subject = '🛒 Nueva Venta Realizada - Transferencia SPEI';

        // Generar resumen de productos para administrador
        $resumen = '';
        $totalPorTipo = [
            'mercancia' => 0,
            'libro' => 0,
            'ebook' => 0,
            'webinar' => 0
        ];
        $ivaPorTipo = [
            'mercancia' => 0,
            'libro' => 0,
            'ebook' => 0,
            'webinar' => 0
        ];

        $tipoIconos = [
            'mercancia' => '📦',
            'libro' => '📚',
            'ebook' => '💻',
            'webinar' => '🎥'
        ];

        foreach ($items as $item) {
            $nombreProducto = htmlspecialchars($item['nombre'] ?? $item['titulo'], ENT_QUOTES, 'UTF-8');
            $tipo = $item['tipo'];
            $icono = $tipoIconos[$tipo] ?? '🛍️';
            $subtotal = floatval($item['subtotal']);
            $totalPorTipo[$tipo] += $subtotal;
            $ivaPorTipo[$tipo] += floatval($item['iva']);

            // Información adicional para webinars
            $infoAdicional = '';
            if ($tipo === 'webinar' && isset($item['fecha'])) {
                $fechaWebinar = date('d/m/Y H:i', strtotime($item['fecha']));
                $infoAdicional = "<br><small style='color: #666;'>📅 {$fechaWebinar}</small>";
            }

            $ivaInfo = $item['aplica_iva'] ? " (+ IVA)" : " (Exento)";

            $resumen .= "<tr>
                <td style='padding: 8px; border-bottom: 1px solid #eee;'>
                    {$icono} {$nombreProducto}
                    {$infoAdicional}
                </td>
                <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: center;'>
                    " . ucfirst($tipo) . "{$ivaInfo}
                </td>
                <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: center;'>
                    {$item['cantidad']}
                </td>
                <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;'>
                    \$" . number_format($subtotal, 2) . "
                </td>
            </tr>";
        }

        // Generar resumen por tipo
        $resumenTipos = '';
        foreach ($totalPorTipo as $tipo => $totalTipo) {
            if ($totalTipo > 0) {
                $icono = $tipoIconos[$tipo];
                $ivaInfo = $ivaPorTipo[$tipo] > 0 ? " (IVA: \$" . number_format($ivaPorTipo[$tipo], 2) . ")" : " (Exento)";
                $resumenTipos .= "<tr>
                    <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$icono} " . ucfirst($tipo) . "</td>
                    <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right; font-weight: bold;'>\$" . number_format($totalTipo, 2) . "{$ivaInfo}</td>
                </tr>";
            }
        }

        $subtotalFormateado = number_format($subtotal_sin_iva, 2);
        $ivaFormateado = number_format($total_iva, 2);
        $totalFormateado = number_format($total, 2);
        $clienteSeguro = htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8');
        $fechaHora = date('d/m/Y H:i:s');

        $mail->isHTML(true);
        $mail->Body = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Nueva Venta - Tienda IMCYC</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
            <div style='max-width: 700px; margin: 0 auto; background-color: #ffffff;'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 25px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 26px;'>🛒 Nueva Venta Realizada</h1>
                    <p style='margin: 10px 0 0 0; font-size: 16px;'>Transferencia SPEI - {$fechaHora}</p>
                </div>

                <!-- Información del cliente -->
                <div style='padding: 25px;'>
                    <div style='background-color: #f8f9fa; border-left: 4px solid #28a745; padding: 20px; margin-bottom: 25px;'>
                        <h3 style='color: #155724; margin-top: 0;'>👤 Información del Cliente</h3>
                        <p style='margin: 5px 0; font-size: 18px;'><strong>Cliente:</strong> {$clienteSeguro}</p>
                        <div style='margin-top: 15px;'>
                            <div style='font-size: 16px; color: #666;'>Subtotal: \${$subtotalFormateado}</div>
                            <div style='font-size: 16px; color: #666;'>IVA (16%): \${$ivaFormateado}</div>
                            <div style='font-size: 20px; color: #28a745; font-weight: bold;'>Total: \${$totalFormateado}</div>
                        </div>
                    </div>

                    <!-- Resumen por tipo de producto -->
                    <div style='margin-bottom: 25px;'>
                        <h3 style='color: #495057; border-bottom: 2px solid #28a745; padding-bottom: 10px;'>
                            📊 Resumen por Categoría
                        </h3>
                        <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                            <thead>
                                <tr style='background-color: #e9ecef;'>
                                    <th style='padding: 12px; text-align: left; border-bottom: 2px solid #ddd;'>Categoría</th>
                                    <th style='padding: 12px; text-align: right; border-bottom: 2px solid #ddd;'>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$resumenTipos}
                            </tbody>
                        </table>
                    </div>

                    <!-- Productos vendidos -->
                    <div style='margin-bottom: 25px;'>
                        <h3 style='color: #495057; border-bottom: 2px solid #28a745; padding-bottom: 10px;'>
                            🛍️ Productos Vendidos
                        </h3>
                        <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                            <thead>
                                <tr style='background-color: #e9ecef;'>
                                    <th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Producto</th>
                                    <th style='padding: 10px; text-align: center; border-bottom: 2px solid #ddd;'>Tipo</th>
                                    <th style='padding: 10px; text-align: center; border-bottom: 2px solid #ddd;'>Cantidad</th>
                                    <th style='padding: 10px; text-align: right; border-bottom: 2px solid #ddd;'>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$resumen}
                            </tbody>
                            <tfoot>
                                <tr style='background-color: #f8f9fa;'>
                                    <td colspan='3' style='padding: 10px; text-align: right; font-weight: bold;'>SUBTOTAL:</td>
                                    <td style='padding: 10px; text-align: right; font-weight: bold;'>\${$subtotalFormateado}</td>
                                </tr>
                                <tr style='background-color: #f8f9fa;'>
                                    <td colspan='3' style='padding: 10px; text-align: right; font-weight: bold;'>IVA (16%):</td>
                                    <td style='padding: 10px; text-align: right; font-weight: bold;'>\${$ivaFormateado}</td>
                                </tr>
                                <tr style='background-color: #28a745; color: white; font-weight: bold; font-size: 16px;'>
                                    <td colspan='3' style='padding: 15px; text-align: right;'>TOTAL GENERAL:</td>
                                    <td style='padding: 15px; text-align: right;'>\${$totalFormateado}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Acción requerida -->
                    <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 2px solid #ffc107; border-radius: 10px; padding: 20px; text-align: center;'>
                        <h4 style='color: #856404; margin-top: 0;'>⚠️ Acción Requerida</h4>
                        <p style='color: #856404; margin: 10px 0; font-size: 16px;'>
                            <strong>Esperar comprobante de pago del cliente en:</strong><br>
                            <a href='mailto:cursos@imcyc.com' style='color: #856404; font-size: 18px; text-decoration: none; font-weight: bold;'>
                                📧 cursos@imcyc.com
                            </a>
                        </p>
                        <p style='color: #856404; margin: 5px 0; font-size: 14px;'>
                            Una vez recibido el comprobante, procesar el pedido correspondiente.<br>
                            <strong>IVA aplicado</strong> según normativa fiscal vigente.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div style='background-color: #495057; color: white; padding: 15px; text-align: center;'>
                    <p style='margin: 0; font-size: 14px;'>
                        Sistema Automático de Notificaciones - Tienda IMCYC<br>
                        <strong>Instituto Mexicano del Cemento y del Concreto</strong>
                    </p>
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
        error_log("Alerta de venta enviada exitosamente a: " . $para);
        return true;

    } catch (Exception $e) {
        error_log("Error al enviar alerta de venta: " . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Confirmación de Compra - Transferencia SPEI</title>
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

        .info-pago {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 5px solid #2196f3;
        }

        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c5aa0;
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
    </style>
</head>

<body>
    <div class="container mt-4">
        <div class="comprobante bg-white p-5 shadow-sm rounded mx-auto">
            <div class="text-center mb-4">
                <h1 class="text-success mb-3">¡Gracias por tu compra! 🎉</h1>
                <h3 class="text-muted"><?= htmlspecialchars($nombre) ?></h3>
            </div>

            <div class="mb-4">
                <h4 class="mb-3 border-bottom pb-2">🛍️ Resumen del Pedido</h4>
                <div class="row">
                    <div class="col-md-8">
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
                                                Cantidad: <?= $item['cantidad'] ?>
                                            </span>
                                            <div class="text-muted">
                                                $<?= number_format($item['precio'] ?? $item['precio_unitario'], 2) ?> c/u
                                            </div>
                                            <div class="fw-bold">
                                                $<?= $item['subtotal'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title text-center">Resumen de Pago</h5>
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <td>Subtotal:</td>
                                        <td class="text-end">$<?= number_format($subtotal_sin_iva, 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td>IVA (16%):</td>
                                        <td class="text-end">$<?= number_format($total_iva, 2) ?></td>
                                    </tr>
                                    <tr class="table-success">
                                        <td><strong>Total:</strong></td>
                                        <td class="text-end"><strong>$<?= number_format($total, 2) ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="info-pago alert border-0 p-4">
                <h4 class="alert-heading mb-3">💳 Instrucciones de Pago SPEI</h4>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2"><strong>Banco:</strong> <?= htmlspecialchars($banco) ?></li>
                            <li class="mb-2"><strong>CLABE:</strong> <code><?= htmlspecialchars($clabe) ?></code></li>
                            <li class="mb-2"><strong>Cuenta:</strong> <?= htmlspecialchars($cuenta) ?></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2"><strong>Beneficiario:</strong> <?= htmlspecialchars($beneficiario) ?></li>
                            <li class="mb-2"><strong>Referencia:</strong>
                                <code><?= htmlspecialchars($referencia) ?></code>
                            </li>
                            <li class="mb-2"><strong>Vence:</strong> <span class="text-danger"><?= $vence ?></span></li>
                        </ul>
                    </div>
                </div>

                <div class="alert alert-warning mt-3 mb-0">
                    <strong>📧 Importante:</strong> Envía tu comprobante de pago a
                    <a href="mailto:cursos@imcyc.com" class="alert-link">cursos@imcyc.com</a>
                    para procesar tu pedido.
                </div>
            </div>

            <div class="alert alert-info">
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
                <a href="mis_pedidos.php" class="btn btn-outline-primary px-4">Ver Mis Pedidos</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>