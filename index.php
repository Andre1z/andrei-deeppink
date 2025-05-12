<?php
/**
 * index.php
 *
 * Página principal de la aplicación.
 * Gestiona el login de usuarios, la creación de reportes de auditoría web (usando la clase DeepPink)
 * y la visualización y eliminación de reportes almacenados.
 *
 * Todos los estilos visuales se cargan desde "css/index.css".
 */

session_start();
require_once 'i18n.php';

try {
    // Conexión a la base de datos SQLite y creación de tablas necesarias.
    $pdo = new PDO('sqlite:db.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tabla de usuarios.
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        name TEXT,
        email TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabla de reportes.
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        url TEXT NOT NULL,
        report_html TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Error al conectar o inicializar la base de datos: " . $e->getMessage());
}

/**
 * Cierre de sesión:
 * Si se detecta la acción 'logout', se limpian las variables de sesión y se redirige al login.
 */
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['logged_in']);
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    header("Location: index.php");
    exit;
}

/**
 * Proceso de autenticación:
 * Si el usuario no ha iniciado sesión, se muestra el formulario de login.
 * También se permite cambiar el idioma desde el listado de opciones.
 */
if (!isset($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $loginUser = trim($_POST['username']);
        $loginPass = trim($_POST['password']);
        if (isset($_POST['lang'])) {
            $_SESSION['language'] = $_POST['lang'];
            require_once 'i18n.php';
        }
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $loginUser]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se compara la contraseña (en este ejemplo en texto plano).
        if ($user && $user['password'] === $loginPass) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: index.php");
            exit;
        } else {
            $loginError = __('invalid_credentials');
        }
    } else {
        // Mostrar formulario de login si no hay envío.
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?php echo __('login_page_title'); ?></title>
            <link rel="stylesheet" href="css/style.css">
        </head>
        <body>
            <div class="login-panel">
                <h2><?php echo __('login_title'); ?></h2>
                <?php if (isset($loginError)) { echo "<p class='error'>$loginError</p>"; } ?>
                <form method="post" action="index.php">
                    <input type="text" name="username" placeholder="<?php echo __('username_placeholder'); ?>" required>
                    <input type="password" name="password" placeholder="<?php echo __('password_placeholder'); ?>" required>
                    <label for="lang"><?php echo __('select_language'); ?></label>
                    <select name="lang" id="lang">
                        <option value="en">English</option>
                        <option value="es">Español</option>
                        <option value="fr">Français</option>
                        <option value="de">Deutsch</option>
                    </select>
                    <input type="submit" name="login" value="<?php echo __('login_button'); ?>">
                </form>
                <p><a href="register.php"><?php echo __('register_here'); ?></a></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * Eliminación de reportes:
 * Si se recibe 'action=delete' y se especifica el ID del reporte,
 * se elimina de la base de datos (comprobando además que el reporte pertenezca al usuario).
 */
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $reportId = intval($_GET['id']);
    $deleteStmt = $pdo->prepare("DELETE FROM reports WHERE id = :id AND user_id = :uid");
    $deleteStmt->execute([':id' => $reportId, ':uid' => $_SESSION['user_id']]);
    header("Location: index.php?tab=view_reports");
    exit;
}

/**
 * Generación del reporte:
 * Si se envía el formulario con 'report_action' igual a 'gen_report', se utiliza la clase DeepPink.
 */
if (isset($_POST['report_action']) && $_POST['report_action'] === 'gen_report') {
    require_once 'DeepPink.php';
    $inputUrl = trim($_POST['url']);
    $reporter = new DeepPink($inputUrl);

    // Se inicia el búfer para capturar la salida HTML.
    ob_start();
    echo "<table class='report-table'>";
        $reporter->displayPageTitle();
        $reporter->displayMetaDescription();
        $reporter->displayFrequentWords();
        $reporter->displayWordCloud();
        for ($hdr = 1; $hdr <= 6; $hdr++) {
            $reporter->displayHeadingTags($hdr);
        }
        $reporter->checkRobotsFile();
        $reporter->checkSitemapFile();
        $reporter->checkImagesAltText();
        $reporter->checkFaviconPresence();
    echo "</table>";
    $generatedReport = ob_get_clean();

    // Si se opta por guardar el reporte.
    if (isset($_POST['save_report']) && $_POST['save_report'] === '1') {
        $saveStmt = $pdo->prepare("INSERT INTO reports (user_id, url, report_html) VALUES (:uid, :url, :report)");
        $saveStmt->execute([
            ':uid' => $_SESSION['user_id'],
            ':url' => $inputUrl,
            ':report' => $generatedReport
        ]);
        $reportFeedback = __('save_report') . " " . __('generated_report');
    }
}

/**
 * Consulta de reportes:
 * Se agrupan los reportes guardados por URL, y se recupera la lista completa de reportes.
 */
$groupStmt = $pdo->prepare("SELECT url, COUNT(*) AS total, MAX(created_at) AS last_date FROM reports WHERE user_id = :uid GROUP BY url ORDER BY last_date DESC");
$groupStmt->execute([':uid' => $_SESSION['user_id']]);
$reportsSummary = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

$allStmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = :uid ORDER BY created_at DESC");
$allStmt->execute([':uid' => $_SESSION['user_id']]);
$allReports = $allStmt->fetchAll(PDO::FETCH_ASSOC);

// Determinamos la pestaña activa ('new_report' o 'view_reports'). Por defecto, "new_report".
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'new_report';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo __('dashboard_title'); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Cabecera del panel -->
    <header id="dashboard-header">
        <div class="header-left">
            <h1><?php echo __('welcome_message'); ?></h1>
            <p><?php echo __('dashboard_subtitle'); ?></p>
        </div>
        <div class="header-right">
            <a href="index.php?action=logout"><?php echo __('logout'); ?></a>
        </div>
    </header>
    <!-- Contenedor principal del Dashboard -->
    <div id="dashboard-container">
        <!-- Menú lateral -->
        <nav id="dashboard-nav">
            <ul>
                <li><a href="index.php?tab=new_report"><?php echo __('create_report'); ?></a></li>
                <li><a href="index.php?tab=view_reports"><?php echo __('view_reports'); ?></a></li>
            </ul>
        </nav>
        <!-- Sección de contenido -->
        <main id="dashboard-content">
            <?php
            if (isset($reportFeedback)) {
                echo "<p class='feedback'>$reportFeedback</p>";
            }
            if ($currentTab === 'new_report') {
                ?>
                <h2><?php echo __('create_report'); ?></h2>
                <form method="post" action="index.php">
                    <p>
                        <label for="url"><?php echo __('enter_url'); ?></label><br>
                        <input type="url" id="url" name="url" required style="max-width:400px; width:100%;">
                    </p>
                    <input type="hidden" name="report_action" value="gen_report">
                    <p><input type="submit" value="<?php echo __('create_report'); ?>"></p>
                </form>
                <?php
                if (isset($generatedReport) && (!isset($_POST['save_report']) || $_POST['save_report'] !== '1')) {
                    echo "<h2>" . __('generated_report') . "</h2>";
                    echo $generatedReport;
                    echo "<form method='post' action='index.php'>";
                    echo "<input type='hidden' name='report_action' value='gen_report'>";
                    echo "<input type='hidden' name='url' value='" . htmlspecialchars($inputUrl, ENT_QUOTES) . "'>";
                    echo "<input type='hidden' name='save_report' value='1'>";
                    echo "<input type='submit' value='" . __('save_report') . "'>";
                    echo "</form>";
                }
            } elseif ($currentTab === 'view_reports') {
                echo "<h2>" . __('view_reports') . "</h2>";
                if ($reportsSummary) {
                    echo "<table class='report-table'>";
                    echo "<tr><th>" . __('url') . "</th><th>" . __('report_count') . "</th><th>" . __('last_report') . "</th><th>" . __('actions') . "</th></tr>";
                    foreach ($reportsSummary as $row) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['url']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['total']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['last_date']) . "</td>";
                        echo "<td><a href='index.php?tab=view_reports&url=" . urlencode($row['url']) . "'>" . __('view_details') . "</a></td>";
                        echo "</tr>";
                    }
                    echo "</table>";

                    // Vista detallada si se especifica la URL.
                    if (isset($_GET['url'])) {
                        $targetUrl = $_GET['url'];
                        $detailStmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = :uid AND url = :url ORDER BY created_at DESC");
                        $detailStmt->execute([':uid' => $_SESSION['user_id'], ':url' => $targetUrl]);
                        $detailedReports = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
                        if ($detailedReports) {
                            echo "<h3>" . __('reports_for') . " " . htmlspecialchars($targetUrl) . "</h3>";
                            echo "<table class='report-table'>";
                            echo "<tr><th>" . __('date') . "</th><th>" . __('actions') . "</th></tr>";
                            foreach ($detailedReports as $rep) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($rep['created_at']) . "</td>";
                                echo "<td>";
                                echo "<a href='index.php?action=view&id=" . $rep['id'] . "&tab=view_reports'>" . __('view') . "</a> | ";
                                echo "<a href='index.php?action=print&id=" . $rep['id'] . "&tab=view_reports' target='_blank'>" . __('print') . "</a> | ";
                                echo "<a href='index.php?action=delete&id=" . $rep['id'] . "&tab=view_reports' onclick=\"return confirm('¿Desea eliminar este reporte?');\">" . __('delete') . "</a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            echo "</table>";
                        } else {
                            echo "<p>" . __('no_reports') . "</p>";
                        }
                    }
                } else {
                    echo "<p>" . __('no_reports') . "</p>";
                }
            }
            // Vista detallada de un reporte único (triggered by action=view).
            if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
                $repId = intval($_GET['id']);
                $stmtRep = $pdo->prepare("SELECT * FROM reports WHERE id = :id AND user_id = :uid");
                $stmtRep->execute([':id' => $repId, ':uid' => $_SESSION['user_id']]);
                $singleReport = $stmtRep->fetch(PDO::FETCH_ASSOC);
                if ($singleReport) {
                    echo "<h2>" . __('generated_report') . "</h2>";
                    echo "<p><a href='index.php?tab=view_reports'>" . __('back_to_reports') . "</a></p>";
                    echo $singleReport['report_html'];
                }
            }
            // Vista de impresión: se abre en una nueva ventana y se dispara window.print().
            if (isset($_GET['action']) && $_GET['action'] === 'print' && isset($_GET['id'])) {
                $repId = intval($_GET['id']);
                $stmtPrint = $pdo->prepare("SELECT * FROM reports WHERE id = :id AND user_id = :uid");
                $stmtPrint->execute([':id' => $repId, ':uid' => $_SESSION['user_id']]);
                $printReport = $stmtPrint->fetch(PDO::FETCH_ASSOC);
                if ($printReport) {
                    ?>
                    <!doctype html>
                    <html>
                    <head>
                        <meta charset="utf-8">
                        <title><?php echo __('generated_report'); ?></title>
                        <link rel="stylesheet" href="css/style.css">
                        <style>
                            @media print {
                                body * {
                                    visibility: hidden;
                                }
                                .printable, .printable * {
                                    visibility: visible;
                                }
                                .printable {
                                    position: absolute;
                                    left: 0;
                                    top: 0;
                                    width: 100%;
                                }
                            }
                        </style>
                        <script>
                            window.onload = function() {
                                window.print();
                            };
                        </script>
                    </head>
                    <body>
                        <div class="printable">
                            <?php echo $printReport['report_html']; ?>
                        </div>
                    </body>
                    </html>
                    <?php
                    exit;
                }
            }
            ?>
        </main>
    </div>
</body>
</html>