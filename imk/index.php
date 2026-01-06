<?php
session_start();
require 'config.php';

// Cek Simple Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch user phone details logic...
$stmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$userData = $res->fetch_assoc();
$userPhone = $userData['phone'] ?? '';

// Handle API requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // --- New Chatbot API ---
    if ($_GET['action'] == 'get_chat_history') {
        $stmt = $conn->prepare("SELECT * FROM chat_logs WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $chats = [];
        while($row = $res->fetch_assoc()) {
            $chats[] = $row;
        }
        echo json_encode($chats);
        exit;
    }
    
    if ($_GET['action'] == 'send_chat') {
        $input = json_decode(file_get_contents('php://input'), true);
        $msg = $input['message'];
        
        // Simpan pesan user
        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'user', ?)");
        $stmt->bind_param("is", $user_id, $msg);
        $stmt->execute();
        
        // SMART BOT RESPONSES
        $msgLower = strtolower($msg);
        $user_name = $_SESSION['user_name']; // Pastikan session start di atas
        
        $botReply = "Maaf, saya tidak mengerti. Ketik 'help' untuk lihat bantuan. 🤖";

        if (strpos($msgLower, 'halo') !== false || strpos($msgLower, 'hi') !== false) {
            $botReply = "Halo $user_name! 👋 Ada yang bisa saya bantu hari ini? Jangan lupa cek deadline tugasmu ya!";
        } 
        elseif (strpos($msgLower, 'tugas') !== false || strpos($msgLower, 'deadline') !== false) {
            $botReply = "Untuk melihat daftar tugas lengkap, silakan cek Dashboard di samping kiri. Saya akan otomatis ingatkan kamu kalau ada deadline mepet! ⏰";
        }
        elseif (strpos($msgLower, 'jam') !== false) {
            $botReply = "Sekarang jam " . date('H:i') . ". Waktu terus berjalan, ayo produktif! ⏳";
        }
        elseif (strpos($msgLower, 'siapa') !== false) {
            $botReply = "Saya adalah Asisten Virtual Tugas IMK. Tugas saya bikin kamu ga lupa ngerjain tugas! 😎";
        }
        elseif (strpos($msgLower, 'makasih') !== false || strpos($msgLower, 'thanks') !== false) {
            $botReply = "Sama-sama! Semangat terus belajarnya! 🔥";
        }
        elseif (strpos($msgLower, 'help') !== false || strpos($msgLower, 'bantuan') !== false) {
            $botReply = "🤖 **Menu Bantuan** 🤖\n\nCoba ketik kata kunci ini:\n- *Halo*: Sapa bot\n- *Tugas*: Info tugas\n- *Jam*: Cek waktu server\n- *Set HP 08xx*: Simpan nomor WA\n- *Siapa*: Info bot";
        }
        elseif (preg_match('/^(set hp|ganti wa|nomor wa|my wa)\s*(\d+)/', $msgLower, $matches)) {
            $newPhone = $matches[2];
            // Format 08 -> 628
            if (substr($newPhone, 0, 1) == '0') $newPhone = '62' . substr($newPhone, 1);
            
            $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
            $stmt->bind_param("si", $newPhone, $user_id);
            $stmt->execute();
            
            // Update session if needed or just reply
            $_SESSION['wa_number'] = $newPhone; // Optional
            
            $botReply = "✅ Nomor WhatsApp berhasil disimpan: *$newPhone*\nBot sekarang bisa mengirim reminder ke WA kamu!";
        }
        else {
             // Default greeting for unknown
             $botReply = "Halo! Saya Asisten Bot. Ketik *help* untuk melihat apa yang bisa saya lakukan. 🤖";
        }

        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'bot', ?)");
        $stmt->bind_param("is", $user_id, $botReply);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }

    // --- Existing Task API ---
    if ($_GET['action'] == 'update_phone') {
        $input = json_decode(file_get_contents('php://input'), true);
        $phone = $input['phone'];
        if (substr($phone, 0, 1) == '0') $phone = '62' . substr($phone, 1);
        $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
        $stmt->bind_param("si", $phone, $user_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] == 'get_tasks') {
        $result = $conn->query("SELECT * FROM tasks WHERE user_id = $user_id ORDER BY completed ASC, deadline ASC");
        $tasks = [];
        while($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        echo json_encode($tasks);
        exit;
    }
    
    if ($_GET['action'] == 'add_task') {
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $conn->prepare("INSERT INTO tasks (user_id, name, course, deadline) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $input['name'], $input['course'], $input['deadline']);
        $stmt->execute();
        echo json_encode(['id' => $conn->insert_id]);
        exit;
    }

    if ($_GET['action'] == 'toggle_task') {
        $id = $_GET['id'];
        $conn->query("UPDATE tasks SET completed = NOT completed WHERE id = $id AND user_id = $user_id");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] == 'delete_task') {
        $id = $_GET['id'];
        $conn->query("DELETE FROM tasks WHERE id = $id AND user_id = $user_id");
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_GET['action'] == 'update_task') {
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $conn->prepare("UPDATE tasks SET name=?, course=?, deadline=? WHERE id=? AND user_id=?");
        $stmt->bind_param("sssii", $input['name'], $input['course'], $input['deadline'], $input['id'], $user_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Tugas IMK</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- Integration Settings Modal -->
    <div id="waModal" class="modal hidden">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2><i class="fa-brands fa-whatsapp"></i> Setup Reminder</h2>
            <p>Masukkan nomor WhatsApp untuk menerima notifikasi otomatis dari Bot.</p>
            <div class="form-group">
                <label>Nomor WhatsApp</label>
                <input type="text" id="waNumberInput" placeholder="08..." value="<?php echo htmlspecialchars($userPhone); ?>">
            </div>
            <button class="btn-primary" onclick="saveWaNumber()">Simpan</button>
        </div>
    </div>

    <!-- Chatbot Widget -->
    <div id="chatbot-widget" class="chatbot-container hidden-chat">
        <div class="chat-header">
            <div class="chat-title">
                <i class="fa-solid fa-robot"></i> Assistant Bot
            </div>
            <button class="close-chat" onclick="toggleChat()"><i class="fa-solid fa-times"></i></button>
        </div>
        <div class="chat-body" id="chatBody">
            <!-- Messages injected here -->
        </div>
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="Ketik pesan..." onkeypress="handleChatKey(event)">
            <button onclick="sendChatMessage()"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
    </div>
    
    <!-- Chat Trigger Button -->
    <button class="chat-trigger" onclick="toggleChat()">
        <i class="fa-solid fa-message"></i>
    </button>

    <div class="app-container">
        
        <!-- Header -->
        <header>
            <div class="logo-area">
                <div class="logo-icon">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div class="logo-text">
                    <h1>Hi, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
                    <p>Fokus selesaikan tugasmu hari ini.</p>
                </div>
            </div>
            
            <a href="logout.php" class="btn-logout" style="color:white; text-decoration:none; background:rgba(255,255,255,0.2); padding:8px 15px; border-radius:10px; font-size:0.9rem;">
                <i class="fa-solid fa-sign-out-alt"></i> Keluar
            </a>
        </header>
        
        <!-- Integration Bar -->
        <div class="integration-bar">
            <button class="btn-int" onclick="alert('Fitur Sync Google Calendar aktif via link di tiap tugas.')">
                <i class="fa-brands fa-google"></i> Sync Calendar <span class="badge-on">ON</span>
            </button>
            <button class="btn-int" onclick="openWaModal()" style="margin-left:10px;">
                <i class="fa-brands fa-whatsapp"></i> Ganti Nomor WA
            </button>
            
        </div>

        <!-- Main Content (Sidebar + Tasks) -->
        <div class="content-wrapper">
            <aside class="sidebar">
                <section class="input-section">
                    <h2><i class="fa-solid fa-plus-circle"></i> Tugas Baru</h2>
                    <form id="taskForm">
                        <input type="hidden" id="taskId">
                        <div class="form-group">
                            <label for="taskName">Nama Tugas</label>
                            <input type="text" id="taskName" placeholder="Contoh: Makalah HCI" required>
                        </div>
                        <div class="form-group">
                            <label for="courseName">Mata Kuliah</label>
                            <input type="text" id="courseName" placeholder="Contoh: Interaksi Manusia Komputer" required>
                        </div>
                        <div class="form-group">
                            <label for="deadline">Deadline</label>
                            <input type="datetime-local" id="deadline" required>
                        </div>
                        <button type="submit" class="btn-primary" id="submitBtn">Simpan Tugas</button>
                        <button type="button" id="cancelEditBtn" class="btn-primary" style="background:#6B7280; margin-top:10px; display:none;">Batal Edit</button>
                    </form>
                </section>

                <section class="stats-section">
                    <div class="stat-card active" data-filter="all">
                        <span class="stat-number" id="totalTasks">0</span>
                        <span class="stat-label">Semua</span>
                    </div>
                    <div class="stat-card" data-filter="pending">
                        <span class="stat-number" id="pendingTasks">0</span>
                        <span class="stat-label">Pending</span>
                    </div>
                    <div class="stat-card" data-filter="completed">
                        <span class="stat-number" id="completedTasks">0</span>
                        <span class="stat-label">Selesai</span>
                    </div>
                </section>
            </aside>

            <main class="task-list-section">
                <div class="section-header">
                    <h2 class="section-title">Daftar Tugas</h2>
                </div>
                <div id="taskList" class="task-grid"></div>
                <div id="emptyState" class="empty-state hidden">
                    <img src="https://cdni.iconscout.com/illustration/premium/thumb/empty-state-2130362-1800926.png" alt="No tasks">
                    <p>Belum ada tugas.</p>
                </div>
            </main>
        </div>
    </div>

<script>
    const API_URL = 'index.php?action=';
    let userPhone = "<?php echo $userPhone; ?>"; 

    // Bot Simulation
    async function runBotSimulation(silent = false) {
        // if(!userPhone && !silent) return alert("Setting nomor WA dulu via profil!"); 
        // Logic auto check anyway
        
        try {
            const res = await fetch('reminder_bot.php');
            const data = await res.json();
            
            // Jika ada log baru
            if(data.logs && data.logs.length > 0) {
                // Play notification sound
                playNotificationSound();

                if(!silent) alert("Bot Reminder: \n" + data.logs.join("\n"));
                
                // Refresh chat so badge logic triggers
                fetchChatHistory(); 
            } 
        } catch(e) { console.error("Bot Check Error", e); }
    }

    function playNotificationSound() {
        // Simple beep using AudioContext or default hosted sound
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 500;
            gain.gain.value = 0.1;
            osc.start();
            setTimeout(() => osc.stop(), 200);
        } catch(e) {}
    }

    // --- Chat Logic ---
    const chatWidget = document.getElementById('chatbot-widget');
    const chatBody = document.getElementById('chatBody');
    const chatInput = document.getElementById('chatInput');
    const chatTrigger = document.querySelector('.chat-trigger');
    let lastChatCount = 0;

    function toggleChat() {
        chatWidget.classList.toggle('hidden-chat');
        if(!chatWidget.classList.contains('hidden-chat')){
            fetchChatHistory();
            removeAllBadges();
            setTimeout(() => chatBody.scrollTop = chatBody.scrollHeight, 100);
        }
    }
    
    function removeAllBadges() {
        const badges = document.querySelectorAll('.chat-badge');
        badges.forEach(b => b.remove());
        // Sync local count
        fetch(API_URL + 'get_chat_history').then(r => r.json()).then(d => { lastChatCount = d.length; });
    }

    async function fetchChatHistory() {
        try {
            const res = await fetch(API_URL + 'get_chat_history');
            const chats = await res.json();
            
            // Render logic
            const isScrolledToBottom = chatBody.scrollHeight - chatBody.clientHeight <= chatBody.scrollTop + 50;
            
            chatBody.innerHTML = '';
            chats.forEach(msg => {
                const div = document.createElement('div');
                div.className = `chat-msg ${msg.sender === 'bot' ? 'bot-msg' : 'user-msg'}`;
                
                // Parse Time
                const date = new Date(msg.created_at);
                const timeStr = date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                
                div.innerHTML = msg.message.replace(/\n/g, '<br>') + `<span class="chat-time">${timeStr}</span>`;
                chatBody.appendChild(div);
            });
            
            // Auto Scroll if was at bottom
            if(isScrolledToBottom) chatBody.scrollTop = chatBody.scrollHeight;
            
            // Badge Logic
            if(chats.length > lastChatCount) {
                if(chatWidget.classList.contains('hidden-chat')) {
                    playNotificationSound();
                    updateBadge(chats.length - lastChatCount);
                } else {
                    lastChatCount = chats.length;
                    // playNotificationSound(); // Optional: sound even if open
                }
            }
            
        } catch(e) {}
    }
    
    function updateBadge(count) {
        let badge = document.querySelector('.chat-badge');
        if(!badge) {
            badge = document.createElement('div');
            badge.className = 'chat-badge';
            chatTrigger.appendChild(badge);
        }
        badge.innerText = "!"; // Or count, but ! is cleaner for "New"
    }

    async function sendChatMessage() {
        const txt = chatInput.value.trim();
        if(!txt) return;
        
        chatInput.value = '';
        const div = document.createElement('div');
        div.className = 'chat-msg user-msg';
        div.innerText = txt;
        chatBody.appendChild(div);
        chatBody.scrollTop = chatBody.scrollHeight;
        
        await fetch(API_URL + 'send_chat', {
            method: 'POST',
            body: JSON.stringify({ message: txt })
        });
        fetchChatHistory();
    }

    function handleChatKey(e) {
        if(e.key === 'Enter') sendChatMessage();
    }

    // --- Basic App Logic ---
    const taskForm = document.getElementById('taskForm');
    const taskList = document.getElementById('taskList');
    const totalTasksEl = document.getElementById('totalTasks');
    const pendingTasksEl = document.getElementById('pendingTasks');
    const completedTasksEl = document.getElementById('completedTasks');
    const emptyState = document.getElementById('emptyState');
    const submitBtn = document.getElementById('submitBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const taskIdInput = document.getElementById('taskId');
    const filterCards = document.querySelectorAll('.stat-card');
    const waModal = document.getElementById('waModal');
    const closeModal = document.querySelector('.close-modal');
    
    let tasks = [];
    let currentFilter = 'all';
    let isEditing = false; 

    document.addEventListener('DOMContentLoaded', () => {
        fetchTasks();
        setDefaultDate();
        setInterval(renderTasks, 1000); // UI Update setiap detik
        
        // Auto-run bot check (Background Process)
        runBotSimulation(true); 
        setInterval(() => runBotSimulation(true), 30000); 

        // Real-time Chat Sync (Every 5 seconds)
        // This ensures the badge appears if bot sends message in background
        setInterval(() => fetchChatHistory(), 5000); 
    });

    taskForm.addEventListener('submit', handleFormSubmit);
    cancelEditBtn.addEventListener('click', resetForm);
    closeModal.addEventListener('click', () => waModal.classList.add('hidden'));
    window.onclick = (e) => { if (e.target == waModal) waModal.classList.add('hidden'); }

    filterCards.forEach(card => {
        card.addEventListener('click', () => {
            filterCards.forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            currentFilter = card.dataset.filter;
            renderTasks();
        });
    });

    async function fetchTasks() {
        try { const res = await fetch(API_URL + 'get_tasks'); tasks = await res.json(); renderTasks(); updateStats(); } catch (err) { console.error(err); }
    }
    async function handleFormSubmit(e) {
        e.preventDefault();
        const name = document.getElementById('taskName').value;
        const course = document.getElementById('courseName').value;
        const deadline = document.getElementById('deadline').value;
        const id = taskIdInput.value;
        if (!name || !course || !deadline) return;
        const payload = { name, course, deadline, id };
        const action = (isEditing && id) ? 'update_task' : 'add_task';
        await fetch(API_URL + action, { method: 'POST', body: JSON.stringify(payload) });
        fetchTasks(); resetForm();
        setTimeout(() => runBotSimulation(true), 2000); // Trigger check after add
    }
    async function deleteTask(id) { if(confirm('Hapus?')) { await fetch(API_URL + 'delete_task&id=' + id); fetchTasks(); } }
    async function toggleTask(id) { await fetch(API_URL + 'toggle_task&id=' + id); fetchTasks(); }
    
    function openWaModal() { waModal.classList.remove('hidden'); }
    async function saveWaNumber() {
        const phone = document.getElementById('waNumberInput').value;
        if(!phone) return alert("Invalid");
        await fetch(API_URL + 'update_phone', { method: 'POST', body: JSON.stringify({ phone }) });
        userPhone = phone;
        waModal.classList.add('hidden');
        alert("Nomor WA tersimpan!");
    }
    
    function sendToWa(taskName, dateString) {
        if(!userPhone) { openWaModal(); return; }
        let p = userPhone;
        if(p.startsWith('0')) p = '62' + p.substring(1);
        const text = `*Reminder* %0ATugas: ${taskName}%0ADeadline: ${dateString}`;
        window.open(`https://wa.me/${p}?text=${text}`, '_blank');
    }
    
    function addToCalendar(taskName, taskCourse, deadlineIso) {
        const startDate = new Date(deadlineIso);
        const endDate = new Date(startDate.getTime() + 3600000);
        const formatGCal = (d) => d.toISOString().replace(/-|:|\.\d\d\d/g, "");
        const url = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(taskName)}&dates=${formatGCal(startDate)}/${formatGCal(endDate)}&details=${encodeURIComponent(taskCourse)}`;
        window.open(url, '_blank');
    }

    function renderTasks() {
        taskList.innerHTML = '';
        let filteredTasks = tasks;
        if (currentFilter === 'pending') filteredTasks = tasks.filter(t => t.completed == 0);
        if (currentFilter === 'completed') filteredTasks = tasks.filter(t => t.completed == 1);

        if (filteredTasks.length === 0) {
            emptyState.classList.remove('hidden');
        } else {
            emptyState.classList.add('hidden');
            filteredTasks.forEach(task => {
                const isCompleted = task.completed == 1; // Robust check for string "1" or int 1
                const timeLeft = calculateTimeLeft(task.deadline);
                let priorityClass = 'priority-low';
                let countdownClass = 'safe';
                
                if (!isCompleted) {
                    if (timeLeft.total < 0 || timeLeft.days < 1) { priorityClass = 'priority-high'; countdownClass = 'urgent'; }
                    else if (timeLeft.days <= 3) { priorityClass = 'priority-medium'; countdownClass = 'moderate'; }
                } else { priorityClass = 'completed'; }
                
                const dateString = new Date(task.deadline).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

                 let countdownHtml = '';
                if (isCompleted) countdownHtml = `<div class="countdown-box" style="background:#F0FDF4; color:var(--success-color); font-weight:600;"><i class="fa-solid fa-check"></i> Selesai</div>`;
                else if (timeLeft.total < 0) countdownHtml = `<div class="countdown-box urgent"><i class="fa-solid fa-triangle-exclamation"></i> Terlewat: ${Math.abs(timeLeft.days)} Hari</div>`;
                else countdownHtml = `
                    <div class="countdown-box ${countdownClass}">
                        <div class="time-part"><span class="time-val">${timeLeft.days}</span><span class="time-lbl">Hari</span></div><span class="time-sep">:</span>
                        <div class="time-part"><span class="time-val">${timeLeft.hours}</span><span class="time-lbl">Jam</span></div><span class="time-sep">:</span>
                        <div class="time-part"><span class="time-val">${timeLeft.minutes}</span><span class="time-lbl">Mnt</span></div><span class="time-sep">:</span>
                        <div class="time-part"><span class="time-val">${timeLeft.seconds}</span><span class="time-lbl">Dtk</span></div>
                    </div>`;

                const html = `
                    <div class="task-card ${priorityClass} ${isCompleted ? 'completed' : ''}">
                        <div class="card-header">
                            <span class="course-badge">${task.course}</span>
                            <div class="card-actions">
                                <button class="action-btn" onclick="addToCalendar('${task.name}', '${task.course}', '${task.deadline}')"><i class="fa-regular fa-calendar-plus"></i></button>
                                <button class="action-btn edit" onclick="editTask(${task.id})"><i class="fa-solid fa-pen"></i></button>
                                <button class="action-btn delete" onclick="deleteTask(${task.id})"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                        <h3 class="task-title">${task.name}</h3>
                        ${countdownHtml}
                        <div class="card-footer">
                            <span class="deadline-text"><i class="fa-regular fa-calendar"></i> ${dateString}</span>
                            <button class="check-btn ${isCompleted ? 'active' : ''}" onclick="toggleTask(${task.id})">
                                <i class="fa-solid fa-check"></i> ${isCompleted ? 'Selesai' : 'Tandai Selesai'}
                            </button>
                        </div>
                    </div>`;
                taskList.innerHTML += html;
            });
        }
    }
    
    function resetForm() { taskForm.reset(); taskIdInput.value = ''; submitBtn.innerText = 'Simpan Tugas'; cancelEditBtn.style.display = 'none'; isEditing = false; setDefaultDate(); }
    function setDefaultDate() { const now = new Date(); now.setHours(now.getHours() + 24); const diff = now.getTimezoneOffset()*60000; document.getElementById('deadline').value = new Date(now-diff).toISOString().slice(0,16); }
    function updateStats() { totalTasksEl.innerText = tasks.length; pendingTasksEl.innerText = tasks.filter(t => t.completed==0).length; completedTasksEl.innerText = tasks.filter(t => t.completed==1).length; }
    function calculateTimeLeft(d) { 
        const t = Date.parse(d) - Date.now(); 
        return { 
            total:t, 
            days:Math.floor(t/(86400000)), 
            hours:Math.floor((t/3600000)%24), 
            minutes:Math.floor((t/60000)%60),
            seconds:Math.floor((t/1000)%60)
        }; 
    }
    function editTask(id) { const t = tasks.find(x => x.id == id); if(t) { document.getElementById('taskName').value = t.name; document.getElementById('courseName').value = t.course; document.getElementById('deadline').value = t.deadline.replace(' ','T').slice(0,16); taskIdInput.value = t.id; submitBtn.innerText = 'Update'; cancelEditBtn.style.display = 'block'; isEditing = true; } }

</script>
</body>
</html>
