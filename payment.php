<?php
// Handle POST requests (API calls and callbacks)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set timezone and define log file
    date_default_timezone_set('Africa/Nairobi');
    define('LOG_FILE', __DIR__ . '/payment_log.txt');
    
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "D1a2n3i4e5l6.";
    $dbname = "slms";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }
    
    // --- Get Raw Input ---
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    // If content-type is JSON, handle the JSON data
    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        logMessage("Request received: $rawData");
        
        // --- ROUTING ---
        if(isset($data['Body']['stkCallback'])){
            handleCallback($data['Body']['stkCallback']);
            exit;
        }
        
        if($data && isset($data['student_name'])){
            initiateSTKPush($data);
            exit;
        }
    } 
    // Handle form data (for payment status checking)
    else {
        if (isset($_POST['action']) && $_POST['action'] === 'check_payment_status' && isset($_POST['checkout_id'])) {
            checkPaymentStatus($_POST['checkout_id']);
            exit;
        }
    }
    
    // Fallback for POST requests
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'msg'=>'Invalid action']);
    exit;
}

// If it's a GET request, serve the HTML page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Output the HTML frontend
    outputFrontend();
    exit;
}

// ==================== FUNCTIONS ====================

// --- Log Helper ---
function logMessage($msg){
    file_put_contents(LOG_FILE, "[".date("Y-m-d H:i:s")."] $msg\n", FILE_APPEND);
}

// --- Callback Handler ---
function handleCallback($stk){
    global $conn;
    $resultCode = $stk['ResultCode'];
    $resultDesc = $stk['ResultDesc'];
    logMessage("DEBUG: Callback ResultCode=$resultCode, ResultDesc=$resultDesc");

    if($resultCode==0 && isset($stk['CallbackMetadata']['Item'])){
        $items = [];
        foreach($stk['CallbackMetadata']['Item'] as $item){
            $items[$item['Name']] = $item['Value'] ?? null;
        }

        $amount  = $items['Amount'] ?? 'N/A';
        $receipt = $items['MpesaReceiptNumber'] ?? 'N/A';
        $phone   = $items['PhoneNumber'] ?? 'N/A';
        $date    = $items['TransactionDate'] ?? 'N/A';

        $temp = fetchTempRecord($phone, $amount);
        if($temp){
            savePermanentPayment($temp, $phone, $amount, $receipt, $date);
            updatePaymentStatus($temp['checkout_id'], 'success');
            logMessage("✅ Payment recorded: $receipt, $amount, $phone, Term=".$temp['term']);
        } else {
            logMessage("⚠ No temp record found for $phone, $amount");
        }
    } else {
        // Payment failed
        $merchantRequestID = $stk['MerchantRequestID'] ?? 'N/A';
        $checkoutRequestID = $stk['CheckoutRequestID'] ?? 'N/A';
        
        // Try to find the checkout_id from the pending_payments table
        $checkout_id = findCheckoutId($merchantRequestID, $checkoutRequestID);
        if ($checkout_id) {
            updatePaymentStatus($checkout_id, 'failed');
            logMessage("❌ Payment failed: $resultDesc for checkout_id: $checkout_id");
        } else {
            logMessage("❌ Payment failed: $resultDesc (could not find checkout_id)");
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['status'=>'ok']);
}

// --- Find Checkout ID from Merchant Request ID ---
function findCheckoutId($merchantRequestID, $checkoutRequestID) {
    global $conn;
    
    // Try to find by merchant request ID first
    $stmt = $conn->prepare("SELECT checkout_id FROM pending_payments WHERE merchant_request_id = ?");
    $stmt->bind_param("s", $merchantRequestID);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return $row['checkout_id'];
    }
    
    // If not found, try by checkout request ID
    $stmt = $conn->prepare("SELECT checkout_id FROM pending_payments WHERE checkout_request_id = ?");
    $stmt->bind_param("s", $checkoutRequestID);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return $row['checkout_id'];
    }
    
    return false;
}

// --- Update Payment Status ---
function updatePaymentStatus($checkout_id, $status) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE pending_payments SET status = ? WHERE checkout_id = ?");
    $stmt->bind_param("ss", $status, $checkout_id);
    $stmt->execute();
}

// --- Fetch Temp Record ---
function fetchTempRecord($phone, $amount){
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM pending_payments WHERE phone=? AND amount=? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("sd", $phone, $amount);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows>0 ? $res->fetch_assoc() : false;
}

// --- Save Permanent Payment ---
function savePermanentPayment($temp, $phone, $amount, $receipt, $date){
    global $conn;
    $stmt = $conn->prepare("INSERT INTO student_payments (student_name, admission_no, term, phone, amount, receipt_number, transaction_date) VALUES (?,?,?,?,?,?,?)");
    $transaction_date = date('Y-m-d H:i:s', strtotime($date));
    $stmt->bind_param("sssdsss", $temp['student_name'], $temp['admission_no'], $temp['term'], $phone, $amount, $receipt, $transaction_date);
    $stmt->execute();
}

// --- Check Payment Status ---
function checkPaymentStatus($checkout_id) {
    global $conn;
    
    header('Content-Type: application/json');
    
    $stmt = $conn->prepare("SELECT status FROM pending_payments WHERE checkout_id = ?");
    $stmt->bind_param("s", $checkout_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['status' => $row['status']]);
    } else {
        // Check if payment was successful and moved to student_payments
        $stmt = $conn->prepare("SELECT * FROM student_payments WHERE receipt_number IN (SELECT receipt_number FROM pending_payments WHERE checkout_id = ?)");
        $stmt->bind_param("s", $checkout_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'pending']);
        }
    }
}

// --- Initiate STK Push ---
function initiateSTKPush($data){
    global $conn;

    $student_name = $conn->real_escape_string($data['student_name']);
    $admission_no = $conn->real_escape_string($data['admission_no']);
    $term = $conn->real_escape_string($data['term']);
    $amount = floatval($data['amount']);
    $phone = $data['phone'] ?? "2547XXXXXXXX";

    $checkout_id = uniqid('stk_');

    $conn->query("INSERT INTO pending_payments (checkout_id, student_name, admission_no, term, phone, amount, created_at, status) 
                  VALUES ('$checkout_id','$student_name','$admission_no','$term','$phone','$amount',NOW(), 'pending')");

    $token = getAccessToken();
    logMessage("OAuth Token: $token");

    $BusinessShortCode = "174379";
    $Passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
    $Timestamp = date("YmdHis");
    $Password = base64_encode($BusinessShortCode.$Passkey.$Timestamp);

    $stk_payload = [
        "BusinessShortCode"=>$BusinessShortCode,
        "Password"=>$Password,
        "Timestamp"=>$Timestamp,
        "TransactionType"=>"CustomerPayBillOnline",
        "Amount"=>$amount,
        "PartyA"=>$phone,
        "PartyB"=>$BusinessShortCode,
        "PhoneNumber"=>$phone,
        "CallBackURL"=>"https://6f733c3aacfc.ngrok-free.app/payment.php",
        "AccountReference"=>$admission_no,
        "TransactionDesc"=>"SLMS Fee Payment"
    ];

    $ch = curl_init("https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        logMessage("cURL Error: $error");
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'message'=>"cURL Error: $error",'raw_response'=>null]);
        return;
    }
    curl_close($ch);

    logMessage("STK Push Response: $response");
    $respData = json_decode($response,true);

    header('Content-Type: application/json');
    if(isset($respData['ResponseCode']) && $respData['ResponseCode']==0){
        // Save merchant request ID and checkout request ID for callback matching
        $merchantRequestID = $respData['MerchantRequestID'] ?? '';
        $checkoutRequestID = $respData['CheckoutRequestID'] ?? '';
        
        $stmt = $conn->prepare("UPDATE pending_payments SET merchant_request_id = ?, checkout_request_id = ? WHERE checkout_id = ?");
        $stmt->bind_param("sss", $merchantRequestID, $checkoutRequestID, $checkout_id);
        $stmt->execute();
        
        logMessage("[$checkout_id] STK Push initiated successfully.");
        echo json_encode(['success'=>true,'checkout_id'=>$checkout_id,'raw_response'=>json_decode(json_encode($respData), true)]);
    } else {
        $errorMsg = $respData['errorMessage'] ?? 'STK Push failed';
        logMessage("[$checkout_id] STK Push failed: $errorMsg");
        echo json_encode(['success'=>false,'message'=>$errorMsg,'raw_response'=>json_decode(json_encode($respData), true)]);
    }
}

// --- Get OAuth Token ---
function getAccessToken(){
    $consumerKey = "kZGdl8Ff0iWOrybQQQ5q09BbJsnQJICUPndEbRABAvqDszu3";
    $consumerSecret = "X47uG3A47hHZHh2DkpKaJxlgwYr2UQScu9yZ23OH1zDpRSYb8DsCvANJlZ3Ad6JI";
    $credentials = base64_encode("$consumerKey:$consumerSecret");
    $url = "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $credentials"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    logMessage("Access Token Response: $result");
    $data = json_decode($result,true);
    return $data['access_token'] ?? '';
}

// --- Output Frontend HTML ---
function outputFrontend() {
    // Output the HTML frontend
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLMS Fee Payment System</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, #2c3e50, #4a6491);
            color: white;
            padding: 20px 0;
            text-align: center;
            border-radius: 8px 8px 0 0;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        
        .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .content {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .form-section {
            flex: 1;
            min-width: 300px;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .info-section {
            flex: 1;
            min-width: 300px;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #4a6491;
            box-shadow: 0 0 0 2px rgba(74, 100, 145, 0.2);
        }
        
        button {
            background: linear-gradient(135deg, #2c3e50, #4a6491);
            color: white;
            border: none;
            padding: 14px 20px;
            width: 100%;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .payment-info {
            background: #e8f4fc;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #3498db;
        }
        
        .payment-info h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #d1e9fb;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .log-container {
            margin-top: 30px;
        }
        
        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .log-header h3 {
            color: #2c3e50;
        }
        
        #clearLog {
            padding: 8px 15px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: auto;
        }
        
        .log {
            background: #2c3e50;
            color: #fff;
            padding: 20px;
            border-radius: 8px;
            height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 14px;
        }
        
        .log-entry {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #3e5871;
        }
        
        .log-entry:last-child {
            border-bottom: none;
        }
        
        .timestamp {
            color: #3498db;
            margin-right: 10px;
        }
        
        .success {
            color: #2ecc71;
        }
        
        .error {
            color: #e74c3c;
        }
        
        .warning {
            color: #f39c12;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            background: #f8f9fa;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .status-dot.ready {
            background: #2ecc71;
        }
        
        .status-dot.processing {
            background: #f39c12;
            animation: pulse 1.5s infinite;
        }
        
        .status-dot.error {
            background: #e74c3c;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        footer {
            text-align: center;
            margin-top: 30px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .content {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>SLMS Fee Payment</h1>
            <p class="subtitle">Secure payment processing via M-Pesa</p>
        </header>
        
        <div class="content">
            <div class="form-section">
                <h2>Payment Details</h2>
                <div class="form-group">
                    <label for="student_name">Student Name:</label>
                    <input type="text" id="student_name" placeholder="Enter full name">
                </div>
                
                <div class="form-group">
                    <label for="admission_no">Admission No:</label>
                    <input type="text" id="admission_no" placeholder="Student admission number">
                </div>
                
                <div class="form-group">
                    <label for="term">Term:</label>
                    <select id="term">
                        <option value="Term1">Term 1</option>
                        <option value="Term2">Term 2</option>
                        <option value="Term3">Term 3</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number:</label>
                    <input type="text" id="phone" placeholder="2547XXXXXXXX">
                </div>
                
                <div class="form-group">
                    <label for="amount">Amount (KES):</label>
                    <input type="number" id="amount" placeholder="Amount to pay">
                </div>
                
                <button id="payButton">Pay Now</button>
                
                <div class="status-indicator">
                    <div class="status-dot ready"></div>
                    <span id="statusText">Ready to process payment</span>
                </div>
            </div>
            
            <div class="info-section">
                <h2>Payment Information</h2>
                <div class="payment-info">
                    <h3>Instructions</h3>
                    <div class="info-item">
                        <span class="info-label">Payment Method:</span>
                        <span>M-Pesa</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Business Shortcode:</span>
                        <span>174379</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Account Reference:</span>
                        <span id="accountRef">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Transaction Status:</span>
                        <span id="transactionStatus">Pending</span>
                    </div>
                </div>
                
                <div class="log-container">
                    <div class="log-header">
                        <h3>Payment Log</h3>
                        <button id="clearLog">Clear Log</button>
                    </div>
                    <div class="log" id="paymentLog"></div>
                </div>
            </div>
        </div>
        
        <footer>
            <p>© 2023 School Learning Management System (SLMS) | Secure Payment Gateway</p>
        </footer>
    </div>

    <script>
        document.addEventListener(\'DOMContentLoaded\', function() {
            const payButton = document.getElementById(\'payButton\');
            const clearLogButton = document.getElementById(\'clearLog\');
            const paymentLog = document.getElementById(\'paymentLog\');
            const statusText = document.getElementById(\'statusText\');
            const statusDot = document.querySelector(\'.status-dot\');
            const accountRef = document.getElementById(\'accountRef\');
            const transactionStatus = document.getElementById(\'transactionStatus\');
            
            // Format and add a log entry
            function addLogEntry(message, type = \'info\') {
                const now = new Date();
                const timestamp = now.toLocaleTimeString();
                const logEntry = document.createElement(\'div\');
                logEntry.className = `log-entry ${type}`;
                logEntry.innerHTML = `<span class="timestamp">[${timestamp}]</span> ${message}`;
                paymentLog.appendChild(logEntry);
                paymentLog.scrollTop = paymentLog.scrollHeight;
            }
            
            // Update status indicator
            function updateStatus(message, status) {
                statusText.textContent = message;
                statusDot.className = \'status-dot \' + status;
                
                // Update transaction status display
                if (status === \'ready\') {
                    transactionStatus.textContent = \'Ready\';
                    transactionStatus.style.color = \'#2ecc71\';
                } else if (status === \'processing\') {
                    transactionStatus.textContent = \'Processing\';
                    transactionStatus.style.color = \'#f39c12\';
                } else if (status === \'error\') {
                    transactionStatus.textContent = \'Failed\';
                    transactionStatus.style.color = \'#e74c3c\';
                }
            }
            
            // Clear log
            clearLogButton.addEventListener(\'click\', function() {
                paymentLog.innerHTML = \'\';
                addLogEntry(\'Log cleared\', \'info\');
            });
            
            // Initialize log
            addLogEntry(\'Payment system initialized\', \'success\');
            updateStatus(\'Ready to process payment\', \'ready\');
            
            // Handle payment button click
            payButton.addEventListener(\'click\', function() {
                // Get form values
                const studentName = document.getElementById(\'student_name\').value;
                const admissionNo = document.getElementById(\'admission_no\').value;
                const term = document.getElementById(\'term\').value;
                const phone = document.getElementById(\'phone\').value;
                const amount = document.getElementById(\'amount\').value;
                
                // Basic validation
                if (!studentName || !admissionNo || !phone || !amount) {
                    addLogEntry(\'Please fill all required fields\', \'error\');
                    updateStatus(\'Validation failed\', \'error\');
                    return;
                }
                
                if (!phone.startsWith(\'254\') || phone.length !== 12) {
                    addLogEntry(\'Phone number must start with 254 and be 12 digits long\', \'error\');
                    updateStatus(\'Invalid phone number\', \'error\');
                    return;
                }
                
                if (amount <= 0) {
                    addLogEntry(\'Amount must be greater than zero\', \'error\');
                    updateStatus(\'Invalid amount\', \'error\');
                    return;
                }
                
                // Update UI
                addLogEntry(\'Initiating payment request...\', \'info\');
                updateStatus(\'Processing payment...\', \'processing\');
                accountRef.textContent = admissionNo;
                transactionStatus.textContent = \'Initiating\';
                transactionStatus.style.color = \'#f39c12\';
                
                // Prepare payment data
                const paymentData = {
                    student_name: studentName,
                    admission_no: admissionNo,
                    term: term,
                    phone: phone,
                    amount: parseFloat(amount)
                };
                
                // Send payment request to server
                fetch(\'payment.php\', {
                    method: \'POST\',
                    headers: {
                        \'Content-Type\': \'application/json\'
                    },
                    body: JSON.stringify(paymentData)
                })
                .then(response => {
                    // First, check if the response is OK (status in the range 200-299)
                    if (!response.ok) {
                        throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                    }
                    
                    // Check the content type to see if it\'s JSON
                    const contentType = response.headers.get(\'content-type\');
                    if (!contentType || !contentType.includes(\'application/json\')) {
                        // If it\'s not JSON, read as text and log the issue
                        return response.text().then(text => {
                            addLogEntry(`Server returned non-JSON response: ${text}`, \'error\');
                            throw new Error(\'Server returned non-JSON response\');
                        });
                    }
                    
                    // If it is JSON, parse it
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        addLogEntry(`STK push initiated successfully. Checkout ID: ${data.checkout_id}`, \'success\');
                        addLogEntry(\'Waiting for payment confirmation...\', \'info\');
                        transactionStatus.textContent = \'Pending\';
                        transactionStatus.style.color = \'#f39c12\';
                        
                        // Start checking payment status
                        checkPaymentStatus(data.checkout_id);
                    } else {
                        addLogEntry(`Payment initiation failed: ${data.message}`, \'error\');
                        updateStatus(\'Payment failed\', \'error\');
                        transactionStatus.textContent = \'Failed\';
                        transactionStatus.style.color = \'#e74c3c\';
                    }
                })
                .catch(error => {
                    addLogEntry(`Request failed: ${error.message}`, \'error\');
                    updateStatus(\'Request failed\', \'error\');
                    transactionStatus.textContent = \'Error\';
                    transactionStatus.style.color = \'#e74c3c\';
                });
            });
            
            // Function to check payment status
            function checkPaymentStatus(checkoutId) {
                addLogEntry(`Checking payment status for ${checkoutId}...`, \'info\');
                
                // Create a FormData object to send the checkout ID
                const formData = new FormData();
                formData.append(\'checkout_id\', checkoutId);
                formData.append(\'action\', \'check_payment_status\');
                
                fetch(\'payment.php\', {
                    method: \'POST\',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                    }
                    
                    // Always try to parse as JSON first
                    return response.json().catch(() => {
                        // If JSON parsing fails, try to parse as text
                        return response.text().then(text => {
                            // If it looks like JSON, try to parse it manually
                            if (text.trim().startsWith(\'{\') || text.trim().startsWith(\'[\')) {
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    addLogEntry(`Server returned malformed JSON: ${text}`, \'error\');
                                    throw new Error(\'Server returned malformed JSON\');
                                }
                            } else {
                                addLogEntry(`Server returned non-JSON response: ${text}`, \'error\');
                                throw new Error(\'Server returned non-JSON response\');
                            }
                        });
                    });
                })
                .then(data => {
                    if (data.status === \'success\') {
                        addLogEntry(\'Payment confirmed successfully!\', \'success\');
                        updateStatus(\'Payment completed\', \'ready\');
                        transactionStatus.textContent = \'Completed\';
                        transactionStatus.style.color = \'#2ecc71\';
                    } else if (data.status === \'failed\') {
                        addLogEntry(\'Payment failed. Please try again.\', \'error\');
                        updateStatus(\'Payment failed\', \'error\');
                        transactionStatus.textContent = \'Failed\';
                        transactionStatus.style.color = \'#e74c3c\';
                    } else if (data.status === \'pending\') {
                        addLogEntry(\'Payment still pending, checking again in 5 seconds...\', \'warning\');
                        setTimeout(() => checkPaymentStatus(checkoutId), 5000);
                    } else {
                        addLogEntry(`Payment status: ${data.status}`, \'info\');
                        updateStatus(\'Payment processing\', \'processing\');
                        setTimeout(() => checkPaymentStatus(checkoutId), 5000);
                    }
                })
                .catch(error => {
                    addLogEntry(`Error checking payment status: ${error.message}`, \'error\');
                    updateStatus(\'Status check failed\', \'error\');
                });
            }
        });
    </script>
</body>
</html>';
}
?>