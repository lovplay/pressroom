<?php
require_once('config/config.php');

// Consulta de prueba
$sql = "SELECT 1";
if ($conn->query($sql) === TRUE) {
    echo "La consulta se ejecutó correctamente.";
} else {
    echo "Error en la consulta: " . $conn->error;
}

// Cerrar conexión
$conn->close();
?>