<?php
/**
 * register.php
 *
 * Archivo de registro para nuevos usuarios.
 * Permite crear una cuenta (previa verificación de que el nombre de usuario no exista)
 * y almacena la contraseña de forma segura utilizando password_hash().
 * 
 * Los estilos se cargan desde "css/register.css".
 */

session_start();
require_once 'i18n.php';

try {
    // Conexión a la base de datos SQLite y creación de la tabla de usuarios (si no existe).
    $connection = new PDO('sqlite:db.sqlite');
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        name TEXT,
        email TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Error en la conexión o inicialización de la base de datos: " . $e->getMessage());
}

/**
 * Proceso de registro de usuario.
 * Se verifica que el nombre de usuario no exista; en caso afirmativo, se genera un hash para la contraseña
 * (usando PASSWORD_DEFAULT) y se almacena en la base de datos.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim($_POST['username']);
    $newPassword = trim($_POST['password']);
    $newName     = trim($_POST['name']);
    $newEmail    = trim($_POST['email']);
    
    // Verifica si el usuario ya existe
    $checkStmt = $connection->prepare("SELECT id FROM users WHERE username = :username");
    $checkStmt->execute([':username' => $newUsername]);
    
    if ($checkStmt->fetch()) {
        $errorRegister = __('username_exists');
    } else {
        // Se genera el hash seguro de la contraseña.
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $insertStmt = $connection->prepare("INSERT INTO users (username, password, name, email) 
                                              VALUES (:username, :password, :name, :email)");
        $insertStmt->execute([
            ':username' => $newUsername,
            ':password' => $passwordHash,
            ':name'     => $newName,
            ':email'    => $newEmail
        ]);
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
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <div class="register-panel">
        <h2><?php echo __('register_title'); ?></h2>
        <?php 
            if (isset($errorRegister)) {
                echo "<p class='error'>{$errorRegister}</p>";
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