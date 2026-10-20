<?php
session_start();
include "db.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];$hasVoted = false;


$check_sql = "SELECT id FROM votes WHERE username = ?";
$check_stmt = mysqli_prepare($conn,$check_sql);
if ($check_stmt) {
    mysqli_stmt_bind_param($check_stmt, "s", $username);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    if (mysqli_stmt_num_rows($check_stmt) > 0) {$hasVoted = true;
    }
    mysqli_stmt_close($check_stmt);
}


$total_voters = 0;
$voters_sql = "SELECT COUNT(*) AS total_voters FROM users";
$voters_result = mysqli_query($conn,$voters_sql);
if ($voters_result) {
    $voters_data = mysqli_fetch_assoc($voters_result);
    $total_voters = (int)$voters_data['total_voters'];
}


$total_votes = 0;
$votes_sql = "SELECT COUNT(*) AS total_votes FROM votes";
$votes_result = mysqli_query($conn,$votes_sql);
if ($votes_result) {
    $votes_data = mysqli_fetch_assoc($votes_result);
    $total_votes = (int)$votes_data['total_votes'];
}


$turnout_pct = ($total_voters > 0) ? round(($total_votes / $total_voters) * 100, 1) : 0;


$sql = "SELECT * FROM candidate";
$result = mysqli_query($conn,$sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Vote System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        .candidate-img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #198754;
        }

        .candidate-placeholder {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #6c757d;
            border: 3px solid #198754;
        }

        <?php if ($hasVoted): ?>
        .candidate-section {
            filter: blur(4px);
            pointer-events: none;
            user-select: none;
            opacity: 0.6;
        }
        <?php endif; ?>
    </style>
</head>
<body class="bg-light py-4">

<div class="container" style="max-width: 1000px;">

    <?php if ($hasVoted): ?>
        <div class="alert alert-warning text-center fw-bold mb-4 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>You have already cast your vote! Voting is now disabled for your account.
        </div>
    <?php endif; ?>

   
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <div>
                <h2 class="h4 fw-bold text-dark mb-1">Select Your Candidate</h2>
                <p class="text-muted small mb-0">Choose one candidate below and submit your vote.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="logout.php" class="btn btn-outline-danger fw-bold">
                    Logout
                </a>
            </div>
        </div>
    </div>

    
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">Eligible Voters</span>
                        <h3 class="fw-bold text-primary mb-0 mt-1"><?php echo number_format($total_voters); ?></h3>
                    </div>
                    <div class="fs-1 text-primary opacity-50">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">Total Votes Cast</span>
                        <h3 class="fw-bold text-success mb-0 mt-1"><?php echo number_format($total_votes); ?></h3>
                    </div>
                    <div class="fs-1 text-success opacity-50">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">Voter Turnout</span>
                        <h3 class="fw-bold text-info mb-0 mt-1"><?php echo $turnout_pct; ?>%</h3>
                    </div>
                    <div class="fs-1 text-info opacity-50">
                        <i class="bi bi-pie-chart-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <form action="voteprocess.php" method="POST">
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 candidate-section">
            <?php
            if ($result && mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $imagePath = isset($row['Photo']) && !empty($row['Photo']) ?$row['Photo'] : '';
                    ?>
                    <div class="col">
                        <label class="card h-100 shadow-sm border-2 user-select-none candidate-card-label" for="candidate_<?php echo $row['Cid']; ?>" style="cursor: pointer;">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <div class="text-center mb-3">
                                        <?php if (!empty($imagePath) && file_exists($imagePath)): ?>
                                            <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Candidate Image" class="candidate-img mb-2 shadow-sm">
                                        <?php else: ?>
                                            <div class="candidate-placeholder mx-auto mb-2 shadow-sm">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        <?php endif; ?>

                                        <h3 class="h5 fw-bold text-dark mb-1"><?php echo htmlspecialchars($row['Full_name']); ?></h3>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                                            <?php echo htmlspecialchars($row['Position']); ?>
                                        </span>
                                    </div>

                                    <div class="text-muted small mb-3">
                                        <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($row['Address']); ?></p>
                                        <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($row['Phone']); ?></p>
                                    </div>
                                </div>

                                <div class="pt-3 border-top d-flex align-items-center gap-2">
                                    <input 
                                        type="checkbox" 
                                        id="candidate_<?php echo $row['Cid']; ?>" 
                                        name="candidate_id[]" 
                                        value="<?php echo $row['Cid']; ?>"
                                        class="form-check-input border-success candidate-checkbox m-0"
                                        style="width: 1.3em; height: 1.3em; cursor: pointer;"
                                        <?php if ($hasVoted) echo 'disabled'; ?>
                                    >
                                    <span class="fw-bold text-success small">Tick to select candidate</span>
                                </div>
                            </div>
                        </label>
                    </div>
                    <?php
                }
            } else {
                echo "<div class='col-12'><p class='text-center text-muted'>No candidates found.</p></div>";
            }
            ?>
        </div>

        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-success btn-lg px-5 fw-bold shadow-sm" <?php if ($hasVoted) echo 'disabled style="opacity: 0.6; cursor: not-allowed;"'; ?>>
                    Submit Vote
                </button>
            </div>
        <?php endif; ?>
    </form>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/bootstrap.bundle.min.js"></script>

<script>
document.querySelectorAll('.candidate-checkbox').forEach((checkbox) => {
    checkbox.addEventListener('change', function() {
        document.querySelectorAll('.candidate-checkbox').forEach((cb) => {
            if (cb !== this) {
                cb.checked = false;
                cb.closest('.card').classList.remove('border-success', 'bg-light');
            }
        });

        const parentCard = this.closest('.card');
        if (this.checked) {
            parentCard.classList.add('border-success', 'bg-light');
        } else {
            parentCard.classList.remove('border-success', 'bg-light');
        }
    });
});
</script>

</body>
</html>
<?php mysqli_close($conn); ?>