<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Foodify Team Officer Panel</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="../uploads/images/foodify_icon.png" rel="icon">
  <link href="../uploads/images/foodify_icon.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="../bootstrap/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../bootstrap/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../bootstrap/assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../bootstrap/assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="../bootstrap/assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="../bootstrap/assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="../bootstrap/assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="../bootstrap/assets/css/style.css" rel="stylesheet">
  <?php

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
?>
</head>
