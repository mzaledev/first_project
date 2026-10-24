<?php

session_start();

date_default_timezone_set('Africa/Dar_es_Salaam');

/*
|--------------------------------------------------------------------------
| HIDE PHP ERRORS FROM USERS
|--------------------------------------------------------------------------
| Errors hazitaonekana kwenye browser/localhost.
| Zitaandikwa kwenye PHP error log badala yake.
*/
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once 'db.php';


/* =========================================================
   ADMIN LOGIN CHECK
========================================================= */

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php?role=admin");
    exit;
}


$adminUsername =
    $_SESSION['admin_username'] ?? 'Administrator';

$message = '';
$error = '';

$uploadDir =
    __DIR__ . '/uploads/';


/* =========================================================
   CREATE UPLOAD FOLDER
========================================================= */

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}


/* =========================================================
   DEPARTMENT → COURSE LIST
========================================================= */

$departments = [

    'Medical Science' => [
        'Clinical Medicine',
        'Optometry',
        'Medical Laboratory Sciences'
    ],

    'Information Technology' => [
        'Information Communication Technology',
        'Health Information Sciences'
    ],

    'Emergency Health and Safety' => [
        'Occupational Health and Safety',
        'Public Health'
    ],

    'Nursing and Midwifery' => [
        'Nursing and Midwifery'
    ],

    'Pharmaceutical Science' => [
        'Pharmaceutical Sciences'
    ],

    'Counseling Psychology' => [
        'Counseling Psychology'
    ]

];


/* =========================================================
   GET DEPARTMENT FROM COURSE
========================================================= */

function getDepartmentFromCourse(
    string $course,
    array $departments
): string {

    foreach ($departments as $department => $courses) {

        if (in_array($course, $courses, true)) {
            return $department;
        }
    }

    return '';
}


/* =========================================================
   GET CURRENTLY RUNNING ELECTION
========================================================= */

function getRunningElection(PDO $conn): ?array
{
    try {

        $stmt = $conn->query("
            SELECT
                id,
                election_name,
                start_time,
                end_time,
                status
            FROM elections
            WHERE status NOT IN ('cancelled', 'closed')
              AND start_time <= NOW()
              AND end_time >= NOW()
            ORDER BY start_time ASC
            LIMIT 1
        ");

        $election =
            $stmt->fetch(PDO::FETCH_ASSOC);

        return $election ?: null;

    } catch (Throwable $e) {

        error_log(
            'getRunningElection error: ' .
            $e->getMessage()
        );

        return null;
    }
}


/* =========================================================
   UPLOAD PHOTO
========================================================= */

function uploadPhoto(
    array $file,
    string $uploadDir
): ?string {

    if (
        !isset($file['error']) ||
        $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }


    if ($file['error'] !== UPLOAD_ERR_OK) {

        throw new Exception(
            "There was an error uploading the candidate photo."
        );
    }


    /* Maximum 3MB */

    if ($file['size'] > 3 * 1024 * 1024) {

        throw new Exception(
            "Candidate photo must not exceed 3MB."
        );
    }


    if (
        empty($file['tmp_name']) ||
        !is_uploaded_file($file['tmp_name'])
    ) {

        throw new Exception(
            "Invalid uploaded photo."
        );
    }


    $allowedMimeTypes = [

        'image/jpeg' => 'jpg',

        'image/png' => 'png',

        'image/webp' => 'webp'

    ];


    $finfo =
        finfo_open(FILEINFO_MIME_TYPE);


    if (!$finfo) {

        throw new Exception(
            "Unable to validate the uploaded photo."
        );
    }


    $mimeType =
        finfo_file(
            $finfo,
            $file['tmp_name']
        );


    finfo_close($finfo);


    if (!isset($allowedMimeTypes[$mimeType])) {

        throw new Exception(
            "Only JPG, PNG and WEBP photos are allowed."
        );
    }


    $extension =
        $allowedMimeTypes[$mimeType];


    $newFileName =
        bin2hex(random_bytes(16)) .
        '.' .
        $extension;


    $destination =
        $uploadDir .
        $newFileName;


    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {

        throw new Exception(
            "Failed to save the uploaded photo."
        );
    }


    return $newFileName;
}


/* =========================================================
   DELETE PHOTO
========================================================= */

function deletePhoto(
    string $photo,
    string $uploadDir
): void {

    if (empty($photo)) {
        return;
    }


    $photoPath =
        $uploadDir .
        basename($photo);


    if (is_file($photoPath)) {
        @unlink($photoPath);
    }
}


/* =========================================================
   CURRENT RUNNING ELECTION
========================================================= */

$runningElection =
    getRunningElection($conn);


/* =========================================================
   HANDLE POST REQUESTS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action'] ?? '';


    try {


        /* =====================================================
           ADD CANDIDATE
        ===================================================== */

        if ($action === 'add_candidate') {

            /*
             * Check election again from database.
             */

            $runningElection =
                getRunningElection($conn);


            if ($runningElection) {

                throw new Exception(
                    "You cannot add a candidate because the election is currently running."
                );
            }


            $name =
                trim($_POST['name'] ?? '');


            $department =
                trim($_POST['department'] ?? '');


            $course =
                trim($_POST['course'] ?? '');


            $status =
                $_POST['status'] ?? 'active';


            /* Validate name */

            if ($name === '') {

                throw new Exception(
                    "Candidate name is required."
                );
            }


            /* Validate department */

            if (
                $department === '' ||
                !array_key_exists(
                    $department,
                    $departments
                )
            ) {

                throw new Exception(
                    "Please select a valid department."
                );
            }


            /* Validate course */

            if (
                $course === '' ||
                !in_array(
                    $course,
                    $departments[$department],
                    true
                )
            ) {

                throw new Exception(
                    "Please select a valid course for the selected department."
                );
            }


            /* Validate status */

            if (
                !in_array(
                    $status,
                    ['active', 'inactive'],
                    true
                )
            ) {

                $status = 'active';
            }


            /* Upload photo */

            $photo = null;


            if (isset($_FILES['photo'])) {

                $photo =
                    uploadPhoto(
                        $_FILES['photo'],
                        $uploadDir
                    );
            }


            /* Insert candidate */

            $stmt =
                $conn->prepare("
                    INSERT INTO candidates
                    (
                        name,
                        department,
                        course,
                        photo,
                        status,
                        created_at
                    )
                    VALUES
                    (
                        :name,
                        :department,
                        :course,
                        :photo,
                        :status,
                        NOW()
                    )
                ");


            $stmt->execute([

                ':name' =>
                    $name,

                ':department' =>
                    $department,

                ':course' =>
                    $course,

                ':photo' =>
                    $photo,

                ':status' =>
                    $status

            ]);


            $message =
                "Candidate added successfully.";
        }


        /* =====================================================
           EDIT CANDIDATE
        ===================================================== */

        elseif ($action === 'edit_candidate') {

            $candidateId =
                (int)(
                    $_POST['candidate_id'] ?? 0
                );


            $name =
                trim($_POST['name'] ?? '');


            $department =
                trim($_POST['department'] ?? '');


            $course =
                trim($_POST['course'] ?? '');


            $status =
                $_POST['status'] ?? 'active';


            if ($candidateId <= 0) {

                throw new Exception(
                    "Invalid candidate."
                );
            }


            if ($name === '') {

                throw new Exception(
                    "Candidate name is required."
                );
            }


            if (
                $department === '' ||
                !array_key_exists(
                    $department,
                    $departments
                )
            ) {

                throw new Exception(
                    "Please select a valid department."
                );
            }


            if (
                $course === '' ||
                !in_array(
                    $course,
                    $departments[$department],
                    true
                )
            ) {

                throw new Exception(
                    "Please select a valid course for the selected department."
                );
            }


            if (
                !in_array(
                    $status,
                    ['active', 'inactive'],
                    true
                )
            ) {

                $status = 'active';
            }


            /* Get old candidate */

            $stmt =
                $conn->prepare("
                    SELECT
                        id,
                        name,
                        department,
                        course,
                        photo,
                        status
                    FROM candidates
                    WHERE id = ?
                    LIMIT 1
                ");


            $stmt->execute([
                $candidateId
            ]);


            $candidate =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$candidate) {

                throw new Exception(
                    "Candidate not found."
                );
            }


            /* New photo */

            $newPhoto = null;


            if (
                isset($_FILES['photo']) &&
                $_FILES['photo']['error']
                !== UPLOAD_ERR_NO_FILE
            ) {

                $newPhoto =
                    uploadPhoto(
                        $_FILES['photo'],
                        $uploadDir
                    );
            }


            if ($newPhoto !== null) {

                $stmt =
                    $conn->prepare("
                        UPDATE candidates
                        SET
                            name = :name,
                            department = :department,
                            course = :course,
                            photo = :photo,
                            status = :status
                        WHERE id = :id
                    ");


                $stmt->execute([

                    ':name' =>
                        $name,

                    ':department' =>
                        $department,

                    ':course' =>
                        $course,

                    ':photo' =>
                        $newPhoto,

                    ':status' =>
                        $status,

                    ':id' =>
                        $candidateId

                ]);


                /* Delete old photo */

                if (
                    !empty(
                        $candidate['photo']
                    )
                ) {

                    deletePhoto(
                        $candidate['photo'],
                        $uploadDir
                    );
                }

            } else {

                $stmt =
                    $conn->prepare("
                        UPDATE candidates
                        SET
                            name = :name,
                            department = :department,
                            course = :course,
                            status = :status
                        WHERE id = :id
                    ");


                $stmt->execute([

                    ':name' =>
                        $name,

                    ':department' =>
                        $department,

                    ':course' =>
                        $course,

                    ':status' =>
                        $status,

                    ':id' =>
                        $candidateId

                ]);
            }


            $message =
                "Candidate updated successfully.";
        }


        /* =====================================================
           ACTIVATE CANDIDATE
        ===================================================== */

        elseif (
            $action === 'activate_candidate'
        ) {

            $candidateId =
                (int)(
                    $_POST['candidate_id'] ?? 0
                );


            if ($candidateId <= 0) {

                throw new Exception(
                    "Invalid candidate."
                );
            }


            $stmt =
                $conn->prepare("
                    UPDATE candidates
                    SET status = 'active'
                    WHERE id = ?
                ");


            $stmt->execute([
                $candidateId
            ]);


            $message =
                "Candidate activated successfully.";
        }


        /* =====================================================
           DEACTIVATE CANDIDATE
        ===================================================== */

        elseif (
            $action === 'deactivate_candidate'
        ) {

            $candidateId =
                (int)(
                    $_POST['candidate_id'] ?? 0
                );


            if ($candidateId <= 0) {

                throw new Exception(
                    "Invalid candidate."
                );
            }


            $stmt =
                $conn->prepare("
                    UPDATE candidates
                    SET status = 'inactive'
                    WHERE id = ?
                ");


            $stmt->execute([
                $candidateId
            ]);


            $message =
                "Candidate deactivated successfully.";
        }


        /* =====================================================
           DELETE CANDIDATE
        ===================================================== */

        elseif (
            $action === 'delete_candidate'
        ) {

            $candidateId =
                (int)(
                    $_POST['candidate_id'] ?? 0
                );


            if ($candidateId <= 0) {

                throw new Exception(
                    "Invalid candidate."
                );
            }


            /* Get candidate */

            $stmt =
                $conn->prepare("
                    SELECT
                        id,
                        name,
                        photo
                    FROM candidates
                    WHERE id = ?
                    LIMIT 1
                ");


            $stmt->execute([
                $candidateId
            ]);


            $candidate =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$candidate) {

                throw new Exception(
                    "Candidate not found."
                );
            }


            /* Check votes */

            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)
                    FROM votes
                    WHERE candidate_id = ?
                ");


            $stmt->execute([
                $candidateId
            ]);


            $voteCount =
                (int)$stmt->fetchColumn();


            if ($voteCount > 0) {

                throw new Exception(
                    "This candidate already has votes. Deactivate them instead."
                );
            }


            /* Delete candidate */

            $stmt =
                $conn->prepare("
                    DELETE FROM candidates
                    WHERE id = ?
                ");


            $stmt->execute([
                $candidateId
            ]);


            /* Delete photo */

            if (
                !empty(
                    $candidate['photo']
                )
            ) {

                deletePhoto(
                    $candidate['photo'],
                    $uploadDir
                );
            }


            $message =
                "Candidate removed successfully.";
        }

    } catch (Throwable $e) {

        /*
         * Do not display localhost/PHP errors.
         * Save technical error to log.
         */

        error_log(
            'Manage Candidates error: ' .
            $e->getMessage()
        );


        /*
         * Show only controlled application messages.
         */

        $error =
            $e instanceof Exception
                ? $e->getMessage()
                : "An unexpected error occurred. Please try again.";
    }


    $runningElection =
        getRunningElection($conn);
}


/* =========================================================
   LOAD CANDIDATE FOR EDIT
========================================================= */

$editCandidate = null;


if (isset($_GET['edit'])) {

    $editId =
        (int)$_GET['edit'];


    if ($editId > 0) {

        try {

            $stmt =
                $conn->prepare("
                    SELECT
                        id,
                        name,
                        department,
                        course,
                        photo,
                        status,
                        created_at
                    FROM candidates
                    WHERE id = ?
                    LIMIT 1
                ");


            $stmt->execute([
                $editId
            ]);


            $editCandidate =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            /*
             * Old candidates may not have department.
             */

            if (
                $editCandidate &&
                empty(
                    $editCandidate['department']
                )
            ) {

                $editCandidate['department'] =
                    getDepartmentFromCourse(
                        $editCandidate['course'],
                        $departments
                    );
            }

        } catch (Throwable $e) {

            error_log(
                'Edit candidate load error: ' .
                $e->getMessage()
            );

            $error =
                "Unable to load candidate information.";
        }
    }
}


/* =========================================================
   GET ALL CANDIDATES
========================================================= */

$candidates = [];


try {

    $stmt =
        $conn->query("
            SELECT
                c.id,
                c.name,
                c.department,
                c.course,
                c.photo,
                c.status,
                c.created_at,
                COUNT(v.id) AS total_votes
            FROM candidates c
            LEFT JOIN votes v
                ON v.candidate_id = c.id
            GROUP BY
                c.id,
                c.name,
                c.department,
                c.course,
                c.photo,
                c.status,
                c.created_at
            ORDER BY c.id DESC
        ");


    $candidates =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $e) {

    error_log(
        'Candidates list error: ' .
        $e->getMessage()
    );

    $error =
        "Unable to load candidates.";
}


$runningElection =
    getRunningElection($conn);

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Manage Candidates - ZSH Voting System
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            background: #f4f7fb;
            font-family: Arial, Helvetica, sans-serif;
        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #073b5c;
            color: white;
            z-index: 1000;
            overflow-y: auto;
        }


        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid
                rgba(255,255,255,0.12);
        }


        .sidebar-header h4 {
            margin: 0;
            font-weight: 700;
        }


        .sidebar-header small {
            opacity: 0.75;
        }


        .sidebar-menu {
            padding: 20px 12px;
        }


        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 7px;
            transition: 0.2s;
        }


        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #12618b;
        }


        .sidebar-menu a.logout {
            background: #dc3545;
            margin-top: 20px;
        }


        .sidebar-menu a.logout:hover {
            background: #bb2d3b;
        }


        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
        }


        .topbar {
            background: white;
            padding: 18px 30px;
            border-bottom: 1px solid #e5e7eb;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;
        }


        .topbar h3 {
            margin: 0;
            color: #073b5c;
            font-weight: 700;
        }


        .topbar-date {
            color: #6c757d;
            font-size: 14px;
        }


        .content {
            padding: 30px;
        }


        /* =====================================================
           CARDS
        ===================================================== */

        .card {
            border: none;
            border-radius: 14px;

            box-shadow:
                0 4px 18px
                rgba(0,0,0,0.06);
        }


        .section-title {
            color: #073b5c;
            font-weight: 700;
        }


        /* =====================================================
           LOCK CARD
        ===================================================== */

        .lock-card {
            background: #fff3cd;
            border: 1px solid #ffda6a;
            color: #664d03;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }


        .lock-icon {
            font-size: 40px;
        }


        .locked-form {
            opacity: 0.65;
            pointer-events: none;
        }


        /* =====================================================
           CANDIDATE PHOTO
        ===================================================== */

        .candidate-photo {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
        }


        .no-photo {
            width: 55px;
            height: 55px;
            border-radius: 50%;

            background: #e9ecef;

            display: flex;
            align-items: center;
            justify-content: center;

            color: #6c757d;
            font-size: 24px;
        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status-badge {
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }


        .status-active {
            background: #d1e7dd;
            color: #0f5132;
        }


        .status-inactive {
            background: #f8d7da;
            color: #842029;
        }


        .status-pending {
            background: #fff3cd;
            color: #664d03;
        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table-responsive {
            border-radius: 12px;
        }


        .table th {
            background: #073b5c;
            color: white;
            white-space: nowrap;
        }


        .table td {
            vertical-align: middle;
        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 991px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }


            .sidebar-menu {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }


            .sidebar-menu a {
                margin: 0;
                flex: 1 1 200px;
            }


            .sidebar-menu a.logout {
                margin-top: 0;
            }


            .main-content {
                margin-left: 0;
            }


            .topbar {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
            }


            .content {
                padding: 20px;
            }
        }


        @media (max-width: 576px) {

            .content {
                padding: 15px;
            }


            .topbar h3 {
                font-size: 20px;
            }


            .sidebar-header {
                padding: 18px;
            }


            .sidebar-menu {
                display: block;
            }


            .sidebar-menu a {
                margin-bottom: 7px;
            }


            .card-body {
                padding: 16px;
            }


            .btn {
                margin-bottom: 5px;
            }


            .action-buttons {
                display: block !important;
            }


            .action-buttons form,
            .action-buttons a {
                display: block;
                width: 100%;
            }


            .action-buttons .btn {
                width: 100%;
            }
        }

    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<div class="sidebar">

    <div class="sidebar-header">

        <h4>

            <i class="bi bi-check2-square"></i>

            ZSH Voting

        </h4>

        <small>
            Administration Panel
        </small>

    </div>


    <div class="sidebar-menu">

        <a href="admin_dashboard.php">

            <i class="bi bi-speedometer2"></i>

            <span>
                Dashboard
            </span>

        </a>


        <a href="manage_students.php">

            <i class="bi bi-people"></i>

            <span>
                Manage Students
            </span>

        </a>


        <a
            href="manage_candidates.php"
            class="active"
        >

            <i class="bi bi-person-badge"></i>

            <span>
                Manage Candidates
            </span>

        </a>


        <a href="election_settings.php">

            <i class="bi bi-gear"></i>

            <span>
                Election Settings
            </span>

        </a>


        <a href="results.php">

            <i class="bi bi-bar-chart"></i>

            <span>
                Election Results
            </span>

        </a>


        <a
            href="logout.php"
            class="logout"
            onclick="
                return confirm(
                    'Are you sure you want to logout?'
                );
            "
        >

            <i class="bi bi-box-arrow-right"></i>

            <span>
                Logout
            </span>

        </a>

    </div>

</div>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content">


    <!-- TOPBAR -->

    <div class="topbar">

        <div>

            <h3>

                <i class="bi bi-person-badge"></i>

                Manage Candidates

            </h3>

        </div>


        <div class="topbar-date">

            <i class="bi bi-calendar3"></i>

            <?= htmlspecialchars(
                date('l, F j, Y')
            ) ?>

            &nbsp; | &nbsp;

            <strong>

                Welcome,
                <?= htmlspecialchars(
                    $adminUsername
                ) ?>!

            </strong>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="content">


        <!-- =================================================
             SUCCESS MESSAGE
        ================================================== -->

        <?php if ($message): ?>

            <div
                class="alert alert-success alert-dismissible fade show"
            >

                <i class="bi bi-check-circle-fill"></i>

                <?= htmlspecialchars(
                    $message
                ) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <!-- =================================================
             ERROR MESSAGE
        ================================================== -->

        <?php if ($error): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
            >

                <i class="bi bi-exclamation-triangle-fill"></i>

                <?= htmlspecialchars(
                    $error
                ) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <!-- =================================================
             RUNNING ELECTION WARNING
        ================================================== -->

        <?php if ($runningElection): ?>

            <div class="lock-card">

                <div
                    class="d-flex align-items-start gap-3"
                >

                    <div class="lock-icon">

                        <i class="bi bi-lock-fill"></i>

                    </div>


                    <div>

                        <h5 class="fw-bold mb-2">

                            Candidate Adding Is Locked

                        </h5>


                        <p class="mb-1">

                            The election

                            <strong>

                                <?= htmlspecialchars(
                                    $runningElection['election_name']
                                ) ?>

                            </strong>

                            is currently running.

                        </p>


                        <p class="mb-0">

                            You cannot add new candidates
                            while voting is in progress.

                        </p>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <!-- =================================================
             EDIT CANDIDATE
        ================================================== -->

        <?php if ($editCandidate): ?>

            <div class="card mb-4">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between
                        align-items-center mb-4"
                    >

                        <h5 class="section-title mb-0">

                            <i class="bi bi-pencil-square"></i>

                            Edit Candidate

                        </h5>


                        <a
                            href="manage_candidates.php"
                            class="btn btn-outline-secondary btn-sm"
                        >

                            <i class="bi bi-x-circle"></i>

                            Cancel Edit

                        </a>

                    </div>


                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="edit_candidate"
                        >


                        <input
                            type="hidden"
                            name="candidate_id"
                            value="<?= (int)$editCandidate['id'] ?>"
                        >


                        <div class="row g-3">


                            <!-- NAME -->

                            <div class="col-md-6">

                                <label
                                    class="form-label fw-semibold"
                                >

                                    Candidate Name

                                </label>


                                <input
                                    type="text"
                                    name="name"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $editCandidate['name']
                                    ) ?>"
                                    required
                                >

                            </div>


                            <!-- DEPARTMENT -->

                            <div class="col-md-6">

                                <label
                                    class="form-label fw-semibold"
                                >

                                    Department

                                </label>


                                <select
                                    name="department"
                                    id="editDepartment"
                                    class="form-select"
                                    required
                                >

                                    <option value="">

                                        Select Department

                                    </option>


                                    <?php foreach (
                                        $departments
                                        as $department => $courseList
                                    ): ?>

                                        <option
                                            value="<?= htmlspecialchars(
                                                $department
                                            ) ?>"
                                            <?= (
                                                $editCandidate['department']
                                                === $department
                                            )
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >

                                            <?= htmlspecialchars(
                                                $department
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- COURSE -->

                            <div class="col-md-6">

                                <label
                                    class="form-label fw-semibold"
                                >

                                    Course

                                </label>


                                <select
                                    name="course"
                                    id="editCourse"
                                    class="form-select"
                                    required
                                >

                                    <option value="">

                                        Select Department First

                                    </option>

                                </select>

                            </div>


                            <!-- STATUS -->

                            <div class="col-md-6">

                                <label
                                    class="form-label fw-semibold"
                                >

                                    Status

                                </label>


                                <select
                                    name="status"
                                    class="form-select"
                                >

                                    <option
                                        value="active"
                                        <?= (
                                            $editCandidate['status']
                                            === 'active'
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        Active

                                    </option>


                                    <option
                                        value="inactive"
                                        <?= (
                                            $editCandidate['status']
                                            === 'inactive'
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        Inactive

                                    </option>

                                </select>

                            </div>


                            <!-- PHOTO -->

                            <div class="col-md-6">

                                <label
                                    class="form-label fw-semibold"
                                >

                                    Change Photo

                                </label>


                                <input
                                    type="file"
                                    name="photo"
                                    class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp"
                                >


                                <small class="text-muted">

                                    JPG, PNG or WEBP.
                                    Maximum 3MB.

                                </small>

                            </div>


                            <!-- CURRENT PHOTO -->

                            <?php if (
                                !empty(
                                    $editCandidate['photo']
                                )
                            ): ?>

                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Current Photo

                                    </label>


                                    <div>

                                        <img
                                            src="uploads/<?= htmlspecialchars(
                                                $editCandidate['photo']
                                            ) ?>"
                                            class="candidate-photo"
                                            alt="Candidate Photo"
                                        >

                                    </div>

                                </div>

                            <?php endif; ?>


                            <!-- UPDATE -->

                            <div class="col-12">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >

                                    <i class="bi bi-save"></i>

                                    Update Candidate

                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


        <?php else: ?>


            <!-- =================================================
                 ADD CANDIDATE
            ================================================== -->

            <div class="card mb-4">

                <div class="card-body">

                    <h5 class="section-title mb-4">

                        <i class="bi bi-person-plus-fill"></i>

                        Add Candidate

                    </h5>


                    <?php if ($runningElection): ?>


                        <!-- LOCKED FORM -->

                        <div class="locked-form">

                            <div class="row g-3">


                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Candidate Name

                                    </label>


                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="Candidate name"
                                        disabled
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Department

                                    </label>


                                    <select
                                        class="form-select"
                                        disabled
                                    >

                                        <option>

                                            Select Department

                                        </option>

                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Course

                                    </label>


                                    <select
                                        class="form-select"
                                        disabled
                                    >

                                        <option>

                                            Select Department First

                                        </option>

                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Status

                                    </label>


                                    <select
                                        class="form-select"
                                        disabled
                                    >

                                        <option>

                                            Active

                                        </option>

                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Candidate Photo

                                    </label>


                                    <input
                                        type="file"
                                        class="form-control"
                                        disabled
                                    >

                                </div>


                                <div class="col-12">

                                    <button
                                        type="button"
                                        class="btn btn-secondary"
                                        disabled
                                    >

                                        <i
                                            class="bi bi-lock-fill"
                                        ></i>

                                        Add Candidate Locked

                                    </button>

                                </div>

                            </div>

                        </div>


                        <div
                            class="alert alert-warning mt-3 mb-0"
                        >

                            <i
                                class="bi bi-info-circle"
                            ></i>

                            <strong>
                                Add Candidate
                            </strong>

                            is disabled because voting
                            has already started.

                        </div>


                    <?php else: ?>


                        <!-- ACTIVE FORM -->

                        <form
                            method="POST"
                            enctype="multipart/form-data"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="add_candidate"
                            >


                            <div class="row g-3">


                                <!-- NAME -->

                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Candidate Name

                                    </label>


                                    <input
                                        type="text"
                                        name="name"
                                        class="form-control"
                                        placeholder="Enter candidate name"
                                        required
                                    >

                                </div>


                                <!-- DEPARTMENT -->

                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Department

                                    </label>


                                    <select
                                        name="department"
                                        id="department"
                                        class="form-select"
                                        required
                                    >

                                        <option value="">

                                            Select Department

                                        </option>


                                        <?php foreach (
                                            $departments
                                            as $department => $courseList
                                        ): ?>

                                            <option
                                                value="<?= htmlspecialchars(
                                                    $department
                                                ) ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    $department
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <!-- COURSE -->

                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Course

                                    </label>


                                    <select
                                        name="course"
                                        id="course"
                                        class="form-select"
                                        required
                                        disabled
                                    >

                                        <option value="">

                                            Select Department First

                                        </option>

                                    </select>


                                    <small
                                        class="text-muted"
                                    >

                                        Select a department first
                                        to see its courses.

                                    </small>

                                </div>


                                <!-- STATUS -->

                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Status

                                    </label>


                                    <select
                                        name="status"
                                        class="form-select"
                                    >

                                        <option value="active">

                                            Active

                                        </option>


                                        <option value="inactive">

                                            Inactive

                                        </option>

                                    </select>

                                </div>


                                <!-- PHOTO -->

                                <div class="col-md-6">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        Candidate Photo

                                    </label>


                                    <input
                                        type="file"
                                        name="photo"
                                        class="form-control"
                                        accept=".jpg,.jpeg,.png,.webp"
                                    >


                                    <small
                                        class="text-muted"
                                    >

                                        JPG, PNG or WEBP.
                                        Maximum 3MB.

                                    </small>

                                </div>


                                <!-- SUBMIT -->

                                <div class="col-12">

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >

                                        <i
                                            class="bi bi-person-plus"
                                        ></i>

                                        Add Candidate

                                    </button>

                                </div>

                            </div>

                        </form>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>


        <!-- =================================================
             CANDIDATES LIST
        ================================================== -->

        <div class="card">

            <div class="card-body">


                <div
                    class="d-flex justify-content-between
                    align-items-center mb-4"
                >

                    <h5
                        class="section-title mb-0"
                    >

                        <i
                            class="bi bi-people-fill"
                        ></i>

                        Candidates List

                    </h5>


                    <span class="badge bg-primary">

                        <?= count($candidates) ?>

                        Candidates

                    </span>

                </div>


                <?php if (empty($candidates)): ?>


                    <div
                        class="text-center py-5"
                    >

                        <i
                            class="bi bi-person-x"
                            style="
                                font-size:50px;
                                color:#adb5bd;
                            "
                        ></i>


                        <h5
                            class="mt-3 text-muted"
                        >

                            No candidates found.

                        </h5>


                        <p
                            class="text-muted"
                        >

                            Add candidates using
                            the form above.

                        </p>

                    </div>


                <?php else: ?>


                    <div class="table-responsive">

                        <table
                            class="table table-hover align-middle"
                        >

                            <thead>

                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        Candidate
                                    </th>

                                    <th>
                                        Department
                                    </th>

                                    <th>
                                        Course
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Votes
                                    </th>

                                    <th>
                                        Created
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach (
                                    $candidates
                                    as $index => $candidate
                                ): ?>

                                    <tr>


                                        <!-- NUMBER -->

                                        <td>

                                            <?= $index + 1 ?>

                                        </td>


                                        <!-- CANDIDATE -->

                                        <td>

                                            <div
                                                class="
                                                d-flex
                                                align-items-center
                                                gap-3
                                                "
                                            >

                                                <?php if (
                                                    !empty(
                                                        $candidate['photo']
                                                    )
                                                ): ?>

                                                    <img
                                                        src="uploads/<?= htmlspecialchars(
                                                            $candidate['photo']
                                                        ) ?>"
                                                        class="candidate-photo"
                                                        alt="Candidate Photo"
                                                    >

                                                <?php else: ?>

                                                    <div
                                                        class="no-photo"
                                                    >

                                                        <i
                                                            class="bi bi-person"
                                                        ></i>

                                                    </div>

                                                <?php endif; ?>


                                                <div>

                                                    <strong>

                                                        <?= htmlspecialchars(
                                                            $candidate['name']
                                                        ) ?>

                                                    </strong>

                                                </div>

                                            </div>

                                        </td>


                                        <!-- DEPARTMENT -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $candidate['department']
                                                    ?: getDepartmentFromCourse(
                                                        $candidate['course'],
                                                        $departments
                                                    )
                                            ) ?>

                                        </td>


                                        <!-- COURSE -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $candidate['course']
                                            ) ?>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <?php if (
                                                $candidate['status']
                                                === 'active'
                                            ): ?>

                                                <span
                                                    class="
                                                    status-badge
                                                    status-active
                                                    "
                                                >

                                                    Active

                                                </span>

                                            <?php elseif (
                                                $candidate['status']
                                                === 'inactive'
                                            ): ?>

                                                <span
                                                    class="
                                                    status-badge
                                                    status-inactive
                                                    "
                                                >

                                                    Inactive

                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="
                                                    status-badge
                                                    status-pending
                                                    "
                                                >

                                                    <?= htmlspecialchars(
                                                        ucfirst(
                                                            $candidate['status']
                                                        )
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- VOTES -->

                                        <td>

                                            <strong>

                                                <?= (int)$candidate[
                                                    'total_votes'
                                                ] ?>

                                            </strong>

                                        </td>


                                        <!-- CREATED -->

                                        <td>

                                            <?= htmlspecialchars(
                                                date(
                                                    'M d, Y',
                                                    strtotime(
                                                        $candidate[
                                                            'created_at'
                                                        ]
                                                    )
                                                )
                                            ) ?>

                                        </td>


                                        <!-- ACTIONS -->

                                        <td>

                                            <div
                                                class="
                                                d-flex
                                                flex-wrap
                                                gap-1
                                                action-buttons
                                                "
                                            >


                                                <!-- EDIT -->

                                                <a
                                                    href="manage_candidates.php?edit=<?= (int)$candidate['id'] ?>"
                                                    class="
                                                    btn
                                                    btn-sm
                                                    btn-primary
                                                    "
                                                >

                                                    <i
                                                        class="
                                                        bi bi-pencil
                                                        "
                                                    ></i>

                                                    Edit

                                                </a>


                                                <!-- ACTIVATE / DEACTIVATE -->

                                                <?php if (
                                                    $candidate['status']
                                                    === 'active'
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        class="d-inline"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="deactivate_candidate"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="candidate_id"
                                                            value="<?= (int)$candidate['id'] ?>"
                                                        >


                                                        <button
                                                            type="submit"
                                                            class="
                                                            btn
                                                            btn-sm
                                                            btn-warning
                                                            "
                                                            onclick="
                                                            return confirm(
                                                                'Deactivate this candidate?'
                                                            );
                                                            "
                                                        >

                                                            <i
                                                                class="
                                                                bi bi-pause-circle
                                                                "
                                                            ></i>

                                                            Deactivate

                                                        </button>

                                                    </form>


                                                <?php else: ?>


                                                    <form
                                                        method="POST"
                                                        class="d-inline"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="activate_candidate"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="candidate_id"
                                                            value="<?= (int)$candidate['id'] ?>"
                                                        >


                                                        <button
                                                            type="submit"
                                                            class="
                                                            btn
                                                            btn-sm
                                                            btn-success
                                                            "
                                                            onclick="
                                                            return confirm(
                                                                'Activate this candidate?'
                                                            );
                                                            "
                                                        >

                                                            <i
                                                                class="
                                                                bi bi-check-circle
                                                                "
                                                            ></i>

                                                            Activate

                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                                <!-- REMOVE -->

                                                <?php if (
                                                    (int)$candidate[
                                                        'total_votes'
                                                    ] === 0
                                                ): ?>


                                                    <form
                                                        method="POST"
                                                        class="d-inline"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="delete_candidate"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="candidate_id"
                                                            value="<?= (int)$candidate['id'] ?>"
                                                        >


                                                        <button
                                                            type="submit"
                                                            class="
                                                            btn
                                                            btn-sm
                                                            btn-danger
                                                            "
                                                            onclick="
                                                            return confirm(
                                                                'Are you sure you want to remove this candidate?'
                                                            );
                                                            "
                                                        >

                                                            <i
                                                                class="
                                                                bi bi-trash
                                                                "
                                                            ></i>

                                                            Remove

                                                        </button>

                                                    </form>


                                                <?php else: ?>


                                                    <button
                                                        type="button"
                                                        class="
                                                        btn
                                                        btn-sm
                                                        btn-secondary
                                                        "
                                                        disabled
                                                        title="Candidate has votes"
                                                    >

                                                        <i
                                                            class="
                                                            bi bi-lock
                                                            "
                                                        ></i>

                                                        Remove

                                                    </button>

                                                <?php endif; ?>


                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<!-- =========================================================
     DEPARTMENT → COURSE JAVASCRIPT
========================================================= -->

<script>

    const departmentCourses =
        <?= json_encode(
            $departments,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>;


    /* =====================================================
       ADD CANDIDATE
    ===================================================== */

    const departmentSelect =
        document.getElementById(
            'department'
        );


    const courseSelect =
        document.getElementById(
            'course'
        );


    if (
        departmentSelect &&
        courseSelect
    ) {

        departmentSelect.addEventListener(
            'change',
            function () {

                const department =
                    this.value;


                courseSelect.innerHTML =
                    '';


                if (!department) {

                    courseSelect.disabled =
                        true;


                    const option =
                        document.createElement(
                            'option'
                        );


                    option.value =
                        '';


                    option.textContent =
                        'Select Department First';


                    courseSelect.appendChild(
                        option
                    );


                    return;
                }


                courseSelect.disabled =
                    false;


                const defaultOption =
                    document.createElement(
                        'option'
                    );


                defaultOption.value =
                    '';


                defaultOption.textContent =
                    'Select Course';


                courseSelect.appendChild(
                    defaultOption
                );


                const courses =
                    departmentCourses[
                        department
                    ] || [];


                courses.forEach(
                    function(course) {

                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            course;


                        option.textContent =
                            course;


                        courseSelect.appendChild(
                            option
                        );

                    }
                );

            }
        );

    }


    /* =====================================================
       EDIT CANDIDATE
    ===================================================== */

    const editDepartment =
        document.getElementById(
            'editDepartment'
        );


    const editCourse =
        document.getElementById(
            'editCourse'
        );


    <?php if ($editCandidate): ?>

        const selectedEditCourse =
            <?= json_encode(
                $editCandidate['course'],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ) ?>;


        if (
            editDepartment &&
            editCourse
        ) {

            function loadEditCourses() {

                const department =
                    editDepartment.value;


                editCourse.innerHTML =
                    '';


                if (!department) {

                    editCourse.disabled =
                        true;


                    const option =
                        document.createElement(
                            'option'
                        );


                    option.value =
                        '';


                    option.textContent =
                        'Select Department First';


                    editCourse.appendChild(
                        option
                    );


                    return;
                }


                editCourse.disabled =
                    false;


                const defaultOption =
                    document.createElement(
                        'option'
                    );


                defaultOption.value =
                    '';


                defaultOption.textContent =
                    'Select Course';


                editCourse.appendChild(
                    defaultOption
                );


                const courses =
                    departmentCourses[
                        department
                    ] || [];


                courses.forEach(
                    function(course) {

                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            course;


                        option.textContent =
                            course;


                        if (
                            course ===
                            selectedEditCourse
                        ) {

                            option.selected =
                                true;
                        }


                        editCourse.appendChild(
                            option
                        );

                    }
                );

            }


            editDepartment.addEventListener(
                'change',
                loadEditCourses
            );


            loadEditCourses();

        }

    <?php endif; ?>

</script>

</body>

</html>