<?php
session_start();
require_once 'database.php'; // Include your database connection settings

// Set timezone and current time for check-in
date_default_timezone_set('Asia/Manila');
$checkInDate = date("Y-m-d H:i:s");
$checkInDisplay = date("F j, Y h:i A");

$guest_name = $_GET['guest_name'] ?? '';
$checkin = $_GET['checkin'] ?? '';
$checkout = $_GET['checkout'] ?? '';
$num_people = $_GET['num_people'] ?? '';
$room_number = $_GET['room_number'] ?? '';


$room_number = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int)($_POST['room_number'] ?? 0)
    : (int)($_GET['room_number'] ?? 0);

if (!$room_number) {
    echo "Error: Room number is required.";
    exit();
}

// Fetch room info
$roomQuery = "SELECT * FROM rooms WHERE room_number = ? AND status = 'available'";
$stmt = $conn->prepare($roomQuery);
$stmt->bind_param("i", $room_number);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();
$stmt->close();

if (!$room && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "
    <style>
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545; /* red danger */
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.4s, transform 0.4s;
            transform: translateY(-20px);
            z-index: 9999;
        }
        .toast.show {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }
        .btn-toast {
            margin: 20px;
            padding: 10px 16px;
            border: none;
            background: #007bff;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-toast:hover {
            background: #0056b3;
        }
    </style>

    <button id='toastBtn' class='btn-toast'>Show Warning</button>
    <div id='toast' class='toast'>Room not found or not available.</div>

    <script>
        document.getElementById('toastBtn').addEventListener('click', function() {
            let toast = document.getElementById('toast');
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000); // auto-hide after 4s
        });
    </script>
    ";
    exit();
}

// Check if there is an ACTIVE booking for this room
$bookingStatus = null;
$bookingQuery = "SELECT status FROM bookings WHERE room_number = ? ORDER BY end_date DESC LIMIT 1";
$stmtBooking = $conn->prepare($bookingQuery);
$stmtBooking->bind_param("i", $room_number);
$stmtBooking->execute();
$stmtBooking->bind_result($bookingStatus);
$stmtBooking->fetch();
$stmtBooking->close();

// Only block if the last booking is still active
if ($bookingStatus === 'booked' || $bookingStatus === 'checked_in') {
    echo "
    <div style='max-width:600px;margin:60px auto;padding:40px 30px;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);text-align:center;'>
        <i class='fas fa-ban fa-3x text-danger mb-3'></i>
        <h2 class='mb-2'>Check-In Unavailable</h2>
        <p class='mb-3'>This room is still <strong>occupied</strong> or <strong>reserved</strong>.<br>
        Please select another room.</p>
        <a href='receptionist-room.php' class='btn btn-primary mt-2'><i class='fas fa-arrow-left me-2'></i>Back to Rooms</a>
    </div>
    ";
    exit();
}

// ✅ If last booking was completed/checked_out → room is available
$conn->query("UPDATE rooms SET status = 'available' WHERE room_number = $room_number");

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $guest_name = htmlspecialchars(trim($_POST['guest_name']));
    $address = htmlspecialchars(trim($_POST['address']));
    $telephone = htmlspecialchars(trim($_POST['telephone']));
    $room_type = htmlspecialchars(trim($_POST['room_type']));
    $stay_duration = (int)($_POST['stay_duration']);
    $amount_paid = isset($_POST['amount_paid']) && is_numeric($_POST['amount_paid']) 
        ? (float)$_POST['amount_paid'] 
        : 0.00;
    $change = max(0, $amount_paid - $total_price);
    $payment_mode = htmlspecialchars(trim($_POST['payment_mode']));
    $gcash_reference = htmlspecialchars(trim($_POST['gcash_ref_id'] ?? ''));
    $user_id = $_SESSION['user_id'];

    if (
        empty($guest_name) || empty($address) || empty($telephone) ||
        $stay_duration <= 0 || $payment_mode === "select" ||
        ($payment_mode === "gcash" && empty($gcash_reference))
    ) {
        echo "Please fill in all required fields.";
        exit();
    }

    $pricing = [
        3 => $room['price_3hrs'],
        6 => $room['price_6hrs'],
        12 => $room['price_12hrs'],
        24 => $room['price_24hrs']
    ];

    if (!isset($pricing[$stay_duration])) {
        echo "Invalid stay duration.";
        exit();
    }

    $total_price = $pricing[$stay_duration];

    // Calculate check-out time
    $check_out_date = new DateTime();
    $check_out_date->modify("+$stay_duration hours");
    $formatted_check_out = $check_out_date->format('Y-m-d H:i:s');

    // Insert into the database
    $sql = "INSERT INTO checkins (guest_name, address, telephone, room_number, room_type, stay_duration, total_price, amount_paid, change_amount, payment_mode, gcash_reference, check_in_date, check_out_date, receptionist_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssisisddssssi",
        $guest_name,
        $address,
        $telephone,
        $room_number,
        $room_type,
        $stay_duration,
        $total_price,
        $amount_paid,
        $change,
        $payment_mode,
        $gcash_reference,
        $checkInDate,
        $formatted_check_out,
        $user_id
    );
    
    

    if ($stmt->execute()) {
        $updateRoomStatusQuery = "UPDATE rooms SET status = 'booked' WHERE room_number = ?";
        $updateStmt = $conn->prepare($updateRoomStatusQuery);
        $updateStmt->bind_param("i", $room_number);
        $updateStmt->execute();

        $_SESSION['guest_name'] = $guest_name;
        $_SESSION['room_number'] = $room_number;
        $_SESSION['room_type'] = $room_type;
        $_SESSION['check_in_date'] = $checkInDisplay;
        $_SESSION['check_out_date'] = $check_out_date->format('F j, Y h:i A');
        $_SESSION['total_price'] = $total_price;

        // header("Location: receptionist-guest.php");
        header("Location: receptionist-guest.php?success=checkedin"); // with toast alert
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>

<!-- HTML FORM -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-In | Gitarra Apartelle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="style.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        gcash: '#0063F7',
                    },
                },
            },
        }
    </script>
</head>

<body class="bg-gray-50 font-sans">
    <div class="container mx-auto py-8 px-4">
        <div class="max-w-5xl mx-auto">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden transition-all duration-300 hover:shadow-xl mb-6">
                <div class="bg-primary-600 text-white px-6 py-4 flex justify-between items-center">
                    <h4 class="text-xl font-semibold flex items-center"><i class="fas fa-check-circle mr-2"></i>Guest Check-In</h4>
                    <div id="currentTime" class="text-white text-sm"></div>
                </div>
                
                <div class="p-6">
                    <form method="post" id="checkInForm" onsubmit="return validateForm();">
                        <input type="hidden" name="room_number" value="<?php echo htmlspecialchars($room_number); ?>">
                        
                        <div class="mb-8">
                            <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg mb-6">
                                <div class="flex items-center">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <p>You are checking in a guest for <span class="font-semibold">Room <?php echo $room['room_number']; ?> (<?php echo ucfirst($room['room_type']); ?>)</span></p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-white rounded-xl shadow-md overflow-hidden h-full transition-all duration-300 hover:shadow-lg">
                                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                                        <h5 class="font-medium text-gray-700 flex items-center"><i class="fas fa-user mr-2 text-primary-500"></i>Guest Information</h5>
                                    </div>
                                    <div class="p-5">
                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-medium mb-2">Guest Name</label>
                                            <input type="text" name="guest_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors" value="<?= htmlspecialchars($guest_name) ?>" required oninput="this.value = this.value.replace(/[0-9]/g, '')">
                                        </div>
                                        <div class="flex flex-col mb-5">
                                        <label for="telephone" class="mb-1 font-medium text-gray-700">Contact Number</label>

                                        <div class="flex">
                                            <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                                            <i class="fas fa-mobile"></i>
                                            </span>
                                            <input
                                            type="text"
                                            id="telephone"
                                            name="telephone"
                                            class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors"
                                            placeholder="+63 9xx-xxx-xxxx"
                                            required
                                            oninput="formatPhilippineNumber(this)"
                                            onblur="checkPhoneValidity(this)"
                                            >
                                        </div>

                                            <div id="phone-error" class="text-red-500 text-sm mb-1 hidden">
                                            Please enter a valid mobile number (e.g. +63 9XX-XXX-XXXX).
                                        </div>

                                        </div>

                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-medium mb-2">Address</label>
                                            <div class="flex">
                                                <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </span>
                                                <input type="text" name="address" class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-white rounded-xl shadow-md overflow-hidden h-full transition-all duration-300 hover:shadow-lg">
                                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                                        <h5 class="font-medium text-gray-700 flex items-center"><i class="fas fa-calendar-alt mr-2 text-primary-500"></i>Stay Information</h5>
                                    </div>
                                    <div class="p-5">
                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-medium mb-2">Check-in Date & Time</label>
                                            <div class="flex">
                                                <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                                                    <i class="fas fa-clock"></i>
                                                </span>
                                                <input type="text" class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg bg-gray-50" value="<?php echo $checkInDisplay; ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-medium mb-2">Stay Duration</label>
                                            <select name="stay_duration" id="stay_duration" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors" required onchange="updateSummary();">
                                                <option value="">Select Duration</option>
                                                <option value="3">3 Hours - ₱<?php echo number_format($room['price_3hrs'], 2); ?></option>
                                                <option value="6">6 Hours - ₱<?php echo number_format($room['price_6hrs'], 2); ?></option>
                                                <option value="12">12 Hours - ₱<?php echo number_format($room['price_12hrs'], 2); ?></option>
                                                <option value="24">24 Hours - ₱<?php echo number_format($room['price_24hrs'], 2); ?></option>
                                            </select>
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-gray-700 text-sm font-medium mb-2">Estimated Check-out</label>
                                            <div class="flex">
                                                <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                                                    <i class="fas fa-calendar-check"></i>
                                                </span>
                                                <input type="text" id="checkout_datetime" class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg bg-gray-50" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-8">
                            <div class="bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:shadow-lg">
                                <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                                    <h5 class="font-medium text-gray-700 flex items-center"><i class="fas fa-credit-card mr-2 text-primary-500"></i>Payment Method</h5>
                                </div>
                                <div class="p-5">
                                    <input type="hidden" name="payment_mode" id="payment_mode" value="select">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="border border-gray-200 rounded-lg p-4 cursor-pointer transition-all duration-300 hover:border-gray-400 hover:-translate-y-1" id="cash_option" onclick="selectPayment('cash')">
                                            <div class="flex items-center">
                                                <div class="text-2xl text-green-500 mr-3">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                </div>
                                                <div>
                                                    <h5 class="font-medium mb-1">Cash Payment</h5>
                                                    <p class="text-sm text-gray-500">Pay with cash upon check-in</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="border border-gray-200 rounded-lg p-4 cursor-pointer transition-all duration-300 hover:border-gray-400 hover:-translate-y-1" id="gcash_option" onclick="selectPayment('gcash')">
                                            <div class="flex items-center">
                                                <div class="text-2xl text-[#0063F7] mr-3">
                                                    <i class="fas fa-mobile-alt"></i>
                                                </div>
                                                <div>
                                                    <h5 class="font-medium mb-1">GCash Payment</h5>
                                                    <p class="text-sm text-gray-500">Pay using GCash mobile wallet</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
<!-- CASH PAYMENT SECTION -->
<div id="cash_section" class="hidden mt-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-700 text-sm font-medium mb-2">Amount Paid</label>
            <div class="flex">
                <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">₱</span>
                <input type="number" name="amount_paid" id="cash_amount_paid"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors"
                    oninput="calculateChange(); validateCashAmount();">
            </div>
            <p id="cash_error" class="text-red-500 text-sm mt-1 hidden">Amount paid must be at least equal to the total price.</p>
        </div>

        <div>
            <label class="block text-gray-700 text-sm font-medium mb-2">Change</label>
            <div class="flex">
                <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">₱</span>
                <input type="text" name="change" id="change"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg bg-gray-50" readonly>
            </div>
        </div>
    </div>
</div>

<!-- GCASH PAYMENT SECTION -->
<div id="gcash_section" class="hidden mt-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
                <h5 class="font-medium text-blue-700 flex items-center mb-3">
                    <i class="fas fa-info-circle mr-2"></i>GCash Payment Instructions
                </h5>
                <ol class="list-decimal pl-5 text-blue-700 text-sm space-y-2">
                    <li>Open your GCash app</li>
                    <li>Send payment to: <span class="font-semibold">09123456789</span></li>
                    <li>Enter the exact amount: <span class="font-semibold" id="gcash_amount_display">₱0.00</span></li>
                    <li>Complete the payment and note your reference number</li>
                </ol>
            </div>
        </div>

        <div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-medium mb-2">Amount Paid via GCash</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">₱</span>
                    <input type="number" name="amount_paid" id="gcash_amount_paid"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg bg-gray-50" readonly>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-medium mb-2">GCash Reference Number</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">
                        <i class="fas fa-hashtag"></i>
                    </span>
                    <input type="text" name="gcash_ref_id" id="gcash_ref_id"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-colors"
                        placeholder="Enter your GCash reference number"
                        oninput="validateGCashRef();">
                </div>
                <p id="gcash_error" class="text-red-500 text-sm mt-1 hidden">Please enter the GCash reference number.</p>
                <p id="gcash_digit_error" class="text-red-500 text-sm mt-1 hidden">Reference number must contain digits only.</p>
                <p class="text-xs text-gray-500 mt-1">Enter the reference number from your GCash transaction</p>
            </div>
        </div>
    </div>
</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:shadow-lg">
                                <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                                    <h5 class="font-medium text-gray-700 flex items-center"><i class="fas fa-receipt mr-2 text-primary-500"></i>Booking Summary</h5>
                                </div>
                                <div class="p-5">
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Room Number:</span>
                                            <span class="font-medium"><?php echo $room['room_number']; ?></span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Room Type:</span>
                                            <span class="font-medium"><?php echo ucfirst($room['room_type']); ?></span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Duration:</span>
                                            <span class="font-medium" id="summary_duration">-</span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Check-in:</span>
                                            <span class="font-medium"><?php echo $checkInDisplay; ?></span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Check-out:</span>
                                            <span class="font-medium" id="summary_checkout">-</span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Payment Method:</span>
                                            <span class="font-medium" id="summary_payment">-</span>
                                        </div>
                                        <div class="flex justify-between pt-4 mt-2 border-t border-gray-200">
                                            <span class="text-gray-800 font-medium">Total Amount:</span>
                                            <span class="font-bold text-primary-600" id="summary_total">₱0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex flex-col justify-end">
                                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-3 px-4 rounded-lg transition-colors duration-300 flex items-center justify-center mb-3">
                                    <i class="fas fa-check-circle mr-2"></i>Confirm Check-In
                                </button>
                                <a href="receptionist-room.php" class="w-full bg-white hover:bg-gray-100 text-gray-700 font-medium py-3 px-4 rounded-lg border border-gray-300 transition-colors duration-300 flex items-center justify-center">
                                    <i class="fas fa-arrow-left mr-2"></i>Back to Rooms
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to update the estimated check-out date and total price based on stay duration
        const priceMap = {
            3: <?php echo (float)$room['price_3hrs']; ?>,
            6: <?php echo (float)$room['price_6hrs']; ?>,
            12: <?php echo (float)$room['price_12hrs']; ?>,
            24: <?php echo (float)$room['price_24hrs']; ?>,
        };


        function updateSummary() {
            const duration = parseInt(document.getElementById("stay_duration").value);
            if (!duration || !priceMap[duration]) {
                document.getElementById("checkout_datetime").value = '';
                document.getElementById("summary_duration").textContent = '-';
                document.getElementById("summary_checkout").textContent = '-';
                document.getElementById("summary_total").textContent = '₱0.00';
                document.getElementById("gcash_amount_display").textContent = '₱0.00';
                if (document.getElementById("gcash_amount_paid")) {
                    document.getElementById("gcash_amount_paid").value = '';
                }
                return;
            }
            
            const now = new Date();
            const checkoutTime = new Date(now.getTime() + duration * 60 * 60 * 1000);
            const formattedCheckout = checkoutTime.toLocaleString();
            
            document.getElementById("checkout_datetime").value = formattedCheckout;
            document.getElementById("summary_duration").textContent = `${duration} hours`;
            document.getElementById("summary_checkout").textContent = formattedCheckout;
            
            const totalPrice = priceMap[duration];
            document.getElementById("summary_total").textContent = `₱${totalPrice.toFixed(2)}`;
            document.getElementById("gcash_amount_display").textContent = `₱${totalPrice.toFixed(2)}`;
            
            if (document.getElementById("gcash_amount_paid")) {
                document.getElementById("gcash_amount_paid").value = totalPrice.toFixed(2);
            }
        }

        function selectPayment(mode) {
            // Update hidden input
            document.getElementById("payment_mode").value = mode;
            
            // Update UI
            document.getElementById("cash_option").classList.remove("ring-2", "ring-primary-500", "border-primary-500", "bg-primary-50");
            document.getElementById("gcash_option").classList.remove("ring-2", "ring-primary-500", "border-primary-500", "bg-primary-50");
            document.getElementById(`${mode}_option`).classList.add("ring-2", "ring-primary-500", "border-primary-500", "bg-primary-50");
            
            // Show/hide appropriate sections
            document.getElementById("cash_section").classList.add("hidden");
            document.getElementById("gcash_section").classList.add("hidden");
            document.getElementById(`${mode}_section`).classList.remove("hidden");
            
            // Update summary
            document.getElementById("summary_payment").textContent = mode === 'cash' ? 'Cash' : 'GCash';
            
            // Set required fields
            const gcashRefInput = document.getElementById("gcash_ref_id");
            if (gcashRefInput) {
                gcashRefInput.required = mode === "gcash";
            }
            
            // Update amount fields
            const duration = parseInt(document.getElementById("stay_duration").value);
            if (duration && priceMap[duration]) {
                const totalPrice = priceMap[duration];
                if (mode === "gcash") {
                    document.getElementById("gcash_amount_paid").value = totalPrice.toFixed(2);
                }
            }
        }

        function calculateChange() {
            const total = parseFloat(document.getElementById("summary_total").textContent.replace('₱', '').replace(',', '')) || 0;
            const paid = parseFloat(document.getElementById("cash_amount_paid").value) || 0;
            const changeInput = document.getElementById("change");

            if (paid > total) {
                // ✅ Show change only when overpaid
                changeInput.value = (paid - total).toFixed(2);
            } else {
                // ✅ Hide change if exact or underpaid
                changeInput.value = '';
            }
        }
         
            // ---- FORM VALIDATION ----
            function validateForm() {
                const paymentMode = document.getElementById("payment_mode").value;
                const duration = parseInt(document.getElementById("stay_duration").value);
                const totalPrice = priceMap[duration];
                const paid = parseFloat(document.getElementById("cash_amount_paid").value) || 0;

                document.getElementById("cash_error").classList.add("hidden");
                document.getElementById("gcash_error").classList.add("hidden");
                document.getElementById("gcash_digit_error").classList.add("hidden");

                if (!duration || !priceMap[duration]) {
                    alert("Please select a stay duration.");
                    return false;
                }

                if (paymentMode === "select") {
                    alert("Please select a payment method.");
                    return false;
                }

                if (paymentMode === "cash") {
                    if (paid < totalPrice) {
                        document.getElementById("cash_error").classList.remove("hidden");
                        return false;
                    }
                } else if (paymentMode === "gcash") {
                    const refNumber = document.getElementById("gcash_ref_id").value.trim();
                    if (!refNumber) {
                        document.getElementById("gcash_error").classList.remove("hidden");
                        return false;
                    } else if (!/^\d+$/.test(refNumber)) {
                        document.getElementById("gcash_digit_error").classList.remove("hidden");
                        return false;
                    }
                }

                return true;
            }

            // ---- LIVE VALIDATION ----
            function validateCashAmount() {
                const duration = parseInt(document.getElementById("stay_duration").value);
                const totalPrice = priceMap[duration];
                const paid = parseFloat(document.getElementById("cash_amount_paid").value) || 0;
                const error = document.getElementById("cash_error");

                if (paid >= totalPrice) {
                    // ✅ Valid if exact or more
                    error.classList.add("hidden");
                } else {
                    // ❌ Not enough payment
                    error.classList.remove("hidden");
                }
            }

        function validateGCashRef() {
            const refNumber = document.getElementById("gcash_ref_id").value.trim();
            const errorEmpty = document.getElementById("gcash_error");
            const errorDigits = document.getElementById("gcash_digit_error");

            // Reset all
            errorEmpty.classList.add("hidden");
            errorDigits.classList.add("hidden");

            if (!refNumber) {
                errorEmpty.classList.remove("hidden");
            } else if (!/^\d+$/.test(refNumber)) {
                errorDigits.classList.remove("hidden");
            }
        }

        // Update clock
        function updateClock() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { 
                weekday: 'long',
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
        }

        setInterval(updateClock, 1000);
        updateClock();

        // validate contact number
        function formatPhilippineNumber(input) {
        let value = input.value.replace(/[^\d+]/g, "");

        if (!value.startsWith("+63")) {
            value = "+63" + value.replace(/^\+?63?/, "");
        }

        const digits = value.replace(/\D/g, "").substring(2, 12);

        let formatted = "+63";
        if (digits.length > 0) formatted += " " + digits.substring(0, 1);
        if (digits.length > 1) formatted += digits.substring(1, 3);
        if (digits.length > 3) formatted += "-" + digits.substring(3, 6);
        if (digits.length > 6) formatted += "-" + digits.substring(6, 10);

        input.value = formatted;
        }

        function checkPhoneValidity(input) {
        const pattern = /^\+63 9\d{2}-\d{3}-\d{4}$/;
        const feedback = document.getElementById("phone-error");

        if (!pattern.test(input.value)) {
            input.classList.add("is-invalid");
            feedback.classList.remove("hidden");
        } else {
            input.classList.remove("is-invalid");
            feedback.classList.add("hidden");
        }
        }

        // Clean up before submitting (remove spaces and dashes)
        document.querySelector("form")?.addEventListener("submit", function () {
        const input = document.getElementById("telephone");
        input.value = input.value.replace(/[\s-]/g, ""); // e.g. +639123456789
        });
    </script>
</body>

</html>