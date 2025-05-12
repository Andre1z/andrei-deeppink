<?php
/**
 * authenticateUser.php
 *
 * Este archivo define la función authenticateUser(), la cual se encarga de verificar
 * si las credenciales suministradas (nombre de usuario y contraseña) corresponden a un usuario
 * existente en la tabla "users" de la base de datos "db.sqlite". La comparación de la contraseña
 * se realiza de manera segura usando password_verify().
 *
 * Uso:
 *   $user = authenticateUser($username, $password);
 *   if ($user) {
 *       // Las credenciales son correctas, se puede continuar.
 *   } else {
 *       // Se imprimirá "Usuario o contraseña incorrectos".
 *   }
 *
 * NOTA: Se asume que la base de datos y la tabla "users" ya existen.
 */

function authenticateUser($username, $password) {
    try {
        // Crear una conexión PDO a la base de datos SQLite.
        $pdo = new PDO('sqlite:db.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Preparar la consulta para obtener el usuario con el nombre de usuario proporcionado.
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar que el usuario existe y que la contraseña es correcta.
        if ($user) {
            if (password_verify($password, $user['password'])) {
                // Credenciales válidas; retornar la información del usuario.
                return $user;
            } else {
                // Contraseña incorrecta.
                echo "Usuario o contraseña incorrectos";
                return false;
            }
        } else {
            // Usuario no encontrado.
            echo "Usuario o contraseña incorrectos";
            return false;
        }
    } catch (PDOException $e) {
        // Manejo de errores en la conexión o consulta a la base de datos.
        echo "Error en la base de datos: " . $e->getMessage();
        return false;
    }
}
?>