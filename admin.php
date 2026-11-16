<?php
require __DIR__ . '/config.php';

$forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https' || setting('FORCE_HTTPS') === '1';
session_set_cookie_params([
    'httponly' => true,
    'samesite' => $isHttps ? 'None' : 'Lax',
    'secure' => $isHttps,
]);
session_start();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$formatBytes = static function (int $bytes): string {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    return number_format(max(1, $bytes / 1024), 0) . ' KB';
};
$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'admin.php'));
$dashboardPath = in_array($requestPath, ['/admin', '/admin/'], true)
    ? '/admin'
    : (in_array($scriptName, ['admin.php', 'dashboard.php'], true) ? '/' . $scriptName : '/admin');

$pageSize = 50;
$pageRaw = $_GET['page'] ?? 1;
$page = filter_var(is_scalar($pageRaw) ? $pageRaw : 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$filterErrors = [];
$emailRaw = $_GET['email'] ?? '';
$filterEmail = is_string($emailRaw) ? trim(substr($emailRaw, 0, 190)) : '';
$parseFilterDate = static function ($value): ?string {
    if (!is_string($value)) return null;
    if ($value === '') return '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) return null;
    return $date->format('Y-m-d') === $value ? $value : null;
};
$fromRaw = $_GET['date_from'] ?? '';
$toRaw = $_GET['date_to'] ?? '';
$filterFrom = $parseFilterDate($fromRaw);
$filterTo = $parseFilterDate($toRaw);
if ($filterFrom === null) { $filterErrors[] = 'Enter a valid start date.'; $filterFrom = ''; }
if ($filterTo === null) { $filterErrors[] = 'Enter a valid end date.'; $filterTo = ''; }
if ($filterFrom !== '' && $filterTo !== '' && $filterFrom > $filterTo) {
    $filterErrors[] = 'The start date must be on or before the end date.';
    $filterFrom = $filterTo = '';
}
$filterParams = [];
if ($filterEmail !== '') $filterParams['email'] = $filterEmail;
if ($filterFrom !== '') $filterParams['date_from'] = $filterFrom;
if ($filterTo !== '') $filterParams['date_to'] = $filterTo;
$pageUrl = static function (int $targetPage) use ($dashboardPath, $filterParams): string {
    $params = $filterParams;
    if ($targetPage > 1) $params['page'] = $targetPage;
    $query = http_build_query($params);
    return $dashboardPath . ($query !== '' ? '?' . $query : '');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'logout') {
    if (empty($_SESSION['ok']) || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request.');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    header('Location: ' . $dashboardPath);
    exit;
}

$error = '';
if (empty($_SESSION['ok'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        usleep(400000);
        $submittedPassword = (string)$_POST['password'];
        $validHash = ADMIN_HASH !== 'PASTE_PASSWORD_HASH_HERE' && password_verify($submittedPassword, ADMIN_HASH);
        $validSecret = ADMIN_PASSWORD !== '' && hash_equals(ADMIN_PASSWORD, $submittedPassword);
        if ($validHash || $validSecret) {
            session_regenerate_id(true);
            $_SESSION['ok'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: ' . $dashboardPath);
            exit;
        }
        $error = 'Wrong password.';
    }
    ?><!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin login</title>
    <body style="font-family:system-ui;background:#06122a;color:#eaf1ff;display:grid;place-items:center;min-height:100vh;margin:0">
    <form method="post" style="background:#0f2449;padding:32px;border-radius:16px;width:min(340px,90vw)">
    <h2 style="margin-top:0">Admin login</h2>
    <input type="password" name="password" placeholder="Password" required autofocus style="width:100%;padding:12px;border-radius:10px;border:1px solid #1f3a6b;margin-bottom:14px;box-sizing:border-box">
    <button style="width:100%;padding:12px;border:0;border-radius:10px;background:#38e8c6;font-weight:700;cursor:pointer">Log in</button>
    <p style="color:#ff8a8a"><?= $h($error) ?></p></form></body><?php
    exit;
}

$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['act'] ?? '') === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]);
    } elseif (($_POST['act'] ?? '') === 'read' && $id > 0) {
        $pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = ?')->execute([$id]);
    } else {
        http_response_code(400);
        exit('Invalid action.');
    }
    header('Location: ' . $pageUrl($page));
    exit;
}

$messageTotal = (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
$unread = (int)$pdo->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();
$todayTotal = (int)$pdo->query('SELECT COUNT(*) FROM messages WHERE DATE(created_at) = CURRENT_DATE')->fetchColumn();
$conditions = [];
$queryParams = [];
if ($filterEmail !== '') {
    $conditions[] = "m.email LIKE :email ESCAPE '!'";
    $escapedEmail = strtr($filterEmail, ['!' => '!!', '%' => '!%', '_' => '!_']);
    $queryParams['email'] = '%' . $escapedEmail . '%';
}
if ($filterFrom !== '') {
    $conditions[] = 'm.created_at >= :date_from';
    $queryParams['date_from'] = $filterFrom . ' 00:00:00';
}
if ($filterTo !== '') {
    $conditions[] = 'm.created_at <= :date_to';
    $queryParams['date_to'] = $filterTo . ' 23:59:59';
}
$filterSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$filteredCountQuery = $pdo->prepare('SELECT COUNT(*) FROM messages m' . $filterSql);
$filteredCountQuery->execute($queryParams);
$filteredTotal = (int)$filteredCountQuery->fetchColumn();
$filteredUnreadQuery = $pdo->prepare('SELECT COUNT(*) FROM messages m WHERE m.is_read = 0' . ($conditions ? ' AND ' . implode(' AND ', $conditions) : ''));
$filteredUnreadQuery->execute($queryParams);
$filteredUnread = (int)$filteredUnreadQuery->fetchColumn();
$totalPages = max(1, (int)ceil($filteredTotal / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$firstMessage = $filteredTotal ? $offset + 1 : 0;
$lastMessage = min($offset + $pageSize, $filteredTotal);
$rowsQuery = $pdo->prepare('SELECT m.*, (SELECT COUNT(*) FROM message_files f WHERE f.message_id = m.id) AS file_count FROM messages m' . $filterSql . ' ORDER BY m.created_at DESC, m.id DESC LIMIT :limit OFFSET :offset');
foreach ($queryParams as $name => $value) $rowsQuery->bindValue(':' . $name, $value, PDO::PARAM_STR);
$rowsQuery->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$rowsQuery->bindValue(':offset', $offset, PDO::PARAM_INT);
$rowsQuery->execute();
$rows = $rowsQuery->fetchAll();
$fileQuery = $pdo->prepare('SELECT id, original_name, file_size FROM message_files WHERE message_id = ? ORDER BY id');
?><!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Private message dashboard</title><style>
*{box-sizing:border-box}
a{overflow-wrap:anywhere}
button{min-height:44px}
.dashboard-title small{color:#38e8c6;font-size:.65em}
.message-card{overflow-wrap:anywhere}
.message-actions{display:flex;flex-wrap:wrap;gap:10px}
.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:18px 0}
.stat-card{background:#0f2449;border:1px solid #1f3a6b;border-radius:12px;padding:14px;min-width:0}
.stat-card span{display:block;color:#9db2d6;font-size:.85rem}
.stat-card strong{display:block;color:#38e8c6;font-size:1.5rem;margin-top:4px}
.filter-form{display:grid;grid-template-columns:minmax(160px,2fr) repeat(2,minmax(140px,1fr)) auto auto;gap:10px;align-items:end;background:#0f2449;border:1px solid #1f3a6b;border-radius:14px;padding:14px;margin:18px 0}
.filter-form label{display:grid;gap:6px;color:#b8c8e4;font-size:.9rem;min-width:0}
.filter-form input{width:100%;min-width:0;min-height:44px;padding:9px 11px;background:#06122a;color:#eaf1ff;border:1px solid #38598a;border-radius:9px;font:inherit}
.filter-form button,.filter-form a{min-height:44px;display:inline-flex;align-items:center;justify-content:center;padding:9px 12px;border-radius:9px;font:inherit;font-weight:700;white-space:nowrap}
.filter-form button{background:#38e8c6;color:#06122a;border:0;cursor:pointer}
.filter-form a{color:#9db2d6;border:1px solid #38598a;text-decoration:none}
@media(max-width:720px){.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.filter-form{grid-template-columns:1fr 1fr}.filter-form label:first-child{grid-column:1/-1}}
@media(max-width:560px){.dashboard-title small{display:block;margin-top:4px}}
.delete-dialog{position:fixed;inset:0;z-index:100;display:grid;place-items:center;padding:20px;background:rgba(3,10,25,.78);animation:deleteFade .18s ease both}
.delete-dialog[hidden]{display:none}
.delete-dialog__panel{width:min(440px,100%);background:#102953;border:1px solid #38e8c6;border-radius:18px;padding:24px;box-shadow:0 24px 70px rgba(0,0,0,.45);animation:deletePop .22s cubic-bezier(.2,.75,.2,1) both}
.delete-dialog__panel h2{margin:0 0 10px;font-size:1.25rem}
.delete-dialog__panel p{margin:0;color:#b8c8e4;line-height:1.55}
.delete-dialog__actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}
.delete-dialog__actions button{border:1px solid #38598a;border-radius:10px;padding:10px 18px;font:inherit;font-weight:700;cursor:pointer}
.delete-dialog__cancel{background:#183563;color:#eaf1ff}
.delete-dialog__ok{background:#ff6f7d;border-color:#ff6f7d;color:#210910}
.delete-dialog__actions button:focus{outline:3px solid #38e8c6;outline-offset:2px}
@keyframes deleteFade{from{opacity:0}to{opacity:1}}
@keyframes deletePop{from{opacity:0;transform:translateY(8px) scale(.98)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){.delete-dialog,.delete-dialog__panel{animation:none}}
</style>
<body style="font-family:system-ui;background:#06122a;color:#eaf1ff;margin:0;padding:clamp(12px,4vw,24px)">
<div style="max-width:900px;margin:auto">
<h1 class="dashboard-title">Private message dashboard</h1>
<p style="color:#9db2d6">Owner-only dashboard · submitted visitor messages are stored securely.</p>
<form method="post" action="<?= $h($dashboardPath) ?>" style="margin:0 0 18px"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><button name="act" value="logout" style="color:#9db2d6;background:transparent;border:1px solid #38598a;border-radius:10px;padding:8px 14px">Log out</button></form>
<section class="stats-grid" aria-label="Message statistics">
<div class="stat-card"><span>All messages</span><strong><?= $messageTotal ?></strong></div>
<div class="stat-card"><span>Unread</span><strong><?= $unread ?></strong></div>
<div class="stat-card"><span>Received today</span><strong><?= $todayTotal ?></strong></div>
<div class="stat-card"><span>Matching filters</span><strong><?= $filteredTotal ?></strong></div>
</section>
<form class="filter-form" method="get" action="<?= $h($dashboardPath) ?>">
<label>Email contains<input type="search" name="email" value="<?= $h($filterEmail) ?>" maxlength="190" placeholder="Search by email address"></label>
<label>From date<input type="date" name="date_from" value="<?= $h($filterFrom) ?>"></label>
<label>To date<input type="date" name="date_to" value="<?= $h($filterTo) ?>"></label>
<button type="submit">Apply filters</button><a href="<?= $h($dashboardPath) ?>">Clear</a>
</form>
<?php if ($filterErrors): ?><p role="alert" style="color:#ffb3b8"><?= $h(implode(' ', $filterErrors)) ?></p><?php endif; ?>
<p style="color:#9db2d6">Messages <?= $firstMessage ?>–<?= $lastMessage ?> of <?= $filteredTotal ?> matching results · <?= $filteredUnread ?> unread matching filters.</p>
<?php if (!$rows): ?><p><?= $filterParams ? 'No messages match these filters.' : 'No messages yet.' ?></p><?php endif; ?>
<?php foreach ($rows as $row): ?>
<div class="message-card" style="background:#0f2449;border:1px solid <?= $row['is_read'] ? '#1f3a6b' : '#38e8c6' ?>;border-radius:14px;padding:18px;margin-bottom:14px">
<b><?= $h($row['name']) ?></b> · <a style="color:#38e8c6" href="mailto:<?= $h($row['email']) ?>"><?= $h($row['email']) ?></a>
<div style="color:#9db2d6;font-size:.85rem"><?= $h($row['created_at']) ?> · IP <?= $h($row['ip']) ?></div>
<p style="white-space:pre-wrap"><?= $h($row['message']) ?></p>
<?php $fileQuery->execute([(int)$row['id']]); $files = $fileQuery->fetchAll(); ?>
<?php if ($files): ?><div style="border-top:1px solid #1f3a6b;padding-top:12px;margin-top:12px"><b>Project detail files (<?= count($files) ?>)</b><ul style="margin:8px 0;padding-left:20px"><?php foreach ($files as $file): ?><li><a style="color:#38e8c6" href="download.php?id=<?= (int)$file['id'] ?>"><?= $h($file['original_name']) ?></a> <small style="color:#9db2d6">(<?= $h($formatBytes((int)$file['file_size'])) ?>)</small></li><?php endforeach; ?></ul></div><?php elseif ((int)$row['project_details']): ?><p style="color:#9db2d6;font-size:.9rem">Visitor requested project-detail contact but did not attach files.</p><?php endif; ?>
<form class="message-actions" method="post" action="<?= $h($pageUrl($page)) ?>"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<?php if (!$row['is_read']): ?><button name="act" value="read">Mark as read</button><?php endif; ?>
<button type="button" data-delete-trigger>Delete</button></form>
</div><?php endforeach; ?>
<?php if ($totalPages > 1): ?><nav aria-label="Message pages" style="display:flex;justify-content:space-between;gap:12px;margin:20px 0"><span><?php if ($page > 1): ?><a href="<?= $h($pageUrl($page - 1)) ?>" style="color:#38e8c6">← Newer messages</a><?php endif; ?></span><span>Page <?= $page ?> of <?= $totalPages ?></span><span><?php if ($page < $totalPages): ?><a href="<?= $h($pageUrl($page + 1)) ?>" style="color:#38e8c6">Older messages →</a><?php endif; ?></span></nav><?php endif; ?>
<p style="color:#9db2d6;font-size:.9rem">This dashboard checks for new visitor messages every 30 seconds. <a href="<?= $h($pageUrl($page)) ?>" style="color:#38e8c6">Refresh now</a></p>
</div>
<div class="delete-dialog" id="deleteDialog" role="dialog" aria-modal="true" aria-labelledby="deleteDialogTitle" hidden>
<div class="delete-dialog__panel"><h2 id="deleteDialogTitle">Delete this message?</h2><p>This action permanently removes the visitor message and its uploaded project files. Do you want to continue?</p><div class="delete-dialog__actions"><button type="button" class="delete-dialog__cancel" id="deleteCancel">CANCEL</button><button type="button" class="delete-dialog__ok" id="deleteOk">OK</button></div></div>
</div>
<script>
window.setTimeout(function(){window.location.reload();},30000);
(function(){var dialog=document.getElementById("deleteDialog"),ok=document.getElementById("deleteOk"),cancel=document.getElementById("deleteCancel"),pendingForm=null,previousFocus=null;
function closeDialog(){dialog.hidden=true;pendingForm=null;if(previousFocus)previousFocus.focus()}
document.querySelectorAll("[data-delete-trigger]").forEach(function(button){button.addEventListener("click",function(){pendingForm=button.form;previousFocus=button;dialog.hidden=false;cancel.focus()})});
cancel.addEventListener("click",closeDialog);
dialog.addEventListener("click",function(event){if(event.target===dialog)closeDialog()});
document.addEventListener("keydown",function(event){if(!dialog.hidden&&event.key==="Escape"){event.preventDefault();closeDialog()}});
ok.addEventListener("click",function(){if(!pendingForm)return;var form=pendingForm;pendingForm=null;var action=document.createElement("input");action.type="hidden";action.name="act";action.value="delete";form.appendChild(action);form.submit()});})();
</script></body>
