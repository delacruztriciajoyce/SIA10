<?php
header('Content-Type: application/json; charset=utf-8');

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'northwind';

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $mysqli->connect_error]);
    exit;
}

$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';
$where = [];

if ($startDate !== '') {
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    if ($start !== false) {
        $where[] = "o.OrderDate >= '" . $mysqli->real_escape_string($start->format('Y-m-d')) . "'";
    }
}
if ($endDate !== '') {
    $end = DateTime::createFromFormat('Y-m-d', $endDate);
    if ($end !== false) {
        $where[] = "o.OrderDate <= '" . $mysqli->real_escape_string($end->format('Y-m-d')) . "'";
    }
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = ' WHERE ' . implode(' AND ', $where);
}

// Total revenue per year
$annualRevenueSql = "SELECT YEAR(o.OrderDate) AS year, SUM(od.Quantity * od.UnitPrice * (1 - od.Discount)) AS revenue
    FROM orders o
    JOIN order_details od ON o.OrderID = od.OrderID
    {$whereSql}
    GROUP BY YEAR(o.OrderDate)
    ORDER BY YEAR(o.OrderDate) ASC";

$annualRevenue = [];
$result = $mysqli->query($annualRevenueSql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $annualRevenue[] = [
            'year' => $row['year'],
            'revenue' => round((float) $row['revenue'], 2)
        ];
    }
    $result->free();
}

// Best-selling products by quantity
$bestSellingSql = "SELECT p.ProductName AS productName, SUM(od.Quantity) AS totalQuantity
    FROM order_details od
    JOIN orders o ON o.OrderID = od.OrderID
    JOIN products p ON p.ProductID = od.ProductID
    {$whereSql}
    GROUP BY p.ProductID
    ORDER BY totalQuantity DESC
    LIMIT 10";

$bestSellingProducts = [];
$result = $mysqli->query($bestSellingSql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bestSellingProducts[] = [
            'productName' => $row['productName'],
            'totalQuantity' => (int) $row['totalQuantity']
        ];
    }
    $result->free();
}

// Revenue by product
$revenueByProductSql = "SELECT p.ProductName AS productName, SUM(od.Quantity * od.UnitPrice * (1 - od.Discount)) AS revenue
    FROM order_details od
    JOIN orders o ON o.OrderID = od.OrderID
    JOIN products p ON p.ProductID = od.ProductID
    {$whereSql}
    GROUP BY p.ProductID
    ORDER BY revenue DESC
    LIMIT 10";

$revenueByProduct = [];
$result = $mysqli->query($revenueByProductSql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $revenueByProduct[] = [
            'productName' => $row['productName'],
            'revenue' => round((float) $row['revenue'], 2)
        ];
    }
    $result->free();
}

// Top 10 customers by amount spent
$topCustomersSql = "SELECT c.CustomerID, c.CompanyName AS customerName, SUM(od.Quantity * od.UnitPrice * (1 - od.Discount)) AS spent
    FROM orders o
    JOIN order_details od ON o.OrderID = od.OrderID
    JOIN customers c ON c.CustomerID = o.CustomerID
    {$whereSql}
    GROUP BY c.CustomerID
    ORDER BY spent DESC
    LIMIT 10";

$topCustomers = [];
$result = $mysqli->query($topCustomersSql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topCustomers[] = [
            'customerID' => $row['CustomerID'],
            'customerName' => $row['customerName'],
            'spent' => round((float) $row['spent'], 2)
        ];
    }
    $result->free();
}

$response = [
    'annualRevenue' => $annualRevenue,
    'bestSellingProducts' => $bestSellingProducts,
    'revenueByProduct' => $revenueByProduct,
    'topCustomers' => $topCustomers
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$mysqli->close();