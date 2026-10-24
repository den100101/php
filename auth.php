
<?php
require_once __DIR__ . "/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get the current logged-in user's role.
 */
function current_role(): string
{
    return ucfirst(strtolower(trim($_SESSION["USERTYPE"] ?? "")));
}

/**
 * Get the current user's profile image path.
 * The database column is IMAGE_PATH.
 */
function current_user_image(): string
{
    return trim((string)($_SESSION["IMAGE_PATH"] ?? ""));
}

/**
 * Require a logged-in user.
 */
function require_login(): void
{
    if (
        empty($_SESSION["USER_ID"]) ||
        empty($_SESSION["USERNAME"]) ||
        empty($_SESSION["USERTYPE"])
    ) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Role-based page permissions.
 */
function can_access_page(string $page): bool
{
    $permissions = [
        "Admin" => [
            "subjects",
            "term",
            "instructor",
            "school_year",
            "section",
            "year_level",
            "students",
            "accounts"
        ],

        "Registrar" => [
            "subjects",
            "term",
            "instructor",
            "school_year"
        ],

        "Dean" => [
            "subjects",
            "section",
            "instructor",
            "year_level"
        ],

        "Instructor" => [
            "subjects",
            "section"
        ],

        "Student" => [
            "subjects"
        ]
    ];

    $role = current_role();

    return isset($permissions[$role])
        && in_array($page, $permissions[$role], true);
}

/**
 * Block access when the user lacks permission.
 */
function require_page_access(string $page): void
{
    require_login();

    if (!can_access_page($page)) {
        http_response_code(403);
        exit("403 Forbidden: Your user type is not allowed to access this page.");
    }
}

/**
 * Require one of the specified roles.
 */
function require_role(array $roles): void
{
    require_login();

    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        exit("403 Forbidden: You do not have permission to perform this action.");
    }
}

/**
 * Generate a CSRF token.
 */
function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

/**
 * Generate the hidden CSRF form field.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") .
        '">';
}

/**
 * Validate a submitted CSRF token.
 */
function verify_csrf(): void
{
    $submittedToken = $_POST["csrf_token"] ?? "";
    $sessionToken = $_SESSION["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !is_string($sessionToken) ||
        $sessionToken === "" ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        http_response_code(403);
        exit("403 Forbidden: Invalid security token. Refresh the page and try again.");
    }
}
