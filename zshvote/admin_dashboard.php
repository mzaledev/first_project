<?php
session_start();

date_default_timezone_set('Africa/Dar_es_Salaam');

require_once "db.php";

// Protect the admin dashboard
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php?role=admin");
    exit;
}

$username = $_SESSION["admin_username"] ?? "Administrator";

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

// Total students
$totalStudents = (int)$conn->query("
    SELECT COUNT(*) FROM students
")->fetchColumn();

// Total candidates
$totalCandidates = (int)$conn->query("
    SELECT COUNT(*) FROM candidates
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| Find Current, Upcoming, or Most Recent Completed Election
|--------------------------------------------------------------------------
*/

$electionStmt = $conn->query("
    SELECT
        id,
        election_name,
        start_time,
        end_time,
        status
    FROM elections
    WHERE status != 'cancelled'
    ORDER BY
        CASE
            WHEN start_time <= NOW() AND end_time > NOW() THEN 0
            WHEN start_time > NOW() THEN 1
            ELSE 2
        END,
        CASE
            WHEN start_time > NOW() THEN start_time
            ELSE NULL
        END ASC,
        start_time DESC,
        id DESC
    LIMIT 1
");

$currentElection = $electionStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Count Votes for the Selected Election
|--------------------------------------------------------------------------
*/

$totalVotes = 0;

if ($currentElection) {
    $voteStmt = $conn->prepare("
        SELECT COUNT(*)
        FROM votes
        WHERE election_id = ?
    ");

    $voteStmt->execute([$currentElection["id"]]);
    $totalVotes = (int)$voteStmt->fetchColumn();
}

/*
|--------------------------------------------------------------------------
| Current Date
|--------------------------------------------------------------------------
*/

$today = date("l, F j, Y");

/*
|--------------------------------------------------------------------------
| Election Status
|--------------------------------------------------------------------------
*/

$electionStatus = "No Current Election";

if ($currentElection) {
    $now = time();
    $startTime = strtotime($currentElection["start_time"]);
    $endTime = strtotime($currentElection["end_time"]);

    if ($now < $startTime) {
        $electionStatus = "Scheduled";
    } elseif ($now < $endTime) {
        $electionStatus = "Voting Open";
    } else {
        $electionStatus = "Election Complete";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Admin Dashboard | ZSH Voting System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet">

    <style>
        body {
            margin: 0;
            background: #f3f6fb;
            font-family: Arial, sans-serif;
        }

        /* SIDEBAR */
        .sidebar {
            width: 250px;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: #073b5c;
            color: white;
            padding: 25px 15px;
            overflow-y: auto;
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
            transition: 0.2s;
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

        /* MAIN CONTENT */
        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-width: 0;
        }

        /* TOPBAR */
        .topbar {
            background: #0d6efd;
            color: white;
            border-radius: 12px;
            padding: 22px 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px #00000008;
        }

        .topbar h3,
        .topbar .date-text,
        .topbar .text-muted {
            color: white !important;
        }

        .date-text {
            margin-top: 8px;
            margin-bottom: 0;
        }

        /* STAT CARDS */
        .stat-card {
            border: none;
            border-radius: 14px;
            min-height: 185px;
            box-shadow: 0 4px 15px #00000008;
            transition: transform 0.2s;
        }

        .stat-card:hover,
        .action-card:hover {
            transform: translateY(-4px);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 25px;
            margin-bottom: 18px;
        }

        .icon-blue {
            background: #e0edff;
            color: #1769d2;
        }

        .icon-green {
            background: #dcfce7;
            color: #15803d;
        }

        .icon-orange {
            background: #ffedd5;
            color: #c2410c;
        }

        /* ACTION CARDS */
        .action-card {
            border: none;
            border-radius: 14px;
            min-height: 220px;
            box-shadow: 0 4px 15px #00000008;
            transition: transform 0.2s;
        }

        .action-icon {
            font-size: 30px;
            color: #0d6efd;
            margin-bottom: 15px;
        }

        .action-icon.warning {
            color: #d39e00;
        }

        .action-icon.history {
            color: #6f42c1;
        }

        .action-icon.results {
            color: #198754;
        }

        /* ELECTION CARD */
        .election-info {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 15px #00000008;
        }

        /* MOBILE */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                min-height: auto;
                height: auto;
                position: relative;
                padding: 18px 15px;
            }

            .brand img {
                width: 60px;
                height: 60px;
            }

            .sidebar nav {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .sidebar nav a {
                flex: 1 1 145px;
                margin-top: 5px;
            }

            .logout-link {
                margin-top: 5px !important;
            }

            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            .topbar {
                padding: 20px;
            }

            .action-card,
            .stat-card {
                min-height: auto;
            }
        }

        @media (max-width: 400px) {
            .main-content {
                padding: 12px;
            }

            .topbar {
                padding: 16px;
            }

            .election-info .card-body {
                padding: 18px !important;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="brand">
        <img src="logo.png" alt="ZSH Logo"
             onerror="this.style.display='none'">

        <h5>ZSH VOTING SYSTEM</h5>
        <small>Administration Panel</small>
    </div>

    <nav class="mt-4">
        <a href="admin_dashboard.php" class="active">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <a href="manage_students.php">
            <i class="bi bi-people"></i> Manage Students
        </a>

        <a href="manage_candidates.php">
            <i class="bi bi-person-badge"></i> Manage Candidates
        </a>

        <a href="election_settings.php">
            ⚙️ Election Settings
        </a>

        <a href="results.php">
            <i class="bi bi-bar-chart"></i> Election Results
        </a>

        <a href="election_history.php">
            <i class="bi bi-clock-history"></i> Election History
        </a>

        <a href="logout.php"
           class="logout-link"
           onclick="return confirm('Are you sure you want to logout?')">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- WELCOME -->
    <div class="topbar">
        <h3 class="fw-bold mb-1">
            Welcome, <?= htmlspecialchars($username) ?>!
        </h3>

        <p class="date-text">
            <i class="bi bi-calendar3"></i>
            <?= htmlspecialchars($today) ?>
        </p>

        <p class="mt-3 mb-0">
            Manage students, candidates, and election activities
            from your dashboard.
        </p>
    </div>

    <!-- CURRENT ELECTION -->
    <h5 class="fw-bold mb-3">Current Election</h5>

    <div class="card election-info mb-4">
        <div class="card-body p-4">

            <?php if ($currentElection): ?>

                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="fw-bold mb-2">
                            <i class="bi bi-ballot"></i>
                            <?= htmlspecialchars($currentElection["election_name"]) ?>
                        </h5>

                        <p class="text-muted mb-1">
                            <i class="bi bi-calendar-event"></i>
                            Start:
                            <?= htmlspecialchars($currentElection["start_time"]) ?>
                        </p>

                        <p class="text-muted mb-0">
                            <i class="bi bi-calendar-x"></i>
                            End:
                            <?= htmlspecialchars($currentElection["end_time"]) ?>
                        </p>
                    </div>

                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <?php if ($electionStatus === "Voting Open"): ?>

                            <span class="badge bg-success fs-6">
                                <i class="bi bi-check-circle"></i>
                                Voting Open
                            </span>

                        <?php elseif ($electionStatus === "Scheduled"): ?>

                            <span class="badge bg-warning text-dark fs-6">
                                <i class="bi bi-clock"></i>
                                Scheduled
                            </span>

                        <?php else: ?>

                            <span class="badge bg-primary fs-6">
                                <i class="bi bi-check-circle-fill"></i>
                                Election Complete
                            </span>

                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($electionStatus === "Election Complete"): ?>
                    <div class="alert alert-primary mt-4 mb-0">
                        <i class="bi bi-check-circle-fill"></i>
                        <strong>Election Complete!</strong>
                        The scheduled election end time has been reached.
                    </div>
                <?php endif; ?>

            <?php else: ?>

                <div class="text-center py-3">
                    <i class="bi bi-calendar-x fs-1 text-muted"></i>

                    <h5 class="mt-3">No Current Election</h5>

                    <p class="text-muted mb-3">
                        There is no active or upcoming election.
                    </p>

                    <a href="election_settings.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i>
                        Schedule Election
                    </a>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <!-- STATISTICS -->
    <h5 class="fw-bold mb-3">Election Overview</h5>

    <div class="row g-4 mb-4">

        <!-- TOTAL STUDENTS -->
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body p-4">
                    <div class="stat-icon icon-blue">
                        <i class="bi bi-people"></i>
                    </div>

                    <h6 class="text-muted">Total Students</h6>
                    <h2 class="fw-bold"><?= $totalStudents ?></h2>

                    <p class="text-muted mb-0">
                        Registered student accounts
                    </p>
                </div>
            </div>
        </div>

        <!-- TOTAL CANDIDATES -->
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body p-4">
                    <div class="stat-icon icon-green">
                        <i class="bi bi-person-badge"></i>
                    </div>

                    <h6 class="text-muted">Total Candidates</h6>
                    <h2 class="fw-bold"><?= $totalCandidates ?></h2>

                    <p class="text-muted mb-0">
                        Registered election candidates
                    </p>
                </div>
            </div>
        </div>

        <!-- ELECTION VOTES -->
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body p-4">
                    <div class="stat-icon icon-orange">
                        <i class="bi bi-check2-square"></i>
                    </div>

                    <h6 class="text-muted">Selected Election Votes</h6>
                    <h2 class="fw-bold"><?= $totalVotes ?></h2>

                    <p class="text-muted mb-0">
                        Votes recorded for the displayed election
                    </p>
                </div>
            </div>
        </div>

    </div>

    <!-- SYSTEM MANAGEMENT -->
    <h5 class="fw-bold mb-3">System Management</h5>

    <div class="row g-4">

        <!-- MANAGE STUDENTS -->
        <div class="col-md-6 col-lg-4">
            <div class="card action-card">
                <div class="card-body p-4">
                    <div class="action-icon">
                        <i class="bi bi-people"></i>
                    </div>

                    <h5 class="fw-bold">Manage Students</h5>

                    <p class="text-muted">
                        Register new students and manage their accounts.
                    </p>

                    <a href="manage_students.php" class="btn btn-primary mt-2">
                        Manage Students
                    </a>
                </div>
            </div>
        </div>

        <!-- MANAGE CANDIDATES -->
        <div class="col-md-6 col-lg-4">
            <div class="card action-card">
                <div class="card-body p-4">
                    <div class="action-icon">
                        <i class="bi bi-person-badge"></i>
                    </div>

                    <h5 class="fw-bold">Manage Candidates</h5>

                    <p class="text-muted">
                        Add candidates, update their details, and manage
                        their election status.
                    </p>

                    <a href="manage_candidates.php" class="btn btn-primary mt-2">
                        Manage Candidates
                    </a>
                </div>
            </div>
        </div>

        <!-- ELECTION RESULTS -->
        <div class="col-md-6 col-lg-4">
            <div class="card action-card">
                <div class="card-body p-4">
                    <div class="action-icon results">
                        <i class="bi bi-bar-chart"></i>
                    </div>

                    <h5 class="fw-bold">Election Results</h5>

                    <p class="text-muted">
                        Review the votes recorded for the selected election.
                    </p>

                    <a href="results.php" class="btn btn-success mt-2">
                        View Results
                    </a>
                </div>
            </div>
        </div>

        <!-- ELECTION HISTORY -->
        <div class="col-md-6 col-lg-4">
            <div class="card action-card">
                <div class="card-body p-4">
                    <div class="action-icon history">
                        <i class="bi bi-clock-history"></i>
                    </div>

                    <h5 class="fw-bold">Election History</h5>

                    <p class="text-muted">
                        View previous elections, voting statistics,
                        and archived results.
                    </p>

                    <a href="election_history.php" class="btn btn-dark mt-2">
                        <i class="bi bi-clock-history"></i>
                        View History
                    </a>
                </div>
            </div>
        </div>

        <!-- ELECTION SETTINGS -->
        <div class="col-md-6 col-lg-4">
            <div class="card action-card">
                <div class="card-body p-4">
                    <div class="action-icon">
                        <i class="bi bi-gear"></i>
                    </div>

                    <h5 class="fw-bold">Election Settings</h5>

                    <p class="text-muted">
                        Schedule and manage election dates and times.
                    </p>

                    <a href="election_settings.php" class="btn btn-primary mt-2">
                        <i class="bi bi-gear"></i>
                        Election Settings
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- FOOTER -->
    <footer class="text-center text-muted mt-5 pb-3">
        <small>
            &copy; <?= date("Y") ?> ZSH Voting System.
            <br>
            designed by @lucho
        </small>
    </footer>

</div>

</body>
</html>