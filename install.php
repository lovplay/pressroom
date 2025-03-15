<?php
ob_start(); // Inicia el buffer de salida

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar si la instalación ya se realizó
if (file_exists(__DIR__ . '/config/config.php')) {
    die("Instalación ya completada. Elimina install.php para continuar.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host       = trim($_POST['db_host']);
    $db_user       = trim($_POST['db_user']);
    $db_password   = trim($_POST['db_password']);
    $db_name       = trim($_POST['db_name']);
    $site_title    = trim($_POST['site_title']);
    $language      = trim($_POST['language']);
    $admin_email   = trim($_POST['admin_email']);
    // Hashear la contraseña ingresada
    $admin_password = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);

    try {
        // Conectar a MySQL
        $conn = new mysqli($db_host, $db_user, $db_password);
        if ($conn->connect_error) {
            throw new Exception("Error de conexión: " . $conn->connect_error);
        }

        // Crear base de datos si no existe
        $createDbQuery = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if (!$conn->query($createDbQuery)) {
            throw new Exception("Error al crear la base de datos: " . $conn->error);
        }

        // Seleccionar la base de datos
        $conn->select_db($db_name);

        // Verificar si el archivo schema.sql existe (ubicación corregida)
        $schemaFile = __DIR__ . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("El archivo schema.sql no se encontró en: " . $schemaFile);
        }

        // Leer y ejecutar el schema.sql
        $sqlContent = file_get_contents($schemaFile);
        $queries = array_filter(array_map('trim', explode(";", $sqlContent)));
        foreach ($queries as $query) {
            if (!empty($query)) {
                if (!$conn->query($query)) {
                    throw new Exception("Error en SQL: " . $conn->error . "\nConsulta: " . $query);
                }
            }
        }

        // Manejar la subida del logo
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $site_logo_path = "";
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == 0) {
            $fileName      = basename($_FILES['site_logo']['name']);
            $uploadPath    = $uploadDir . $fileName;
            $fileExtension = pathinfo($uploadPath, PATHINFO_EXTENSION);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
                throw new Exception("El archivo de logo no es válido.");
            }
            
            if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $uploadPath)) {
                $site_logo_path = '/uploads/' . $fileName;
            } else {
                throw new Exception("No se pudo subir el logo.");
            }
        } else {
            throw new Exception("No se subió ningún logo.");
        }
        
        // Insertar el usuario administrador en la tabla users
        $full_name = "Administrador de " . $site_title;
        $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, role) VALUES (?, ?, ?, 'admin')");
        if (!$stmt) {
            throw new Exception("Error preparando la consulta de usuario: " . $conn->error);
        }
        $stmt->bind_param("sss", $admin_email, $admin_password, $full_name);
        if (!$stmt->execute()) {
            throw new Exception("Error insertando admin user: " . $stmt->error);
        }
        $stmt->close();
        
        // Generar archivo de configuración con la estructura exacta
        $configContent = <<<PHP
<?php
// Configuración de la base de datos
define('DB_HOST', '$db_host'); // Host de la base de datos
define('DB_USER', '$db_user'); // Usuario de la base de datos
define('DB_PASSWORD', '$db_password'); // Contraseña de la base de datos
define('DB_NAME', '$db_name'); // Nombre de la base de datos
define('SITE_TITLE', '$site_title'); // Título del sitio
define('SITE_LOGO', '$site_logo_path');
define('LANGUAGE', '$language');
define('ADMIN_EMAIL', '$admin_email');
define('ADMIN_PASSWORD', '$admin_password');

// Conexión a la base de datos
\$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Verificar conexión
if (\$conn->connect_error) {
    die("Error de conexión a la base de datos. Por favor, contacta al administrador.");
}

// Configurar el conjunto de caracteres a UTF-8
\$conn->set_charset("utf8");

// Configuración de uploads
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB

function getDatabaseConnection() {
    \$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if (\$conn->connect_error) {
        die("Connection failed: " . \$conn->connect_error);
    }
    return \$conn;
}
?>
PHP;

        // Guardar el archivo de configuración
        $configPath = __DIR__ . '/config/config.php';
        if (!file_put_contents($configPath, $configContent)) {
            throw new Exception("No se pudo escribir el archivo de configuración.");
        }

        // Cerrar conexión
        $conn->close();

        // Limpiar el buffer de salida y redirigir a login.php (ruta relativa)
        if (ob_get_length()) {
            ob_end_clean();
        }
        header("Location: public/login.php");
        exit();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}

ob_end_flush(); // Envía el buffer de salida
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - PressRoom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@100">
    <style>
        .installation-box {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            background: #f8f9fa;
            font-family:'Poppins';
        }
        .requirements-list {
            list-style: none;
            padding: 0;
        }
        .requirement-item::before {
            content: "✓";
            color: #28a745;
            margin-right: 0.5rem;
        }
        h2, h3, h4, p {
            font-family:'Plus Jakarta Sans';
        }
    </style>
</head>
<body>
    <div class="installation-box">
        <center><img src="logo_index.png" style="width:auto;"></center>
        <h1 class="text-center mb-4">🛠️ Instalación de PressRoom</h1>
        <p>PressRoom es un CMS open source de <strong>código abierto para industrias de noticias,</strong> en su hosting o servicio local prepare la información solicitada para proceder con su Instalación.</p>
        <div class="alert alert-info mb-4">
            <h5>Requisitos del Sistema</h5>
            <ul class="requirements-list">
                <li class="requirement-item">PHP 7.4 o superior</li>
                <li class="requirement-item">MySQL 5.7+ o MariaDB 10.2+</li>
                <li class="requirement-item">Permisos de escritura en /assets y /config</li>
            </ul>
        </div>
        <div class="installation-box">
            <h1 class="text-center mb-4">Configuración Inicial</h1>
            <form method="POST" enctype="multipart/form-data">
                <h4 class="mb-3">Configuración de la Base de Datos</h4>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Host de la base de datos</label>
                        <input type="text" name="db_host" class="form-control" value="localhost" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nombre de la base de datos</label>
                        <input type="text" name="db_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Usuario</label>
                        <input type="text" name="db_user" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="db_password" class="form-control">
                    </div>
                </div>

                <hr class="my-5">
                
                <h4 class="mb-3">Configuración del Sitio</h4>
                <div class="mb-3">
                    <label class="form-label">Título del sitio</label>
                    <input type="text" name="site_title" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Logo del sitio (450x75px)</label>
                    <input type="file" name="site_logo" class="form-control" accept="image/*" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Idioma predeterminado</label>
                    <select name="language" class="form-select" required>
                        <option value="es">Español</option>
                        <option value="en">English</option>
                    </select>
                </div>

                <hr class="my-5">

                <h4 class="mb-3">Cuenta de Administrador</h4>
                <div class="mb-3">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" name="admin_email" class="form-control" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="admin_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    Completar Instalación
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
