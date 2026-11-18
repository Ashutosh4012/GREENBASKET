<?php

declare(strict_types=1);

$rootDir = __DIR__;
$storePath = $rootDir . '/data/store.json';
$defaultAdminPassword = getenv('QB_ADMIN_PASSWORD') ?: 'QuickBite@2026';

function readStoreData(string $storePath): array
{
    if (!is_file($storePath)) {
        return [
            'users' => [],
            'orders' => [],
            'sessions' => [],
            'adminSessions' => [],
            'catalogStores' => [],
            'storeManagers' => [],
        ];
    }

    $raw = file_get_contents($storePath);
    if ($raw === false || trim($raw) === '') {
        return [
            'users' => [],
            'orders' => [],
            'sessions' => [],
            'adminSessions' => [],
            'catalogStores' => [],
            'storeManagers' => [],
        ];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [
            'users' => [],
            'orders' => [],
            'sessions' => [],
            'adminSessions' => [],
            'catalogStores' => [],
            'storeManagers' => [],
        ];
    }

    return [
        'users' => array_values($data['users'] ?? []),
        'orders' => array_values($data['orders'] ?? []),
        'sessions' => array_values($data['sessions'] ?? []),
        'adminSessions' => array_values($data['adminSessions'] ?? []),
        'catalogStores' => array_values($data['catalogStores'] ?? []),
        'storeManagers' => array_values($data['storeManagers'] ?? []),
    ];
}

function writeStoreData(string $storePath, array $data): void
{
    $dir = dirname($storePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    file_put_contents($storePath, $encoded . PHP_EOL);
}

function jsonResponse(mixed $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResponse(string $message, int $status = 400): void
{
    jsonResponse(['message' => $message], $status);
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function getBearerToken(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function createToken(): string
{
    return bin2hex(random_bytes(24));
}

function hashPassword(string $password, string $salt): string
{
    return hash('sha256', $password . ':' . $salt);
}

function sanitizeUser(array $user): array
{
    return [
        'id' => $user['id'] ?? null,
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'phone' => $user['phone'] ?? '',
        'address' => $user['address'] ?? '',
        'favourites' => $user['favourites'] ?? [],
        'createdAt' => $user['createdAt'] ?? null,
        'updatedAt' => $user['updatedAt'] ?? null,
    ];
}

function getUserFromToken(array $data, string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $now = time() * 1000;
    foreach ($data['sessions'] ?? [] as $entry) {
        if (($entry['token'] ?? '') === $token && (($entry['expiresAt'] ?? 0) > $now)) {
            foreach ($data['users'] ?? [] as $user) {
                if (($user['id'] ?? '') === ($entry['userId'] ?? '')) {
                    return $user;
                }
            }
        }
    }

    return null;
}

function getAdminAccess(array $data, string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $now = time() * 1000;
    foreach ($data['adminSessions'] ?? [] as $entry) {
        if (($entry['token'] ?? '') === $token && (($entry['expiresAt'] ?? 0) > $now)) {
            return [
                'role' => $entry['role'] ?? 'super',
                'storeId' => $entry['storeId'] ?? null,
                'storeName' => $entry['storeName'] ?? '',
                'canManageAllStores' => $entry['role'] ?? 'super' !== 'store',
                'defaultStorePasswordPattern' => 'Store<StoreId>@QB',
            ];
        }
    }

    return null;
}

function findStore(array $data, int $storeId): ?array
{
    foreach ($data['catalogStores'] ?? [] as $store) {
        if ((int)($store['id'] ?? 0) === $storeId) {
            return $store;
        }
    }

    return null;
}

function inventorySummary(array $stores): array
{
    $products = [];
    foreach ($stores as $store) {
        foreach ($store['menu'] ?? [] as $product) {
            $products[] = $product;
        }
    }

    $totalProducts = count($products);
    $lowStock = count(array_filter($products, static fn(array $product): bool => (int)($product['stock'] ?? 0) <= (int)($product['reorderLevel'] ?? 0)));
    $outOfStock = count(array_filter($products, static fn(array $product): bool => (int)($product['stock'] ?? 0) <= 0));
    $value = array_sum(array_map(static fn(array $product): int => (int)($product['price'] ?? 0) * (int)($product['stock'] ?? 0), $products));

    return [
        'totalProducts' => $totalProducts,
        'lowStock' => $lowStock,
        'outOfStock' => $outOfStock,
        'value' => $value,
    ];
}

function normalizeStorePayload(array $payload): array
{
    return [
        'id' => (int)($payload['id'] ?? 0),
        'name' => (string)($payload['name'] ?? ''),
        'cuisine' => (string)($payload['cuisine'] ?? ''),
        'rating' => (float)($payload['rating'] ?? 4.5),
        'deliveryTime' => (int)($payload['deliveryTime'] ?? 30),
        'minOrder' => (int)($payload['minOrder'] ?? 199),
        'avgCost' => (int)($payload['avgCost'] ?? 500),
        'category' => (string)($payload['category'] ?? 'essentials'),
        'promo' => (string)($payload['promo'] ?? ''),
        'tags' => is_array($payload['tags'] ?? null) ? array_values($payload['tags']) : ['New Store'],
        'img' => (string)($payload['img'] ?? ''),
        'address' => (string)($payload['address'] ?? ''),
        'menu' => is_array($payload['menu'] ?? null) ? array_values($payload['menu']) : [],
    ];
}

function normalizeProductPayload(array $payload, int $storeId): array
{
    return [
        'id' => (int)($payload['id'] ?? 0),
        'restId' => $storeId,
        'name' => (string)($payload['name'] ?? ''),
        'desc' => (string)($payload['desc'] ?? ''),
        'price' => (int)($payload['price'] ?? 0),
        'img' => (string)($payload['img'] ?? ''),
        'veg' => (bool)($payload['veg'] ?? true),
        'bestseller' => (bool)($payload['bestseller'] ?? false),
        'stock' => (int)($payload['stock'] ?? 0),
        'reorderLevel' => (int)($payload['reorderLevel'] ?? 8),
        'category' => (string)($payload['category'] ?? 'essentials'),
        'unit' => (string)($payload['unit'] ?? 'unit'),
        'sku' => (string)($payload['sku'] ?? ''),
        'tags' => is_array($payload['tags'] ?? null) ? array_values($payload['tags']) : [],
    ];
}

function calculateTotals(array $items, ?string $couponCode = null): array
{
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += (int)($item['price'] ?? 0) * (int)($item['qty'] ?? 1);
    }

    $discount = 0;
    if ($couponCode === 'FIRST50' && $subtotal >= 249) {
        $discount = 50;
    } elseif ($couponCode === 'SAVE20' && $subtotal >= 299) {
        $discount = (int)floor($subtotal * 0.2);
    }

    $tax = (int)floor($subtotal * 0.05);
    $delivery = $subtotal >= 399 ? 0 : 40;
    $total = max(0, $subtotal + $tax + $delivery - $discount);

    return [
        'subtotal' => $subtotal,
        'tax' => $tax,
        'delivery' => $delivery,
        'discount' => $discount,
        'total' => $total,
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$uri = rawurldecode($uri);

if ($method === 'OPTIONS') {
    jsonResponse([], 204);
}

if (preg_match('#^/api/#', $uri) !== 1) {
    if ($uri === '/' || $uri === '/index.php') {
        $file = $rootDir . '/index.html';
        if (is_file($file)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($file);
            exit;
        }
    }

    $requestedFile = $rootDir . $uri;
    if (is_file($requestedFile)) {
        $extension = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($requestedFile);
        exit;
    }

    $file = $rootDir . '/index.html';
    if (is_file($file)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        exit;
    }

    errorResponse('Not found', 404);
}

$data = readStoreData($storePath);
$body = getJsonBody();
$token = getBearerToken();

if ($method === 'GET' && $uri === '/api/health') {
    jsonResponse(['ok' => true, 'service' => 'php']);
}

if ($method === 'GET' && $uri === '/api/catalog') {
    $stores = $data['catalogStores'] ?? [];
    jsonResponse([
        'location' => 'All zones',
        'source' => 'QuickBite PHP backend',
        'fetchedAt' => time() * 1000,
        'stores' => $stores,
        'summary' => inventorySummary($stores),
    ]);
}

if ($method === 'POST' && $uri === '/api/auth/signup') {
    $name = trim((string)($body['name'] ?? ''));
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $phone = trim((string)($body['phone'] ?? ''));
    $password = (string)($body['password'] ?? '');

    if ($name === '' || $email === '' || $phone === '' || $password === '') {
        errorResponse('Please fill all signup fields', 400);
    }

    if (!preg_match('/^\d{10}$/', $phone)) {
        errorResponse('Enter a valid 10-digit phone number', 400);
    }

    if (strlen($password) < 6) {
        errorResponse('Password must be at least 6 characters', 400);
    }

    foreach ($data['users'] ?? [] as $entry) {
        if (strtolower((string)($entry['email'] ?? '')) === $email) {
            errorResponse('An account with this email already exists', 409);
        }
    }

    $salt = bin2hex(random_bytes(16));
    $now = time() * 1000;
    $user = [
        'id' => 'php-' . $now,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => '',
        'favourites' => [],
        'passwordHash' => hashPassword($password, $salt),
        'passwordSalt' => $salt,
        'createdAt' => $now,
        'updatedAt' => $now,
    ];
    $data['users'][] = $user;

    $authToken = createToken();
    $data['sessions'][] = [
        'token' => $authToken,
        'userId' => $user['id'],
        'createdAt' => $now,
        'expiresAt' => $now + 7 * 24 * 60 * 60 * 1000,
    ];

    writeStoreData($storePath, $data);
    jsonResponse(['token' => $authToken, 'user' => sanitizeUser($user)]);
}

if ($method === 'POST' && $uri === '/api/auth/login') {
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $password = (string)($body['password'] ?? '');

    if ($email === '' || $password === '') {
        errorResponse('Enter email and password', 400);
    }

    foreach ($data['users'] ?? [] as $user) {
        if (strtolower((string)($user['email'] ?? '')) === $email) {
            $salt = (string)($user['passwordSalt'] ?? '');
            if (hashPassword($password, $salt) === (string)($user['passwordHash'] ?? '')) {
                $now = time() * 1000;
                $authToken = createToken();
                $data['sessions'][] = [
                    'token' => $authToken,
                    'userId' => $user['id'],
                    'createdAt' => $now,
                    'expiresAt' => $now + 7 * 24 * 60 * 60 * 1000,
                ];
                writeStoreData($storePath, $data);
                jsonResponse(['token' => $authToken, 'user' => sanitizeUser($user)]);
            }
        }
    }

    errorResponse('Account not found or password is incorrect', 401);
}

if ($method === 'GET' && $uri === '/api/auth/me') {
    $user = getUserFromToken($data, $token);
    if ($user === null) {
        errorResponse('Your session has expired. Please sign in again.', 401);
    }

    jsonResponse(['user' => sanitizeUser($user)]);
}

if ($method === 'POST' && $uri === '/api/auth/logout') {
    if ($token !== '') {
        $data['sessions'] = array_values(array_filter($data['sessions'] ?? [], static fn(array $entry): bool => ($entry['token'] ?? '') !== $token));
        writeStoreData($storePath, $data);
    }

    jsonResponse([]);
}

if ($method === 'PUT' && $uri === '/api/users/me/profile') {
    $user = getUserFromToken($data, $token);
    if ($user === null) {
        errorResponse('Your session has expired. Please sign in again.', 401);
    }

    if (array_key_exists('name', $body)) {
        $name = trim((string)$body['name']);
        if ($name === '') {
            errorResponse('Name cannot be empty', 400);
        }
        $user['name'] = $name;
    }

    if (array_key_exists('phone', $body)) {
        $phone = trim((string)$body['phone']);
        if (!preg_match('/^\d{10}$/', $phone)) {
            errorResponse('Enter a valid 10-digit phone number', 400);
        }
        $user['phone'] = $phone;
    }

    if (array_key_exists('address', $body)) {
        $user['address'] = trim((string)$body['address']);
    }

    if (array_key_exists('favourites', $body)) {
        if (!is_array($body['favourites'])) {
            errorResponse('Favourites must be an array', 400);
        }
        $user['favourites'] = array_values(array_unique(array_map('intval', $body['favourites'])));
    }

    $user['updatedAt'] = time() * 1000;
    foreach ($data['users'] ?? [] as $index => $entry) {
        if (($entry['id'] ?? '') === ($user['id'] ?? '')) {
            $data['users'][$index] = $user;
            break;
        }
    }

    writeStoreData($storePath, $data);
    jsonResponse(['user' => sanitizeUser($user)]);
}

if ($method === 'GET' && $uri === '/api/orders/me') {
    $user = getUserFromToken($data, $token);
    if ($user === null) {
        errorResponse('Your session has expired. Please sign in again.', 401);
    }

    $orders = array_values(array_filter($data['orders'] ?? [], static fn(array $order): bool => ($order['userId'] ?? '') === ($user['id'] ?? '')));
    usort($orders, static fn(array $a, array $b): int => ((int)($b['createdAt'] ?? 0)) <=> ((int)($a['createdAt'] ?? 0)));

    jsonResponse(['orders' => $orders]);
}

if ($method === 'POST' && $uri === '/api/orders') {
    $user = getUserFromToken($data, $token);
    if ($user === null) {
        errorResponse('Your session has expired. Please sign in again.', 401);
    }

    $customer = is_array($body['customer'] ?? null) ? $body['customer'] : [];
    $name = trim((string)($customer['name'] ?? ''));
    $phone = trim((string)($customer['phone'] ?? ''));
    $address = trim((string)($customer['address'] ?? ''));
    $note = trim((string)($customer['note'] ?? ''));
    $payment = trim((string)($body['payment'] ?? ''));
    $restId = (int)($body['restId'] ?? 0);
    $items = is_array($body['items'] ?? null) ? $body['items'] : [];

    if ($name === '' || $phone === '' || $address === '' || $payment === '') {
        errorResponse('Please fill all required fields', 400);
    }

    if (!preg_match('/^\d{10}$/', $phone)) {
        errorResponse('Enter a valid 10-digit phone number', 400);
    }

    $store = findStore($data, $restId);
    if ($store === null && count($items) > 0) {
        $store = findStore($data, (int)($items[0]['restId'] ?? 0));
    }

    if ($store === null) {
        errorResponse('Choose a valid grocery store', 400);
    }

    $resolvedItems = [];
    foreach ($items as $item) {
        $productId = (int)($item['id'] ?? 0);
        $qty = max(1, (int)($item['qty'] ?? 1));
        foreach ($store['menu'] ?? [] as $product) {
            if ((int)($product['id'] ?? 0) === $productId) {
                $resolvedItems[] = [
                    'id' => $productId,
                    'restId' => (int)($product['restId'] ?? $restId),
                    'name' => (string)($product['name'] ?? ''),
                    'price' => (int)($product['price'] ?? 0),
                    'img' => (string)($product['img'] ?? ''),
                    'qty' => $qty,
                    'sku' => (string)($product['sku'] ?? ''),
                    'unit' => (string)($product['unit'] ?? 'unit'),
                    'category' => (string)($product['category'] ?? 'essentials'),
                ];
                break;
            }
        }
    }

    if (count($resolvedItems) === 0) {
        errorResponse('Choose at least one product', 400);
    }

    foreach ($resolvedItems as $item) {
        foreach ($store['menu'] ?? [] as $index => $product) {
            if ((int)($product['id'] ?? 0) === (int)($item['id'] ?? 0)) {
                $stock = (int)($product['stock'] ?? 0);
                if ($stock < (int)($item['qty'] ?? 1)) {
                    errorResponse('One or more selected products are out of stock', 409);
                }
                $store['menu'][$index]['stock'] = $stock - (int)($item['qty'] ?? 1);
                break;
            }
        }
    }

    foreach ($data['catalogStores'] ?? [] as $index => $entry) {
        if ((int)($entry['id'] ?? 0) === (int)($store['id'] ?? 0)) {
            $data['catalogStores'][$index] = $store;
            break;
        }
    }

    $totals = calculateTotals($resolvedItems, (string)($body['couponCode'] ?? ''));
    $createdAt = time() * 1000;
    $order = [
        'orderId' => 'QB' . $createdAt,
        'restId' => (int)($store['id'] ?? 0),
        'restName' => (string)($store['name'] ?? ''),
        'restImg' => (string)($store['img'] ?? ''),
        'customer' => [
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'note' => $note,
        ],
        'userId' => $user['id'],
        'userEmail' => $user['email'],
        'items' => $resolvedItems,
        'subtotal' => $totals['subtotal'],
        'tax' => $totals['tax'],
        'delivery' => $totals['delivery'],
        'total' => $totals['total'],
        'couponCode' => (string)($body['couponCode'] ?? ''),
        'discount' => $totals['discount'],
        'payment' => $payment,
        'status' => 'Pending',
        'date' => date('d M Y, h:i:s A', $createdAt / 1000),
        'createdAt' => $createdAt,
        'etaMinutes' => 25,
        'stockReverted' => false,
    ];

    $user['name'] = $name;
    $user['phone'] = $phone;
    $user['address'] = $address;
    $user['updatedAt'] = $createdAt;

    foreach ($data['users'] ?? [] as $index => $entry) {
        if (($entry['id'] ?? '') === ($user['id'] ?? '')) {
            $data['users'][$index] = $user;
            break;
        }
    }

    array_unshift($data['orders'], $order);
    writeStoreData($storePath, $data);
    jsonResponse(['order' => $order, 'user' => sanitizeUser($user)]);
}

if ($method === 'POST' && $uri === '/api/admin/login') {
    $accessType = strtolower((string)($body['accessType'] ?? 'super')) === 'store' ? 'store' : 'super';
    $password = (string)($body['password'] ?? '');
    $storeId = (int)($body['storeId'] ?? 0);

    if ($password === '') {
        errorResponse($accessType === 'store' ? 'Enter the store password' : 'Enter the admin password', 400);
    }

    if ($accessType === 'store') {
        $store = findStore($data, $storeId);
        if ($store === null) {
            errorResponse('Choose a valid grocery store', 400);
        }

        $expectedPassword = 'Store' . $storeId . '@QB';
        if ($password !== $expectedPassword) {
            errorResponse('Incorrect store password', 401);
        }

        $now = time() * 1000;
        $tokenValue = createToken();
        $data['adminSessions'][] = [
            'token' => $tokenValue,
            'role' => 'store',
            'storeId' => $storeId,
            'storeName' => $store['name'],
            'createdAt' => $now,
            'expiresAt' => $now + 12 * 60 * 60 * 1000,
        ];
        writeStoreData($storePath, $data);
        jsonResponse(['token' => $tokenValue, 'access' => ['role' => 'store', 'storeId' => $storeId, 'storeName' => $store['name'], 'canManageAllStores' => false, 'defaultStorePasswordPattern' => 'Store<StoreId>@QB']]);
    }

    if ($password !== $defaultAdminPassword) {
        errorResponse('Incorrect admin password', 401);
    }

    $now = time() * 1000;
    $tokenValue = createToken();
    $data['adminSessions'][] = [
        'token' => $tokenValue,
        'role' => 'super',
        'storeId' => null,
        'storeName' => '',
        'createdAt' => $now,
        'expiresAt' => $now + 12 * 60 * 60 * 1000,
    ];
    writeStoreData($storePath, $data);
    jsonResponse(['token' => $tokenValue, 'access' => ['role' => 'super', 'storeId' => null, 'storeName' => '', 'canManageAllStores' => true, 'defaultStorePasswordPattern' => 'Store<StoreId>@QB']]);
}

if ($method === 'POST' && $uri === '/api/admin/logout') {
    if ($token !== '') {
        $data['adminSessions'] = array_values(array_filter($data['adminSessions'] ?? [], static fn(array $entry): bool => ($entry['token'] ?? '') !== $token));
        writeStoreData($storePath, $data);
    }

    jsonResponse([]);
}

if ($method === 'GET' && $uri === '/api/admin/me') {
    $access = getAdminAccess($data, $token);
    if ($access === null) {
        errorResponse('Admin session expired. Please unlock the dashboard again.', 401);
    }

    jsonResponse(['access' => $access]);
}

if ($method === 'GET' && $uri === '/api/admin/orders') {
    $access = getAdminAccess($data, $token);
    if ($access === null) {
        errorResponse('Admin session expired. Please unlock the dashboard again.', 401);
    }

    $orders = $data['orders'] ?? [];
    if (($access['role'] ?? 'super') === 'store') {
        $storeId = (int)($access['storeId'] ?? 0);
        $orders = array_values(array_filter($orders, static fn(array $order): bool => (int)($order['restId'] ?? 0) === $storeId));
    }

    usort($orders, static fn(array $a, array $b): int => ((int)($b['createdAt'] ?? 0)) <=> ((int)($a['createdAt'] ?? 0)));
    jsonResponse(['orders' => $orders, 'access' => $access]);
}

if ($method === 'GET' && $uri === '/api/admin/catalog') {
    $access = getAdminAccess($data, $token);
    if ($access === null) {
        errorResponse('Admin session expired. Please unlock the dashboard again.', 401);
    }

    $stores = $data['catalogStores'] ?? [];
    if (($access['role'] ?? 'super') === 'store') {
        $storeId = (int)($access['storeId'] ?? 0);
        $stores = array_values(array_filter($stores, static fn(array $store): bool => (int)($store['id'] ?? 0) === $storeId));
    }

    jsonResponse(['stores' => $stores, 'summary' => inventorySummary($stores), 'globalSummary' => inventorySummary($data['catalogStores'] ?? []), 'access' => $access]);
}

if ($method === 'POST' && $uri === '/api/admin/stores') {
    $access = getAdminAccess($data, $token);
    if ($access === null) {
        errorResponse('Admin session expired. Please unlock the dashboard again.', 401);
    }

    if (($access['role'] ?? 'super') === 'store') {
        errorResponse('Store managers cannot create new stores.', 403);
    }

    $name = trim((string)($body['name'] ?? ''));
    if ($name === '') {
        errorResponse('Store name is required', 400);
    }

    $existing = array_filter($data['catalogStores'] ?? [], static fn(array $store): bool => strtolower((string)($store['name'] ?? '')) === strtolower($name));
    if (count($existing) > 0) {
        errorResponse('A store with this name already exists', 409);
    }

    $maxStoreId = 0;
    foreach ($data['catalogStores'] ?? [] as $store) {
        $maxStoreId = max($maxStoreId, (int)($store['id'] ?? 0));
    }
    $nextStoreId = $maxStoreId + 1;
    $managerPassword = trim((string)($body['managerPassword'] ?? '')) ?: 'Store' . $nextStoreId . '@QB';

    $storeEntry = normalizeStorePayload([
        'id' => $nextStoreId,
        'name' => $name,
        'cuisine' => trim((string)($body['cuisine'] ?? 'Daily Grocery Essentials')),
        'rating' => (float)($body['rating'] ?? 4.5),
        'deliveryTime' => (int)($body['deliveryTime'] ?? 30),
        'minOrder' => (int)($body['minOrder'] ?? 199),
        'avgCost' => (int)($body['avgCost'] ?? 500),
        'category' => trim((string)($body['category'] ?? 'essentials')),
        'promo' => trim((string)($body['promo'] ?? 'Fresh grocery picks available daily')),
        'tags' => is_array($body['tags'] ?? null) ? $body['tags'] : ['New Store'],
        'img' => trim((string)($body['img'] ?? 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=800&q=80&auto=format&fit=crop')),
        'address' => trim((string)($body['address'] ?? 'Service area not specified')),
        'menu' => [],
    ]);

    $data['catalogStores'][] = $storeEntry;
    $data['storeManagers'][] = [
        'storeId' => $storeEntry['id'],
        'password' => $managerPassword,
        'createdAt' => time() * 1000,
    ];
    writeStoreData($storePath, $data);

    jsonResponse(['store' => $storeEntry, 'managerPassword' => $managerPassword, 'summary' => inventorySummary($data['catalogStores'] ?? []), 'globalSummary' => inventorySummary($data['catalogStores'] ?? []), 'access' => $access]);
}

if ($method === 'POST' && $uri === '/api/admin/products') {
    $access = getAdminAccess($data, $token);
    if ($access === null) {
        errorResponse('Admin session expired. Please unlock the dashboard again.', 401);
    }

    $name = trim((string)($body['name'] ?? ''));
    $price = (int)($body['price'] ?? 0);
    if ($name === '' || $price <= 0) {
        errorResponse('Enter valid product details and price', 400);
    }

    $targetStores = [];
    if (($access['role'] ?? 'super') === 'store') {
        $storeId = (int)($access['storeId'] ?? 0);
        $store = findStore($data, $storeId);
        if ($store !== null) {
            $targetStores[] = $store;
        }
    } else {
        $applyToAll = (bool)($body['applyToAll'] ?? false);
        if ($applyToAll) {
            $targetStores = $data['catalogStores'] ?? [];
        } else {
            $storeId = (int)($body['storeId'] ?? 0);
            $store = findStore($data, $storeId);
            if ($store !== null) {
                $targetStores[] = $store;
            }
        }
    }

    if (count($targetStores) === 0) {
        errorResponse('Choose a valid grocery store', 400);
    }

    $maxProductId = 0;
    foreach ($targetStores as $store) {
        foreach ($store['menu'] ?? [] as $product) {
            $maxProductId = max($maxProductId, (int)($product['id'] ?? 0));
        }
    }

    $createdProducts = [];
    foreach ($targetStores as $store) {
        $productId = $maxProductId + 1;
        $product = normalizeProductPayload([
            'id' => $productId,
            'name' => $name,
            'desc' => trim((string)($body['desc'] ?? '')),
            'price' => $price,
            'img' => trim((string)($body['img'] ?? '')),
            'veg' => (bool)($body['veg'] ?? true),
            'bestseller' => (bool)($body['bestseller'] ?? false),
            'stock' => (int)($body['stock'] ?? 0),
            'reorderLevel' => (int)($body['reorderLevel'] ?? 8),
            'category' => trim((string)($body['category'] ?? 'essentials')),
            'unit' => trim((string)($body['unit'] ?? 'unit')),
            'sku' => trim((string)($body['sku'] ?? '')),
            'tags' => is_array($body['tags'] ?? null) ? $body['tags'] : [],
        ], (int)($store['id'] ?? 0));
        $store['menu'] = array_values([$product, ...($store['menu'] ?? [])]);

        foreach ($data['catalogStores'] ?? [] as $index => $entry) {
            if ((int)($entry['id'] ?? 0) === (int)($store['id'] ?? 0)) {
                $data['catalogStores'][$index] = $store;
                break;
            }
        }

        $createdProducts[] = $product;
        $maxProductId = $productId;
    }

    writeStoreData($storePath, $data);
    jsonResponse(['product' => $createdProducts[0] ?? null, 'products' => $createdProducts, 'createdCount' => count($createdProducts), 'storeId' => $createdProducts[0]['restId'] ?? null, 'storeIds' => array_values(array_unique(array_map('intval', array_column($createdProducts, 'restId')))), 'scope' => count($createdProducts) > 1 ? 'all-stores' : 'single-store', 'summary' => inventorySummary($data['catalogStores'] ?? []), 'access' => $access]);
}

if (preg_match('#^/api/admin/products/([0-9]+)(/stock)?$#', $uri, $matches) === 1) {
    $access = getAdminAccess($data, $token);
    if ($access === null) {
        errorResponse('Admin session expired. Please unlock the dashboard again.', 401);
    }

    $productId = (int)$matches[1];
    $foundStore = null;
    $foundProduct = null;
    $foundIndex = null;
    foreach ($data['catalogStores'] ?? [] as $storeIndex => $store) {
        foreach ($store['menu'] ?? [] as $productIndex => $product) {
            if ((int)($product['id'] ?? 0) === $productId) {
                $foundStore = $store;
                $foundProduct = $product;
                $foundIndex = [$storeIndex, $productIndex];
                break 2;
            }
        }
    }

    if ($foundProduct === null) {
        errorResponse('Product not found', 404);
    }

    if ($method === 'PATCH' && isset($matches[2]) && $matches[2] === '/stock') {
        $delta = (int)($body['delta'] ?? 0);
        $newStock = max(0, (int)($foundProduct['stock'] ?? 0) + $delta);
        $data['catalogStores'][$foundIndex[0]]['menu'][$foundIndex[1]]['stock'] = $newStock;
        writeStoreData($storePath, $data);
        jsonResponse(['product' => $data['catalogStores'][$foundIndex[0]]['menu'][$foundIndex[1]], 'summary' => inventorySummary($data['catalogStores'] ?? [])]);
    }

    if ($method === 'PATCH') {
        foreach (['name', 'desc', 'price', 'img', 'veg', 'bestseller', 'stock', 'reorderLevel', 'category', 'unit', 'sku'] as $field) {
            if (array_key_exists($field, $body)) {
                $value = $body[$field];
                if ($field === 'price' || $field === 'stock' || $field === 'reorderLevel') {
                    $value = (int)$value;
                }
                if ($field === 'veg' || $field === 'bestseller') {
                    $value = (bool)$value;
                }
                $data['catalogStores'][$foundIndex[0]]['menu'][$foundIndex[1]][$field] = $value;
            }
        }
        writeStoreData($storePath, $data);
        jsonResponse(['product' => $data['catalogStores'][$foundIndex[0]]['menu'][$foundIndex[1]], 'summary' => inventorySummary($data['catalogStores'] ?? [])]);
    }
}

if (preg_match('#^/api/admin/orders/([0-9A-Za-z\-]+)(/status)?$#', $uri, $matches) === 1) {
    $access = getAdminAccess($data, $token);
    if ($access === null) {
        errorResponse('Admin session expired. Please unlock the dashboard again.', 401);
    }

    $orderId = $matches[1];
    $orderIndex = null;
    foreach ($data['orders'] ?? [] as $index => $order) {
        if (($order['orderId'] ?? '') === $orderId) {
            $orderIndex = $index;
            break;
        }
    }

    if ($orderIndex === null) {
        errorResponse('Order not found', 404);
    }

    if ($method === 'PATCH' && isset($matches[2]) && $matches[2] === '/status') {
        $status = trim((string)($body['status'] ?? ''));
        if ($status === '') {
            errorResponse('Status is required', 400);
        }
        $data['orders'][$orderIndex]['status'] = $status;
        writeStoreData($storePath, $data);
        jsonResponse(['order' => $data['orders'][$orderIndex]]);
    }
}

errorResponse('Not found', 404);
