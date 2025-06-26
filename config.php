<?php
// config.php - Agregar estas configuraciones de correo

// === CONFIGURACIÓN DE CORREO ===
// Dirección de correo que aparecerá como remitente
define('MAIL_FROM_ADDRESS', 'noreply@imcyc.com.mx');

// Nombre que aparecerá como remitente
define('MAIL_FROM_NAME', 'IMCYC - Instituto Mexicano del Cemento y del Concreto');

// Boundary para mensajes multipart
define('MAIL_BOUNDARY', 'IMCYC_' . md5(time()));

// URL base del sitio (para enlaces en correos)
define('SITE_URL', 'https://www.imcyc.com');

// === CONFIGURACIÓN SMTP (OPCIONAL - SI USAS SMTP) ===
// Si quieres usar SMTP en lugar de la función mail() de PHP
define('SMTP_HOST', 'mail.imcyc.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'tienda_correo');
define('SMTP_PASSWORD', 'imcyc2025*');
define('SMTP_ENCRYPTION', 'ssl'); // 'tls' o 'ssl'

// Verificar si las constantes ya están definidas antes de declararlas
if (!defined('DB_DSN'))
    define('DB_DSN', 'mysql:host=localhost;dbname=tienda;charset=utf8mb4');
if (!defined('DB_USER'))
    define('DB_USER', 'admin');
if (!defined('DB_PASS'))
    define('DB_PASS', 'Imc590923cz4#');
?>