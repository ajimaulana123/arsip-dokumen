<?php
session_start();
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Arsip Dokumen</title>
    <style>
        /* Reset default browser styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Set body style */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #E6F2FF, #D1E9FF); /* Soft gradient background */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #333;
        }

        /* Animasi fade-in untuk login-container */
        @keyframes fadeIn {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Login container style */
        .login-container {
            background-color: #ffffff;
            width: 100%;
            max-width: 450px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            box-sizing: border-box;
            animation: fadeIn 0.8s ease-out;
        }

        /* Hover effect on the container */
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        /* Header style */
        .login-container h2 {
            color: #4A90E2;
            margin-bottom: 30px;
            font-size: 30px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Image style */
        .login-container img {
            max-width: 100px;
            margin-bottom: 20px;
            transition: transform 0.3s ease-in-out;
        }

        .login-container img:hover {
            transform: scale(1.1);
        }

        /* Input field style */
        .input-field {
            width: 100%;
            padding: 15px;
            margin: 12px 0;
            border: 1px solid #D1D8E1;
            border-radius: 10px;
            font-size: 16px;
            background-color: #f9f9f9;
            color: #333;
            transition: all 0.3s ease;
        }

        /* Focus effect on input */
        .input-field:focus {
            border-color: #4A90E2;
            background-color: #eef5ff;
            outline: none;
            box-shadow: 0 0 8px rgba(74, 144, 226, 0.2);
        }

        /* Button style */
        .login-button {
            width: 100%;
            padding: 15px;
            background-color: #4A90E2;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .login-button:hover {
            background-color: #357ABD;
            transform: translateY(-3px);
        }

        /* Footer text style */
        .footer-text {
            margin-top: 20px;
            font-size: 10px; 
            color: #888; 
        }
        
        .footer-text a { 
            color: #4A90E2; 
            text-decoration: none; 
        }

        /* Responsive design for smaller screens */
        @media (max-width:500px) { 
            .login-container { 
                padding: 25px; 
                width: 90%; 
            }
            .login-container h2 { 
                font-size: 24px; 
            }
            .login-button { 
                padding: 14px; 
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>SISTEM ARSIP DOKUMEN</h2>
        <form method="POST" action="proses_login.php">
            <input type="text" name="username" class="input-field" placeholder="Username" required><br>
            <input type="password" name="password" class="input-field" placeholder="Password" required><br>
            <button type="submit" class="login-button">Login</button>
        </form>
        <?php if (isset($error_message)) { ?>
            <div style="color: red; margin-top: 10px;">
                <?php echo $error_message; ?>
            </div>
        <?php } ?>
        <div class="footer-text">
            <p>©2025 <a href="#">PT Piranti Teknik Indonesia</a>. Semua Hak Dilindungi.</p>
            <p><a href="forgot-password.html">Lupa Password?</a></p>
        </div>
    </div>
</body>
</html>
