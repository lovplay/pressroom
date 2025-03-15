<?php
// /demo/index.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Obtener la URL solicitada
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/demo'; // Ajusta esto según tu configuración

// Remover el base path de la URL
$request_uri = str_replace($base_path, '', $request_uri);

// Analizar la URL para determinar qué mostrar
$path_parts = explode('/', trim($request_uri, '/'));

// Variable para determinar qué tipo de contenido mostrar
$content_type = 'home';
$content_slug = '';

// Analizar la URL para determinar el contenido
if (!empty($path_parts[0])) {
    switch ($path_parts[0]) {
        case 'article':
            if (isset($path_parts[1])) {
                $content_type = 'article';
                $content_slug = $path_parts[1];
            }
            break;
        // Puedes agregar más casos aquí para otras secciones
    }
}

// Directorio donde se encuentran los temas
$themesDir = __DIR__ . '/themes/';

// Archivo que contiene el nombre del tema activo
$activeThemeFile = $themesDir . 'active_theme.txt';

// Determinar el tema activo
$activeTheme = 'default';
if (file_exists($activeThemeFile)) {
    $readTheme = trim(file_get_contents($activeThemeFile));
    if (!empty($readTheme)) {
        $activeTheme = $readTheme;
    }
}

// Si es un artículo individual, obtener los datos del artículo
if ($content_type === 'article' && !empty($content_slug)) {
    $article_query = "SELECT a.*, c.name as category_name, u.username as author_name 
                     FROM articles a 
                     LEFT JOIN categories c ON a.category_id = c.id 
                     LEFT JOIN users u ON a.author_id = u.id 
                     WHERE a.slug = ? AND a.status = 'published'";
    
    $stmt = $conn->prepare($article_query);
    $stmt->bind_param("s", $content_slug);
    $stmt->execute();
    $article_result = $stmt->get_result();
    
    if ($article_result->num_rows > 0) {
        $current_article = $article_result->fetch_assoc();
    } else {
        // Redirigir a 404 si el artículo no existe
        header("HTTP/1.0 404 Not Found");
        include __DIR__ . '/404.php';
        exit;
    }
}

// Construir la ruta al archivo apropiado del tema
if ($content_type === 'article') {
    $themeFile = $themesDir . $activeTheme . '/article.php';
    if (!file_exists($themeFile)) {
        $themeFile = $themesDir . $activeTheme . '/index.php';
    }
} else {
    $themeFile = $themesDir . $activeTheme . '/index.php';
}

// Verificar que el archivo existe
if (!file_exists($themeFile)) {
    die("Error: El tema activo ('" . htmlspecialchars($activeTheme) . "') no existe o no tiene el archivo necesario.");
}

// Definir variables que el tema necesitará
$page_type = $content_type;
$current_slug = $content_slug;

// Incluir el archivo del tema
include $themeFile;
?>