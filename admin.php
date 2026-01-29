<?php
require_once("config.php");
require_once("lib/Argh/src/Argh.php");
require_once("functions.php");

use \xionic\Argh\Argh;

// Helper: escape output
function h($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }

// Handle CSV export
if (isset($_GET["csv"])) {
    header('Content-type: text/csv');
    header('Content-disposition: attachment;filename=' . (isset($_GET['client_key']) ? $_GET['client_key'] : 'export') . ".csv");
    if (count($rows)) {
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            $row['created'] = (!isset($_GET['timestamp']) && isset($row['created'])) ? date('Y/m/d H:i:s', $row['created']) : $row['created'];
            fputcsv($out, $row);
        }
        fclose($out);
    }
    exit;
}

// Handle AJAX requests for dependent dropdowns
if (isset($_GET['ajax']) && $_GET['ajax'] === 'tags' && isset($_GET['client_key'])) {
    header('Content-Type: application/json');
    echo json_encode(get_tags($_GET['client_key']));
    exit;
}
if (isset($_GET['ajax']) && $_GET['ajax'] === 'keys' && isset($_GET['tag']) && isset($_GET['client_key'])) {
    header('Content-Type: application/json');
    echo json_encode(get_keys($_GET['tag'], $_GET['client_key']));
    exit;
}

// Handle search
$rows = [];
$limit = 200;
$show_all = isset($_GET['show_all']);
if (isset($_GET['submit'])) {
    
    $args = $_GET;
	Argh::validate($args, [
        "client_key" => ["optional", "string"],
        "tag" => ["optional", "string"],
        "key" => ["optional", "string"],
        "since" => ["optional"]
    ]);
    $params = [];
    $where = [];
    if (!empty($args["client_key"])) {
        $where[] = "tClient.client_key = :client_key";
        $params[":client_key"] = $args["client_key"];
    }
    if (!empty($args["tag"])) {
        $where[] = "tTag.tag_name = :tag_name";
        $params[":tag_name"] = $args["tag"];
    }
    if (!empty($args["key"])) {
        $where[] = "tKey.key_name = :key_name";
        $params[":key_name"] = $args["key"];
    }
    if (!empty($args["since"])) {
        $where[] = "tValue.created > :created";
        $params[":created"] = strtotime($args["since"]);
    }
    $where_sql = $where ? " AND " . implode(" AND ", $where) : "";
    $sql = "SELECT client_name, client_key, tag_name as tag, key_name as `key`, value_id, value_data as `value`, `created` FROM tClient INNER JOIN tTag ON (tClient.client_id = tTag.client_id) INNER JOIN tKey ON (tTag.tag_id = tKey.tag_id) INNER JOIN tValue ON (tKey.key_id = tValue.key_id) WHERE tClient.client_id = tClient.client_id $where_sql ORDER BY tValue.created DESC";
    if (!$show_all) {
        $sql .= " LIMIT $limit";
    }
    try {
        $db = get_db_connection();
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Database error: " . h($e->getMessage()));
    }
}

?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>EAPS Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2em; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #333; padding: 8px; }
        th { background: #eee; }
        select, input[type=datetime-local] { padding: 4px; margin: 4px 0; }
        .form-row { margin-bottom: 1em; }
    </style>
    <script>
    // AJAX for dependent dropdowns
    function updateTags(selectedTag) {
        var clientKey = document.getElementById('client_key').value;
        fetch('?ajax=tags&client_key=' + encodeURIComponent(clientKey))
            .then(r => r.json())
            .then(tags => {
                var tagSel = document.getElementById('tag');
                tagSel.innerHTML = '';
                tags.forEach(tag => {
                    var opt = document.createElement('option');
                    opt.value = tag.tag_name;
                    opt.textContent = tag.tag_name;
                    if(selectedTag && selectedTag === tag.tag_name) opt.selected = true;
                    tagSel.appendChild(opt);
                });
                // If a tag is selected, update keys with that tag
                var tagToSelect = selectedTag || (tags.length > 0 ? tags[0].tag_name : '');
                updateKeys(document.getElementById('key').getAttribute('data-selected'));
            });
    }
    function updateKeys(selectedKey) {
        var tagName = document.getElementById('tag').value;
        var clientKey = document.getElementById('client_key').value;
        fetch('?ajax=keys&tag=' + encodeURIComponent(tagName) + '&client_key=' + encodeURIComponent(clientKey))
            .then(r => r.json())
            .then(keys => {
                var keySel = document.getElementById('key');
                keySel.innerHTML = '';
                keys.forEach(k => {
                    var opt = document.createElement('option');
                    opt.value = k.key_name;
                    opt.textContent = k.key_name;
                    if(selectedKey && selectedKey === k.key_name) opt.selected = true;
                    keySel.appendChild(opt);
                });
            });
    }
    window.addEventListener('DOMContentLoaded', function() {
        var selectedTag = "<?=isset($_GET['tag']) ? h($_GET['tag']) : ''?>";
        var selectedKey = "<?=isset($_GET['key']) ? h($_GET['key']) : ''?>";
        document.getElementById('key').setAttribute('data-selected', selectedKey);
        document.getElementById('client_key').addEventListener('change', function() { updateTags(); });
        document.getElementById('tag').addEventListener('change', function() { updateKeys(); });
        updateTags(selectedTag);
        setTimeout(function() { updateKeys(selectedKey); }, 300);
    });
    </script>
</head>
<body>
    <h1>EAPS Admin</h1>
    <form method="GET" id="searchForm">
        <div class="form-row">
            <label for="client_key">Client name:</label>
            <select name="client_key" id="client_key">
                <?php foreach(get_clients() as $client): ?>
                    <option value="<?=h($client['client_key'])?>" <?=isset($_GET['client_key'])&&$_GET['client_key']==$client['client_key']?'selected':''?>><?=h($client['client_name'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label for="tag">Tag:</label>
            <select name="tag" id="tag"></select>
        </div>
        <div class="form-row">
            <label for="key">Key:</label>
            <select name="key" id="key"></select>
        </div>
        <div class="form-row">
            <label for="since">Since:</label>
            <input type="datetime-local" name="since" id="since" value="<?=isset($_GET['since'])?h($_GET['since']):''?>">
        </div>
        <input name="submit" type="submit" value="Search">
        <a href="?<?=http_build_query(array_merge($_GET, ['csv'=>'true']))?>">Export as CSV</a>
    </form>
    <?php if (isset($_GET['submit'])): ?>
        <div><?=count($rows)?> records found<?php if (!$show_all && count($rows) === $limit): ?> (showing first <?=$limit?>)
            <form method="get" style="display:inline">
                <?php foreach ($_GET as $k => $v): if ($k !== 'show_all') { ?>
                    <input type="hidden" name="<?=h($k)?>" value="<?=h($v)?>">
                <?php } endforeach; ?>
                <button type="submit" name="show_all" value="1">Show All</button>
            </form>
        <?php endif; ?></div>
        <table>
            <thead>
                <tr>
                    <?php foreach ($rows[0] ?? [] as $col => $v): ?>
                        <th><?=h($col)?></th>
                    <?php endforeach; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <form method="POST" class="inline-edit-form">
                        <?php foreach ($row as $k => $v): ?>
                            <td>
                                <?php if ($k === 'value'): ?>
                                    <input type="text" name="edit[<?=h($row['value_id'])?>][value]" value="<?=h($v)?>">
                                <?php elseif ($k === 'created'): ?>
                                    <input type="datetime-local" name="edit[<?=h($row['value_id'])?>][created]" value="<?=date('Y-m-d\TH:i', is_numeric($v) ? $v : strtotime($v))?>">
                                <?php else: ?>
                                    <?=h($v)?></td>
                                <?php endif; ?>
                        <?php endforeach; ?>
                        <td>
                            <button type="submit" name="save" value="<?=h($row['value_id'])?>">Save</button>
                            <button type="submit" name="delete" value="<?=h($row['value_id'])?>" onclick="return confirm('Delete this record?')">Delete</button>
                        </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
<?php
// Handle edit and delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once("functions.php");
    $db = get_db_connection();
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
        $fields = $_POST['edit'][$value_id];
        if (isset($fields['value'])) {
            $stmt = $db->prepare("UPDATE tValue SET value_data = :value WHERE value_id = :value_id");
            $stmt->bindValue(":value", $fields['value'], PDO::PARAM_STR);
            $stmt->bindValue(":value_id", $value_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        if (isset($fields['created'])) {
            $created = strtotime($fields['created']);
            if ($created) {
                $stmt = $db->prepare("UPDATE tValue SET created = :created WHERE value_id = :value_id");
                $stmt->bindValue(":created", $created, PDO::PARAM_INT);
                $stmt->bindValue(":value_id", $value_id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
        // Use a redirect with header('Location: ...') and then exit to ensure a true POST-redirect-GET
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . '?' . $_SERVER['QUERY_STRING']);
        exit;
    }
}

// CSV export logic (refactored)
if (isset($_GET["csv"])) {
    header('Content-type: text/csv');
    header('Content-disposition: attachment;filename=' . (isset($_GET['client_key']) ? $_GET['client_key'] : 'export') . ".csv");
    if (count($rows)) {
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            $row['created'] = (!isset($_GET['timestamp']) && isset($row['created'])) ? date('Y/m/d H:i:s', $row['created']) : $row['created'];
            fputcsv($out, $row);
        }
        fclose($out);
    }
    exit;
}
?>