'use strict';
const $ = id => document.getElementById(id);
let csrf = '', currentUser = null, records = [], users = [];
function message(text, error = false) { $('message').textContent = text; $('message').className = error ? 'error' : 'success'; document.querySelectorAll('.dialog-message').forEach(p => { p.textContent = error ? text : ''; }); }
async function api(action, data) {
  const response = await fetch(`api.php?action=${action}`, {
    credentials: 'same-origin', cache: 'no-store',
    ...(data === undefined ? {} : {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf}, body: JSON.stringify(data)})
  });
  const result = await response.json();
  if (!response.ok) throw new Error(result.error || 'Request failed.');
  return result;
}
function run(fn) { return async event => { if (event) event.preventDefault(); try { await fn(event); } catch (e) { message(e.message, true); } }; }
function cell(row, text) { const td = row.insertCell(); td.textContent = text; return td; }
function actionButton(container, title, fn, danger = false) { const b = document.createElement('button'); b.textContent = title; b.className = danger ? 'danger small' : 'secondary small'; b.type = 'button'; b.addEventListener('click', run(fn)); container.append(b); }
function showSession() {
  $('login-panel').hidden = !!currentUser; $('dashboard').hidden = !currentUser; $('logout').hidden = !currentUser;
  $('identity').textContent = currentUser ? `${currentUser.name} · ${currentUser.role}` : '';
  $('users-tab').hidden = currentUser?.role !== 'admin';
  $('greeting').textContent = currentUser?.role === 'admin' ? 'All records' : 'My records';
  $('scope').textContent = currentUser?.role === 'admin' ? 'Manage records across your team.' : 'Create and manage your own records.';
  $('users-panel').hidden = true; $('records-panel').hidden = false;
}
async function loadRecords() { records = (await api('records')).records; renderRecords(); }
function renderRecords() {
  const term = $('search').value.toLowerCase(); const list = records.filter(r => `${r.title} ${r.description} ${r.status}`.toLowerCase().includes(term));
  $('records-body').replaceChildren(); $('count').textContent = `${records.length} records`; $('empty').hidden = list.length > 0;
  $('empty').textContent = records.length ? 'No matching records.' : 'No records yet. Create your first record.';
  for (const record of list) {
    const row = $('records-body').insertRow(); const info = cell(row, record.title); const desc = document.createElement('small'); desc.textContent = record.description; info.append(desc);
    cell(row, record.status).className = 'status'; cell(row, new Date(record.updated_at).toLocaleString());
    const actions = cell(row, ''); actionButton(actions, 'Edit', () => editRecord(record));
    actionButton(actions, 'Delete', async () => { if (!confirm(`Delete “${record.title}”?`)) return; await api('delete_record', {id: record.id}); await loadRecords(); message('Record deleted.'); }, true);
  }
}
function fillForm(form, values) { form.reset(); document.querySelectorAll('.dialog-message').forEach(p => { p.textContent = ''; }); for (const [key, value] of Object.entries(values)) if (form.elements.namedItem(key)) form.elements.namedItem(key).value = value; }
function editRecord(record = {id: '', title: '', description: '', status: 'Pending'}) { fillForm($('record-form'), record); $('record-title').textContent = record.id ? 'Edit record' : 'New record'; $('record-dialog').showModal(); }
async function loadUsers() {
  users = (await api('users')).users; $('users-body').replaceChildren();
  for (const user of users) {
    const row = $('users-body').insertRow(); cell(row, user.name); cell(row, user.email); cell(row, user.role);
    const actions = cell(row, ''); actionButton(actions, 'Edit', () => editUser(user));
    if (user.id !== currentUser.id) actionButton(actions, 'Delete', async () => { if (!confirm(`Delete ${user.name} and all their records?`)) return; await api('delete_user', {id: user.id}); await loadUsers(); await loadRecords(); message('Account deleted.'); }, true);
  }
}
function editUser(user = {id: '', name: '', email: '', role: 'user'}) { fillForm($('user-form'), {...user, password: ''}); $('user-form').elements.password.required = !user.id; $('user-title').textContent = user.id ? 'Edit user' : 'New user'; $('user-dialog').showModal(); }
$('login-form').addEventListener('submit', run(async () => { const result = await api('login', Object.fromEntries(new FormData($('login-form')))); csrf = result.csrf; currentUser = result.user; $('login-form').reset(); showSession(); await loadRecords(); message('Signed in successfully.'); }));
$('logout').addEventListener('click', run(async () => { await api('logout', {}); location.reload(); }));
$('record-form').addEventListener('submit', run(async () => { await api('save_record', Object.fromEntries(new FormData($('record-form')))); $('record-dialog').close(); await loadRecords(); message('Record saved.'); }));
$('user-form').addEventListener('submit', run(async () => { await api('save_user', Object.fromEntries(new FormData($('user-form')))); $('user-dialog').close(); const session = await api('session'); currentUser = session.user; $('identity').textContent = `${currentUser.name} · ${currentUser.role}`; await loadUsers(); message('Account saved.'); }));
$('add-record').addEventListener('click', () => editRecord()); $('add-user').addEventListener('click', () => editUser());
$('search').addEventListener('input', renderRecords);
$('records-tab').addEventListener('click', () => { $('records-panel').hidden = false; $('users-panel').hidden = true; });
$('users-tab').addEventListener('click', run(async () => { await loadUsers(); $('records-panel').hidden = true; $('users-panel').hidden = false; }));
document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => $(b.dataset.close).close()));
run(async () => { const session = await api('session'); csrf = session.csrf; currentUser = session.user; showSession(); if (currentUser) await loadRecords(); })();
