<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

require_once __DIR__ . '/db.php';

$conn->exec("SET time_zone = '+03:00'");


/* =========================
   CHECK LOGIN
========================= */

if (!isset($_SESSION['student_id'])) {

    header("Location: index.php?role=student");
    exit;
}


$studentId =
    (int) $_SESSION['student_id'];

$message = '';
$error = '';


/* =========================
   GET STUDENT
========================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        student_id,
        name,
        username,
        password,
        status
     FROM students
     WHERE id = ?
     LIMIT 1"
);

$stmt->execute([
    $studentId
]);

$student =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (
    !$student ||
    ($student['status'] ?? 'active') !== 'active'
) {

    session_unset();
    session_destroy();

    header("Location: index.php?role=student");
    exit;
}


/* =========================
   CHANGE PASSWORD
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $currentPassword =
        $_POST['current_password'] ?? '';

    $newPassword =
        $_POST['new_password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    if (
        $currentPassword === '' ||
        $newPassword === '' ||
        $confirmPassword === ''
    ) {

        $error =
            'Please fill in all password fields.';

    } elseif (
        strlen($newPassword) < 6
    ) {

        $error =
            'New password must be at least 6 characters.';

    } elseif (
        $newPassword !== $confirmPassword
    ) {

        $error =
            'New password and confirmation password do not match.';

    } elseif (
        $currentPassword === $newPassword
    ) {

        $error =
            'Your new password must be different from your current password.';

    } else {

        /*
         * Support both existing:
         * - hashed passwords
         * - plain-text passwords
         */

        $currentPasswordCorrect =
            false;


        if (
            password_verify(
                $currentPassword,
                $student['password']
            )
        ) {

            $currentPasswordCorrect =
                true;

        } elseif (
            $currentPassword ===
            $student['password']
        ) {

            $currentPasswordCorrect =
                true;
        }


        if (!$currentPasswordCorrect) {

            $error =
                'Current password is incorrect.';

        } else {

            try {

                $hashedPassword =
                    password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );


                $update =
                    $conn->prepare(
                        "UPDATE students
                         SET password = ?
                         WHERE id = ?
                         LIMIT 1"
                    );


                $update->execute([
                    $hashedPassword,
                    $studentId
                ]);


                $message =
                    'Your password has been changed successfully.';


                /*
                 * Reload student information
                 */

                $stmt->execute([
                    $studentId
                ]);

                $student =
                    $stmt->fetch(PDO::FETCH_ASSOC);


            } catch (Throwable $e) {

                error_log(
                    "ZSH student password change error: " .
                    $e->getMessage()
                );

                $error =
                    'Unable to change password. Please try again.';
            }
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

<title>
    My Account | ZSH Voting System
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<style>

body {

    margin: 0;
    background: #f4f7fc;
    color: #1f2937;
    font-family: Arial, sans-serif;

}


.navbar-custom {

    background:
        linear-gradient(
            110deg,
            #102b52,
            #1d4e89
        );

    padding: 14px 0;

    box-shadow:
        0 4px 15px #102b5220;

}


.navbar-brand {

    color: white !important;

}


.account-card {

    max-width: 600px;
    width: 100%;

    background: white;

    border-radius: 18px;

    padding: 30px;

    box-shadow:
        0 8px 28px #00000012;

}


.account-header {

    background:
        linear-gradient(
            120deg,
            #102b52,
            #2563eb
        );

    color: white;

    border-radius: 15px;

    padding: 25px;

    margin-bottom: 25px;

}


.account-icon {

    width: 70px;
    height: 70px;

    margin:
        0 auto 12px;

    background: white;

    color: #102b52;

    border-radius: 50%;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 32px;

}


.info-box {

    background: #f4f7fc;

    border-radius: 12px;

    padding: 18px;

    margin-bottom: 28px;

}


.info-label {

    color: #6b7280;

    font-size: 13px;

    margin-bottom: 3px;

}


.info-value {

    color: #102b52;

    font-weight: 700;

}


.form-label {

    font-weight: 600;

}


.btn-change {

    background:
        linear-gradient(
            100deg,
            #2563eb,
            #1746a2
        );

    color: white;

    border: 0;

    border-radius: 10px;

    font-weight: 700;

}


.btn-change:hover {

    background:
        linear-gradient(
            100deg,
            #1746a2,
            #102b52
        );

    color: white;

}


footer {

    color: #718096;

    padding: 28px 0;

    text-align: center;

}


@media (max-width: 576px) {

    .account-card {

        padding: 20px;

    }


    .navbar-brand span {

        font-size: 14px;

    }


    .navbar-brand img {

        width: 45px !important;
        height: 45px !important;

        margin-right: 7px !important;

    }

}

</style>

</head>


<body>


<!-- =========================
     NAVBAR
========================= -->

<nav class="navbar navbar-dark navbar-custom">

    <div class="container">


        <a
            class="navbar-brand d-flex align-items-center fw-bold"
            href="student_dashboard.php"
        >

            <img
                src="logo.png"
                alt="ZSH Logo"

                style="
                    width:55px;
                    height:55px;
                    object-fit:contain;
                    margin-right:12px;
                "
            >

            <span>
                ZSH Voting System
            </span>

        </a>


        <a
            href="student_dashboard.php"
            class="btn btn-outline-light"
        >

            <i class="bi bi-arrow-left me-1"></i>

            Dashboard

        </a>

    </div>

</nav>


<main class="container py-5">

    <div class="d-flex justify-content-center">

        <div class="account-card">


            <!-- ACCOUNT HEADER -->

            <div class="account-header text-center">

                <div class="account-icon">

                    <i class="bi bi-person-fill"></i>

                </div>


                <h4 class="fw-bold mb-1">

                    My Account

                </h4>


                <p class="mb-0">

                    Manage your account and password

                </p>

            </div>


            <!-- SUCCESS MESSAGE -->

            <?php if ($message !== ''): ?>

                <div class="alert alert-success">

                    <i
                        class="bi bi-check-circle-fill me-2"
                    ></i>

                    <?= htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- ERROR MESSAGE -->

            <?php if ($error !== ''): ?>

                <div class="alert alert-danger">

                    <i
                        class="
                            bi
                            bi-exclamation-triangle-fill
                            me-2
                        "
                    ></i>

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- STUDENT INFORMATION -->

            <div class="info-box">

                <div class="row g-3">


                    <div class="col-md-6">

                        <div class="info-label">

                            Student ID

                        </div>


                        <div class="info-value">

                            <?= htmlspecialchars(
                                $student['student_id'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="info-label">

                            Username

                        </div>


                        <div class="info-value">

                            <?= htmlspecialchars(
                                $student['username'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    </div>


                    <div class="col-12">

                        <div class="info-label">

                            Name

                        </div>


                        <div class="info-value">

                            <?= htmlspecialchars(
                                $student['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>


            <!-- CHANGE PASSWORD -->

            <h5 class="fw-bold mb-3">

                <i
                    class="
                        bi
                        bi-shield-lock-fill
                        me-2
                    "
                ></i>

                Change Password

            </h5>


            <form
                method="POST"
                action="student_account.php"
            >


                <!-- CURRENT PASSWORD -->

                <div class="mb-3">

                    <label class="form-label">

                        Current Password

                    </label>


                    <input
                        type="password"
                        name="current_password"
                        id="current_password"
                        class="form-control"
                        placeholder="Enter your current password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <!-- NEW PASSWORD -->

                <div class="mb-3">

                    <label class="form-label">

                        New Password

                    </label>


                    <input
                        type="password"
                        name="new_password"
                        id="new_password"
                        class="form-control"
                        placeholder="Enter new password"
                        minlength="6"
                        autocomplete="new-password"
                        required
                    >


                    <small class="text-muted">

                        Password must be at least 6 characters.

                    </small>

                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="mb-4">

                    <label class="form-label">

                        Confirm New Password

                    </label>


                    <input
                        type="password"
                        name="confirm_password"
                        id="confirm_password"
                        class="form-control"
                        placeholder="Confirm new password"
                        minlength="6"
                        autocomplete="new-password"
                        required
                    >

                </div>


                <!-- BUTTON -->

                <button
                    type="submit"
                    class="btn btn-change w-100 py-2"
                >

                    <i
                        class="bi bi-key-fill me-2"
                    ></i>

                    Change Password

                </button>


            </form>

        </div>

    </div>

</main>


<footer>

    <div class="container">

        ZSH Voting System
        &copy;
        <?= date('Y') ?>.
        All rights reserved.

    </div>

</footer>


</body>

</html>