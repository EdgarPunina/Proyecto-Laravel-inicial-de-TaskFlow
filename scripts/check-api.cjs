const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const base = process.env.TASKFLOW_URL || 'http://127.0.0.1:8000';
const evidence = [];

async function request(method, endpoint, expected, body) {
  const response = await fetch(base + '/api' + endpoint, {
    method,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });
  const raw = await response.text();
  assert.equal(response.status, expected, `${method} ${endpoint}: ${raw}`);
  const data = raw ? JSON.parse(raw) : null;
  evidence.push({ method, endpoint, expected, status: response.status, body: body ?? null, response: data });
  console.log(`${method} ${endpoint}: ${response.status} OK`);
  return data;
}

(async () => {
  const initial = await request('GET', '/tasks', 200);
  assert.ok(initial.data.length >= 2, 'Ejecuta TaskDemoSeeder para crear dos tareas de prueba.');
  const userId = initial.data[0].user_id;
  let taskId;
  try {
    const created = await request('POST', '/tasks', 201, { title: 'Probar API sesión 04', user_id: userId });
    taskId = created.data.id;
    assert.equal(created.data.status, 'pendiente');
    await request('GET', `/tasks/${taskId}`, 200);
    await request('PUT', `/tasks/${taskId}`, 200, { status: 'en_progreso' });
    await request('PATCH', `/tasks/${taskId}`, 200, { status: 'completada' });
    await request('POST', '/tasks', 422, { user_id: userId });
    await request('POST', '/tasks', 422, { title: 'Usuario inexistente', user_id: 2147483647 });
    await request('PATCH', `/tasks/${taskId}`, 422, { status: 'incorrecto' });
    await request('DELETE', `/tasks/${taskId}`, 204);
    const deletedId = taskId;
    taskId = undefined;
    await request('GET', `/tasks/${deletedId}`, 404);
    const final = await request('GET', '/tasks', 200);
    assert.equal(final.data.length, initial.data.length);
  } finally {
    if (taskId !== undefined) await request('DELETE', `/tasks/${taskId}`, 204);
  }
  const docs = path.join(__dirname, '../docs');
  fs.writeFileSync(path.join(docs, 'evidencia-http.json'), JSON.stringify({
    executed_at: new Date().toISOString(), base_url: base, checks: evidence,
  }, null, 2) + '\n');
})().catch(error => { console.error(error); process.exitCode = 1; });
