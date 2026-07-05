<?php
session_start();
require_once "config/database.php";
require_once "config/constants.php";

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit();
}

// Handle login
if ($_POST && isset($_POST['login'])) {
    $database = new Database();
    $db = $database->getConnection();

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    
    // Get current fiscal year
    $fiscal_year = $db->query("SELECT * FROM ff_sch.fiscal_years WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $current_fiscal_year_id = $fiscal_year ? $fiscal_year['id'] : 1;

    $stmt = $db->prepare("SELECT * FROM ff_sch.users WHERE username = :username AND is_active = true");
    $stmt->bindParam(':username', $username);
    $stmt->execute();

    if ($stmt->rowCount() === 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['fiscal_yr'] = $current_fiscal_year_id;

            header("Location: " . BASE_URL . "dashboard.php");
            exit();
        }
    }
    $error = "Invalid username or password!";
}
?>
<!-- <!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

</head>
<body>

<div class="login-wrapper">
    <div class="brand-icon">
        <i class="fas fa-store"></i>
    </div>
    <h3><?php echo COMPANY_NAME; ?></h3>
    <p>Welcome back! Please sign in</p>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger text-center py-2"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label text-white-50">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label text-white-50">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>
        </div>

        <button type="submit" name="login" class="btn btn-primary w-100">
            <i class="fas fa-sign-in-alt me-2"></i> Login
        </button>
    </form>
</div>

<footer>© <?php echo date('Y'); ?> <?php echo COMPANY_NAME; ?> — All Rights Reserved.</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> -->

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Billing System</title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
<link href="<?php echo ASSETS_URL; ?>/css/css2.css" rel="stylesheet">
<link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
<style>
    body {
        height: 100vh;
        background: radial-gradient(circle at top left, #6a11cb 0%, #2575fc 100%);
        display: flex;
        justify-content: center;
        align-items: center;
        font-family: "Poppins", sans-serif;
        overflow: hidden;
    }

    .login-wrapper {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(12px);
        border-radius: 20px;
        padding: 40px 35px;
        width: 380px;
        color: #fff;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        animation: fadeIn 1.2s ease;
    }

    .login-wrapper h3 {
        font-weight: 600;
        text-align: center;
        margin-bottom: 10px;
    }

    .login-wrapper p {
        text-align: center;
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.8);
        margin-bottom: 30px;
    }

    .form-control {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: #fff;
        border-radius: 12px;
        padding: 12px 15px;
    }
    .form-control:focus {
        background: none;
        border: 1px solid #fff;
        color: #fff;
    }

    .form-control::placeholder {
        color: rgba(255, 255, 255, 0.7);
    }

    .input-group{
        border: 1px solid #657479;
        border-radius: 15px;
    }

    .input-group-text {
        background: transparent;
        border: none;
        color: #fff;
    }

    .btn-primary {
        background: linear-gradient(90deg, #6a11cb, #2575fc);
        border: none;
        border-radius: 12px;
        padding: 12px;
        font-weight: 500;
        transition: 0.3s;
    }

    .btn-primary:hover {
        background: linear-gradient(90deg, #2575fc, #6a11cb);
        transform: translateY(-2px);
    }

    .alert {
        border-radius: 10px;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .brand-icon {
        text-align: center;
        font-size: 3rem;
        color: #fff;
        margin-bottom: 15px;
    }

    footer {
        position: absolute;
        bottom: 10px;
        width: 100%;
        text-align: center;
        color: rgba(255,255,255,0.8);
        font-size: 0.85rem;
    }
</style>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body, html {
    height: 100%;
    overflow: hidden;
}

#particles-js {
    position: fixed;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at top left, #001f3f, #0f2027, #203a43, #2c5364);
    background-size: cover;
    z-index: 0;
}

.footer-text {
    margin-top: 15px;
    color: rgba(255,255,255,0.7);
    font-size: 13px;
}
</style>
</head>
<body>

<div id="particles-js"></div>

<div class="login-wrapper">
    <div class="brand-icon">
        <i class="fas fa-store"></i>
    </div>
    <h3><?php echo COMPANY_NAME; ?></h3>
    <!-- <p>Welcome back! Please sign in</p> -->

    <?php if(isset($error)): ?>
        <div class="alert alert-danger text-center py-2"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label text-white-50">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" name="username" class="form-control" placeholder="Enter your username" required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label text-white-50">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>
        </div>

        <button type="submit" name="login" class="btn btn-primary w-100">
            <i class="fas fa-sign-in-alt me-2"></i> Login
        </button>
    </form>
</div>

<!-- Particle JS CDN -->
<script src="<?php echo ASSETS_URL; ?>/js/particles.min.js"></script>

<script>
// ParticleJS config
particlesJS("particles-js", {
  "particles": {
    "number": { "value": 80 },
    "color": { "value": "#ffffff" },
    "shape": { "type": "circle" },
    "opacity": { "value": 0.5 },
    "size": { "value": 3 },
    "line_linked": {
      "enable": true,
      "distance": 150,
      "color": "#00c6ff",
      "opacity": 0.4,
      "width": 1
    },
    "move": {
      "enable": true,
      "speed": 3
    }
  },
  "interactivity": {
    "detect_on": "canvas",
    "events": { "onhover": { "enable": true, "mode": "repulse" } },
    "modes": { "repulse": { "distance": 100 } }
  },
  "retina_detect": true
});
</script>

</body>
</html>