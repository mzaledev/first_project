<?php
session_start();
include "db.php";

$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT * FROM admin WHERE username = ?";
$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 1) {

        $admin = mysqli_fetch_assoc($result);

        if ($password === $admin['password']) {

            $_SESSION['username'] = $admin['username'];

            // Successful login → go to admin result
            header("Location: results.php");
            exit();

        } else {
            echo "<script>
                    alert('Invalid password!');
                    window.history.back();
                  </script>";
        }

    } else {

        echo "<script>
                alert('Username not found!');
                window.history.back();
              </script>";
    }

    mysqli_stmt_close($stmt);

} else {

    echo "<script>
            alert('Database query error!');
            window.history.back();
          </script>";
}

mysqli_close($conn);
?>