<?php

class SmsParser {
    private $allowedSenders = [];
    
    public function __construct() {
        $this->loadAllowedSenders();
    }
    
    private function loadAllowedSenders() {
        $configPath = __DIR__ . '/../config/allowed_senders.json';
        
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            if (json_last_error() === JSON_ERROR_NONE && isset($config['senders'])) {
                $this->allowedSenders = $config['senders'];
            } else {
                error_log("Warning: Invalid JSON in allowed_senders.json, using default whitelist");
                $this->allowedSenders = ['MCB'];
            }
        } else {
            // Default fallback if config file doesn't exist
            $this->allowedSenders = ['MCB'];
        }
    }
    
    private function isAllowedSender($sender) {
        return in_array($sender, $this->allowedSenders, true);
    }
    
    public function parse($sender, $content) {
        if (!$this->isAllowedSender($sender)) {
            return ['error' => 'Invalid sender: ' . $sender . '. Allowed: ' . implode(', ', $this->allowedSenders)];
        }
        
        if (strpos($content, 'Juice Summary') !== false) {
            return ['error' => 'Summary message - not a payment'];
        }
        
        $data = [
            'sender' => $sender,
            'amount' => $this->extractAmount($content),
            'reference' => $this->extractReference($content),
            'order_number' => $this->extractOrderNumber($content),
            'payment_date' => $this->extractDate($content),
            'raw_message' => $content
        ];
        
        if (!$data['amount']) {
            return ['error' => 'Could not extract amount'];
        }
        
        return $data;
    }
    
    private function extractAmount($content) {
        if (preg_match('/Rs\s*(\d+(?:\.\d{2})?)/i', $content, $matches)) {
            return floatval($matches[1]);
        }
        return null;
    }
    
    private function extractReference($content) {
        if (preg_match('/reference\s+([A-Z0-9]+)/i', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function extractOrderNumber($content) {
        if (preg_match('/&\s*text\s+([A-Z0-9]+)/i', $content, $matches)) {
            $text = $matches[1];
            if (preg_match('/^[0-9A-F]{10}$/i', $text)) {
                return $text;
            }
        }
        return null;
    }
    
    private function extractDate($content) {
        if (preg_match('/on\s+(\d{2}\/\d{2}\/\d{2})/i', $content, $matches)) {
            $dateParts = explode('/', $matches[1]);
            if (count($dateParts) === 3) {
                $year = '20' . $dateParts[2];
                $month = str_pad($dateParts[1], 2, '0', STR_PAD_LEFT);
                $day = str_pad($dateParts[0], 2, '0', STR_PAD_LEFT);
                return "$year-$month-$day";
            }
        }
        return date('Y-m-d');
    }
}