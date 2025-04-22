<?php
session_start();
require_once 'i18n.php';

try {
    $db = new PDO('sqlite:db.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table if not exists (if not already created in index.php)
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        name TEXT,
        email TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']); // In production, hash the password.
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    
    // Check if username already exists.
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        $error = __('username_exists');
    } else {
        $stmt = $db->prepare("INSERT INTO users (username, password, name, email) VALUES (:username, :password, :name, :email)");
        $stmt->execute([
            ':username' => $username,
            ':password' => $password, // In production, hash this.
            ':name' => $name,
            ':email' => $email
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
    .login-container input[type="email"] {
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
    <h2><?php echo __('register_title'); ?></h2>
    <?php if(isset($error)) { echo "<p class='login-error'>$error</p>"; } ?>
    <form method="post" action="register.php">
      <input type="text" name="username" placeholder="<?php echo __('username_placeholder'); ?>" required>
      <input type="password" name="password" placeholder="<?php echo __('password_placeholder'); ?>" required>
      <input type="text" name="name" placeholder="<?php echo __('name_placeholder'); ?>" required>
      <input type="email" name="email" placeholder="<?php echo __('email_placeholder'); ?>" required>
      <input type="submit" value="<?php echo __('register_button'); ?>">
    </form>
    <p style="text-align:center;"><a href="index.php"><?php echo __('back_to_login'); ?></a></p>
  </div>
</body>
</html>

