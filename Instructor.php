<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

require_login();
require_page_access("instructor");

$role = current_role();
$message = "";
$messageType = "notice";

$canAdd = in_array($role, ["Admin", "Registrar"], true);
$canDelete = $role === "Admin";

function instructor_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// ADD INSTRUCTOR
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add-instructor"])) {
    verify_csrf();

    if (!$canAdd) {
        http_response_code(403);
        exit("You are not allowed to add instructors.");
    }

    $salutation = trim((string)($_POST["salutation"] ?? ""));
    $first = trim((string)($_POST["firstname"] ?? ""));
    $middle = trim((string)($_POST["middlename"] ?? ""));
    $last = trim((string)($_POST["lastname"] ?? ""));

    $allowedSalutations = ["Dr.", "Mr.", "Ms.", "Mrs.", "Prof."];

    if (
        !in_array($salutation, $allowedSalutations, true)
        || $first === ""
        || $last === ""
        || mb_strlen($first) > 100
        || mb_strlen($middle) > 100
        || mb_strlen($last) > 100
    ) {
        $message = "Enter a valid salutation, first name, and last name.";
        $messageType = "alert";
    } else {
        $photo = null;
        $uploadedPath = null;
        $uploadError = "";

        if (
            isset($_FILES["photo"])
            && $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES["photo"];

            if ($file["error"] !== UPLOAD_ERR_OK) {
                $uploadError = "Photo upload failed. Please try again.";
            } elseif ($file["size"] > 2 * 1024 * 1024) {
                $uploadError = "Photo must not exceed 2 MB.";
            } elseif (!is_uploaded_file($file["tmp_name"])) {
                $uploadError = "Invalid photo upload.";
            } else {
                $imageInfo = @getimagesize($file["tmp_name"]);
                $mime = $imageInfo["mime"] ?? "";

                $allowedTypes = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if (!$imageInfo || !isset($allowedTypes[$mime])) {
                    $uploadError = "Photo must be a valid JPG, PNG, or WEBP image.";
                } else {
                    $uploadDir = __DIR__ . "/uploads/instructors";

                    if (
                        !is_dir($uploadDir)
                        && !mkdir($uploadDir, 0755, true)
                        && !is_dir($uploadDir)
                    ) {
                        $uploadError = "Unable to create the photo upload directory.";
                    } else {
                        $fileName = bin2hex(random_bytes(16))
                            . "." . $allowedTypes[$mime];

                        $destination = $uploadDir . "/" . $fileName;

                        if (move_uploaded_file($file["tmp_name"], $destination)) {
                            $photo = "uploads/instructors/" . $fileName;
                            $uploadedPath = $destination;
                        } else {
                            $uploadError = "Could not save the uploaded photo.";
                        }
                    }
                }
            }
        }

        if ($uploadError !== "") {
            $message = $uploadError;
            $messageType = "alert";
        } else {
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO tbl_instructors
                    (SALUTATION, FIRSTNAME, MIDDLENAME, LASTNAME, PHOTO)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "sssss",
                    $salutation,
                    $first,
                    $middle,
                    $last,
                    $photo
                );

                $stmt->execute();
                $stmt->close();

                header("Location: Instructor.php?added=1");
                exit();
            } catch (Throwable $e) {
                if ($uploadedPath !== null && is_file($uploadedPath)) {
                    unlink($uploadedPath);
                }

                error_log("Add instructor error: " . $e->getMessage());
                $message = "Could not save the instructor. Please try again.";
                $messageType = "alert";
            }
        }
    }
}

// DELETE INSTRUCTOR
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete-instructor"])) {
    verify_csrf();

    if (!$canDelete) {
        http_response_code(403);
        exit("Only Admin can delete instructors.");
    }

    $id = filter_input(INPUT_POST, "instructor_id", FILTER_VALIDATE_INT);

    if (!$id) {
        $message = "Invalid instructor ID.";
        $messageType = "alert";
    } else {
        try {
            $stmt = $conn->prepare(
                "SELECT PHOTO FROM tbl_instructors WHERE ID = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$existing) {
                $message = "Instructor not found.";
                $messageType = "alert";
            } else {
                $stmt = $conn->prepare(
                    "DELETE FROM tbl_instructors WHERE ID = ?"
                );
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();

                $photoPath = $existing["PHOTO"] ?? "";

                if (
                    $photoPath !== ""
                    && strpos($photoPath, "uploads/instructors/") === 0
                    && basename($photoPath) === substr(
                        $photoPath,
                        strlen("uploads/instructors/")
                    )
                ) {
                    $fullPath = __DIR__ . "/" . $photoPath;

                    if (is_file($fullPath)) {
                        unlink($fullPath);
                    }
                }

                header("Location: Instructor.php?deleted=1");
                exit();
            }
        } catch (Throwable $e) {
            error_log("Delete instructor error: " . $e->getMessage());
            $message = "Could not delete the instructor.";
            $messageType = "alert";
        }
    }
}

if (isset($_GET["added"])) {
    $message = "Instructor added successfully.";
    $messageType = "notice";
} elseif (isset($_GET["deleted"])) {
    $message = "Instructor deleted successfully.";
    $messageType = "notice";
}

try {
    $instructors = $conn->query(
        "SELECT ID, SALUTATION, FIRSTNAME, MIDDLENAME, LASTNAME, PHOTO
         FROM tbl_instructors
         ORDER BY LASTNAME, FIRSTNAME"
    );
} catch (Throwable $e) {
    error_log("Instructor list error: " . $e->getMessage());
    exit("Unable to load the instructor directory.");
}

$userImage = current_user_image();
$username = $_SESSION["USERNAME"] ?? "";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instructors | Course Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

</head>

<body class="app-page">

    <header class="nav">
        <a class="brand" href="tblcourse.php">
            <span class="brand-mark small">CP</span>
            <span>Course Portal</span>
        </a>

        <div class="nav-right">
            <?php if ($userImage !== ""): ?>
                <img
                    class="profile-image"
                    src="<?= instructor_h($userImage) ?>"
                    alt="Profile image">
            <?php endif; ?>

            <span class="role-pill"><?= instructor_h($role) ?></span>
            <span class="user-name"><?= instructor_h($username) ?></span>
            <a class="logout" href="logout.php">Log out</a>
        </div>
    </header>

    <div class="app-layout">
        <aside class="sidebar">
            <p class="side-label">MAIN MENU</p>

            <a class="side-link" href="tblcourse.php">
                <span>▦</span> Subjects
            </a>

            <a class="side-link active" href="Instructor.php">
                <span>♙</span> Instructor
            </a>

            <?php if ($role === "Admin"): ?>
                <a class="side-link" href="accounts.php">
                    <span>♧</span> Accounts
                </a>
            <?php endif; ?>

            <div class="sidebar-bottom">
                <span class="online-dot"></span>
                Signed in as <?= instructor_h($role) ?>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-heading">
                <div>
                    <p class="eyebrow dark">ACADEMIC MANAGEMENT</p>
                    <h1>Instructors</h1>
                    <p class="muted">Manage instructor names and profile photos.</p>
                </div>
            </div>

            <?php if ($message !== ""): ?>
                <div class="<?= instructor_h($messageType) ?>">
                    <?= instructor_h($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($canAdd): ?>
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Add Instructor</h2>
                            <p class="muted">Fields marked with * are required.</p>
                        </div>
                    </div>

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        class="add-form instructor-form">
                        <?= csrf_field() ?>

                        <div class="field">
                            <label for="salutation">Salutation *</label>
                            <select id="salutation" name="salutation" required>
                                <option value="">Select</option>
                                <option value="Dr.">Dr.</option>
                                <option value="Mr.">Mr.</option>
                                <option value="Ms.">Ms.</option>
                                <option value="Mrs.">Mrs.</option>
                                <option value="Prof.">Prof.</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="firstname">First name *</label>
                            <input
                                id="firstname"
                                name="firstname"
                                maxlength="100"
                                required>
                        </div>

                        <div class="field">
                            <label for="middlename">Middle name</label>
                            <input
                                id="middlename"
                                name="middlename"
                                maxlength="100">
                        </div>

                        <div class="field">
                            <label for="lastname">Last name *</label>
                            <input
                                id="lastname"
                                name="lastname"
                                maxlength="100"
                                required>
                        </div>

                        <div class="field">
                            <label for="photo">Profile photo</label>
                            <input
                                id="photo"
                                type="file"
                                name="photo"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <small>JPG, PNG, or WEBP; maximum 2 MB.</small>
                        </div>

                        <button class="btn" type="submit" name="add-instructor" value="1">
                            + Add Instructor
                        </button>
                    </form>
                </section>
            <?php else: ?>
                <div class="notice">You have view-only access to instructors.</div>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-heading list-heading">
                    <div>
                        <h2>Instructor Directory</h2>
                        <p class="muted">Browse and search instructor records.</p>
                    </div>

                    <label class="search-wrap">
                        <span>⌕</span>
                        <input
                            class="search"
                            id="q"
                            placeholder="Search instructors..."
                            aria-label="Search instructors">
                    </label>
                </div>

                <div class="table-wrap">
                    <table id="tbl">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Photo</th>
                                <th>Salutation</th>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Last Name</th>
                                <?php if ($canDelete): ?>
                                    <th>Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($i = $instructors->fetch_assoc()): ?>
                                <tr>
                                    <td><?= (int)$i["ID"] ?></td>

                                    <td>
                                        <?php if (!empty($i["PHOTO"])): ?>
                                            <img
                                                class="instructor-photo"
                                                src="<?= instructor_h($i["PHOTO"]) ?>"
                                                alt="Instructor photo">
                                        <?php else: ?>
                                            <span class="photo-placeholder">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= instructor_h($i["SALUTATION"]) ?></td>
                                    <td><?= instructor_h($i["FIRSTNAME"]) ?></td>
                                    <td><?= instructor_h($i["MIDDLENAME"] ?? "") ?></td>
                                    <td><?= instructor_h($i["LASTNAME"]) ?></td>

                                    <?php if ($canDelete): ?>
                                        <td>
                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Delete this instructor?');">
                                                <?= csrf_field() ?>

                                                <input
                                                    type="hidden"
                                                    name="instructor_id"
                                                    value="<?= (int)$i["ID"] ?>">

                                                <button
                                                    class="del"
                                                    type="submit"
                                                    name="delete-instructor"
                                                    value="1">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <?php if ($instructors->num_rows === 0): ?>
                        <div class="empty">No instructor records yet.</div>
                    <?php endif; ?>
                </div>
            </section>

            <footer class="footer">Course Portal · Instructor directory</footer>
        </main>
    </div>

    <script>
        document.getElementById("q").addEventListener("input", function() {
            const value = this.value.toLowerCase().trim();

            document.querySelectorAll("#tbl tbody tr").forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(value) ?
                    "" :
                    "none";
            });
        });
    </script>

</body>

</html>