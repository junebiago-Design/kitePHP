<!-- Page: DatabaseController | View: database -->
<?php $this->include('header', ['projectName' => $projectName]); ?>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="/">DATABASE MANAGER</a>
    </div>
</nav>

<div class="container-fluid p-4">
    <div class="container mt-3">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDbModal">
                + New Database
            </button>
        </div>

        <p class="text-muted">
            Create SQLite databases in <code>/database</code>, add tables with primary keys
            and foreign keys to other tables, and manage rows.
        </p>

        <div id="dbAlert" class="alert d-none" role="alert"></div>

        <div id="dbAccordion" class="accordion mb-4"></div>

        <!-- SQL Console -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-1">
                <span>SQL Console</span>
                <small class="text-muted">Run raw SQL against any database</small>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-2">
                    <div class="col-sm-6 col-md-4">
                        <label class="form-label">Database</label>
                        <input type="text" id="sqlDbName" class="form-control" list="sqlDbNameList" placeholder="e.g. shop (existing or new)">
                        <datalist id="sqlDbNameList"></datalist>
                        <div class="form-text">If this name doesn't exist yet, it will be created.</div>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">SQL command(s)</label>
                    <textarea id="sqlCommandInput" class="form-control font-monospace" rows="8"
                              placeholder="CREATE TABLE users (&#10;  id INTEGER PRIMARY KEY AUTOINCREMENT,&#10;  name TEXT NOT NULL&#10;);"></textarea>
                    <div class="form-text">
                        Supports <code>CREATE TABLE</code>, <code>ALTER TABLE</code>, <code>DROP TABLE</code>,
                        <code>INSERT</code>, <code>UPDATE</code>, <code>DELETE</code>, and <code>SELECT</code>.
                        Multiple statements can be separated by semicolons and run together as one transaction.
                    </div>
                </div>
                <button type="button" class="btn btn-primary" onclick="runSqlCommand()">Run SQL</button>
                <div id="sqlResults" class="mt-3"></div>
            </div>
        </div>

    </div>
</div>

<!-- Create Database Modal -->
<div class="modal fade" id="createDbModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Database</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Database name</label>
                    <input type="text" id="newDbName" class="form-control" placeholder="e.g. shop">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea id="newDbDescription" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createDatabase()">Create</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Table Modal -->
<div class="modal fade" id="createTableModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Table <small class="text-muted" id="tableModalDbLabel"></small></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tableModalDbName">

                <div class="mb-3">
                    <label class="form-label">Table name</label>
                    <input type="text" id="newTableName" class="form-control" placeholder="e.g. users">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" id="newTableDescription" class="form-control">
                </div>

                <label class="form-label">Columns</label>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="columnsTable">
                        <thead>
                            <tr>
                                <th style="width:16%">Name</th>
                                <th style="width:14%">Type</th>
                                <th style="width:7%">Length</th>
                                <th style="width:6%" class="text-center">PK</th>
                                <th style="width:8%" class="text-center">Auto Inc</th>
                                <th style="width:6%" class="text-center">FK</th>
                                <th style="width:14%">Ref Table</th>
                                <th style="width:14%">Ref Column</th>
                                <th style="width:4%"></th>
                            </tr>
                        </thead>
                        <tbody id="columnsBody"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addColumnRow()">+ Add Column</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createTable()">Create Table</button>
            </div>
        </div>
    </div>
</div>

<!-- View Table Modal -->
<div class="modal fade" id="viewTableModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Table: <span id="viewTableName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="viewTableDbName">
                <form id="addRowForm" class="row g-2 mb-3 align-items-end"></form>
                <div class="table-responsive">
                    <table class="table table-striped table-sm" id="rowsTable">
                        <thead><tr id="rowsTableHead"></tr></thead>
                        <tbody id="rowsTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// <<< THIS IS THE KEY FIX >>>
// Route through KitePHP front controller, not a raw PHP file.
const DB_AJAX_URL = '<?= htmlspecialchars($ajaxUrl) ?>';

let currentSchema = { columns: [], foreign_keys: [] };

function showAlert(message, type = 'danger') {
    const el = document.getElementById('dbAlert');
    el.className = `alert alert-${type}`;
    el.textContent = message;
}

async function callApi(action, params = {}) {
    const body = new URLSearchParams({ action, ...params });
    const res  = await fetch(DB_AJAX_URL, {
        method: 'POST',
        body,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Request failed.');
    return json;
}

async function loadDatabases() {
    try {
        const { data } = await callApi('list_databases');
        renderDatabases(data);
    } catch (e) { showAlert(e.message); }
}

function renderDatabases(databases) {
    const wrap = document.getElementById('dbAccordion');
    wrap.innerHTML = '';

    if (!databases.length) {
        wrap.innerHTML = '<p class="text-muted">No databases yet. Create one to get started.</p>';
        return;
    }

    databases.forEach((db, i) => {
        const id = `dbCollapse${i}`;
        const item = document.createElement('div');
        item.className = 'accordion-item';
        item.innerHTML = `
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${id}">
                    <strong class="me-2">${db.name}</strong>
                    <span class="text-muted small">${db.description || ''}</span>
                </button>
            </h2>
            <div id="${id}" class="accordion-collapse collapse" data-bs-parent="#dbAccordion">
                <div class="accordion-body">
                    <div class="d-flex justify-content-between mb-2">
                        <div>
                            <button class="btn btn-sm btn-outline-primary" onclick="openCreateTableModal('${db.name}')">+ Add Table</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="openSqlConsoleFor('${db.name}')">SQL Console</button>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDatabase('${db.name}')">Delete Database</button>
                    </div>
                    <table class="table table-sm">
                        <thead><tr><th>Table</th><th>Description</th><th style="width:160px"></th></tr></thead>
                        <tbody>
                            ${db.tables.map(t => `
                                <tr>
                                    <td>${t.name}</td>
                                    <td class="text-muted">${t.description || ''}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="openViewTable('${db.name}','${t.name}')">View</button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTable('${db.name}','${t.name}')">Delete</button>
                                    </td>
                                </tr>
                            `).join('') || '<tr><td colspan="3" class="text-muted">No tables yet.</td></tr>'}
                        </tbody>
                    </table>
                </div>
                
            </div>`;
        wrap.appendChild(item);
    });
}

async function createDatabase() {
    const db_name     = document.getElementById('newDbName').value.trim();
    const description = document.getElementById('newDbDescription').value.trim();
    try {
        await callApi('create_database', { db_name, description });
        bootstrap.Modal.getInstance(document.getElementById('createDbModal')).hide();
        document.getElementById('newDbName').value = '';
        document.getElementById('newDbDescription').value = '';
        loadDatabases();
    } catch (e) { showAlert(e.message); }
}

async function deleteDatabase(db_name) {
    if (!confirm(`Delete database "${db_name}"? This cannot be undone.`)) return;
    try {
        await callApi('delete_database', { db_name });
        loadDatabases();
    } catch (e) { showAlert(e.message); }
}

// -------- Columns / Tables --------

const LENGTH_TYPES = ['VARCHAR', 'CHAR', 'DECIMAL', 'NUMERIC'];

function addColumnRow(prefill = {}) {
    const tbody = document.getElementById('columnsBody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" class="form-control form-control-sm col-name" value="${prefill.name || ''}"></td>
        <td>
            <select class="form-select form-select-sm col-type" onchange="toggleLengthInput(this); toggleAutoIncrement(this);">
                <optgroup label="Text">
                    <option value="TEXT" selected>TEXT</option>
                    <option value="VARCHAR">VARCHAR</option>
                    <option value="CHAR">CHAR</option>
                </optgroup>
                <optgroup label="Whole number">
                    <option value="INTEGER">INTEGER</option>
                    <option value="BIGINT">BIGINT</option>
                    <option value="SMALLINT">SMALLINT</option>
                    <option value="TINYINT">TINYINT</option>
                    <option value="BOOLEAN">BOOLEAN</option>
                </optgroup>
                <optgroup label="Decimal / Float">
                    <option value="REAL">REAL</option>
                    <option value="FLOAT">FLOAT</option>
                    <option value="DOUBLE">DOUBLE</option>
                    <option value="DECIMAL">DECIMAL</option>
                    <option value="NUMERIC">NUMERIC</option>
                </optgroup>
                <optgroup label="Date / Time">
                    <option value="DATE">DATE</option>
                    <option value="DATETIME">DATETIME</option>
                    <option value="TIMESTAMP">TIMESTAMP</option>
                </optgroup>
                <optgroup label="Binary">
                    <option value="BLOB">BLOB</option>
                </optgroup>
                <optgroup label="Security">
                    <option value="PASSWORD">PASSWORD (auto-hashed)</option>
                </optgroup>
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm col-length" placeholder="e.g. 255" style="display:none"></td>
        <td class="text-center"><input type="checkbox" class="form-check-input col-pk" onchange="toggleAutoIncrement(this)"></td>
        <td class="text-center"><input type="checkbox" class="form-check-input col-autoincrement" disabled></td>
        <td class="text-center"><input type="checkbox" class="form-check-input col-fk" onchange="toggleFkSelects(this)"></td>
        <td><select class="form-select form-select-sm col-fk-table" disabled onchange="loadFkColumns(this)"></select></td>
        <td><select class="form-select form-select-sm col-fk-column" disabled></select></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger" onclick="this.closest('tr').remove()">&times;</button></td>`;
    tbody.appendChild(row);
}

function toggleLengthInput(selectEl) {
    const row = selectEl.closest('tr');
    const len = row.querySelector('.col-length');
    if (LENGTH_TYPES.includes(selectEl.value)) { len.style.display = ''; }
    else { len.style.display = 'none'; len.value = ''; }
}

function toggleAutoIncrement(el) {
    const row = el.closest('tr');
    const pk  = row.querySelector('.col-pk').checked;
    const int = row.querySelector('.col-type').value === 'INTEGER';
    const ai  = row.querySelector('.col-autoincrement');
    const ok  = pk && int;
    ai.disabled = !ok;
    if (!ok) ai.checked = false;
}

function toggleFkSelects(cb) {
    const row = cb.closest('tr');
    const ts  = row.querySelector('.col-fk-table');
    const cs  = row.querySelector('.col-fk-column');
    ts.disabled = !cb.checked;
    cs.disabled = !cb.checked;
    if (cb.checked) populateFkTableOptions(ts);
}

async function populateFkTableOptions(selectEl) {
    const db_name = document.getElementById('tableModalDbName').value;
    try {
        const { data } = await callApi('list_tables', { db_name });
        selectEl.innerHTML = '<option value="">-- select table --</option>' +
            data.map(t => `<option value="${t.name}">${t.name}</option>`).join('');
    } catch (e) { showAlert(e.message); }
}

async function loadFkColumns(tableSelectEl) {
    const row = tableSelectEl.closest('tr');
    const col = row.querySelector('.col-fk-column');
    const db_name = document.getElementById('tableModalDbName').value;
    const table_name = tableSelectEl.value;
    if (!table_name) { col.innerHTML = ''; return; }
    try {
        const { data } = await callApi('get_table_schema', { db_name, table_name });
        col.innerHTML = data.columns.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
    } catch (e) { showAlert(e.message); }
}

function openCreateTableModal(db_name) {
    document.getElementById('tableModalDbName').value = db_name;
    document.getElementById('tableModalDbLabel').textContent = `(${db_name})`;
    document.getElementById('newTableName').value = '';
    document.getElementById('newTableDescription').value = '';
    document.getElementById('columnsBody').innerHTML = '';
    addColumnRow({ name: 'id' });
    const idRow = document.querySelector('#columnsBody tr');
    idRow.querySelector('.col-type').value = 'INTEGER';
    idRow.querySelector('.col-pk').checked = true;
    toggleAutoIncrement(idRow.querySelector('.col-pk'));
    idRow.querySelector('.col-autoincrement').checked = true;
    new bootstrap.Modal(document.getElementById('createTableModal')).show();
}

async function createTable() {
    const db_name   = document.getElementById('tableModalDbName').value;
    const table_name= document.getElementById('newTableName').value.trim();
    const description = document.getElementById('newTableDescription').value.trim();

    const columns = [...document.querySelectorAll('#columnsBody tr')].map(row => ({
        name:          row.querySelector('.col-name').value.trim(),
        type:          row.querySelector('.col-type').value,
        length:        row.querySelector('.col-length').value.trim(),
        pk:            row.querySelector('.col-pk').checked,
        autoincrement: row.querySelector('.col-autoincrement').checked,
        fk_table:      row.querySelector('.col-fk').checked ? row.querySelector('.col-fk-table').value : '',
        fk_column:     row.querySelector('.col-fk').checked ? row.querySelector('.col-fk-column').value : '',
    })).filter(c => c.name);

    try {
        await callApi('create_table', { db_name, table_name, description, columns: JSON.stringify(columns) });
        bootstrap.Modal.getInstance(document.getElementById('createTableModal')).hide();
        loadDatabases();
    } catch (e) { showAlert(e.message); }
}

async function deleteTable(db_name, table_name) {
    if (!confirm(`Delete table "${table_name}"?`)) return;
    try {
        await callApi('delete_table', { db_name, table_name });
        loadDatabases();
    } catch (e) { showAlert(e.message); }
}

// -------- Rows --------

async function openViewTable(db_name, table_name) {
    document.getElementById('viewTableDbName').value = db_name;
    document.getElementById('viewTableName').textContent = `${table_name} (${db_name})`;

    try {
        const schemaRes = await callApi('get_table_schema', { db_name, table_name });
        currentSchema = schemaRes.data;
        const rowsRes = await callApi('select_rows', { db_name, table_name });
        renderRowsTable(table_name, currentSchema.columns, rowsRes.data);
        renderAddRowForm(db_name, table_name, currentSchema.columns);
        new bootstrap.Modal(document.getElementById('viewTableModal')).show();
    } catch (e) { showAlert(e.message); }
}

function renderRowsTable(table_name, columns, rows) {
    const head = document.getElementById('rowsTableHead');
    const body = document.getElementById('rowsTableBody');
    const names = columns.map(c => c.name);
    const pwd = new Set(columns.filter(c => c.type.toUpperCase().startsWith('PASSWORD')).map(c => c.name));

    head.innerHTML = names.map(c => `<th>${c}</th>`).join('') + '<th></th>';

    body.innerHTML = rows.map(row => `
        <tr>
            ${names.map(c => `<td>${pwd.has(c) && row[c] ? '••••••••' : (row[c] ?? '')}</td>`).join('')}
            <td><button class="btn btn-sm btn-outline-danger" onclick='deleteRowClick(${JSON.stringify(row)})'>Delete</button></td>
        </tr>`).join('') ||
        `<tr><td colspan="${names.length + 1}" class="text-muted">No rows yet.</td></tr>`;
}

function renderAddRowForm(db_name, table_name, columns) {
    const form = document.getElementById('addRowForm');
    const pkNames = columns.filter(c => c.pk).map(c => c.name);
    const editable = columns.filter(c => !(pkNames.length === 1 && c.pk && c.type === 'INTEGER'));

    form.innerHTML = editable.map(c => {
        const pwd = c.type.toUpperCase().startsWith('PASSWORD');
        return `
            <div class="col-auto">
                <label class="form-label small mb-0">${c.name}</label>
                <input type="${pwd ? 'password' : 'text'}" class="form-control form-control-sm add-row-field"
                       data-col="${c.name}" placeholder="${c.type}" ${pwd ? 'autocomplete="new-password"' : ''}>
            </div>`;
    }).join('') + `
        <div class="col-auto">
            <button type="button" class="btn btn-sm btn-primary" onclick="submitAddRow('${db_name}','${table_name}')">Add Row</button>
        </div>`;
}

async function submitAddRow(db_name, table_name) {
    const data = {};
    document.querySelectorAll('#addRowForm .add-row-field').forEach(i => {
        if (i.value !== '') data[i.dataset.col] = i.value;
    });
    try {
        await callApi('insert_row', { db_name, table_name, data: JSON.stringify(data) });
        openViewTable(db_name, table_name);
    } catch (e) { showAlert(e.message); }
}

async function deleteRowClick(row) {
    const db_name = document.getElementById('viewTableDbName').value;
    const table_name = document.getElementById('viewTableName').textContent.split(' (')[0];
    const pkCols = currentSchema.columns.filter(c => c.pk).map(c => c.name);
    const where = {};
    (pkCols.length ? pkCols : Object.keys(row)).forEach(k => where[k] = row[k]);

    if (!confirm('Delete this row?')) return;
    try {
        await callApi('delete_row', { db_name, table_name, where: JSON.stringify(where) });
        openViewTable(db_name, table_name);
    } catch (e) { showAlert(e.message); }
}

// -------- SQL Console --------

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

function openSqlConsoleFor(db_name) {
    document.getElementById('sqlDbName').value = db_name;
    document.getElementById('sqlCommandInput').focus();
    document.getElementById('sqlCommandInput').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function populateSqlDbNameList() {
    try {
        const { data } = await callApi('list_databases');
        document.getElementById('sqlDbNameList').innerHTML =
            data.map(d => `<option value="${escapeHtml(d.name)}">`).join('');
    } catch (e) {}
}

function renderSqlResult(r, i) {
    const header = `<div class="small text-muted mb-1">Statement ${i + 1}: <code>${escapeHtml(r.sql)}</code></div>`;

    if (r.type === 'select') {
        if (!r.rows || r.rows.length === 0) {
            return `<div class="mb-3">${header}<div class="text-muted small">0 rows returned.</div></div>`;
        }
        const cols = Object.keys(r.rows[0]);
        return `
            <div class="mb-3">
                ${header}
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-1">
                        <thead><tr>${cols.map(c => `<th>${escapeHtml(c)}</th>`).join('')}</tr></thead>
                        <tbody>
                            ${r.rows.map(row => `<tr>${cols.map(c => `<td>${escapeHtml(row[c])}</td>`).join('')}</tr>`).join('')}
                        </tbody>
                    </table>
                </div>
                <div class="text-muted small">${r.count} row(s).</div>
            </div>`;
    }

    return `<div class="mb-3">${header}<div class="text-success small">OK &mdash; ${r.affected} row(s) affected.</div></div>`;
}

async function runSqlCommand() {
    const db_name = document.getElementById('sqlDbName').value.trim();
    const sql     = document.getElementById('sqlCommandInput').value;
    const out     = document.getElementById('sqlResults');
    out.innerHTML = '';

    if (!db_name) { showAlert('Enter a database name to run the SQL against.'); return; }
    if (!sql.trim()) { showAlert('Enter a SQL command to run.'); return; }

    try {
        const { results } = await callApi('execute_sql', { db_name, sql });
        out.innerHTML = results.map((r, i) => renderSqlResult(r, i)).join('') ||
            '<div class="text-muted small">No statements ran.</div>';
        loadDatabases();
        populateSqlDbNameList();
    } catch (e) { showAlert(e.message); }
}

// Init
loadDatabases();
populateSqlDbNameList();
</script>

<br><br>

    <!-- Footer: mt-auto pushes it to the bottom -->
    <footer class="mt-auto bg-dark text-light rounded text-center" >
        <div class="container py-4">
            <p class="mb-1">&copy; 2026 KitePHP. All rights reserved.</p>
            <ul class="list-inline mb-0" text-light rounded>
      <li class="list-inline-item"><a href="#" class="text-secondary text-decoration-none">Privacy Policy</a></li>
      <li class="list-inline-item"><a href="#" class="text-secondary text-decoration-none">Terms of Service</a></li>
      <li class="list-inline-item"><a href="#" class="text-secondary text-decoration-none">Contact</a></li>
    </ul>
          
        </div>
    </footer>
<?= $this->assets('js'); ?>