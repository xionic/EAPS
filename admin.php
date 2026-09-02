<?php
require_once(__DIR__ . "/config.php");
require_once(__DIR__ . "/lib/Argh/src/Argh.php");
require_once(__DIR__ . "/functions.php");

use \xionic\Argh\Argh;

// ── Auth gate ─────────────────────────────────────────────────────────────
session_start();

if (ADMIN_REQUIRE_AUTH) {
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    if (ADMIN_LAN_ONLY) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!preg_match('/^(127\.|10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.)/', $ip)) {
            http_response_code(403);
            exit('403 Forbidden');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_login_username'])) {
        if (
            $_POST['_login_username'] === ADMIN_USERNAME &&
            password_verify($_POST['_login_password'] ?? '', ADMIN_PASSWORD_HASH)
        ) {
            session_regenerate_id(true);
            $_SESSION['admin_authed'] = true;
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        $_login_error = true;
    }

    if (empty($_SESSION['admin_authed'])) {
        $e = isset($_login_error) ? '<p class="err">Invalid credentials.</p>' : '';
        echo '<!DOCTYPE html><html lang="en"><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>EAPS Admin \xe2\x80\x94 Login</title>'
            . '<style>'
            . '*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif;background:#0d1117;color:#e6edf3;min-height:100vh;display:flex;align-items:center;justify-content:center;font-size:14px}'
            . '.box{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:32px;width:320px}'
            . '.logo{width:36px;height:36px;background:linear-gradient(135deg,#58a6ff 0%,#bc8cff 100%);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;margin:0 auto 20px}'
            . 'h1{font-size:16px;font-weight:600;text-align:center;margin-bottom:20px}'
            . 'label{display:block;font-size:11px;font-weight:600;color:#8b949e;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}'
            . 'input{width:100%;background:#0d1117;border:1px solid #30363d;border-radius:5px;color:#e6edf3;padding:7px 10px;font-size:14px;margin-bottom:14px}'
            . 'input:focus{outline:none;border-color:#58a6ff}'
            . 'button{width:100%;background:#238636;border:1px solid #2ea043;border-radius:5px;color:#fff;padding:8px;font-size:14px;font-weight:600;cursor:pointer}'
            . 'button:hover{background:#2ea043}'
            . '.err{color:#f85149;font-size:13px;text-align:center;margin-bottom:12px}'
            . '</style></head><body>'
            . '<div class="box"><div class="logo">E</div>'
            . '<h1>EAPS Admin</h1>' . $e
            . '<form method="post">'
            . '<label for="u">Username</label>'
            . '<input id="u" name="_login_username" type="text" autocomplete="username" required>'
            . '<label for="p">Password</label>'
            . '<input id="p" name="_login_password" type="password" autocomplete="current-password" required>'
            . '<button type="submit">Sign in</button>'
            . '</form></div></body></html>';
        exit;
    }
}
// ──────────────────────────────────────────────────────────────────────────

function h($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }

// POST handlers — before HTML so redirect headers work
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = get_db_connection();

    if (isset($_POST['ajax_delete'])) {
        header('Content-Type: application/json');
        $vid = intval($_POST['ajax_delete']);
        if ($vid > 0) {
            $stmt = $db->prepare("DELETE FROM tValue WHERE value_id = :vid");
            $stmt->bindValue(':vid', $vid, PDO::PARAM_INT);
            $stmt->execute();
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if (isset($_POST['ajax_delete_bulk']) && is_array($_POST['ajax_delete_bulk'])) {
        header('Content-Type: application/json');
        $stmt = $db->prepare("DELETE FROM tValue WHERE value_id = :vid");
        foreach ($_POST['ajax_delete_bulk'] as $vid) {
            $vid = intval($vid);
            if ($vid > 0) {
                $stmt->bindValue(':vid', $vid, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if (isset($_POST['delete_selected']) && is_array($_POST['delete_selected'])) {
        $stmt = $db->prepare("DELETE FROM tValue WHERE value_id = :vid");
        foreach ($_POST['delete_selected'] as $vid) {
            $vid = intval($vid);
            if ($vid > 0) {
                $stmt->bindValue(':vid', $vid, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . '?' . $_SERVER['QUERY_STRING']);
        exit;
    }

    if (isset($_POST['delete'])) {
        $value_id = intval($_POST['delete']);
        $stmt = $db->prepare("DELETE FROM tValue WHERE value_id = :value_id");
        $stmt->bindValue(":value_id", $value_id, PDO::PARAM_INT);
        $stmt->execute();
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . '?' . $_SERVER['QUERY_STRING']);
        exit;
    }

    if (isset($_POST['save']) && isset($_POST['edit'])) {
        $value_id = intval($_POST['save']);
        $fields = $_POST['edit'][$value_id] ?? [];
        if (isset($fields['value'])) {
            $stmt = $db->prepare("UPDATE tValue SET value_data = :value WHERE value_id = :value_id");
            $stmt->bindValue(":value", $fields['value'], PDO::PARAM_STR);
            $stmt->bindValue(":value_id", $value_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        if (isset($fields['created'])) {
            $created = strtotime($fields['created']);
            if ($created !== false) {
                $stmt = $db->prepare("UPDATE tValue SET created = :created WHERE value_id = :value_id");
                $stmt->bindValue(":created", $created, PDO::PARAM_INT);
                $stmt->bindValue(":value_id", $value_id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . '?' . $_SERVER['QUERY_STRING']);
        exit;
    }
}

// AJAX: tags for client
if (isset($_GET['ajax']) && $_GET['ajax'] === 'tags' && isset($_GET['client_key'])) {
    header('Content-Type: application/json');
    echo json_encode(get_tags($_GET['client_key']));
    exit;
}

// AJAX: keys for tags
if (isset($_GET['ajax']) && $_GET['ajax'] === 'keys' && isset($_GET['tag']) && isset($_GET['client_key'])) {
    header('Content-Type: application/json');
    $tags = $_GET['tag'];
    if (!is_array($tags)) $tags = [$tags];
    $tags = array_values(array_filter(array_map('strval', $tags), function($t) { return $t !== ''; }));
    if (!$tags) { echo json_encode([]); exit; }
    try {
        $db = get_db_connection();
        $placeholders = [];
        $params = [':client_key' => $_GET['client_key']];
        foreach ($tags as $i => $tag) {
            $ph = ':tag_' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $tag;
        }
        $sql = "SELECT DISTINCT tTag.tag_name, tKey.key_name
                FROM tKey
                INNER JOIN tTag ON (tKey.tag_id = tTag.tag_id)
                INNER JOIN tClient ON (tTag.client_id = tClient.client_id)
                WHERE tClient.client_key = :client_key
                  AND tTag.tag_name IN (" . implode(',', $placeholders) . ")
                ORDER BY tTag.tag_name ASC, tKey.key_name ASC";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        echo json_encode([]);
    }
    exit;
}

// Search
$rows = [];
$limit = 200;
$show_all = isset($_GET['show_all']);
if (isset($_GET['submit'])) {
    $args = $_GET;
    $selected_tags = [];
    if (isset($args['tag'])) {
        $selected_tags = is_array($args['tag']) ? $args['tag'] : [$args['tag']];
        $selected_tags = array_values(array_filter(array_map('strval', $selected_tags), function($t) { return $t !== ''; }));
    }
    $selected_keys = [];
    if (isset($args['key'])) {
        $selected_keys = is_array($args['key']) ? $args['key'] : [$args['key']];
        $selected_keys = array_values(array_filter(array_map('strval', $selected_keys), function($k) { return $k !== ''; }));
    }
    $params = [];
    $where = [];
    if (!empty($args['client_key'])) {
        $where[] = "tClient.client_key = :client_key";
        $params[':client_key'] = $args['client_key'];
    }
    if ($selected_tags) {
        $tag_phs = [];
        foreach ($selected_tags as $i => $t) {
            $ph = ':tag_name_' . $i;
            $tag_phs[] = $ph;
            $params[$ph] = $t;
        }
        $where[] = "tTag.tag_name IN (" . implode(', ', $tag_phs) . ")";
    }
    if ($selected_keys) {
        $pair_clauses = [];
        foreach ($selected_keys as $i => $ep) {
            $decoded = json_decode($ep, true);
            if (!is_array($decoded) || !isset($decoded['tag'], $decoded['key'])) continue;
            $tag_ph = ':key_tag_' . $i;
            $key_ph = ':key_name_' . $i;
            $params[$tag_ph] = (string)$decoded['tag'];
            $params[$key_ph] = (string)$decoded['key'];
            $pair_clauses[] = "(tTag.tag_name = $tag_ph AND tKey.key_name = $key_ph)";
        }
        if ($pair_clauses) $where[] = "(" . implode(" OR ", $pair_clauses) . ")";
    }
    if (!empty($args['after'])) {
        $where[] = "tValue.created > :after";
        $params[':after'] = strtotime($args['after']);
    }
    if (!empty($args['before'])) {
        $where[] = "tValue.created < :before";
        $params[':before'] = strtotime($args['before']);
    }
    $where_sql = $where ? " AND " . implode(" AND ", $where) : "";
    $sql = "SELECT client_name, client_key, tag_name as tag, key_name as `key`, value_id, value_data as `value`, `created`
            FROM tClient
            INNER JOIN tTag ON (tClient.client_id = tTag.client_id)
            INNER JOIN tKey ON (tTag.tag_id = tKey.tag_id)
            INNER JOIN tValue ON (tKey.key_id = tValue.key_id)
            WHERE tClient.client_id = tClient.client_id $where_sql
            ORDER BY tValue.created DESC";
    if (!$show_all) $sql .= " LIMIT $limit";
    try {
        $db = get_db_connection();
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Database error: " . h($e->getMessage()));
    }
}

// CSV export
if (isset($_GET['csv'])) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-type: text/csv');
    header('Content-disposition: attachment;filename=' . (isset($_GET['client_key']) ? $_GET['client_key'] : 'export') . '.csv');
    if (count($rows)) {
        $out = fopen('php://output', 'w');
        $strip = isset($_GET['strip_client_id']) && $_GET['strip_client_id'] === 'on';
        $headers = array_keys($rows[0]);
        if ($strip) $headers = array_values(array_filter($headers, function($col) { return $col !== 'client_key'; }));
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            $row['created'] = (!isset($_GET['timestamp']) && isset($row['created'])) ? date('Y/m/d H:i:s', $row['created']) : $row['created'];
            if ($strip) unset($row['client_key']);
            fputcsv($out, $row);
        }
        fclose($out);
    }
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EAPS Admin</title>
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --bg:          #0d1117;
        --surface:     #161b22;
        --surface2:    #1c2128;
        --border:      #30363d;
        --border-soft: #21262d;
        --text:        #e6edf3;
        --muted:       #8b949e;
        --accent:      #58a6ff;
        --accent-dim:  rgba(88,166,255,.15);
        --danger:      #f85149;
        --danger-dim:  rgba(248,81,73,.15);
        --success:     #3fb950;
        --warn:        #d29922;
        --r:           8px;
        --rs:          5px;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        font-size: 14px;
        line-height: 1.5;
    }

    /* ── Header ── */
    .hdr {
        position: sticky; top: 0; z-index: 100;
        background: var(--surface);
        border-bottom: 1px solid var(--border);
        padding: 0 24px;
        height: 52px;
        display: flex; align-items: center; gap: 12px;
    }
    .hdr-logo {
        width: 30px; height: 30px; flex-shrink: 0;
        background: linear-gradient(135deg, #58a6ff 0%, #bc8cff 100%);
        border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 800; color: #fff; letter-spacing: -.5px;
    }
    .hdr h1 { font-size: 15px; font-weight: 600; letter-spacing: -.01em; }
    .hdr-divider { width: 1px; height: 18px; background: var(--border); }
    .hdr-sub { font-size: 12px; color: var(--muted); }

    /* ── Layout ── */
    .main { padding: 20px 24px; max-width: 1440px; margin: 0 auto; }

    /* ── Card ── */
    .card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r);
        overflow: clip;
        margin-bottom: 16px;
    }
    .card-hdr {
        padding: 12px 20px;
        border-bottom: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        flex-wrap: wrap;
    }
    .card-title { font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 7px; }
    .card-body { padding: 20px; }

    /* ── Form ── */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
        gap: 16px;
        align-items: start;
    }
    .form-field { display: flex; flex-direction: column; gap: 6px; }

    label, .lbl {
        font-size: 11px; font-weight: 600;
        color: var(--muted);
        text-transform: uppercase; letter-spacing: .06em;
        user-select: none;
    }
    .lbl-hint { font-weight: 400; text-transform: none; letter-spacing: 0; opacity: .7; }

    select, input[type=datetime-local], input[type=text] {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: var(--rs);
        color: var(--text);
        padding: 7px 10px;
        font-size: 13px; font-family: inherit;
        width: 100%;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    select:focus, input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-dim);
    }
    select[multiple] {
        padding: 4px;
        min-height: 110px;
    }
    select[multiple] option { padding: 4px 8px; border-radius: 3px; }
    select[multiple] option:checked { background: var(--accent); color: #0d1117; }

    .input-row { display: flex; gap: 6px; align-items: center; }
    .input-row input { flex: 1; min-width: 0; }

    /* ── Buttons ── */
    .btn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 7px 14px;
        border-radius: var(--rs);
        border: 1px solid transparent;
        font-size: 13px; font-weight: 500; font-family: inherit;
        cursor: pointer; text-decoration: none; white-space: nowrap;
        transition: background .15s, color .15s, border-color .15s, filter .15s;
    }
    .btn svg { flex-shrink: 0; }
    .btn-primary  { background: var(--accent); color: #0d1117; border-color: var(--accent); }
    .btn-primary:hover { filter: brightness(1.12); }
    .btn-ghost    { background: transparent; color: var(--muted); border-color: var(--border); }
    .btn-ghost:hover { color: var(--text); border-color: var(--muted); }
    .btn-danger   { background: transparent; color: var(--danger); border-color: var(--danger); }
    .btn-danger:hover { background: var(--danger-dim); }
    .btn-danger-solid { background: var(--danger); color: #fff; border-color: var(--danger); }
    .btn-danger-solid:hover { filter: brightness(1.1); }
    .btn-success  { background: var(--success); color: #0d1117; border-color: var(--success); }
    .btn-success:hover { filter: brightness(1.1); }
    .btn-sm { padding: 4px 10px; font-size: 12px; }

    .form-actions {
        display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
        margin-top: 16px; padding-top: 16px;
        border-top: 1px solid var(--border);
    }

    /* ── Results bar ── */
    .results-bar {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 8px;
        padding: 10px 16px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--rs);
        margin-bottom: 12px;
        font-size: 13px;
    }
    .badge-warn {
        display: inline-block; padding: 1px 8px; border-radius: 999px;
        background: rgba(210,153,34,.18); color: #e3b341;
        border: 1px solid rgba(210,153,34,.3);
        font-size: 11px; font-weight: 500;
    }

    /* ── Table ── */
    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }

    table { border-collapse: collapse; width: 100%; font-size: 13px; }
    thead tr { background: var(--surface2); }
    th {
        padding: 9px 12px;
        text-align: left;
        font-size: 11px; font-weight: 600; color: var(--muted);
        text-transform: uppercase; letter-spacing: .06em;
        white-space: nowrap;
        border-bottom: 2px solid var(--border);
        background: var(--surface2);
    }
    tbody tr { border-bottom: 1px solid var(--border-soft); transition: background .1s; }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: rgba(255,255,255,.025); }
    tbody tr.row-selected { background: rgba(88,166,255,.08) !important; }

    td { padding: 8px 12px; vertical-align: middle; }
    td.muted-cell { color: var(--muted); font-size: 12px; }

    td input[type=text]          { padding: 4px 8px; font-size: 12px; width: 100%; min-width: 90px; }
    td input[type=datetime-local]{ padding: 4px 8px; font-size: 12px; width: auto; min-width: 170px; }

    .mono { font-family: 'SF Mono', 'Cascadia Code', Consolas, monospace; font-size: 12px; }

    input[type=checkbox] {
        width: 15px; height: 15px;
        accent-color: var(--accent);
        cursor: pointer; flex-shrink: 0;
    }
    th:first-child, td:first-child { padding-left: 16px; width: 44px; }
    th:last-child,  td:last-child  { padding-right: 16px; }

    .action-btns { display: flex; gap: 4px; }

    /* ── Column toggles ── */
    [data-col].col-hidden { display: none; }
    .col-toggles { display: flex; gap: 4px; flex-wrap: wrap; }
    .col-toggle-btn { background: transparent; color: var(--muted); border-color: var(--border); }
    .col-toggle-btn.active { background: var(--accent-dim); color: var(--accent); border-color: rgba(88,166,255,.4); }

    /* ── Bulk bar ── */
    .bulk-bar {
        position: fixed; bottom: 20px; left: 50%;
        transform: translateX(-50%) translateY(100px);
        opacity: 0; pointer-events: none;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 999px;
        padding: 8px 12px 8px 20px;
        display: flex; align-items: center; gap: 10px;
        box-shadow: 0 8px 40px rgba(0,0,0,.65), 0 0 0 1px rgba(88,166,255,.1);
        transition: transform .3s cubic-bezier(.34,1.56,.64,1), opacity .2s;
        z-index: 300; white-space: nowrap;
    }
    .bulk-bar.show {
        transform: translateX(-50%) translateY(0);
        opacity: 1; pointer-events: auto;
    }
    .bulk-count { font-size: 13px; font-weight: 600; }
    .bulk-sep   { width: 1px; height: 18px; background: var(--border); }

    /* ── Empty state ── */
    .empty { text-align: center; padding: 48px 24px; color: var(--muted); font-size: 14px; }

    /* ── Mobile ── */
    @media (max-width: 640px) {
        .main { padding: 12px; }
        .hdr  { padding: 0 16px; }
        .hdr-divider, .hdr-sub { display: none; }
        .card-body { padding: 14px; }
        .form-grid { grid-template-columns: 1fr; }
        .form-actions { flex-direction: column; align-items: stretch; }
        .form-actions .btn, .form-actions a { justify-content: center; }
        .results-bar { flex-direction: column; align-items: flex-start; }
        .bulk-bar {
            left: 12px; right: 12px; bottom: 12px;
            transform: translateY(100px);
            border-radius: var(--r);
        }
        .bulk-bar.show { transform: translateY(0); }
    }
    </style>
</head>
<body>

<header class="hdr">
    <div class="hdr-logo">E</div>
    <h1>EAPS Admin</h1>
    <div class="hdr-divider"></div>
    <span class="hdr-sub">Data management</span>
    <?php if (ADMIN_REQUIRE_AUTH): ?>
    <a href="?logout" class="btn btn-ghost btn-sm" style="margin-left:auto">Sign out</a>
    <?php endif; ?>
</header>

<main class="main">

    <!-- Search card -->
    <div class="card">
        <div class="card-hdr">
            <span class="card-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                Search
            </span>
        </div>
        <div class="card-body">
            <form method="GET" id="searchForm">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="client_key">Client</label>
                        <select name="client_key" id="client_key">
                            <?php foreach(get_clients() as $client): ?>
                                <option value="<?=h($client['client_key'])?>" <?=isset($_GET['client_key'])&&$_GET['client_key']==$client['client_key']?'selected':''?>><?=h($client['client_name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="tag">Tags <span class="lbl-hint">(ctrl+click multi-select)</span></label>
                        <select name="tag[]" id="tag" multiple></select>
                    </div>
                    <div class="form-field">
                        <label for="key">Keys <span class="lbl-hint">(ctrl+click multi-select)</span></label>
                        <select name="key[]" id="key" multiple></select>
                    </div>
                    <div class="form-field">
                        <label for="after">After</label>
                        <div class="input-row">
                            <input type="datetime-local" step="1" name="after" id="after" value="<?=isset($_GET['after'])?h($_GET['after']):''?>">
                            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('after').value=''">Any</button>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="before">Before</label>
                        <div class="input-row">
                            <input type="datetime-local" step="1" name="before" id="before" value="<?=isset($_GET['before'])?h($_GET['before']):''?>">
                            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('before').value=''">Any</button>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="submit" value="1" class="btn btn-primary">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        Search
                    </button>
                    <a href="<?=h(strtok($_SERVER['REQUEST_URI'], '?'))?>" class="btn btn-ghost">Clear</a>
                    <button type="button" class="btn btn-ghost" onclick="exportCSV()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export CSV
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_GET['submit'])): ?>

    <!-- Results bar -->
    <div class="results-bar">
        <span>
            <strong><?=count($rows)?></strong> record<?=count($rows)!==1?'s':''?> found
            <?php if (!$show_all && count($rows) === $limit): ?>
                <span class="badge-warn">first <?=$limit?> shown</span>
            <?php endif; ?>
        </span>
        <?php if (!$show_all && count($rows) === $limit): ?>
            <form method="get">
                <?php foreach ($_GET as $k => $v): if ($k === 'show_all') continue; ?>
                    <?php if (is_array($v)): foreach ($v as $vv): ?>
                        <input type="hidden" name="<?=h($k)?>[]" value="<?=h($vv)?>">
                    <?php endforeach; else: ?>
                        <input type="hidden" name="<?=h($k)?>" value="<?=h($v)?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <button type="submit" name="show_all" value="1" class="btn btn-ghost btn-sm">Show all</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (count($rows)): ?>

    <!-- Row-level forms live outside the table (valid HTML, linked via form= attribute) -->
    <?php foreach ($rows as $row): ?>
    <form id="row-form-<?=h($row['value_id'])?>" method="POST"></form>
    <?php endforeach; ?>

    <script>window.EAPS_COLS = <?= json_encode(array_merge(array_keys($rows[0]), ['actions'])) ?>;</script>
    <div class="card">
        <div class="card-hdr">
            <span class="card-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="18"/><rect x="14" y="3" width="7" height="18"/></svg>
                Columns
            </span>
            <div class="col-toggles" id="col-toggles"></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all" title="Select / deselect all"></th>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                            <th data-col="<?=h($col)?>"><?=h($col)?></th>
                        <?php endforeach; ?>
                        <th data-col="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): $fid = 'row-form-' . intval($row['value_id']); ?>
                    <tr>
                        <td><input type="checkbox" class="row-cb" value="<?=h($row['value_id'])?>"></td>
                        <?php foreach ($row as $k => $v): ?>
                        <td data-col="<?=h($k)?>"<?=$k==='value_id'?' class="muted-cell"':''?>>
                            <?php if ($k === 'value'): ?>
                                <input type="text" name="edit[<?=h($row['value_id'])?>][value]" value="<?=h($v)?>" form="<?=h($fid)?>">
                            <?php elseif ($k === 'created'): ?>
                                <input type="datetime-local" step="1" name="edit[<?=h($row['value_id'])?>][created]" value="<?=date('Y-m-d\TH:i:s', is_numeric($v) ? $v : strtotime($v))?>" form="<?=h($fid)?>">
                            <?php else: ?>
                                <span class="mono"><?=h($v)?></span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td data-col="actions">
                            <div class="action-btns">
                                <button type="submit" name="save"   value="<?=h($row['value_id'])?>" form="<?=h($fid)?>" class="btn btn-ghost btn-sm">Save</button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteRow(<?=intval($row['value_id'])?>)">Del</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <div class="card"><p class="empty">No records found for this query.</p></div>
    <?php endif; ?>

    <?php endif; ?>

</main>

<!-- Floating bulk-action bar (appears when rows are checked) -->
<div class="bulk-bar" id="bulk-bar">
    <span class="bulk-count"><span id="sel-count">0</span> selected</span>
    <div class="bulk-sep"></div>
    <button class="btn btn-danger-solid btn-sm" onclick="deleteSelected()">Delete selected</button>
    <button class="btn btn-ghost btn-sm" onclick="clearSelection()">Clear</button>
</div>

<script>
// ── Dependent dropdowns ──────────────────────────────────────────────────────
function updateTags(selectedTags) {
    var ck = document.getElementById('client_key').value;
    fetch('?ajax=tags&client_key=' + encodeURIComponent(ck))
        .then(function(r) { return r.json(); })
        .then(function(tags) {
            var sel = document.getElementById('tag');
            var prev = new Set(selectedTags || []);
            sel.innerHTML = '';
            tags.forEach(function(t) {
                var o = document.createElement('option');
                o.value = t.tag_name;
                o.textContent = t.tag_name;
                o.selected = prev.has(t.tag_name);
                sel.appendChild(o);
            });
            updateKeys();
        });
}

function getSelected(id) {
    return Array.from(document.getElementById(id).selectedOptions).map(function(o) { return o.value; });
}

function updateKeys() {
    var tags = getSelected('tag');
    var ck = document.getElementById('client_key').value;
    var keySel = document.getElementById('key');
    var prevKeys = new Set(getSelected('key'));
    keySel.innerHTML = '';
    if (!tags.length) return;
    var p = new URLSearchParams({ ajax: 'keys', client_key: ck });
    tags.forEach(function(t) { p.append('tag[]', t); });
    fetch('?' + p.toString())
        .then(function(r) { return r.json(); })
        .then(function(keys) {
            keys.forEach(function(k) {
                var o = document.createElement('option');
                var pair = JSON.stringify({ tag: k.tag_name, key: k.key_name });
                o.value = pair;
                o.textContent = k.tag_name + ' › ' + k.key_name;
                o.selected = prevKeys.has(pair);
                keySel.appendChild(o);
            });
        });
}

function exportCSV() {
    var strip = confirm('Strip client ID from export?\nOK = remove it   |   Cancel = keep it');
    var p = new URLSearchParams(window.location.search);
    p.set('csv', 'true');
    if (strip) p.set('strip_client_id', 'on');
    window.location.href = '?' + p.toString();
}

// ── Multi-select ─────────────────────────────────────────────────────────────
var bulkBar  = document.getElementById('bulk-bar');
var selCount = document.getElementById('sel-count');
var selectAll = document.getElementById('select-all');

function updateBulkBar() {
    var cbs = document.querySelectorAll('.row-cb');
    var checked = document.querySelectorAll('.row-cb:checked');
    var n = checked.length;
    if (selCount) selCount.textContent = n;
    if (bulkBar)  bulkBar.classList.toggle('show', n > 0);
    if (selectAll) {
        selectAll.indeterminate = n > 0 && n < cbs.length;
        selectAll.checked = n > 0 && n === cbs.length;
    }
    cbs.forEach(function(cb) {
        cb.closest('tr').classList.toggle('row-selected', cb.checked);
    });
}

document.addEventListener('change', function(e) {
    if (e.target.id === 'select-all') {
        document.querySelectorAll('.row-cb').forEach(function(cb) { cb.checked = e.target.checked; });
        updateBulkBar();
    } else if (e.target.classList.contains('row-cb')) {
        updateBulkBar();
    }
});

function deleteRow(vid) {
    if (!confirm('Delete this record?')) return;
    var row = document.querySelector('.row-cb[value="' + vid + '"]').closest('tr');
    var fd = new FormData();
    fd.append('ajax_delete', vid);
    fetch(window.location.pathname, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d.ok) { row.remove(); updateBulkBar(); } });
}

function deleteSelected() {
    var checked = Array.from(document.querySelectorAll('.row-cb:checked'));
    if (!checked.length) return;
    if (!confirm('Permanently delete ' + checked.length + ' record' + (checked.length !== 1 ? 's' : '') + '?')) return;
    var fd = new FormData();
    checked.forEach(function(cb) { fd.append('ajax_delete_bulk[]', cb.value); });
    fetch(window.location.pathname, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                checked.forEach(function(cb) { cb.closest('tr').remove(); });
                updateBulkBar();
            }
        });
}

function clearSelection() {
    document.querySelectorAll('.row-cb').forEach(function(cb) { cb.checked = false; });
    if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
    updateBulkBar();
}

// ── Init dropdowns on page load ───────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', function() {
    var ckEl  = document.getElementById('client_key');
    var tagEl = document.getElementById('tag');
    if (!ckEl || !tagEl) return;

    var prevTags = <?= json_encode(isset($_GET['tag']) ? (is_array($_GET['tag']) ? array_values($_GET['tag']) : [$_GET['tag']]) : []) ?>;
    var prevKeys = <?= json_encode(isset($_GET['key']) ? (is_array($_GET['key']) ? array_values($_GET['key']) : [$_GET['key']]) : []) ?>;

    ckEl.addEventListener('change', function() { updateTags([]); });
    tagEl.addEventListener('change', updateKeys);

    updateTags(prevTags);
    setTimeout(function() {
        var keySel = document.getElementById('key');
        var prevSet = new Set(prevKeys);
        Array.from(keySel.options).forEach(function(o) { o.selected = prevSet.has(o.value); });
    }, 200);
});

// ── Column toggles ────────────────────────────────────────────────────────────
(function() {
    var COLS = window.EAPS_COLS || [];
    if (!COLS.length) return;
    var DEFAULT_ON = new Set(['key', 'value', 'created', 'actions']);
    var STORAGE_KEY = 'eaps_cols';
    var LABELS = { created: 'timestamp' };

    function loadState() {
        try {
            var s = JSON.parse(localStorage.getItem(STORAGE_KEY));
            if (s && typeof s === 'object') return s;
        } catch(e) {}
        var s = {};
        COLS.forEach(function(c) { s[c] = DEFAULT_ON.has(c); });
        return s;
    }

    function applyState(state) {
        COLS.forEach(function(c) {
            var hide = !state[c];
            document.querySelectorAll('[data-col="' + c + '"]').forEach(function(el) {
                el.classList.toggle('col-hidden', hide);
            });
            var btn = document.querySelector('[data-toggle-col="' + c + '"]');
            if (btn) btn.classList.toggle('active', !!state[c]);
        });
    }

    var container = document.getElementById('col-toggles');
    if (!container) return;
    var state = loadState();
    COLS.forEach(function(c) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm col-toggle-btn';
        btn.dataset.toggleCol = c;
        btn.textContent = LABELS[c] || c;
        btn.addEventListener('click', function() {
            state[c] = !state[c];
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
            applyState(state);
        });
        container.appendChild(btn);
    });
    applyState(state);
})();
</script>
</body>
</html>
