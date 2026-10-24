<?php
/*
 * Run this once in a local development environment after importing schema.sql:
 * http://localhost/4A-FLORES/create_test_accounts.php
 * Then DELETE this file. It creates 3 accounts for each user type.
 */
require "db.php";

$accounts = [
    ["admin01", "AdminPass123!", "Admin"],
    ["admin02", "AdminPass123!", "Admin"],
    ["admin03", "AdminPass123!", "Admin"],
    ["registrar01", "RegistrarPass123!", "Registrar"],
    ["registrar02", "RegistrarPass123!", "Registrar"],
    ["registrar03", "RegistrarPass123!", "Registrar"],
    ["dean01", "DeanPass123!", "Dean"],
    ["dean02", "DeanPass123!", "Dean"],
    ["dean03", "DeanPass123!", "Dean"],
    ["instructor01", "InstructorPass123!", "Instructor"],
    ["instructor02", "InstructorPass123!", "Instructor"],
    ["instructor03", "InstructorPass123!", "Instructor"],
    ["student01", "StudentPass123!", "Student"],
    ["student02", "StudentPass123!", "Student"],
    ["student03", "StudentPass123!", "Student"],
];

$check = $conn->query("SHOW COLUMNS FROM tbl_account LIKE 'USERTYPE'");
if (!$check || $check->num_rows === 0) {
    exit("Import schema.sql first. The tbl_account table needs a USERTYPE column.");
}
$created = 0;
$skipped = 0;
$stmt = $conn->prepare("SELECT ID FROM tbl_account WHERE USERNAME = ?");
$insert = $conn->prepare("INSERT INTO tbl_account (USERNAME, PASSWORD, USERTYPE) VALUES (?, ?, ?)");
foreach ($accounts as [$username, $plainPassword, $role]) {
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $skipped++;
        continue;
    }
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $insert->bind_param("sss", $username, $hash, $role);
    if ($insert->execute()) $created++;
}
echo "<h1>Test account setup complete</h1>";
echo "<p>Created: " . (int)$created . " | Already existed: " . (int)$skipped . "</p>";
echo "<p><strong>Important:</strong> delete create_test_accounts.php now.</p>";
echo "<p>Demo password for each role: use the matching password shown in the PHP file.</p>";
?>
