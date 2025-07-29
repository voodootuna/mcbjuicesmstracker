<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCB Juice Payment Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .table-container { overflow-x: auto; margin-top: 20px; border: 1px solid #ddd; border-radius: 4px; }
        table { border-collapse: collapse; width: auto; min-width: 100%; }
        th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; white-space: nowrap; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status.pending { background: #fff3cd; color: #856404; }
        .status.completed { background: #d4edda; color: #155724; }
        .status.cancelled { background: #f8d7da; color: #721c24; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-complete { background: #28a745; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        .btn-edit { background: #17a2b8; color: white; text-decoration: none; display: inline-block; margin-right: 5px; }
        .btn:hover { opacity: 0.8; }
        .filters { margin-bottom: 20px; }
        .filters select { padding: 8px; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .date { font-size: 12px; line-height: 1.3; width: 1%; }
        .date-day { display: block; font-weight: bold; }
        .date-time { display: block; font-size: 10px; color: #6c757d; }
        .sender-ref { line-height: 1.2; width: 1%; }
        .sender-ref-sender { display: block; font-family: monospace; font-size: 11px; font-weight: bold; overflow: hidden; text-overflow: ellipsis; }
        .sender-ref-reference { display: block; font-family: monospace; font-size: 9px; color: #6c757d; overflow: hidden; text-overflow: ellipsis; }
        .order-number { font-family: monospace; font-size: 11px; color: #28a745; width: 1%; overflow: hidden; text-overflow: ellipsis; }
        .order-id { font-family: monospace; font-size: 11px; color: #007cba; width: 1%; overflow: hidden; text-overflow: ellipsis; }
        .payment-method { font-size: 11px; width: 1%; }
        .amount { font-weight: bold; color: #007cba; width: 1%; }
        .raw-message { word-wrap: break-word; font-size: 10px; color: #666; white-space: normal; cursor: pointer; position: relative; }
        .raw-message-short { display: block; }
        .raw-message-full { display: none; }
        .raw-message.expanded .raw-message-short { display: none; }
        .raw-message.expanded .raw-message-full { display: block; }
        .expand-indicator { color: #007cba; font-weight: bold; margin-left: 5px; }
        .raw-message:hover { background-color: #f8f9fa; }
        .auto-reload { margin-left: 20px; }
        .auto-reload input[type="checkbox"] { margin-right: 5px; }
        .auto-reload.active { color: #28a745; font-weight: bold; }
        .filters { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; align-items: center; }
        .filters input, .filters select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .filters input[type="date"] { width: 150px; }
        .filters input[type="text"] { width: 200px; }
        .pagination { margin: 20px 0; text-align: center; }
        .pagination a, .pagination span { display: inline-block; padding: 8px 12px; margin: 0 2px; text-decoration: none; border: 1px solid #ddd; border-radius: 4px; }
        .pagination a:hover { background-color: #f5f5f5; }
        .pagination .current { background-color: #007cba; color: white; border-color: #007cba; }
        .pagination .disabled { color: #ccc; cursor: not-allowed; }
        
        /* Mobile Responsive Design */
        @media (max-width: 768px) {
            .container { margin: 10px; padding: 15px; }
            .filters { flex-direction: column; gap: 8px; }
            .filters input, .filters select, .filters button { width: 100%; margin-bottom: 8px; }
            .auto-reload { margin-left: 0; margin-top: 10px; }
            
            /* Hide less important columns on mobile */
            table th:nth-child(3), /* Sender */
            table td:nth-child(3),
            table th:nth-child(5), /* Reference */
            table td:nth-child(5),
            table th:nth-child(7), /* Order ID */
            table td:nth-child(7),
            table th:nth-child(9), /* Raw Message */
            table td:nth-child(9) {
                display: none;
            }
            
            /* Make remaining columns more mobile-friendly */
            table { font-size: 12px; }
            th, td { padding: 8px 4px; }
            .date { font-size: 10px; min-width: 80px; }
            .amount { font-size: 12px; min-width: 60px; }
            .order-number { font-size: 10px; }
            .btn { padding: 4px 8px; font-size: 10px; margin: 1px; }
            
            /* Stack pagination on mobile */
            .pagination { margin: 15px 0; }
            .pagination a, .pagination span { padding: 6px 8px; margin: 1px; font-size: 12px; }
        }
        
        @media (max-width: 480px) {
            /* Ultra-mobile view */
            .container { margin: 5px; padding: 10px; }
            h1 { font-size: 18px; }
            
            /* Show only essential columns */
            table th:nth-child(1), /* ID */
            table td:nth-child(1),
            table th:nth-child(6), /* Order Number */  
            table td:nth-child(6) {
                display: none;
            }
            
            /* Make table more compact */
            table { font-size: 11px; }
            th, td { padding: 6px 2px; }
            .date { font-size: 9px; min-width: 60px; }
            .amount { font-size: 11px; min-width: 50px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>MCB Juice Payment Management</h1>
        
        <div style="margin-bottom: 20px;">
            <a href="/admin/new" class="btn btn-primary" style="text-decoration: none; display: inline-block;">+ Create New Payment</a>
        </div>
        
        <form method="GET" class="filters">
            <select name="status" onchange="this.form.submit()">
                <option value="">All Payments</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            
            <input type="text" name="search" placeholder="Search reference, order#..." 
                   value="<?= htmlspecialchars($filters['search'] ?? '') ?>" 
                   onchange="this.form.submit()">
            
            <input type="date" name="date_from" 
                   value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" 
                   onchange="this.form.submit()" title="From date">
            
            <input type="date" name="date_to" 
                   value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" 
                   onchange="this.form.submit()" title="To date">
            
            <button type="submit">Filter</button>
            <a href="/admin" style="margin-left: 10px;">Clear</a>
            
            <span style="margin-left: 20px; font-weight: bold;">
                <?= $pagination['total_count'] ?> payments (Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?>)
            </span>
            
            <label class="auto-reload" id="autoReloadLabel">
                <input type="checkbox" id="autoReload" onchange="toggleAutoReload()">
                Smart auto-reload (checks every 30s)
            </label>
        </form>
        
        <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Sender/Ref</th>
                    <th>Amount</th>
                    <th>O#</th>
                    <th>OID</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Message</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr data-status="<?= htmlspecialchars($payment['status']) ?>">
                    <td><?= $payment['id'] ?></td>
                    <td class="date" title="<?= date('M j, Y H:i:s', strtotime($payment['created_at'])) ?>">
                        <span class="date-day"><?= date('M j', strtotime($payment['created_at'])) ?></span>
                        <span class="date-time"><?= date('H:i', strtotime($payment['created_at'])) ?></span>
                    </td>
                    <td class="sender-ref" title="Sender: <?= htmlspecialchars($payment['sender'] ?? '') ?>&#10;Ref: <?= htmlspecialchars($payment['reference'] ?? '') ?>">
                        <span class="sender-ref-sender"><?= htmlspecialchars($payment['sender'] ?? '-') ?></span>
                        <span class="sender-ref-reference"><?= htmlspecialchars($payment['reference'] ?? '-') ?></span>
                    </td>
                    <td class="amount">Rs <?= number_format($payment['amount'], 2) ?></td>
                    <td class="order-number" title="<?= htmlspecialchars($payment['order_number'] ?? '') ?>"><?= htmlspecialchars($payment['order_number'] ?? '-') ?></td>
                    <td class="order-id" title="<?= htmlspecialchars($payment['order_id'] ?? '') ?>"><?= htmlspecialchars($payment['order_id'] ?? '-') ?></td>
                    <td class="payment-method">
                        <?= ($payment['payment_method'] ?? 'mobile_number') == 'bank_transfer' ? 'Bank' : 'Mobile' ?>
                    </td>
                    <td>
                        <span class="status <?= $payment['status'] ?>">
                            <?= ucfirst($payment['status']) ?>
                        </span>
                    </td>
                    <td class="raw-message" onclick="toggleMessage(this)">
                        <span class="raw-message-short">
                            <?= htmlspecialchars(substr($payment['raw_message'], 0, 50)) ?>
                            <?php if (strlen($payment['raw_message']) > 50): ?>
                                <span class="expand-indicator">... [click to expand]</span>
                            <?php endif; ?>
                        </span>
                        <span class="raw-message-full">
                            <?= htmlspecialchars($payment['raw_message']) ?>
                            <span class="expand-indicator">[click to collapse]</span>
                        </span>
                    </td>
                    <td>
                        <a href="/admin/edit/<?= $payment['id'] ?>" class="btn btn-edit">Edit</a>
                        <?php if ($payment['status'] === 'pending'): ?>
                            <button class="btn btn-complete" onclick="updateStatus(<?= $payment['id'] ?>, 'completed')">Complete</button>
                            <button class="btn btn-cancel" onclick="updateStatus(<?= $payment['id'] ?>, 'cancelled')">Cancel</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($pagination['has_prev']): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['page'] - 1])) ?>">← Previous</a>
            <?php else: ?>
                <span class="disabled">← Previous</span>
            <?php endif; ?>
            
            <?php
            $start = max(1, $pagination['page'] - 2);
            $end = min($pagination['total_pages'], $pagination['page'] + 2);
            
            if ($start > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                <?php if ($start > 2): ?><span>...</span><?php endif; ?>
            <?php endif;
            
            for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i == $pagination['page']): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor;
            
            if ($end < $pagination['total_pages']): ?>
                <?php if ($end < $pagination['total_pages'] - 1): ?><span>...</span><?php endif; ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['total_pages']])) ?>"><?= $pagination['total_pages'] ?></a>
            <?php endif; ?>
            
            <?php if ($pagination['has_next']): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['page'] + 1])) ?>">Next →</a>
            <?php else: ?>
                <span class="disabled">Next →</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function updateStatus(id, status) {
            if (!confirm(`Mark payment ${id} as ${status}?`)) return;
            
            fetch(`/api/payments/${id}/status`, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({status: status})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
        
        
        function toggleMessage(element) {
            element.classList.toggle('expanded');
        }
        
        let autoReloadInterval = null;
        let lastCheckTime = new Date().toISOString();
        
        function toggleAutoReload() {
            const checkbox = document.getElementById('autoReload');
            const label = document.getElementById('autoReloadLabel');
            
            if (checkbox.checked) {
                // Start smart auto-reload
                lastCheckTime = new Date().toISOString();
                autoReloadInterval = setInterval(checkForNewPayments, 30000); // Check every 30 seconds
                
                label.classList.add('active');
                console.log('Smart auto-reload enabled: checking every 30 seconds');
            } else {
                // Stop auto-reload
                if (autoReloadInterval) {
                    clearInterval(autoReloadInterval);
                    autoReloadInterval = null;
                }
                
                label.classList.remove('active');
                console.log('Auto-reload disabled');
            }
        }
        
        function checkForNewPayments() {
            fetch(`/api/payments/new?since=${encodeURIComponent(lastCheckTime)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        console.log(`Found ${data.count} new payments, reloading...`);
                        location.reload();
                    } else {
                        lastCheckTime = new Date().toISOString();
                        console.log('No new payments');
                    }
                })
                .catch(error => {
                    console.error('Error checking for new payments:', error);
                });
        }
    </script>
</body>
</html>