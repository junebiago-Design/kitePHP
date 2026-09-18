<!-- View: DbCodeGeneratorController | Generated from view.php.kite -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DbCodeGenerator | <?= htmlspecialchars($projectName) ?></title>
    <?= $this->assets('css'); ?>
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?= htmlspecialchars($baseUrl) ?>/"><?= htmlspecialchars($projectName) ?></a>
    </div>
</nav>

<main class="flex-grow-1">
    <div class="container-fluid p-4">
        <div class="container mt-3">

            <h1 class="mb-3">Generate Connection Code</h1>

            <p class="text-muted">
                Pick one of your SQLite databases below and generate ready-to-paste PHP code
                for connecting to it (via PDO) and reading/writing each of its tables from
                any other script or project.
            </p>

            <div id="genAlert" class="alert d-none" role="alert"></div>

            <div class="row g-2 align-items-end mb-3">
                <div class="col-sm-6 col-md-4">
                    <label class="form-label">Database</label>
                    <select id="genDbSelect" class="form-select">
                        <option value="">-- select database --</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-primary" onclick="generateCode()">Generate Code</button>
                </div>
            </div>

            <div id="genCodeWrap" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Connection code</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyGeneratedCode()">
                        Copy to clipboard
                    </button>
                </div>
                <pre class="bg-dark text-light p-3 rounded" style="max-height:600px; overflow:auto;"><code id="genCodeOutput"></code></pre>
            </div>

        </div>
    </div>
</main>

<script>
// Route through KitePHP front controller — NOT a raw PHP file.
const GEN_AJAX_URL = '<?= htmlspecialchars($ajaxUrl) ?>';

async function genCallApi(action, params = {}) {
    const body = new URLSearchParams({ action, ...params });
    const res  = await fetch(GEN_AJAX_URL, {
        method: 'POST',
        body,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Request failed.');
    return json;
}

function genShowAlert(message, type = 'danger') {
    const el = document.getElementById('genAlert');
    el.className = `alert alert-${type}`;
    el.textContent = message;
}

async function loadGenDatabases() {
    try {
        const { data } = await genCallApi('list_databases');
        const select = document.getElementById('genDbSelect');

        if (data.length === 0) {
            select.innerHTML = '<option value="">No databases yet</option>';
            return;
        }

        select.innerHTML = '<option value="">-- select database --</option>' +
            data.map(d => `<option value="${d.name}">${d.name}${d.description ? ' — ' + d.description : ''}</option>`).join('');
    } catch (e) {
        genShowAlert(e.message);
    }
}

async function generateCode() {
    const db_name = document.getElementById('genDbSelect').value;
    if (!db_name) {
        genShowAlert('Select a database first.');
        return;
    }

    try {
        const { code } = await genCallApi('generate_code', { db_name });
        document.getElementById('genCodeOutput').textContent = code;
        document.getElementById('genCodeWrap').classList.remove('d-none');
    } catch (e) {
        genShowAlert(e.message);
    }
}

function copyGeneratedCode() {
    const text = document.getElementById('genCodeOutput').textContent;
    navigator.clipboard.writeText(text).then(() => {
        genShowAlert('Code copied to clipboard.', 'success');
    }).catch(() => {
        genShowAlert('Could not copy automatically — please select the code and copy manually.');
    });
}

// Init
loadGenDatabases();
</script>
 <!-- Footer: mt-auto pushes it to the bottom -->
    <footer class="mt-auto bg-dark text-light rounded text-center" >
        <div class="container py-4">
            <p class="mb-1">&copy; 2026 KitePHP. All rights reserved.</p>
            <ul class="list-inline mb-0" text-light rounded>
      <li class="list-inline-item"><a href="#" class="text-secondary text-decoration-none">Privacy Policy</a></li>
      <li class="list-inline-item">&middot;</li>
      <li class="list-inline-item"><a href="#" class="text-secondary text-decoration-none">Terms of Service</a></li>
      <li class="list-inline-item">&middot;</li>
      <li class="list-inline-item"><a href="" class="text-secondary text-decoration-none">Contact</a></li>
    </ul>
          
        </div>
<?= $this->assets('js'); ?>
</body>
</html>