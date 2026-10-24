<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

date_default_timezone_set('Africa/Dar_es_Salaam');
require_once __DIR__ . '/db.php';

try {
    $conn->exec("SET time_zone = '+03:00'");
} catch (Throwable $e) {
    error_log("Timezone error: " . $e->getMessage());
}

/* =========================
   LIVE ELECTION API
========================= */
if (isset($_GET['action']) && $_GET['action'] === 'live_data') {

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    try {

        /*
         * Get the nearest election that has not ended.
         * This allows the Live Screen to count down BEFORE
         * the election starts, just like Student Dashboard.
         */
        $stmt = $conn->query("
            SELECT id, election_name, start_time, end_time, status
            FROM elections
            WHERE LOWER(TRIM(status)) NOT IN ('cancelled', 'closed')
              AND end_time > NOW()
            ORDER BY
                CASE
                    WHEN start_time <= NOW() AND end_time > NOW() THEN 0
                    ELSE 1
                END,
                start_time ASC,
                id ASC
            LIMIT 1
        ");

        $election = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$election) {

            echo json_encode([
                'success' => true,
                'server_time' => date('c'),
                'election' => null,
                'total_votes' => 0,
                'bars' => []
            ]);

            exit;
        }

        /* =========================
           GET CANDIDATE RESULTS
        ========================= */

        $stmt = $conn->prepare("
            SELECT
                c.id,
                c.name,
                COUNT(v.id) AS vote_count
            FROM candidates c
            LEFT JOIN votes v
                ON v.candidate_id = c.id
               AND v.election_id = ?
            WHERE c.status = 'active'
            GROUP BY c.id, c.name
            ORDER BY c.id ASC
        ");

        $stmt->execute([(int)$election['id']]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalVotes = 0;

        foreach ($rows as $row) {
            $totalVotes += max(0, (int)$row['vote_count']);
        }

        $bars = [];

        foreach ($rows as $row) {

            $votes = max(0, (int)$row['vote_count']);

            $bars[] = [
                'name' => $row['name'],
                'percentage' => $totalVotes > 0
                    ? round(($votes / $totalVotes) * 100, 1)
                    : 0
            ];
        }

        /*
         * Determine whether election has started.
         */
        $now = new DateTime(
            'now',
            new DateTimeZone('Africa/Dar_es_Salaam')
        );

        $start = new DateTime(
            $election['start_time'],
            new DateTimeZone('Africa/Dar_es_Salaam')
        );

        $end = new DateTime(
            $election['end_time'],
            new DateTimeZone('Africa/Dar_es_Salaam')
        );

        if ($now < $start) {
            $phase = 'before_start';
        } elseif ($now >= $start && $now < $end) {
            $phase = 'ongoing';
        } else {
            $phase = 'ended';
        }

        echo json_encode([
            'success' => true,

            'server_time' => date('c'),

            'election' => [
                'id' => (int)$election['id'],
                'name' => $election['election_name'],

                'start_time' => $start->format('c'),

                'end_time' => $end->format('c'),

                'status' => $election['status'],

                'phase' => $phase
            ],

            'total_votes' => $totalVotes,

            'bars' => $bars

        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

    } catch (Throwable $e) {

        error_log("Live API error: " . $e->getMessage());

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to load live election data.'
        ]);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Live Election Screen | ZSH</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
      rel="stylesheet">

<style>

:root {
    --navy: #073b5c;
    --blue: #12618b;
    --page: #f2f6fb;
    --muted: #6b7c8f;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--page);
    color: #193247;
    font-family: Arial, Helvetica, sans-serif;
}

.navbar {
    background: linear-gradient(120deg, #052c46, #0b5279);
    padding: 13px 0;
    box-shadow: 0 3px 14px #0002;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    color: white;
    font-size: 19px;
    font-weight: 800;
    text-decoration: none;
}

.brand img {
    width: 42px;
    height: 42px;
    object-fit: contain;
    padding: 3px;
    background: white;
    border-radius: 8px;
}

.back-btn {
    color: white;
    border: 1px solid #ffffff99;
    padding: 8px 13px;
    border-radius: 8px;
    text-decoration: none;
}

.back-btn:hover {
    color: var(--navy);
    background: white;
}

.page {
    max-width: 1250px;
    margin: 30px auto;
    padding: 0 18px;
}

.page-title {
    font-size: clamp(25px, 4vw, 34px);
    font-weight: 800;
}

.subtitle,
.muted {
    color: var(--muted);
}

.panel {
    background: white;
    border: 1px solid #e6edf4;
    border-radius: 16px;
    padding: 23px;
    margin-bottom: 20px;
    box-shadow: 0 5px 22px #14334b0c;
}

.small-heading {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    color: var(--blue);
}

.election-name {
    font-size: clamp(21px, 3vw, 29px);
    font-weight: 800;
    margin: 9px 0 12px;
    overflow-wrap: anywhere;
}

.live-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 13px;
    border-radius: 30px;
    background: #e4f8ed;
    color: #087b48;
    font-size: 13px;
    font-weight: 800;
}

.live-badge.waiting {
    background: #fff4d6;
    color: #9a6700;
}

.live-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #10a765;
    animation: pulse 1.5s infinite;
}

.live-badge.waiting .live-dot {
    background: #e0a100;
}

@keyframes pulse {

    50% {
        opacity: .4;
        transform: scale(.8);
    }

}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.stat-card {
    padding: 22px;
    border-radius: 15px;
    color: white;
    background: linear-gradient(135deg, #073b5c, #12618b);
    box-shadow: 0 6px 18px #073b5c22;
}

.stat-card.votes {
    background: linear-gradient(135deg, #087b65, #10a765);
}

.stat-label {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    opacity: .9;
}

.stat-number {
    font-size: clamp(30px, 5vw, 42px);
    font-weight: 900;
    margin-top: 8px;
    font-variant-numeric: tabular-nums;
}

.stat-caption {
    font-size: 12px;
    margin-top: 3px;
    opacity: .85;
}

/* =========================
   COUNTDOWN
========================= */

.countdown-card {
    text-align: center;
    color: white;
    background: linear-gradient(135deg, #073b5c, #12618b);
    border-radius: 16px;
    padding: 25px 15px;
    margin-bottom: 20px;
}

.countdown-label {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.4px;
}

#countdown {
    font-size: clamp(28px, 6vw, 48px);
    font-weight: 900;
    letter-spacing: 1px;
    margin-top: 8px;
    font-variant-numeric: tabular-nums;
    word-spacing: 6px;
}

.countdown-help {
    font-size: 13px;
    opacity: .8;
    margin-top: 6px;
}

/* =========================
   SCHEDULE
========================= */

.schedule-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.schedule-item {
    background: #f7f9fc;
    border: 1px solid #e7edf3;
    border-radius: 12px;
    padding: 16px;
}

.schedule-label {
    font-size: 12px;
    color: var(--muted);
    text-transform: uppercase;
    font-weight: 800;
    margin-bottom: 7px;
}

.schedule-value {
    font-weight: 700;
    overflow-wrap: anywhere;
}

/* =========================
   GRAPH
========================= */

.graph-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 8px;
}

.graph-title {
    font-size: 22px;
    font-weight: 900;
    margin: 0;
}

.graph-tools {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.graph-btn {
    background: var(--navy);
    color: white;
    border: 0;
    border-radius: 9px;
    padding: 9px 13px;
    font-size: 13px;
    font-weight: 700;
}

.graph-btn:hover {
    background: var(--blue);
}

.chart-container {
    position: relative;
    width: 100%;
    height: 430px;
    margin-top: 18px;
}

.chart-note {
    text-align: center;
    color: var(--muted);
    font-size: 12px;
    margin-top: 12px;
}

.graph-panel:fullscreen {
    background: white;
    border: 0;
    border-radius: 0;
    padding: 25px;
    margin: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
}

.graph-panel:fullscreen .chart-container {
    height: calc(100vh - 170px);
    min-height: 300px;
}

.graph-panel:fullscreen .graph-title {
    font-size: 28px;
}

/* =========================
   MESSAGE
========================= */

.message-box {
    background: white;
    border: 1px solid #e6edf4;
    border-radius: 15px;
    padding: 35px 20px;
    text-align: center;
}

.message-box i {
    display: block;
    font-size: 38px;
    color: var(--blue);
    margin-bottom: 12px;
}

.message-box h3 {
    font-weight: 800;
}

.message-box p {
    color: var(--muted);
    margin-bottom: 0;
    overflow-wrap: anywhere;
}

.footer {
    text-align: center;
    color: var(--muted);
    font-size: 13px;
    padding: 10px 15px 28px;
}

/* =========================
   MOBILE
========================= */

@media (max-width: 600px) {

    .page {
        margin: 20px auto;
        padding: 0 12px;
    }

    .panel {
        padding: 16px;
    }

    .brand {
        font-size: 15px;
    }

    .brand img {
        width: 35px;
        height: 35px;
    }

    .back-btn {
        padding: 7px 9px;
        font-size: 12px;
    }

    .stats-grid {
        gap: 10px;
    }

    .stat-card {
        padding: 16px 12px;
    }

    .stat-label {
        font-size: 10px;
    }

    .stat-caption {
        font-size: 11px;
    }

    .schedule-grid {
        grid-template-columns: 1fr;
    }

    .chart-container {
        height: 380px;
    }

    .graph-header {
        align-items: flex-start;
    }

    .graph-title {
        font-size: 20px;
    }

    .graph-btn {
        padding: 8px 10px;
    }

    #countdown {
        font-size: 27px;
        word-spacing: 3px;
    }

    .graph-panel:fullscreen {
        padding: 13px;
    }

    .graph-panel:fullscreen .chart-container {
        height: calc(100vh - 150px);
    }
}

</style>
</head>

<body>

<nav class="navbar">

    <div class="container d-flex justify-content-between align-items-center gap-2">

        <a href="index.php" class="brand">

            <img src="logo.png" alt="ZSH Logo">

            <span>ZSH Voting System</span>

        </a>

        <a href="index.php" class="back-btn">

            <i class="bi bi-arrow-left"></i>
            Back

        </a>

    </div>

</nav>

<main class="page">

    <h1 class="page-title">
        Election Live Screen
    </h1>

    <p class="subtitle mb-4">
        Follow election activity as it updates.
    </p>

    <div id="loadingMessage" class="message-box">

        <i class="bi bi-arrow-repeat"></i>

        <h3>
            Loading Election...
        </h3>

        <p>
            Please wait while we check the election status.
        </p>

    </div>

    <div id="errorMessage" class="message-box d-none">

        <i class="bi bi-exclamation-triangle"></i>

        <h3>
            Unable to Load Election
        </h3>

        <p id="errorText">
            Please try again.
        </p>

        <button type="button"
                id="retryButton"
                class="graph-btn mt-3">

            <i class="bi bi-arrow-clockwise"></i>
            Try Again

        </button>

    </div>

    <div id="noElectionMessage" class="message-box d-none">

        <i class="bi bi-calendar2-x"></i>

        <h3>
            No Upcoming Election
        </h3>

        <p>
            This screen will show the next scheduled election.
        </p>

    </div>

    <div id="electionContent" class="d-none">

        <!-- =========================
             ELECTION INFORMATION
        ========================== -->

        <section class="panel">

            <div class="small-heading">
                Election
            </div>

            <h2 id="electionName"
                class="election-name">
                Election Name
            </h2>

            <span id="liveBadge"
                  class="live-badge waiting">

                <span class="live-dot"></span>

                <span id="liveBadgeText">
                    Election starts soon
                </span>

            </span>

        </section>


        <!-- =========================
             STATISTICS
        ========================== -->

        <section class="stats-grid">

            <div class="stat-card votes">

                <div class="stat-label">

                    <i class="bi bi-check2-square"></i>
                    Total Votes Cast

                </div>

                <div id="totalVotes"
                     class="stat-number">

                    0

                </div>

                <div class="stat-caption">
                    Recorded votes in this election
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">

                    <i class="bi bi-people"></i>
                    Active Candidates

                </div>

                <div id="candidateCount"
                     class="stat-number">

                    0

                </div>

                <div class="stat-caption">
                    Candidates shown in the graph
                </div>

            </div>

        </section>


        <!-- =========================
             COUNTDOWN
        ========================== -->

        <section class="countdown-card">

            <div id="countdownLabel"
                 class="countdown-label">

                <i class="bi bi-clock"></i>
                TIME UNTIL ELECTION STARTS

            </div>

            <div id="countdown">
                0d 00h 00m 00s
            </div>

            <div id="countdownHelp"
                 class="countdown-help">

                Please wait until voting begins.

            </div>

        </section>


        <!-- =========================
             ELECTION SCHEDULE
        ========================== -->

        <section class="panel">

            <h2 class="graph-title mb-3">
                Election Schedule
            </h2>

            <div class="schedule-grid">

                <div class="schedule-item">

                    <div class="schedule-label">

                        <i class="bi bi-play-circle"></i>
                        Start Time

                    </div>

                    <div id="startTime"
                         class="schedule-value">

                        -

                    </div>

                </div>


                <div class="schedule-item">

                    <div class="schedule-label">

                        <i class="bi bi-stop-circle"></i>
                        End Time

                    </div>

                    <div id="endTime"
                         class="schedule-value">

                        -

                    </div>

                </div>

            </div>

        </section>


        <!-- =========================
             LIVE GRAPH
        ========================== -->

        <section id="graphPanel"
                 class="panel graph-panel">

            <div class="graph-header">

                <div>

                    <h2 class="graph-title">
                        Live Election Graph
                    </h2>

                    <div class="muted small mt-1">
                        Candidate vote percentages
                    </div>

                </div>

                <div class="graph-tools">

                    <span class="live-badge">

                        <span class="live-dot"></span>
                        Live

                    </span>

                    <button type="button"
                            id="fullscreenButton"
                            class="graph-btn">

                        <i class="bi bi-arrows-fullscreen"></i>
                        Enlarge Graph

                    </button>

                </div>

            </div>


            <div id="noCandidatesMessage"
                 class="alert alert-info d-none mt-3">

                No active candidates are available.

            </div>


            <div id="chartContainer"
                 class="chart-container">

                <canvas id="electionChart"></canvas>

            </div>


            <div class="chart-note">

                Candidate names are hidden. Touching or hovering over a bar
                will not display additional information.

            </div>

        </section>

    </div>

</main>


<footer class="footer">

    &copy; <?php echo date('Y'); ?>
    ZSH Voting System

</footer>


<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>


<script>

/* =========================
   ELEMENTS
========================= */

const loadingMessage =
    document.getElementById('loadingMessage');

const errorMessage =
    document.getElementById('errorMessage');

const errorText =
    document.getElementById('errorText');

const noElectionMessage =
    document.getElementById('noElectionMessage');

const electionContent =
    document.getElementById('electionContent');

const countdownElement =
    document.getElementById('countdown');

const countdownLabel =
    document.getElementById('countdownLabel');

const countdownHelp =
    document.getElementById('countdownHelp');

const liveBadge =
    document.getElementById('liveBadge');

const liveBadgeText =
    document.getElementById('liveBadgeText');

const chartContainer =
    document.getElementById('chartContainer');

const noCandidatesMessage =
    document.getElementById('noCandidatesMessage');

const graphPanel =
    document.getElementById('graphPanel');

const fullscreenButton =
    document.getElementById('fullscreenButton');

const retryButton =
    document.getElementById('retryButton');


/* =========================
   VARIABLES
========================= */

let electionChart = null;

let electionStartTime = null;

let electionEndTime = null;

let serverTimeDifference = 0;

let requestInProgress = false;

let countdownRefreshRequested = false;


/* =========================
   COLORS
========================= */

const candidateColors = [

    '#2563eb',
    '#f97316',
    '#10b981',
    '#9333ea',
    '#e11d48',
    '#0891b2',
    '#ca8a04',
    '#4f46e5',
    '#65a30d',
    '#db2777',
    '#7c3aed',
    '#0284c7'

];


/* =========================
   DATE FORMAT
========================= */

function formatDate(dateString) {

    const date = new Date(dateString);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleString('en-GB', {

        timeZone: 'Africa/Dar_es_Salaam',

        day: '2-digit',

        month: 'short',

        year: 'numeric',

        hour: '2-digit',

        minute: '2-digit',

        hour12: false

    });
}


/* =========================
   COUNTDOWN FORMAT
   SAME STYLE AS
   STUDENT DASHBOARD
========================= */

function formatCountdown(milliseconds) {

    const totalSeconds =
        Math.max(
            0,
            Math.floor(milliseconds / 1000)
        );

    const days =
        Math.floor(totalSeconds / 86400);

    const hours =
        Math.floor(
            (totalSeconds % 86400) / 3600
        );

    const minutes =
        Math.floor(
            (totalSeconds % 3600) / 60
        );

    const seconds =
        totalSeconds % 60;

    return (
        days + 'd ' +
        String(hours).padStart(2, '0') + 'h ' +
        String(minutes).padStart(2, '0') + 'm ' +
        String(seconds).padStart(2, '0') + 's'
    );
}


/* =========================
   DESTROY GRAPH
========================= */

function destroyGraph() {

    if (electionChart) {

        electionChart.destroy();

        electionChart = null;
    }
}


/* =========================
   CREATE GRAPH
========================= */

function createGraph(bars) {

    destroyGraph();

    const percentages =
        bars.map(
            item => Number(item.percentage) || 0
        );

    const colors =
        bars.map(
            (_, index) =>
                candidateColors[
                    index % candidateColors.length
                ]
        );

    electionChart = new Chart(

        document
            .getElementById('electionChart')
            .getContext('2d'),

        {

            type: 'bar',

            data: {

                labels: bars.map(() => ''),

                datasets: [{

                    label: '',

                    data: percentages,

                    backgroundColor: colors,

                    borderColor: colors,

                    borderWidth: 1,

                    borderRadius: 8,

                    borderSkipped: false,

                    maxBarThickness: 68,

                    categoryPercentage: 0.72,

                    barPercentage: 0.85

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                events: [],

                animation: {
                    duration: 450
                },

                layout: {

                    padding: {
                        top: 28,
                        right: 16,
                        left: 5,
                        bottom: 5
                    }

                },

                plugins: {

                    legend: {
                        display: false
                    },

                    tooltip: {
                        enabled: false
                    }

                },

                scales: {

                    x: {

                        grid: {
                            display: false
                        },

                        ticks: {
                            display: false
                        },

                        border: {
                            display: false
                        }

                    },

                    y: {

                        beginAtZero: true,

                        max: 100,

                        ticks: {

                            stepSize: 20,

                            callback:
                                value => value + '%',

                            color: '#6b7c8f'

                        },

                        grid: {
                            color: '#e9eef5'
                        },

                        border: {
                            display: false
                        }

                    }

                }

            }

        }

    );
}


/* =========================
   UPDATE GRAPH
========================= */

function updateGraph(bars) {

    document.getElementById(
        'candidateCount'
    ).textContent = bars.length;

    if (!bars.length) {

        destroyGraph();

        chartContainer.classList.add('d-none');

        noCandidatesMessage.classList.remove(
            'd-none'
        );

        return;
    }

    chartContainer.classList.remove(
        'd-none'
    );

    noCandidatesMessage.classList.add(
        'd-none'
    );

    const percentages =
        bars.map(
            item => Number(item.percentage) || 0
        );

    if (
        !electionChart ||
        electionChart.data.datasets[0].data.length !==
        bars.length
    ) {

        createGraph(bars);

        return;
    }

    electionChart.data.labels =
        bars.map(() => '');

    electionChart.data.datasets[0].data =
        percentages;

    electionChart.update('none');
}


/* =========================
   FULLSCREEN
========================= */

async function toggleGraphFullscreen() {

    try {

        if (!document.fullscreenElement) {

            if (!graphPanel.requestFullscreen) {

                alert(
                    'Full screen is not supported by this browser.'
                );

                return;
            }

            await graphPanel.requestFullscreen();

        } else {

            await document.exitFullscreen();
        }

    } catch (error) {

        console.error(
            'Fullscreen error:',
            error
        );
    }
}


fullscreenButton.addEventListener(
    'click',
    toggleGraphFullscreen
);


/* =========================
   FULLSCREEN CHANGE
========================= */

document.addEventListener(
    'fullscreenchange',
    function() {

        if (
            document.fullscreenElement ===
            graphPanel
        ) {

            fullscreenButton.innerHTML =
                '<i class="bi bi-fullscreen-exit"></i> Exit Full Screen';

        } else {

            fullscreenButton.innerHTML =
                '<i class="bi bi-arrows-fullscreen"></i> Enlarge Graph';
        }

        if (electionChart) {

            setTimeout(
                () => electionChart.resize(),
                100
            );
        }

    }
);


/* =========================
   CLEAR SCREEN
========================= */

function clearElectionScreen() {

    electionContent.classList.add(
        'd-none'
    );

    destroyGraph();

    electionStartTime = null;

    electionEndTime = null;

    countdownElement.textContent =
        '0d 00h 00m 00s';

    countdownLabel.innerHTML =
        '<i class="bi bi-clock"></i> TIME UNTIL ELECTION STARTS';

    countdownHelp.textContent =
        'Please wait until voting begins.';

    liveBadge.classList.add(
        'waiting'
    );

    liveBadgeText.textContent =
        'Election starts soon';

    document.getElementById(
        'totalVotes'
    ).textContent = '0';

    document.getElementById(
        'candidateCount'
    ).textContent = '0';
}


/* =========================
   NO ELECTION
========================= */

function showNoElection() {

    loadingMessage.classList.add(
        'd-none'
    );

    errorMessage.classList.add(
        'd-none'
    );

    noElectionMessage.classList.remove(
        'd-none'
    );

    clearElectionScreen();
}


/* =========================
   ERROR
========================= */

function showError(message) {

    loadingMessage.classList.add(
        'd-none'
    );

    noElectionMessage.classList.add(
        'd-none'
    );

    electionContent.classList.add(
        'd-none'
    );

    errorText.textContent =
        message ||
        'Unable to load election data.';

    errorMessage.classList.remove(
        'd-none'
    );
}


/* =========================
   UPDATE COUNTDOWN
========================= */

function updateCountdown() {

    if (
        !electionStartTime ||
        !electionEndTime
    ) {
        return;
    }

    const now =
        Date.now() +
        serverTimeDifference;


    /*
     * BEFORE ELECTION STARTS
     */

    if (
        now < electionStartTime
    ) {

        const remaining =
            electionStartTime - now;

        countdownLabel.innerHTML =
            '<i class="bi bi-clock"></i> TIME UNTIL ELECTION STARTS';

        countdownHelp.textContent =
            'Please wait until voting begins.';

        liveBadge.classList.add(
            'waiting'
        );

        liveBadgeText.textContent =
            'Election starts soon';

        countdownElement.textContent =
            formatCountdown(remaining);

        countdownRefreshRequested =
            false;

        return;
    }


    /*
     * ELECTION IS CURRENTLY RUNNING
     */

    if (
        now >= electionStartTime &&
        now < electionEndTime
    ) {

        const remaining =
            electionEndTime - now;

        countdownLabel.innerHTML =
            '<i class="bi bi-clock"></i> TIME UNTIL ELECTION ENDS';

        countdownHelp.textContent =
            'Voting is open. Please vote before time runs out.';

        liveBadge.classList.remove(
            'waiting'
        );

        liveBadgeText.textContent =
            'Election in progress';

        countdownElement.textContent =
            formatCountdown(remaining);

        countdownRefreshRequested =
            false;

        return;
    }


    /*
     * ELECTION HAS ENDED
     */

    countdownElement.textContent =
        '0d 00h 00m 00s';

    countdownLabel.innerHTML =
        '<i class="bi bi-check-circle"></i> ELECTION CLOSED';

    countdownHelp.textContent =
        'The election has ended.';

    liveBadge.classList.remove(
        'waiting'
    );

    liveBadgeText.textContent =
        'Election ended';


    /*
     * Refresh data once after election ends.
     */

    if (!countdownRefreshRequested) {

        countdownRefreshRequested =
            true;

        loadLiveData();
    }
}


/* =========================
   LOAD LIVE DATA
========================= */

async function loadLiveData() {

    if (requestInProgress) {
        return;
    }

    requestInProgress = true;

    try {

        const apiUrl =
            new URL(
                window.location.href
            );

        apiUrl.searchParams.set(
            'action',
            'live_data'
        );

        apiUrl.searchParams.set(
            '_',
            Date.now()
        );


        const response =
            await fetch(
                apiUrl.toString(),
                {
                    method: 'GET',

                    cache: 'no-store',

                    headers: {
                        'Accept':
                            'application/json'
                    }
                }
            );


        const responseText =
            await response.text();


        if (!response.ok) {

            console.error(
                'API HTTP error:',
                response.status,
                responseText
            );

            throw new Error(
                'API returned HTTP ' +
                response.status
            );
        }


        let data;


        try {

            data =
                JSON.parse(
                    responseText
                );

        } catch (parseError) {

            console.error(
                'Invalid API response:',
                responseText
            );

            throw new Error(
                'API did not return valid JSON. Check PHP errors.'
            );
        }


        if (!data.success) {

            throw new Error(
                data.message ||
                'Unable to load election data.'
            );
        }


        /*
         * Synchronize browser time with server.
         */

        if (data.server_time) {

            serverTimeDifference =
                new Date(
                    data.server_time
                ).getTime() -
                Date.now();
        }


        loadingMessage.classList.add(
            'd-none'
        );

        errorMessage.classList.add(
            'd-none'
        );


        /*
         * No upcoming/ongoing election.
         */

        if (!data.election) {

            showNoElection();

            return;
        }


        noElectionMessage.classList.add(
            'd-none'
        );

        electionContent.classList.remove(
            'd-none'
        );


        /*
         * Election name.
         */

        document.getElementById(
            'electionName'
        ).textContent =
            data.election.name ||
            'Election';


        /*
         * Schedule.
         */

        document.getElementById(
            'startTime'
        ).textContent =
            formatDate(
                data.election.start_time
            );


        document.getElementById(
            'endTime'
        ).textContent =
            formatDate(
                data.election.end_time
            );


        /*
         * Total votes.
         */

        document.getElementById(
            'totalVotes'
        ).textContent =
            Number(
                data.total_votes || 0
            ).toLocaleString(
                'en-US'
            );


        /*
         * Save start and end times.
         */

        electionStartTime =
            new Date(
                data.election.start_time
            ).getTime();


        electionEndTime =
            new Date(
                data.election.end_time
            ).getTime();


        if (
            Number.isNaN(
                electionStartTime
            ) ||
            Number.isNaN(
                electionEndTime
            )
        ) {

            throw new Error(
                'Invalid election time returned by the API.'
            );
        }


        /*
         * Update graph.
         */

        updateGraph(
            Array.isArray(data.bars)
                ? data.bars
                : []
        );


        /*
         * Update countdown.
         */

        updateCountdown();


    } catch (error) {

        console.error(
            'Live election error:',
            error
        );

        showError(
            error.message ||
            'An unexpected error occurred.'
        );

    } finally {

        requestInProgress = false;
    }
}


/* =========================
   RETRY
========================= */

retryButton.addEventListener(
    'click',
    function() {

        errorMessage.classList.add(
            'd-none'
        );

        loadingMessage.classList.remove(
            'd-none'
        );

        loadLiveData();
    }
);


/* =========================
   INITIAL LOAD
========================= */

loadLiveData();


/*
 * Refresh election data every 10 seconds.
 */

setInterval(
    loadLiveData,
    10000
);


/*
 * Update countdown every second.
 */

setInterval(
    updateCountdown,
    1000
);

</script>

</body>
</html>