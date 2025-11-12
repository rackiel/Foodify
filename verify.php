<?php
include 'config/db.php';
$token = $_GET['token'] ?? '';
$verified = false;
if ($token) {
    $stmt = $conn->prepare("UPDATE user_accounts SET is_verified=1 WHERE verification_token=? AND is_verified=0");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $verified = true;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification - Foodify</title>
    <link href="bootstrap/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="bootstrap/assets/css/style.css" rel="stylesheet">
</head>
<body style="background: #f9f9f9;">
    <div class="container d-flex flex-column justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="card shadow p-4" style="max-width: 420px; width: 100%; border-radius: 18px;">
            <div class="text-center mb-3">
                <img src="uploads/images/foodify_logo.png" alt="Foodify Logo" style="height: 50px;">
            </div>
            <?php if ($verified): ?>
                <h3 class="text-success text-center mb-3">Account Verified!</h3>
                <p class="text-center">Your email has been successfully verified. Please wait for admin approval before you can log in.</p>
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-success">Go to Login</a>
                </div>
            <?php else: ?>
                <h3 class="text-danger text-center mb-3">Verification Failed</h3>
                <p class="text-center">Invalid or expired verification link. Please check your email or contact support.</p>
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
