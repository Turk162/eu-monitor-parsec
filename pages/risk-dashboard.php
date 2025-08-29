<?php
// ===================================================================
//  RISK DASHBOARD
// ===================================================================

// ===================================================================
//  PAGE CONFIGURATION
// ===================================================================
$page_title = 'Risk Dashboard - EU Project Manager';
$page_css_path = '../assets/css/pages/project-detail.css'; // Reusing existing styles
$page_js_path = ''; // No custom JS for now

// ===================================================================
//  INCLUDE HEADER & DATABASE
// ===================================================================
require_once '../includes/header.php';

// We assume $user_id and $user_role are available from header.php
// We also assume the project ID is 1 for this example. 
// In a real scenario, this would be dynamic (e.g., $_GET['project_id']).
$project_id_to_view = 28; 

// ===================================================================
//  FETCH RISK DATA
// ===================================================================
$database = new Database();
$conn = $database->connect();

$sql = "SELECT 
            r.risk_code,
            r.category,
            r.description,
            pr.current_probability,
            pr.current_impact,
            pr.current_score,
            pr.status,
            pr.last_updated
        FROM project_risks pr
        JOIN risks r ON pr.risk_id = r.id
        WHERE pr.project_id = :project_id
        ORDER BY pr.current_score DESC, r.risk_code ASC";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':project_id', $project_id_to_view, PDO::PARAM_INT);
$stmt->execute();
$risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get badge color based on status
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Critical':
            return 'badge-danger';
        case 'High':
            return 'badge-warning';
        case 'Medium':
            return 'badge-info';
        case 'Low':
            return 'badge-success';
        default:
            return 'badge-secondary';
    }
}
?>

<body class="">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

        <div class="content">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="nc-icon nc-alert-circle-i text-danger"></i>
                                Project Risk Dashboard
                            </h4>
                            <p class="category">Real-time overview of project risks (Project ID: <?php echo $project_id_to_view; ?>)</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="text-primary">
                                        <th>Code</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th class="text-center">Probability</th>
                                        <th class="text-center">Impact</th>
                                        <th class="text-center">Score</th>
                                        <th class="text-center">Status</th>
                                        <th>Last Updated</th>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($risks)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No risk data available for this project.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($risks as $risk): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($risk['risk_code']); ?></td>
                                                    <td><?php echo htmlspecialchars($risk['category']); ?></td>
                                                    <td><?php echo htmlspecialchars($risk['description']); ?></td>
                                                    <td class="text-center"><?php echo htmlspecialchars($risk['current_probability']); ?></td>
                                                    <td class="text-center"><?php echo htmlspecialchars($risk['current_impact']); ?></td>
                                                    <td class="text-center font-weight-bold"><?php echo htmlspecialchars($risk['current_score']); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge <?php echo getStatusBadgeClass($risk['status']); ?>">
                                                            <?php echo htmlspecialchars($risk['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date("Y-m-d H:i", strtotime($risk['last_updated'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../includes/footer.php'; ?>
    </div>
</body>

</html>
