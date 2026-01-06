<?php
// reminder_bot.php
// Script Bot Pengingat (Multi-Milestone)
// Triggered by: Cron Job (Server) or Auto-Run (Client JS)

require 'config.php';
require 'whatsapp_service.php';

header('Content-Type: application/json');

$response = [];
$triggered = false;

// Ambil tugas yang belum selesai
$sql = "SELECT t.*, u.phone, u.name as user_name 
        FROM tasks t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.completed = 0";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $deadline = new DateTime($row['deadline']);
        $now = new DateTime();
        
        $interval = $now->diff($deadline);
        $minutesDiff = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        $hours = $minutesDiff / 60;
        
        // Skip jika deadline sudah lewat (minutesDiff < 0)
        if ($deadline < $now) { continue; }

        // Helper function untuk format waktu sisa
        $formatTime = function($totalMinutes) {
            $h = floor($totalMinutes / 60);
            $m = $totalMinutes % 60;
            if ($h > 0) return "$h Jam $m Menit";
            return "$m Menit";
        };

        // Helper function untuk kirim & update dengan Range Validasi
        $checkAndSend = function($colName, $minHours, $maxHours, $msgTitle, $customMsg) use ($conn, $row, &$response, &$triggered, $hours, $minutesDiff, $formatTime) {
            
            // Logika baru: Strict Window Check
            // Hanya kirim notif 24 jam jika waktu benar-benar di ANTARA 23-25 jam.
            // Jika sisa 1 jam, notif 24 jam TIDAK akan terkirim.
            
            if ($hours >= $minHours && $hours <= $maxHours && $row[$colName] == 0) {
                $sisaWaktu = $formatTime($minutesDiff);
                
                $msg = "🤖 *Reminder Bot* 🤖\n\nMata Kuliah: *{$row['course']}*\nJudul Tugas: *{$row['name']}*\n\n{$customMsg}\n\nDeadline: {$row['deadline']}\nSisa Waktu: {$sisaWaktu}";
                
                sendWhatsAppMessage($conn, $row['user_id'], $row['phone'], $msg);
                
                $conn->query("UPDATE tasks SET $colName = 1 WHERE id = " . $row['id']);
                $response[] = "Notif $msgTitle dikirim ke {$row['user_name']}";
                $triggered = true;
                return true; 
            }
            return false;
        };

        // 1. Reminder 1 Jam (Window: 0 - 1.2 Jam)
        if($checkAndSend('notif_1h', 0, 1.2, "1 Jam", "⚠️ *URGENT*: Deadline tinggal kurang dari 1 JAM lagi! Segera submit!")) continue;

        // 2. Reminder 2 Jam (Window: 1.2 - 2.5 Jam)
        if($checkAndSend('notif_2h', 1.2, 2.5, "2 Jam", "⚠️ Waktu tinggal sekitar 2 Jam lagi. Semangat fokusnya!")) continue;

        // 3. Reminder 3 Jam (Window: 2.5 - 3.5 Jam)
        if($checkAndSend('notif_3h', 2.5, 3.5, "3 Jam", "⏰ Tinggal 3 Jam lagi nih. Jangan lupa cek kelengkapan tugas.")) continue;

        // 4. Reminder 6 Jam (Window: 5.5 - 6.5 Jam)
        if($checkAndSend('notif_6h', 5.5, 6.5, "6 Jam", "📢 Reminder: Masih ada 6 Jam. Cukup untuk finishing!")) continue;

        // 5. Reminder 1 Hari (Window: 23 - 25 Jam)
        if($checkAndSend('notif_1d', 23, 25, "1 Hari", "📅 Besok deadline lho! Persiapkan dari sekarang biar tenang.")) continue;

        // Cleanup: Jika tugas dibuat dadakan (misal deadline 1 jam lagi),
        // otomatis tandai notifikasi 1 hari, 6 jam, dll sebagai "sudah berlalu" agar tidak menumpuk di DB atau trigger aneh2 nanti.
        if ($hours < 23 && $row['notif_1d'] == 0) $conn->query("UPDATE tasks SET notif_1d = 2 WHERE id = " . $row['id']);
        if ($hours < 5.5 && $row['notif_6h'] == 0) $conn->query("UPDATE tasks SET notif_6h = 2 WHERE id = " . $row['id']);
        if ($hours < 2.5 && $row['notif_3h'] == 0) $conn->query("UPDATE tasks SET notif_3h = 2 WHERE id = " . $row['id']);
        
    }
}

if (!$triggered) {
    //$response[] = "Bot idle. Tidak ada milestone reminder yang cocok saat ini.";
}

echo json_encode(["status" => "success", "logs" => $response]);
?>
