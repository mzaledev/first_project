
<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php?role=admin");
    exit;
}

$message = '';
$error = '';
$rowErrors = [];

/* DOWNLOAD CSV TEMPLATE */
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_template.csv"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, ['student_id', 'name', 'username', 'password']);
    fputcsv($output, ['ST001', 'Ahmed Hassan', 'ahmed001', 'ChangeMe123']);

    fclose($output);
    exit;
}

/* ADD ONE STUDENT */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $student_id = trim($_POST['student_id'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($student_id === '' || $name === '' || $username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } else {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO students (student_id, name, username, password)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $student_id,
                $name,
                $username,
                $hashedPassword
            ]);

            $message = 'Student added successfully.';
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $error = 'Student ID or username already exists.';
            } else {
                $error = 'Unable to add student. Please check the database.';
            }
        }
    }
}

/* IMPORT MULTIPLE STUDENTS FROM CSV */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_students'])) {
    $added = 0;
    $skipped = 0;
    $fileHandle = false;
    $transactionStarted = false;

    if (
        !isset($_FILES['students_file']) ||
        $_FILES['students_file']['error'] !== UPLOAD_ERR_OK
    ) {
        $error = 'Please select a valid CSV file.';
    } elseif ($_FILES['students_file']['size'] > 5 * 1024 * 1024) {
        $error = 'The file is too large. Maximum file size is 5 MB.';
    } elseif (
        strtolower(pathinfo($_FILES['students_file']['name'], PATHINFO_EXTENSION)) !== 'csv'
    ) {
        $error = 'Please upload a CSV file only.';
    } else {
        $fileHandle = fopen($_FILES['students_file']['tmp_name'], 'r');

        if ($fileHandle === false) {
            $error = 'Unable to read the uploaded file.';
        } else {
            try {
                $header = fgetcsv($fileHandle);

                if (!$header || count($header) < 4) {
                    throw new Exception('The CSV file does not contain the required headings.');
                }

                // Remove the UTF-8 BOM and normalize headings.
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

                $header = array_map(
                    function ($value) {
                        return strtolower(trim((string)$value));
                    },
                    $header
                );

                $expected = ['student_id', 'name', 'username', 'password'];

                if (array_slice($header, 0, 4) !== $expected) {
                    throw new Exception(
                        'The first four headings must be: student_id, name, username, password.'
                    );
                }

                $conn->beginTransaction();
                $transactionStarted = true;

                $check = $conn->prepare(
                    "SELECT COUNT(*) FROM students
                     WHERE student_id = ? OR username = ?"
                );

                $insert = $conn->prepare(
                    "INSERT INTO students (student_id, name, username, password)
                     VALUES (?, ?, ?, ?)"
                );

                $line = 1;

                while (($data = fgetcsv($fileHandle)) !== false) {
                    $line++;

                    if (count($data) === 1 && trim((string)$data[0]) === '') {
                        continue;
                    }

                    if (count($data) < 4) {
                        $skipped++;
                        $rowErrors[] = "Row $line: Incomplete information.";
                        continue;
                    }

                    $sid = trim((string)$data[0]);
                    $studentName = trim((string)$data[1]);
                    $user = trim((string)$data[2]);
                    $pass = (string)$data[3];

                    if (
                        $sid === '' ||
                        $studentName === '' ||
                        $user === '' ||
                        trim($pass) === ''
                    ) {
                        $skipped++;
                        $rowErrors[] = "Row $line: One or more fields are empty.";
                        continue;
                    }

                    if (
                        strlen($sid) > 100 ||
                        strlen($studentName) > 100 ||
                        strlen($user) > 100
                    ) {
                        $skipped++;
                        $rowErrors[] = "Row $line: A Student ID, name, or username is too long.";
                        continue;
                    }

                    if (strlen($pass) < 8 || strlen($pass) > 72) {
                        $skipped++;
                        $rowErrors[] = "Row $line: Password must contain 8 to 72 characters.";
                        continue;
                    }

                    $check->execute([$sid, $user]);

                    if ((int)$check->fetchColumn() > 0) {
                        $skipped++;
                        $rowErrors[] = "Row $line: Student ID or username already exists.";
                        continue;
                    }

                    $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

                    $insert->execute([
                        $sid,
                        $studentName,
                        $user,
                        $hashedPassword
                    ]);

                    $added++;
                }

                $conn->commit();
                $transactionStarted = false;

                $message = "$added student(s) added successfully. $skipped row(s) skipped.";

            } catch (Throwable $e) {
                if ($transactionStarted && $conn->inTransaction()) {
                    $conn->rollBack();
                }

                $error = $e instanceof PDOException
                    ? 'Unable to import students. Please check the database structure.'
                    : $e->getMessage();

                $rowErrors = [];
            } finally {
                if (is_resource($fileHandle)) {
                    fclose($fileHandle);
                }
            }
        }
    }
}

/* REMOVE STUDENT WHO HAS NOT VOTED */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_student'])) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if ($id) {
        try {
            $check = $conn->prepare(
                "SELECT COUNT(*) FROM votes WHERE student_id = ?"
            );
            $check->execute([$id]);

            if ((int)$check->fetchColumn() > 0) {
                $error = 'This student has already voted and cannot be removed.';
            } else {
                $delete = $conn->prepare("DELETE FROM students WHERE id = ?");
                $delete->execute([$id]);

                $message = $delete->rowCount() > 0
                    ? 'Student removed successfully.'
                    : 'Student was not found.';
            }
        } catch (PDOException $e) {
            $error = 'Unable to remove this student. Please check the database.';
        }
    } else {
        $error = 'Invalid student ID.';
    }
}

/* DISPLAY STUDENTS */
$stmt = $conn->query(
    "SELECT
        s.id,
        s.student_id,
        s.name,
        s.username,
        s.status,
        s.created_at,
        (
            SELECT COUNT(*)
            FROM votes v
            WHERE v.student_id = s.id
        ) AS voted
     FROM students s
     ORDER BY s.id DESC"
);

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$adminUsername = $_SESSION['admin_username'] ?? 'Administrator';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Manage Students | ZSH Voting System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            margin: 0;
            background: #f3f6fb;
            font-family: Arial, sans-serif;
        }

        /* ORIGINAL SIDEBAR - KEEPING THE SAME DESIGN */
        .sidebar {
            width: 245px;
            min-height: 100vh;
            background: #102b52;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            padding: 25px 15px;
        }

        .brand {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 1px solid #385273;
            margin-bottom: 20px;
        }

        .brand img {
            width: 75px;
            height: 75px;
            object-fit: contain;
            background: white;
            border-radius: 50%;
            padding: 5px;
        }

        .sidebar a {
            display: block;
            color: #e8eef8;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 7px;
            margin-bottom: 7px;
            transition: 0.2s;
        }

        .sidebar a:hover {
            background: #24518a;
            color: white;
        }

        /* ORIGINAL RED LOGOUT BUTTON */
        .sidebar a.logout {
            background: #dc3545;
            color: white;
            margin-top: 20px;
        }

        .sidebar a.logout:hover {
            background: #bb2d3b;
            color: white;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 245px;
            padding: 30px;
        }

        /* ORIGINAL BLUE TOPBAR */
        .topbar {
            background: #0d6efd;
            color: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .08);
        }

        .topbar h3 {
            color: white;
            margin-bottom: 8px;
        }

        .topbar p,
        .topbar small {
            color: white;
        }

        .panel {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .05);
        }

        .panel h5 {
            color: #102b52;
        }

        /* TABLE */
        .table th {
            background: #102b52;
            color: white;
            white-space: nowrap;
        }

        /* REMOVE BUTTON */
        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
        }

        .btn-danger:hover {
            background: #bb2d3b;
            border-color: #bb2d3b;
        }

        /* CSV IMPORT SECTION */
        .csv-info {
            background: #f3f6fb;
            border: 1px dashed #b9c9dd;
            padding: 15px;
            border-radius: 8px;
            color: #344b60;
            font-size: 14px;
        }

        .form-control {
            border-radius: 7px;
        }

        /* MOBILE */
        @media (max-width: 800px) {
            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<!-- ORIGINAL SIDEBAR -->
<aside class="sidebar">

    <div class="brand">
        <img
            src="logo.png"
            alt="ZSH Logo"
            onerror="this.style.display='none'"
        >

        <h5 class="mt-3">
            ZSH Voting System
        </h5>

        <small>
            Administrator Panel
        </small>
    </div>

    <a href="admin_dashboard.php">
        🏠 Dashboard
    </a>

    <a href="manage_students.php" class="active">
        👨‍🎓 Manage Students
    </a>

    <a href="manage_candidates.php">
        🗳️ Manage Candidates
    </a>

    <a href="election_settings.php">
        ⚙️ Election Settings
    </a>

    <a href="results.php">
        📊 Election Results
    </a>

    <a
        href="logout.php"
        class="logout"
        onclick="return confirm('Are you sure you want to log out?');"
    >
        🚪 Logout
    </a>

</aside>

<!-- MAIN CONTENT -->
<main class="main-content">

    <!-- ORIGINAL BLUE HEADER -->
    <div class="topbar">
        <h3>Manage Students</h3>

        <p class="mb-0">
            Welcome,
            <?= htmlspecialchars($adminUsername, ENT_QUOTES, 'UTF-8') ?>!
        </p>

        <small>
            View registered students and manage their records.
        </small>
    </div>

    <!-- SUCCESS MESSAGE -->
    <?php if ($message !== ''): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- ERROR MESSAGE -->
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- CSV ROW ERRORS -->
    <?php if (!empty($rowErrors)): ?>
        <div class="alert alert-warning">
            <strong>Import details:</strong>

            <ul class="mb-0 mt-2">
                <?php foreach (array_slice($rowErrors, 0, 20) as $rowError): ?>
                    <li>
                        <?= htmlspecialchars($rowError, ENT_QUOTES, 'UTF-8') ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (count($rowErrors) > 20): ?>
                <small>
                    <?= count($rowErrors) - 20 ?> additional error(s) not shown.
                </small>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- IMPORT MULTIPLE STUDENTS -->
    <section class="panel">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h5 class="mb-0">Import Multiple Students</h5>

            <a
                href="manage_students.php?download_template=1"
                class="btn btn-outline-primary btn-sm"
            >
                Download CSV Template
            </a>
        </div>

        <div class="csv-info mb-3">
            <strong>Instructions:</strong>
            <ol class="mb-2 mt-2">
                <li>Download the CSV template.</li>
                <li>Open it in Excel and enter the student information.</li>
                <li>Save the file in CSV format.</li>
                <li>Upload the completed file below.</li>
            </ol>

            <strong>Required columns:</strong>
            student_id, name, username, password.

            <br>
            <strong>Note:</strong>
            Each password must contain at least 8 characters.
            Maximum file size is 5 MB.
        </div>

        <form method="POST" action="manage_students.php" enctype="multipart/form-data">
            <div class="row g-3 align-items-end">

                <div class="col-md-8">
                    <label for="students_file" class="form-label">
                        Select CSV File
                    </label>

                    <input
                        type="file"
                        id="students_file"
                        name="students_file"
                        class="form-control"
                        accept=".csv,text/csv"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <button
                        type="submit"
                        name="import_students"
                        value="1"
                        class="btn btn-success w-100"
                    >
                        Import Students
                    </button>
                </div>

            </div>
        </form>

    </section>

    <!-- ORIGINAL ADD STUDENT FORM -->
    <section class="panel">

        <h5 class="mb-3">
            Add New Student
        </h5>

        <form method="POST" action="manage_students.php">

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Student ID</label>

                    <input
                        type="text"
                        name="student_id"
                        class="form-control"
                        placeholder="e.g. ST004"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">Full Name</label>

                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        placeholder="Enter student's full name"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">Username</label>

                    <input
                        type="text"
                        name="username"
                        class="form-control"
                        placeholder="Create username"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">Password</label>

                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="Create password"
                        minlength="8"
                        required
                    >
                </div>

            </div>

            <button
                type="submit"
                name="add_student"
                class="btn btn-primary mt-3"
            >
                Add Student
            </button>

        </form>
    </section>

    <!-- ORIGINAL REGISTERED STUDENTS TABLE -->
    <section class="panel">

        <h5 class="mb-3">
            Registered Students
        </h5>

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Account Status</th>
                        <th>Voting Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($students) > 0): ?>

                    <?php foreach ($students as $index => $student): ?>

                        <tr>
                            <td><?= $index + 1 ?></td>

                            <td>
                                <?= htmlspecialchars(
                                    $student['student_id'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $student['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $student['username'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?php if ($student['status'] === 'active'): ?>
                                    <span class="badge bg-success">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ((int)$student['voted'] > 0): ?>
                                    <span class="badge bg-primary">
                                        Voted
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">
                                        Not Voted
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ((int)$student['voted'] === 0): ?>

                                    <form
                                        method="POST"
                                        action="manage_students.php"
                                        onsubmit="return confirm('Remove this student account?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$student['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="remove_student"
                                            class="btn btn-danger btn-sm"
                                        >
                                            Remove
                                        </button>
                                    </form>

                                <?php else: ?>

                                    <button
                                        class="btn btn-secondary btn-sm"
                                        disabled
                                    >
                                        Cannot Remove
                                    </button>

                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="text-center">
                            No students registered yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>
    </section>

</main>

</body>
</html>