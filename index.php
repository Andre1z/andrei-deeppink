<?php
/**
 * index.php
 *
 * Página principal de la aplicación.
 * Gestiona el login de usuarios, la generación de reportes de auditoría web (usando la clase DeepPink)
 * y la visualización/eliminación de reportes guardados.
 *
 * Se utiliza la función authenticateUser() (en "authenticateUser.php") para verificar
 * las credenciales del usuario. Si las credenciales son incorrectas, se muestra en el formulario
 * de login el mensaje "Usuario o contraseña incorrectos" en rojo, sin redirigir al dashboard.
 *
 * Todos los estilos se cargan desde "css/style.css".
 */

session_start();
require_once 'i18n.php';

try {
    // Conexión a la base de datos SQLite e inicialización de tablas si no existen.
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
    die("Error al inicializar la base de datos: " . $e->getMessage());
}

/**
 * Proceso de cierre de sesión.
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
 * Se utiliza la función authenticateUser() (definida en "authenticateUser.php") para la verificación.
 * En caso de que las credenciales sean incorrectas, se asigna el mensaje de error para mostrarlo en el formulario.
 */
if (!isset($_SESSION['logged_in'])) {
    if (isset($_POST['login'])) {
        $loginUser = trim($_POST['username']);
        $loginPass = trim($_POST['password']);

        if (isset($_POST['lang'])) {
            $_SESSION['language'] = $_POST['lang'];
            require_once 'i18n.php';
        }
        require_once 'authenticateUser.php';
        $user = authenticateUser($loginUser, $loginPass);
        if ($user) {
            // Credenciales válidas: se guardan los datos en la sesión y se redirige.
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: index.php");
            exit;
        } else {
            // Si la autenticación falla, se asigna el mensaje de error para mostrarlo en el login.
            $loginError = "Usuario o contraseña incorrectos";
        }
    }
    // Mostrar el formulario de login si el usuario no está autenticado.
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
            <?php
            // Se muestra el mensaje de error únicamente en el bloque del login.
            if (isset($loginError)) {
                echo "<p class='error'>{$loginError}</p>";
            }
            ?>
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

// Si el usuario ya se ha autenticado, se continúa con el dashboard.
$userId = $_SESSION['user_id'] ?? 0;

/**
 * Proceso de eliminación de reportes.
 */
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $reportId = intval($_GET['id']);
    $deleteStmt = $pdo->prepare("DELETE FROM reports WHERE id = :id AND user_id = :uid");
    $deleteStmt->execute([':id' => $reportId, ':uid' => $userId]);
    header("Location: index.php?tab=view_reports");
    exit;
}

/**
 * Proceso de generación de reportes.
 * Se utiliza la clase DeepPink para analizar la URL ingresada y generar un reporte.
 */
if (isset($_POST['report_action']) && $_POST['report_action'] === 'gen_report') {
    require_once 'DeepPink.php';
    $inputUrl = trim($_POST['url']);
    $reporter = new DeepPink($inputUrl);

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

    if (isset($_POST['save_report']) && $_POST['save_report'] === '1') {
        $saveStmt = $pdo->prepare("INSERT INTO reports (user_id, url, report_html) VALUES (:uid, :url, :report)");
        $saveStmt->execute([
            ':uid'    => $userId,
            ':url'    => $inputUrl,
            ':report' => $generatedReport
        ]);
        $reportFeedback = __('save_report') . " " . __('generated_report');
    }
}

/**
 * Consulta de reportes agrupados para el usuario actual.
 */
$groupStmt = $pdo->prepare("SELECT url, COUNT(*) AS total, MAX(created_at) AS last_date 
                            FROM reports 
                            WHERE user_id = :uid 
                            GROUP BY url 
                            ORDER BY last_date DESC");
$groupStmt->execute([':uid' => $userId]);
$reportsSummary = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

$allStmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = :uid ORDER BY created_at DESC");
$allStmt->execute([':uid' => $userId]);
$allReports = $allStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <!-- Cabecera del Dashboard -->
    <header id="dashboard-header">
        <div class="header-left">
            <h1><?php echo __('welcome_message'); ?></h1>
            <p><?php echo __('dashboard_subtitle'); ?></p>
        </div>
        <div class="header-right">
            <a href="index.php?action=logout"><?php echo __('logout'); ?></a>
        </div>
    </header>
    <!-- Contenedor principal de la aplicación -->
    <div id="dashboard-container">
        <!-- Menú lateral -->
        <nav id="dashboard-nav">
            <ul>
                <li><a href="index.php?tab=new_report"><?php echo __('create_report'); ?></a></li>
                <li><a href="index.php?tab=view_reports"><?php echo __('view_reports'); ?></a></li>
            </ul>
        </nav>
        <!-- Sección principal de contenido -->
        <main id="dashboard-content">
            <?php
            if (isset($reportFeedback)) {
                echo "<p class='feedback'>{$reportFeedback}</p>";
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

                    // Vista detallada para una URL específica.
                    if (isset($_GET['url'])) {
                        $targetUrl = $_GET['url'];
                        $detailStmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = :uid AND url = :url ORDER BY created_at DESC");
                        $detailStmt->execute([':uid' => $userId, ':url' => $targetUrl]);
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
            if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
                $repId = intval($_GET['id']);
                $stmtRep = $pdo->prepare("SELECT * FROM reports WHERE id = :id AND user_id = :uid");
                $stmtRep->execute([':id' => $repId, ':uid' => $userId]);
                $singleReport = $stmtRep->fetch(PDO::FETCH_ASSOC);
                if ($singleReport) {
                    echo "<h2>" . __('generated_report') . "</h2>";
                    echo "<p><a href='index.php?tab=view_reports'>" . __('back_to_reports') . "</a></p>";
                    echo $singleReport['report_html'];
                }
            }
            if (isset($_GET['action']) && $_GET['action'] === 'print' && isset($_GET['id'])) {
                $repId = intval($_GET['id']);
                $stmtPrint = $pdo->prepare("SELECT * FROM reports WHERE id = :id AND user_id = :uid");
                $stmtPrint->execute([':id' => $repId, ':uid' => $userId]);
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
                                body * { visibility: hidden; }
                                .printable, .printable * { visibility: visible; }
                                .printable { position: absolute; left: 0; top: 0; width: 100%; }
                            }
                        </style>
                        <script>
                            window.onload = function() { window.print(); };
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