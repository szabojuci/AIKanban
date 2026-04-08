<?php

$host = 'localhost';
$db   = 'taipo';
$user = 'root';
$pass = '';

$dsn = "mysql:host=$host;dbname=$db";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    throw new PDOException($e->getMessage(), (int)$e->getCode());
}

$sql = "INSERT INTO taipo_users (id, username, password_hash, created_at, is_instructor) VALUES (?, ?, ?, ?, ?)";
$stmt = $pdo->prepare($sql);

$std_fake_pass = '';  # MAKE SURE TO CHECK THE MIN_PASSWORD_LENGTH IN THE .env FILE (8 chars in the example, so don't use: 123456. maybe it will make the sql import, but if so, you won't be able to log in). once it's done: delete the file from a server, or at least remove your credentials.
$std_fake_pass_hash = password_hash($std_fake_pass, PASSWORD_BCRYPT);

for ($i = 1; $i <= 100; $i++) {
    $id = $i + 42;
    $stmt->execute([
        $id,
        sprintf('std%04d', $i),  # std0001, std0002, ... , std0100
        $std_fake_pass_hash,
        date('Y-m-d H:i:s'),
        0  # 1 = instructor, so don't use 1 here.
    ]);
}

echo "Successfully added 100 students.";
