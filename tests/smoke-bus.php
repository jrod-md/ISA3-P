<?php
declare(strict_types=1);

// Portable counterpart of scripts/smoke-test.ps1. Owns only its PHP children.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';

use Marketplace\Bus\Config\Config;
use Marketplace\Shared\Support\Database;

$services = [
    'alpha' => [8101, 'services/provider-alpha/public'],
    'beta' => [8102, 'services/provider-beta/public'],
    'gamma' => [8103, 'services/provider-gamma/public'],
    'bus' => [8000, 'apps/bus/public'],
];
$processes = [];
$searchIds = [];
$passed = 0;
$failed = 0;
$logDir = PROJECT_ROOT . '/.runtime/smoke-bus-' . bin2hex(random_bytes(5));
$http = null;

function expect(bool $condition, string $name): void {
    global $passed, $failed;
    if ($condition) { $passed++; echo "PASS {$name}\n"; }
    else { $failed++; echo "FAIL {$name}\n"; }
}

function request(string $url, string $method = 'GET', ?array $data = null): array {
    global $http;
    $http ??= curl_init();
    curl_setopt_array($http, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 8, CURLOPT_COOKIEFILE => '',
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $data === null ? [] : ['Content-Type: application/json']]);
    curl_setopt($http, CURLOPT_POSTFIELDS, $data === null ? null : json_encode($data));
    // Reset the method after CURLOPT_POSTFIELDS, which can otherwise select POST.
    curl_setopt($http, CURLOPT_CUSTOMREQUEST, $method);
    $body = curl_exec($http);
    if ($body === false) { throw new RuntimeException(curl_error($http)); }
    return ['status' => curl_getinfo($http, CURLINFO_RESPONSE_CODE), 'body' => $body,
        'json' => json_decode($body, true)];
}

function search(string $query): array {
    global $searchIds;
    $response = request('http://127.0.0.1:8000/api/search?' . $query);
    if ($response['status'] !== 200 || !is_array($response['json'])) {
        throw new RuntimeException('Search failed: ' . $response['body']);
    }
    $searchIds[] = $response['json']['search_id'];
    return $response['json'];
}

function startService(string $name): void {
    global $services, $processes, $logDir;
    [$port, $directory] = $services[$name];
    $public = PROJECT_ROOT . '/' . $directory;
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $public, $public . '/index.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $logDir . '/' . $name . '.out.log', 'a'],
            2 => ['file', $logDir . '/' . $name . '.err.log', 'a']], $pipes, PROJECT_ROOT);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start ' . $name); }
    fclose($pipes[0]);
    $processes[$name] = $process;
    for ($attempt = 0; $attempt < 40; $attempt++) {
        if (!proc_get_status($process)['running']) { break; }
        try {
            if ((request('http://127.0.0.1:' . $port . '/health')['json']['status'] ?? null) === 'ok') { return; }
        } catch (Throwable) { }
        usleep(100000);
    }
    throw new RuntimeException('Service unavailable: ' . $name . '; see ' . $logDir);
}

function stopService(string $name): void {
    global $processes;
    if (!isset($processes[$name])) { return; }
    $process = $processes[$name];
    if (proc_get_status($process)['running']) { proc_terminate($process); }
    proc_close($process);
    unset($processes[$name]);
}

try {
    // Fail before starting anything if another instance occupies a project port.
    foreach ($services as [$port]) {
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, 0.2);
        if ($socket !== false) {
            fclose($socket);
            throw new RuntimeException("Port {$port} is occupied. Stop your own development runtime before this test.");
        }
    }
    mkdir($logDir, 0700, true);
    // Test-only overrides inherited by children; no .env changes or DB server control.
    putenv('SEARCH_TTL_SECONDS=3');
    putenv('CLEANUP_INTERVAL_SECONDS=1');
    putenv('PROVIDER_ALPHA_URL=http://127.0.0.1:8101');
    putenv('PROVIDER_BETA_URL=http://127.0.0.1:8102');
    putenv('PROVIDER_GAMMA_URL=http://127.0.0.1:8103');
    foreach (array_keys($services) as $name) { startService($name); }
    $worker = proc_open([PHP_BINARY, PROJECT_ROOT . '/scripts/cleanup-worker.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $logDir . '/worker.out.log', 'a'],
            2 => ['file', $logDir . '/worker.err.log', 'a']], $pipes, PROJECT_ROOT);
    if (!is_resource($worker)) { throw new RuntimeException('Cannot start worker'); }
    fclose($pipes[0]); $processes['worker'] = $worker;

    foreach ($services as $name => [$port]) {
        expect((request('http://127.0.0.1:' . $port . '/health')['json']['status'] ?? null) === 'ok', $name . ' health');
    }
    expect(str_contains(request('http://127.0.0.1:8000/')['body'], 'Búsqueda unificada'), 'Search UI');
    expect(str_contains(request('http://127.0.0.1:8000/admin')['body'], 'id="login-form"'), 'Original login UI');
    foreach (['alpha' => 8101, 'beta' => 8102, 'gamma' => 8103] as $name => $port) {
        $response = request('http://127.0.0.1:' . $port . '/api/products?q=laptop&category=computers&max_price=900');
        expect($response['status'] === 200 && str_contains(strtolower($response['body']), 'laptop'), $name . ' combined filters');
        $injection = request('http://127.0.0.1:' . $port . '/api/products?q=' . rawurlencode("' OR 1=1 --"))['json'];
        expect(($injection['count'] ?? $injection['total_items'] ?? $injection['matches'] ?? -1) === 0, $name . ' SQL literal');
    }
    $login = request('http://127.0.0.1:8000/api/admin/login', 'POST', [
        'username' => Config::string('ADMIN_USERNAME', 'admin'),
        'password' => Config::string('ADMIN_PASSWORD', 'demo-isa3-2026')]);
    expect($login['status'] === 200 && ($login['json']['authenticated'] ?? false), 'Demo admin authentication');
    $formsTest = proc_open([PHP_BINARY, PROJECT_ROOT . '/tests/smoke-black-box.php', 'http://127.0.0.1:8000'],
        [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $formsPipes, PROJECT_ROOT);
    if (!is_resource($formsTest)) { throw new RuntimeException('Cannot start black-box HTTP tests'); }
    fclose($formsPipes[0]);
    if (proc_close($formsTest) !== 0) { throw new RuntimeException('Black-box HTTP tests failed'); }
    $result = search('q=laptop&category=computers&max_price=900&provider=all&sort=price_asc');
    expect(count(array_filter($result['providers'], fn ($provider) => $provider['status'] === 'ok')) === 3 && $result['total'] > 0, 'All providers aggregated');
    expect(count(array_filter($result['results'], fn ($product) => isset($product['external_id'], $product['provider']))) === $result['total'], 'Normalized results');
    $prices = array_column($result['results'], 'price'); $sorted = $prices; sort($sorted, SORT_NUMERIC);
    expect($prices === $sorted, 'Global price sort');
    $id = $result['search_id'];
    expect((request('http://127.0.0.1:8000/api/search/' . $id)['json']['search_id'] ?? null) === $id, 'Cached SearchSession');
    $listed = request('http://127.0.0.1:8000/api/admin/searches')['json'];
    expect(in_array($id, array_column($listed['searches'], 'id'), true), 'Admin lists search');
    expect((request('http://127.0.0.1:8000/api/admin/searches/' . $id, 'DELETE')['json']['deleted'] ?? 0) === 1, 'Single delete');
    expect(request('http://127.0.0.1:8000/api/search/' . $id)['status'] === 404, 'Cache cascade after delete');

    stopService('gamma');
    $partial = search('q=laptop&provider=all');
    expect($partial['providers']['gamma']['status'] === 'error' && $partial['total'] > 0 && count($partial['warnings']) > 0, 'Partial provider failure');
    stopService('alpha'); stopService('beta');
    $unavailable = request('http://127.0.0.1:8000/api/search?q=laptop&provider=all');
    expect($unavailable['status'] === 502 && ($unavailable['json']['error']['code'] ?? null) === 'ALL_PROVIDERS_FAILED', 'Total provider failure');
    foreach (['alpha', 'beta', 'gamma'] as $name) { startService($name); }
    $expiring = search('q=mouse&provider=alpha');
    sleep(5);
    $pdo = Database::connect(Config::string('BUS_DB_NAME', 'bus_meta'));
    $query = $pdo->prepare('SELECT COUNT(*) FROM search_sessions s LEFT JOIN search_cache c ON c.search_id = s.id WHERE s.id = ?');
    $query->execute([$expiring['search_id']]);
    $cacheQuery = $pdo->prepare('SELECT COUNT(*) FROM search_cache WHERE search_id = ?');
    $cacheQuery->execute([$expiring['search_id']]);
    expect(request('http://127.0.0.1:8000/api/search/' . $expiring['search_id'])['status'] === 404
        && (int) $query->fetchColumn() === 0 && (int) $cacheQuery->fetchColumn() === 0
        && proc_get_status($worker)['running'], 'Worker physically expires session and cache');
    // Delete-all affects every search: exercise it only on the disposable CI database.
    // On a local database use single deletion and preserve unrelated searches.
    $one = search('q=monitor&provider=alpha');
    $two = search('q=phone&provider=beta');
    if (getenv('GITHUB_ACTIONS') === 'true') {
        $deleted = request('http://127.0.0.1:8000/api/admin/searches', 'DELETE')['json'];
        $final = request('http://127.0.0.1:8000/api/admin/searches')['json'];
        expect(($deleted['deleted'] ?? 0) >= 2 && $final['count'] === 0, 'Delete all (CI database)');
    } else {
        $ok = true;
        foreach ([$one, $two] as $item) {
            $ok = $ok && (request('http://127.0.0.1:8000/api/admin/searches/' . $item['search_id'], 'DELETE')['json']['deleted'] ?? 0) === 1;
        }
        expect($ok, 'Delete own temporary searches (local database)');
    }
} catch (Throwable $error) {
    $failed++; fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n");
} finally {
    // Also clean owned search rows on assertion/setup failure, without touching QA data.
    if ($searchIds !== []) {
        try {
            $delete = Database::connect(Config::string('BUS_DB_NAME', 'bus_meta'))->prepare('DELETE FROM search_sessions WHERE id = ?');
            foreach ($searchIds as $id) { $delete->execute([$id]); }
        } catch (Throwable $error) { $failed++; fwrite(STDERR, 'Cleanup failed: ' . $error->getMessage() . "\n"); }
    }
    foreach (array_reverse(array_keys($processes)) as $name) { stopService($name); }
}
echo "Summary: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
