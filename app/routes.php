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
    
    // Fix newlines and carriage returns in JSON from MacDroid
    $cleanInput = str_replace(["\n", "\r", "\t"], [" ", " ", " "], $input);
    $cleanInput = preg_replace('/\s+/', ' ', $cleanInput); // Collapse multiple spaces
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
    
    // Debug: Log successful JSON parsing
    if (getenv('DEBUG_SMS_REQUESTS') === 'true' || $_ENV['DEBUG_SMS_REQUESTS'] ?? false) {
        file_put_contents(__DIR__ . '/../debug_requests.log', 
            "JSON PARSED OK: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
    }
    
    if (!isset($data['sender']) || !isset($data['content'])) {
        Flight::halt(400, json_encode(['error' => 'Missing sender or content']));
    }
    
    try {
        $parser = new SmsParser();
        $parsedData = $parser->parse($data['sender'], $data['content']);
        
        // Debug: Log parsing result
        if (getenv('DEBUG_SMS_REQUESTS') === 'true' || $_ENV['DEBUG_SMS_REQUESTS'] ?? false) {
            file_put_contents(__DIR__ . '/../debug_requests.log', 
                "SMS PARSE RESULT: " . json_encode($parsedData, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
        }
        
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

Flight::route('PUT /api/payments/@id/order', function($id) {
    header('Content-Type: application/json');
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['order_number']) && !isset($data['order_id'])) {
        Flight::halt(400, json_encode(['error' => 'Missing order_number or order_id']));
    }
    
    try {
        $db = new Database();
        $orderNumber = $data['order_number'] ?? null;
        $orderId = $data['order_id'] ?? null;
        
        $result = $db->updatePaymentOrderDetails($id, $orderNumber, $orderId);
        
        if ($result) {
            Flight::json(['success' => true, 'message' => 'Order details updated']);
        } else {
            Flight::halt(404, json_encode(['error' => 'Payment not found']));
        }
    } catch (Exception $e) {
        Flight::halt(500, json_encode(['error' => 'Server error: ' . $e->getMessage()]));
    }
});

Flight::route('GET /api/payments/@id', function($id) {
    header('Content-Type: application/json');
    
    try {
        $db = new Database();
        $payment = $db->getPaymentById($id);
        
        if ($payment) {
            Flight::json(['payment' => $payment]);
        } else {
            Flight::halt(404, json_encode(['error' => 'Payment not found']));
        }
    } catch (Exception $e) {
        Flight::halt(500, json_encode(['error' => 'Server error: ' . $e->getMessage()]));
    }
});

Flight::route('DELETE /api/payments/@id', function($id) {
    header('Content-Type: application/json');
    
    try {
        $db = new Database();
        
        // First check if payment exists
        $payment = $db->getPaymentById($id);
        if (!$payment) {
            Flight::halt(404, json_encode(['error' => 'Payment not found']));
        }
        
        // Delete the payment
        $result = $db->deletePayment($id);
        
        if ($result) {
            Flight::json(['success' => true, 'message' => 'Payment deleted successfully']);
        } else {
            Flight::halt(500, json_encode(['error' => 'Failed to delete payment']));
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

Flight::route('GET /admin/edit/@id', function($id) {
    try {
        $db = new Database();
        $payment = $db->getPaymentById($id);
        
        if (!$payment) {
            Flight::halt(404, "Payment not found");
        }
        
        Flight::render('edit_payment', ['payment' => $payment]);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
});

Flight::route('GET /admin/new', function() {
    Flight::render('new_payment');
});

Flight::route('POST /admin/new', function() {
    try {
        // Get form data
        $sender = trim($_POST['sender'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $reference = trim($_POST['reference'] ?? '');
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_time = $_POST['payment_time'] ?? date('H:i');
        $order_number = trim($_POST['order_number'] ?? '');
        $order_id = trim($_POST['order_id'] ?? '');
        $raw_message = trim($_POST['raw_message'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        $payment_method = $_POST['payment_method'] ?? 'mobile_number';
        
        // Combine date and time for payment_date field
        $payment_datetime = $payment_date . ' ' . $payment_time . ':00';
        
        // Validate required fields
        if (empty($sender) || empty($reference) || $amount <= 0) {
            Flight::render('new_payment', [
                'error' => 'Please fill in all required fields'
            ]);
            return;
        }
        
        // Prepare data for insertion
        $data = [
            'sender' => $sender,
            'amount' => $amount,
            'reference' => $reference,
            'payment_date' => $payment_datetime,
            'order_number' => $order_number ?: null,
            'order_id' => $order_id ?: null,
            'raw_message' => $raw_message ?: "Manual entry created on " . date('Y-m-d H:i:s'),
            'payment_method' => $payment_method
        ];
        
        // Insert into database
        $db = new Database();
        $payment_id = $db->insertPayment($data);
        
        if ($payment_id) {
            // Update status if not pending
            if ($status !== 'pending') {
                $db->updatePaymentStatus($payment_id, $status);
            }
            
            Flight::render('new_payment', [
                'success' => true,
                'payment_id' => $payment_id
            ]);
        } else {
            Flight::render('new_payment', [
                'error' => 'Failed to create payment'
            ]);
        }
    } catch (Exception $e) {
        Flight::render('new_payment', [
            'error' => 'Error: ' . $e->getMessage()
        ]);
    }
});

Flight::route('GET /', function() {
    Flight::json(['message' => 'MCB Juice Payment API', 'version' => '1.0']);
});