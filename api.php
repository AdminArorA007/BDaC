<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$config = [
    'cache_ttl' => 600, // 10 minutes
    'tlds' => [
        'Popular'  => ['com', 'net', 'org', 'co', 'io', 'ai', 'app', 'dev', 'xyz', 'me'],
        'Regional' => ['in', 'co.in', 'org.in', 'uk', 'co.uk', 'ca', 'de', 'eu'],
        'Tech'     => ['tech', 'cloud', 'software', 'digital', 'systems', 'code', 'site'],
    ],
];

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$action = $_GET['action'] ?? 'check';

// 1. Return configured TLDs to frontend
if ($action === 'config') {
    echo json_encode(['tlds' => $config['tlds']]);
    exit;
}

// 2. Inspect full RDAP details for a "Taken" domain
if ($action === 'inspect') {
    $domain = strtolower(trim($_GET['domain'] ?? ''));
    if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $domain)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid domain format']);
        exit;
    }
    echo json_encode(inspectDomainRdap($domain, $cacheDir));
    exit;
}

// 3. Check availability for a single domain
$domain = strtolower(trim($_GET['domain'] ?? ''));
if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $domain)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid domain']);
    exit;
}

$cacheFile = $cacheDir . '/dom_' . md5($domain) . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $config['cache_ttl']) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached)) {
        $cached['cached'] = true;
        echo json_encode($cached);
        exit;
    }
}

$start = microtime(true);
$result = checkViaRdapAndDns($domain, $cacheDir);
$result['domain'] = $domain;
$result['ms'] = (int) round((microtime(true) - $start) * 1000);
$result['cached'] = false;

if ($result['status'] !== 'error') {
    @file_put_contents($cacheFile, json_encode($result));
}

echo json_encode($result);
exit;

/**
 * Fetch and cache IANA RDAP Bootstrap JSON (covers 1,200+ TLDs)
 */
function getRdapBootstrap(string $cacheDir): array
{
    $bootstrapFile = $cacheDir . '/iana_rdap_dns.json';
    if (file_exists($bootstrapFile) && (time() - filemtime($bootstrapFile)) < 86400) {
        $data = json_decode((string) file_get_contents($bootstrapFile), true);
        if (is_array($data)) {
            return $data;
        }
    }

    $ch = curl_init('https://data.iana.org/rdap/dns.json');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'PortableDomainChecker/1.0',
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    $parsed = json_decode((string) $raw, true);
    $map = [];
    if (!empty($parsed['services'])) {
        foreach ($parsed['services'] as $service) {
            [$tlds, $urls] = $service;
            $baseUrl = rtrim($urls[0] ?? '', '/') . '/';
            foreach ($tlds as $tld) {
                $map[strtolower($tld)] = $baseUrl;
            }
        }
        @file_put_contents($bootstrapFile, json_encode($map));
    }

    return $map;
}

/**
 * Check availability using authoritative RDAP, falling back to DNS NS/SOA check
 */
function checkViaRdapAndDns(string $domain, string $cacheDir): array
{
    $parts = explode('.', $domain);
    $tld = end($parts);
    $rdapMap = getRdapBootstrap($cacheDir);

    if (isset($rdapMap[$tld])) {
        $url = $rdapMap[$tld] . 'domain/' . rawurlencode($domain);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 7,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json'],
            CURLOPT_USERAGENT => 'PortableDomainChecker/1.0',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 404) {
            return ['status' => 'available', 'method' => 'RDAP'];
        }
        if ($code === 200 && !empty($body)) {
            return ['status' => 'taken', 'method' => 'RDAP'];
        }
    }

    // Fallback for ccTLDs without RDAP (e.g., .co.in, .uk)
    $hasDns = checkdnsrr($domain, 'NS') || checkdnsrr($domain, 'SOA') || checkdnsrr($domain, 'A');
    return [
        'status' => $hasDns ? 'taken' : 'available',
        'method' => 'DNS',
    ];
}

/**
 * Fetch registrar, creation date, expiry date, and nameservers via RDAP
 */
function inspectDomainRdap(string $domain, string $cacheDir): array
{
    $parts = explode('.', $domain);
    $tld = end($parts);
    $rdapMap = getRdapBootstrap($cacheDir);

    if (!isset($rdapMap[$tld])) {
        $ns = @dns_get_record($domain, DNS_NS) ?: [];
        return [
            'domain' => $domain,
            'source' => 'DNS Only (Registry does not publish public RDAP)',
            'nameservers' => array_column($ns, 'target'),
        ];
    }

    $url = $rdapMap[$tld] . 'domain/' . rawurlencode($domain);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json'],
        CURLOPT_USERAGENT => 'PortableDomainChecker/1.0',
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        return ['domain' => $domain, 'error' => 'RDAP details unavailable (HTTP ' . $code . ')'];
    }

    $json = json_decode((string) $raw, true);
    $events = [];
    foreach ($json['events'] ?? [] as $ev) {
        if (!empty($ev['eventAction']) && !empty($ev['eventDate'])) {
            $events[$ev['eventAction']] = substr($ev['eventDate'], 0, 10);
        }
    }

    $registrar = 'Unknown';
    foreach ($json['entities'] ?? [] as $ent) {
        if (in_array('registrar', $ent['roles'] ?? [], true)) {
            $vcard = $ent['vcardArray'][1] ?? [];
            foreach ($vcard as $row) {
                if (($row[0] ?? '') === 'fn' && !empty($row[3])) {
                    $registrar = $row[3];
                    break 2;
                }
            }
        }
    }

    $nameservers = [];
    foreach ($json['nameservers'] ?? [] as $ns) {
        if (!empty($ns['ldhName'])) {
            $nameservers[] = strtolower($ns['ldhName']);
        }
    }

    return [
        'domain'      => $domain,
        'registrar'   => $registrar,
        'registered'  => $events['registration'] ?? 'N/A',
        'expires'     => $events['expiration'] ?? 'N/A',
        'updated'     => $events['last changed'] ?? 'N/A',
        'status'      => $json['status'] ?? [],
        'nameservers' => $nameservers,
    ];
}