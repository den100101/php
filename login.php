<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

$message = "";

if (
  isset($_SESSION["USER_ID"], $_SESSION["USERNAME"], $_SESSION["USERTYPE"])
) {
  header("Location: tblcourse.php");
  exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit-btn"])) {
  $username = trim((string)($_POST["usernamelog"] ?? ""));
  $password = (string)($_POST["passwordlog"] ?? "");

  if ($username === "" || $password === "") {
    $message = "Please enter your username and password.";
  } else {
    try {
      $stmt = $conn->prepare(
        "SELECT ID, USERNAME, PASSWORD, USERTYPE, IMAGE_PATH
                 FROM tbl_account
                 WHERE USERNAME = ?
                 LIMIT 1"
      );

      $stmt->bind_param("s", $username);
      $stmt->execute();

      $result = $stmt->get_result();
      $account = $result->fetch_assoc();

      $stmt->close();

      if (
        $account &&
        password_verify($password, $account["PASSWORD"])
      ) {
        session_regenerate_id(true);

        $_SESSION["USER_ID"] = (int)$account["ID"];
        $_SESSION["USERNAME"] = $account["USERNAME"];
        $_SESSION["USERTYPE"] = ucfirst(
          strtolower(trim($account["USERTYPE"]))
        );
        $_SESSION["IMAGE_PATH"] = $account["IMAGE_PATH"] ?? "";

        header("Location: tblcourse.php");
        exit();
      }

      $message = "Invalid username or password.";
    } catch (Throwable $e) {
      error_log("Login error: " . $e->getMessage());
      $message = "Unable to log in right now. Please try again.";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>

<body>
  <div class="auth">
    <div class="auth-card">
      <div class="auth-side">
        <div class="logo">📚</div>
        <h1>Welcome back</h1>
        <p>Log in to view and manage your course list.</p>
      </div>
      <div class="auth-form">
        <h2>Log in</h2>
        <p class="sub">Enter your details to continue.</p>
        <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <form method="post">
          <div class="field"><label>Username</label>
            <div class="input-wrap"><input type="text" name="usernamelog" required></div>
          </div>
          <div class="field"><label>Password</label>
            <div class="input-wrap"><input type="password" name="passwordlog" id="pw" required>
              <button type="button" class="toggle" onclick="var i=document.getElementById('pw');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁':'🙈'">👁</button>
            </div>
          </div>
          <button class="btn" type="submit" name="submit-btn">Login</button>
        </form>
        <div class="switch">No account yet? <a href="register.php">Register</a></div>
      </div>
    </div>
  </div>
</body>

</html>