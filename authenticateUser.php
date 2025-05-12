<?php
/**
 * authenticateUser.php
 *
 * Esta función verifica si las credenciales brindadas (username y password) 
 * existen en la tabla "users" de la base de datos "db.sqlite" y si la contraseña 
 * coincide (usando password_verify()). En caso de éxito, retorna la información 
 * del usuario; de lo contrario, retorna false sin imprimir nada.
 *
 * @param string $username El nombre de usuario.
 * @param string $password La contraseña en texto plano a verificar.
 * @return array|bool Los datos del usuario en caso de éxito o false en caso contrario.
 */
function authenticateUser($username, $password) {
    try {
        // Conexión a la base de datos SQLite.
        $pdo = new PDO('sqlite:db.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Prepara y ejecuta la consulta para obtener el usuario.
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si el usuario existe y la contraseña es correcta, se retorna la información.
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        } else {
            return false;
        }
    } catch (PDOException $e) {
        // En caso de error, se retorna false (opcionalmente podrías registrar el error).
        return false;
    }
}
?>