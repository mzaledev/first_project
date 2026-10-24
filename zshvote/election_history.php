<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

require_once "db.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php?role=admin");
    exit;
}

$username = $_SESSION["admin_username"] ?? "Administrator";

$historyId = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

/*
 * Get all archived elections
 */
$historyList = $conn->query("
    SELECT *
    FROM election_history
    ORDER BY end_time DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$selectedHistory = null;
$results = [];

if ($historyId > 0) {

    $stmt = $conn->prepare("
        SELECT *
        FROM election_history
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$historyId]);

    $selectedHistory = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedHistory) {

        $stmtResults = $conn->prepare("
            SELECT *
            FROM election_history_results
            WHERE history_id = ?
            ORDER BY total_votes DESC, candidate_name ASC
        ");

        $stmtResults->execute([$historyId]);

        $results = $stmtResults->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Election History | ZSH Voting System</title>

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

.main-content {
    margin-left: 250px;
    padding: 30px;
}

.card-box {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 15px #0000000d;
}

.stat-box {
    border-radius: 12px;
    padding: 20px;
    background: #f8fafc;
}

@media (max-width: 768px) {

    .sidebar {
        width: 100%;
        min-height: auto;
        position: relative;
    }

    .main-content {
        margin-left: 0;
        padding: 18px;
    }

}

@media print {

    .sidebar,
    .no-print {
        display: none !important;
    }

    .main-content {
        margin: 0;
        padding: 0;
    }

}

</style>

</head>

<body>

<div class="sidebar">

    <div class="brand">

        <img src="logo.png"
             alt="ZSH Logo"
             onerror="this.style.display='none'">

        <h5>ZSH VOTING SYSTEM</h5>

        <small>Administration Panel</small>

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

        <a href="election_settings.php">
            ⚙️ Election Settings
        </a>

        <a href="results.php">
            <i class="bi bi-bar-chart"></i>
            Election Results
        </a>

        <a href="election_history.php" class="active">
            <i class="bi bi-clock-history"></i>
            Election History
        </a>

        <a href="logout.php"
           class="logout-link"
           onclick="return confirm('Are you sure you want to logout?')">

            <i class="bi bi-box-arrow-right"></i>
            Logout

        </a>

    </nav>

</div>

<div class="main-content">

    <div class="card card-box mb-4">

        <div class="card-body p-4">

            <h3 class="fw-bold">
                <i class="bi bi-clock-history"></i>
                Election History
            </h3>

            <p class="text-muted">
                View results and statistics from previous elections.
            </p>

        </div>

    </div>

    <?php if (empty($historyList)): ?>

        <div class="alert alert-info">
            No election history is available yet.
        </div>

    <?php else: ?>

        <div class="card card-box mb-4 no-print">

            <div class="card-body p-4">

                <h5 class="fw-bold mb-3">
                    Previous Elections
                </h5>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>
                                <th>Election</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Voted</th>
                                <th>Percentage</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($historyList as $history): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $history["election_name"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $history["start_time"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $history["end_time"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int)$history["students_voted"] ?>
                                    /
                                    <?= (int)$history["total_students"] ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float)$history["voting_percentage"],
                                        2
                                    ) ?>%
                                </td>

                                <td>

                                    <a href="election_history.php?id=<?= (int)$history["id"] ?>"
                                       class="btn btn-sm btn-primary">

                                        <i class="bi bi-eye"></i>
                                        View

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php endif; ?>

    <?php if ($selectedHistory): ?>

        <div class="card card-box">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <h3 class="fw-bold">
                            <?= htmlspecialchars(
                                $selectedHistory["election_name"]
                            ) ?>
                        </h3>

                        <p class="text-muted">
                            <?= htmlspecialchars(
                                $selectedHistory["start_time"]
                            ) ?>
                            -
                            <?= htmlspecialchars(
                                $selectedHistory["end_time"]
                            ) ?>
                        </p>

                    </div>

                    <button onclick="window.print()"
                            class="btn btn-dark no-print">

                        <i class="bi bi-printer"></i>
                        Print

                    </button>

                </div>

                <hr>

                <div class="row g-3 mb-4">

                    <div class="col-md-4">

                        <div class="stat-box">

                            <small class="text-muted">
                                Total Students
                            </small>

                            <h3 class="fw-bold">
                                <?= (int)$selectedHistory["total_students"] ?>
                            </h3>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="stat-box">

                            <small class="text-muted">
                                Students Voted
                            </small>

                            <h3 class="fw-bold text-success">
                                <?= (int)$selectedHistory["students_voted"] ?>
                            </h3>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="stat-box">

                            <small class="text-muted">
                                Voting Percentage
                            </small>

                            <h3 class="fw-bold text-primary">
                                <?= number_format(
                                    (float)$selectedHistory["voting_percentage"],
                                    2
                                ) ?>%
                            </h3>

                        </div>

                    </div>

                </div>

                <h5 class="fw-bold mb-3">
                    Candidate Results
                </h5>

                <div class="table-responsive">

                    <table class="table table-bordered">

                        <thead class="table-light">

                            <tr>
                                <th>#</th>
                                <th>Candidate</th>
                                <th>Department</th>
                                <th>Course</th>
                                <th>Total Votes</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if (empty($results)): ?>

                            <tr>

                                <td colspan="5"
                                    class="text-center text-muted">

                                    No candidate results available.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php $position = 1; ?>

                            <?php foreach ($results as $result): ?>

                                <tr>

                                    <td>
                                        <?= $position++ ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $result["candidate_name"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $result["department"] ?? ""
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $result["course"] ?? ""
                                        ) ?>
                                    </td>

                                    <td class="fw-bold">

                                        <?= (int)$result["total_votes"] ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

</body>
</html>