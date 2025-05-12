<?php
/**
 * createAdminTableAndUser.php
 *
 * Este script se encarga de crear la tabla "admin" en la base de datos "db.sqlite"
 * con los mismos campos que la tabla "users" (id, username, password, name, email, created_at),
 * y de insertar un usuario administrativo con:
 *
 *   - username: "andrei"
 *   - password: "andrei" (almacenada de forma segura usando password_hash())
 *   - name: "Andrei"
 *   - email: "andrei@example.com"
 *
 * Una vez ejecutado, el usuario administrativo quedará disponible para acceder a admin.php.
 * Después de ejecutar el script, se recomienda eliminarlo o desactivarlo para evitar ejecuciones
 * posteriores.
 */

try {
    // Conectar a la base de datos SQLite.
    $pdo = new PDO('sqlite:db.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear la tabla "admin" si no existe.
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS admin (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE,
            password TEXT,
            name TEXT,
            email TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createTableQuery);

    // Datos del usuario administrativo.
    $username = "andrei";
    $password_plain = "andrei";
    // Se genera el hash seguro de la contraseña.
    $hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);
    $name = "Andrei";
    $email = "andrei@example.com";

    // Comprobar si ya existe un usuario con el mismo username en la tabla admin.
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $existingAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingAdmin) {
        echo "El usuario administrador '$username' ya existe.\n";
    } else {
        // Insertar el nuevo usuario administrativo.
        $insertQuery = "INSERT INTO admin (username, password, name, email) VALUES (:username, :password, :name, :email)";
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->execute([
            ':username' => $username,
            ':password' => $hashed_password,
            ':name'     => $name,
            ':email'    => $email,
        ]);
        echo "El usuario administrador '$username' ha sido creado exitosamente.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>