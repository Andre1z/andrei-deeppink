<?php
/**
 * admin.php
 *
 * Panel de administración para gestionar usuarios y reportes.
 * Permite el inicio de sesión para el usuario administrador, así como la visualización,
 * edición y eliminación de registros.
 *
 * NOTA: La autenticación se efectúa únicamente con el usuario "andrei".
 */

// Iniciamos la sesión y cargamos la internacionalización
session_start();
require_once 'i18n.php';

/**
 * Conexión con la base de datos SQLite.
 * Se crean las tablas 'users' y 'reports' si aún no existen.
 */
try {
    $dbConnection = new PDO('sqlite:db.sqlite');
    $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Tabla de usuarios
    $dbConnection->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        name TEXT,
        email TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Tabla de reportes
    $dbConnection->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        url TEXT NOT NULL,
        report_html TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $exception) {
    die("Error de conexión/Inicialización de BD: " . $exception->getMessage());
}

/**
 * Proceso de autenticación del administrador.
 * Sólo se permite al usuario "jocarsa" ingresar.
 */
if (!isset($_SESSION['admin_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        $inputUsername = trim($_POST['username']);
        $inputPassword = trim($_POST['password']);
        
        // Consulta para localizar al usuario
        $stmt = $dbConnection->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $inputUsername]);
        $adminRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Solo se permite si el nombre de usuario es "jocarsa" y la contraseña coincide
        if ($adminRecord && $inputUsername === 'jocarsa' && $adminRecord['password'] === $inputPassword) {
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_user'] = $inputUsername;
        } else {
            $loginError = __('invalid_credentials');
        }
    } else {
        // Si no se ha enviado el formulario, mostrar la página de login
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?php echo __('admin_login_title'); ?></title>
            <link rel="stylesheet" href="css/admin.css">
            <style>
                /* Estilos embebidos para el formulario de inicio de sesión */
                .login-box {
                    max-width: 300px;
                    margin: 50px auto;
                    padding: 20px;
                    background-color: #fff;
                    border: 1px solid #ccc;
                    box-shadow: 2px 2px 10px rgba(0,0,0,0.1);
                }
                .login-box h2 {
                    text-align: center;
                    margin-bottom: 15px;
                }
                .login-box input[type="text"],
                .login-box input[type="password"] {
                    width: 100%;
                    padding: 10px;
                    margin: 8px 0;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                .login-box input[type="submit"] {
                    width: 100%;
                    padding: 10px;
                    background: #0073aa;
                    border: none;
                    color: #fff;
                    border-radius: 4px;
                    cursor: pointer;
                }
                .error-msg {
                    color: red;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2><?php echo __('admin_login_title'); ?></h2>
                <?php if(isset($loginError)) echo "<p class='error-msg'>$loginError</p>"; ?>
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
}

// Procesamos las acciones dentro del panel de administración
$actionMode = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

/**
 * Proceso de cierre de sesión.
 */
if ($actionMode == 'logout') {
    unset($_SESSION['admin_authenticated']);
    unset($_SESSION['admin_user']);
    header("Location: admin.php");
    exit;
}

/**
 * Procedimiento para eliminar un usuario.
 * No se permite eliminar al administrador 'jocarsa'.
 */
if ($actionMode === 'delete_user' && isset($_GET['id'])) {
    $userId = intval($_GET['id']);
    $stmt = $dbConnection->prepare("SELECT username FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userInfo && $userInfo['username'] === 'jocarsa') {
        $errorMsg = "No se puede eliminar el usuario administrador.";
    } else {
        $stmt = $dbConnection->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        header("Location: admin.php?action=manage_users");
        exit;
    }
}

/**
 * Procedimiento para eliminar un reporte.
 */
if ($actionMode === 'delete_report' && isset($_GET['id'])) {
    $reportId = intval($_GET['id']);
    $stmt = $dbConnection->prepare("DELETE FROM reports WHERE id = :id");
    $stmt->execute([':id' => $reportId]);
    header("Location: admin.php?action=manage_reports");
    exit;
}

/**
 * Si se envía el formulario para crear un nuevo usuario, se inserta en la BD.
 */
if ($actionMode === 'add_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUser = trim($_POST['username']);
    $newPass = trim($_POST['password']);
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    
    $stmt = $dbConnection->prepare("INSERT INTO users (username, password, name, email) VALUES (:username, :password, :name, :email)");
    $stmt->execute([
        ':username' => $newUser,
        ':password' => $newPass,
        ':name' => $newName,
        ':email' => $newEmail
    ]);
    header("Location: admin.php?action=manage_users");
    exit;
}

/**
 * Proceso de edición de usuario.
 * Si se ha enviado el formulario, se actualizan los datos; de lo contrario, se recuperan para mostrarlos.
 */
if ($actionMode === 'edit_user' && isset($_GET['id'])) {
    $editUserId = intval($_GET['id']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $updatedUser = trim($_POST['username']);
        $updatedPass = trim($_POST['password']);
        $updatedName = trim($_POST['name']);
        $updatedEmail = trim($_POST['email']);
        
        $stmt = $dbConnection->prepare("UPDATE users SET username = :username, password = :password, name = :name, email = :email WHERE id = :id");
        $stmt->execute([
            ':username' => $updatedUser,
            ':password' => $updatedPass,
            ':name' => $updatedName,
            ':email' => $updatedEmail,
            ':id' => $editUserId
        ]);
        header("Location: admin.php?action=manage_users");
        exit;
    } else {
        $stmt = $dbConnection->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $editUserId]);
        $userToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo __('admin_dashboard_title'); ?></title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* Estilos básicos para el panel de administración */
        #headerPanel {
            background: #23282d;
            color: #fff;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #navPanel {
            width: 220px;
            background: #32373c;
            color: #fff;
            padding: 20px;
        }
        #navPanel ul {
            list-style: none;
            padding: 0;
        }
        #navPanel ul li {
            margin-bottom: 15px;
        }
        #navPanel ul li a {
            color: #fff;
            text-decoration: none;
        }
        #mainContent {
            flex-grow: 1;
            padding: 20px;
            background: #fff;
        }
        .dataTable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .dataTable th, .dataTable td {
            border: 1px solid #ddd;
            padding: 10px;
        }
    </style>
</head>
<body>
<div id="headerPanel">
    <h1><?php echo __('admin_dashboard_title'); ?></h1>
    <div>
        <a href="admin.php?action=logout" style="color: #fff;"><?php echo __('logout'); ?></a>
    </div>
</div>
<div style="display: flex;">
    <div id="navPanel">
        <ul>
            <li><a href="admin.php?action=manage_users"><?php echo __('manage_users'); ?></a></li>
            <li><a href="admin.php?action=manage_reports"><?php echo __('manage_reports'); ?></a></li>
        </ul>
    </div>
    <div id="mainContent">
        <?php
        // Despliegue del panel principal si no se especifica otra acción.
        if ($actionMode === 'dashboard') {
            echo "<h2>" . __('admin_dashboard_title') . "</h2>";
            echo "<p>Bienvenido al panel de administración.</p>";
        }
        
        // Gestión de usuarios
        if ($actionMode === 'manage_users') {
            echo "<h2>" . __('manage_users') . "</h2>";
            if (isset($errorMsg)) {
                echo "<p style='color:red;'>$errorMsg</p>";
            }
            
            $usersQuery = $dbConnection->query("SELECT * FROM users ORDER BY created_at DESC");
            $usersList = $usersQuery->fetchAll(PDO::FETCH_ASSOC);
            if ($usersList) {
                echo "<table class='dataTable'>";
                echo "<tr><th>ID</th><th>" . __('username') . "</th><th>" . __('name') . "</th><th>" . __('email') . "</th><th>" . __('created_at') . "</th><th>" . __('actions') . "</th></tr>";
                foreach ($usersList as $user) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                    echo "<td>";
                    echo "<a href='admin.php?action=edit_user&id=" . $user['id'] . "'>Edit</a> | ";
                    if ($user['username'] !== 'jocarsa') {
                        echo "<a href='admin.php?action=delete_user&id=" . $user['id'] . "' onclick=\"return confirm('¿Confirma la eliminación?');\">Delete</a>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No se encontraron usuarios.</p>";
            }
            echo "<h3>Agrega un Nuevo Usuario</h3>";
            ?>
            <form method="post" action="admin.php?action=add_user">
                <p><input type="text" name="username" placeholder="<?php echo __('username_placeholder'); ?>" required></p>
                <p><input type="password" name="password" placeholder="<?php echo __('password_placeholder'); ?>" required></p>
                <p><input type="text" name="name" placeholder="<?php echo __('name_placeholder'); ?>" required></p>
                <p><input type="email" name="email" placeholder="<?php echo __('email_placeholder'); ?>" required></p>
                <p><input type="submit" value="Add User"></p>
            </form>
            <?php
        } elseif ($actionMode === 'edit_user' && isset($userToEdit)) {
            ?>
            <h2>Edit User</h2>
            <form method="post" action="admin.php?action=edit_user&id=<?php echo $userToEdit['id']; ?>">
                <p><input type="text" name="username" value="<?php echo htmlspecialchars($userToEdit['username']); ?>" required></p>
                <p><input type="password" name="password" placeholder="<?php echo __('password_placeholder'); ?>" required></p>
                <p><input type="text" name="name" value="<?php echo htmlspecialchars($userToEdit['name']); ?>" required></p>
                <p><input type="email" name="email" value="<?php echo htmlspecialchars($userToEdit['email']); ?>" required></p>
                <p><input type="submit" value="Update User"></p>
            </form>
            <?php
        } elseif ($actionMode === 'manage_reports') {
            echo "<h2>" . __('manage_reports') . "</h2>";
            $reportStmt = $dbConnection->query("SELECT r.*, u.username FROM reports r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");
            $reportsList = $reportStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($reportsList) {
                echo "<table class='dataTable'>";
                echo "<tr><th>ID</th><th>" . __('url') . "</th><th>" . __('user') . "</th><th>" . __('created_at') . "</th><th>" . __('actions') . "</th></tr>";
                foreach ($reportsList as $report) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($report['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($report['url']) . "</td>";
                    echo "<td>" . htmlspecialchars($report['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($report['created_at']) . "</td>";
                    echo "<td>";
                    echo "<a href='admin.php?action=view_report&id=" . $report['id'] . "'>View</a> | ";
                    echo "<a href='admin.php?action=delete_report&id=" . $report['id'] . "' onclick=\"return confirm('¿Confirmar eliminación?');\">Delete</a>";
                    echo "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No reports found.</p>";
            }
        } elseif ($actionMode === 'view_report' && isset($_GET['id'])) {
            $repId = intval($_GET['id']);
            $stmt = $dbConnection->prepare("SELECT r.*, u.username FROM reports r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = :id");
            $stmt->execute([':id' => $repId]);
            $reportData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($reportData) {
                echo "<h2>Report Details</h2>";
                echo "<p><strong>" . __('url') . ":</strong> " . htmlspecialchars($reportData['url']) . "</p>";
                echo "<p><strong>User:</strong> " . htmlspecialchars($reportData['username']) . "</p>";
                echo "<p><strong>" . __('created_at') . ":</strong> " . htmlspecialchars($reportData['created_at']) . "</p>";
                echo $reportData['report_html'];
                echo "<p><a href='admin.php?action=manage_reports'>" . __('back_to_reports') . "</a></p>";
            }
        } else {
            // Pantalla por defecto del panel de administración
            echo "<h2>" . __('admin_dashboard_title') . "</h2>";
            echo "<p>Bienvenido al panel de administración.</p>";
        }
        ?>
    </div>
</div>
</body>
</html>