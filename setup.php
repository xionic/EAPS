<?php


$hash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && $_POST['password'] !== '') {
    $hash = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>EAPS Setup — Generate Password Hash</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif;background:#0d1117;color:#e6edf3;min-height:100vh;display:flex;align-items:center;justify-content:center;font-size:14px}
.box{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:32px;width:460px;display:flex;flex-direction:column;gap:18px}
.logo{width:36px;height:36px;background:linear-gradient(135deg,#58a6ff 0%,#bc8cff 100%);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;margin:0 auto}
h1{font-size:16px;font-weight:600;text-align:center}
.sub{font-size:12px;color:#8b949e;text-align:center;margin-top:-10px}
label{display:block;font-size:11px;font-weight:600;color:#8b949e;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}
input[type=password],input[type=text]{width:100%;background:#0d1117;border:1px solid #30363d;border-radius:5px;color:#e6edf3;padding:7px 10px;font-size:14px;font-family:inherit}
input:focus{outline:none;border-color:#58a6ff;box-shadow:0 0 0 3px rgba(88,166,255,.15)}
button{width:100%;background:#238636;border:1px solid #2ea043;border-radius:5px;color:#fff;padding:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit}
button:hover{background:#2ea043}
.result{display:flex;flex-direction:column;gap:8px}
.result-label{font-size:11px;font-weight:600;color:#3fb950;text-transform:uppercase;letter-spacing:.06em}
.hash{width:100%;background:#0d1117;border:1px solid #30363d;border-radius:5px;color:#e6edf3;padding:7px 10px;font-size:12px;font-family:'SF Mono','Cascadia Code',Consolas,monospace;word-break:break-all;cursor:text;resize:none}
.copy-btn{width:100%;background:transparent;border:1px solid #30363d;border-radius:5px;color:#8b949e;padding:6px;font-size:12px;font-weight:500;cursor:pointer;font-family:inherit}
.copy-btn:hover{color:#e6edf3;border-color:#8b949e}
.note{font-size:12px;color:#8b949e;background:#0d1117;border:1px solid #30363d;border-radius:5px;padding:10px 12px;line-height:1.6}
.note code{font-family:'SF Mono','Cascadia Code',Consolas,monospace;font-size:11px;color:#e6edf3}
</style>
</head>
<body>
<div class="box">
    <div class="logo">E</div>
    <h1>Generate Password Hash</h1>
    <p class="sub">Paste the result into <code style="font-family:monospace;font-size:11px">ADMIN_PASSWORD_HASH</code> in config.php</p>

    <form method="post">
        <label for="pw">Password</label>
        <input id="pw" name="password" type="password" autocomplete="new-password" required placeholder="Enter password to hash">
        <br><br>
        <button type="submit">Generate hash</button>
    </form>

    <?php if ($hash): ?>
    <div class="result">
        <span class="result-label">Hash (bcrypt, cost 12)</span>
        <textarea class="hash" rows="3" readonly onclick="this.select()"><?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?></textarea>
        <button class="copy-btn" onclick="navigator.clipboard.writeText(<?= json_encode($hash) ?>).then(()=>{this.textContent='Copied!';setTimeout(()=>this.textContent='Copy to clipboard',1500)})">Copy to clipboard</button>
    </div>
    <?php endif; ?>

    <p class="note">Set in config.php:<br><code>define('ADMIN_PASSWORD_HASH', '&lt;hash&gt;');</code></p>
</div>
</body>
</html>
