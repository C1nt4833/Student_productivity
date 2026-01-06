<?php
session_start();
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['name'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Email tidak ditemukan!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Tugas Kuliah</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f6f8fd 0%, #eef2f9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            max-width: 900px;
            width: 100%;
            min-height: 550px;
        }
        .auth-left {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .auth-right {
            flex: 1;
            background: var(--primary-color);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .auth-right::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            top: -50px;
            right: -50px;
        }

        .auth-title {
            font-size: 2rem;
            margin-bottom: 5px;
            color: var(--text-main);
        }
        .auth-subtitle {
            color: var(--text-muted);
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #eee;
            border-radius: 10px;
            font-family: inherit;
            font-size: 1rem;
            transition: 0.3s;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        .btn-auth {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-auth:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }
        .error-msg {
            background: #fee2e2;
            color: #ef4444;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .illustration-img {
            max-width: 80%;
            margin-bottom: 20px;
        }
        .switch-auth {
            margin-top: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        .switch-auth a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .auth-container {
                flex-direction: column;
                min-height: auto;
            }
            .auth-right {
                display: none;
            }
            .auth-left {
                padding: 30px;
            }
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-left">
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Masuk untuk mengelola tugas & deadline.</p>

            <?php if ($error): ?>
                <div class="error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label style="font-weight:600; font-size:0.9rem; margin-bottom:5px; display:block;">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="nama@email.com" required>
                </div>
                <div class="form-group">
                    <label style="font-weight:600; font-size:0.9rem; margin-bottom:5px; display:block;">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-auth">Masuk</button>
            </form>

            <div class="switch-auth">
                Belum punya akun? <a href="register.php">Daftar sekarang</a>
            </div>
        </div>
        <div class="auth-right">
            <i class="fa-solid fa-graduation-cap" style="font-size: 4rem; margin-bottom:20px;"></i>
            <h2>Student Productivity</h2>
            <p>"Cara terbaik untuk memprediksi masa depan adalah dengan menciptakannya."</p>
        </div>
    </div>

</body>
</html>
