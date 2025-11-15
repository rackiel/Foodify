<?php
include '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

$user_id = $_SESSION['user_id'];

echo "<h1>DEBUG: Residents List Issue</h1>";

// Get team officer's info
$stmt = $conn->prepare("SELECT user_id, full_name, address, role FROM user_accounts WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$officer = $result->fetch_assoc();
$stmt->close();

echo "<h2>Team Officer Info</h2>";
echo "<p><strong>ID:</strong> " . $officer['user_id'] . "</p>";
echo "<p><strong>Name:</strong> " . $officer['full_name'] . "</p>";
echo "<p><strong>Role:</strong> " . $officer['role'] . "</p>";
echo "<p><strong>Address:</strong> " . ($officer['address'] ? '<span style="color:green;"><strong>' . htmlspecialchars($officer['address']) . '</strong></span>' : '<strong style="color:red;">NOT SET</strong>') . "</p>";

if (empty($officer['address'])) {
    echo "<p style='color:red;'>⚠️ Officer has no address! Must set address in Settings first.</p>";
} else {
    echo "<p style='color:green;'>✓ Officer has address set.</p>";
}

echo "<hr>";
echo "<h2>All Users with Addresses</h2>";

$result = $conn->query("SELECT user_id, full_name, role, address FROM user_accounts WHERE address IS NOT NULL AND address != '' ORDER BY role, address");
echo "<table border='1' cellpadding='10' style='width:100%;'>";
echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Name</th><th>Role</th><th>Address</th></tr>";
$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    $bgcolor = ($row['address'] === $officer['address']) ? '#ffffcc' : '';
    echo "<tr style='background:$bgcolor;'>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>" . $row['full_name'] . "</td>";
    echo "<td>" . $row['role'] . "</td>";
    echo "<td>" . htmlspecialchars($row['address']) . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p>Total users with addresses: $count</p>";

echo "<hr>";
echo "<h2>Residents Count by Address</h2>";

$result = $conn->query("SELECT address, COUNT(*) as count FROM user_accounts WHERE role = 'resident' AND address IS NOT NULL AND address != '' GROUP BY address");
echo "<table border='1' cellpadding='10' style='width:100%;'>";
echo "<tr style='background:#f0f0f0;'><th>Address</th><th>Count</th></tr>";
while ($row = $result->fetch_assoc()) {
    $match = ($row['address'] === $officer['address']) ? '✓ MATCH' : '';
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['address']) . " $match</td>";
    echo "<td>" . $row['count'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>Residents for Officer's Address</h2>";

if (!empty($officer['address'])) {
    $stmt = $conn->prepare("SELECT user_id, full_name, email, phone_number, address, role, status FROM user_accounts WHERE role = 'resident' AND address = ?");
    $stmt->bind_param('s', $officer['address']);
    $stmt->execute();
    $result = $stmt->get_result();
    $residents = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo "<p>Searching for residents with address: <strong>" . htmlspecialchars($officer['address']) . "</strong></p>";
    echo "<p>Found: <strong>" . count($residents) . " residents</strong></p>";

    if (count($residents) > 0) {
        echo "<table border='1' cellpadding='10' style='width:100%;'>";
        echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Status</th></tr>";
        foreach ($residents as $resident) {
            echo "<tr>";
            echo "<td>" . $resident['user_id'] . "</td>";
            echo "<td>" . $resident['full_name'] . "</td>";
            echo "<td>" . $resident['email'] . "</td>";
            echo "<td>" . $resident['phone_number'] . "</td>";
            echo "<td>" . htmlspecialchars($resident['address']) . "</td>";
            echo "<td>" . $resident['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>No residents found with this address!</p>";
    }
} else {
    echo "<p style='color:red;'>Officer has no address set.</p>";
}

echo "<hr>";
echo "<p><a href='residents.php'>Go back to Residents page</a></p>";
