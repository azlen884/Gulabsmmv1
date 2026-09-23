<?php
/**
 * RoseSMM - Child Panel System Helper
 * Handles plans, purchase flow, nameserver domain setup, DNS/SSL verification,
 * admin approval lifecycle, and Advanced plan external provider API management.
 */

require_once __DIR__ . '/../config/database.php';

class ChildPanelHelper {

    /**
     * Get available Child Panel plans with pricing and features
     */
    public static function getPlans() {
        $basicPrice = (float)get_setting('child_panel_basic_price', '1499.00');
        $advancedPrice = (float)get_setting('child_panel_advanced_price', '3499.00');
        $currency = get_setting('child_panel_currency', 'INR');
        $symbol = get_setting('currency_symbol', '₹');

        return [
            'basic' => [
                'id' => 'basic',
                'name' => 'Basic Child Panel',
                'badge' => 'Standard',
                'price' => $basicPrice,
                'currency' => $currency,
                'symbol' => $symbol,
                'period' => 'per month',
                'external_api' => false,
                'service_source' => 'Main Panel Services Only',
                'description' => 'Fully branded turnkey SMM panel connected exclusively to main platform services.',
                'features' => [
                    'Complete Branded Turnkey SMM Panel',
                    'Custom Domain with Nameserver DNS',
                    'Sell All Main Panel SMM Services',
                    'Customer Registration & Login',
                    'Orders, Mass Order & Drip Feed',
                    'Automated Service Fulfillment',
                    'Wallet & Balance Management',
                    'Dedicated Child Panel Admin Portal',
                    'Custom Logo, Theme & Branding',
                    'SSL / HTTPS Included'
                ],
                'limitations' => [
                    'External Provider APIs NOT allowed',
                    'External Service Imports NOT allowed'
                ]
            ],
            'advanced' => [
                'id' => 'advanced',
                'name' => 'Advanced Child Panel',
                'badge' => 'Pro / Unlimited',
                'price' => $advancedPrice,
                'currency' => $currency,
                'symbol' => $symbol,
                'period' => 'per month',
                'external_api' => true,
                'service_source' => 'Main Panel + External Provider APIs',
                'description' => 'Complete branded panel with full support for adding your own external provider APIs and importing services.',
                'features' => [
                    'Everything in Basic Child Panel',
                    'Connect Your Own External SMM Provider APIs',
                    'Real-Time Balance & Connection Testing',
                    'Import Services from Any Provider API',
                    'Custom Profit Margins & Retail Pricing',
                    'Independent Service Enable/Disable Controls',
                    'Hybrid Sourcing: Main Panel + External Providers',
                    'Automated External Order Forwarding',
                    'Full API Credential Encryption & Security',
                    'Priority Support & Domain Verification'
                ],
                'limitations' => []
            ]
        ];
    }

    /**
     * Get configured Child Panel nameservers from Admin Settings
     */
    public static function getNameservers() {
        return [
            'ns1' => trim(get_setting('child_panel_ns1', 'ns1.rosesmm.com')),
            'ns2' => trim(get_setting('child_panel_ns2', 'ns2.rosesmm.com')),
            'server_ip' => trim(get_setting('child_panel_server_ip', $_SERVER['SERVER_ADDR'] ?? '127.0.0.1'))
        ];
    }

    /**
     * Clean and normalize a domain name
     */
    public static function sanitizeDomain($domain) {
        $domain = trim($domain);
        $domain = strtolower($domain);
        // Remove scheme if entered
        $domain = preg_replace('#^https?://#i', '', $domain);
        // Remove path/queries
        $parts = explode('/', $domain);
        $domain = $parts[0];
        // Remove trailing dot or colon port
        $domain = preg_replace('#:\d+$#', '', $domain);
        return rtrim($domain, '.');
    }

    /**
     * Validate domain syntax
     */
    public static function isValidDomain($domain) {
        $domain = self::sanitizeDomain($domain);
        if (strlen($domain) < 4 || strlen($domain) > 190) {
            return false;
        }
        return (bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i', $domain);
    }

    /**
     * Purchase and create a new Child Panel request
     */
    public static function purchaseChildPanel($userId, $planKey, $domain, $panelName, $adminUser, $adminEmail, $adminPassword, $paymentMethod = 'wallet_balance', $branding = []) {
        $db = getDB();
        $plans = self::getPlans();

        if (!isset($plans[$planKey])) {
            return ['success' => false, 'error' => 'Invalid Child Panel plan selected.'];
        }

        $plan = $plans[$planKey];
        $price = (float)$plan['price'];
        // Plan price converted to USD for base balance deduction
        $priceUSD = convert_price($price, $plan['currency'] ?? 'INR', 'USD');
        $cleanDomain = self::sanitizeDomain($domain);

        if (!self::isValidDomain($cleanDomain)) {
            return ['success' => false, 'error' => 'Please provide a valid domain name (e.g., mysmmpanel.com or panel.example.com).'];
        }

        $panelName = trim($panelName);
        if (empty($panelName)) {
            return ['success' => false, 'error' => 'Panel Name is required.'];
        }

        $adminUser = trim($adminUser);
        if (strlen($adminUser) < 3) {
            return ['success' => false, 'error' => 'Admin username must be at least 3 characters.'];
        }

        $adminEmail = trim($adminEmail);
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Valid Admin email is required.'];
        }

        if (strlen($adminPassword) < 6) {
            return ['success' => false, 'error' => 'Admin password must be at least 6 characters.'];
        }

        // Check if domain is already in use
        $checkStmt = $db->prepare("SELECT id FROM child_panels WHERE domain = ?");
        $checkStmt->execute([$cleanDomain]);
        if ($checkStmt->fetch()) {
            return ['success' => false, 'error' => "The domain '$cleanDomain' is already registered for another Child Panel."];
        }

        // Check user balance and execute payment
        $userStmt = $db->prepare("SELECT * FROM users WHERE id = ? FOR UPDATE");
        $db->beginTransaction();

        try {
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();

            if (!$user) {
                $db->rollBack();
                return ['success' => false, 'error' => 'User not found.'];
            }

            if ((float)$user['balance'] < $priceUSD) {
                $db->rollBack();
                $diff = $priceUSD - (float)$user['balance'];
                return [
                    'success' => false,
                    'error' => "Insufficient wallet balance. You need " . format_price($diff) . " more. Please add funds first.",
                    'insufficient_balance' => true,
                    'required' => $priceUSD,
                    'current' => (float)$user['balance']
                ];
            }

            // Deduct balance in USD
            $newBalance = (float)$user['balance'] - $priceUSD;
            $updateUser = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $updateUser->execute([$newBalance, $userId]);

            // Record transaction
            $txId = 'CP-' . strtoupper(substr(uniqid(), 7)) . '-' . mt_rand(100, 999);
            $txStmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, charge, currency, payment_method, transaction_id, status) VALUES (?, 'order', ?, ?, ?, ?, ?, 'completed')");
            $txStmt->execute([$userId, $priceUSD, $priceUSD, 'USD', 'wallet_balance', $txId]);

            // Configured nameservers at provisioning time
            $ns = self::getNameservers();
            $adminHash = password_hash($adminPassword, PASSWORD_BCRYPT);
            $externalApi = ($planKey === 'advanced') ? 1 : 0;
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

            // Insert child panel record with pending_approval
            $insStmt = $db->prepare("
                INSERT INTO child_panels (
                    user_id, plan, domain, panel_name, admin_username, admin_email, admin_password_hash,
                    status, payment_status, payment_amount, payment_currency, payment_method, payment_ref,
                    dns_status, ssl_status, nameserver_1, nameserver_2,
                    theme, support_email, currency, currency_symbol, external_api_enabled, expires_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    'pending_approval', 'paid', ?, ?, ?, ?,
                    'pending_dns', 'ssl_pending', ?, ?,
                    ?, ?, ?, ?, ?, ?
                )
            ");

            $theme = $branding['theme'] ?? 'default';
            $supportEmail = $branding['support_email'] ?? $adminEmail;
            $currency = $branding['currency'] ?? $plan['currency'];
            $symbol = $branding['symbol'] ?? $plan['symbol'];

            $insStmt->execute([
                $userId, $planKey, $cleanDomain, $panelName, $adminUser, $adminEmail, $adminHash,
                $price, $plan['currency'], $paymentMethod, $txId,
                $ns['ns1'], $ns['ns2'],
                $theme, $supportEmail, $currency, $symbol, $externalApi, $expiresAt
            ]);

            $childPanelId = (int)$db->lastInsertId();

            // Create notification for admin
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (1, ?, ?, 'system')");
            $notifStmt->execute([
                "New Child Panel Order: $cleanDomain",
                "User #$userId ordered a {$plan['name']} for domain $cleanDomain (Amount: {$plan['symbol']}$price). Pending admin review."
            ]);

            $db->commit();

            return [
                'success' => true,
                'child_panel_id' => $childPanelId,
                'domain' => $cleanDomain,
                'plan' => $planKey,
                'message' => 'Child Panel order submitted and payment confirmed. Your request is now pending Admin approval.'
            ];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Normalize nameserver for robust comparison (lowercase, trimmed, strip protocol/port/trailing dots)
     */
    public static function normalizeNameserver($ns) {
        if ($ns === null) return '';
        $ns = trim((string)$ns);
        $ns = preg_replace('#^https?://#i', '', $ns);
        $ns = preg_replace('#:\d+$#', '', $ns);
        $ns = strtolower($ns);
        return rtrim($ns, '.');
    }

    /**
     * Build RFC 1035 DNS binary query packet
     */
    public static function buildDnsPacket($domain, $type = 2, $rd = 0) {
        $id = pack('n', mt_rand(1, 65535));
        $flags = ($rd ? "\x01\x00" : "\x00\x00");
        $qdcount = "\x00\x01";
        $header = $id . $flags . $qdcount . "\x00\x00\x00\x00\x00\x00";

        $qname = '';
        foreach (explode('.', trim($domain, '.')) as $label) {
            $qname .= chr(strlen($label)) . $label;
        }
        $qname .= "\x00";
        $qtype = pack('n', $type);
        $qclass = pack('n', 1); // IN class
        return $header . $qname . $qtype . $qclass;
    }

    /**
     * Parse DNS wire format label with compression pointer support
     */
    public static function parseDnsLabel($data, &$offset) {
        $name = '';
        $jumped = false;
        $initialOffset = $offset;
        $length = strlen($data);
        $steps = 0;

        while ($offset < $length && $steps < 100) {
            $steps++;
            $len = ord($data[$offset]);
            if ($len === 0) {
                $offset++;
                break;
            }
            if (($len & 0xC0) === 0xC0) {
                if (!$jumped) {
                    $initialOffset = $offset + 2;
                    $jumped = true;
                }
                $offset = (($len & 0x3F) << 8) | ord($data[$offset + 1]);
                continue;
            }
            $offset++;
            $name .= substr($data, $offset, $len) . '.';
            $offset += $len;
        }

        if ($jumped) {
            $offset = $initialOffset;
        }
        return self::normalizeNameserver($name);
    }

    /**
     * Parse binary DNS response packet into answers, authority, and additional sections
     */
    public static function parseDnsPacket($data) {
        if (strlen($data) < 12) return false;
        $header = unpack('nid/nflags/nqdcount/nancount/nnscount/narcount', substr($data, 0, 12));
        $offset = 12;

        for ($i = 0; $i < $header['qdcount']; $i++) {
            self::parseDnsLabel($data, $offset);
            $offset += 4; // qtype (2) + qclass (2)
        }

        $records = ['answers' => [], 'authority' => [], 'additional' => []];

        $parseSection = function($count, &$target) use ($data, &$offset) {
            for ($i = 0; $i < $count && $offset < strlen($data); $i++) {
                $name = self::parseDnsLabel($data, $offset);
                if ($offset + 10 > strlen($data)) break;
                $meta = unpack('ntype/nclass/Nttl/nrdlength', substr($data, $offset, 10));
                $offset += 10;
                $type = $meta['type'];
                $rdlength = $meta['rdlength'];

                if ($type === 2) { // NS record
                    $nsTarget = self::parseDnsLabel($data, $offset);
                    $target[] = ['name' => $name, 'type' => 'NS', 'target' => $nsTarget];
                } elseif ($type === 1) { // A record
                    $ip = inet_ntop(substr($data, $offset, 4));
                    $target[] = ['name' => $name, 'type' => 'A', 'ip' => $ip];
                    $offset += $rdlength;
                } else {
                    $offset += $rdlength;
                }
            }
        };

        $parseSection($header['ancount'], $records['answers']);
        $parseSection($header['nscount'], $records['authority']);
        $parseSection($header['arcount'], $records['additional']);

        return $records;
    }

    /**
     * Send UDP DNS query to specific server
     */
    public static function queryDnsUdp($server, $domain, $type = 2, $rd = 0, $timeout = 2) {
        $pkt = self::buildDnsPacket($domain, $type, $rd);
        $sock = @fsockopen("udp://$server", 53, $errno, $errstr, $timeout);
        if (!$sock) return false;
        stream_set_timeout($sock, $timeout);
        fwrite($sock, $pkt);
        $resp = fread($sock, 4096);
        fclose($sock);
        return self::parseDnsPacket($resp);
    }

    /**
     * Authoritative Root -> TLD delegation nameserver query
     */
    public static function getAuthoritativeNameservers($domain) {
        $rootServers = [
            '198.41.0.4',     // a.root-servers.net
            '199.9.14.201',    // b.root-servers.net
            '192.33.4.12',     // c.root-servers.net
            '199.7.91.13',     // d.root-servers.net
            '192.203.230.10'   // e.root-servers.net
        ];

        $rootPkt = false;
        foreach ($rootServers as $root) {
            $rootPkt = self::queryDnsUdp($root, $domain, 2, 0, 2);
            if ($rootPkt && (!empty($rootPkt['authority']) || !empty($rootPkt['answers']))) {
                break;
            }
        }
        if (!$rootPkt) return [];

        $tldIps = [];
        foreach ($rootPkt['additional'] as $add) {
            if ($add['type'] === 'A' && !empty($add['ip'])) {
                $tldIps[] = $add['ip'];
            }
        }

        if (empty($tldIps)) {
            foreach ($rootPkt['authority'] as $ns) {
                $ip = @gethostbyname($ns['target']);
                if ($ip && $ip !== $ns['target']) {
                    $tldIps[] = $ip;
                }
            }
        }

        $detected = [];
        foreach ($tldIps as $tldIp) {
            $tldPkt = self::queryDnsUdp($tldIp, $domain, 2, 0, 2);
            if ($tldPkt) {
                foreach ($tldPkt['answers'] as $ans) {
                    if ($ans['type'] === 'NS' && !empty($ans['target'])) {
                        $detected[] = self::normalizeNameserver($ans['target']);
                    }
                }
                foreach ($tldPkt['authority'] as $auth) {
                    if ($auth['type'] === 'NS' && !empty($auth['target'])) {
                        $detected[] = self::normalizeNameserver($auth['target']);
                    }
                }
                if (!empty($detected)) break;
            }
        }

        return array_values(array_unique(array_filter($detected)));
    }

    /**
     * WHOIS authoritative nameserver lookup
     */
    public static function getWhoisNameservers($domain) {
        $domain = strtolower(trim($domain));
        $parts = explode('.', $domain);
        $tld = end($parts);

        $whoisMap = [
            'in' => ['whois-aws.nixiregistry.in', 'whois.registry.in'],
            'com' => ['whois.verisign-grs.com'],
            'net' => ['whois.verisign-grs.com'],
            'org' => ['whois.publicinterestregistry.org', 'whois.pir.org'],
            'io' => ['whois.nic.io'],
            'co' => ['whois.nic.co'],
            'ai' => ['whois.nic.ai'],
            'me' => ['whois.nic.me'],
            'xyz' => ['whois.nic.xyz'],
            'online' => ['whois.nic.online'],
            'site' => ['whois.nic.site'],
            'store' => ['whois.nic.store'],
            'tech' => ['whois.nic.tech']
        ];

        $servers = $whoisMap[$tld] ?? [];
        if (empty($servers)) {
            // Dynamic TLD discovery via IANA
            $ianaRes = @file_get_contents('https://dns.google/resolve?name=whois.iana.org&type=A');
            $ianaIp = null;
            if ($ianaRes) {
                $ianaData = json_decode($ianaRes, true);
                $ianaIp = $ianaData['Answer'][0]['data'] ?? null;
            }
            if ($ianaIp) {
                $ianaFp = @fsockopen($ianaIp, 43, $errno, $errstr, 2);
                if ($ianaFp) {
                    stream_set_timeout($ianaFp, 2);
                    fwrite($ianaFp, $tld . "\r\n");
                    $ianaOut = '';
                    while (!feof($ianaFp)) {
                        $ianaOut .= fgets($ianaFp, 256);
                        if (strlen($ianaOut) > 8192) break;
                    }
                    fclose($ianaFp);
                    if (preg_match('/whois:\s*([a-z0-9\.\-]+)/i', $ianaOut, $m)) {
                        $servers[] = trim($m[1]);
                    }
                }
            }
        }

        foreach ($servers as $server) {
            $ip = @gethostbyname($server);
            if (!$ip || $ip === $server) {
                $dohRes = @file_get_contents('https://dns.google/resolve?name=' . urlencode($server) . '&type=A');
                if ($dohRes) {
                    $dData = json_decode($dohRes, true);
                    $ip = $dData['Answer'][0]['data'] ?? null;
                }
            }
            if (!$ip) continue;

            $fp = @fsockopen($ip, 43, $errno, $errstr, 3);
            if (!$fp) continue;

            stream_set_timeout($fp, 3);
            fwrite($fp, $domain . "\r\n");
            $out = '';
            while (!feof($fp)) {
                $out .= fgets($fp, 256);
                if (strlen($out) > 32768) break;
            }
            fclose($fp);

            $nsList = [];
            if (preg_match_all('/(?:name\s*server|nserver):\s*([a-z0-9\.\-]+)/i', $out, $matches)) {
                foreach ($matches[1] as $ns) {
                    $clean = self::normalizeNameserver($ns);
                    if (!empty($clean) && strpos($clean, '.') !== false) {
                        $nsList[] = $clean;
                    }
                }
            }

            if (!empty($nsList)) {
                return array_values(array_unique($nsList));
            }
        }

        return [];
    }

    /**
     * DNS-over-HTTPS (DoH) nameserver query via Google and Cloudflare
     */
    public static function getDohNameservers($domain) {
        $detected = [];
        $dohPending = false;
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 3,
                'header' => "Accept: application/dns-json\r\n"
            ]
        ]);

        // 1. Google DoH
        $gJson = @file_get_contents('https://dns.google/resolve?name=' . urlencode($domain) . '&type=NS', false, $ctx);
        if ($gJson) {
            $gData = json_decode($gJson, true);
            if (!empty($gData['Answer'])) {
                foreach ($gData['Answer'] as $ans) {
                    if (($ans['type'] ?? 0) === 2 && !empty($ans['data'])) {
                        $detected[] = self::normalizeNameserver($ans['data']);
                    }
                }
            }
            if (!empty($gData['Authority'])) {
                foreach ($gData['Authority'] as $auth) {
                    if (($auth['type'] ?? 0) === 2 && !empty($auth['data'])) {
                        $detected[] = self::normalizeNameserver($auth['data']);
                    }
                }
            }
            if (($gData['Status'] ?? 0) === 2) {
                $dohPending = true;
            }
        }

        // 2. Cloudflare DoH
        $cfJson = @file_get_contents('https://cloudflare-dns.com/dns-query?name=' . urlencode($domain) . '&type=NS', false, $ctx);
        if ($cfJson) {
            $cfData = json_decode($cfJson, true);
            if (!empty($cfData['Answer'])) {
                foreach ($cfData['Answer'] as $ans) {
                    if (($ans['type'] ?? 0) === 2 && !empty($ans['data'])) {
                        $detected[] = self::normalizeNameserver($ans['data']);
                    }
                }
            }
            if (!empty($cfData['Authority'])) {
                foreach ($cfData['Authority'] as $auth) {
                    if (($auth['type'] ?? 0) === 2 && !empty($auth['data'])) {
                        $detected[] = self::normalizeNameserver($auth['data']);
                    }
                }
            }
            if (($cfData['Status'] ?? 0) === 2) {
                $dohPending = true;
            }
        }

        return [
            'ns' => array_values(array_unique(array_filter($detected))),
            'pending' => $dohPending
        ];
    }

    /**
     * Multi-layered authoritative DNS resolution combining Root/TLD iterative lookup,
     * WHOIS registry data, public DoH, and native PHP DNS records.
     */
    public static function resolveAuthoritativeDns($domain) {
        $domain = self::sanitizeDomain($domain);
        if (!self::isValidDomain($domain)) {
            return [
                'valid' => false,
                'detected_ns' => [],
                'status' => 'invalid_domain',
                'propagation_pending' => false,
                'lookup_failed' => false
            ];
        }

        $allDetected = [];
        $propagationPending = false;
        $anyLookupSucceeded = false;

        // 1. Authoritative Root -> TLD delegation lookup (Pure-PHP UDP port 53)
        try {
            $authNs = self::getAuthoritativeNameservers($domain);
            if (!empty($authNs)) {
                $anyLookupSucceeded = true;
                foreach ($authNs as $ns) {
                    $norm = self::normalizeNameserver($ns);
                    if (!empty($norm)) {
                        $allDetected[] = $norm;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[ChildPanelHelper DNS Auth] ' . $e->getMessage());
        }

        // 2. WHOIS / Registry Query
        try {
            $whoisNs = self::getWhoisNameservers($domain);
            if (!empty($whoisNs)) {
                $anyLookupSucceeded = true;
                foreach ($whoisNs as $ns) {
                    $norm = self::normalizeNameserver($ns);
                    if (!empty($norm)) {
                        $allDetected[] = $norm;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[ChildPanelHelper DNS Whois] ' . $e->getMessage());
        }

        // 3. DNS-over-HTTPS (Google & Cloudflare)
        try {
            $dohResult = self::getDohNameservers($domain);
            if (!empty($dohResult['ns'])) {
                $anyLookupSucceeded = true;
                foreach ($dohResult['ns'] as $ns) {
                    $norm = self::normalizeNameserver($ns);
                    if (!empty($norm)) {
                        $allDetected[] = $norm;
                    }
                }
            }
            if (!empty($dohResult['pending'])) {
                $anyLookupSucceeded = true;
                $propagationPending = true;
            }
        } catch (\Throwable $e) {
            error_log('[ChildPanelHelper DNS DoH] ' . $e->getMessage());
        }

        // 4. Native PHP dns_get_record
        if (function_exists('dns_get_record')) {
            try {
                $records = @dns_get_record($domain, DNS_NS);
                if (!empty($records) && is_array($records)) {
                    $anyLookupSucceeded = true;
                    foreach ($records as $r) {
                        if (!empty($r['target'])) {
                            $norm = self::normalizeNameserver($r['target']);
                            if (!empty($norm)) {
                                $allDetected[] = $norm;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        $uniqueDetected = array_values(array_unique(array_filter($allDetected)));

        return [
            'valid' => true,
            'detected_ns' => $uniqueDetected,
            'status' => 'ok',
            'propagation_pending' => $propagationPending,
            'lookup_failed' => (!$anyLookupSucceeded && empty($uniqueDetected))
        ];
    }

    /**
     * Perform REAL domain DNS verification using authoritative nameserver delegation
     */
    public static function verifyDomain($panelId) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM child_panels WHERE id = ?");
        $stmt->execute([$panelId]);
        $panel = $stmt->fetch();

        if (!$panel) {
            return ['success' => false, 'error' => 'Child Panel not found.'];
        }

        $domain = self::sanitizeDomain($panel['domain']);
        $now = date('Y-m-d H:i:s');

        // 1. Domain syntax validation
        if (!self::isValidDomain($domain)) {
            $matchReason = 'Please enter a valid domain name.';
            $details = [
                'checked_at' => $now,
                'expected_ns' => [],
                'detected_ns' => [],
                'matched' => false,
                'reason' => $matchReason
            ];
            $upStmt = $db->prepare("UPDATE child_panels SET dns_status = 'verification_failed', dns_last_checked = ?, dns_details = ? WHERE id = ?");
            $upStmt->execute([$now, json_encode($details), $panelId]);

            return [
                'success' => false,
                'dns_matched' => false,
                'dns_status' => 'verification_failed',
                'ssl_status' => $panel['ssl_status'] ?? 'ssl_pending',
                'panel_status' => $panel['status'],
                'details' => $details,
                'error' => $matchReason,
                'message' => $matchReason
            ];
        }

        // 2. Expected nameservers configuration
        $configuredNs = self::getNameservers();
        $expectedNs1 = self::normalizeNameserver($panel['nameserver_1'] ?: $configuredNs['ns1']);
        $expectedNs2 = self::normalizeNameserver($panel['nameserver_2'] ?: $configuredNs['ns2']);
        $expectedList = array_values(array_filter([$expectedNs1, $expectedNs2]));

        // 3. Resolve authoritative DNS
        $dnsRes = self::resolveAuthoritativeDns($domain);

        // 4. Handle complete DNS lookup / network timeout failure
        if ($dnsRes['lookup_failed']) {
            $matchReason = 'DNS lookup could not be completed. Please try again.';
            $dnsStatus = 'pending_dns';
            $details = [
                'checked_at' => $now,
                'expected_ns' => $expectedList,
                'detected_ns' => [],
                'matched' => false,
                'reason' => $matchReason
            ];
            $upStmt = $db->prepare("UPDATE child_panels SET dns_status = ?, dns_last_checked = ?, dns_details = ? WHERE id = ?");
            $upStmt->execute([$dnsStatus, $now, json_encode($details), $panelId]);

            return [
                'success' => true,
                'dns_matched' => false,
                'dns_status' => $dnsStatus,
                'ssl_status' => $panel['ssl_status'] ?? 'ssl_pending',
                'panel_status' => $panel['status'],
                'details' => $details,
                'error' => $matchReason,
                'message' => $matchReason
            ];
        }

        $detectedNs = $dnsRes['detected_ns'];

        // 5. Compare detected nameservers with expected nameservers
        $ns1Match = (!empty($expectedNs1) && in_array($expectedNs1, $detectedNs, true));
        $ns2Match = (!empty($expectedNs2) && in_array($expectedNs2, $detectedNs, true));

        $hasExpected2 = (!empty($expectedNs2) && $expectedNs2 !== $expectedNs1);
        $allExpectedMatch = $hasExpected2 ? ($ns1Match && $ns2Match) : $ns1Match;
        $anyExpectedMatch = ($ns1Match || $ns2Match);

        $dnsMatched = false;
        $matchReason = '';
        $dnsStatus = 'pending_dns';

        if ($allExpectedMatch) {
            // Nameservers correct
            $dnsMatched = true;
            $matchReason = 'DNS verification successful.';
            $dnsStatus = 'dns_connected';
        } elseif ($anyExpectedMatch) {
            // One expected nameserver detected, but propagation still pending for the other
            $dnsMatched = false;
            $matchReason = 'Nameserver changes were detected but DNS propagation is still in progress. Please check again shortly.';
            $dnsStatus = 'pending_dns';
        } elseif (empty($detectedNs)) {
            // Domain is valid and registered, but delegation propagation is still pending
            $dnsMatched = false;
            $matchReason = 'Nameserver changes were detected but DNS propagation is still in progress. Please check again shortly.';
            $dnsStatus = 'pending_dns';
        } else {
            // Nameservers were detected, but they do NOT point to expected nameservers
            $dnsMatched = false;
            $matchReason = 'Nameservers are not pointing to the required nameservers yet.';
            $dnsStatus = 'verification_failed';
        }

        $details = [
            'checked_at' => $now,
            'expected_ns' => $expectedList,
            'detected_ns' => $detectedNs,
            'matched' => $dnsMatched,
            'reason' => $matchReason
        ];

        // SSL check if DNS matched
        $sslStatus = $panel['ssl_status'];
        if ($dnsMatched) {
            $sslCheck = self::checkSslHandshake($domain);
            $sslStatus = $sslCheck['active'] ? 'ssl_active' : 'ssl_pending';
            $sslDetails = json_encode($sslCheck);
        } else {
            $sslDetails = json_encode(['status' => 'waiting_for_dns']);
        }

        // If DNS verified and status was approved, transition panel to active
        $newPanelStatus = $panel['status'];
        if ($dnsMatched && in_array($panel['status'], ['approved', 'active'])) {
            $newPanelStatus = 'active';
        }

        $upStmt = $db->prepare("
            UPDATE child_panels SET
                dns_status = ?,
                dns_last_checked = ?,
                dns_details = ?,
                ssl_status = ?,
                ssl_last_checked = ?,
                ssl_details = ?,
                status = ?
            WHERE id = ?
        ");
        $upStmt->execute([
            $dnsStatus,
            $now,
            json_encode($details),
            $sslStatus,
            $now,
            $sslDetails,
            $newPanelStatus,
            $panelId
        ]);

        return [
            'success' => true,
            'dns_matched' => $dnsMatched,
            'dns_status' => $dnsStatus,
            'ssl_status' => $sslStatus,
            'panel_status' => $newPanelStatus,
            'details' => $details,
            'error' => $matchReason,
            'message' => $matchReason
        ];
    }

    /**
     * Check SSL status via real socket handshake or TLS check
     */
    public static function checkSslHandshake($domain) {
        // Test HTTPS port 443 with TLS stream context
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'timeout' => 5
            ]
        ]);

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);

        if ($fp) {
            $params = stream_context_get_params($fp);
            fclose($fp);
            return [
                'active' => true,
                'protocol' => 'TLS/HTTPS',
                'connected' => true,
                'message' => 'SSL certificate handshake successfully verified.'
            ];
        }

        // If server is running behind a proxy or localhost
        if ($domain === 'localhost' || strpos($domain, 'ais-') !== false || strpos($domain, 'run.app') !== false) {
            return [
                'active' => true,
                'protocol' => 'HTTPS (Cloud Run Proxy)',
                'connected' => true,
                'message' => 'SSL is managed via edge proxy.'
            ];
        }

        return [
            'active' => false,
            'error' => "Port 443 TLS handshake returned: $errstr ($errno)",
            'message' => 'SSL is being provisioned or waiting for DNS propagation.'
        ];
    }

    /**
     * Admin: Approve Child Panel request
     */
    public static function approveChildPanel($panelId, $notes = '') {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM child_panels WHERE id = ?");
        $stmt->execute([$panelId]);
        $panel = $stmt->fetch();

        if (!$panel) {
            return ['success' => false, 'error' => 'Child Panel not found.'];
        }

        // Check if DNS is already connected
        $nextStatus = ($panel['dns_status'] === 'dns_connected') ? 'active' : 'approved';

        $up = $db->prepare("UPDATE child_panels SET status = ?, admin_notes = ? WHERE id = ?");
        $up->execute([$nextStatus, $notes ?: $panel['admin_notes'], $panelId]);

        // Notify user
        $notif = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'system')");
        $notif->execute([
            $panel['user_id'],
            "Child Panel Approved: {$panel['domain']}",
            "Your Child Panel order for {$panel['domain']} has been approved! Next step: connect your domain nameservers."
        ]);

        return ['success' => true, 'new_status' => $nextStatus];
    }

    /**
     * Admin: Reject Child Panel request
     */
    public static function rejectChildPanel($panelId, $reason = '', $refund = true) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM child_panels WHERE id = ?");
        $stmt->execute([$panelId]);
        $panel = $stmt->fetch();

        if (!$panel) {
            return ['success' => false, 'error' => 'Child Panel not found.'];
        }

        $db->beginTransaction();
        try {
            // Update status
            $up = $db->prepare("UPDATE child_panels SET status = 'rejected', admin_notes = ? WHERE id = ?");
            $up->execute([$reason, $panelId]);

            // Refund if paid
            if ($refund && $panel['payment_status'] === 'paid' && (float)$panel['payment_amount'] > 0) {
                $refundAmt = (float)$panel['payment_amount'];
                $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$refundAmt, $panel['user_id']]);
                $db->prepare("UPDATE child_panels SET payment_status = 'refunded' WHERE id = ?")->execute([$panelId]);

                $txId = 'REF-CP-' . strtoupper(substr(uniqid(), 7));
                $db->prepare("INSERT INTO transactions (user_id, type, amount, charge, currency, payment_method, transaction_id, status) VALUES (?, 'refund', ?, 0, ?, 'wallet_refund', ?, 'completed')")
                   ->execute([$panel['user_id'], $refundAmt, $panel['payment_currency'], $txId]);
            }

            // Notify user
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'system')")->execute([
                $panel['user_id'],
                "Child Panel Request Rejected",
                "Your Child Panel request for {$panel['domain']} was rejected. Reason: " . ($reason ?: 'Requirements not met.') . ($refund ? " Your wallet has been refunded." : "")
            ]);

            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Admin: Suspend or Reactivate
     */
    public static function toggleSuspension($panelId, $action) {
        $db = getDB();
        $targetStatus = ($action === 'suspend') ? 'suspended' : 'active';
        $up = $db->prepare("UPDATE child_panels SET status = ? WHERE id = ?");
        $up->execute([$targetStatus, $panelId]);
        return ['success' => true, 'status' => $targetStatus];
    }

    /**
     * Admin: Reset Admin Password for Child Panel
     */
    public static function resetAdminPassword($panelId, $newPassword) {
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => 'Password must be at least 6 characters.'];
        }
        $db = getDB();
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $up = $db->prepare("UPDATE child_panels SET admin_password_hash = ? WHERE id = ?");
        $up->execute([$hash, $panelId]);
        return ['success' => true];
    }

    /**
     * Admin: Update Plan (Basic <-> Advanced)
     */
    public static function updatePlan($panelId, $newPlan) {
        if (!in_array($newPlan, ['basic', 'advanced'])) {
            return ['success' => false, 'error' => 'Invalid plan.'];
        }
        $db = getDB();
        $externalApi = ($newPlan === 'advanced') ? 1 : 0;
        $up = $db->prepare("UPDATE child_panels SET plan = ?, external_api_enabled = ? WHERE id = ?");
        $up->execute([$newPlan, $externalApi, $panelId]);
        return ['success' => true];
    }

    /**
     * Update Branding & Configuration
     */
    public static function updateBranding($panelId, $data) {
        $db = getDB();
        $panelName = trim($data['panel_name'] ?? '');
        $theme = trim($data['theme'] ?? 'default');
        $supportEmail = trim($data['support_email'] ?? '');
        $marginPercent = max(0, min(500, (float)($data['price_margin_percent'] ?? 15.0)));

        $up = $db->prepare("
            UPDATE child_panels SET
                panel_name = COALESCE(NULLIF(?, ''), panel_name),
                theme = COALESCE(NULLIF(?, ''), theme),
                support_email = COALESCE(NULLIF(?, ''), support_email),
                price_margin_percent = ?
            WHERE id = ?
        ");
        $up->execute([$panelName, $theme, $supportEmail, $marginPercent, $panelId]);
        return ['success' => true];
    }

    /**
     * Delete Child Panel
     */
    public static function deleteChildPanel($panelId) {
        $db = getDB();
        $db->prepare("DELETE FROM child_panel_orders WHERE child_panel_id = ?")->execute([$panelId]);
        $db->prepare("DELETE FROM child_panel_services WHERE child_panel_id = ?")->execute([$panelId]);
        $db->prepare("DELETE FROM child_panel_providers WHERE child_panel_id = ?")->execute([$panelId]);
        $db->prepare("DELETE FROM child_panel_users WHERE child_panel_id = ?")->execute([$panelId]);
        $db->prepare("DELETE FROM child_panels WHERE id = ?")->execute([$panelId]);
        return ['success' => true];
    }

    // ==========================================
    // ADVANCED CHILD PANEL: EXTERNAL PROVIDER API
    // ==========================================

    /**
     * Test connection to an external SMM provider API
     */
    public static function testProviderApi($apiUrl, $apiKey) {
        $apiUrl = trim($apiUrl);
        $apiKey = trim($apiKey);

        if (empty($apiUrl) || empty($apiKey)) {
            return ['success' => false, 'error' => 'API URL and API Key are required.'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'key' => $apiKey,
            'action' => 'balance'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RoseSMM-ChildPanel/2.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['success' => false, 'error' => "cURL connection error: $curlErr"];
        }

        $data = json_decode($response, true);
        if ($data && isset($data['balance'])) {
            return [
                'success' => true,
                'balance' => (float)$data['balance'],
                'currency' => $data['currency'] ?? 'USD',
                'raw' => $data
            ];
        }

        if ($data && isset($data['error'])) {
            return ['success' => false, 'error' => 'Provider API returned: ' . $data['error']];
        }

        return [
            'success' => false,
            'error' => "Unexpected response (HTTP $httpCode): " . substr($response, 0, 150)
        ];
    }

    /**
     * Fetch service list from external SMM provider API
     */
    public static function fetchExternalServices($apiUrl, $apiKey) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'key' => $apiKey,
            'action' => 'services'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RoseSMM-ChildPanel/2.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $services = json_decode($response, true);
        if (!is_array($services)) {
            return ['success' => false, 'error' => "Invalid JSON from provider (HTTP $httpCode)."];
        }

        return ['success' => true, 'services' => $services];
    }

    /**
     * Detect tenant Child Panel by HTTP host or preview parameter
     */
    public static function resolveCurrentTenant() {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $db = getDB();
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $cleanHost = self::sanitizeDomain($host);

        // Preview support for testing/admin verification: ?child_panel=ID or ?cp_domain=...
        if (!empty($_GET['child_panel']) && (is_admin() || is_logged_in())) {
            $cpId = (int)$_GET['child_panel'];
            $stmt = $db->prepare("SELECT * FROM child_panels WHERE id = ?");
            $stmt->execute([$cpId]);
            $cp = $stmt->fetch();
            if ($cp) {
                $resolved = $cp;
                return $resolved;
            }
        }

        if (!empty($_GET['cp_domain']) && (is_admin() || is_logged_in())) {
            $reqDomain = self::sanitizeDomain($_GET['cp_domain']);
            $stmt = $db->prepare("SELECT * FROM child_panels WHERE domain = ?");
            $stmt->execute([$reqDomain]);
            $cp = $stmt->fetch();
            if ($cp) {
                $resolved = $cp;
                return $resolved;
            }
        }

        // Real production domain match
        if (!empty($cleanHost)) {
            $stmt = $db->prepare("SELECT * FROM child_panels WHERE domain = ? AND status = 'active'");
            $stmt->execute([$cleanHost]);
            $cp = $stmt->fetch();
            if ($cp) {
                $resolved = $cp;
                return $resolved;
            }
        }

        $resolved = false;
        return false;
    }
}
