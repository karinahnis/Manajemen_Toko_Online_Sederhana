<?php
// Pastikan semua error ditampilkan saat development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load Composer's autoloader jika menggunakan Composer
// Pastikan path ini benar sesuai struktur proyek Anda.
// Jika folder 'vendor' berada di root proyek (misal: bissmillah/vendor/), maka ../vendor/autoload.php sudah benar.
require '../vendor/autoload.php';

// Impor kelas PHPMailer ke namespace global
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Konfigurasi koneksi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "basic_online_store";

$conn = null;

// Buat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi di awal
if ($conn->connect_error) {
    // Untuk debugging, kita bisa tampilkan error langsung.
    // Di produksi, sebaiknya log error dan tampilkan pesan umum.
    die("Koneksi database gagal: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        header("Location: ../forgot-password.html?error=" . urlencode("Email harus diisi."));
        $conn->close();
        exit();
    }

    // 1. Cek apakah email terdaftar di database
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        header("Location: ../forgot-password.html?error=" . urlencode("Terjadi kesalahan sistem (prepare check email): " . $conn->error));
        $conn->close();
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        // Untuk keamanan, jangan beritahu apakah email terdaftar atau tidak.
        // Cukup beritahu pengguna bahwa jika email terdaftar, link akan dikirim.
        header("Location: ../forgot-password.html?success=" . urlencode("Jika email Anda terdaftar, tautan reset password telah dikirim ke email Anda."));
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->bind_result($user_id);
    $stmt->fetch();
    $stmt->close();

    // 2. Buat token reset password yang unik dan berumur pendek
    $token = bin2hex(random_bytes(32)); // Token acak 64 karakter
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token berlaku 1 jam

    // 3. Simpan token ke database (kolom reset_token dan reset_token_expires_at harus sudah ada)
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
    if (!$stmt) {
        header("Location: ../forgot-password.html?error=" . urlencode("Terjadi kesalahan sistem (prepare update token): " . $conn->error));
        $conn->close();
        exit();
    }
    $stmt->bind_param("ssi", $token, $expires_at, $user_id);

    if (!$stmt->execute()) {
        header("Location: ../forgot-password.html?error=" . urlencode("Gagal menyimpan token reset password: " . $stmt->error));
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->close();

    // 4. Kirim email reset password
    $mail = new PHPMailer(true); // Meneruskan `true` mengaktifkan pengecualian

    try {
        // Konfigurasi Server SMTP
        $mail->isSMTP();
        // --- GANTI DENGAN KREDENSIAL SMTP ANDA ---
        $mail->Host       = 'smtp.gmail.com'; // Contoh untuk Gmail. Ganti dengan host SMTP Anda.
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email@gmail.com'; // Ganti dengan email Anda (misal: namakamu@gmail.com)
        $mail->Password   = 'your_email_app_password';   // Ganti dengan password aplikasi email Anda (BUKAN password akun login Gmail)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Gunakan ENCRYPTION_SMTPS untuk SSL/TLS (port 465)
        $mail->Port       = 465; // Port untuk SMTPS. Jika pakai STARTTLS, pakai port 587.

        // Jika Anda menggunakan STARTTLS (port 587), ganti:
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // $mail->Port       = 587;

        // Penerima
        $mail->setFrom('no-reply@yourdomain.com', 'Nama Aplikasi Anda'); // Ganti dengan email pengirim Anda
        $mail->addAddress($email); // Email penerima

        // Konten Email
        $mail->isHTML(true); // Atur format email ke HTML
        $mail->Subject = 'Reset Password Anda untuk [Nama Aplikasi Anda]';
        // Sesuaikan PATH_TO_YOUR_PROJECT dengan path folder utama proyek Anda di localhost
        // Contoh: http://localhost/bissmillah/reset-password.html?token=
        $reset_link = "http://localhost/bissmillah/reset-password.html?token=" . $token;

        $mail->Body    = "Halo,<br><br>"
                       . "Anda menerima email ini karena ada permintaan reset password untuk akun Anda di [Nama Aplikasi Anda].<br>"
                       . "Untuk mereset password Anda, silakan klik tautan di bawah ini:<br><br>"
                       . "<a href='$reset_link'>$reset_link</a><br><br>"
                       . "Tautan ini akan kedaluwarsa dalam 1 jam.<br>"
                       . "Jika Anda tidak meminta ini, silakan abaikan email ini.<br><br>"
                       . "Terima kasih,<br>"
                       . "Tim [Nama Aplikasi Anda]";
        $mail->AltBody = "Halo,\n\n"
                       . "Anda menerima email ini karena ada permintaan reset password untuk akun Anda di [Nama Aplikasi Anda].\n"
                       . "Untuk mereset password Anda, silakan salin dan tempel tautan ini ke browser Anda:\n"
                       . "$reset_link\n\n"
                       . "Tautan ini akan kedaluwarsa dalam 1 jam.\n"
                       . "Jika Anda tidak meminta ini, silakan abaikan email ini.\n\n"
                       . "Terima kasih,\n"
                       . "Tim [Nama Aplikasi Anda]";

        $mail->send();
        header("Location: ../forgot-password.html?success=" . urlencode("Jika email Anda terdaftar, tautan reset password telah dikirim ke email Anda."));
        exit();

    } catch (Exception $e) {
        // --- INI ADALAH BAGIAN UTAMA UNTUK DEBUGGING ---
        // Tampilkan error PHPMailer langsung di browser untuk debugging.
        // HANYA AKTIFKAN INI SAAT DEBUGGING!
        echo "Gagal mengirim email reset password. Detail error: " . $mail->ErrorInfo;
        // Anda juga bisa mencetak stack trace untuk detail lebih lanjut:
        // echo "<pre>"; print_r($e); echo "</pre>";
        exit(); // Hentikan eksekusi setelah menampilkan error

        // --- SAAT PRODUKSI, KEMBALIKAN KE INI ---
        // header("Location: ../forgot-password.html?error=" . urlencode("Gagal mengirim email reset password. Silakan coba lagi nanti."));
        // exit();
    }

} else {
    // Jika diakses langsung tanpa POST request, alihkan kembali
    header("Location: ../forgot-password.html");
    exit();
}


?>