<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

require_once "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $error = "Please enter your username and password.";
    } else {
        try {

            /* =========================
               ADMIN LOGIN
            ========================= */

            $stmt = $conn->prepare(
                "SELECT * FROM `admin`
                 WHERE username = ?
                 LIMIT 1"
            );

            $stmt->execute([$username]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $admin &&
                (
                    password_verify($password, $admin["password"]) ||
                    $password === $admin["password"]
                )
            ) {
                session_regenerate_id(true);

                $_SESSION["admin_id"] = $admin["id"];
                $_SESSION["admin_username"] = $admin["username"];

                header("Location: admin_dashboard.php");
                exit;
            }


            /* =========================
               STUDENT LOGIN
            ========================= */

            $stmt = $conn->prepare(
                "SELECT *
                 FROM `students`
                 WHERE username = ?
                 LIMIT 1"
            );

            $stmt->execute([$username]);

            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $student &&
                (
                    password_verify($password, $student["password"]) ||
                    $password === $student["password"]
                )
            ) {

                if (($student["status"] ?? "active") !== "active") {

                    $error = "Your account is not active.";

                } else {

                    session_regenerate_id(true);

                    $_SESSION["student_id"] = $student["id"];
                    $_SESSION["student_username"] = $student["username"];

                    header("Location: student_dashboard.php");
                    exit;
                }

            } elseif ($error === "") {

                $error = "Invalid username or password.";
            }

        } catch (Throwable $e) {

            error_log(
                "ZSH login error: " . $e->getMessage()
            );

            $error = "A system error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>ZSH Voting System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f0f5f8;
            font-family: Arial, sans-serif;
            margin: 0;
        }

        .navbar {
            background: #de4d4d;
            padding: 10px 0;
        }

        .navbar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .brand-area {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 18px;
        }

        .nav-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            background: white;
            border-radius: 50%;
            padding: 3px;
        }

        .live-button {
            background: #073b5c;
            color: white;
            border: 1px solid white;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: bold;
            text-decoration: none;
            white-space: nowrap;
        }

        .live-button:hover {
            background: #12618b;
            color: white;
        }

        .login-card {
            max-width: 420px;
            width: 100%;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 28px #00000012;
        }

        .login-logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        .btn-login {
            background: #087e8b;
            color: white;
            font-weight: bold;
        }

        .btn-login:hover {
            background: #f62c54;
            color: white;
        }

        .form-label {
            font-weight: 600;
        }

        @media (max-width: 480px) {

            .brand-area {
                font-size: 14px;
            }

            .nav-logo {
                width: 40px;
                height: 40px;
            }

            .live-button {
                font-size: 13px;
                padding: 9px 11px;
            }

            .login-card {
                padding: 24px 20px;
            }
        }

    </style>

</head>

<body>

<nav class="navbar">

    <div class="container navbar-content">

        <a
            class="brand-area"
            href="index.php"
        >

            <img
                src="logo.png"
                alt="ZSH Logo"
                class="nav-logo"
            >

            <span>ZSH | Voting System</span>

        </a>


        <a
            href="live_screen.php"
            class="live-button"
        >

            <span class="me-1">●</span>

            Live Screen

        </a>

    </div>

</nav>


<main
    class="container d-flex justify-content-center align-items-center py-5"
    style="min-height: 85vh;"
>

    <div class="login-card">

        <div class="text-center mb-4">

            <img
                src="logo.png"
                alt="ZSH Logo"
                class="login-logo"
            >

            <h4 class="fw-bold">
                Welcome
            </h4>


        </div>


        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="index.php"
        >

            <div class="mb-3">

                <label class="form-label">
                    Username
                </label>

                <input
                    type="text"
                    name="username"
                    class="form-control"
                    placeholder="Enter username"
                    autocomplete="username"
                    required
                    autofocus
                >

            </div>


            <div class="mb-4">

                <label class="form-label">
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    class="form-control"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn btn-login w-100 py-2"
            >
                LOGIN
            </button>

        </form>


        <p class="text-center text-muted small mt-4 mb-0">

            &copy; <?= date("Y") ?> ZSH Voting System

            <br>

            Designed by @lucho

        </p>

    </div>

</main>

</body>

</html>