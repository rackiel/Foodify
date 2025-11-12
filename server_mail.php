<?php
use PHPMailer\PHPMailer\PHPMailer;
//Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; // Set your SMTP server
                $mail->SMTPAuth   = true;
                $mail->Username   = 'docvic.santiago@gmail.com'; // Your SMTP username
                $mail->Password   = 'zyzphvfzxadjmems'; // Your SMTP password or app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                //Recipients
                $mail->setFrom('docvic.santiago@gmail.com', 'Foodify');
?>