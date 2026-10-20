<?php
session_start();
include "db.php";

if (!isset($_SESSION['username'])) {
    header("Location: adminlogin.php");
    exit();
}

$sql = "SELECT c.Cid, c.Full_name, c.Position, COUNT(v.candidate_id) AS total_votes 
        FROM candidate c 
        LEFT JOIN votes v ON c.Cid = v.candidate_id 
        GROUP BY c.Cid, c.Full_name, c.Position 
        ORDER BY total_votes DESC";

$result = mysqli_query($conn,$sql);

$candidateNames = [];$voteCounts = [];
$candidatesData = [];$grandTotalVotes = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $candidateNames[] =$row['Full_name'];
    $count = (int)$row['total_votes'];
    $voteCounts[] =$count;
    $grandTotalVotes +=$count;
    $candidatesData[] =$row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voting Results - Vote System</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #eef2f5;
            padding-bottom: 15px;
        }
        .header-bar h2 {
            margin: 0;
            color: #333;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .print-btn {
            background-color: #1ed680;
            color: white;
            border: none;
            padding: 9px 18px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.1s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .print-btn:hover {
            background-color: #12b368;
            transform: translateY(-1px);
        }
        
        /* Logout Button Styling */
        .logout-btn {
            background-color: #ffffff;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            padding: 7px 16px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .logout-btn:hover {
            background-color: #e74c3c;
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.25);
        }

        .stats-card {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: #fff;
            padding: 18px 24px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.25);
        }
        .stats-card .info-title {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.85;
            margin: 0;
        }
        .stats-card .counter-number {
            font-size: 32px;
            font-weight: bold;
            margin: 4px 0 0 0;
        }
        .stats-card .icon {
            font-size: 36px;
            opacity: 0.9;
        }

        .chart-box {
            margin: 30px 0;
            position: relative;
            padding: 15px;
            background: #fafafa;
            border-radius: 8px;
            border: 1px solid #eaeaea;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background-color: #f8f9fa;
            color: #a6251b;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f1f5f9;
        }

        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            .container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            .header-actions {
                display: none;
            }
            .chart-box {
                border: none;
                background: none;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-bar">
        <h2>Live Voting Results</h2>
        <div class="header-actions">
            <button class="print-btn" onclick="window.print()">
                🖨️ Print Results
            </button>
            <a href="logout.php" class="logout-btn">
                🚪 Logout
            </a>
        </div>
    </div>

    <div class="stats-card">
        <div>
            <p class="info-title">Total Cast Votes</p>
            <p class="counter-number" id="voterCounter">0</p>
        </div>
        <div class="icon">🗳</div>
    </div>

    <div class="chart-box">
        <canvas id="resultsChart" height="120"></canvas>
    </div>

    <table>
        <thead>
            <tr>
                <th>Candidate Name</th>
                <th>Position</th>
                <th>Total Votes</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($candidatesData as$candidate): 
                $pct =$grandTotalVotes > 0 ? round(($candidate['total_votes'] /$grandTotalVotes) * 100, 1) : 0;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($candidate['Full_name']); ?></td>
                <td><?php echo htmlspecialchars($candidate['Position']); ?></td>
                <td><strong><?php echo $candidate['total_votes']; ?></strong></td>
                <td><span><?php echo $pct; ?>%</span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function animateCounter(id, target, duration = 1500) {
    const element = document.getElementById(id);
    if (!element || target === 0) return;

    let start = 0;
    const stepTime = Math.abs(Math.floor(duration / target));
    const timer = setInterval(() => {
        start += 1;
        element.textContent = start.toLocaleString();
        if (start >= target) {
            element.textContent = target.toLocaleString();
            clearInterval(timer);
        }
    }, Math.max(stepTime, 15));
}

Chart.register(ChartDataLabels);

const labels = <?php echo json_encode($candidateNames); ?>;
const data = <?php echo json_encode($voteCounts); ?>;
const totalVotes = <?php echo $grandTotalVotes; ?>;

document.addEventListener("DOMContentLoaded", () => {
    animateCounter('voterCounter', totalVotes);
});

const generateColors = (count) => {
    const palette = [
        '#63d0ff', '#36A2EB', '#FFCE56', '#4BC0C0', 
        '#9966FF', '#FF9F40', '#E7E9ED', '#76D7C4', 
        '#F7DC6F', '#BB8FCE'
    ];
    let colors = [];
    for (let i = 0; i < count; i++) {
        colors.push(palette[i % palette.length]);
    }
    return colors;
};

const barColors = generateColors(labels.length);

const ctx = document.getElementById('resultsChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                type: 'line',
                label: 'Vote Trend',
                data: data,
                borderColor: '#2c3e50',
                borderWidth: 2,
                fill: false,
                tension: 0.3,
                pointBackgroundColor: '#d61e6b',
                pointRadius: 5,
                datalabels: { display: false }
            },
            {
                type: 'bar',
                label: 'Number of Votes',
                data: data,
                backgroundColor: barColors,
                borderColor: barColors,
                borderWidth: 1,
                borderRadius: 8,
                barPercentage: 0.6
            }
        ]
    },
    options: {
        responsive: true,
        animation: {
            duration: 1200,
            easing: 'easeOutQuart'
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let val = context.raw;
                        let percentage = totalVotes > 0 ? ((val / totalVotes) * 100).toFixed(1) : 0;
                        return ` Votes: ${val} (${percentage}%)`;
                    }
                }
            },
            datalabels: {
                anchor: 'end',
                align: 'top',
                formatter: (value) => {
                    if (totalVotes === 0) return '0%';
                    let percentage = ((value / totalVotes) * 100).toFixed(1);
                    return `${value} (${percentage}%)`;
                },
                font: {
                    weight: 'bold',
                    size: 11
                },
                color: '#444'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});
</script>

</body>
</html>
<?php mysqli_close($conn); ?>