<?php
$files = [
    'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php',
    'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
    'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php'
];

foreach ($files as $url) {
    $filename = basename($url);
    $content = file_get_contents($url);
    if ($content !== false) {
        file_put_contents($filename, $content);
        echo "Downloaded: $filename\n";
    } else {
        echo "Failed to download: $filename\n";
    }
}
echo "PHPMailer files downloaded successfully!";
?> 