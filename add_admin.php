<?php
require_once __DIR__ . '/config.php';

$pdo = db();

$username = 'admin';

/*
PASSWORD:
admin123
*/

$password = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO admins (username, password)
     VALUES (?, ?)'
);

$stmt->execute([$username, $password]);

echo "Admin user created successfully.";