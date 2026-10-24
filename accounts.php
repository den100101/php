<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

require_login();
require_role(["Admin"]);

$message = "";
$error = "";

$allowedRoles = [
    "Admin",
    "Registrar",
    "Dean",
    "Instructor",
    "Student"
];

// PROCESS ACCOUNT CHANGES
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    // UPDATE USER ROLE
    if (isset($_POST["update_role"])) {
        $accountId = filter_input(INPUT_POST, "account_id", FILTER_VALIDATE_INT);
        $newRole = trim((string)($_POST["usertype"] ?? ""));

        if (!$accountId || !in_array($newRole, $allowedRoles, true)) {
            $error = "Invalid account or user type.";
        } elseif (
            $accountId === (int)$_SESSION["USER_ID"]
            && $newRole !== "Admin"
        ) {
            $error = "You cannot remove your own Admin role.";
        } else {
            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare(
                    "SELECT ID, USERTYPE
                     FROM tbl_account
                     WHERE ID = ?
                     FOR UPDATE"
                );
                $stmt->bind_param("i", $accountId);
                $stmt->execute();
                $account = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$account) {
                    $conn->rollback();
                    $error = "Account not found.";
                } elseif (
                    strtolower(trim($account["USERTYPE"])) === "admin"
                    && $newRole !== "Admin"
                ) {
                    $stmt = $conn->prepare(
                        "SELECT COUNT(*) AS total
                         FROM tbl_account
                         WHERE LOWER(TRIM(USERTYPE)) = 'admin'"
                    );
                    $stmt->execute();
                    $adminCount = (int)$stmt->get_result()
                        ->fetch_assoc()["total"];
                    $stmt->close();

                    if ($adminCount <= 1) {
                        $conn->rollback();
                        $error = "You cannot remove the last Admin account.";
                    } else {
                        $stmt = $conn->prepare(
                            "UPDATE tbl_account
                             SET USERTYPE = ?
                             WHERE ID = ?"
                        );
                        $stmt->bind_param("si", $newRole, $accountId);
                        $stmt->execute();
                        $stmt->close();

                        $conn->commit();
                        $message = "User type updated successfully.";
                    }
                } else {
                    $stmt = $conn->prepare(
                        "UPDATE tbl_account
                         SET USERTYPE = ?
                         WHERE ID = ?"
                    );
                    $stmt->bind_param("si", $newRole, $accountId);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    $message = "User type updated successfully.";
                }
            } catch (Throwable $e) {
                try {
                    $conn->rollback();
                } catch (Throwable $ignored) {
                    // Transaction may already be closed.
                }

                error_log("Account role update error: " . $e->getMessage());
                $error = "Unable to update the user type. Please try again.";
            }
        }
    }

    // RESET PASSWORD
    elseif (isset($_POST["reset_password"])) {
        $accountId = filter_input(INPUT_POST, "account_id", FILTER_VALIDATE_INT);
        $newPassword = (string)($_POST["new_password"] ?? "");
        $confirmPassword = (string)($_POST["confirm_password"] ?? "");

        if (!$accountId) {
            $error = "Invalid account.";
        } elseif (strlen($newPassword) < 8) {
            $error = "Password must contain at least 8 characters.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Passwords do not match.";
        } else {
            try {
                $stmt = $conn->prepare(
                    "SELECT ID FROM tbl_account WHERE ID = ? LIMIT 1"
                );
                $stmt->bind_param("i", $accountId);
                $stmt->execute();
                $account = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$account) {
                    $error = "Account not found.";
                } else {
                    $hashedPassword = password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare(
                        "UPDATE tbl_account
                         SET PASSWORD = ?
                         WHERE ID = ?"
                    );
                    $stmt->bind_param("si", $hashedPassword, $accountId);
                    $stmt->execute();
                    $stmt->close();

                    $message = "Password reset successfully.";
                }
            } catch (Throwable $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error = "Unable to reset the password. Please try again.";
            }
        }
    }
}

// GET ACCOUNTS
$accounts = [];

try {
    $result = $conn->query(
        "SELECT ID, USERNAME, USERTYPE
         FROM tbl_account
         ORDER BY USERNAME ASC"
    );

    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
} catch (Throwable $e) {
    error_log("Account list error: " . $e->getMessage());
    $error = "Unable to load accounts.";
}

function escape_output($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="dashboard-container">

        <header class="nav">
            <div class="nav-left">
                <h2>Account Management</h2>
            </div>

            <div class="nav-right">
                <span>
                    <?= escape_output($_SESSION["USERNAME"] ?? "Admin") ?>
                    (Admin)
                </span>
                <a href="tblcourse.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </header>

        <main class="dashboard-content">
            <h1>Manage Accounts</h1>
            <p>Update user types and reset account passwords.</p>

            <?php if ($message !== ""): ?>
                <div class="success-message">
                    <?= escape_output($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="error-message">
                    <?= escape_output($error) ?>
                </div>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-header">
                    <h2>Registered Accounts</h2>
                    <input
                        type="search"
                        id="accountSearch"
                        placeholder="Search username or role..."
                        autocomplete="off">
                </div>

                <div class="table-container">
                    <table id="accountsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>User Type</th>
                                <th>Update Role</th>
                                <th>Reset Password</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($accounts)): ?>
                                <tr>
                                    <td colspan="5">No accounts found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($accounts as $account): ?>
                                    <?php
                                    $id = (int)$account["ID"];
                                    $username = $account["USERNAME"];
                                    $role = ucfirst(
                                        strtolower(trim($account["USERTYPE"]))
                                    );
                                    ?>

                                    <tr>
                                        <td><?= $id ?></td>
                                        <td><?= escape_output($username) ?></td>
                                        <td><?= escape_output($role) ?></td>

                                        <td>
                                            <form method="POST" class="account-form">
                                                <?= csrf_field() ?>

                                                <input
                                                    type="hidden"
                                                    name="account_id"
                                                    value="<?= $id ?>">

                                                <select name="usertype" required>
                                                    <?php foreach ($allowedRoles as $allowedRole): ?>
                                                        <option
                                                            value="<?= escape_output($allowedRole) ?>"
                                                            <?= $role === $allowedRole ? "selected" : "" ?>>
                                                            <?= escape_output($allowedRole) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <button
                                                    type="submit"
                                                    name="update_role"
                                                    value="1">
                                                    Save Role
                                                </button>
                                            </form>
                                        </td>

                                        <td>
                                            <form
                                                method="POST"
                                                class="account-form password-form">
                                                <?= csrf_field() ?>

                                                <input
                                                    type="hidden"
                                                    name="account_id"
                                                    value="<?= $id ?>">

                                                <input
                                                    type="password"
                                                    name="new_password"
                                                    placeholder="New password"
                                                    minlength="8"
                                                    autocomplete="new-password"
                                                    required>

                                                <input
                                                    type="password"
                                                    name="confirm_password"
                                                    placeholder="Confirm password"
                                                    minlength="8"
                                                    autocomplete="new-password"
                                                    required>

                                                <button
                                                    type="submit"
                                                    name="reset_password"
                                                    value="1"
                                                    onclick="return confirm('Reset the password for this account?');">
                                                    Reset Password
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        const searchInput = document.getElementById("accountSearch");

        searchInput.addEventListener("input", function() {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll("#accountsTable tbody tr");

            rows.forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(searchTerm) ?
                    "" :
                    "none";
            });
        });
    </script>

</body>

</html>