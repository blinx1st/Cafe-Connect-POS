<?php

declare(strict_types=1);

// Copy this file to config/mail.local.php and fill your Gmail SMTP values.
// Use a Gmail App Password, not your normal Gmail password.
return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'your-gmail@gmail.com',
    'password' => 'your-16-character-app-password',
    'from_email' => 'your-gmail@gmail.com',
    'from_name' => 'Cafe Connect',
    'timeout' => 15,
];
