<?php
session_start();

date_default_timezone_set('Africa/Dar_es_Salaam');

require_once 'db.php';


// =====================================================
// ADMIN LOGIN CHECK
// =====================================================

if (!isset($_SESSION['admin_id'])) {

    header('Location: index.php?role=admin');

    exit;
}


$username = $_SESSION['admin_username'] ?? 'Administrator';

$message = '';

$messageType = 'success';


// Today's date for calendar minimum
$todayDate = date('Y-m-d');


// =====================================================
// HANDLE FORM SUBMISSIONS
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


 // =================================================
// REMOVE ELECTION
// =================================================

if (isset($_POST['remove_election_id'])) {

    $electionId =
        (int)$_POST['remove_election_id'];


    try {

        /*
         * Check election
         */

        $check =
            $conn->prepare("
                SELECT
                    id,
                    election_name,
                    status
                FROM elections
                WHERE id = ?
            ");

        $check->execute([
            $electionId
        ]);


        $election =
            $check->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$election) {

            $message =
                'Election not found.';

            $messageType =
                'danger';

        } else {


            /*
             * Check whether votes exist
             */

            $voteCheck =
                $conn->prepare("
                    SELECT COUNT(*)
                    FROM votes
                    WHERE election_id = ?
                ");

            $voteCheck->execute([
                $electionId
            ]);


            $voteCount =
                (int)$voteCheck->fetchColumn();


            /*
             * Check history
             */

            $historyCheck =
                $conn->prepare("
                    SELECT COUNT(*)
                    FROM election_history
                    WHERE election_id = ?
                ");

            $historyCheck->execute([
                $electionId
            ]);


            $historyCount =
                (int)$historyCheck->fetchColumn();


            if (
                $voteCount > 0 ||
                $historyCount > 0
            ) {

                $message =
                    'This election cannot be removed because its voting records are preserved as election history.';

                $messageType =
                    'danger';

            } else {


                /*
                 * Safe to remove only if
                 * no votes and no history.
                 */

                $delete =
                    $conn->prepare("
                        DELETE FROM elections
                        WHERE id = ?
                    ");

                $delete->execute([
                    $electionId
                ]);


                if (
                    $delete->rowCount() > 0
                ) {

                    $message =
                        'Election removed successfully.';

                    $messageType =
                        'success';

                } else {

                    $message =
                        'Unable to remove the election.';

                    $messageType =
                        'danger';
                }
            }
        }


    } catch (PDOException $e) {

        $message =
            'Database error while removing the election.';

        $messageType =
            'danger';
    }
}

    // =================================================
    // CANCEL ELECTION
    // =================================================

    elseif (isset($_POST['cancel_election_id'])) {

        $electionId = (int) $_POST['cancel_election_id'];


        try {

            $check = $conn->prepare(
                "SELECT id, status, start_time
                 FROM elections
                 WHERE id = ?"
            );

            $check->execute([$electionId]);

            $election = $check->fetch(PDO::FETCH_ASSOC);


            if (!$election) {

                $message = 'Election not found.';

                $messageType = 'danger';

            } elseif (
                strtolower(trim($election['status'])) === 'cancelled'
            ) {

                $message =
                    'This election is already cancelled.';

                $messageType = 'warning';

            } elseif (
                strtolower(trim($election['status'])) !== 'scheduled'
            ) {

                $message =
                    'Only scheduled elections can be cancelled.';

                $messageType = 'danger';

            } elseif (
                strtotime($election['start_time']) <= time()
            ) {

                $message =
                    'This election has already started and cannot be cancelled here.';

                $messageType = 'danger';

            } else {

                $update = $conn->prepare(
                    "UPDATE elections
                     SET status = 'cancelled'
                     WHERE id = ?"
                );

                $update->execute([$electionId]);


                $message =
                    'Election cancelled successfully.';

                $messageType = 'success';
            }

        } catch (PDOException $e) {

            $message =
                'Database error while cancelling the election.';

            $messageType = 'danger';
        }
    }


    // =================================================
    // SCHEDULE NEW ELECTION
    // =================================================

    elseif (isset($_POST['schedule_election'])) {

        $name = trim(
            $_POST['election_name'] ?? ''
        );


        $startDate = $_POST['start_date'] ?? '';

        $startTime = $_POST['start_time'] ?? '';


        $endDate = $_POST['end_date'] ?? '';

        $endTime = $_POST['end_time'] ?? '';


        // =============================================
        // CHECK ALL FIELDS
        // =============================================

        if (
            $name === '' ||
            $startDate === '' ||
            $startTime === '' ||
            $endDate === '' ||
            $endTime === ''
        ) {

            $message =
                'Please complete all fields.';

            $messageType = 'danger';

        } else {


            // =========================================
            // CHECK START DATE IS NOT IN THE PAST
            // =========================================

            if ($startDate < $todayDate) {

                $message =
                    'The start date cannot be in the past.';

                $messageType = 'danger';

            }

            // =========================================
            // CHECK END DATE IS NOT IN THE PAST
            // =========================================

            elseif ($endDate < $todayDate) {

                $message =
                    'The end date cannot be in the past.';

                $messageType = 'danger';

            }

            // =========================================
            // CHECK END DATE AGAINST START DATE
            // =========================================

            elseif ($endDate < $startDate) {

                $message =
                    'The end date cannot be before the start date.';

                $messageType = 'danger';

            } else {


                // =====================================
                // CREATE START DATETIME
                // =====================================

                $start = DateTime::createFromFormat(
                    '!Y-m-d H:i',
                    "$startDate $startTime"
                );


                // =====================================
                // CREATE END DATETIME
                // =====================================

                $end = DateTime::createFromFormat(
                    '!Y-m-d H:i',
                    "$endDate $endTime"
                );


                // =====================================
                // CURRENT TIME
                // =====================================

                $now = new DateTime();


                // =====================================
                // VALIDATE START
                // =====================================

                $validStart =
                    $start &&
                    $start->format('Y-m-d H:i') ===
                    "$startDate $startTime";


                // =====================================
                // VALIDATE END
                // =====================================

                $validEnd =
                    $end &&
                    $end->format('Y-m-d H:i') ===
                    "$endDate $endTime";


                if (!$validStart || !$validEnd) {

                    $message =
                        'Please select valid dates and times.';

                    $messageType = 'danger';

                }

                // =====================================
                // START MUST BE FUTURE
                // =====================================

                elseif ($start <= $now) {

                    $message =
                        'The start time must be in the future.';

                    $messageType = 'danger';

                }

                // =====================================
                // END MUST BE AFTER START
                // =====================================

                elseif ($end <= $start) {

                    $message =
                        'The end time must be after the start time.';

                    $messageType = 'danger';

                } else {


                    // =================================
                    // SAVE ELECTION
                    // =================================

                    try {

                        $stmt = $conn->prepare(
                            "INSERT INTO elections
                            (
                                election_name,
                                start_time,
                                end_time,
                                status
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                'scheduled'
                            )"
                        );


                        $stmt->execute([

                            $name,

                            $start->format(
                                'Y-m-d H:i:s'
                            ),

                            $end->format(
                                'Y-m-d H:i:s'
                            )

                        ]);


                        $message =
                            'Election scheduled successfully.';

                        $messageType = 'success';


                    } catch (PDOException $e) {

                        $message =
                            'Unable to schedule election. Please check your database table.';

                        $messageType = 'danger';
                    }
                }
            }
        }
    }
}


// =====================================================
// LOAD ALL ELECTIONS
// =====================================================

try {

    $elections = $conn->query(
        "SELECT *
         FROM elections
         ORDER BY id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $elections = [];

    $message =
        'Unable to load elections. Please check your database.';

    $messageType = 'danger';
}


// =====================================================
// TODAY
// =====================================================

$today = date('l, F j, Y');

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Election Settings | ZSH Voting</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >


    <style>

        body {

            margin: 0;

            background: #f3f6fb;

            font-family: Arial, sans-serif;

        }


        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {

            width: 250px;

            min-height: 100vh;

            position: fixed;

            top: 0;

            left: 0;

            background: #073b5c;

            color: white;

            padding: 25px 15px;

        }


        .brand {

            text-align: center;

            padding-bottom: 25px;

            border-bottom: 1px solid #ffffff30;

        }


        .brand img {

            width: 75px;

            height: 75px;

            object-fit: contain;

            background: white;

            border-radius: 50%;

            padding: 5px;

        }


        .brand h5 {

            margin-top: 12px;

            font-weight: bold;

        }


        .sidebar a {

            display: block;

            color: #e7f0f7;

            text-decoration: none;

            padding: 13px 15px;

            border-radius: 8px;

            margin-top: 8px;

            transition: .2s;

        }


        .sidebar a:hover,

        .sidebar a.active {

            background: #12618b;

            color: white;

        }


        .sidebar a i {

            margin-right: 10px;

        }


        .logout-link {

            margin-top: 30px !important;

            background: #b91c1c;

        }


        .logout-link:hover {

            background: #991b1b !important;

        }


        /* =========================
           MAIN CONTENT
        ========================= */

        .main-content {

            margin-left: 250px;

            padding: 30px;

        }


        .topbar {

            background: white;

            padding: 18px 22px;

            border-radius: 12px;

            margin-bottom: 25px;

            box-shadow: 0 2px 8px #00000008;

        }


        .content-card {

            border: none;

            border-radius: 12px;

            overflow: hidden;

        }


        .form-control,

        .form-select {

            min-height: 44px;

            border-radius: 8px;

        }


        .form-control:focus,

        .form-select:focus {

            border-color: #12618b;

            box-shadow: 0 0 0 .2rem #12618b20;

        }


        .time-box {

            background: #f8fafc;

            padding: 16px;

            border-radius: 10px;

            border: 1px solid #e1e8f0;

            height: 100%;

        }


        .time-heading {

            font-weight: bold;

            margin-bottom: 14px;

            color: #073b5c;

        }


        .schedule-btn {

            min-height: 44px;

            padding: 10px 22px;

            border-radius: 8px;

        }


        /* =========================
           ACTION BUTTONS
        ========================= */

        .action-buttons {

            display: flex;

            gap: 6px;

            flex-wrap: wrap;

        }


        .action-buttons form {

            margin: 0;

        }


        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 768px) {

            .sidebar {

                width: 100%;

                min-height: auto;

                position: relative;

            }


            .brand img {

                width: 60px;

                height: 60px;

            }


            .main-content {

                margin-left: 0;

                padding: 18px;

            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">


    <div class="brand">


        <img
            src="logo.png"
            alt="ZSH Logo"
            onerror="this.style.display='none'"
        >


        <h5>

            ZSH VOTING SYSTEM

        </h5>


        <small>

            Administration Panel

        </small>


    </div>


    <nav class="mt-4">


        <a href="admin_dashboard.php">

            <i class="bi bi-speedometer2"></i>

            Dashboard

        </a>


        <a href="manage_students.php">

            <i class="bi bi-people"></i>

            Manage Students

        </a>


        <a href="manage_candidates.php">

            <i class="bi bi-person-badge"></i>

            Manage Candidates

        </a>


        <a
            href="election_settings.php"
            class="active"
        >

            <i class="bi bi-gear"></i>

            Election Settings

        </a>


        <a href="results.php">

            <i class="bi bi-bar-chart"></i>

            Election Results

        </a>


        <a
            href="logout.php"
            class="logout-link"
            onclick="return confirm('Are you sure you want to logout?')"
        >

            <i class="bi bi-box-arrow-right"></i>

            Logout

        </a>


    </nav>

</div>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<div class="main-content">


    <!-- TOP BAR -->

    <div class="topbar d-flex justify-content-between align-items-center flex-wrap gap-2">


        <div>

            <h4 class="mb-1">

                Election Settings

            </h4>


            <small class="text-muted">

                Manage and schedule elections

            </small>

        </div>


        <div class="text-md-end">


            <strong>

                Welcome,

                <?= htmlspecialchars($username) ?>

            </strong>


            <div class="text-muted small">

                <?= htmlspecialchars($today) ?>

            </div>


        </div>


    </div>


    <!-- =================================================
         NOTIFICATION
    ================================================= -->

    <?php if ($message !== ''): ?>


        <div
            class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show"
            role="alert"
        >


            <?= htmlspecialchars($message) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>


        </div>


    <?php endif; ?>


    <!-- =================================================
         SCHEDULE NEW ELECTION
    ================================================= -->

    <div class="card content-card shadow-sm mb-4">


        <div class="card-header bg-white py-3">


            <h5 class="mb-0">

                <i class="bi bi-calendar-plus me-2"></i>

                Schedule a New Election

            </h5>


        </div>


        <div class="card-body p-4">


            <form
                method="POST"
                action="election_settings.php"
            >


                <!-- ELECTION NAME -->

                <div class="mb-4">


                    <label class="form-label fw-bold">

                        Election Name

                    </label>


                    <input
                        type="text"
                        name="election_name"
                        class="form-control"
                        placeholder="Example: College President Election"
                        maxlength="100"
                        required
                    >


                </div>


                <div class="row g-3">


                    <!-- =========================
                         START
                    ========================= -->

                    <div class="col-lg-6">


                        <div class="time-box">


                            <div class="time-heading">

                                <i class="bi bi-calendar-event me-2"></i>

                                Election Start

                            </div>


                            <label class="form-label">

                                Start Date

                            </label>


                            <input
                                type="date"
                                name="start_date"
                                id="start_date"
                                class="form-control mb-3"
                                min="<?= htmlspecialchars($todayDate) ?>"
                                required
                            >


                            <label class="form-label">

                                Start Time

                            </label>


                            <input
                                type="time"
                                name="start_time"
                                class="form-control"
                                required
                            >


                            <small class="text-muted d-block mt-2">

                                Past dates are disabled.
                                Use 24-hour time.
                                For example, 14:00 means 2:00 PM.

                            </small>


                        </div>


                    </div>


                    <!-- =========================
                         END
                    ========================= -->

                    <div class="col-lg-6">


                        <div class="time-box">


                            <div class="time-heading">

                                <i class="bi bi-calendar-check me-2"></i>

                                Election End

                            </div>


                            <label class="form-label">

                                End Date

                            </label>


                            <input
                                type="date"
                                name="end_date"
                                id="end_date"
                                class="form-control mb-3"
                                min="<?= htmlspecialchars($todayDate) ?>"
                                required
                            >


                            <label class="form-label">

                                End Time

                            </label>


                            <input
                                type="time"
                                name="end_time"
                                class="form-control"
                                required
                            >


                            <small class="text-muted d-block mt-2">

                                End date cannot be before start date.
                                The end time must be later than the start time.

                            </small>


                        </div>


                    </div>


                </div>


                <!-- =========================
                     BUTTON
                ========================= -->

                <div class="mt-4">


                    <button
                        type="submit"
                        name="schedule_election"
                        value="1"
                        class="btn btn-primary schedule-btn"
                    >

                        <i class="bi bi-calendar-check me-1"></i>

                        Schedule Election

                    </button>


                </div>


            </form>


        </div>


    </div>


    <!-- =================================================
         ALL ELECTIONS
    ================================================= -->

    <div class="card content-card shadow-sm">


        <div class="card-header bg-white py-3">


            <h5 class="mb-0">

                <i class="bi bi-list-check me-2"></i>

                All Elections

            </h5>


        </div>


        <div class="card-body p-4">


            <div class="table-responsive">


                <table class="table table-bordered table-hover align-middle">


                    <thead class="table-light">


                        <tr>

                            <th>#</th>

                            <th>Election</th>

                            <th>Start Time</th>

                            <th>End Time</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>


                    </thead>


                    <tbody>


                    <?php if (empty($elections)): ?>


                        <tr>


                            <td
                                colspan="6"
                                class="text-center text-muted py-4"
                            >

                                No elections found.


                            </td>


                        </tr>


                    <?php else: ?>


                        <?php foreach ($elections as $election): ?>


                            <?php

                            $startDateTime =
                                new DateTime(
                                    $election['start_time']
                                );


                            $now = new DateTime();


                            $savedStatus =
                                strtolower(
                                    trim(
                                        (string)
                                        ($election['status'] ?? '')
                                    )
                                );


                            if ($savedStatus === 'cancelled') {

                                $status = 'Cancelled';

                                $badge = 'danger';

                            } elseif ($savedStatus === 'scheduled') {

                                $status = 'Scheduled';

                                $badge = 'warning';

                            } elseif ($savedStatus === 'open') {

                                $status = 'Open';

                                $badge = 'success';

                            } elseif ($savedStatus === 'closed') {

                                $status = 'Closed';

                                $badge = 'secondary';

                            } else {

                                $status = 'Unknown';

                                $badge = 'secondary';

                            }

                            ?>


                            <tr>


                                <td>

                                    <?= (int) $election['id'] ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $election['election_name']
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            'd M Y, H:i',
                                            strtotime(
                                                $election['start_time']
                                            )
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            'd M Y, H:i',
                                            strtotime(
                                                $election['end_time']
                                            )
                                        )
                                    ) ?>

                                </td>


                                <td>


                                    <span
                                        class="badge text-bg-<?= $badge ?>"
                                    >

                                        <?= htmlspecialchars($status) ?>

                                    </span>


                                </td>


                                <td>


                                    <div class="action-buttons">


                                        <!-- =========================
                                             CANCEL
                                        ========================= -->

                                        <?php if (
                                            $savedStatus === 'scheduled'
                                            && $now < $startDateTime
                                        ): ?>


                                            <form
                                                method="POST"
                                                action="election_settings.php"
                                                onsubmit="return confirm('Are you sure you want to cancel this election?');"
                                            >


                                                <input
                                                    type="hidden"
                                                    name="cancel_election_id"
                                                    value="<?= (int) $election['id'] ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="btn btn-danger btn-sm"
                                                >

                                                    <i class="bi bi-x-circle"></i>

                                                    Cancel

                                                </button>


                                            </form>


                                        <?php elseif (
                                            $savedStatus === 'cancelled'
                                        ): ?>


                                            <span class="text-danger fw-bold">

                                                <i class="bi bi-x-circle"></i>

                                                Cancelled

                                            </span>


                                        <?php else: ?>


                                            <span class="text-muted">

                                                —

                                            </span>


                                        <?php endif; ?>


                                        <!-- =========================
                                             REMOVE
                                        ========================= -->

                                        <form
                                            method="POST"
                                            action="election_settings.php"
                                            onsubmit="return confirm('WARNING: This will permanently remove this election from the database. Are you sure you want to continue?');"
                                        >


                                            <input
                                                type="hidden"
                                                name="remove_election_id"
                                                value="<?= (int) $election['id'] ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="btn btn-outline-danger btn-sm"
                                            >

                                                <i class="bi bi-trash"></i>

                                                Remove

                                            </button>


                                        </form>


                                    </div>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </div>


    </div>


    <!-- =================================================
         FOOTER
    ================================================= -->

    <footer class="text-center text-muted mt-4 small">

        &copy; <?= date('Y') ?>

        ZSH Voting System

    </footer>


</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const startDate =
        document.getElementById('start_date');

    const endDate =
        document.getElementById('end_date');


    // ============================================
    // START DATE CHANGED
    // ============================================

    startDate.addEventListener('change', function () {

        const selectedStartDate =
            this.value;


        if (selectedStartDate !== '') {

            // End date cannot be before start date
            endDate.min =
                selectedStartDate;


            // If current end date is before start date,
            // clear it
            if (
                endDate.value !== '' &&
                endDate.value < selectedStartDate
            ) {

                endDate.value = '';

            }

        } else {

            // Restore today's minimum date
            endDate.min =
                '<?= htmlspecialchars($todayDate) ?>';

        }

    });


    // ============================================
    // END DATE VALIDATION
    // ============================================

    endDate.addEventListener('change', function () {

        if (
            startDate.value !== '' &&
            this.value !== '' &&
            this.value < startDate.value
        ) {

            alert(
                'End date cannot be before start date.'
            );

            this.value = '';

        }

    });

});

</script>


<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>