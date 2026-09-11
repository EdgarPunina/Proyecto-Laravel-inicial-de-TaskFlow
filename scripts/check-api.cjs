const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const base = process.env.TASKFLOW_URL || 'http://127.0.0.1:8000';
const evidence = [];
const tokens = new Set();
const tasks = new Map();

function redact(value) {
  if (Array.isArray(value)) return value.map(redact);
  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.entries(value)
      .filter(([key]) => !['trace', 'file', 'line', 'exception'].includes(key))
      .map(([key, entry]) => [key, ['password', 'token'].includes(key) ? '[OMITIDO]' : redact(entry)]));
  }
  return value;
}

function request(method, endpoint, expected, body, token) {
  const args = ['--silent', '--show-error', '--max-time', '15', '--request', method,
    '--header', 'Accept: application/json', '--header', 'Content-Type: application/json',
    '--write-out', '\n%{http_code}', base + '/api' + endpoint];
  if (token) args.push('--header', `Authorization: Bearer ${token}`);
  if (body !== undefined) args.push('--data-binary', '@-');
  const result = spawnSync(process.platform === 'win32' ? 'curl.exe' : 'curl', args, {
    input: body === undefined ? undefined : JSON.stringify(body), encoding: 'utf8', windowsHide: true,
  });
  if (result.error) throw result.error;
  assert.equal(result.status, 0, result.stderr);
  const split = result.stdout.lastIndexOf('\n');
  const status = Number(result.stdout.slice(split + 1));
  const raw = result.stdout.slice(0, split);
  const data = raw ? JSON.parse(raw) : null;
  assert.equal(status, expected, `${method} ${endpoint}: ${JSON.stringify(redact(data))}`);
  evidence.push({ method, endpoint, authenticated: Boolean(token), expected, status,
    body: redact(body ?? null), response: redact(data) });
  console.log(`${method} ${endpoint}: ${status} OK`);
  return data;
}

try {
  const suffix = Date.now();
  const anaCredentials = { name: 'Ana', email: `ana.${suffix}@example.com`, password: 'password123' };
  const betoCredentials = { name: 'Beto', email: `beto.${suffix}@example.com`, password: 'password123' };
  const ana = request('POST', '/register', 200, anaCredentials);
  tokens.add(ana.token);
  const beto = request('POST', '/register', 200, betoCredentials);
  tokens.add(beto.token);
  assert.ok(ana.token && beto.token);
  assert.equal(ana.user.password, undefined);
  request('POST', '/register', 422, anaCredentials);
  request('POST', '/login', 401, { email: anaCredentials.email, password: 'incorrecta' });
  const login = request('POST', '/login', 200, { email: anaCredentials.email, password: anaCredentials.password });
  tokens.add(login.token);
  request('GET', '/tasks', 401);
  request('GET', '/user', 401);
  request('POST', '/logout', 401);
  request('GET', '/tasks', 401, undefined, 'token-invalido');
  const profile = request('GET', '/user', 200, undefined, ana.token);
  assert.equal(profile.id, ana.user.id);
  assert.deepEqual(request('GET', '/tasks', 200, undefined, ana.token).data, []);
  const task = request('POST', '/tasks', 201, { title: 'Tarea de Ana', user_id: beto.user.id }, ana.token).data;
  tasks.set(task.id, ana.token);
  assert.equal(task.user_id, ana.user.id);
  assert.equal(task.status, 'pendiente');
  const betoTask = request('POST', '/tasks', 201, { title: 'Tarea de Beto' }, beto.token).data;
  tasks.set(betoTask.id, beto.token);
  assert.deepEqual(request('GET', '/tasks', 200, undefined, ana.token).data.map(t => t.id), [task.id]);
  assert.deepEqual(request('GET', '/tasks', 200, undefined, beto.token).data.map(t => t.id), [betoTask.id]);
  request('GET', `/tasks/${task.id}`, 404, undefined, beto.token);
  request('PUT', `/tasks/${task.id}`, 404, { status: 'completada' }, beto.token);
  request('PATCH', `/tasks/${task.id}`, 404, { status: 'completada' }, beto.token);
  request('DELETE', `/tasks/${task.id}`, 404, undefined, beto.token);
  assert.equal(request('GET', `/tasks/${task.id}`, 200, undefined, ana.token).data.status, 'pendiente');
  request('PUT', `/tasks/${task.id}`, 200, { status: 'en_progreso' }, ana.token);
  request('PATCH', `/tasks/${task.id}`, 200, { status: 'completada' }, ana.token);
  request('POST', '/tasks', 422, {}, ana.token);
  request('PATCH', `/tasks/${task.id}`, 422, { status: 'incorrecto' }, ana.token);
  request('DELETE', `/tasks/${task.id}`, 204, undefined, ana.token);
  tasks.delete(task.id);
  request('GET', `/tasks/${task.id}`, 404, undefined, ana.token);
  request('DELETE', `/tasks/${betoTask.id}`, 204, undefined, beto.token);
  tasks.delete(betoTask.id);
  request('POST', '/logout', 200, undefined, ana.token);
  tokens.delete(ana.token);
  request('GET', '/tasks', 401, undefined, ana.token);
  request('GET', '/tasks', 200, undefined, login.token);
  request('POST', '/logout', 200, undefined, login.token);
  tokens.delete(login.token);
  request('POST', '/logout', 200, undefined, beto.token);
  tokens.delete(beto.token);
  request('GET', '/tasks', 401, undefined, beto.token);

  fs.writeFileSync(path.join(__dirname, '../docs/evidencia-http-sesion-05.json'), JSON.stringify({
    executed_at: new Date().toISOString(), client: 'curl', base_url: base, checks: evidence,
  }, null, 2) + '\n');
  // Datos de demostración locales para el próximo login; storage/app no se versiona.
  fs.writeFileSync(path.join(__dirname, '../storage/app/practice-credentials.json'), JSON.stringify({
    ana: anaCredentials, beto: betoCredentials,
  }, null, 2) + '\n');
  console.log(`${evidence.length} comprobaciones HTTP correctas. Tokens de prueba revocados.`);
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
} finally {
  for (const [id, token] of tasks) {
    try { request('DELETE', `/tasks/${id}`, 204, undefined, token); } catch (error) { console.error(error.message); process.exitCode = 1; }
  }
  for (const token of tokens) {
    try { request('POST', '/logout', 200, undefined, token); } catch (error) { console.error(error.message); process.exitCode = 1; }
  }
}
