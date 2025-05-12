<?php
/**
 * register.php
 *
 * Archivo de registro para nuevos usuarios.
 * Permite crear una nueva cuenta si el nombre de usuario no existe aún en la base de datos.
 * Los elementos visuales se cargan a través del archivo "css/register.css".
 */

session_start();
require_once 'i18n.php';

try {
    // Conexión a la base de datos SQLite y configuración de errores.
    $connection = new PDO('sqlite:db.sqlite');
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Creación de la tabla de usuarios, en caso de que no esté creada.
    $connection->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        name TEXT,
        email TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $exc) {
    die("Error en la conexión o inicialización de la base de datos: " . $exc->getMessage());
}

/**
 * Proceso de registro:
 * Si se envía el formulario mediante POST, se extraen los datos y se verifica si el nombre de usuario
 * ya existe. Si no existe, se registra el nuevo usuario en la base de datos.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim($_POST['username']);
    $newPassword = trim($_POST['password']);
    $newName     = trim($_POST['name']);
    $newEmail    = trim($_POST['email']);
    
    // Se consulta la base de datos para confirmar que el usuario no exista.
    $checkStmt = $connection->prepare("SELECT id FROM users WHERE username = :username");
    $checkStmt->execute([':username' => $newUsername]);
    
    if ($checkStmt->fetch()) {
        // Si se encuentra un registro, se informa al usuario.
        $errorRegister = __('username_exists');
    } else {
        // Inserción del nuevo usuario en la tabla 'users'.
        $insertStmt = $connection->prepare("INSERT INTO users (username, password, name, email) VALUES (:username, :password, :name, :email)");
        $insertStmt->execute([
            ':username' => $newUsername,
            ':password' => $newPassword, // En producción, se recomienda utilizar un hash.
            ':name'     => $newName,
            ':email'    => $newEmail
        ]);
        // Redirige al login tras el registro exitoso.
        header("Location: index.php");
        exit;
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo __('register_page_title'); ?></title>
    <!-- Se utiliza el CSS exclusivo para la página de registro -->
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <div class="register-panel">
        <h2><?php echo __('register_title'); ?></h2>
        <?php 
            // Mostramos el mensaje de error si el usuario ya existe.
            if (isset($errorRegister)) {
                echo "<p class='error'>" . $errorRegister . "</p>";
            } 
        ?>
        <form method="post" action="register.php">
            <input type="text" name="username" placeholder="<?php echo __('username_placeholder'); ?>" required>
            <input type="password" name="password" placeholder="<?php echo __('password_placeholder'); ?>" required>
            <input type="text" name="name" placeholder="<?php echo __('name_placeholder'); ?>" required>
            <input type="email" name="email" placeholder="<?php echo __('email_placeholder'); ?>" required>
            <input type="submit" value="<?php echo __('register_button'); ?>">
        </form>
        <p><a href="index.php"><?php echo __('back_to_login'); ?></a></p>
    </div>
</body>
</html>