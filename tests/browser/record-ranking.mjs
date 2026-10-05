// Real Blade controls and bundled Alpine. Pilot mode reads existing data; neither mode writes registry records.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFileSync, mkdtempSync, existsSync } from 'node:fs';
import { rm } from 'node:fs/promises';
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
Illuminate\Support\Facades\View::share('errors', $errors);
$purok = (new App\Models\Purok(['purok_number' => 5, 'purok_name' => 'Fixture']))->setAttribute('id', 1);
$households = collect(range(1, 100))->map(function ($number) use ($purok) {
    $household = (new App\Models\Household(['household_no' => (string)$number, 'household_address' => 'Purok 5']))->setAttribute('id', $number);
    return $household->setRelation('purok', $purok)->setRelation('headResident', null);
})->sortBy('household_no', SORT_STRING)->values();
$resident = (new App\Models\Resident(['first_name' => 'Juan', 'last_name' => 'Santos', 'official_resident_code' => 'PS-EXACT-5']))->setAttribute('id', 1);
$resident->setRelation('household', $households->first());
$residents = collect([$resident]);
if (getenv('SEARCH_RANKING_PILOT') === '1') {
    $barangayId = App\Models\Barangay::where('name', 'Pooc Oriental')->value('id');
    if (!$barangayId) throw new RuntimeException('Pooc Oriental is required for pilot verification.');
    $households = App\Models\Household::whereHas('purok', fn($q) => $q->where('barangay_id', $barangayId))
        ->active()->with(['purok', 'headResident'])->orderBy('household_no')->get();
    $residents = App\Models\Resident::whereHas('household.purok', fn($q) => $q->where('barangay_id', $barangayId))
        ->active()->where('resident_status', 'active')->with('household.purok')->orderBy('last_name')->orderBy('first_name')->get();
}
$groups = $households->groupBy(fn($h) => $h->purok->id);
$worstPosition = -1; $selected = collect();
foreach ($groups as $group) {
    $matches = $group->filter(fn($h) => str_contains(strtolower($h->household_no.' '.$h->household_address), '5'))->values();
    $position = $matches->search(fn($h) => $h->household_no === '5');
    if ($position !== false && $position > $worstPosition) { $worstPosition = $position; $selected = $group->values(); }
}
if ($worstPosition < 12) throw new RuntimeException('Fixture must reproduce #5 beyond the original cap.');
$exact = $selected->firstWhere('household_no', '5');
$purok = $exact->purok;
$options = $selected->map(fn($h) => ['id' => $h->id, 'household_no' => $h->household_no, 'household_address' => $h->household_address])->values()->all();
$render = fn($source, $vars = []) => Illuminate\Support\Facades\Blade::render($source, $vars + ['errors' => $errors, 'routePrefix' => 'secretary']);
$scripts = [];
foreach (['create', 'edit'] as $kind) {
    preg_match('/<script>(.*?)<\/script>/s', file_get_contents('resources/views/admin/geometry/residents/'.$kind.'.blade.php'), $script);
    $scripts[$kind] = $render($script[1]);
}
$source = file_get_contents('resources/views/admin/geometry/residents/partials/form.blade.php');
$start = strpos($source, '<label for="household_id"');
$end = strpos($source, "\n        @error('household_id')", $start);
$control = $render(substr($source, $start, $end-$start));
$source = file_get_contents('resources/views/secretary/residents/relocate.blade.php');
preg_match('/<script>(.*?)<\/script>/s', $source, $script);
$scripts['relocate'] = $script[1];
$start = strpos($source, '<label for="target_household_id"');
$end = strpos($source, "\n                        @error('target_household_id')", $start);
$relocation = $render(substr($source, $start, $end-$start));
$source = file_get_contents('resources/views/secretary/certificates/create.blade.php');
$start = strpos($source, '$residentOptions =');
$end = strpos($source, '$errorStep =', $start);
$certificate = $render('@php '.substr($source, $start, $end-$start).' @endphp
<x-searchable-record-select name="resident_id" :options="$residentOptions" />
<x-searchable-record-select name="household_id" :options="$householdOptions" />', compact('households', 'residents'));
$coded = $residents->first(fn($r) => $r->official_resident_code);
echo json_encode(['scripts' => $scripts, 'control' => $control, 'relocation' => $relocation, 'certificate' => $certificate,
    'options' => $options, 'purok' => (string)$purok->id, 'exactId' => (string)$exact->id, 'oldPosition' => $worstPosition+1,
    'code' => $coded?->official_resident_code, 'codedId' => (string)$coded?->id,
    'name' => $residents->first()->formal_name, 'residentCount' => $residents->count(), 'householdCount' => $households->count(),
    'exactHouseholdCount' => $households->where('household_no', '5')->count()]);
`], { cwd: root, encoding: 'utf8', maxBuffer: 20 * 1024 * 1024 }));
const requests = [];
const server = createServer((req, res) => {
    if (req.url === '/app.css') { res.setHeader('Content-Type', 'text/css'); return res.end(css); }
    if (req.url === '/app.js') { res.setHeader('Content-Type', 'text/javascript'); return res.end(js); }
    if (req.url.startsWith('/households?')) {
        requests.push(req.url); res.setHeader('Content-Type', 'application/json'); return res.end(JSON.stringify(fragments.options));
    }
    const kind = req.url.slice(1);
    const state = kind === 'certificate' ? '{}' : kind === 'relocate'
        ? `residentRelocation('/households', 'existing_household', '${fragments.purok}', '', [])`
        : `residentForm('/puroks', '/households', '1', '', '', [{id:'${fragments.purok}'}], [])`;
    const controls = kind === 'certificate' ? fragments.certificate : kind === 'relocate' ? fragments.relocation
        : `<select id="purok" x-model="purokId" @change="loadHouseholds()"><option value="">Choose Purok</option><option value="${fragments.purok}">Selected Purok</option></select>${fragments.control}`;
    res.setHeader('Content-Type', 'text/html');
    res.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/app.css"></head><body><main class="p-4"><form method="${kind === 'certificate' ? 'POST' : 'GET'}" x-data="${state}">${controls}</form></main><script>${fragments.scripts[kind] ?? ''}</script><script type="module" src="/app.js"></script></body></html>`);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;
const profile = mkdtempSync(`${tmpdir()}/healthlink-ranking-`);
const browser = spawn(browserBin, ['--headless=new', '--no-sandbox', '--disable-gpu', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
let closeBrowser;
try {
    for (let i = 0; i < 150 && !existsSync(`${profile}/DevToolsActivePort`); i++) await sleep(30);
    const port = readFileSync(`${profile}/DevToolsActivePort`, 'utf8').split('\n')[0];
    const tabs = await fetch(`http://127.0.0.1:${port}/json/list`).then(response => response.json());
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    let id = 0; const pending = new Map(); const errors = [];
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
        if (!message.id) return;
        const task = pending.get(message.id); pending.delete(message.id);
        message.error ? task.reject(message.error) : task.resolve(message.result);
    });
    const call = (method, params = {}) => new Promise((resolve, reject) => {
        const current = ++id;
        const timeout = setTimeout(() => { pending.delete(current); reject(new Error(`CDP timeout: ${method}`)); }, 10000);
        pending.set(current, { resolve: value => { clearTimeout(timeout); resolve(value); }, reject: value => { clearTimeout(timeout); reject(value); } });
        socket.send(JSON.stringify({ id: current, method, params }));
    });
    closeBrowser = () => call('Browser.close');
    const evaluate = async expression => {
        const result = await call('Runtime.evaluate', { expression, returnByValue: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
        return result.result.value;
    };
    const wait = async expression => {
        for (let i = 0; i < 150; i++) { if (await evaluate(expression)) return; await sleep(30); }
        throw new Error(`Timeout: ${expression}; errors=${JSON.stringify(errors)}; state=${JSON.stringify(await evaluate('({url:location.href,alpine:typeof window.Alpine,form:document.querySelector("form")?.getAttribute("x-data")})'))}`);
    };
    const type = async (selector, value) => evaluate(`{ const input=document.querySelector(${JSON.stringify(selector)});input.focus();input.value=${JSON.stringify(value)};input.dispatchEvent(new Event('input',{bubbles:true})); }`);
    await call('Page.enable');
    await call('Runtime.enable');
    for (const kind of ['create', 'edit', 'relocate']) {
        await call('Page.navigate', { url: `${base}/${kind}` });
        await wait('window.Alpine && document.querySelector("form")._x_dataStack');
        if (kind !== 'relocate') await evaluate(`{ const select=document.querySelector('#purok');select.value='${fragments.purok}';select.dispatchEvent(new Event('change',{bubbles:true})); }`);
        const input = kind === 'relocate' ? '#target_household_id' : '#household_id';
        await wait(`!document.querySelector('${input}').disabled`);
        await type(input, '5');
        await wait(`Alpine.$data(document.querySelector('form')).filteredHouseholds[0]?.id == '${fragments.exactId}'`);
        assert.equal(await evaluate('Alpine.$data(document.querySelector("form")).filteredHouseholds.length'), 12);
        await evaluate(`document.querySelector('${input}').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}));`);
        const field = kind === 'relocate' ? 'target_household_id' : 'household_id';
        assert.equal(await evaluate(`new FormData(document.querySelector('form')).get('${field}')`), fragments.exactId);
        await type(input, '5');
        await evaluate(`document.querySelector('${input}').parentElement.querySelector('button').dispatchEvent(new MouseEvent('mousedown',{bubbles:true,cancelable:true}));`);
        assert.equal(await evaluate(`new FormData(document.querySelector('form')).get('${field}')`), fragments.exactId);
        await type(input, '5');
        const secondId = String(await evaluate('Alpine.$data(document.querySelector("form")).filteredHouseholds[1].id'));
        await evaluate(`{ const input=document.querySelector('${input}');input.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true,cancelable:true}));input.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true})); }`);
        assert.equal(await evaluate(`new FormData(document.querySelector('form')).get('${field}')`), secondId);
    }
    await call('Page.navigate', { url: `${base}/certificate` });
    await wait('window.Alpine && document.querySelector("#household_id").parentElement._x_dataStack');
    await type('#household_id', '5');
    await wait('Alpine.$data(document.querySelector("#household_id").parentElement).filteredOptions.length === 12');
    const householdResults = await evaluate('Alpine.$data(document.querySelector("#household_id").parentElement).filteredOptions.map(o=>({id:o.value,identifier:o.ranking.primaryIdentifier,description:o.description}))');
    assert.equal(householdResults[0].identifier, '5');
    assert.ok(householdResults.slice(0, Math.min(12, fragments.exactHouseholdCount)).every(option => option.identifier === '5'));
    const timings = await evaluate(`{ const selector=Alpine.$data(document.querySelector('#household_id').parentElement);const start=performance.now();for(let i=0;i<20;i++)selector.refreshResults();window.rankingMs=(performance.now()-start)/20; } window.rankingMs`);
    if (fragments.code) {
        await type('#resident_id', fragments.code);
        await wait(`Alpine.$data(document.querySelector('#resident_id').parentElement).filteredOptions[0]?.value === '${fragments.codedId}'`);
        await evaluate("document.querySelector('#resident_id').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}));");
        assert.equal(await evaluate('new FormData(document.querySelector("form")).get("resident_id")'), fragments.codedId);
    }
    await type('#resident_id', fragments.name);
    await wait(`Alpine.$data(document.querySelector('#resident_id').parentElement).filteredOptions[0]?.label === ${JSON.stringify(fragments.name)}`);
    const residentTimings = await evaluate(`{ const selector=Alpine.$data(document.querySelector('#resident_id').parentElement);const start=performance.now();for(let i=0;i<20;i++)selector.refreshResults();window.residentRankingMs=(performance.now()-start)/20; } window.residentRankingMs`);
    // Exercise the shared Blade/Alpine combobox with real keyboard focus and form bindings.
    await evaluate(`window.selectorSubmits=0;document.querySelector('form').addEventListener('submit',event=>{event.preventDefault();window.selectorSubmits++;});`);
    const key = async (key, modifiers = 0) => {
        const code = { Enter: 13, Tab: 9, Escape: 27, ArrowDown: 40, ArrowUp: 38 }[key];
        await call('Input.dispatchKeyEvent', { type: 'keyDown', key, modifiers, windowsVirtualKeyCode: code });
        await call('Input.dispatchKeyEvent', { type: 'keyUp', key, modifiers, windowsVirtualKeyCode: code });
    };
    assert.equal(await evaluate("document.querySelector('#resident_id').getAttribute('role')"), 'combobox');
    assert.equal(await evaluate("document.querySelector('#resident_id').getAttribute('aria-autocomplete')"), 'list');
    assert.equal(await evaluate("new Set([...document.querySelectorAll('[role=listbox]')].map(list=>list.id)).size"), 2);
    const active = () => evaluate("document.querySelector('#resident_id').getAttribute('aria-activedescendant')");
    assert.equal(await evaluate("document.querySelector('#resident_id').getAttribute('aria-expanded')"), 'true');
    assert.equal(await evaluate("document.getElementById(document.querySelector('#resident_id').getAttribute('aria-activedescendant')).getAttribute('role')"), 'option');
    assert.equal(await evaluate("[...document.querySelectorAll('[role=option]')].every(option=>option.tabIndex===-1)"), true);
    assert.equal(await evaluate('document.activeElement.id'), 'resident_id', 'Input must have focus before native keyboard events');
    await key('ArrowDown'); await key('ArrowUp');
    const chosen = await evaluate("Alpine.$data(document.querySelector('#resident_id').parentElement).filteredOptions[0].value");
    await key('Enter');
    assert.equal(await evaluate("new FormData(document.querySelector('form')).get('resident_id')"), chosen);
    assert.equal(await evaluate('window.selectorSubmits'), 0);
    assert.equal(await evaluate("document.activeElement.id"), 'resident_id');
    assert.equal(await active(), null);
    await key('ArrowDown');
    await wait("document.querySelector('#resident_id').getAttribute('aria-expanded')==='true'");
    assert.equal(await evaluate("document.querySelector('#resident_id').getAttribute('aria-activedescendant')===document.querySelector('#resident_id').getAttribute('aria-controls')+'-option-0'"), true);
    assert.equal(await evaluate("document.getElementById(document.querySelector('#resident_id').getAttribute('aria-activedescendant')).getAttribute('aria-selected')"), 'true');
    await key('Escape');
    assert.equal(await evaluate("new FormData(document.querySelector('form')).get('resident_id')"), chosen);
    assert.equal(await evaluate("document.querySelector('#resident_id').getAttribute('aria-expanded')"), 'false');
    await key('ArrowUp'); await key('Tab');
    assert.equal(await evaluate('document.activeElement.id'), 'household_id');
    assert.equal(await evaluate("document.querySelector('#resident_id').getAttribute('aria-expanded')"), 'false');
    await key('Tab', 8);
    assert.equal(await evaluate('document.activeElement.id'), 'resident_id');
    await type('#resident_id', 'no-such-fixture-resident');
    await wait("Alpine.$data(document.querySelector('#resident_id').parentElement).filteredOptions.length===0");
    await key('ArrowDown'); await key('ArrowUp'); await key('Enter');
    assert.equal(await active(), null);
    assert.equal(await evaluate('window.selectorSubmits'), 0);
    await type('#resident_id', fragments.name);
    await wait("document.querySelector('#resident_id').getAttribute('aria-activedescendant')!==null");
    await evaluate("document.querySelector('#resident_id').parentElement.querySelector('[role=option]').click()");
    assert.equal(await evaluate("new FormData(document.querySelector('form')).get('resident_id')"), chosen);
    await type('#resident_id', fragments.name);
    await wait("document.querySelector('#resident_id').getAttribute('aria-expanded')==='true'");
    await evaluate("document.querySelector('#resident_id').parentElement.querySelector('[role=option]').dispatchEvent(new MouseEvent('mousedown',{bubbles:true,cancelable:true}))");
    assert.equal(await evaluate("new FormData(document.querySelector('form')).get('resident_id')"), chosen);
    assert.deepEqual(errors, []);
    await call('Emulation.setDeviceMetricsOverride', { width: 375, height: 812, deviceScaleFactor: 1, mobile: true });
    assert.equal(await evaluate('document.documentElement.scrollWidth <= window.innerWidth'), true);
    assert.equal(requests.length, 3);
    if (process.env.DEPENDENT_OPTIONS_RACE === '1') {
        for (const kind of ['create', 'edit']) {
            await call('Page.navigate', { url: `${base}/${kind}` });
            await wait('window.Alpine && document.querySelector("form")._x_dataStack');
            const state = 'Alpine.$data(document.querySelector("form"))';
            await evaluate(`{
                window.lookupRequests=[];
                window.fetch = url => new Promise((resolve,reject) => window.lookupRequests.push({
                    url, reject, success: data => resolve({ok:true,json:async()=>data}),
                    fail: () => resolve({ok:false,json:async()=>({})})
                }));
                const select=document.querySelector('#purok');
                select.add(new Option('Other Purok','B'));
                ${state}.purokId='${fragments.purok}';
                ${state}.households=${JSON.stringify(fragments.options)};
                ${state}.selectHousehold(${state}.households[0]);
            }`);
            await wait(`document.querySelector('input[name=household_id]').value !== ''`);
            const change = value => evaluate(`{
                const select=document.querySelector('#purok');select.value=${JSON.stringify(value)};
                select.dispatchEvent(new Event('change',{bubbles:true}));
            }`);
            await change(fragments.purok);
            await wait('window.lookupRequests.length===1');
            assert.equal(await evaluate(`${state}.householdId`), '');
            assert.equal(await evaluate('document.querySelector("#household_id").disabled'), true);
            await change('B'); await wait('window.lookupRequests.length===2');
            await evaluate('window.lookupRequests[1].success([{id:999,household_no:"B",household_address:"Current Purok"}])');
            await wait(`${state}.households[0]?.id===999 && !${state}.householdLoading`);
            await evaluate('window.lookupRequests[0].success([{id:888,household_no:"A",household_address:"Stale Purok"}])');
            await evaluate('0');
            assert.equal(await evaluate(`${state}.households[0].id`), 999);
            await evaluate(`${state}.selectHousehold(${state}.households[0])`);
            await change(fragments.purok); await wait('window.lookupRequests.length===3');
            assert.equal(await evaluate(`${state}.householdId`), '');
            await evaluate('window.lookupRequests[2].fail()');
            await wait(`${state}.householdError !== '' && !${state}.householdLoading`);
            assert.equal(await evaluate(`${state}.households.length`), 0);
            assert.equal(await evaluate('document.querySelector("#household_id").disabled'), true);
            await wait(`[...document.querySelectorAll('[role=status]')].some(p=>p.textContent.includes('Unable to load households') && getComputedStyle(p).display!=='none')`);
            await change('B'); await wait('window.lookupRequests.length===4');
            await change(''); await evaluate('window.lookupRequests[3].success([{id:999,household_no:"B",household_address:"Late"}])');
            await evaluate('0');
            assert.equal(await evaluate(`${state}.households.length`), 0);
            assert.equal(await evaluate(`${state}.householdId`), '');
            assert.equal(await evaluate(`${state}.householdError`), '');
        }
        assert.deepEqual(errors, []);
        console.log('Resident dependent-options browser checks passed: real create/edit Alpine controls, immediate clearing, reversed responses, current HTTP failure/status, clear during flight.');
    }
    console.log(JSON.stringify({ result: 'passed', mode: process.env.SEARCH_RANKING_PILOT === '1' ? 'read-only pilot data' : 'synthetic fixture',
        households: fragments.householdCount, residents: fragments.residentCount, originalExactPosition: fragments.oldPosition,
        rankedExactPosition: 1, householdRankMs: Number(timings.toFixed(2)), residentRankMs: Number(residentTimings.toFixed(2)),
        paths: ['create', 'edit', 'relocation', 'certificate household', 'certificate resident'], selectorAccessibility: 'passed' }));
} finally {
    await closeBrowser?.().catch(() => {});
    socket?.close();
    if (browser.exitCode === null) browser.kill('SIGTERM');
    await new Promise(resolve => browser.exitCode !== null ? resolve() : browser.once('exit', resolve));
    await new Promise(resolve => server.close(resolve));
    await rm(profile, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 });
}
