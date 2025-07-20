<?php

Flight::route('POST /api/sms', function() {
    header('Content-Type: application/json');
    
    $input = file_get_contents('php://input');
    
    // Debug: Log incoming request (only if enabled)
    if (getenv('DEBUG_SMS_REQUESTS') === 'true' || $_ENV['DEBUG_SMS_REQUESTS'] ?? false) {
        $debugData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'],
            'headers' => getallheaders(),
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'NOT_SET',
            'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'NOT_SET',
            'raw_body' => $input,
            'body_length' => strlen($input),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'NOT_SET'
        ];
        
        file_put_contents(__DIR__ . '/../debug_requests.log', 
            "=== REQUEST DEBUG ===\n" . 
            json_encode($debugData, JSON_PRETTY_PRINT) . 
            "\n\n", FILE_APPEND);
    }
    
    // Fix newlines in JSON from MacDroid
    $cleanInput = str_replace(["\n", "\r"], [" ", " "], $input);
    $data = json_decode($cleanInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = [
            'error' => 'Invalid JSON', 
            'json_error' => json_last_error_msg(),
            'raw_input' => $input,
            'cleaned_input' => $cleanInput
        ];
        if (getenv('DEBUG_SMS_REQUESTS') === 'true' || $_ENV['DEBUG_SMS_REQUESTS'] ?? false) {
            file_put_contents(__DIR__ . '/../debug_requests.log', 
                "JSON ERROR: " . json_encode($error, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
        }
        Flight::halt(400, json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]));
    }
    
    if (!isset($data['sender']) || !isset($data['content'])) {
        Flight::halt(400, json_encode(['error' => 'Missing sender or content']));
    }
    
    try {
        $parser = new SmsParser();
        $parsedData = $parser->parse($data['sender'], $data['content']);
        
        if (isset($parsedData['error'])) {
            Flight::halt(400, json_encode($parsedData));
        }
        
        $db = new Database();
        $insertId = $db->insertPayment($parsedData);
        
        if ($insertId) {
            Flight::json([
                'success' => true,
                'id' => $insertId,
                'data' => $parsedData
            ], 201);
        } else {
            Flight::halt(500, json_encode(['error' => 'Failed to save payment']));
        }
    } catch (Exception $e) {
        Flight::halt(500, json_encode(['error' => 'Server error: ' . $e->getMessage()]));
    }
});

Flight::route('GET /api/payments', function() {
    header('Content-Type: application/json');
    
    try {
        $db = new Database();
        $status = Flight::request()->query['status'] ?? null;
        $page = max(1, intval(Flight::request()->query['page'] ?? 1));
        $limit = min(100, max(10, intval(Flight::request()->query['limit'] ?? 50)));
        $search = Flight::request()->query['search'] ?? null;
        $dateFrom = Flight::request()->query['date_from'] ?? null;
        $dateTo = Flight::request()->query['date_to'] ?? null;
        
        $offset = ($page - 1) * $limit;
        $payments = $db->getAllPayments($status, $limit, $offset, $search, $dateFrom, $dateTo);
        $totalCount = $db->getPaymentCount($status, $search, $dateFrom, $dateTo);
        $totalPages = ceil($totalCount / $limit);
        
        Flight::json([
            'payments' => $payments,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_count' => $totalCount,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]);
    } catch (Exception $e) {
        Flight::halt(500, json_encode(['error' => 'Server error: ' . $e->getMessage()]));
    }
});

Flight::route('GET /api/payments/new', function() {
    header('Content-Type: application/json');
    
    try {
        $db = new Database();
        $since = Flight::request()->query['since'] ?? null;
        
        if (!$since) {
            Flight::halt(400, json_encode(['error' => 'Missing since parameter']));
        }
        
        $payments = $db->getNewPaymentsSince($since);
        
        Flight::json([
            'payments' => $payments,
            'count' => count($payments)
        ]);
    } catch (Exception $e) {
        Flight::halt(500, json_encode(['error' => 'Server error: ' . $e->getMessage()]));
    }
});

Flight::route('PUT /api/payments/@id/status', function($id) {
    header('Content-Type: application/json');
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['status'])) {
        Flight::halt(400, json_encode(['error' => 'Missing status']));
    }
    
    $allowedStatuses = ['pending', 'completed', 'cancelled'];
    if (!in_array($data['status'], $allowedStatuses)) {
        Flight::halt(400, json_encode(['error' => 'Invalid status. Allowed: ' . implode(', ', $allowedStatuses)]));
    }
    
    try {
        $db = new Database();
        $result = $db->updatePaymentStatus($id, $data['status']);
        
        if ($result) {
            Flight::json(['success' => true, 'message' => 'Status updated']);
        } else {
            Flight::halt(404, json_encode(['error' => 'Payment not found']));
        }
    } catch (Exception $e) {
        Flight::halt(500, json_encode(['error' => 'Server error: ' . $e->getMessage()]));
    }
});

Flight::route('GET /admin', function() {
    try {
        $db = new Database();
        $page = max(1, intval(Flight::request()->query['page'] ?? 1));
        $limit = 50;
        $status = Flight::request()->query['status'] ?? null;
        $search = Flight::request()->query['search'] ?? null;
        $dateFrom = Flight::request()->query['date_from'] ?? null;
        $dateTo = Flight::request()->query['date_to'] ?? null;
        
        $offset = ($page - 1) * $limit;
        $payments = $db->getAllPayments($status, $limit, $offset, $search, $dateFrom, $dateTo);
        $totalCount = $db->getPaymentCount($status, $search, $dateFrom, $dateTo);
        $totalPages = ceil($totalCount / $limit);
        
        Flight::render('admin', [
            'payments' => $payments,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_count' => $totalCount,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'filters' => [
                'status' => $status,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ]);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
});

Flight::route('GET /', function() {
    Flight::json(['message' => 'MCB Juice Payment API', 'version' => '1.0']);
});