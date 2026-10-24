<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

require_login();
require_page_access("subjects");

$role = current_role();
$name = $_SESSION["USERNAME"] ?? "";
$userImage = current_user_image();

$canManageCourses = in_array(
  $role,
  ["Admin", "Registrar", "Dean"],
  true
);

$message = "";
$messageType = "notice";

// ADD COURSE
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add-btn"])) {
  verify_csrf();

  if (!$canManageCourses) {
    http_response_code(403);
    exit("You are not allowed to add courses.");
  }

  $code = strtoupper(trim((string)($_POST["code"] ?? "")));
  $courseName = trim((string)($_POST["name"] ?? ""));
  $units = filter_input(INPUT_POST, "units", FILTER_VALIDATE_INT);

  if (
    $code === ""
    || $courseName === ""
    || mb_strlen($code) > 20
    || mb_strlen($courseName) > 100
    || $units === false
    || $units === null
    || $units < 1
    || $units > 9
  ) {
    $message = "Enter a valid course code, course name, and units (1–9).";
    $messageType = "alert";
  } else {
    try {
      $stmt = $conn->prepare(
        "INSERT INTO tbl_course (COURSE_CODE, COURSE_NAME, UNITS)
                 VALUES (?, ?, ?)"
      );

      $stmt->bind_param("ssi", $code, $courseName, $units);
      $stmt->execute();
      $stmt->close();

      header("Location: tblcourse.php?added=1");
      exit();
    } catch (Throwable $e) {
      error_log("Add course error: " . $e->getMessage());
      $message = "Could not add the course. Check whether the course code already exists.";
      $messageType = "alert";
    }
  }
}

// DELETE COURSE
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete-course"])) {
  verify_csrf();

  if (!$canManageCourses) {
    http_response_code(403);
    exit("You are not allowed to delete courses.");
  }

  $id = filter_input(INPUT_POST, "course_id", FILTER_VALIDATE_INT);

  if (!$id) {
    $message = "Invalid course ID.";
    $messageType = "alert";
  } else {
    try {
      $stmt = $conn->prepare("DELETE FROM tbl_course WHERE ID = ?");
      $stmt->bind_param("i", $id);
      $stmt->execute();

      if ($stmt->affected_rows > 0) {
        $stmt->close();
        header("Location: tblcourse.php?deleted=1");
        exit();
      }

      $stmt->close();
      $message = "Course not found.";
      $messageType = "alert";
    } catch (Throwable $e) {
      error_log("Delete course error: " . $e->getMessage());
      $message = "Could not delete the course.";
      $messageType = "alert";
    }
  }
}

if (isset($_GET["added"])) {
  $message = "Course added successfully.";
  $messageType = "notice";
} elseif (isset($_GET["deleted"])) {
  $message = "Course deleted successfully.";
  $messageType = "notice";
}

// DASHBOARD DATA
try {
  $courses = $conn->query(
    "SELECT ID, COURSE_CODE, COURSE_NAME, UNITS
         FROM tbl_course
         ORDER BY COURSE_CODE"
  );

  $total = $courses->num_rows;

  $units = $conn->query(
    "SELECT COALESCE(SUM(UNITS), 0) AS total_units FROM tbl_course"
  )->fetch_assoc()["total_units"];

  $users = $conn->query(
    "SELECT COUNT(*) AS total_users FROM tbl_account"
  )->fetch_assoc()["total_users"];
} catch (Throwable $e) {
  error_log("Dashboard query error: " . $e->getMessage());
  exit("Unable to load dashboard data.");
}

function course_h($value): string
{
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | Course Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>

<body>
  <header class="nav">
    <a class="brand" href="tblcourse.php">🎓 Course Portal</a>

    <div class="user">
      <?php if ($userImage !== ""): ?>
        <div class="image-container">
          <img
            class="profile-image"
            src="<?= course_h($userImage) ?>"
            alt="Profile image">
        </div>
      <?php else: ?>
        <div class="avatar">
          <?= course_h(strtoupper(substr($name, 0, 1))) ?>
        </div>
      <?php endif; ?>

      <span><?= course_h($name) ?></span>
      <span class="role-pill"><?= course_h($role) ?></span>
      <a class="logout" href="logout.php">Logout</a>
    </div>
  </header>

  <div class="container">
    <div class="hello">
      <h1>Hello, <?= course_h($name) ?> 👋</h1>
      <p>Here's an overview of your courses.</p>
    </div>

    <div class="panel">
      <div class="panel-head">
        <h3>Academic Management</h3>
      </div>

      <div class="add-form">
        <a class="btn" href="Instructor.php">
          Manage Instructors
        </a>

        <?php if ($role === "Admin"): ?>
          <a class="btn" href="accounts.php">
            Manage Accounts
          </a>
        <?php endif; ?>
      </div>
    </div>


    <div class="stats">
      <div class="stat">
        <div class="ic">📘</div>
        <div>
          <b><?= (int)$total ?></b>
          <small>Courses</small>
        </div>
      </div>

      <div class="stat">
        <div class="ic">⭐</div>
        <div>
          <b><?= (int)$units ?></b>
          <small>Total units</small>
        </div>
      </div>

      <?php if ($role === "Admin"): ?>
        <div class="stat">
          <div class="ic">👥</div>
          <div>
            <b><?= (int)$users ?></b>
            <small>Registered users</small>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($message !== ""): ?>
      <div class="<?= course_h($messageType) ?>">
        <?= course_h($message) ?>
      </div>
    <?php endif; ?>

    <?php if ($canManageCourses): ?>
      <div class="panel">
        <div class="panel-head">
          <h3>Add a Course</h3>
        </div>

        <form method="POST" class="add-form">
          <?= csrf_field() ?>

          <div class="field" style="margin:0">
            <label for="code">Code</label>
            <div class="input-wrap">
              <input
                id="code"
                name="code"
                maxlength="20"
                placeholder="IT107"
                required>
            </div>
          </div>

          <div class="field" style="margin:0">
            <label for="course-name">Course name</label>
            <div class="input-wrap">
              <input
                id="course-name"
                name="name"
                maxlength="100"
                placeholder="Course title"
                required>
            </div>
          </div>

          <div class="field" style="margin:0">
            <label for="units">Units</label>
            <div class="input-wrap">
              <input
                id="units"
                type="number"
                name="units"
                value="3"
                min="1"
                max="9"
                required>
            </div>
          </div>

          <button class="btn" type="submit" name="add-btn" value="1">
            Add Course
          </button>
        </form>
      </div>
    <?php else: ?>
      <div class="notice">
        You have view-only access to courses.
      </div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-head">
        <h3>Course List</h3>
        <input
          class="search"
          id="q"
          placeholder="🔍 Search courses..."
          aria-label="Search courses">
      </div>

      <div class="table-wrap">
        <table id="tbl">
          <thead>
            <tr>
              <th>#</th>
              <th>Code</th>
              <th>Course</th>
              <th>Units</th>
              <?php if ($canManageCourses): ?>
                <th>Action</th>
              <?php endif; ?>
            </tr>
          </thead>

          <tbody>
            <?php $n = 1; ?>
            <?php while ($c = $courses->fetch_assoc()): ?>
              <tr>
                <td><?= $n++ ?></td>

                <td>
                  <span class="badge">
                    <?= course_h($c["COURSE_CODE"]) ?>
                  </span>
                </td>

                <td><?= course_h($c["COURSE_NAME"]) ?></td>

                <td>
                  <span class="units">
                    <?= (int)$c["UNITS"] ?> units
                  </span>
                </td>

                <?php if ($canManageCourses): ?>
                  <td>
                    <form
                      method="POST"
                      onsubmit="return confirm('Delete this course?');">
                      <?= csrf_field() ?>

                      <input
                        type="hidden"
                        name="course_id"
                        value="<?= (int)$c["ID"] ?>">

                      <button
                        class="del"
                        type="submit"
                        name="delete-course"
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

        <?php if ($total === 0): ?>
          <div class="empty">No courses yet.</div>
        <?php endif; ?>
      </div>
    </div>
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