<?php
// تنظیمات اولیه
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

// تنظیمات دیتابیس MySQL برای XAMPP
$db_host = 'localhost';
$db_user = 'eaarmiuy_chatroom';
$db_pass = '@85Arshiasadr5';
$db_name = 'eaarmiuy_chat.db';

// اتصال به MySQL
try {
    $conn = new mysqli($db_host, $db_user, $db_pass);
    
    // ایجاد دیتابیس اگر وجود نداشت
    $conn->query("CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($db_name);
    
    // ایجاد جدول کاربران
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        avatar_color VARCHAR(7) DEFAULT '#5865F2',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // ایجاد جدول پیام‌ها
    $conn->query("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
} catch (Exception $e) {
    die(json_encode(['success' => false, 'message' => 'خطا در اتصال به دیتابیس']));
}

// مدیریت درخواست‌های AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // ثبت نام
    if ($action === 'register') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if (strlen($username) < 3) {
            echo json_encode(['success' => false, 'message' => 'نام کاربری باید حداقل 3 کاراکتر باشد']);
            exit;
        }
        
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'رمز عبور باید حداقل 6 کاراکتر باشد']);
            exit;
        }
        
        // رنگ تصادفی برای آواتار
        $colors = ['#5865F2', '#57F287', '#FEE75C', '#EB459E', '#ED4245', '#3BA55D'];
        $avatar_color = $colors[array_rand($colors)];
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, avatar_color) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashedPassword, $avatar_color);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => '✅ ثبت نام با موفقیت انجام شد!']);
        } else {
            echo json_encode(['success' => false, 'message' => '❌ این نام کاربری قبلاً استفاده شده است']);
        }
        $stmt->close();
        exit;
    }
    
    // ورود
    if ($action === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['avatar_color'] = $user['avatar_color'];
            echo json_encode(['success' => true, 'message' => '✅ خوش آمدید!']);
        } else {
            echo json_encode(['success' => false, 'message' => '❌ نام کاربری یا رمز عبور اشتباه است']);
        }
        $stmt->close();
        exit;
    }
    
    // ارسال پیام
    if ($action === 'send_message') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا وارد شوید']);
            exit;
        }
        
        $message = trim($_POST['message']);
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'پیام نمی‌تواند خالی باشد']);
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO messages (user_id, username, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['username'], $message);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در ارسال پیام']);
        }
        $stmt->close();
        exit;
    }
    
    // دریافت پیام‌ها
    if ($action === 'get_messages') {
        $lastId = isset($_POST['last_id']) ? intval($_POST['last_id']) : 0;
        
        $stmt = $conn->prepare("SELECT m.*, u.avatar_color FROM messages m 
                                JOIN users u ON m.user_id = u.id 
                                WHERE m.id > ? ORDER BY m.id ASC LIMIT 50");
        $stmt->bind_param("i", $lastId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        $stmt->close();
        exit;
    }
    
    // خروج
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArshiaHMC ROOM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background: #36393f;
            color: #dcddde;
            height: 100vh;
            overflow: hidden;
        }
        
        .container {
            display: flex;
            height: 100vh;
            background: #36393f;
        }
        
        /* Sidebar (مثل Discord) */
        .sidebar {
            width: 240px;
            background: #2f3136;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #202225;
        }
        
        .server-header {
            height: 48px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            font-weight: 600;
            font-size: 15px;
            color: #fff;
            border-bottom: 1px solid #202225;
            box-shadow: 0 1px 0 rgba(4,4,5,0.2);
        }
        
        .channels-list {
            flex: 1;
            padding: 8px;
            overflow-y: auto;
        }
        
        .channel-item {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            margin-bottom: 2px;
            border-radius: 4px;
            color: #96989d;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        
        .channel-item:hover {
            background: #393c43;
            color: #dcddde;
        }
        
        .channel-item.active {
            background: #404249;
            color: #fff;
        }
        
        .channel-icon {
            margin-left: 6px;
            font-size: 20px;
        }
        
        /* Main Chat Area */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #36393f;
        }
        
        .chat-header {
            height: 48px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            border-bottom: 1px solid #202225;
            box-shadow: 0 1px 0 rgba(4,4,5,0.2);
        }
        
        .channel-name {
            font-weight: 600;
            color: #fff;
            font-size: 16px;
        }
        
        .channel-name::before {
            content: '#';
            color: #72767d;
            margin-left: 4px;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 16px;
            display: flex;
            flex-direction: column;
        }
        
        .messages-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .messages-container::-webkit-scrollbar-track {
            background: #2e3338;
        }
        
        .messages-container::-webkit-scrollbar-thumb {
            background: #202225;
            border-radius: 4px;
        }
        
        .messages-container::-webkit-scrollbar-thumb:hover {
            background: #1a1c1f;
        }
        
        .message {
            display: flex;
            padding: 4px 16px;
            margin-bottom: 8px;
            position: relative;
            animation: messageAppear 0.3s ease;
        }
        
        @keyframes messageAppear {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message:hover {
            background: #32353b;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #fff;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .message-content {
            flex: 1;
        }
        
        .message-header {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
        }
        
        .message-username {
            font-weight: 600;
            color: #fff;
            margin-left: 8px;
            font-size: 15px;
        }
        
        .message-time {
            font-size: 12px;
            color: #72767d;
        }
        
        .message-text {
            color: #dcddde;
            line-height: 1.375;
            word-wrap: break-word;
            font-size: 15px;
        }
        
        .message-input-container {
            padding: 16px;
            background: #36393f;
            border-top: 1px solid #202225;
        }
        
        .chat-footer {
            background: #2f3136;
            padding: 8px 16px;
            text-align: center;
            font-size: 11px;
            color: #72767d;
            border-top: 1px solid #202225;
        }
        
        .chat-footer a {
            color: #5865f2;
            text-decoration: none;
            transition: color 0.17s ease;
        }
        
        .chat-footer a:hover {
            color: #4752c4;
            text-decoration: underline;
        }
        
        .message-input-wrapper {
            background: #40444b;
            border-radius: 8px;
            padding: 11px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #202225;
        }
        
        .message-input {
            flex: 1;
            background: transparent;
            border: none;
            color: #dcddde;
            font-size: 15px;
            outline: none;
            min-width: 0;
        }
        
        .message-input::placeholder {
            color: #72767d;
        }
        
        .send-btn {
            background: #5865f2;
            border: none;
            color: #fff;
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.17s ease;
            flex-shrink: 0;
            white-space: nowrap;
        }
        
        .send-btn:hover {
            background: #4752c4;
        }
        
        .send-btn:active {
            background: #3c45a5;
        }
        
        /* User Panel در پایین Sidebar */
        .user-panel {
            height: 52px;
            background: #292b2f;
            display: flex;
            align-items: center;
            padding: 0 8px;
            border-top: 1px solid #202225;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-left: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #fff;
            font-size: 14px;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: #fff;
        }
        
        .user-status {
            font-size: 12px;
            color: #b9bbbe;
        }
        
        .logout-btn {
            background: transparent;
            border: none;
            color: #b9bbbe;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.17s ease;
        }
        
        .logout-btn:hover {
            background: #3f4147;
            color: #fff;
        }
        
        /* Auth Container */
        .auth-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #36393f;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .auth-box {
            background: #2f3136;
            padding: 32px;
            border-radius: 8px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.24);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .auth-header h1 {
            color: #fff;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .auth-header p {
            color: #b9bbbe;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #b9bbbe;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            background: #202225;
            border: 1px solid #1e1f22;
            border-radius: 4px;
            color: #dcddde;
            font-size: 16px;
            outline: none;
            transition: border-color 0.2s ease;
        }
        
        .form-group input:focus {
            border-color: #5865f2;
        }
        
        .form-group input::placeholder {
            color: #72767d;
        }
        
        .btn-primary {
            width: 100%;
            padding: 12px;
            background: #5865f2;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.17s ease;
            margin-top: 8px;
        }
        
        .btn-primary:hover {
            background: #4752c4;
        }
        
        .btn-secondary {
            width: 100%;
            padding: 12px;
            background: transparent;
            border: none;
            color: #00aff4;
            font-size: 14px;
            cursor: pointer;
            margin-top: 8px;
        }
        
        .btn-secondary:hover {
            text-decoration: underline;
        }
        
        .message-box {
            margin-bottom: 16px;
            padding: 12px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .error-message {
            background: #f04747;
            color: #fff;
        }
        
        .success-message {
            background: #43b581;
            color: #fff;
        }
        
        .hidden {
            display: none !important;
        }
        
        /* Responsive برای موبایل و تبلت */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                right: -240px;
                top: 0;
                height: 100vh;
                z-index: 1000;
                transition: right 0.3s ease;
            }
            
            .sidebar.show {
                right: 0;
                box-shadow: -2px 0 8px rgba(0,0,0,0.3);
            }
            
            .mobile-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 999;
                display: none;
            }
            
            .mobile-overlay.show {
                display: block;
            }
            
            .chat-header {
                padding: 0 12px;
            }
            
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                background: transparent;
                border: none;
                color: #b9bbbe;
                font-size: 20px;
                cursor: pointer;
                border-radius: 4px;
                margin-left: 8px;
            }
            
            .mobile-menu-btn:active {
                background: #404249;
            }
            
            .channel-name {
                font-size: 15px;
            }
            
            .messages-container {
                padding: 12px 8px;
            }
            
            .message {
                padding: 4px 8px;
            }
            
            .message-avatar {
                width: 36px;
                height: 36px;
                margin-left: 12px;
                font-size: 14px;
            }
            
            .message-username {
                font-size: 14px;
            }
            
            .message-text {
                font-size: 14px;
            }
            
            .message-input-container {
                padding: 12px 8px;
            }
            
            .message-input-wrapper {
                padding: 8px 12px;
            }
            
            .message-input {
                font-size: 14px;
            }
            
            .send-btn {
                padding: 6px 12px;
                font-size: 14px;
            }
            
            .auth-box {
                padding: 24px;
                margin: 16px;
            }
            
            .auth-header h1 {
                font-size: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .message-avatar {
                width: 32px;
                height: 32px;
                margin-left: 8px;
                font-size: 13px;
            }
            
            .message-username {
                font-size: 13px;
            }
            
            .message-text {
                font-size: 13px;
            }
            
            .send-btn {
                padding: 6px 10px;
                font-size: 13px;
            }
        }
        
        .mobile-menu-btn {
            display: none;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: flex;
            }
        }
    </style>
</head>
<body>
    <!-- Auth Screen -->
    <div class="auth-container" id="authContainer">
        <div class="auth-box">
            <div class="auth-header">
                <h1>خوش آمدید!</h1>
                <p>وارد شوید یا ثبت نام کنید</p>
            </div>
            
            <div id="messageBox"></div>
            
            <!-- Login Form -->
            <form id="loginForm">
                <div class="form-group">
                    <label>نام کاربری</label>
                    <input type="text" id="loginUsername" placeholder="نام کاربری خود را وارد کنید" required>
                </div>
                <div class="form-group">
                    <label>رمز عبور</label>
                    <input type="password" id="loginPassword" placeholder="رمز عبور خود را وارد کنید" required>
                </div>
                <button type="submit" class="btn-primary">ورود</button>
                <button type="button" class="btn-secondary" onclick="toggleForms()">حساب کاربری ندارید؟ ثبت نام کنید</button>
            </form>
            
            <!-- Register Form -->
            <form id="registerForm" class="hidden">
                <div class="form-group">
                    <label>نام کاربری</label>
                    <input type="text" id="registerUsername" placeholder="حداقل 3 کاراکتر" required>
                </div>
                <div class="form-group">
                    <label>رمز عبور</label>
                    <input type="password" id="registerPassword" placeholder="حداقل 6 کاراکتر" required>
                </div>
                <button type="submit" class="btn-primary">ثبت نام</button>
                <button type="button" class="btn-secondary" onclick="toggleForms()">حساب کاربری دارید؟ وارد شوید</button>
            </form>
        </div>
    </div>
    
    <!-- Chat Interface -->
    <div class="container hidden" id="chatContainer">
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileSidebar()"></div>
        
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="server-header">
                💬 چت گروهی
            </div>
            
            <div class="channels-list">
                <div class="channel-item active">
                    <span class="channel-icon">#</span>
                    <span>عمومی</span>
                </div>
            </div>
            
            <div class="user-panel">
                <div class="user-avatar" id="userAvatar"></div>
                <div class="user-info">
                    <div class="user-name" id="userName"></div>
                    <div class="user-status">آنلاین</div>
                </div>
                <button class="logout-btn" onclick="logout()" title="خروج">🚪</button>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="chat-header">
                <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">☰</button>
                <span class="channel-name">عمومی</span>
            </div>
            
            <div class="messages-container" id="messagesContainer">
                <!-- پیام‌ها اینجا نمایش داده می‌شوند -->
            </div>
            
            <div class="message-input-container">
                <div class="message-input-wrapper">
                    <input 
                        type="text" 
                        class="message-input" 
                        id="messageInput" 
                        placeholder="پیام خود را در #عمومی بنویسید..."
                        onkeypress="handleKeyPress(event)"
                    >
                    <button class="send-btn" onclick="sendMessage()">ارسال</button>
                </div>
            </div>
            
            <div class="chat-footer">
                Developed By ArshiaHMC With Love
                <br>
                Version 1.0
                <br>
                More Soon...
            </div>
        </div>
    </div>
    
    <script>
        let lastMessageId = 0;
        let pollingInterval;
        let currentUsername = '';
        
        // بررسی لاگین
        <?php if (isset($_SESSION['user_id'])): ?>
            showChat('<?php echo $_SESSION['username']; ?>', '<?php echo $_SESSION['avatar_color']; ?>');
        <?php endif; ?>
        
        // تغییر فرم‌ها
        function toggleForms() {
            document.getElementById('loginForm').classList.toggle('hidden');
            document.getElementById('registerForm').classList.toggle('hidden');
            document.getElementById('messageBox').innerHTML = '';
        }
        
        // نمایش پیام
        function showMessage(message, isError = false) {
            const messageBox = document.getElementById('messageBox');
            messageBox.innerHTML = `<div class="${isError ? 'error-message' : 'success-message'} message-box">${message}</div>`;
            setTimeout(() => messageBox.innerHTML = '', 4000);
        }
        
        // ثبت نام
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'register');
            formData.append('username', document.getElementById('registerUsername').value);
            formData.append('password', document.getElementById('registerPassword').value);
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    showMessage(data.message, false);
                    setTimeout(() => {
                        toggleForms();
                        document.getElementById('registerUsername').value = '';
                        document.getElementById('registerPassword').value = '';
                    }, 1500);
                } else {
                    showMessage(data.message, true);
                }
            } catch (error) {
                showMessage('❌ خطا در اتصال به سرور', true);
            }
        });
        
        // ورود
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('username', document.getElementById('loginUsername').value);
            formData.append('password', document.getElementById('loginPassword').value);
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    showMessage(data.message, true);
                }
            } catch (error) {
                showMessage('❌ خطا در اتصال به سرور', true);
            }
        });
        
        // نمایش چت
        function showChat(username, avatarColor) {
            currentUsername = username;
            document.getElementById('authContainer').classList.add('hidden');
            document.getElementById('chatContainer').classList.remove('hidden');
            document.getElementById('userName').textContent = username;
            document.getElementById('userAvatar').textContent = username.charAt(0).toUpperCase();
            document.getElementById('userAvatar').style.background = avatarColor;
            
            loadMessages();
            startPolling();
        }
        
        // ارسال پیام
        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message) return;
            
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('message', message);
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    input.value = '';
                    loadMessages();
                }
            } catch (error) {
                console.error('خطا در ارسال پیام');
            }
        }
        
        // دریافت پیام‌ها
        async function loadMessages() {
            const formData = new FormData();
            formData.append('action', 'get_messages');
            formData.append('last_id', lastMessageId);
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success && data.messages.length > 0) {
                    const container = document.getElementById('messagesContainer');
                    
                    data.messages.forEach(msg => {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = 'message';
                        
                        const time = new Date(msg.created_at).toLocaleTimeString('fa-IR', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        messageDiv.innerHTML = `
                            <div class="message-avatar" style="background: ${msg.avatar_color}">
                                ${msg.username.charAt(0).toUpperCase()}
                            </div>
                            <div class="message-content">
                                <div class="message-header">
                                    <span class="message-username">${escapeHtml(msg.username)}</span>
                                    <span class="message-time">${time}</span>
                                </div>
                                <div class="message-text">${escapeHtml(msg.message)}</div>
                            </div>
                        `;
                        
                        container.appendChild(messageDiv);
                        lastMessageId = msg.id;
                    });
                    
                    // اسکرول به آخرین پیام با انیمیشن
                    setTimeout(() => {
                        container.scrollTo({
                            top: container.scrollHeight,
                            behavior: 'smooth'
                        });
                    }, 100);
                }
            } catch (error) {
                console.error('خطا در دریافت پیام‌ها');
            }
        }
        
        // Polling برای Real-time
        function startPolling() {
            pollingInterval = setInterval(loadMessages, 1000);
        }
        
        // خروج
        async function logout() {
            const formData = new FormData();
            formData.append('action', 'logout');
            
            await fetch('', { method: 'POST', body: formData });
            clearInterval(pollingInterval);
            location.reload();
        }
        
        // Enter برای ارسال
        function handleKeyPress(event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        }
        
        // امنیت XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Toggle Sidebar موبایل
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
    </script>
</body>
</html>
