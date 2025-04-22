<?php
session_start();
require_once 'i18n.php';

try {
    $db = new PDO('sqlite:db.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        name TEXT,
        email TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create reports table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        url TEXT NOT NULL,
        report_html TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// --- Logout Process ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['loggedin']);
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    header("Location: index.php");
    exit;
}

// --- Login Process ---
if (!isset($_SESSION['loggedin'])) {
    if (isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        if (isset($_POST['lang'])) {
            $_SESSION['lang'] = $_POST['lang'];
            require_once 'i18n.php';
        }
        
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // For demonstration purposes, password is checked in plain text.
        if ($user && $user['password'] === $password) {
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: index.php");
            exit;
        } else {
            $login_error = __('invalid_credentials');
        }
    }
    ?>
    <!doctype html>
    <html>
    <head>
      <meta charset="utf-8">
      <title><?php echo __('login_page_title'); ?></title>
      <link rel="stylesheet" href="css/style.css">
      <style>
        .login-container {
          max-width: 300px;
          margin: 30px auto;
          padding: 20px;
          background: #fff;
          border: 1px solid #ddd;
          box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .login-container h2 {
          text-align: center;
          margin-bottom: 20px;
        }
        .login-container input[type="text"],
        .login-container input[type="password"],
        .login-container select {
          width: 100%;
          padding: 10px;
          margin: 10px 0;
          border: 1px solid #ccc;
          border-radius: 4px;
        }
        .login-container input[type="submit"] {
          width: 100%;
          padding: 10px;
          background: #0073aa;
          color: #fff;
          border: none;
          border-radius: 4px;
          cursor: pointer;
        }
        .login-container input[type="submit"]:hover {
          background: #005177;
        }
        .login-error {
          color: red;
          text-align: center;
        }
      </style>
    </head>
    <body>
      <div class="login-container">
        <h2><?php echo __('login_title'); ?></h2>
        <?php if(isset($login_error)) { echo "<p class='login-error'>$login_error</p>"; } ?>
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
        <p style="text-align:center;"><a href="register.php"><?php echo __('register_here'); ?></a></p>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// --- Process Report Deletion ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Optional: verify that the report belongs to the current user.
    $stmt = $db->prepare("DELETE FROM reports WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $id, ':user_id' => $_SESSION['user_id']]);
    header("Location: index.php?tab=view_reports");
    exit;
}

// --- Process Report Generation & Saving ---
if (isset($_POST['dp_action']) && $_POST['dp_action'] === 'generate_report') {
    require_once 'DeepPink.php';
    $url = trim($_POST['url']);
    $web = new DeepPink($url);
    ob_start();
    echo "<table class='report-table'>";
      $web->dameTitulo();
      $web->dameDescripcion();
      $web->damePalabras();
      $web->nubeDePalabras();
      for ($i = 1; $i <= 6; $i++) {
          $web->dameTitulos($i);
      }
      $web->checkRobots();
      $web->checkSitemap();
      $web->checkImagesAlt();
      $web->checkFavicon();
    echo "</table>";
    $report_html = ob_get_clean();

    if (isset($_POST['dp_save']) && $_POST['dp_save'] === '1') {
        $stmt = $db->prepare("INSERT INTO reports (user_id, url, report_html) VALUES (:user_id, :url, :report_html)");
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':url' => $url,
            ':report_html' => $report_html
        ]);
        $save_message = __('save_report') . " " . __('generated_report');
    }
}

// Retrieve Saved Reports grouped by URL for the logged-in user.
$stmt = $db->prepare("SELECT url, COUNT(*) as count, MAX(created_at) as last_report FROM reports WHERE user_id = :user_id GROUP BY url ORDER BY last_report DESC");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$grouped_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve all reports for detailed view if needed.
$reports = $db->prepare("SELECT * FROM reports WHERE user_id = :user_id ORDER BY created_at DESC");
$reports->execute([':user_id' => $_SESSION['user_id']]);
$all_reports = $reports->fetchAll(PDO::FETCH_ASSOC);

// Determine Active Tab (default to "new_report")
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'new_report';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo __('dashboard_title'); ?></title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* Dashboard Layout Styles */
    body {
      font-family: sans-serif;
      background-color: #f1f1f1;
      margin: 0;
      padding: 0;
    }
    #dashboard-header {
      background: #23282d;
      color: #fff;
      padding: 10px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    #dashboard-header h1 {
      margin: 0;
      font-size: 24px;
    }
    #dashboard-header p {
      margin: 0;
      font-size: 14px;
    }
    #dashboard-header a {
      color: #fff;
      text-decoration: none;
      margin-left: 20px;
    }
    #dashboard-container {
      display: flex;
      min-height: calc(100vh - 60px);
    }
    #dashboard-nav {
      width: 200px;
      background: #32373c;
      color: #fff;
      padding: 20px;
    }
    #dashboard-nav ul {
      list-style: none;
      padding: 0;
    }
    #dashboard-nav ul li {
      margin-bottom: 10px;
    }
    #dashboard-nav ul li a {
      color: #fff;
      text-decoration: none;
    }
    #dashboard-content {
      flex: 1;
      padding: 20px;
      background: #fff;
    }
    .report-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    .report-table th, .report-table td {
      border: 1px solid #ddd;
      padding: 8px;
    }
  </style>
</head>
<body>
  <div id="dashboard-header">
    <div>
      <h1><?php echo __('welcome_message'); ?></h1>
      <p><?php echo __('dashboard_subtitle'); ?></p>
    </div>
    <div>
      <a href="index.php?action=logout"><?php echo __('logout'); ?></a>
    </div>
  </div>
  <div id="dashboard-container">
    <div id="dashboard-nav">
      <ul>
        <li><a href="index.php?tab=new_report"><?php echo __('create_report'); ?></a></li>
        <li><a href="index.php?tab=view_reports"><?php echo __('view_reports'); ?></a></li>
      </ul>
    </div>
    <div id="dashboard-content">
      <?php
      if (isset($save_message)) {
          echo "<p style='color:green;'>$save_message</p>";
      }
      
      if ($tab === 'new_report') {
      ?>
          <h2><?php echo __('create_report'); ?></h2>
          <form method="post" action="">
            <p>
              <label for="url"><?php echo __('enter_url'); ?></label><br>
              <input type="url" id="url" name="url" required style="width:100%;max-width:400px;">
            </p>
            <p>
              <input type="hidden" name="dp_action" value="generate_report">
              <input type="submit" value="<?php echo __('create_report'); ?>">
            </p>
          </form>
          <?php
          if (isset($report_html) && (!isset($_POST['dp_save']) || $_POST['dp_save'] !== '1')) {
              echo "<h2>" . __('generated_report') . "</h2>";
              echo $report_html;
              echo "<form method='post' action=''>";
              echo "<input type='hidden' name='dp_action' value='generate_report'>";
              echo "<input type='hidden' name='url' value='" . htmlspecialchars($url, ENT_QUOTES) . "'>";
              echo "<input type='hidden' name='dp_save' value='1'>";
              echo "<input type='submit' value='" . __('save_report') . "'>";
              echo "</form>";
          }
      } elseif ($tab === 'view_reports') {
          echo "<h2>" . __('view_reports') . "</h2>";
          if ($grouped_reports) {
              echo "<table class='report-table'>";
              echo "<tr><th>" . __('url') . "</th><th>" . __('report_count') . "</th><th>" . __('last_report') . "</th><th>" . __('actions') . "</th></tr>";
              foreach ($grouped_reports as $grp) {
                  echo "<tr>";
                  echo "<td>" . htmlspecialchars($grp['url']) . "</td>";
                  echo "<td>" . htmlspecialchars($grp['count']) . "</td>";
                  echo "<td>" . htmlspecialchars($grp['last_report']) . "</td>";
                  echo "<td><a href='index.php?tab=view_reports&url=" . urlencode($grp['url']) . "'>" . __('view_details') . "</a></td>";
                  echo "</tr>";
              }
              echo "</table>";
              
              // Detailed view for a specific URL.
              if (isset($_GET['url'])) {
                  $selected_url = $_GET['url'];
                  $stmt = $db->prepare("SELECT * FROM reports WHERE user_id = :user_id AND url = :url ORDER BY created_at DESC");
                  $stmt->execute([':user_id' => $_SESSION['user_id'], ':url' => $selected_url]);
                  $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
                  if ($details) {
                      echo "<h3>" . __('reports_for') . " " . htmlspecialchars($selected_url) . "</h3>";
                      echo "<table class='report-table'>";
                      echo "<tr><th>" . __('date') . "</th><th>" . __('actions') . "</th></tr>";
                      foreach ($details as $detail) {
                          echo "<tr>";
                          echo "<td>" . htmlspecialchars($detail['created_at']) . "</td>";
                          echo "<td>";
                          echo "<a href='index.php?action=view&id=" . $detail['id'] . "&tab=view_reports'>" . __('view') . "</a> | ";
                          echo "<a href='index.php?action=print&id=" . $detail['id'] . "&tab=view_reports' target='_blank'>" . __('print') . "</a> | ";
                          echo "<a href='index.php?action=delete&id=" . $detail['id'] . "&tab=view_reports' onclick=\"return confirm('Are you sure you want to delete this report?');\">" . __('delete') . "</a>";
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
      // Detailed view of a single report.
      if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
          $id = intval($_GET['id']);
          $stmt = $db->prepare("SELECT * FROM reports WHERE id = :id AND user_id = :user_id");
          $stmt->execute([':id' => $id, ':user_id' => $_SESSION['user_id']]);
          $report = $stmt->fetch(PDO::FETCH_ASSOC);
          if ($report) {
              echo "<h2>" . __('generated_report') . "</h2>";
              echo "<p><a href='index.php?tab=view_reports'>" . __('back_to_reports') . "</a></p>";
              echo $report['report_html'];
          }
      }
      if (isset($_GET['action']) && $_GET['action'] === 'print' && isset($_GET['id'])) {
          $id = intval($_GET['id']);
          $stmt = $db->prepare("SELECT * FROM reports WHERE id = :id AND user_id = :user_id");
          $stmt->execute([':id' => $id, ':user_id' => $_SESSION['user_id']]);
          $report = $stmt->fetch(PDO::FETCH_ASSOC);
          if ($report) {
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
                      }
                  </script>
              </head>
              <body>
                  <div class="printable">
                      <?php echo $report['report_html']; ?>
                  </div>
              </body>
              </html>
              <?php
              exit;
          }
      }
      ?>
    </div>
  </div>
</body>
</html>

