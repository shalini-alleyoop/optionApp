<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/connect.php';
start_session_once();
require_https();
redirect_if_no_shopify_context();

$shop = $_GET['shop'] ?? ($_SESSION['shop'] ?? '');
if (!$shop) { http_response_code(401); exit('No shop context.'); }
if (empty($_SESSION['engraving_csrf'])) $_SESSION['engraving_csrf'] = bin2hex(random_bytes(32));

function clean_engraving_html(string $html): string {
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><span>');
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    return preg_replace('/href\s*=\s*(["\'])\s*(javascript|data):.*?\1/i', 'href="#"', trim($html));
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['engraving_csrf'], $_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Invalid request token.'); }
    $title = trim((string)($_POST['title'] ?? ''));
    $html = clean_engraving_html((string)($_POST['content_html'] ?? ''));
    if ($title === '' || $html === '') {
        $message = 'Title and instructions are required.';
    } else {
        $safeShop = $admin->escape($shop); $safeTitle = $admin->escape($title); $safeHtml = $admin->escape($html);
        $admin->query("INSERT INTO engraving_instructions (shop_domain,title,content_html) VALUES ('$safeShop','$safeTitle','$safeHtml') ON DUPLICATE KEY UPDATE title=VALUES(title),content_html=VALUES(content_html)");
        $message = 'Engraving instructions saved.';
    }
}
$settings = $admin->get_engraving_instructions($shop);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Engraving Instructions Editor</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:32px 18px;background:#f5f6f8;color:#202124;font-family:Arial,sans-serif}.page{max-width:1050px;margin:auto}.card{background:#fff;border:1px solid #dfe3e8;border-radius:9px;box-shadow:0 2px 8px #0000000d;overflow:hidden}.header,.field,.actions{padding:20px 24px}.header{border-bottom:1px solid #e5e7eb}.header h1{margin:0 0 6px;font-size:24px}.muted{margin:0;color:#687078}.message{margin:18px 24px 0;padding:12px;background:#e8f5e9;border:1px solid #9ccc9c;border-radius:6px;color:#245b28}.field label{display:block;margin-bottom:7px;font-weight:600}.field input{width:100%;padding:10px 12px;border:1px solid #bbc1c7;border-radius:5px;font:inherit}.tabs{display:flex;gap:4px;padding:0 24px}.tab{padding:9px 15px;border:1px solid #c9ced4;border-radius:6px 6px 0 0;background:#f3f4f6;cursor:pointer}.tab.active{background:#1976d2;border-color:#1976d2;color:#fff}.editor-shell{margin:0 24px;border:1px solid #c9ced4}.toolbar{display:flex;flex-wrap:wrap;gap:5px;padding:8px;background:#f7f8fa;border-bottom:1px solid #d9dde2}.toolbar button{padding:7px 10px;border:1px solid #c4c9cf;border-radius:4px;background:#fff;cursor:pointer}#visualEditor{min-height:300px;padding:16px;outline:none;line-height:1.55}#sourceEditor{display:none;width:100%;min-height:340px;padding:16px;border:0;resize:vertical;outline:none;font:14px/1.5 Consolas,monospace}.actions{display:flex;gap:10px}.button{display:inline-block;padding:11px 17px;border:0;border-radius:5px;font-weight:600;text-decoration:none;cursor:pointer}.primary{background:#1976d2;color:#fff}.secondary{background:#e8eaed;color:#202124}@media(max-width:600px){body{padding:12px 6px}.header,.field,.actions,.tabs{padding-left:12px;padding-right:12px}.editor-shell{margin:0 12px}}
</style></head><body><main class="page"><form method="post" class="card" id="editorForm">
<header class="header"><h1>Engraving Instructions</h1><p class="muted">Use the visual editor or edit the HTML source.</p></header>
<?php if ($message): ?><div class="message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['engraving_csrf'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="content_html" id="contentHtml">
<div class="field"><label for="instructionTitle">Section title</label><input id="instructionTitle" name="title" value="<?= htmlspecialchars($settings['title'], ENT_QUOTES, 'UTF-8') ?>" required></div>
<div class="tabs"><button class="tab active" id="visualTab" type="button">Visual Editor</button><button class="tab" id="sourceTab" type="button">HTML Source</button></div>
<div class="editor-shell"><div class="toolbar" id="toolbar"><button type="button" data-command="bold"><b>B</b></button><button type="button" data-command="italic"><i>I</i></button><button type="button" data-command="underline"><u>U</u></button><button type="button" data-command="insertUnorderedList">• List</button><button type="button" data-command="insertOrderedList">1. List</button><button type="button" data-command="createLink">Link</button><button type="button" data-command="unlink">Unlink</button><button type="button" data-command="removeFormat">Clear</button></div><div id="visualEditor" contenteditable="true"><?= $settings['content_html'] ?></div><textarea id="sourceEditor" spellcheck="false"></textarea></div>
<div class="actions"><button class="button primary" type="submit">Save instructions</button><a class="button secondary" href="dashboard.php?shop=<?= urlencode($shop) ?>">Back</a></div>
</form></main><script>
(function(){const form=document.querySelector('#editorForm'),visual=document.querySelector('#visualEditor'),source=document.querySelector('#sourceEditor'),toolbar=document.querySelector('#toolbar'),visualTab=document.querySelector('#visualTab'),sourceTab=document.querySelector('#sourceTab');let mode='visual';function change(next){if(next===mode)return;if(next==='source')source.value=visual.innerHTML;else visual.innerHTML=source.value;mode=next;visual.style.display=mode==='visual'?'block':'none';source.style.display=mode==='source'?'block':'none';toolbar.style.display=mode==='visual'?'flex':'none';visualTab.classList.toggle('active',mode==='visual');sourceTab.classList.toggle('active',mode==='source')}visualTab.onclick=()=>change('visual');sourceTab.onclick=()=>change('source');toolbar.onclick=e=>{const b=e.target.closest('[data-command]');if(!b)return;visual.focus();let value=null;if(b.dataset.command==='createLink'){value=prompt('Enter link URL');if(!value)return}document.execCommand(b.dataset.command,false,value)};form.onsubmit=()=>document.querySelector('#contentHtml').value=mode==='visual'?visual.innerHTML:source.value;source.value=visual.innerHTML})();
</script></body></html>
