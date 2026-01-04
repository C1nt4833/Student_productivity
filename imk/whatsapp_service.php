<?php
// wordpress_service.php -> whatsapp_service.php
// Menangani logika pengiriman pesan

function sendWhatsAppMessage($conn, $userId, $userPhone, $message) {
    if (!$userId) return;

    // 1. SIMULASI: Masukkan pesan ke Database Chatbot Widget agar muncul di layar user (Seolah-olah Bot Chat)
    // Tipe sender = 'bot'
    $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'bot', ?)");
    $stmt->bind_param("is", $userId, $message);
    $stmt->execute();
}
?>
