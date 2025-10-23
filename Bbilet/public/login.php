<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';

session_name((require __DIR__ . '/../config/config.php')['session_name']);
session_start();

$pdo = Database::getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'E-posta ve şifre zorunludur.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            header('Location: /index.php');
            exit;
        } else {
            $error = 'Geçersiz kimlik bilgileri.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .logo { 
            font-size: 3rem; 
            margin-bottom: 1rem; 
            color: #667eea;
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
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn { 
            padding: 1rem 2rem; 
            border: none; 
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff; 
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
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
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .link a:hover {
            text-decoration: underline;
        }
    </style>
    </head>
<body>
    <div class="login-container">
        <div class="logo">🚌</div>
        <h2>Giriş Yap</h2>
        <?php if ($error): ?><p class="error"><?=$error?></p><?php endif; ?>
        <form method="post">
            <input type="email" name="email" placeholder="E-posta adresiniz" required />
            <input type="password" name="password" placeholder="Şifreniz" required />
            <button class="btn" type="submit">🔑 Giriş Yap</button>
        </form>
        <div class="link">
            <p>Hesabınız yok mu? <a href="/register.php">Kayıt olun</a></p>
        </div>
    </div>
</body>
</html>


