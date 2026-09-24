<?php
header("Content-Type: application/json");
require_once "../conexion.php";

// Verificar que los datos lleguen por método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Obtener los datos del formulario (soporta formato JSON o Form-Data)
    $input = json_decode(file_get_contents('php://input'), true);

    $ci = $input['CI'] ?? $_POST['CI'] ?? null;
    $nombre = $input['nombre'] ?? $_POST['nombre'] ?? null;
    $email = $input['email'] ?? $_POST['email'] ?? null;
    $telefono = $input['telefono'] ?? $_POST['telefono'] ?? null;
    $password = $input['password'] ?? $_POST['password'] ?? null;

    // Validar que todos los campos requeridos estén presentes
    if (!$ci || !$nombre || !$email || !$telefono || !$password) {
        echo json_encode([
            "status" => "error",
            "message" => "Todos los campos son obligatorios."
        ]);
        exit;
    }

    try {
        // 1. Verificar si la C.I. o el Correo ya están registrados
        $stmtCheck = $conexion->prepare("SELECT ci FROM usuario WHERE ci = :ci OR email = :email");
        $stmtCheck->bindParam(':ci', $ci);
        $stmtCheck->bindParam(':email', $email);
        $stmtCheck->execute();

        if ($stmtCheck->rowCount() > 0) {
            echo json_encode([
                "status" => "error",
                "message" => "La C.I. o el correo electrónico ya se encuentran registrados."
            ]);
            exit;
        }

        // 2. Encriptar la contraseña por seguridad
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // 3. Insertar el nuevo usuario en la tabla 'usuario'
        $sql = "INSERT INTO usuario (ci, nombre_completo, email, telefono, contrasena) 
                VALUES (:ci, :nombre_completo, :email, :telefono, :contrasena)";
        
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':ci', $ci);
        $stmt->bindParam(':nombre_completo', $nombre);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':contrasena', $passwordHash);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success",
                "message" => "Usuario registrado correctamente."
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo completar el registro."
            ]);
        }

    } catch (PDOException $e) {
        echo json_encode([
            "status" => "error",
            "message" => "Error en la base de datos: " . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Método de solicitud no válido."
    ]);
}
?>