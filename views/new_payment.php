<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Payment - MCB Juice Payment Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        h1 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .back-link { color: #007cba; text-decoration: none; margin-bottom: 20px; display: inline-block; }
        .back-link:hover { text-decoration: underline; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input, .form-group textarea, .form-group select { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;
        }
        .form-group textarea { height: 100px; resize: vertical; }
        .form-row { display: flex; gap: 20px; align-items: flex-start; }
        .form-row .form-group { flex: 1; min-width: 0; }
        .help-text { font-size: 12px; color: #6c757d; margin-top: 5px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-right: 10px; }
        .btn-primary { background: #007cba; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { opacity: 0.9; }
        .success { color: #28a745; font-weight: bold; margin: 10px 0; padding: 10px; background: #d4edda; border-radius: 4px; }
        .error { color: #dc3545; font-weight: bold; margin: 10px 0; padding: 10px; background: #f8d7da; border-radius: 4px; }
        .required { color: #dc3545; }
        
        @media (max-width: 768px) {
            .container { margin: 10px; padding: 15px; }
            .form-row { flex-direction: column; gap: 10px; }
            .btn { width: 100%; margin-bottom: 10px; margin-right: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/admin" class="back-link">← Back to Payment List</a>
        
        <h1>Create New Payment</h1>
        
        <?php if (isset($success)): ?>
            <div class="success">Payment created successfully! Payment ID: <?= $payment_id ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="/admin/new">
            <div class="form-row">
                <div class="form-group">
                    <label>Sender Phone <span class="required">*</span></label>
                    <input type="text" name="sender" value="<?= $_POST['sender'] ?? '' ?>" required 
                           placeholder="e.g., 57123456" pattern="[0-9]+" title="Enter phone number without spaces or special characters">
                    <div class="help-text">Enter the sender's phone number</div>
                </div>
                
                <div class="form-group">
                    <label>Amount (MUR) <span class="required">*</span></label>
                    <input type="number" name="amount" value="<?= $_POST['amount'] ?? '' ?>" required 
                           step="0.01" min="0.01" placeholder="e.g., 100.00">
                    <div class="help-text">Payment amount in Mauritian Rupees</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Reference Number <span class="required">*</span></label>
                    <input type="text" name="reference" value="<?= $_POST['reference'] ?? '' ?>" required 
                           placeholder="e.g., MCB123456">
                    <div class="help-text">Transaction reference from MCB</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Payment Date <span class="required">*</span></label>
                    <input type="date" name="payment_date" value="<?= $_POST['payment_date'] ?? date('Y-m-d') ?>" required>
                    <div class="help-text">Date when the payment was made</div>
                </div>
                
                <div class="form-group">
                    <label>Payment Time</label>
                    <input type="time" name="payment_time" value="<?= $_POST['payment_time'] ?? date('H:i') ?>">
                    <div class="help-text">Time when the payment was made</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Order Number</label>
                    <input type="text" name="order_number" value="<?= $_POST['order_number'] ?? '' ?>" 
                           placeholder="e.g., ORD-2024-001">
                    <div class="help-text">Optional: Link to order number</div>
                </div>
                
                <div class="form-group">
                    <label>Order ID</label>
                    <input type="text" name="order_id" value="<?= $_POST['order_id'] ?? '' ?>" 
                           placeholder="e.g., 123">
                    <div class="help-text">Optional: Internal order ID</div>
                </div>
            </div>
            
            <div class="form-group">
                <label>SMS Message</label>
                <textarea name="raw_message" placeholder="Optional: Original SMS message text"><?= $_POST['raw_message'] ?? '' ?></textarea>
                <div class="help-text">You can paste the original SMS message here for reference</div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Payment Method <span class="required">*</span></label>
                    <select name="payment_method" required>
                        <option value="mobile_number" <?= ($_POST['payment_method'] ?? 'mobile_number') == 'mobile_number' ? 'selected' : '' ?>>Mobile Number (SMS)</option>
                        <option value="bank_transfer" <?= ($_POST['payment_method'] ?? '') == 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                    <div class="help-text">How the payment was made</div>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="pending" <?= ($_POST['status'] ?? 'pending') == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="completed" <?= ($_POST['status'] ?? '') == 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= ($_POST['status'] ?? '') == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                    <div class="help-text">Payment processing status</div>
                </div>
            </div>
            
            <div>
                <button type="submit" class="btn btn-primary">Create Payment</button>
                <a href="/admin" class="btn btn-secondary" style="text-decoration: none; display: inline-block;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>