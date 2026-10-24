<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

require_once __DIR__ . '/db.php';

$conn->exec("SET time_zone = '+03:00'");


/* =========================
   CHECK STUDENT SESSION
========================= */

if (!isset($_SESSION['student_username'])) {

    header("Location: index.php?role=student");
    exit;
}


$message = '';
$error = '';
$voteSubmitted = false;
$election = null;
$electionError = '';


/* =========================
   GET STUDENT
========================= */

$stmt = $conn->prepare(
    "SELECT id, student_id, name, username, status, has_voted
     FROM students
     WHERE username = ?
     LIMIT 1"
);

$stmt->execute([
    $_SESSION['student_username']
]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);


if (
    !$student ||
    $student['status'] !== 'active'
) {

    session_destroy();

    header("Location: index.php?role=student");
    exit;
}


/* =========================
   GET CURRENT OR UPCOMING ELECTION
========================= */

try {

    $stmt = $conn->query(
        "SELECT id, election_name, start_time, end_time, status
         FROM elections
         WHERE status NOT IN ('cancelled', 'closed')
           AND end_time > NOW()
         ORDER BY start_time ASC
         LIMIT 1"
    );

    $election = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

} catch (PDOException $e) {

    error_log(
        "Election retrieval error: " .
        $e->getMessage()
    );

    $electionError =
        'Unable to retrieve election information.';
}


/* =========================
   CHECK WHETHER STUDENT VOTED
   IN THIS ELECTION
========================= */

if ($election) {

    $stmt = $conn->prepare(
        "SELECT id
         FROM votes
         WHERE student_id = ?
           AND election_id = ?
         LIMIT 1"
    );

    $stmt->execute([
        $student['id'],
        $election['id']
    ]);

    $voteSubmitted =
        (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}


/* =========================
   MESSAGE AFTER VOTING
========================= */

if (
    isset($_GET['voted']) &&
    $_GET['voted'] === '1' &&
    $voteSubmitted
) {

    $message =
        'Your vote has been recorded successfully. Thank you for voting!';
}


/* =========================
   CSRF TOKEN
========================= */

if (empty($_SESSION['vote_csrf'])) {

    $_SESSION['vote_csrf'] =
        bin2hex(random_bytes(32));
}


/* =========================
   CHECK WHETHER VOTING IS OPEN
========================= */

$electionOpen = false;

if ($election) {

    $stmt = $conn->prepare(
        "SELECT COUNT(*)
         FROM elections
         WHERE id = ?
           AND status NOT IN ('cancelled', 'closed')
           AND NOW() >= start_time
           AND NOW() < end_time"
    );

    $stmt->execute([
        $election['id']
    ]);

    $electionOpen =
        ((int) $stmt->fetchColumn() === 1);
}


/* =========================
   PROCESS VOTE
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $candidateId = filter_input(
        INPUT_POST,
        'candidate_id',
        FILTER_VALIDATE_INT
    );

    $token =
        $_POST['csrf_token'] ?? '';


    if (
        !$candidateId ||
        $candidateId < 1
    ) {

        $error =
            'Please select a candidate and try again.';

    } elseif (
        !is_string($token) ||
        !isset($_SESSION['vote_csrf']) ||
        !hash_equals(
            $_SESSION['vote_csrf'],
            $token
        )
    ) {

        $error =
            'Your session has expired. Please refresh the page and try again.';

    } elseif (!$election) {

        $error =
            'There is currently no election available.';

    } elseif (!$electionOpen) {

        $error =
            'Voting is not open or the election period has ended.';

    } elseif ($voteSubmitted) {

        $message =
            'You have already voted in this election.';

    } else {

        try {

            $conn->beginTransaction();


            /* Lock student row */

            $lock = $conn->prepare(
                "SELECT id
                 FROM students
                 WHERE id = ?
                 FOR UPDATE"
            );

            $lock->execute([
                $student['id']
            ]);


            if (!$lock->fetch(PDO::FETCH_ASSOC)) {

                throw new RuntimeException(
                    'Student record was not found.'
                );
            }


            /* Check whether student already voted */

            $checkVote = $conn->prepare(
                "SELECT id
                 FROM votes
                 WHERE student_id = ?
                   AND election_id = ?
                 LIMIT 1"
            );

            $checkVote->execute([
                $student['id'],
                $election['id']
            ]);


            if ($checkVote->fetch(PDO::FETCH_ASSOC)) {

                throw new RuntimeException(
                    'You have already voted in this election.'
                );
            }


            /* Verify election is still open */

            $checkElection = $conn->prepare(
                "SELECT COUNT(*)
                 FROM elections
                 WHERE id = ?
                   AND status NOT IN ('cancelled', 'closed')
                   AND NOW() >= start_time
                   AND NOW() < end_time"
            );

            $checkElection->execute([
                $election['id']
            ]);


            if (
                (int) $checkElection->fetchColumn()
                !== 1
            ) {

                throw new RuntimeException(
                    'The election has closed or the voting period has ended.'
                );
            }


            /* Verify candidate is active */

            $checkCandidate = $conn->prepare(
                "SELECT id
                 FROM candidates
                 WHERE id = ?
                   AND status = 'active'
                 LIMIT 1"
            );

            $checkCandidate->execute([
                $candidateId
            ]);


            if (
                !$checkCandidate->fetch(PDO::FETCH_ASSOC)
            ) {

                throw new RuntimeException(
                    'The selected candidate does not exist or is inactive.'
                );
            }


            /* Save vote */

            $insert = $conn->prepare(
                "INSERT INTO votes
                    (student_id, election_id, candidate_id)
                 VALUES (?, ?, ?)"
            );

            $insert->execute([
                $student['id'],
                $election['id'],
                $candidateId
            ]);


            /*
             * Compatibility field only.
             * It does not block future elections.
             */

            $update = $conn->prepare(
                "UPDATE students
                 SET has_voted = 1
                 WHERE id = ?"
            );

            $update->execute([
                $student['id']
            ]);


            $conn->commit();


            $_SESSION['vote_csrf'] =
                bin2hex(random_bytes(32));


            header(
                "Location: student_dashboard.php?voted=1"
            );

            exit;

        } catch (Throwable $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            error_log(
                "Vote save error: " .
                $e->getMessage()
            );


            if (
                $e instanceof RuntimeException &&
                in_array(
                    $e->getMessage(),
                    [
                        'You have already voted in this election.',
                        'The election has closed or the voting period has ended.',
                        'The selected candidate does not exist or is inactive.',
                        'Student record was not found.'
                    ],
                    true
                )
            ) {

                $error =
                    $e->getMessage();

            } else {

                $error =
                    'Unable to save your vote. Please try again or contact the administrator.';
            }
        }
    }
}


/* =========================
   GET ACTIVE CANDIDATES
========================= */

$stmt = $conn->query(
    "SELECT id, name, course, photo
     FROM candidates
     WHERE status = 'active'
     ORDER BY name ASC"
);

$candidates =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   ESCAPE STUDENT NAME
========================= */

$studentName =
    htmlspecialchars(
        $student['name'],
        ENT_QUOTES,
        'UTF-8'
    );


/* =========================
   COUNTDOWN TIMES
========================= */

$timerStart = '';
$timerEnd = '';

if ($election) {

    $tz =
        new DateTimeZone(
            'Africa/Dar_es_Salaam'
        );

    $start =
        new DateTime(
            $election['start_time'],
            $tz
        );

    $end =
        new DateTime(
            $election['end_time'],
            $tz
        );

    $timerStart =
        $start->format('Y-m-d\TH:i:sP');

    $timerEnd =
        $end->format('Y-m-d\TH:i:sP');
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
    Student Voting | ZSH Voting System
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

:root {

    --navy: #102b52;
    --blue: #2563eb;
    --light-blue: #eaf2ff;
    --page: #f4f7fc;

}


body {

    margin: 0;
    background: var(--page);
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


.account-button {

    border: 1px solid rgba(255,255,255,.8);
    color: white;
    background: transparent;
    font-weight: 600;

}


.account-button:hover {

    background: white;
    color: #102b52;

}


.account-dropdown {

    min-width: 190px;
    border: 0;
    border-radius: 12px;
    box-shadow: 0 8px 25px #00000020;

}


.account-dropdown .dropdown-item {

    padding: 10px 15px;

}


.account-dropdown .dropdown-item:hover {

    background: #eaf2ff;
    color: #102b52;

}


.hero {

    margin-top: 28px;
    padding: 28px;
    color: white;
    border-radius: 20px;

    background:
        linear-gradient(
            120deg,
            #102b52,
            #2563eb
        );

}


.hero p {

    color: #e2eaff;

}


.countdown-card,
.status-panel {

    background: white;
    border: 1px solid #e4eaf4;
    border-radius: 18px;
    padding: 25px;
    margin-top: 24px;
    text-align: center;

    box-shadow:
        0 5px 18px #0f28500d;

}


.section-title {

    font-weight: 750;
    color: var(--navy);

}


.timer {

    color: var(--blue);
    font-weight: 800;

    font-size:
        clamp(
            25px,
            5vw,
            38px
        );

    margin: 12px 0;

}


.candidate-card {

    background: white;
    border: 2px solid transparent;
    border-radius: 18px;
    padding: 24px 18px;
    height: 100%;

    box-shadow:
        0 5px 18px #0f28500d;

    transition: .2s;

}


.candidate-card:hover {

    transform: translateY(-3px);

}


.candidate-card:has(input:checked) {

    border-color: var(--blue);
    background: #f8fbff;

}


.candidate-photo,
.candidate-placeholder {

    width: 112px;
    height: 112px;
    border-radius: 50%;

}


.candidate-photo {

    object-fit: cover;
    border: 4px solid var(--light-blue);

}


.candidate-placeholder {

    margin: auto;

    display: flex;
    align-items: center;
    justify-content: center;

    background: var(--light-blue);
    color: var(--blue);

    font-size: 40px;
    font-weight: bold;

}


.candidate-name {

    font-weight: 750;
    color: var(--navy);
    margin-top: 17px;

}


.select-label {

    display: inline-block;

    margin-top: 12px;

    padding: 9px 14px;

    background: var(--light-blue);
    color: var(--navy);

    border-radius: 10px;

}


.btn-vote {

    border: 0;
    border-radius: 12px;
    padding: 13px 35px;
    font-weight: 700;

    background:
        linear-gradient(
            100deg,
            #2563eb,
            #1746a2
        );

}


.success-panel {

    background: white;
    border: 1px solid #cdebd8;
    border-radius: 18px;
    padding: 30px 25px;
    margin-top: 24px;
    text-align: center;

    box-shadow:
        0 5px 18px #0f28500d;

}


.success-icon {

    width: 75px;
    height: 75px;

    margin:
        0 auto 16px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #d9fbe5;
    color: #16803d;

    font-size: 42px;
    font-weight: bold;

}


footer {

    color: #718096;
    padding: 28px 0;
    text-align: center;

}


@media (max-width: 576px) {

    .navbar-custom {

        padding: 10px 0;

    }

    .navbar-brand span {

        font-size: 14px;

    }

    .navbar-brand img {

        width: 45px !important;
        height: 45px !important;
        margin-right: 7px !important;

    }

    .account-button {

        font-size: 13px;

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


        <div class="d-flex align-items-center gap-2">

            <!-- ACCOUNT -->

            <div class="dropdown">

                <button
                    class="btn account-button dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >

                    <i class="bi bi-person-circle me-1"></i>

                    Account

                </button>


                <ul
                    class="dropdown-menu dropdown-menu-end account-dropdown"
                >

                    <li>

                        <a
                            class="dropdown-item"
                            href="student_account.php"
                        >

                            <i
                                class="bi bi-key-fill me-2"
                            ></i>

                            Change Password

                        </a>

                    </li>

                </ul>

            </div>


            <!-- LOGOUT -->

            <a
                href="logout.php"
                class="btn btn-danger"
                onclick="
                    return confirm(
                        'Are you sure you want to logout?'
                    );
                "
            >

                <i
                    class="bi bi-box-arrow-right me-1"
                ></i>

                Logout

            </a>

        </div>

    </div>

</nav>


<main class="container pb-4">


    <!-- HERO -->

    <section class="hero">

        <span
            class="badge bg-light text-primary mb-3"
        >
            STUDENT ELECTION PORTAL
        </span>


        <h2 class="fw-bold">

            Welcome,
            <?= $studentName ?>!

        </h2>


        <p class="mb-0">

            Make your voice count.
            Choose your preferred presidential candidate.

        </p>

    </section>


    <!-- COUNTDOWN -->

    <section class="countdown-card">

        <h5
            class="section-title"
            id="electionTitle"
        >
            Election Countdown
        </h5>


        <div
            class="timer"
            id="countdown"
        >
            Loading time...
        </div>


        <p
            class="mb-1"
            id="electionMessage"
        >
            Loading election information.
        </p>


        <?php if ($election): ?>

            <div class="text-muted mt-2">

                <?= htmlspecialchars(
                    $election['election_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>

    </section>


    <!-- ELECTION ERROR -->

    <?php if ($electionError !== ''): ?>

        <div class="alert alert-warning mt-4">

            <?= htmlspecialchars(
                $electionError,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- VOTING ERROR -->

    <?php if ($error !== ''): ?>

        <div
            class="alert alert-danger mt-4 rounded-4"
        >

            <strong>
                Voting Error:
            </strong>

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- MESSAGE -->

    <?php if ($message !== ''): ?>

        <div
            class="alert alert-info mt-4 rounded-4"
        >

            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <section class="mt-5">


    <?php if ($voteSubmitted): ?>


        <!-- ALREADY VOTED -->

        <div class="success-panel">

            <div class="success-icon">
                ✓
            </div>


            <h3
                class="section-title text-success mb-3"
            >
                Thank You!
            </h3>


            <p class="fs-5 mb-2">

                Your vote has been submitted successfully.

            </p>


            <p class="text-muted mb-4">

                Thank you for participating in this election.

            </p>


            <a
                href="live_screen.php"
                class="btn btn-primary btn-lg btn-vote"
            >

                View Live Election Screen

            </a>

        </div>


    <?php else: ?>


        <!-- CANDIDATES -->

        <div
            class="
                d-flex
                flex-wrap
                justify-content-between
                align-items-center
                mb-4
                gap-2
            "
        >

            <div>

                <h3 class="section-title mb-1">

                    Presidential Candidates

                </h3>


                <p class="text-muted mb-0">

                    Select your preferred candidate below.

                </p>

            </div>


            <span
                class="badge text-bg-primary p-2"
            >

                Active Candidates:
                <?= count($candidates) ?>

            </span>

        </div>


        <?php if (!$electionOpen): ?>

            <div
                class="alert alert-info rounded-4"
            >

                Voting is currently closed.
                You can view the candidates below.

            </div>

        <?php endif; ?>


        <?php if (!$candidates): ?>


            <div class="status-panel">

                <h5 class="section-title">

                    No Candidates Available

                </h5>


                <p class="text-muted mb-0">

                    There are no active candidates
                    in the database.
                    Please contact the election administrator.

                </p>

            </div>


        <?php else: ?>


            <form
                method="POST"
                action="student_dashboard.php"
                id="votingForm"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['vote_csrf'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >


                <div class="row g-4">


                <?php foreach ($candidates as $candidate): ?>


                    <div class="col-sm-6 col-lg-4">

                        <div
                            class="
                                candidate-card
                                text-center
                            "
                        >


                            <?php

                            $photoName =
                                !empty($candidate['photo'])
                                    ? basename(
                                        $candidate['photo']
                                    )
                                    : '';

                            $photoPath =
                                __DIR__ .
                                '/uploads/' .
                                $photoName;

                            ?>


                            <?php if (
                                $photoName !== '' &&
                                is_file($photoPath)
                            ): ?>


                                <img
                                    src="uploads/<?= rawurlencode(
                                        $photoName
                                    ) ?>"
                                    alt="Candidate photo"
                                    class="candidate-photo"
                                >


                            <?php else: ?>


                                <div
                                    class="candidate-placeholder"
                                >

                                    <?= htmlspecialchars(
                                        strtoupper(
                                            substr(
                                                $candidate['name'],
                                                0,
                                                1
                                            )
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>


                            <?php endif; ?>


                            <h5 class="candidate-name">

                                <?= htmlspecialchars(
                                    $candidate['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </h5>


                            <p class="text-muted mb-2">

                                <?= htmlspecialchars(
                                    $candidate['course'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>


                            <?php if ($electionOpen): ?>


                                <label class="select-label">

                                    <input
                                        class="form-check-input me-1"
                                        type="radio"
                                        name="candidate_id"
                                        value="<?= (int) $candidate['id'] ?>"
                                        required
                                    >

                                    Select Candidate

                                </label>


                            <?php else: ?>


                                <span
                                    class="select-label"
                                >

                                    Voting Closed

                                </span>


                            <?php endif; ?>


                        </div>

                    </div>


                <?php endforeach; ?>


                </div>


                <?php if ($electionOpen): ?>


                    <div class="text-center mt-5">

                        <button
                            type="submit"
                            id="submitVote"
                            class="
                                btn
                                btn-primary
                                btn-lg
                                btn-vote
                            "
                        >

                            Submit My Vote

                        </button>


                        <p class="text-muted small mt-3">

                            Check your selection carefully.
                            You cannot change your vote
                            after submitting it.

                        </p>

                    </div>


                <?php endif; ?>


            </form>


        <?php endif; ?>


    <?php endif; ?>


    </section>

</main>


<footer>

    <div class="container">

        ZSH Voting System
        &copy;
        <?= date('Y') ?>.
        All rights reserved.

    </div>

</footer>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<script>

const electionExists =
    <?= $election ? 'true' : 'false' ?>;

const startTime =
    <?= json_encode($timerStart) ?>;

const endTime =
    <?= json_encode($timerEnd) ?>;


function updateCountdown() {

    const display =
        document.getElementById('countdown');

    const message =
        document.getElementById('electionMessage');

    const title =
        document.getElementById('electionTitle');


    if (
        !electionExists ||
        !startTime ||
        !endTime
    ) {

        display.textContent =
            'Not Scheduled';

        title.textContent =
            'Election Countdown';

        message.textContent =
            'No election is currently scheduled.';

        return;
    }


    const now =
        Date.now();

    const start =
        new Date(startTime).getTime();

    const end =
        new Date(endTime).getTime();


    function formatRemaining(ms) {

        const days =
            Math.floor(
                ms / 86400000
            );

        const hours =
            Math.floor(
                (ms % 86400000) /
                3600000
            );

        const minutes =
            Math.floor(
                (ms % 3600000) /
                60000
            );

        const seconds =
            Math.floor(
                (ms % 60000) /
                1000
            );


        return (
            `${days}d ` +
            `${hours}h ` +
            `${minutes}m ` +
            `${seconds}s`
        );
    }


    if (now < start) {

        display.textContent =
            formatRemaining(
                start - now
            );

        title.textContent =
            'Time Until Election Starts';

        message.textContent =
            'Please wait until voting begins.';

    } else if (now < end) {

        display.textContent =
            formatRemaining(
                end - now
            );

        title.textContent =
            'Time Until Election Ends';

        message.textContent =
            'Voting is open. Please vote before time runs out.';

    } else {

        display.textContent =
            'ELECTION CLOSED';

        title.textContent =
            'Election Closed';

        message.textContent =
            'The voting period has ended.';
    }
}


updateCountdown();

setInterval(
    updateCountdown,
    1000
);


/* =========================
   REFRESH AT ELECTION BOUNDARY
========================= */

const now =
    Date.now();

const refreshTimes = [];


if (startTime) {

    const startMs =
        new Date(startTime).getTime();

    if (startMs > now) {
        refreshTimes.push(startMs);
    }
}


if (endTime) {

    const endMs =
        new Date(endTime).getTime();

    if (endMs > now) {
        refreshTimes.push(endMs);
    }
}


if (refreshTimes.length > 0) {

    const nextBoundary =
        Math.min(...refreshTimes);

    const delay =
        Math.max(
            1000,
            nextBoundary - now + 1500
        );


    setTimeout(
        function() {
            window.location.reload();
        },
        Math.min(
            delay,
            2147480000
        )
    );

} else {

    setTimeout(
        function() {
            window.location.reload();
        },
        30000
    );
}


/* =========================
   PREVENT REPEATED VOTE SUBMISSIONS
========================= */

const votingForm =
    document.getElementById(
        'votingForm'
    );


if (votingForm) {

    votingForm.addEventListener(
        'submit',
        function(event) {

            const selected =
                document.querySelector(
                    'input[name="candidate_id"]:checked'
                );


            if (!selected) {

                event.preventDefault();

                alert(
                    'Please select a candidate first.'
                );

                return;
            }


            const button =
                document.getElementById(
                    'submitVote'
                );


            if (button) {

                button.disabled =
                    true;

                button.textContent =
                    'Submitting...';
            }

        }
    );
}

</script>

</body>

</html>