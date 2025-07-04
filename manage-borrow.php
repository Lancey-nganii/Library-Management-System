<?php
session_start();
require_once 'config.php';

// Show any PHP errors (for debugging)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Only allow admin access
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

// Get admin name
$admin_name = 'Admin';
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($full_name);
    if ($stmt->fetch() && !empty($full_name)) {
        $admin_name = explode(' ', trim($full_name))[0];
    }
    $stmt->close();
}

// Get pending extension request count
$pending_requests_count = 0;
$pending_result = $conn->query("SELECT COUNT(*) as count FROM extension_requests WHERE status = 'pending'");
if ($pending_result && $row = $pending_result->fetch_assoc()) {
    $pending_requests_count = (int)$row['count'];
}

// Fetch pending reservation count
$pending_reservations_count = 0;
$pending_reservations_result = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'");
if ($pending_reservations_result && $row = $pending_reservations_result->fetch_assoc()) {
    $pending_reservations_count = (int)$row['count'];
}

$borrow_records = $conn->query("
    SELECT 
        br.borrow_id, 
        br.book_id,
        br.user_id,
        b.title, 
        u.full_name, 
        br.borrow_date, 
        br.return_date, 
        br.due_date,
        br.status
    FROM borrow_records br
    JOIN books b ON br.book_id = b.book_id
    JOIN users u ON br.user_id = u.user_id
    ORDER BY br.borrow_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Borrowings | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <header class="admin-header">
        <h1><i class="fas fa-history"></i> Manage Borrowings</h1>
        <a href="login_register.php?logout=1" class="logout-btn">Logout</a>
    </header>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <h3><?= htmlspecialchars($admin_name) ?></h3>
                <p><?= ucfirst($_SESSION['user_type']) ?></p>
            </div>
            <ul class="nav-links">
                <li><a href="admin_page.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage-books.php" class="nav-link"><i class="fas fa-book"></i> Manage Books</a></li>
                <li><a href="manage-users.php" class="nav-link"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="manage-borrow.php" class="nav-link active"><i class="fas fa-history"></i> Borrowings</a></li>
                <li>
                    <a href="extension-requests.php" class="nav-link">
                        <i class="fas fa-hourglass-half"></i> Extension Requests
                        <?php if ($pending_requests_count > 0): ?>
                            <span style="background:#e74c3c;color:#fff;padding:2px 8px;border-radius:12px;font-size:0.9em;margin-left:8px;">
                                <?= $pending_requests_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="reservation-requests.php" class="nav-link">
                        <i class="fas fa-calendar-check"></i> Reservation Requests
                        <?php if ($pending_reservations_count > 0): ?>
                            <span style="background:#3498db;color:#fff;padding:2px 8px;border-radius:12px;font-size:0.9em;margin-left:8px;">
                                <?= $pending_reservations_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </aside>
        <div class="admin-container">
            <main class="admin-main">
                <section class="admin-section active">
                    <h2>Borrow Records</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Borrower</th>
                                <th>Borrow Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($borrow_records && $borrow_records->num_rows > 0): ?>
                            <?php while ($row = $borrow_records->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['title'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['full_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['borrow_date'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['return_date'] ?? '') ?></td>
                                    <td>
                                       <?php
                                            $today = new DateTime();
                                            $returned = !empty($row['return_date']);
                                            $due = new DateTime($row['due_date']);
                                            if ($returned) {
                                                echo '<span style="color: #2ecc71;">Returned</span>';
                                            } else {
                                                // Check for approved extension with new_return_date in the future
                                                $ext_stmt = $conn->prepare("SELECT new_return_date FROM extension_requests WHERE book_id = ? AND user_id = ? AND status = 'approved' ORDER BY new_return_date DESC LIMIT 1");
                                                $ext_stmt->bind_param("ii", $row['book_id'], $row['user_id']);
                                                $ext_stmt->execute();
                                                $ext_stmt->bind_result($new_return_date);
                                                $has_extension = false;
                                                if ($ext_stmt->fetch() && $new_return_date && (new DateTime($new_return_date)) > $today) {
                                                    $has_extension = true;
                                                }
                                                $ext_stmt->close();
                                                if ($has_extension) {
                                                    echo '<span style="color: #2980b9;">Extended</span>';
                                                } elseif ($today > $due) {
                                                    echo '<span style="color: #e67e22;">Overdue</span>';
                                                } else {
                                                    echo '<span style="color: #e74c3c;">Borrowed</span>';
                                                }
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="no-data">No borrow records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </section>
            </main>
        </div>
    </div>
</body>
</html>
