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

// --- Admin Login Process ---
if (!isset($_SESSION['admin_loggedin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        // For simplicity, only user 'jocarsa' is considered admin.
        if ($admin && $username === 'jocarsa' && $admin['password'] === $password) {
            $_SESSION['admin_loggedin'] = true;
            $_SESSION['admin_username'] = $username;
        } else {
            $login_error = __('invalid_credentials');
        }
    } else {
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?php echo __('admin_login_title'); ?></title>
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
                .login-container input[type="password"] {
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
                <h2><?php echo __('admin_login_title'); ?></h2>
                <?php if(isset($login_error)) { echo "<p class='login-error'>$login_error</p>"; } ?>
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

// --- Determine Current Admin Action ---
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

// --- Admin Logout ---
if ($action == 'logout') {
    unset($_SESSION['admin_loggedin']);
    unset($_SESSION['admin_username']);
    header("Location: admin.php");
    exit;
}

// --- Handle User Deletion (prevent deleting admin) ---
if ($action == 'delete_user' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT username FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && $user['username'] === 'jocarsa') {
        $error = "Cannot delete admin user.";
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        header("Location: admin.php?action=manage_users");
        exit;
    }
}

// --- Handle Report Deletion ---
if ($action == 'delete_report' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("DELETE FROM reports WHERE id = :id");
    $stmt->execute([':id' => $id]);
    header("Location: admin.php?action=manage_reports");
    exit;
}

// --- Handle Adding a New User ---
if ($action == 'add_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']); // In production, hash the password.
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    
    $stmt = $db->prepare("INSERT INTO users (username, password, name, email) VALUES (:username, :password, :name, :email)");
    $stmt->execute([
        ':username' => $username,
        ':password' => $password,
        ':name' => $name,
        ':email' => $email
    ]);
    header("Location: admin.php?action=manage_users");
    exit;
}

// --- Handle Editing a User ---
if ($action == 'edit_user' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $name     = trim($_POST['name']);
        $email    = trim($_POST['email']);
        
        $stmt = $db->prepare("UPDATE users SET username = :username, password = :password, name = :name, email = :email WHERE id = :id");
        $stmt->execute([
            ':username' => $username,
            ':password' => $password,
            ':name' => $name,
            ':email' => $email,
            ':id' => $id
        ]);
        header("Location: admin.php?action=manage_users");
        exit;
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $userToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo __('admin_dashboard_title'); ?></title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    #dashboard-header {
      background: #23282d;
      color: #fff;
      padding: 10px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
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
    <h1><?php echo __('admin_dashboard_title'); ?></h1>
    <div>
      <a href="admin.php?action=logout"><?php echo __('logout'); ?></a>
    </div>
  </div>
  <div id="dashboard-container">
    <div id="dashboard-nav">
      <ul>
        <li><a href="admin.php?action=manage_users"><?php echo __('manage_users'); ?></a></li>
        <li><a href="admin.php?action=manage_reports"><?php echo __('manage_reports'); ?></a></li>
      </ul>
    </div>
    <div id="dashboard-content">
      <?php
      if ($action == 'manage_users') {
          echo "<h2>" . __('manage_users') . "</h2>";
          if (isset($error)) {
              echo "<p style='color:red;'>$error</p>";
          }
          $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
          $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
          if ($users) {
              echo "<table class='report-table'>";
              echo "<tr><th>ID</th><th>" . __('username') . "</th><th>" . __('name') . "</th><th>" . __('email') . "</th><th>" . __('created_at') . "</th><th>" . __('actions') . "</th></tr>";
              foreach ($users as $user) {
                  echo "<tr>";
                  echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                  echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                  echo "<td>" . htmlspecialchars($user['name']) . "</td>";
                  echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                  echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                  echo "<td>";
                  echo "<a href='admin.php?action=edit_user&id=" . $user['id'] . "'>Edit</a> | ";
                  if ($user['username'] !== 'jocarsa') {
                      echo "<a href='admin.php?action=delete_user&id=" . $user['id'] . "' onclick=\"return confirm('Are you sure?');\">Delete</a>";
                  }
                  echo "</td>";
                  echo "</tr>";
              }
              echo "</table>";
          } else {
              echo "<p>No users found.</p>";
          }
          echo "<h3>Add New User</h3>";
          ?>
          <form method="post" action="admin.php?action=add_user">
              <p><input type="text" name="username" placeholder="<?php echo __('username_placeholder'); ?>" required></p>
              <p><input type="password" name="password" placeholder="<?php echo __('password_placeholder'); ?>" required></p>
              <p><input type="text" name="name" placeholder="<?php echo __('name_placeholder'); ?>" required></p>
              <p><input type="email" name="email" placeholder="<?php echo __('email_placeholder'); ?>" required></p>
              <p><input type="submit" value="Add User"></p>
          </form>
          <?php
      } elseif ($action == 'edit_user' && isset($userToEdit)) {
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
      } elseif ($action == 'manage_reports') {
          echo "<h2>" . __('manage_reports') . "</h2>";
          $stmt = $db->query("SELECT r.*, u.username FROM reports r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");
          $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
          if ($reports) {
              echo "<table class='report-table'>";
              echo "<tr><th>ID</th><th>" . __('url') . "</th><th>" . __('user') . "</th><th>" . __('created_at') . "</th><th>" . __('actions') . "</th></tr>";
              foreach ($reports as $report) {
                  echo "<tr>";
                  echo "<td>" . htmlspecialchars($report['id']) . "</td>";
                  echo "<td>" . htmlspecialchars($report['url']) . "</td>";
                  echo "<td>" . htmlspecialchars($report['username']) . "</td>";
                  echo "<td>" . htmlspecialchars($report['created_at']) . "</td>";
                  echo "<td>";
                  echo "<a href='admin.php?action=view_report&id=" . $report['id'] . "'>View</a> | ";
                  echo "<a href='admin.php?action=delete_report&id=" . $report['id'] . "' onclick=\"return confirm('Are you sure?');\">Delete</a>";
                  echo "</td>";
                  echo "</tr>";
              }
              echo "</table>";
          } else {
              echo "<p>No reports found.</p>";
          }
      } elseif ($action == 'view_report' && isset($_GET['id'])) {
          $id = intval($_GET['id']);
          $stmt = $db->prepare("SELECT r.*, u.username FROM reports r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = :id");
          $stmt->execute([':id' => $id]);
          $report = $stmt->fetch(PDO::FETCH_ASSOC);
          if ($report) {
              echo "<h2>Report Details</h2>";
              echo "<p><strong>" . __('url') . ":</strong> " . htmlspecialchars($report['url']) . "</p>";
              echo "<p><strong>User:</strong> " . htmlspecialchars($report['username']) . "</p>";
              echo "<p><strong>" . __('created_at') . ":</strong> " . htmlspecialchars($report['created_at']) . "</p>";
              echo $report['report_html'];
              echo "<p><a href='admin.php?action=manage_reports'>" . __('back_to_reports') . "</a></p>";
          }
      } else {
          echo "<h2>" . __('admin_dashboard_title') . "</h2>";
          echo "<p>Welcome to the admin dashboard.</p>";
      }
      ?>
    </div>
  </div>
</body>
</html>

