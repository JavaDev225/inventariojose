<?php
// includes/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventario');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SITE_URL', 'http://localhost/InventarioJose/public/');
define('SITE_NAME', 'Sistema de Inventario');

// Configuración de correo (para recuperar contraseña)
define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
define('SMTP_PORT', 465);
define('SMTP_USER', 'db929e91efb7c2');
define('SMTP_PASS', 'b7fab7742cad51'); // No la contraseña normal, genera una en Gmail
define('SMTP_FROM', 'chirinospires@gmail.com');
define('SMTP_FROM_NAME', SITE_NAME);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>