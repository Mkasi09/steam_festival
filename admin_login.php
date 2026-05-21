<?php
session_start();
require_once __DIR__ . '/config.php';

if (isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {

        $pdo = db();

        $stmt = $pdo->prepare(
            'SELECT * FROM admins WHERE username = ? LIMIT 1'
        );

        $stmt->execute([$username]);

        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            header('Location: admin.php');
            exit;

        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:Arial, Helvetica, sans-serif;
        }

        body{
            background:#f4f7f6;
            display:flex;
            justify-content:center;
            align-items:center;
            height:100vh;
        }

        .login-box{
            width:400px;
            background:#fff;
            padding:40px;
            border-radius:10px;
            box-shadow:0 0 20px rgba(0,0,0,0.1);
        }

        .login-box h1{
            text-align:center;
            margin-bottom:30px;
            color:#0f766e;
        }

        .form-group{
            margin-bottom:20px;
        }

        label{
            display:block;
            margin-bottom:8px;
            font-weight:bold;
        }

        input{
            width:100%;
            padding:12px;
            border:1px solid #ccc;
            border-radius:6px;
            font-size:15px;
        }

        button{
            width:100%;
            padding:14px;
            background:#0f766e;
            color:white;
            border:none;
            border-radius:6px;
            font-size:16px;
            cursor:pointer;
        }

        button:hover{
            background:#115e59;
        }

        .error{
            background:#ffe5e5;
            color:#c62828;
            padding:12px;
            margin-bottom:20px;
            border-radius:6px;
        }

    </style>

</head>
<body>

    <div class="login-box">

        <h1>Admin Login</h1>

        <?php if ($error): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit">
                Login
            </button>

        </form>

    </div>

</body>
</html>