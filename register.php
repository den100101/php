    <?php
    require_once "db.php";

    $message = "";
    $ok = false;

    if (isset($_POST["submit-btn"])) {
      $username = trim($_POST["usernamereg"] ?? "");
      $password = $_POST["passwordreg"] ?? "";
      $confirm = $_POST["confirmreg"] ?? "";

      if ($username === "" || $password === "") {
        $message = "All fields are required.";
      } elseif (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
        $message = "Username must be 3-50 characters and use only letters, numbers, underscores, or hyphens.";
      } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
      } elseif ($password !== $confirm) {
        $message = "Passwords do not match.";
      } elseif (
        !isset($_FILES["imageUpload"]) ||
        $_FILES["imageUpload"]["error"] !== UPLOAD_ERR_OK
      ) {
        $message = "Please select a profile image.";
      } elseif ($_FILES["imageUpload"]["size"] > 2 * 1024 * 1024) {
        $message = "The image must not exceed 2 MB.";
      } else {
        $stmt = $conn->prepare(
          "SELECT ID FROM tbl_account WHERE USERNAME = ?"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
          $message = "Username already exists.";
        } else {
          $tmpFile = $_FILES["imageUpload"]["tmp_name"];
          $imageInfo = getimagesize($tmpFile);

          $allowedMimeTypes = [
            "image/jpeg",
            "image/png",
            "image/gif"
          ];

          if (
            $imageInfo === false ||
            !in_array($imageInfo["mime"], $allowedMimeTypes, true)
          ) {
            $message = "Only valid JPG, PNG, or GIF images are allowed.";
          } else {
            // Ensure the destination folder exists.
            $targetDir = __DIR__ . "/images/users/";

            if (
              !is_dir($targetDir) &&
              !mkdir($targetDir, 0755, true)
            ) {
              $message = "Could not create the image folder.";
            } else {
              // Always save a real JPEG with the required .JPG extension.
              $imageData = file_get_contents($tmpFile);
              $sourceImage = $imageData !== false
                ? @imagecreatefromstring($imageData)
                : false;

              if ($sourceImage === false) {
                $message = "Could not process the uploaded image.";
              } else {
                $width = imagesx($sourceImage);
                $height = imagesy($sourceImage);

                $jpegImage = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($jpegImage, 255, 255, 255);
                imagefill($jpegImage, 0, 0, $white);

                imagecopy(
                  $jpegImage,
                  $sourceImage,
                  0,
                  0,
                  0,
                  0,
                  $width,
                  $height
                );

                $fileName = $username . ".JPG";
                $targetFile = $targetDir . $fileName;
                $imagePath = "images/users/" . $fileName;

                if (imagejpeg($jpegImage, $targetFile, 90)) {
                  $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                  );

                  // Public registration creates Student accounts only.
                  $role = "Student";

                  $insert = $conn->prepare(
                    "INSERT INTO tbl_account
                    (USERNAME, PASSWORD, USERTYPE, IMAGE_PATH)
                    VALUES (?, ?, ?, ?)"
                  );

                  $insert->bind_param(
                    "ssss",
                    $username,
                    $hashedPassword,
                    $role,
                    $imagePath
                  );

                  if ($insert->execute()) {
                    $message = "Registration successful! You can now log in.";
                    $ok = true;
                  } else {
                    // Avoid leaving an orphaned image if saving fails.
                    if (is_file($targetFile)) {
                      unlink($targetFile);
                    }

                    $message = "Registration failed. Please try again.";
                  }

                  $insert->close();
                } else {
                  $message = "Could not save the image.";
                }

                imagedestroy($sourceImage);
                imagedestroy($jpegImage);
              }
            }
          }
        }

        $stmt->close();
      }
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Register</title>
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="style.css">
    </head>

    <body>
      <div class="auth">
        <div class="auth-card">
          <div class="auth-side">
            <div class="logo">🎓</div>
            <h1>Join us today</h1>
            <p>Create your account to browse and manage your courses in one place.</p>
          </div>
          <div class="auth-form">
            <h2>Create account</h2>
            <p class="sub">It only takes a few seconds.</p>
            <?php if ($message): ?><div class="alert <?= $ok ? 'ok' : '' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data">
              <div class="field"><label>Username</label>
                <div class="input-wrap"><input type="text" name="usernamereg" required></div>
              </div>
              <div class="field"><label>Password</label>
                <div class="input-wrap"><input type="password" name="passwordreg" id="pw" required>
                  <button type="button" class="toggle" onclick="tg('pw',this)">👁</button>
                </div>
                <div class="strength"><span id="bar"></span></div>
              </div>
              <div class="field"><label>Confirm password</label>
                <div class="input-wrap"><input type="password" name="confirmreg" id="cf" required>
                  <button type="button" class="toggle" onclick="tg('cf',this)">👁</button>
                </div>
              </div>
              <div class="field">
                <label for="imageUpload">Profile image *</label>
                <div class="input-wrap">
                  <input
                    type="file"
                    name="imageUpload"
                    id="imageUpload"
                    accept="image/jpeg,image/png,image/gif"
                    required>
                </div>
                <small>JPG, PNG, or GIF. Maximum size: 2 MB.</small>
              </div>

              <button class="btn" type="submit" name="submit-btn">Register</button>
            </form>
            <div class="switch">Already have an account? <a href="login.php">Log in</a></div>
          </div>
        </div>
      </div>
      <script>
        function tg(id, b) {
          var i = document.getElementById(id);
          i.type = i.type === 'password' ? 'text' : 'password';
          b.textContent = i.type === 'password' ? '👁' : '🙈'
        }
        document.getElementById('pw').addEventListener('input', function() {
          var v = this.value,
            s = 0;
          if (v.length >= 6) s++;
          if (v.length >= 10) s++;
          if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
          if (/\d/.test(v) && /\W/.test(v)) s++;
          var b = document.getElementById('bar');
          b.style.width = (s * 25) + '%';
          b.style.background = ['#ef4444', '#f59e0b', '#eab308', '#22c55e'][Math.max(s - 1, 0)];
        });
      </script>
    </body>

    </html>