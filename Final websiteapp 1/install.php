<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

use App\Core\Database;

$status = null;
$error = null;
$tableCount = 0;

function install_sql_body(string $path): string
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Cannot read schema file.');
    }

    $marker = '-- 5.2. Queries';
    $position = strpos($sql, $marker);
    if ($position !== false) {
        $sql = substr($sql, 0, $position);
    }

    return $sql;
}

function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $quote = null;
    $escaped = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $buffer .= $char;

        if ($escaped) {
            $escaped = false;
            continue;
        }

        if ($char === '\\') {
            $escaped = true;
            continue;
        }

        if ($quote !== null) {
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === "'" || $char === '"') {
            $quote = $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
        }
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function installed_table_count(): int
{
    if (!Database::ready()) {
        return 0;
    }

    return (int) Database::pdo()->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()"
    )->fetchColumn();
}

function seed_demo_credentials(): void
{
    $pdo = Database::pdo();

    $memberPasswordHash = password_hash('123456', PASSWORD_DEFAULT);
    $memberPhones = ['0900000001', '0900000002', '0900000003', '0900000004', '0900000005', '0900000006'];
    $memberStmt = $pdo->prepare(
        "UPDATE customers
         SET password_hash = :password_hash
         WHERE phone_number = :phone_number"
    );
    foreach ($memberPhones as $phone) {
        $memberStmt->execute([
            'password_hash' => $memberPasswordHash,
            'phone_number' => $phone,
        ]);
    }

    $staffCredentials = [
        'waiter.cg@cafeconnect.test' => ['code' => 'WAIT001', 'password' => 'waiter123', 'pin' => '1111'],
        'cashier.cg@cafeconnect.test' => ['code' => 'CASH001', 'password' => 'cashier123', 'pin' => '2222'],
        'barista.cg@cafeconnect.test' => ['code' => 'BAR001', 'password' => 'barista123', 'pin' => '3333'],
        'owner@cafeconnect.test' => ['code' => 'OWNER001', 'password' => 'owner123', 'pin' => '4444'],
        'marketing@cafeconnect.test' => ['code' => 'MKT001', 'password' => 'marketing123', 'pin' => '5555'],
        'admin@cafeconnect.test' => ['code' => 'ADMIN001', 'password' => 'admin123', 'pin' => '6666'],
        'manager.hk@cafeconnect.test' => ['code' => 'MGR001', 'password' => 'manager123', 'pin' => '7777'],
        'cashier.th@cafeconnect.test' => ['code' => 'CASH002', 'password' => 'cashier123', 'pin' => '2222'],
    ];
    $staffStmt = $pdo->prepare(
        "UPDATE staff
         SET staff_code = :staff_code,
             password_hash = :password_hash,
             pin_hash = :pin_hash
         WHERE email = :email"
    );
    foreach ($staffCredentials as $email => $credential) {
        $staffStmt->execute([
            'staff_code' => $credential['code'],
            'password_hash' => password_hash($credential['password'], PASSWORD_DEFAULT),
            'pin_hash' => password_hash($credential['pin'], PASSWORD_DEFAULT),
            'email' => $email,
        ]);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $pdo = Database::pdo(false);
        $schemaPath = __DIR__ . '/database/cafe_connect_schema.sql';

        foreach (split_sql_statements(install_sql_body($schemaPath)) as $statement) {
            $pdo->exec($statement);
        }
        seed_demo_credentials();

        $tableCount = installed_table_count();
        $status = 'Database cafe_connect_crm has been reset with the MVC POS and website sample data.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

try {
    $tableCount = installed_table_count();
} catch (Throwable $exception) {
    if ($error === null) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cafe Connect Installer</title>
    <link rel="stylesheet" href="assets/css/app.css">
  </head>
  <body class="install-page">
    <main class="install-card">
      <p class="eyebrow">Cafe Connect MVC</p>
      <h1>Cài đặt database XAMPP</h1>
      <p>
        Trang này import <strong>database/cafe_connect_schema.sql</strong> vào MySQL
        bằng cấu hình mặc định <strong>127.0.0.1 / root / mật khẩu rỗng</strong>.
        Thao tác import sẽ reset sample data cho Website + POS roles.
      </p>

      <?php if ($status): ?>
        <div class="notice success"><?= e($status) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="notice danger"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="install-status">
        <span>Database</span>
        <strong><?= $tableCount > 0 ? 'Đã sẵn sàng' : 'Chưa cài đặt' ?></strong>
        <small><?= $tableCount ?> bảng được phát hiện</small>
      </div>

      <form method="post">
        <button type="submit" class="primary-btn">Import / Reset sample data</button>
        <a class="secondary-link" href="index.php">Mở website</a>
        <a class="secondary-link" href="pos.php">Mở POS</a>
      </form>
    </main>
  </body>
</html>
