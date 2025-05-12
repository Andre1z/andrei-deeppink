<?php
/**
 * admin.php
 *
 * Panel de administración.
 * - Se conecta a la base de datos (db.sqlite) y crea la tabla "admin" si no existe.
 * - Permite el login consultando la tabla "admin". Si las credenciales son incorrectas se muestra un mensaje en rojo.
 * - Una vez autenticado, el administrador puede acceder a dos secciones:
 *     1. manage_users: Muestra todos los campos de la tabla "users".
 *     2. manage_reports: Muestra todos los campos de la tabla "reports".
 *
 * Los estilos se cargan desde "css/admin.css".
 */

session_start();
require_once 'i18n.php';

try {
    // Conexión a la base de datos SQLite.
    $dbConnection = new PDO('sqlite:db.sqlite');
    $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear la tabla "admin" si no existe (con los mismos campos que "users").
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS admin (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE,
            password TEXT,
            name TEXT,
            email TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $dbConnection->exec($createTableSQL);
} catch (PDOException $e) {
    die("Error al inicializar la base de datos: " . $e->getMessage());
}

/**
 * Proceso de cierre de sesión.
 */
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_authenticated']);
    unset($_SESSION['admin_user']);
    header("Location: admin.php");
    exit;
}

/**
 * Proceso de autenticación para el panel de administración.
 * Si el usuario no está autenticado, se muestra el formulario de login.
 */
if (!isset($_SESSION['admin_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        $inputUsername = trim($_POST['username']);
        $inputPassword = trim($_POST['password']);

        // Consulta en la tabla admin para buscar el usuario
        $stmt = $dbConnection->prepare("SELECT * FROM admin WHERE username = :username");
        $stmt->execute([':username' => $inputUsername]);
        $adminRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar si se encontró el registro y la contraseña es válida
        if ($adminRecord && password_verify($inputPassword, $adminRecord['password'])) {
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_user'] = $adminRecord['username'];
            header("Location: admin.php");
            exit;
        } else {
            $loginError = "Usuario o contraseña incorrectos";
        }
    }
    // Mostrar el formulario de login si no está autenticado.
    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title><?php echo __('admin_login_title'); ?></title>
        <link rel="stylesheet" href="css/admin.css">
    </head>
    <body>
        <div class="login-container">
            <h2><?php echo __('admin_login_title'); ?></h2>
            <?php
            if (isset($loginError)) {
                echo "<p class='error-msg'>{$loginError}</p>";
            }
            ?>
            <form method="post" action="admin.php">
                <input type="text" name="username" placeholder="<?php echo __('username_placeholder'); ?>" required>
                <input type="password" name="password" placeholder="<?php echo __('password_placeholder'); ?>" required>
                <input type="submit" name="admin_login" value="<?php echo __('login_button'); ?>">
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Si el administrador ya está autenticado, se continua con el dashboard.
$action = isset($_GET['action']) ? $_GET['action'] : '';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo __('admin_dashboard_title'); ?></title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <header id="admin-header">
        <h1><?php echo __('admin_dashboard_title'); ?></h1>
        <nav>
            <a href="admin.php?action=logout"><?php echo __('logout'); ?></a>
        </nav>
    </header>
    <div style="display: flex;">
        <aside id="admin-nav">
            <ul>
                <li><a href="admin.php?action=manage_users"><?php echo __('manage_users'); ?></a></li>
                <li><a href="admin.php?action=manage_reports"><?php echo __('manage_reports'); ?></a></li>
            </ul>
        </aside>
        <main id="admin-content">
            <?php
            if ($action === 'manage_users') {
                // Consulta para obtener todos los campos de la tabla "users".
                try {
                    $usersStmt = $dbConnection->query("SELECT * FROM users ORDER BY created_at DESC");
                    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
                    echo "<h2>" . __('manage_users') . "</h2>";
                    if ($users) {
                        echo "<table class='data-table'>";
                        echo "<tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Password</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Created At</th>
                              </tr>";
                        foreach ($users as $userRow) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($userRow['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($userRow['username']) . "</td>";
                            echo "<td>" . htmlspecialchars($userRow['password']) . "</td>";
                            echo "<td>" . htmlspecialchars($userRow['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($userRow['email']) . "</td>";
                            echo "<td>" . htmlspecialchars($userRow['created_at']) . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<p>No existen usuarios.</p>";
                    }
                } catch (PDOException $ex) {
                    echo "<p>Error: " . $ex->getMessage() . "</p>";
                }
            } elseif ($action === 'manage_reports') {
                // Consulta para obtener todos los campos de la tabla "reports".
                try {
                    $reportsStmt = $dbConnection->query("SELECT * FROM reports ORDER BY created_at DESC");
                    $reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
                    echo "<h2>" . __('manage_reports') . "</h2>";
                    if ($reports) {
                        echo "<table class='data-table'>";
                        echo "<tr>
                                <th>ID</th>
                                <th>User ID</th>
                                <th>URL</th>
                                <th>Report HTML</th>
                                <th>Created At</th>
                              </tr>";
                        foreach ($reports as $reportRow) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($reportRow['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($reportRow['user_id']) . "</td>";
                            echo "<td>" . htmlspecialchars($reportRow['url']) . "</td>";
                            echo "<td>" . htmlspecialchars($reportRow['report_html']) . "</td>";
                            echo "<td>" . htmlspecialchars($reportRow['created_at']) . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<p>No existen reportes.</p>";
                    }
                } catch (PDOException $ex) {
                    echo "<p>Error: " . $ex->getMessage() . "</p>";
                }
            } else {
                // Vista de bienvenida base.
                echo "<h2>" . __('admin_dashboard_title') . "</h2>";
                echo "<p>Bienvenido, " . htmlspecialchars($_SESSION['admin_user']) . ".</p>";
                echo "<p>Selecciona una opción del menú para gestionar usuarios o reportes.</p>";
            }
            ?>
        </main>
    </div>
</body>
</html>