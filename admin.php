<?php
/**
 * admin.php
 *
 * Panel de administración para la gestión de usuarios y reportes.
 * Solo el usuario "andrei" se puede autenticar como administrador.
 * En esta versión, todo el estilo se carga desde "css/admin.css".
 *
 * Funciones principales:
 * - Conexión e inicialización de la base de datos (SQLite).
 * - Proceso de autenticación exclusivo para "andrei".
 * - Gestión de las acciones: listado, adición, edición y eliminación de usuarios,
 *   así como la visualización y eliminación de reportes.
 *
 * NOTA: Toda la configuración visual se realiza a través del archivo de estilos externo.
 */

session_start();
require_once 'i18n.php';

// Conexión a la base de datos SQLite y creación de tablas si es necesario.
try {
    $conexionDB = new PDO('sqlite:db.sqlite');
    $conexionDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Creación de la tabla de usuarios.
    $conexionDB->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        name TEXT,
        email TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Creación de la tabla de reportes.
    $conexionDB->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        url TEXT NOT NULL,
        report_html TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $excepcion) {
    die("Error en la inicialización de la base de datos: " . $excepcion->getMessage());
}

/**
 * Autenticación del usuario administrador.
 * Si aún no se ha autenticado, se muestra el formulario de acceso.
 */
if (!isset($_SESSION['admin_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        $usuarioIngresado = trim($_POST['username']);
        $claveIngresada   = trim($_POST['password']);

        // Buscamos el registro del usuario en la base de datos.
        $consulta = $conexionDB->prepare("SELECT * FROM users WHERE username = :username");
        $consulta->execute([':username' => $usuarioIngresado]);
        $registroAdmin = $consulta->fetch(PDO::FETCH_ASSOC);

        // Solo se permite el acceso si el usuario es "jocarsa" y la contraseña es correcta.
        if ($registroAdmin && $usuarioIngresado === 'jocarsa' && $registroAdmin['password'] === $claveIngresada) {
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_username'] = $usuarioIngresado;
        } else {
            $mensajeErrorLogin = __('invalid_credentials');
        }
    } else {
        // Mostrar página de login si no se envió el formulario
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
                <?php if (isset($mensajeErrorLogin)) { echo "<p class='error'>" . $mensajeErrorLogin . "</p>"; } ?>
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

// Se determina la acción solicitada en el panel.
$accion = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

/**
 * Cierre de sesión del administrador.
 */
if ($accion === 'logout') {
    unset($_SESSION['admin_authenticated']);
    unset($_SESSION['admin_username']);
    header("Location: admin.php");
    exit;
}

/**
 * Eliminación de usuario.
 * Se impide eliminar al usuario administrador "jocarsa".
 */
if ($accion === 'delete_user' && isset($_GET['id'])) {
    $idUsuario = intval($_GET['id']);
    $consulta = $conexionDB->prepare("SELECT username FROM users WHERE id = :id");
    $consulta->execute([':id' => $idUsuario]);
    $usuarioInfo = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($usuarioInfo && $usuarioInfo['username'] === 'jocarsa') {
        $errorUsuario = "No se puede eliminar el usuario administrador.";
    } else {
        $eliminar = $conexionDB->prepare("DELETE FROM users WHERE id = :id");
        $eliminar->execute([':id' => $idUsuario]);
        header("Location: admin.php?action=manage_users");
        exit;
    }
}

/**
 * Eliminación de reporte.
 */
if ($accion === 'delete_report' && isset($_GET['id'])) {
    $idReporte = intval($_GET['id']);
    $eliminarReporte = $conexionDB->prepare("DELETE FROM reports WHERE id = :id");
    $eliminarReporte->execute([':id' => $idReporte]);
    header("Location: admin.php?action=manage_reports");
    exit;
}

/**
 * Inserción de un nuevo usuario.
 */
if ($accion === 'add_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevoUsuario = trim($_POST['username']);
    $nuevaClave   = trim($_POST['password']);
    $nuevoNombre  = trim($_POST['name']);
    $nuevoEmail   = trim($_POST['email']);

    $insertar = $conexionDB->prepare("INSERT INTO users (username, password, name, email) VALUES (:username, :password, :name, :email)");
    $insertar->execute([
        ':username' => $nuevoUsuario,
        ':password' => $nuevaClave,
        ':name'     => $nuevoNombre,
        ':email'    => $nuevoEmail
    ]);
    header("Location: admin.php?action=manage_users");
    exit;
}

/**
 * Actualización de la información de un usuario.
 * Si se envía el formulario, se actualizan los datos;
 * en caso contrario, se carga la información para su edición.
 */
if ($accion === 'edit_user' && isset($_GET['id'])) {
    $idEditar = intval($_GET['id']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $usuarioActualizado = trim($_POST['username']);
        $claveActualizada   = trim($_POST['password']);
        $nombreActualizado  = trim($_POST['name']);
        $emailActualizado   = trim($_POST['email']);

        $actualizar = $conexionDB->prepare("UPDATE users SET username = :username, password = :password, name = :name, email = :email WHERE id = :id");
        $actualizar->execute([
            ':username' => $usuarioActualizado,
            ':password' => $claveActualizada,
            ':name'     => $nombreActualizado,
            ':email'    => $emailActualizado,
            ':id'       => $idEditar
        ]);
        header("Location: admin.php?action=manage_users");
        exit;
    } else {
        $consultaEditar = $conexionDB->prepare("SELECT * FROM users WHERE id = :id");
        $consultaEditar->execute([':id' => $idEditar]);
        $usuarioEditar = $consultaEditar->fetch(PDO::FETCH_ASSOC);
    }
}

?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo __('admin_dashboard_title'); ?></title>
    <!-- Se utiliza exclusivamente el CSS externo para admin.php -->
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <!-- Cabecera del panel de administración -->
    <header id="admin-header">
        <h1><?php echo __('admin_dashboard_title'); ?></h1>
        <nav>
            <a href="admin.php?action=logout"><?php echo __('logout'); ?></a>
        </nav>
    </header>
    
    <!-- Menú de navegación lateral -->
    <aside id="admin-nav">
        <ul>
            <li><a href="admin.php?action=manage_users"><?php echo __('manage_users'); ?></a></li>
            <li><a href="admin.php?action=manage_reports"><?php echo __('manage_reports'); ?></a></li>
        </ul>
    </aside>
    
    <!-- Contenido principal del panel -->
    <main id="admin-content">
        <?php
        // Página de bienvenida predeterminada
        if ($accion === 'dashboard') {
            echo "<h2>" . __('admin_dashboard_title') . "</h2>";
            echo "<p>Bienvenido al panel de administración.</p>";
        }

        // Gestión de usuarios
        if ($accion === 'manage_users') {
            echo "<h2>" . __('manage_users') . "</h2>";
            if (isset($errorUsuario)) {
                echo "<p class='error'>" . $errorUsuario . "</p>";
            }
            $consultaUsuarios = $conexionDB->query("SELECT * FROM users ORDER BY created_at DESC");
            $listaUsuarios = $consultaUsuarios->fetchAll(PDO::FETCH_ASSOC);
            if ($listaUsuarios) {
                echo "<table class='data-table'>";
                echo "<tr><th>ID</th><th>" . __('username') . "</th><th>" . __('name') . "</th><th>" . __('email') . "</th><th>" . __('created_at') . "</th><th>" . __('actions') . "</th></tr>";
                foreach ($listaUsuarios as $usuario) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($usuario['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($usuario['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($usuario['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($usuario['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($usuario['created_at']) . "</td>";
                    echo "<td>";
                    echo "<a href='admin.php?action=edit_user&id=" . $usuario['id'] . "'>Edit</a> | ";
                    if ($usuario['username'] !== 'jocarsa') {
                        echo "<a href='admin.php?action=delete_user&id=" . $usuario['id'] . "' onclick=\"return confirm('¿Confirmar eliminación?');\">Delete</a>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No se encontraron usuarios.</p>";
            }
            echo "<h3>Agregar Nuevo Usuario</h3>";
            ?>
            <form method="post" action="admin.php?action=add_user">
                <p><input type="text" name="username" placeholder="<?php echo __('username_placeholder'); ?>" required></p>
                <p><input type="password" name="password" placeholder="<?php echo __('password_placeholder'); ?>" required></p>
                <p><input type="text" name="name" placeholder="<?php echo __('name_placeholder'); ?>" required></p>
                <p><input type="email" name="email" placeholder="<?php echo __('email_placeholder'); ?>" required></p>
                <p><input type="submit" value="Add User"></p>
            </form>
            <?php
        } elseif ($accion === 'edit_user' && isset($usuarioEditar)) {
            ?>
            <h2>Edit User</h2>
            <form method="post" action="admin.php?action=edit_user&id=<?php echo $usuarioEditar['id']; ?>">
                <p><input type="text" name="username" value="<?php echo htmlspecialchars($usuarioEditar['username']); ?>" required></p>
                <p><input type="password" name="password" placeholder="<?php echo __('password_placeholder'); ?>" required></p>
                <p><input type="text" name="name" value="<?php echo htmlspecialchars($usuarioEditar['name']); ?>" required></p>
                <p><input type="email" name="email" value="<?php echo htmlspecialchars($usuarioEditar['email']); ?>" required></p>
                <p><input type="submit" value="Update User"></p>
            </form>
            <?php
        }
        // Gestión de reportes
        if ($accion === 'manage_reports') {
            echo "<h2>" . __('manage_reports') . "</h2>";
            $consultaReportes = $conexionDB->query("SELECT r.*, u.username FROM reports r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");
            $listaReportes = $consultaReportes->fetchAll(PDO::FETCH_ASSOC);
            if ($listaReportes) {
                echo "<table class='data-table'>";
                echo "<tr><th>ID</th><th>" . __('url') . "</th><th>" . __('user') . "</th><th>" . __('created_at') . "</th><th>" . __('actions') . "</th></tr>";
                foreach ($listaReportes as $reporte) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($reporte['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($reporte['url']) . "</td>";
                    echo "<td>" . htmlspecialchars($reporte['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($reporte['created_at']) . "</td>";
                    echo "<td>";
                    echo "<a href='admin.php?action=view_report&id=" . $reporte['id'] . "'>View</a> | ";
                    echo "<a href='admin.php?action=delete_report&id=" . $reporte['id'] . "' onclick=\"return confirm('¿Confirmar eliminación?');\">Delete</a>";
                    echo "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No reports found.</p>";
            }
        } elseif ($accion === 'view_report' && isset($_GET['id'])) {
            $idReporte = intval($_GET['id']);
            $consultaReporte = $conexionDB->prepare("SELECT r.*, u.username FROM reports r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = :id");
            $consultaReporte->execute([':id' => $idReporte]);
            $detalleReporte = $consultaReporte->fetch(PDO::FETCH_ASSOC);
            if ($detalleReporte) {
                echo "<h2>Report Details</h2>";
                echo "<p><strong>" . __('url') . ":</strong> " . htmlspecialchars($detalleReporte['url']) . "</p>";
                echo "<p><strong>User:</strong> " . htmlspecialchars($detalleReporte['username']) . "</p>";
                echo "<p><strong>" . __('created_at') . ":</strong> " . htmlspecialchars($detalleReporte['created_at']) . "</p>";
                echo $detalleReporte['report_html'];
                echo "<p><a href='admin.php?action=manage_reports'>" . __('back_to_reports') . "</a></p>";
            }
        }
        ?>
    </main>
</body>
</html>