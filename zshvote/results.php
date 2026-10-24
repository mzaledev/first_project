```php
<?php
session_start();
require_once "db.php";

/* =========================================================
   TIMEZONE
========================================================= */

date_default_timezone_set("Africa/Dar_es_Salaam");


/* =========================================================
   CHECK ADMIN LOGIN
========================================================= */

if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php?role=admin");
    exit;
}

$username = $_SESSION["admin_username"] ?? "Administrator";
$today = date("l, F j, Y");


/* =========================================================
   GET ALL ELECTIONS
========================================================= */

$electionStmt = $conn->query(
    "SELECT
        id,
        election_name,
        start_time,
        end_time,
        status
     FROM elections
     ORDER BY id DESC"
);

$elections = $electionStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   SELECT ELECTION
========================================================= */

$selectedElectionId = filter_input(
    INPUT_GET,
    "election_id",
    FILTER_VALIDATE_INT
);

if (!$selectedElectionId && !empty($elections)) {
    $selectedElectionId = (int)$elections[0]["id"];
}


/* =========================================================
   FIND SELECTED ELECTION
========================================================= */

$selectedElection = null;

foreach ($elections as $item) {

    if (
        (int)$item["id"] ===
        (int)$selectedElectionId
    ) {
        $selectedElection = $item;
        break;
    }
}


/* =========================================================
   DEFAULT VALUES
========================================================= */

$totalStudents = (int)$conn->query(
    "SELECT COUNT(*) FROM students"
)->fetchColumn();

$votedStudents = 0;
$notVoted = $totalStudents;
$totalVotes = 0;
$results = [];


/* =========================================================
   PRINT CONTROL
========================================================= */

$canPrint = false;
$printMessage = "";

if ($selectedElection) {

    try {

        $now = new DateTime(
            "now",
            new DateTimeZone("Africa/Dar_es_Salaam")
        );

        $electionEnd = new DateTime(
            $selectedElection["end_time"],
            new DateTimeZone("Africa/Dar_es_Salaam")
        );

        if (
            $selectedElection["status"] !== "cancelled" &&
            $now >= $electionEnd
        ) {

            $canPrint = true;

        } else {

            $canPrint = false;

            if ($now < $electionEnd) {

                $difference = $now->diff($electionEnd);

                $remainingParts = [];

                if ($difference->d > 0) {
                    $remainingParts[] =
                        $difference->d . " day(s)";
                }

                if ($difference->h > 0) {
                    $remainingParts[] =
                        $difference->h . " hour(s)";
                }

                if ($difference->i > 0) {
                    $remainingParts[] =
                        $difference->i . " minute(s)";
                }

                if (empty($remainingParts)) {
                    $remainingParts[] =
                        $difference->s . " second(s)";
                }

                $printMessage =
                    "Printing will be available after the election ends. " .
                    "Time remaining: " .
                    implode(", ", $remainingParts);

            } else {

                $printMessage =
                    "Printing is not available for a cancelled election.";
            }
        }

    } catch (Exception $e) {

        $canPrint = false;

        $printMessage =
            "Election end time could not be verified.";
    }
}


/* =========================================================
   GET RESULTS FOR SELECTED ELECTION
========================================================= */

if ($selectedElection) {

    /* Students who voted in this election */

    $stmt = $conn->prepare(
        "SELECT COUNT(DISTINCT student_id)
         FROM votes
         WHERE election_id = ?"
    );

    $stmt->execute([
        $selectedElectionId
    ]);

    $votedStudents =
        (int)$stmt->fetchColumn();


    /* Students who did not vote */

    $notVoted = max(
        0,
        $totalStudents - $votedStudents
    );


    /* Total votes for this election */

    $stmt = $conn->prepare(
        "SELECT COUNT(*)
         FROM votes
         WHERE election_id = ?"
    );

    $stmt->execute([
        $selectedElectionId
    ]);

    $totalVotes =
        (int)$stmt->fetchColumn();


    /* Candidate results */

    $sql = "
        SELECT
            c.id,
            c.name,
            COUNT(v.id) AS vote_count
        FROM candidates c
        LEFT JOIN votes v
            ON c.id = v.candidate_id
            AND v.election_id = ?
        GROUP BY
            c.id,
            c.name
        ORDER BY
            vote_count DESC,
            c.name ASC
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        $selectedElectionId
    ]);

    $results =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/* =========================================================
   ELECTION DISPLAY INFORMATION
========================================================= */

$electionName = $selectedElection
    ? $selectedElection["election_name"]
    : "No Election Selected";

$electionStatus = $selectedElection
    ? $selectedElection["status"]
    : "";


/* =========================================================
   END TIME FOR JAVASCRIPT
========================================================= */

$electionEndTimestamp = 0;

if ($selectedElection) {

    try {

        $endDateTime = new DateTime(
            $selectedElection["end_time"],
            new DateTimeZone("Africa/Dar_es_Salaam")
        );

        $electionEndTimestamp =
            $endDateTime->getTimestamp() * 1000;

    } catch (Exception $e) {

        $electionEndTimestamp = 0;
    }
}

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
Election Results - ZSH Voting System
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
>

<script
    src="https://cdn.jsdelivr.net/npm/chart.js">
</script>


<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f3f6fb;
    font-family: Arial, sans-serif;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 250px;
    min-height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: #073b5c;
    padding: 25px 15px;
    color: white;
    z-index: 10;
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
    margin-bottom: 10px;
}

.brand h5 {
    margin: 0;
    font-weight: bold;
}

.brand small {
    color: #dbeafe;
}

.sidebar nav {
    margin-top: 20px;
}

.sidebar a {

    display: block;
    color: #e7f0f7;
    text-decoration: none;
    padding: 13px 15px;
    margin-top: 8px;
    border-radius: 8px;
    transition: 0.2s;
}

.sidebar a i {
    margin-right: 10px;
}

.sidebar a:hover,
.sidebar a.active {

    background: #12618b;
    color: white;
}

.sidebar .logout-link {

    background: #b91c1c;
    margin-top: 30px;
}

.sidebar .logout-link:hover {
    background: #991b1b;
}


/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content {

    margin-left: 250px;
    padding: 30px;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    background: white;
    padding: 18px 22px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.topbar h4 {

    margin: 0;
    font-weight: bold;
    color: #073b5c;
}

.date-text {

    color: #6b7280;
    font-size: 14px;
    margin-top: 5px;
}


/* =========================================================
   PRINT HEADER
========================================================= */

.print-header {
    display: none;
}

.print-logo {

    width: 100px;
    height: 100px;
    object-fit: contain;
    margin-bottom: 10px;
}

.print-header h2 {

    font-weight: bold;
    color: #073b5c;
    margin: 0;
}

.print-header h4 {

    margin-top: 5px;
    color: #333;
}

.print-header p {

    margin: 4px 0;
    color: #555;
}


/* =========================================================
   ELECTION SELECTOR
========================================================= */

.election-selector {

    background: white;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 25px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.06);
    border-left: 5px solid #0d6efd;
}

.election-selector label {

    font-weight: bold;
    color: #073b5c;
    margin-bottom: 8px;
}

.selected-election {

    margin-top: 12px;
    padding: 12px 15px;
    background: #eef6ff;
    border-radius: 8px;
    color: #073b5c;
    font-weight: bold;
}


/* =========================================================
   PRINT STATUS
========================================================= */

.print-status {

    border-radius: 10px;
    padding: 15px 18px;
    margin-bottom: 20px;
    font-weight: 500;
}

.print-status.locked {

    background: #fff3cd;
    border: 1px solid #ffda6a;
    color: #664d03;
}

.print-status.unlocked {

    background: #d1e7dd;
    border: 1px solid #a3cfbb;
    color: #0f5132;
}

.print-status i {
    margin-right: 7px;
}


/* =========================================================
   RESULT CARD
========================================================= */

.result-card {

    background: white;
    border-radius: 14px;
    padding: 25px;
    margin-bottom: 24px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.06);
}

.result-title {

    color: #073b5c;
    font-weight: bold;
}


/* =========================================================
   STATISTICS TABLE
========================================================= */

.statistics-table {

    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.statistics-table th {

    background: #073b5c;
    color: white;
    padding: 12px;
    border: 1px solid #d1d5db;
    text-align: left;
}

.statistics-table td {

    padding: 12px;
    border: 1px solid #d1d5db;
    background: white;
}

.statistics-table td:last-child {

    font-weight: bold;
    text-align: center;
    color: #073b5c;
}


/* =========================================================
   GRAPH
========================================================= */

.chart-container {

    position: relative;
    width: 100%;
    height: 420px;
}


/* =========================================================
   TABLE
========================================================= */

.table thead th {

    background: #073b5c;
    color: white;
}

.candidate-name {

    font-weight: bold;
    color: #073b5c;
}


/* =========================================================
   SIGNATURE
========================================================= */

.signature-section {

    page-break-inside: avoid;
}

.signature-box {

    border: 1px solid #d1d5db;
    border-radius: 10px;
    padding: 24px;
    min-height: 240px;
    background: white;
}

.signature-line {

    height: 35px;
    border-bottom: 1px solid #222;
    margin-bottom: 8px;
}

.signature-label {

    font-size: 13px;
    color: #555;
}

.approval-note {

    background: #f8fafc;
    border: 1px solid #dbe3eb;
    border-radius: 8px;
    padding: 15px;
}

footer {
    padding: 15px 0;
}


/* =========================================================
   DISABLED PRINT BUTTON
========================================================= */

.print-disabled {

    cursor: not-allowed !important;
    opacity: 0.65;
}


/* =========================================================
   PRINT
========================================================= */

@media print {

    @page {

        size: A4;
        margin: 12mm;
    }

    body {

        background: white !important;
        font-size: 11px;
    }

    .sidebar {
        display: none !important;
    }

    .no-print {
        display: none !important;
    }

    .main-content {

        margin-left: 0 !important;
        padding: 0 !important;
    }

    /* Logo + heading zinabaki */

    .print-header {

        display: block !important;
        text-align: center;
        margin-bottom: 20px;
    }

    /* Chart haitoki kwenye report */

    .graph-section {
        display: none !important;
    }

    .topbar {
        display: none !important;
    }

    .election-selector {
        display: none !important;
    }

    .print-status {
        display: none !important;
    }

    /* Hakuna boxes kwenye print */

    .result-card {

        box-shadow: none !important;
        border: none !important;
        padding: 10px 0;
        margin-bottom: 18px;
    }

    /* Statistics table */

    .statistics-table {

        width: 100%;
        font-size: 12px;
        margin-top: 10px;
    }

    .statistics-table th,
    .statistics-table td {

        padding: 9px;
        border: 1px solid #555;
    }

    .statistics-table th {

        background: #073b5c !important;
        color: white !important;

        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Results table */

    .table {

        width: 100%;
        font-size: 11px;
    }

    .table thead th {

        background: #073b5c !important;
        color: white !important;

        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Signature section */

    .signature-section {

        break-before: page;
        page-break-before: always;
    }

    .signature-box {

        break-inside: avoid;
        border: 1px solid #555 !important;
        min-height: 230px;
    }

    .approval-note {

        border: 1px solid #555 !important;
        background: white !important;
    }

    footer {

        display: block !important;
        padding-top: 25px;
        color: #555 !important;
    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 768px) {

    .sidebar {

        width: 100%;
        min-height: auto;
        position: relative;
    }

    .main-content {

        margin-left: 0;
        padding: 15px;
    }

    .chart-container {

        height: 320px;
    }

    .statistics-table {

        font-size: 13px;
    }

    .signature-box {

        min-height: 220px;
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


    <nav>

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

            <i class="bi bi-gear"></i>

            Election Settings

        </a>


        <a
            href="results.php"
            class="active"
        >

            <i class="bi bi-bar-chart"></i>

            Election Results

        </a>


        <a href="election_history.php">

            <i class="bi bi-clock-history"></i>

            Election History

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


<!-- =====================================================
     PRINT HEADER
===================================================== -->

<div class="print-header">

    <img
        src="logo.png"
        alt="College Logo"
        class="print-logo"
    >

    <h2>
        ZSH VOTING SYSTEM
    </h2>

    <h4>
        ELECTION RESULTS REPORT
    </h4>


    <?php if ($selectedElection): ?>

        <h5 class="mt-2">

            <?= htmlspecialchars(
                $electionName,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </h5>

    <?php endif; ?>


    <p>
        Official Election Results
    </p>


    <p>

        Date:

        <?= htmlspecialchars(
            $today,
            ENT_QUOTES,
            "UTF-8"
        ) ?>

    </p>

    <hr>

</div>


<!-- =====================================================
     SCREEN HEADER
===================================================== -->

<div class="topbar">

    <h4>

        <i class="bi bi-bar-chart-fill"></i>

        Election Results Report

    </h4>


    <div class="date-text">

        <?= htmlspecialchars(
            $today,
            ENT_QUOTES,
            "UTF-8"
        ) ?>

    </div>

</div>


<!-- =====================================================
     ELECTION SELECTOR
===================================================== -->

<div class="election-selector no-print">

    <form
        method="GET"
        action="results.php"
        class="row g-3 align-items-end"
    >

        <div class="col-md-8">

            <label for="election_id">

                Select Election

            </label>


            <select
                name="election_id"
                id="election_id"
                class="form-select"
                required
            >

                <?php if (!$elections): ?>

                    <option value="">

                        No elections available

                    </option>

                <?php else: ?>

                    <?php foreach ($elections as $item): ?>

                        <option
                            value="<?= (int)$item["id"] ?>"
                            <?= (
                                (int)$item["id"] ===
                                (int)$selectedElectionId
                            )
                            ? "selected"
                            : ""
                            ?>
                        >

                            <?= htmlspecialchars(
                                $item["election_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                            -

                            <?= htmlspecialchars(
                                ucfirst($item["status"]),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                <?php endif; ?>

            </select>

        </div>


        <div class="col-md-4">

            <button
                type="submit"
                class="btn btn-primary w-100"
            >

                <i class="bi bi-search"></i>

                View Election Results

            </button>

        </div>

    </form>


    <?php if ($selectedElection): ?>

        <div class="selected-election">

            <i class="bi bi-check-circle-fill"></i>

            Viewing results for:

            <?= htmlspecialchars(
                $electionName,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>

</div>


<?php if (!$selectedElection): ?>


    <div class="alert alert-info rounded-4">

        <i class="bi bi-info-circle"></i>

        No election has been selected yet.

    </div>


<?php else: ?>


    <!-- =================================================
         PRINT STATUS
    ================================================= -->

    <?php if ($canPrint): ?>

        <div class="print-status unlocked no-print">

            <i class="bi bi-unlock-fill"></i>

            <strong>
                Printing Available
            </strong>

            — The election has ended. Official results can now
            be printed.

        </div>

    <?php else: ?>

        <div class="print-status locked no-print">

            <i class="bi bi-lock-fill"></i>

            <strong>
                Printing Locked
            </strong>

            <br>

            <?= htmlspecialchars(
                $printMessage,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         ELECTION STATISTICS
    ================================================= -->

    <div class="result-card">

        <h5 class="result-title mb-3">

            <i class="bi bi-bar-chart-line"></i>

            Election Statistics

        </h5>


        <div class="table-responsive">

            <table class="statistics-table">

                <thead>

                    <tr>

                        <th>
                            Statistic
                        </th>

                        <th>
                            Total
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <tr>

                        <td>
                            Total Registered Students
                        </td>

                        <td>
                            <?= $totalStudents ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Students Who Voted
                        </td>

                        <td>
                            <?= $votedStudents ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Students Who Did Not Vote
                        </td>

                        <td>
                            <?= $notVoted ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Total Votes
                        </td>

                        <td>
                            <?= $totalVotes ?>
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =================================================
         GRAPH - SCREEN ONLY
    ================================================= -->

    <div class="result-card graph-section">

        <div
            class="d-flex justify-content-between align-items-center
                   flex-wrap gap-3 mb-4"
        >

            <div>

                <h4 class="result-title mb-1">

                    Candidate Election Results

                </h4>


                <p class="text-muted mb-0">

                    <?= htmlspecialchars(
                        $electionName,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </p>

            </div>


            <?php if ($canPrint): ?>

                <button
                    type="button"
                    onclick="printResults()"
                    class="btn btn-primary no-print"
                >

                    <i class="bi bi-printer"></i>

                    Print Results

                </button>

            <?php else: ?>

                <button
                    type="button"
                    class="btn btn-secondary print-disabled no-print"
                    disabled
                >

                    <i class="bi bi-lock-fill"></i>

                    Printing Locked

                </button>

            <?php endif; ?>

        </div>


        <div class="chart-container">

            <canvas id="candidateChart"></canvas>

        </div>

    </div>


    <!-- =================================================
         PRINT BUTTON
    ================================================= -->

    <div class="text-end mb-3 no-print">

        <?php if ($canPrint): ?>

            <button
                type="button"
                onclick="printResults()"
                class="btn btn-primary"
            >

                <i class="bi bi-printer"></i>

                Print Official Report

            </button>

        <?php else: ?>

            <button
                type="button"
                class="btn btn-secondary print-disabled"
                disabled
            >

                <i class="bi bi-lock-fill"></i>

                Print Locked Until Election Ends

            </button>

        <?php endif; ?>

    </div>


    <!-- =================================================
         ELECTION RESULTS
    ================================================= -->

    <div class="result-card">

        <h5 class="result-title mb-3">

            <i class="bi bi-table"></i>

            Election Results

        </h5>


        <div class="table-responsive">

            <table
                class="table table-bordered table-hover align-middle"
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
                            Votes
                        </th>

                        <th>
                            Percentage
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (count($results) > 0): ?>

                    <?php $number = 1; ?>


                    <?php foreach ($results as $row): ?>

                        <?php

                        $voteCount =
                            (int)$row["vote_count"];

                        $percentage =
                            $totalVotes > 0
                            ? ($voteCount / $totalVotes) * 100
                            : 0;

                        ?>


                        <tr>

                            <td>
                                <?= $number++ ?>
                            </td>


                            <td class="candidate-name">

                                <?= htmlspecialchars(
                                    $row["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <td>

                                <strong>
                                    <?= $voteCount ?>
                                </strong>

                            </td>


                            <td>

                                <?= number_format(
                                    $percentage,
                                    1
                                ) ?>%

                            </td>

                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>

                    <tr>

                        <td
                            colspan="4"
                            class="text-center text-muted"
                        >

                            No votes have been recorded
                            for this election yet.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =================================================
         ELECTION SUPERVISOR SIGNATURE
    ================================================= -->

    <div class="result-card signature-section">

        <h4 class="result-title mb-3">

            <i class="bi bi-pen"></i>

            Election Supervisor Signature

        </h4>


        <div class="approval-note mb-4">

            <strong>
                Election:
            </strong>

            <?= htmlspecialchars(
                $electionName,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

            <br><br>


            <strong>
                Declaration:
            </strong>

            I have reviewed the recorded election results
            and acknowledge the information presented
            in this official report.

        </div>


        <div class="row justify-content-center">

            <div class="col-md-7 col-lg-6">

                <div class="signature-box">

                    <h5 class="fw-bold text-center mb-4">

                        ELECTION SUPERVISOR

                    </h5>


                    <div class="signature-line"></div>

                    <div class="signature-label">

                        Full Name

                    </div>


                    <div class="signature-line mt-4"></div>

                    <div class="signature-label">

                        Signature

                    </div>


                    <div class="signature-line mt-4"></div>

                    <div class="signature-label">

                        Date

                    </div>

                </div>

            </div>

        </div>


        <div class="text-center mt-4 no-print">

            <?php if ($canPrint): ?>

                <button
                    type="button"
                    onclick="printResults()"
                    class="btn btn-primary"
                >

                    <i class="bi bi-printer"></i>

                    Print Results for Approval

                </button>

            <?php else: ?>

                <button
                    type="button"
                    class="btn btn-secondary print-disabled"
                    disabled
                >

                    <i class="bi bi-lock-fill"></i>

                    Print Locked Until Election Ends

                </button>

            <?php endif; ?>

        </div>

    </div>


<?php endif; ?>


<!-- =====================================================
     FOOTER
===================================================== -->

<footer class="text-center text-muted">

    <small>

        ZSH Voting System

        &copy; <?= date("Y") ?>

        <br>

        designed by

        <br>

        @lucho

    </small>

</footer>


</div>


<script>

/* =========================================================
   PRINT CONTROL
========================================================= */

const canPrint =
    <?= $canPrint ? "true" : "false" ?>;

const electionEndTimestamp =
    <?= (int)$electionEndTimestamp ?>;


function printResults() {

    if (!canPrint) {

        alert(
            "Printing is locked. " +
            "You can print the election results only after the election has ended."
        );

        return;
    }


    if (
        electionEndTimestamp > 0 &&
        Date.now() < electionEndTimestamp
    ) {

        alert(
            "The election has not ended yet. " +
            "Printing will be available after the election end time."
        );

        return;
    }


    window.print();
}


/* =========================================================
   CANDIDATE DATA
========================================================= */

const candidateNames = <?= json_encode(
    array_column(
        $results,
        "name"
    ),
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
) ?>;


const voteCounts = <?= json_encode(
    array_map(
        "intval",
        array_column(
            $results,
            "vote_count"
        )
    )
) ?>;


/* =========================================================
   CANDIDATE COLORS
========================================================= */

const candidateColors = [

    "#0d6efd",
    "#198754",
    "#dc3545",
    "#ffc107",
    "#6f42c1",
    "#fd7e14",
    "#20c997",
    "#d63384",
    "#6610f2",
    "#0dcaf0",
    "#1982c4",
    "#8ac926"

];


function generateColor(index) {

    if (candidateColors[index]) {
        return candidateColors[index];
    }

    const hue =
        (index * 47) % 360;

    return `hsl(${hue}, 70%, 50%)`;
}


const backgroundColors =
    candidateNames.map(
        function(name, index) {

            return generateColor(index);

        }
    );


const borderColors =
    candidateNames.map(
        function(name, index) {

            if (candidateColors[index]) {
                return candidateColors[index];
            }

            const hue =
                (index * 47) % 360;

            return `hsl(${hue}, 70%, 40%)`;

        }
    );


/* =========================================================
   CREATE CHART
========================================================= */

const chartElement =
    document.getElementById(
        "candidateChart"
    );


if (chartElement) {

    new Chart(
        chartElement,
        {

            type: "bar",

            data: {

                labels: candidateNames,

                datasets: [{

                    label: "Votes",

                    data: voteCounts,

                    backgroundColor:
                        backgroundColors,

                    borderColor:
                        borderColors,

                    borderWidth: 2,

                    borderRadius: 6

                }]

            },


            options: {

                responsive: true,

                maintainAspectRatio: false,


                plugins: {

                    legend: {

                        display: true

                    },


                    tooltip: {

                        callbacks: {

                            label:
                                function(context) {

                                    return " Votes: " +
                                        context.raw;

                                }

                        }

                    }

                },


                scales: {

                    y: {

                        beginAtZero: true,

                        ticks: {

                            stepSize: 1

                        },


                        title: {

                            display: true,

                            text: "Number of Votes"

                        }

                    },


                    x: {

                        title: {

                            display: true,

                            text: "Candidates"

                        }

                    }

                }

            }

        }
    );

}


/* =========================================================
   AUTOMATIC REFRESH AFTER ELECTION ENDS
========================================================= */

if (
    !canPrint &&
    electionEndTimestamp > 0
) {

    const currentTime =
        Date.now();

    const remainingTime =
        electionEndTimestamp -
        currentTime;


    if (remainingTime > 0) {

        setTimeout(
            function() {

                window.location.reload();

            },
            remainingTime + 1000
        );

    }

}

</script>


</body>

</html>