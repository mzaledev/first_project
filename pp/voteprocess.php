<?php 
session_start(); 
include "db.php"; 

$alertMessage = "";

// Process form submission if POST request exists
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Check if user is logged in 
    if (!isset($_SESSION['username'])) { 
        $alertMessage = "Please login first.";
    } else {
        $username = $_SESSION['username']; 

        // 2. Check if candidate was selected 
        if (!isset($_POST['candidate_id']) || empty($_POST['candidate_id'])) { 
            $alertMessage = "Please select a candidate before submitting.";
        } else {
            // 3. Get candidate ID 
            $candidate_id = is_array($_POST['candidate_id']) 
                ? $_POST['candidate_id'][0] 
                : $_POST['candidate_id']; 

            // 4. Check if user already voted 
            $check_sql = "SELECT * FROM votes WHERE username = ?"; 
            $check_stmt = mysqli_prepare($conn, $check_sql); 

            if (!$check_stmt) { 
                $alertMessage = "Database error. Please try again.";
            } else {
                mysqli_stmt_bind_param($check_stmt, "s", $username); 
                mysqli_stmt_execute($check_stmt); 
                $check_result = mysqli_stmt_get_result($check_stmt); 

                if (mysqli_num_rows($check_result) > 0) { 
                    mysqli_stmt_close($check_stmt); 
                    $alertMessage = "You have already cast your vote!";
                } else {
                    mysqli_stmt_close($check_stmt); 

                    // 5. Save vote 
                    $vote_sql = "INSERT INTO votes (username, candidate_id) VALUES (?, ?)"; 
                    $vote_stmt = mysqli_prepare($conn, $vote_sql); 

                    if (!$vote_stmt) { 
                        $alertMessage = "Database query error.";
                    } else {
                        mysqli_stmt_bind_param($vote_stmt, "si", $username, $candidate_id); 

                        // 6. Execute vote 
                        if (mysqli_stmt_execute($vote_stmt)) { 
                            mysqli_stmt_close($vote_stmt); 
                            $alertMessage = "Your vote has been successfully submitted!";
                        } else { 
                            $error = mysqli_error($conn); 
                            mysqli_stmt_close($vote_stmt); 
                            $alertMessage = "Error saving vote: " . $error;
                        } 
                    }
                }
            }
        }
    }
}

// Display alert box and redirect to home.php when OK is clicked
if (!empty($alertMessage)) {
    echo "<script>
        alert(" . json_encode($alertMessage) . ");
        window.location.href = 'home.php';
    </script>";
    exit();
} else {
    // If accessed directly without form submit, redirect to home.php
    header("Location: home.php");
    exit();
}
?>