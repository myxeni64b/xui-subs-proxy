<?php
$basePatterns = [
    'http://YourDomain.com/user/%s'
];

function extractIds($input)
{
    $results = [];
    preg_match_all('~([a-zA-Z0-9]{8,})~', $input, $matches);

    if (!empty($matches[1])) {
        foreach ($matches[1] as $m) {
            $results[$m] = true;
        }
    }
    return array_keys($results);
}

function generateLinks($id, $patterns)
{
    $out = [];
    foreach ($patterns as $pattern) {
        $out[] = sprintf($pattern, $id);
    }
    return $out;
}

$input = isset($_POST['data']) ? $_POST['data'] : '';
$ids = $input ? extractIds($input) : [];

header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Sub Link Generator</title>

<style>
body {
    background: #0f172a;
    color: #e5e7eb;
    font-family: monospace;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 800px;
    margin: auto;
}

h1 {
    color: #38bdf8;
}

textarea {
    width: 100%;
    height: 120px;
    background: #020617;
    color: #e5e7eb;
    border: 1px solid #1e293b;
    padding: 10px;
    border-radius: 6px;
}

button {
    background: #38bdf8;
    color: #020617;
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    margin: 4px 4px 0 0;
}

button:hover {
    background: #0ea5e9;
}

.result {
    margin-top: 20px;
}

.block {
    background: #020617;
    border: 1px solid #1e293b;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 16px;
}

.id {
    color: #22c55e;
    font-weight: bold;
}

.link-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-left: 10px;
}

.link {
    color: #38bdf8;
    word-break: break-all;
}

.copy-btn {
    font-size: 12px;
    padding: 4px 8px;
    background: #1e293b;
    color: #e5e7eb;
}

.top-actions {
    margin-top: 10px;
}
</style>

<script>
function copyText(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
    } else {
        var temp = document.createElement("textarea");
        temp.value = text;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand("copy");
        document.body.removeChild(temp);
    }
}

function copyAll() {
    let all = [];
    document.querySelectorAll('.link').forEach(el => {
        all.push(el.innerText);
    });
    copyText(all.join("\n"));
}

function copyGroup(id) {
    let group = [];
    document.querySelectorAll('[data-group="'+id+'"]').forEach(el => {
        group.push(el.innerText);
    });
    copyText(group.join("\n"));
}
</script>
</head>

<body>
<div class="container">

<h1>Sub Link Generator</h1>

<form method="post">
    <textarea name="data" placeholder="Paste IDs or links here..."><?php echo htmlspecialchars($input); ?></textarea>
    <br>
    <button type="submit">Generate</button>
</form>

<?php if (!empty($ids)): ?>
<div class="top-actions">
    <button onclick="copyAll()">Copy ALL</button>
</div>
<?php endif; ?>

<div class="result">

<?php foreach ($ids as $id): ?>
    <div class="block">
        <div class="id">
            <?php echo htmlspecialchars($id); ?>
            <button class="copy-btn" onclick="copyGroup('<?php echo $id; ?>')">Copy ID Links</button>
        </div>

        <?php foreach (generateLinks($id, $basePatterns) as $link): ?>
            <div class="link-row">
                <span class="link" data-group="<?php echo $id; ?>">
                    <?php echo htmlspecialchars($link); ?>
                </span>
                <button class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($link); ?>')">
                    Copy
                </button>
            </div>
        <?php endforeach; ?>

    </div>
<?php endforeach; ?>

</div>
</div>
</body>
</html>
