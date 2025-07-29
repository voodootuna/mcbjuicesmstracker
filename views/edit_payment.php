<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Payment #<?= $payment['id'] ?> - MCB Juice Payment Admin</title>
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
        .form-group textarea { height: 80px; resize: vertical; }
        .form-row { display: flex; gap: 20px; align-items: flex-start; }
        .form-row .form-group { flex: 1; min-width: 0; }
        .readonly { background-color: #f8f9fa; color: #6c757d; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-right: 10px; }
        .btn-primary { background: #007cba; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.9; }
        .success { color: #28a745; font-weight: bold; margin: 10px 0; }
        .error { color: #dc3545; font-weight: bold; margin: 10px 0; }
        .info-section { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .info-section h3 { margin-top: 0; color: #495057; }
        .amount { font-size: 18px; font-weight: bold; color: #007cba; }
        .status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status.pending { background: #fff3cd; color: #856404; }
        .status.completed { background: #d4edda; color: #155724; }
        .status.cancelled { background: #f8d7da; color: #721c24; }
        
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
        
        <h1>Edit Payment #<?= $payment['id'] ?></h1>
        
        <div class="info-section">
            <h3>Payment Information</h3>
            <p><strong>Amount:</strong> <span class="amount">Rs <?= number_format($payment['amount'], 2) ?></span></p>
            <p><strong>Sender:</strong> <?= htmlspecialchars($payment['sender']) ?></p>
            <p><strong>Reference:</strong> <?= htmlspecialchars($payment['reference'] ?? 'N/A') ?></p>
            <p><strong>Date:</strong> <?= date('M j, Y H:i', strtotime($payment['created_at'])) ?></p>
            <p><strong>Payment Method:</strong> <?= $payment['payment_method'] == 'bank_transfer' ? 'Bank Transfer' : 'Mobile Number (SMS)' ?></p>
            <p><strong>Raw Message:</strong></p>
            <textarea readonly class="readonly"><?= htmlspecialchars($payment['raw_message']) ?></textarea>
        </div>
        
        <form id="editForm">
            <h3>Edit Payment Details</h3>
            
            <div class="form-group">
                <label for="status">Payment Status</label>
                <select id="status" name="status">
                    <option value="pending" <?= $payment['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="completed" <?= $payment['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $payment['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="order_number">Order Number</label>
                    <input type="text" id="order_number" name="order_number" 
                           value="<?= htmlspecialchars($payment['order_number'] ?? '') ?>" 
                           placeholder="e.g., 9308F86353">
                </div>
                <div class="form-group">
                    <label for="order_id">Order ID</label>
                    <input type="text" id="order_id" name="order_id" 
                           value="<?= htmlspecialchars($payment['order_id'] ?? '') ?>" 
                           placeholder="e.g., PMPro Order ID">
                </div>
            </div>
            
            <div id="message"></div>
            
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="/admin" class="btn btn-secondary">Cancel</a>
            <button type="button" class="btn btn-danger" onclick="deletePayment()">Delete Payment</button>
        </form>
    </div>

    <script>
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const status = formData.get('status');
            const order_number = formData.get('order_number') || null;
            const order_id = formData.get('order_id') || null;
            
            // Track promises for both updates
            const promises = [];
            
            // Update status
            promises.push(
                fetch(`/api/payments/<?= $payment['id'] ?>/status`, {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ status: status })
                })
            );
            
            // Update order details
            promises.push(
                fetch(`/api/payments/<?= $payment['id'] ?>/order`, {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        order_number: order_number,
                        order_id: order_id
                    })
                })
            );
            
            // Execute both updates
            Promise.all(promises)
                .then(responses => Promise.all(responses.map(r => r.json())))
                .then(results => {
                    if (results.every(r => r.success)) {
                        showMessage('Payment details updated successfully!', 'success');
                        setTimeout(() => {
                            window.location.href = '/admin';
                        }, 1500);
                    } else {
                        const errors = results.filter(r => !r.success).map(r => r.error || 'Unknown error');
                        showMessage('Error: ' + errors.join(', '), 'error');
                    }
                })
                .catch(error => {
                    showMessage('Error: ' + error.message, 'error');
                });
        });
        
        function deletePayment() {
            const paymentInfo = `
Payment #<?= $payment['id'] ?>
Amount: Rs <?= number_format($payment['amount'], 2) ?>
Reference: <?= htmlspecialchars($payment['reference'] ?? 'N/A') ?>
Date: <?= date('M j, Y H:i', strtotime($payment['created_at'])) ?>`;

            if (confirm(`Are you sure you want to DELETE this payment?\n\n${paymentInfo}\n\nThis action CANNOT be undone!`)) {
                if (confirm('FINAL CONFIRMATION: This will permanently delete the payment record. Are you absolutely sure?')) {
                    fetch(`/api/payments/<?= $payment['id'] ?>`, {
                        method: 'DELETE',
                        headers: {'Content-Type': 'application/json'}
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            showMessage('Payment deleted successfully! Redirecting...', 'success');
                            setTimeout(() => {
                                window.location.href = '/admin';
                            }, 2000);
                        } else {
                            showMessage('Error: ' + (result.error || 'Failed to delete payment'), 'error');
                        }
                    })
                    .catch(error => {
                        showMessage('Error: ' + error.message, 'error');
                    });
                }
            }
        }
        
        function showMessage(text, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.textContent = text;
            messageDiv.className = type;
            
            setTimeout(() => {
                messageDiv.textContent = '';
                messageDiv.className = '';
            }, 3000);
        }
    </script>
</body>
</html>