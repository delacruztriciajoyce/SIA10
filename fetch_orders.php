<?php
header('Content-Type: application/json; charset=utf-8');

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'northwind';

require_once __DIR__ . '/redis_cache.php';

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $mysqli->connect_error]);
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';

$whereClauses = [];
if ($search !== '') {
    $searchEscaped = $mysqli->real_escape_string($search);
    $whereClauses[] = "(OrderID LIKE '%{$searchEscaped}%' OR CustomerID LIKE '%{$searchEscaped}%' OR ShipName LIKE '%{$searchEscaped}%')";
}
if ($startDate !== '') {
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    if ($start !== false) {
        $whereClauses[] = "OrderDate >= '" . $mysqli->real_escape_string($start->format('Y-m-d')) . "'";
    }
}
if ($endDate !== '') {
    $end = DateTime::createFromFormat('Y-m-d', $endDate);
    if ($end !== false) {
        $whereClauses[] = "OrderDate <= '" . $mysqli->real_escape_string($end->format('Y-m-d')) . "'";
    }
}

$cacheKey = 'northwind:orders:calendar:' . md5($search . '|' . $startDate . '|' . $endDate);
$events = getCachedRedisData($cacheKey);

if ($events === null) {
    $query = "SELECT OrderID, CustomerID, EmployeeID, OrderDate, RequiredDate, ShippedDate, ShipName FROM orders";
    if (!empty($whereClauses)) {
        $query .= ' WHERE ' . implode(' AND ', $whereClauses);
    }
    $query .= " ORDER BY OrderDate ASC";

    $result = $mysqli->query($query);
    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Query failed: ' . $mysqli->error]);
        exit;
    }

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $orderDate = $row['OrderDate'] ?: $row['RequiredDate'] ?: $row['ShippedDate'];
        if (!$orderDate) {
            continue;
        }

        $startDate = date('Y-m-d', strtotime($orderDate));
        $shippedDate = $row['ShippedDate'] ? date('Y-m-d', strtotime($row['ShippedDate'])) : null;

        $events[] = [
            'id' => (int) $row['OrderID'],
            'title' => 'Order #' . $row['OrderID'],
            'start' => $startDate,
            'allDay' => true,
            'extendedProps' => [
                'customerId' => $row['CustomerID'],
                'employeeId' => $row['EmployeeID'],
                'orderDate' => $row['OrderDate'] ? date('Y-m-d', strtotime($row['OrderDate'])) : null,
                'requiredDate' => $row['RequiredDate'] ? date('Y-m-d', strtotime($row['RequiredDate'])) : null,
                'shippedDate' => $shippedDate,
                'shipName' => $row['ShipName']
            ]
        ];
    }

    setCachedRedisData($cacheKey, $events, 3600);
} else {
    $events = array_values($events);
}

if ($events === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load order events.']);
    exit;
}

echo json_encode($events, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$mysqli->close();