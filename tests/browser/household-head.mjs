// Renders the real Blade controls with fictional models; never writes registry data.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFileSync, mkdtempSync, rmSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { spawn, execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const browserBin = process.env.BROWSER_BIN ?? ['/opt/google/chrome/chrome', '/usr/bin/google-chrome'].find(existsSync);
assert.ok(browserBin, 'Set BROWSER_BIN to an installed Chromium-compatible browser');
const manifest = JSON.parse(readFileSync(`${root}/public/build/manifest.json`));
const css = readFileSync(`${root}/public/build/${manifest['resources/css/app.css'].file}`);
const js = readFileSync(`${root}/public/build/${manifest['resources/js/app.js'].file}`);
const fragments = JSON.parse(execFileSync('php', ['-r', String.raw`
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$errors = new Illuminate\Support\ViewErrorBag;
$home = new App\Models\Household(['household_no' => '001']);
$home->id = 1; $home->head_resident_id = 1;
$head = new App\Models\Resident(['first_name' => 'Pedro', 'last_name' => 'Santos', 'household_id' => 1, 'relationship_to_head' => 'Head of Household']);
$head->id = 1; $head->exists = true; $head->setRelation('household', $home);
$member = new App\Models\Resident(['first_name' => 'Maria', 'last_name' => 'Santos', 'household_id' => 1, 'relationship_to_head' => 'Spouse', 'sex' => 'Female', 'birth_date' => '1989-01-01']);
$member->id = 2; $member->exists = true; $member->setRelation('household', $home);
$child = new App\Models\Resident(['first_name' => 'Juan', 'last_name' => 'Santos', 'household_id' => 1, 'relationship_to_head' => 'Child']);
$child->id = 3; $home->setRelation('residents', collect([$head, $member, $child]));
$source = file_get_contents('resources/views/admin/geometry/residents/partials/form.blade.php');
$start = strpos($source, '<label for="relationship_to_head"');
$end = strpos($source, "@error('relationship_to_head')", $start);
$control = substr($source, $start, $end - $start);
$render = fn($resident) => Illuminate\Support\Facades\Blade::render($control, ['resident' => $resident, 'errors' => $errors, 'routePrefix' => 'secretary']);
$review = file_get_contents('resources/views/households/head-review.blade.php');
$review = substr($review, strpos($review, '<form'));
$review = substr($review, 0, strrpos($review, '@endsection'));
$puroks = collect([['id' => 11, 'purok_number' => 1, 'purok_name' => 'Centro'], ['id' => 12, 'purok_number' => 2, 'purok_name' => 'Ilaya']]);
$barangays = collect([(new App\Models\Barangay(['name' => 'Pilot']))->setAttribute('id', 1), (new App\Models\Barangay(['name' => 'Other']))->setAttribute('id', 2)]);
$householdForm = file_get_contents('resources/views/admin/geometry/households/partials/form.blade.php');
$householdForm = substr($householdForm, 0, strpos($householdForm, '<label for="household_no"'));
$householdForm = substr($householdForm, 0, strrpos($householdForm, '<div>')).'</div>';
$edit = file_get_contents('resources/views/admin/geometry/households/edit.blade.php');
preg_match('/<script>(.*?)<\/script>/s', $edit, $script);
echo json_encode(['ordinary' => $render($member), 'head' => $render($head), 'age' => $member->age, 'householdScript' => $script[1],
'household' => Illuminate\Support\Facades\Blade::render($householdForm, ['availablePuroks' => $puroks, 'barangays' => $barangays, 'errors' => $errors]),
'review' => Illuminate\Support\Facades\Blade::render($review, ['errors' => $errors, 'payload' => ['first_name' => 'Pedro', 'set_as_household_head' => '0'],
'action' => '/save', 'method' => 'PATCH', 'token' => 'fixture-token', 'cancelUrl' => '/cancel', 'households' => collect([1 => $home]),
'plans' => [1 => ['choose_candidate' => true, 'exclude_id' => 1]]])]);
`], { cwd: root, encoding: 'utf8' }));
const requests = [];
const initialPuroks = [{ id: 11, purok_number: 1, purok_name: 'Centro' }, { id: 12, purok_number: 2, purok_name: 'Ilaya' }];
const page = kind => {
    const household = ['household', 'old-household'].includes(kind);
    const state = household ? `householdForm('/puroks', '${kind === 'old-household' ? '12' : '11'}', '1', ${JSON.stringify(initialPuroks)})` : `{ householdId: '1' }`;
    const content = kind === 'review' ? fragments.review : `<form method="POST" action="/save" x-data='${state.replaceAll("'", '&#39;')}'>${fragments[household ? 'household' : kind]}</form>`;
    return `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/app.css"></head><body><main class="p-4">${content}</main><script>${fragments.householdScript}</script><script type="module" src="/app.js"></script></body></html>`;
};
const server = createServer((req, res) => {
    if (req.url === '/app.css') { res.setHeader('Content-Type', 'text/css'); return res.end(css); }
    if (req.url === '/app.js') { res.setHeader('Content-Type', 'text/javascript'); return res.end(js); }
    if (req.url.startsWith('/puroks?')) {
        res.setHeader('Content-Type', 'application/json');
        return res.end(JSON.stringify(new URL(req.url, 'http://fixture').searchParams.get('barangay_id') === '1' ? initialPuroks : [{ id: 21, purok_number: 1, purok_name: 'Other scope' }]));
    }
    requests.push({ method: req.method, url: req.url });
    res.setHeader('Content-Type', 'text/html');
    res.end(req.url === '/cancel' ? 'Cancelled' : page(req.url.slice(1) || 'ordinary'));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;
const profile = mkdtempSync(`${tmpdir()}/healthlink-head-`);
const browser = spawn(browserBin, ['--headless=new', '--no-sandbox', '--disable-gpu', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
try {
    const portFile = `${profile}/DevToolsActivePort`;
    for (let i = 0; i < 150 && !existsSync(portFile); i++) await sleep(30);
    const port = readFileSync(portFile, 'utf8').split('\n')[0];
    const tabs = await fetch(`http://127.0.0.1:${port}/json/list`).then(response => response.json());
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    let id = 0;
    const pending = new Map();
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (!message.id) return;
        const task = pending.get(message.id);
        pending.delete(message.id);
        message.error ? task.reject(message.error) : task.resolve(message.result);
    });
    const call = (method, params = {}) => new Promise((resolve, reject) => {
        const current = ++id;
        const timeout = setTimeout(() => { pending.delete(current); reject(new Error(`CDP timeout: ${method}`)); }, 10000);
        pending.set(current, { resolve: value => { clearTimeout(timeout); resolve(value); }, reject: value => { clearTimeout(timeout); reject(value); } });
        socket.send(JSON.stringify({ id: current, method, params }));
    });
    const evaluate = async expression => {
        const output = await call('Runtime.evaluate', { expression, returnByValue: true });
        if (output.exceptionDetails) throw new Error(output.exceptionDetails.text);
        return output.result.value;
    };
    const wait = async expression => {
        for (let i = 0; i < 150; i++) { if (await evaluate(expression)) return; await sleep(30); }
        throw new Error(`Timeout: ${expression}`);
    };
    await call('Page.enable');
    await call('Page.navigate', { url: `${base}/ordinary` });
    await wait('window.Alpine && document.querySelector("select").disabled === false');
    assert.equal(await evaluate('document.querySelector("input[type=checkbox]").checked'), false);
    assert.equal(await evaluate('document.querySelector("select").value'), 'Spouse');
    assert.equal(await evaluate('Boolean(document.querySelector("option[value=\\"Head of Household\\"]"))'), false);
    await evaluate('document.querySelector("input[type=checkbox]").click()');
    await wait('document.querySelector("select").disabled');
    assert.deepEqual(await evaluate('new FormData(document.querySelector("form")).getAll("relationship_to_head")'), ['Head of Household']);
    await evaluate('document.querySelector("input[type=checkbox]").click()');
    await wait('!document.querySelector("select").disabled');
    assert.deepEqual(await evaluate('new FormData(document.querySelector("form")).getAll("relationship_to_head")'), ['Spouse']);
    await call('Page.navigate', { url: `${base}/head` });
    await wait('window.Alpine && document.querySelector("select").disabled');
    assert.deepEqual(await evaluate('new FormData(document.querySelector("form")).getAll("relationship_to_head")'), ['Head of Household']);
    assert.equal(await evaluate('getComputedStyle(document.querySelector("input[type=checkbox]").parentElement).display'), 'none');
    await evaluate('Alpine.$data(document.querySelector("form")).householdId = "2"');
    await wait('!document.querySelector("select").disabled');
    assert.equal(await evaluate('document.querySelector("select").value'), '');
    await call('Page.navigate', { url: `${base}/review` });
    await wait('window.Alpine && document.querySelector("#replacement-1")');
    assert.ok(await evaluate(`document.body.textContent.includes('Female') && document.body.textContent.includes('${fragments.age} years old')`));
    assert.ok(await evaluate("document.body.textContent.includes('Sex not recorded') && document.body.textContent.includes('Age not recorded')"));
    assert.equal(await evaluate('document.querySelectorAll("select[name*=relationships]").length'), 2);
    await evaluate('const choice=document.querySelector("#replacement-1");choice.value="2";choice.dispatchEvent(new Event("change",{bubbles:true}));');
    await wait('document.querySelector("#member-1-2").disabled');
    assert.equal(await evaluate('document.querySelector("#member-1-3").value'), 'Child');
    assert.equal(await evaluate('new FormData(document.querySelector("form")).get("first_name")'), 'Pedro');
    await call('Emulation.setDeviceMetricsOverride', { width: 375, height: 812, deviceScaleFactor: 1, mobile: true });
    assert.equal(await evaluate('document.documentElement.scrollWidth <= window.innerWidth'), true);
    await evaluate('document.querySelector("a").click()');
    await wait('document.body.textContent === "Cancelled"');
    assert.equal(requests.at(-1).method, 'GET');
    assert.equal(requests.some(request => request.method !== 'GET'), false);
    await call('Page.navigate', { url: `${base}/household` });
    await wait('window.Alpine && document.querySelector("#purok_id").value === "11"');
    assert.equal(await evaluate('Alpine.$data(document.querySelector("form")).purokId'), '11');
    await call('Page.navigate', { url: `${base}/old-household` });
    await wait('window.Alpine && document.querySelector("#purok_id").value === "12"');
    assert.equal(await evaluate('Alpine.$data(document.querySelector("form")).purokId'), '12');
    await evaluate('const select=document.querySelector("#barangay_id");select.value="2";select.dispatchEvent(new Event("change",{bubbles:true}));');
    await wait('document.querySelector("#purok_id").value === "" && document.querySelector("option[value=\\"21\\"]")');
    assert.equal(await evaluate('Boolean(document.querySelector("option[value=\\"11\\"]"))'), false);
    await evaluate('const purok=document.querySelector("#purok_id");purok.value="21";purok.dispatchEvent(new Event("change",{bubbles:true}));');
    await wait('Alpine.$data(document.querySelector("form")).purokId === "21"');
    assert.equal(requests.some(request => request.method !== 'GET'), false);
    console.log('Household browser fixture passed: Phase 3B controls, review sex/age/fallbacks, persisted/old Purok selection, scope changes, cancel GET, responsive layout.');
} finally {
    socket?.close();
    browser.kill('SIGTERM');
    await new Promise(resolve => server.close(resolve));
    await sleep(100);
    rmSync(profile, { recursive: true, force: true });
}
