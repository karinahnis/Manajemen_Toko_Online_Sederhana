  <?php
$admin_password = "admin123"; // GANTI DENGAN PASSWORD YANG ANDA INGINKAN UNTUK ADMIN!
$hashed_admin_password = password_hash($admin_password, PASSWORD_DEFAULT);
echo "Password yang di-hash: " . $hashed_admin_password;
?>