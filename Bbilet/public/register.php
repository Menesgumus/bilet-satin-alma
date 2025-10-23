<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Util.php';

session_name((require __DIR__ . '/../config/config.php')['session_name']);
session_start();

$pdo = Database::getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullname === '' || $email === '' || $password === '') {
        $error = 'Tüm alanlar zorunludur.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (id, fullname, email, password, role, balance) VALUES (:id, :fullname, :email, :password, :role, :balance)');
            $stmt->execute([
                ':id' => Util::generateUuidV4(),
                ':fullname' => $fullname,
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => 'user',
                ':balance' => 0,
            ]);
            header('Location: /login.php');
            exit;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                $error = 'Bu e-posta ile kayıt mevcut.';
            } else {
                $error = 'Kayıt başarısız.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        .logo { 
            font-size: 3rem; 
            margin-bottom: 1rem; 
            color: #10b981;
        }
        h2 { 
            margin-bottom: 2rem; 
            color: #333;
            font-size: 1.8rem;
        }
        form { 
            display: grid; 
            gap: 1.5rem; 
            text-align: left;
        }
        input {
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        .btn { 
            padding: 1rem 2rem; 
            border: none; 
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff; 
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        .error { 
            color: #dc2626;
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .link {
            margin-top: 2rem;
            color: #6b7280;
        }
        .link a {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
        }
        .link a:hover {
            text-decoration: underline;
        }
    </style>
    </head>
<body>
    <div class="register-container">
        <div class="logo">🚌</div>
        <h2>Kayıt Ol</h2>
        <?php if ($error): ?><p class="error"><?=$error?></p><?php endif; ?>
        <form method="post">
            <input name="fullname" placeholder="Ad Soyadınız" required />
            <input type="email" name="email" placeholder="E-posta adresiniz" required />
            <input type="password" name="password" placeholder="Şifreniz" required />
            <button class="btn" type="submit">✨ Kayıt Ol</button>
        </form>
        <div class="link">
            <p>Zaten hesabınız var mı? <a href="/login.php">Giriş yapın</a></p>
        </div>
    </div>
</body>
</html>


