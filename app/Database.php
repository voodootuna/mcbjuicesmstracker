<?php

class Database {
    private $db;
    private $dbPath;
    
    public function __construct() {
        $this->dbPath = __DIR__ . '/../database/payments.db';
        $this->ensureDatabaseDirectory();
        $this->connect();
        $this->createTables();
    }
    
    private function ensureDatabaseDirectory() {
        $dbDir = dirname($this->dbPath);
        if (!is_dir($dbDir)) {
            if (!mkdir($dbDir, 0755, true)) {
                throw new Exception("Failed to create database directory: $dbDir");
            }
        }
        
        if (!is_writable($dbDir)) {
            throw new Exception("Database directory is not writable: $dbDir");
        }
    }
    
    private function connect() {
        try {
            // Check if SQLite3 extension is loaded
            if (!class_exists('SQLite3')) {
                throw new Exception("SQLite3 extension is not installed");
            }
            
            $this->db = new SQLite3($this->dbPath);
            
            // Set optimizations
            $this->db->busyTimeout(5000);
            $this->db->exec('PRAGMA journal_mode = WAL');
            $this->db->exec('PRAGMA synchronous = NORMAL');
            $this->db->exec('PRAGMA cache_size = 1000');
            
        } catch (Exception $e) {
            $dir = dirname($this->dbPath);
            $perms = is_dir($dir) ? substr(sprintf('%o', fileperms($dir)), -4) : 'N/A';
            
            throw new Exception("Database connection failed: " . $e->getMessage() . 
                              " (Path: {$this->dbPath}, Dir perms: {$perms})");
        }
    }
    
    private function createTables() {
        $sql = "CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender VARCHAR(50),
            amount DECIMAL(10,2),
            reference VARCHAR(50),
            order_number VARCHAR(50),
            payment_date DATE,
            raw_message TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->db->exec($sql);
        
        // Add performance indexes
        $this->createIndexes();
    }
    
    private function createIndexes() {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_payments_created_at ON payments(created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status)",
            "CREATE INDEX IF NOT EXISTS idx_payments_reference ON payments(reference)",
            "CREATE INDEX IF NOT EXISTS idx_payments_order_number ON payments(order_number)",
            "CREATE INDEX IF NOT EXISTS idx_payments_status_created ON payments(status, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_payments_date ON payments(payment_date)"
        ];
        
        foreach ($indexes as $index) {
            $this->db->exec($index);
        }
    }
    
    public function insertPayment($data) {
        $sql = "INSERT INTO payments (sender, amount, reference, order_number, payment_date, raw_message) 
                VALUES (:sender, :amount, :reference, :order_number, :payment_date, :raw_message)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sender', $data['sender'], SQLITE3_TEXT);
        $stmt->bindValue(':amount', $data['amount'], SQLITE3_FLOAT);
        $stmt->bindValue(':reference', $data['reference'], SQLITE3_TEXT);
        $stmt->bindValue(':order_number', $data['order_number'], SQLITE3_TEXT);
        $stmt->bindValue(':payment_date', $data['payment_date'], SQLITE3_TEXT);
        $stmt->bindValue(':raw_message', $data['raw_message'], SQLITE3_TEXT);
        
        $result = $stmt->execute();
        return $result ? $this->db->lastInsertRowID() : false;
    }
    
    public function getAllPayments($status = null, $limit = 50, $offset = 0, $search = null, $dateFrom = null, $dateTo = null) {
        $sql = "SELECT * FROM payments";
        $params = [];
        $conditions = [];
        
        if ($status) {
            $conditions[] = "status = :status";
            $params[':status'] = $status;
        }
        
        if ($search) {
            $conditions[] = "(reference LIKE :search OR order_number LIKE :search OR raw_message LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        if ($dateFrom) {
            $conditions[] = "DATE(created_at) >= :dateFrom";
            $params[':dateFrom'] = $dateFrom;
        }
        
        if ($dateTo) {
            $conditions[] = "DATE(created_at) <= :dateTo";
            $params[':dateTo'] = $dateTo;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, SQLITE3_TEXT);
        }
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        
        $result = $stmt->execute();
        $payments = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $payments[] = $row;
        }
        
        return $payments;
    }
    
    public function getPaymentCount($status = null, $search = null, $dateFrom = null, $dateTo = null) {
        $sql = "SELECT COUNT(*) as count FROM payments";
        $params = [];
        $conditions = [];
        
        if ($status) {
            $conditions[] = "status = :status";
            $params[':status'] = $status;
        }
        
        if ($search) {
            $conditions[] = "(reference LIKE :search OR order_number LIKE :search OR raw_message LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        if ($dateFrom) {
            $conditions[] = "DATE(created_at) >= :dateFrom";
            $params[':dateFrom'] = $dateFrom;
        }
        
        if ($dateTo) {
            $conditions[] = "DATE(created_at) <= :dateTo";
            $params[':dateTo'] = $dateTo;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, SQLITE3_TEXT);
        }
        
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row['count'];
    }
    
    public function getNewPaymentsSince($timestamp, $limit = 10) {
        $sql = "SELECT * FROM payments WHERE created_at > :timestamp ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':timestamp', $timestamp, SQLITE3_TEXT);
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        
        $result = $stmt->execute();
        $payments = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $payments[] = $row;
        }
        
        return $payments;
    }
    
    public function updatePaymentStatus($id, $status) {
        $sql = "UPDATE payments SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        
        return $stmt->execute();
    }
    
    public function getPaymentById($id) {
        $sql = "SELECT * FROM payments WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
}