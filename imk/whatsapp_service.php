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

    // 2. REAL WA (Opsional): Gunakan API Gateway Pihak Ketiga
    // Karena PHP berjalan di localhost, dia TIDAK BISA langsung mengirim WA tanpa perantara.
    // Solusi: Gunakan layanan seperti Fonnte (Gratis/Freemium Indonesia).
    
    // CARA MENGAKTIFKAN FITUR KIRIM WA NYATA (BUKAN SIMULASI):
    // 1. Daftar di https://fonnte.com/
    // 2. Tautkan WA Anda scan QR
    // 3. Ambil Token API
    // 4. Masukkan token di bawah ini & Uncomment kodenya
    
    /*
    $token = "ISI_TOKEN_FONNTE_DISINI"; // <--- GANTI INI
    
    $target = $userPhone; // Pastikan format 08xx atau 62xx
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.fonnte.com/send',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => array(
        'target' => $target,
        'message' => $message,
      ),
      CURLOPT_HTTPHEADER => array(
        "Authorization: $token"
      ),
    ));

    $response = curl_exec($curl);
    if (curl_errno($curl)) {
        // error handling log
        // error_log(curl_error($curl));
    }
    curl_close($curl);
    */
}
?>
