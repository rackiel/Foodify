<?php
include '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

$user_id = $_SESSION['user_id'];

echo "<h1>📋 Residents Diagnostic Report</h1>";

// Get team officer's info
$result = $conn->query("SELECT user_id, full_name, address, role FROM user_accounts WHERE user_id = $user_id");
$officer = $result->fetch_assoc();

echo "<h2>👤 Team Officer Details</h2>";
echo "<table border='1' cellpadding='10' style='width:100%;'>";
echo "<tr><td><strong>ID:</strong></td><td>" . $officer['user_id'] . "</td></tr>";
echo "<tr><td><strong>Name:</strong></td><td>" . $officer['full_name'] . "</td></tr>";
echo "<tr><td><strong>Role:</strong></td><td>" . $officer['role'] . "</td></tr>";
echo "<tr><td><strong>Address (Raw):</strong></td><td style='background:#fff3cd;'><code>" . htmlspecialchars($officer['address']) . "</code></td></tr>";
echo "<tr><td><strong>Address Length:</strong></td><td>" . strlen($officer['address']) . " characters</td></tr>";
echo "<tr><td><strong>Address (with quotes):</strong></td><td><code>'" . $officer['address'] . "'</code></td></tr>";
echo "</table>";

echo "<hr>";
echo "<h2>👥 All Residents in Database</h2>";

$result = $conn->query("SELECT user_id, full_name, role, address FROM user_accounts WHERE role = 'resident'");
$residents_all = $result->fetch_all(MYSQLI_ASSOC);

echo "<p>Total residents: <strong>" . count($residents_all) . "</strong></p>";

if (count($residents_all) > 0) {
    echo "<table border='1' cellpadding='10' style='width:100%;'>";
    echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Name</th><th>Address (Raw)</th><th>Length</th><th>Match?</th></tr>";

    foreach ($residents_all as $resident) {
        $match = ($officer['address'] === $resident['address']) ? '<span style="color:green;">✓ EXACT</span>' : '<span style="color:red;">✗ NO</span>';
        $match_trim = (trim($officer['address']) === trim($resident['address'])) ? '<span style="color:green;">✓ TRIM</span>' : '<span style="color:red;">✗ NO</span>';

        echo "<tr>";
        echo "<td>" . $resident['user_id'] . "</td>";
        echo "<td>" . $resident['full_name'] . "</td>";
        echo "<td><code>" . htmlspecialchars($resident['address']) . "</code></td>";
        echo "<td>" . strlen($resident['address']) . "</td>";
        echo "<td>$match / $match_trim</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'><strong>No residents found in database!</strong></p>";
}

echo "<hr>";
echo "<h2>🔍 Direct SQL Query Test</h2>";

if (!empty($officer['address'])) {
    $address = $officer['address'];
    $result = $conn->query("SELECT user_id, full_name, address FROM user_accounts WHERE role = 'resident' AND address = '$address'");
    $matched = $result->fetch_all(MYSQLI_ASSOC);

    echo "<p>Query: <code>SELECT ... WHERE role = 'resident' AND address = '" . htmlspecialchars($address) . "'</code></p>";
    echo "<p>Results: <strong>" . count($matched) . " residents</strong></p>";

    if (count($matched) > 0) {
        echo "<table border='1' cellpadding='10' style='width:100%;'>";
        echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Name</th><th>Address</th></tr>";
        foreach ($matched as $m) {
            echo "<tr><td>" . $m['user_id'] . "</td><td>" . $m['full_name'] . "</td><td>" . htmlspecialchars($m['address']) . "</td></tr>";
        }
        echo "</table>";
    }
}

echo "<hr>";
echo "<h2>📊 Summary</h2>";

$result = $conn->query("SELECT address, COUNT(*) as count, role FROM user_accounts WHERE address IS NOT NULL AND address != '' GROUP BY address, role ORDER BY role, address");

echo "<table border='1' cellpadding='10' style='width:100%;'>";
echo "<tr style='background:#f0f0f0;'><th>Role</th><th>Address</th><th>Count</th><th>Matches Officer?</th></tr>";

while ($row = $result->fetch_assoc()) {
    $match_icon = ($row['address'] === $officer['address']) ? '<span style="color:green;font-weight:bold;">YES ✓</span>' : '';
    echo "<tr>";
    echo "<td>" . $row['role'] . "</td>";
    echo "<td>" . htmlspecialchars($row['address']) . "</td>";
    echo "<td>" . $row['count'] . "</td>";
    echo "<td>$match_icon</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p><a href='residents.php' style='font-size:16px;'>← Back to Residents Page</a></p>";
