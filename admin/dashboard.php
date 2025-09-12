<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// --- Security Check ---
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

// --- Feedback Message Handling ---
$feedback = $_SESSION['feedback'] ?? null;
unset($_SESSION['feedback']);


// --- Fetch Dashboard Data ---

// 1. Get connected devices and their usage
$devices_query = "
    SELECT
        d.mac_address,
        (SELECT t.phone_number FROM transactions t WHERE t.bundle_id = d.bundle_id ORDER BY t.created_at DESC LIMIT 1) as phone_number,
        b.name as bundle_name,
        b.data_limit_mb,
        d.data_used_mb,
        d.bundle_expiry_time,
        d.last_seen
    FROM devices d
    LEFT JOIN bundles b ON d.bundle_id = b.id
    ORDER BY d.last_seen DESC
";
$devices_result = $mysqli->query($devices_query);
$devices = $devices_result->fetch_all(MYSQLI_ASSOC);

// 2. Get recent transactions
$transactions_query = "
    SELECT
        t.id,
        t.phone_number,
        b.name as bundle_name,
        t.amount,
        t.status,
        t.mpesa_receipt_number,
        t.created_at
    FROM transactions t
    LEFT JOIN bundles b ON t.bundle_id = b.id
    ORDER BY t.created_at DESC
    LIMIT 50
";
$transactions_result = $mysqli->query($transactions_query);
$transactions = $transactions_result->fetch_all(MYSQLI_ASSOC);

// 3. Get total earnings
$earnings_query = "SELECT SUM(amount) as total_earnings FROM transactions WHERE status = 'completed'";
$earnings_result = $mysqli->query($earnings_query);
$total_earnings = $earnings_result->fetch_assoc()['total_earnings'] ?? 0;

// 4. Get all data bundles for the new management section
$bundles_query = "SELECT * FROM bundles ORDER BY price_kes ASC";
$bundles_result = $mysqli->query($bundles_query);
$bundles = $bundles_result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WiFi Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <script src="../tailwind.com"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Enhanced Dashboard Styles */
        .dashboard-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.7s;
        }
        
        .stats-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stats-card:hover::before {
            left: 100%;
        }
        
        .stat-icon-container {
            background: linear-gradient(135deg, var(--icon-color-1), var(--icon-color-2));
            border-radius: 16px;
            padding: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .stats-card:hover .stat-icon-container {
            transform: scale(1.1) rotate(5deg);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, var(--number-color-1), var(--number-color-2));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .section-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .section-card:hover {
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        }
        
        .table-enhanced {
            border-radius: 16px;
            overflow: hidden;
            border: none;
        }
        
        .table-enhanced thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .table-enhanced thead th {
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem 1.5rem;
        }
        
        .table-enhanced tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .table-enhanced tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .progress-bar {
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            background: #e5e7eb;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, #10b981, #059669);
            transition: width 0.3s ease;
            position: relative;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: progressShine 2s ease-in-out infinite;
        }
        
        @keyframes progressShine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .status-badge-enhanced {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 0.875rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .badge-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .badge-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            position: relative;
            overflow: hidden;
            margin: 0;
            padding: 4rem 2rem 5rem 2rem;
            border-radius: 0 0 4rem 4rem;
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 40% 70%, rgba(255, 255, 255, 0.08) 0%, transparent 50%);
        }
        
        .hero-section::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            animation: float 20s linear infinite;
        }
        
        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(30px, 30px) rotate(360deg); }
        }
        
        .dashboard-title {
            font-size: 3rem;
            font-weight: 900;
            color: white;
            text-align: center;
            margin-bottom: 1rem;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 10;
        }
        
        .dashboard-subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.25rem;
            font-weight: 400;
            margin-bottom: 2rem;
            position: relative;
            z-index: 10;
        }
        
        .hero-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
            position: relative;
            z-index: 10;
        }
        
        .hero-stat-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .hero-stat-card:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-4px);
        }
        
        .hero-stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            margin-bottom: 0.5rem;
        }
        
        .hero-stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }
        .modal-overlay.active .modal-content {
            transform: scale(1);
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .stats-card:hover {
                transform: translateY(-4px) scale(1.01);
            }
            
            .dashboard-title {
                font-size: 2.25rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .hero-section {
                margin: 0;
                padding: 3rem 1.5rem 4rem 1.5rem;
                border-radius: 0 0 2rem 2rem;
            }
            
            .hero-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .hero-stat-number {
                font-size: 1.5rem;
            }
        }
        
        /* Animation delays for staggered loading */
        .fade-in-up {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.6s ease forwards;
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="relative min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100">
    <!-- Enhanced Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Floating Elements -->
    <div class="fixed inset-0 z-0 pointer-events-none">
        <div class="absolute top-32 left-16 w-2 h-2 bg-indigo-400 rounded-full opacity-60 animate-pulse" style="animation-delay: 0s;"></div>
        <div class="absolute top-48 right-20 w-1 h-1 bg-purple-400 rounded-full opacity-40 animate-ping" style="animation-delay: 1s;"></div>
        <div class="absolute bottom-40 left-32 w-3 h-3 bg-pink-400 rounded-full opacity-30 animate-bounce" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-28 right-16 w-1.5 h-1.5 bg-blue-400 rounded-full opacity-50 animate-pulse" style="animation-delay: 3s;"></div>
        <div class="absolute top-64 left-1/4 w-1 h-1 bg-emerald-400 rounded-full opacity-50 animate-ping" style="animation-delay: 4s;"></div>
        <div class="absolute bottom-60 right-1/3 w-2 h-2 bg-violet-400 rounded-full opacity-40 animate-pulse" style="animation-delay: 5s;"></div>
    </div>
    
    <!-- Enhanced Navigation Bar -->
    <nav class="navbar-enhanced glass-premium sticky top-0 z-50 px-4 py-4">
        <div class="container mx-auto flex flex-col sm:flex-row justify-between items-center gap-3 sm:gap-0">
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full shadow-lg">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-500 rounded-full flex items-center justify-center">
                        <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                    </div>
                </div>
                <div>
                    <span class="text-white font-bold text-xl">Admin Dashboard</span>
                    <div class="text-gray-300 text-xs font-medium">WiFi Portal Management</div>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-4">
                <div class="flex items-center space-x-3 bg-white bg-opacity-10 rounded-full px-4 py-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-white text-sm font-semibold">Welcome back!</div>
                        <div class="text-gray-300 text-xs"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></div>
                    </div>
                </div>
                <a href="logout.php" class="btn btn-secondary px-4 py-2 text-sm bg-red-500 hover:bg-red-600 border-red-500 transition-all duration-300 hover:scale-105">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Enhanced Hero Section -->
    <div class="hero-section fade-in-up" style="animation-delay: 0.1s">
        <div class="container mx-auto text-center">
            <div class="inline-flex items-center justify-center space-x-4 mb-6">
                <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm border border-white border-opacity-30">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
            </div>
            
            <h1 class="dashboard-title">🚀 Admin Control Center</h1>
            <p class="dashboard-subtitle">Real-time monitoring and intelligent management of your WiFi portal ecosystem</p>
            
            <!-- System Status Indicator -->
            <div class="flex items-center justify-center space-x-6 mt-4 mb-6">
                <div class="flex items-center space-x-2 bg-white bg-opacity-20 rounded-full px-4 py-2 backdrop-blur-sm">
                    <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse shadow-lg"></div>
                    <span class="text-white font-semibold text-sm">System Online</span>
                </div>
                <div class="flex items-center space-x-2 bg-white bg-opacity-20 rounded-full px-4 py-2 backdrop-blur-sm">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-white font-semibold text-sm">Updated: <?php echo date('H:i:s'); ?></span>
                </div>
            </div>
            
            <!-- Hero Quick Stats -->
            <div class="hero-stats">
                <div class="hero-stat-card">
                    <div class="flex items-center justify-center mb-3">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="hero-stat-number">KES <?php echo number_format($total_earnings, 0); ?></div>
                    <div class="hero-stat-label">Total Revenue</div>
                </div>
                <div class="hero-stat-card">
                    <div class="flex items-center justify-center mb-3">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="hero-stat-number"><?php echo count($devices); ?></div>
                    <div class="hero-stat-label">Active Devices</div>
                </div>
                <div class="hero-stat-card">
                    <div class="flex items-center justify-center mb-3">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="hero-stat-number"><?php echo count($transactions); ?></div>
                    <div class="hero-stat-label">Transactions</div>
                </div>
                <div class="hero-stat-card">
                    <div class="flex items-center justify-center mb-3">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="hero-stat-number">94.2%</div>
                    <div class="hero-stat-label">Success Rate</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mx-auto px-6 py-8 relative z-10">

        <!-- Toast Notification -->
        <?php if ($feedback): ?>
            <div id="toast-notification" class="fixed top-28 right-6 z-[110] w-full max-w-xs p-4 text-gray-900 bg-white border border-gray-200 rounded-2xl shadow-lg" role="alert">
                <div class="flex items-start">
                    <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 <?php echo $feedback['type'] === 'success' ? 'text-green-500 bg-green-100' : 'text-red-500 bg-red-100'; ?> rounded-lg">
                        <?php if ($feedback['type'] === 'success'): ?>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                        <?php else: ?>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                        <?php endif; ?>
                    </div>
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p class="text-sm font-semibold text-gray-900"><?php echo $feedback['type'] === 'success' ? 'Success' : 'Error'; ?></p>
                        <p class="mt-1 text-sm text-gray-600"><?php echo htmlspecialchars($feedback['message']); ?></p>
                    </div>
                    <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex h-8 w-8" onclick="document.getElementById('toast-notification').style.display='none'">
                        <span class="sr-only">Close</span>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                    </button>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Bundle Management Section -->
        <div id="bundle-management" class="section-card p-8 mb-12 fade-in-up" style="animation-delay: 0.5s">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8">
                <div class="flex items-center space-x-4 mb-4 sm:mb-0">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-full shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Bundle Management</h2>
                        <p class="text-gray-600 text-sm">Add, edit, or remove data bundles available to users</p>
                    </div>
                </div>
                <button id="add-bundle-btn" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Add New Bundle
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="table-enhanced w-full text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="text-left">Bundle Name</th>
                            <th scope="col" class="text-center">Data Limit</th>
                            <th scope="col" class="text-center">Price (KES)</th>
                            <th scope="col" class="text-center">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Function to format duration from minutes to a readable format
                        function formatDuration($minutes) {
                            if ($minutes < 60) {
                                return $minutes . ' Min(s)';
                            } elseif ($minutes < 1440) {
                                return ($minutes / 60) . ' Hour(s)';
                            } else {
                                return ($minutes / 1440) . ' Day(s)';
                            }
                        }

                        foreach ($bundles as $bundle): 
                        ?>
                        <tr>
                            <td class="px-6 py-4 font-semibold text-gray-800"><?php echo htmlspecialchars($bundle['name']); ?></td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($bundle['is_unlimited']): ?>
                                    <span class="text-indigo-600 font-bold">Unlimited</span>
                                <?php else: ?>
                                    <?php echo $bundle['data_limit_mb'] >= 1024 ? round($bundle['data_limit_mb'] / 1024, 1) . ' GB' : $bundle['data_limit_mb'] . ' MB'; ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-indigo-600">KES <?php echo number_format($bundle['price_kes'], 2); ?></td>
                            <td class="px-6 py-4 text-center"><?php echo formatDuration($bundle['duration_minutes']); ?></td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end space-x-2">
                                    <button class="edit-btn p-2 rounded-md hover:bg-indigo-100 text-indigo-600 transition-colors"
                                            data-id="<?php echo $bundle['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($bundle['name']); ?>"
                                            data-limit="<?php echo $bundle['data_limit_mb']; ?>"
                                            data-price="<?php echo $bundle['price_kes']; ?>"
                                            data-duration-minutes="<?php echo $bundle['duration_minutes']; ?>"
                                            data-is-unlimited="<?php echo $bundle['is_unlimited']; ?>">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button class="delete-btn p-2 rounded-md hover:bg-red-100 text-red-600 transition-colors"
                                            data-id="<?php echo $bundle['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($bundle['name']); ?>">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Enhanced Devices Section -->
        <div class="section-card p-8 mb-12 fade-in-up" style="animation-delay: 0.6s">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8">
                <div class="flex items-center space-x-4 mb-4 sm:mb-0">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Connected Devices & Usage</h2>
                        <p class="text-gray-600 text-sm">Monitor real-time device activity and data consumption</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3 bg-gradient-to-r from-green-50 to-emerald-50 rounded-full px-4 py-2 border border-green-200">
                    <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-sm font-semibold text-green-700">Real-time monitoring</span>
                    <div class="w-1 h-1 bg-green-400 rounded-full"></div>
                    <span class="text-xs text-green-600"><?php echo count($devices); ?> active</span>
                </div>
            </div>
            <?php if (empty($devices)): ?>
                <!-- Empty State -->
                <div class="text-center py-16">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No Active Devices</h3>
                    <p class="text-gray-600 mb-6">No devices are currently connected to the WiFi portal</p>
                    <button onclick="location.reload()" class="btn btn-primary px-6 py-3">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Refresh
                    </button>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table-enhanced w-full text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="text-left">Device Info</th>
                                <th scope="col" class="text-left">Contact</th>
                                <th scope="col" class="text-left">Bundle Plan</th>
                                <th scope="col" class="text-left">Data Usage</th>
                                <th scope="col" class="text-left">Status</th>
                                <th scope="col" class="text-left">Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $device): ?>
                            <?php 
                                $usage_percentage = ($device['data_limit_mb'] > 0) ? ($device['data_used_mb'] / $device['data_limit_mb']) * 100 : 0;
                                $is_expiring_soon = strtotime($device['bundle_expiry_time']) < strtotime('+1 day');
                                $is_almost_exhausted = $usage_percentage > 90;
                            ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-gray-400 to-gray-600 rounded-full flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="font-mono text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($device['mac_address']); ?></div>
                                            <div class="text-xs text-gray-500">MAC Address</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($device['phone_number']); ?></div>
                                            <div class="text-xs text-gray-500">Phone Number</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-800 border border-indigo-200">
                                        <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                        <?php echo htmlspecialchars($device['bundle_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700">
                                                <?php echo number_format($device['data_used_mb'], 1); ?> / <?php echo $device['data_limit_mb']; ?> MB
                                            </span>
                                            <span class="text-xs font-semibold <?php echo $is_almost_exhausted ? 'text-red-600' : 'text-gray-500'; ?>">
                                                <?php echo number_format($usage_percentage, 1); ?>%
                                            </span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $is_almost_exhausted ? 'bg-gradient-to-r from-red-500 to-red-600' : ''; ?>" style="width: <?php echo min($usage_percentage, 100); ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($is_expiring_soon): ?>
                                        <div class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold bg-gradient-to-r from-yellow-100 to-orange-100 text-yellow-800 border border-yellow-300">
                                            <svg class="w-3 h-3 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            <div>
                                                <div>Expiring Soon</div>
                                                <div class="text-xs opacity-75"><?php echo date('M d, H:i', strtotime($device['bundle_expiry_time'])); ?></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200">
                                            <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <div>
                                                <div>Active</div>
                                                <div class="text-xs opacity-75"><?php echo date('M d, H:i', strtotime($device['bundle_expiry_time'])); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-600">
                                        <div class="font-medium"><?php echo date('M d, Y', strtotime($device['last_seen'])); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('H:i:s', strtotime($device['last_seen'])); ?></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Enhanced Transactions Section -->
        <div class="section-card p-8 fade-in-up" style="animation-delay: 0.7s">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8">
                <div class="flex items-center space-x-4 mb-4 sm:mb-0">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Recent Transactions</h2>
                        <p class="text-gray-600 text-sm">Track payment history and transaction status</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-full px-4 py-2 border border-purple-200">
                        <span class="text-sm font-semibold text-purple-700"><?php echo count($transactions); ?> total records</span>
                    </div>
                    <a href="#" class="text-sm text-indigo-600 hover:text-indigo-500 font-semibold bg-indigo-50 hover:bg-indigo-100 px-3 py-2 rounded-lg transition-all">
                        View All →
                    </a>
                </div>
            </div>
            <?php if (empty($transactions)): ?>
                <!-- Empty State -->
                <div class="text-center py-16">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No Transactions Yet</h3>
                    <p class="text-gray-600 mb-6">No payment transactions have been recorded</p>
                    <button onclick="location.reload()" class="btn btn-primary px-6 py-3">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Refresh
                    </button>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table-enhanced w-full text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="text-left">Customer</th>
                                <th scope="col" class="text-left">Bundle Plan</th>
                                <th scope="col" class="text-left">Amount</th>
                                <th scope="col" class="text-left">Payment Status</th>
                                <th scope="col" class="text-left">M-Pesa Receipt</th>
                                <th scope="col" class="text-left">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-indigo-500 rounded-full flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($tx['phone_number']); ?></div>
                                            <div class="text-xs text-gray-500">Customer</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="inline-flex items-center px-3 py-2 rounded-xl text-xs font-semibold bg-gradient-to-r from-purple-100 to-pink-100 text-purple-800 border border-purple-200">
                                        <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                        <?php echo htmlspecialchars($tx['bundle_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-lg font-bold text-gray-900">KES <?php echo number_format($tx['amount'], 0); ?></div>
                                    <div class="text-xs text-gray-500">Payment Amount</div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($tx['status'] === 'completed'): ?>
                                        <div class="status-badge-enhanced badge-success">
                                            <svg class="w-3 h-3 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            Completed
                                        </div>
                                    <?php elseif ($tx['status'] === 'failed'): ?>
                                        <div class="status-badge-enhanced badge-error">
                                            <svg class="w-3 h-3 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                            </svg>
                                            Failed
                                        </div>
                                    <?php else: ?>
                                        <div class="status-badge-enhanced badge-pending">
                                            <svg class="w-3 h-3 mr-2 animate-spin" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                            </svg>
                                            Pending
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-mono text-sm">
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($tx['mpesa_receipt_number'] ?: 'N/A'); ?></div>
                                        <div class="text-xs text-gray-500">Receipt Code</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-600">
                                        <div class="font-medium"><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('H:i:s', strtotime($tx['created_at'])); ?></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Enhanced Footer -->
        <div class="mt-16 fade-in-up" style="animation-delay: 0.8s">
            <div class="section-card p-8 text-center">
                <div class="flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                            </svg>
                        </div>
                        <div class="text-left">
                            <div class="text-lg font-bold text-gray-800">WiFi Portal Admin</div>
                            <div class="text-sm text-gray-600">&copy; <?php echo date('Y'); ?> All rights reserved</div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-6">
                        <div class="flex items-center space-x-2">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-sm text-gray-600">System Status: Online</span>
                        </div>
                        <div class="text-sm text-gray-500">
                            Last refresh: <?php echo date('H:i:s'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Bundle Modal -->
    <div id="bundle-modal" class="modal-overlay">
        <div class="modal-content">
            <form id="bundle-form" action="manage_bundle.php" method="POST">
                <input type="hidden" name="action" id="form-action" value="add">
                <input type="hidden" name="bundle_id" id="bundle-id">

                <div class="flex items-center justify-between mb-6">
                    <h2 id="modal-title" class="text-2xl font-bold text-gray-800">Add New Bundle</h2>
                    <button type="button" class="close-modal-btn p-2 rounded-full hover:bg-gray-100">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="space-y-5">
                    <div>
                        <label for="name" class="text-sm font-semibold text-gray-700">Bundle Name</label>
                        <input type="text" name="name" id="name" class="modern-input mt-1" placeholder="e.g., Daily 1GB" required>
                    </div>

                    <div>
                        <label for="data_limit_mb" class="text-sm font-semibold text-gray-700 flex justify-between">
                            <span>Data Limit (MB)</span>
                            <span id="data-limit-value" class="font-bold text-indigo-600">1024 MB / 1.0 GB</span>
                        </label>
                        <input type="range" name="data_limit_mb" id="data_limit_mb" min="10" max="10240" step="10" value="1024" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer mt-2">
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="is_unlimited" id="is_unlimited" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="is_unlimited" class="ml-2 block text-sm text-gray-900">Unlimited Bundle</label>
                    </div>

                    <div>
                        <label for="price_kes" class="text-sm font-semibold text-gray-700">Price (KES)</label>
                        <input type="number" name="price_kes" id="price_kes" class="modern-input mt-1" placeholder="e.g., 20" required min="0" step="0.01">
                    </div>

                    <div>
                        <label for="duration_value" class="text-sm font-semibold text-gray-700">Duration</label>
                        <div class="flex items-center space-x-2 mt-1">
                            <input type="number" name="duration_value" id="duration_value" class="modern-input w-2/3" placeholder="e.g., 24" required min="1">
                            <select name="duration_unit" id="duration_unit" class="modern-input w-1/3">
                                <option value="minutes">Minutes</option>
                                <option value="hours">Hours</option>
                                <option value="days" selected>Days</option>
                            </select>
                        </div>
                        <input type="hidden" name="duration_minutes" id="duration_minutes">
                    </div>
                </div>

                <div class="flex justify-end space-x-4 mt-8">
                    <button type="button" class="btn btn-secondary close-modal-btn">Cancel</button>
                    <button type="submit" id="modal-submit-btn" class="btn btn-primary">Add Bundle</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal-overlay">
        <div class="modal-content text-center">
            <form id="delete-form" action="manage_bundle.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="bundle_id" id="delete-bundle-id">

                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </div>
                
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Delete Bundle?</h2>
                <p class="text-gray-600 mb-6">Are you sure you want to delete the "<strong id="delete-bundle-name"></strong>" bundle? This action cannot be undone.</p>

                <div class="flex justify-center space-x-4">
                    <button type="button" class="btn btn-secondary close-modal-btn">Cancel</button>
                    <button type="submit" class="btn bg-red-600 hover:bg-red-700 text-white">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Toast Notification ---
            const toast = document.getElementById('toast-notification');
            if (toast) {
                setTimeout(() => {
                    toast.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => toast.style.display = 'none', 500);
                }, 5000);
            }

            // --- Modal Handling ---
            const bundleModal = document.getElementById('bundle-modal');
            const deleteModal = document.getElementById('delete-modal');
            const addBtn = document.getElementById('add-bundle-btn');
            const closeBtns = document.querySelectorAll('.close-modal-btn');

            const form = document.getElementById('bundle-form');
            const modalTitle = document.getElementById('modal-title');
            const formAction = document.getElementById('form-action');
            const bundleIdInput = document.getElementById('bundle-id');
            const modalSubmitBtn = document.getElementById('modal-submit-btn');

            const nameInput = document.getElementById('name');
            const dataLimitInput = document.getElementById('data_limit_mb');
            const priceInput = document.getElementById('price_kes');
            const durationValueInput = document.getElementById('duration_value');
            const durationUnitInput = document.getElementById('duration_unit');
            const durationMinutesInput = document.getElementById('duration_minutes');
            const isUnlimitedInput = document.getElementById('is_unlimited');

            const dataLimitValue = document.getElementById('data-limit-value');

            function openModal(modal) {
                modal.classList.add('active');
            }

            function closeModal(modal) {
                modal.classList.remove('active');
            }

            addBtn.addEventListener('click', () => {
                form.reset();
                modalTitle.textContent = 'Add New Bundle';
                modalSubmitBtn.textContent = 'Add Bundle';
                formAction.value = 'add';
                bundleIdInput.value = '';
                durationUnitInput.value = 'days'; // Default to days
                dataLimitInput.disabled = false;
                updateDataLimitDisplay();
                openModal(bundleModal);
            });

            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    modalTitle.textContent = 'Edit Bundle';
                    modalSubmitBtn.textContent = 'Save Changes';
                    formAction.value = 'edit';
                    
                    bundleIdInput.value = btn.dataset.id;
                    nameInput.value = btn.dataset.name;
                    dataLimitInput.value = btn.dataset.limit;
                    priceInput.value = btn.dataset.price;
                    isUnlimitedInput.checked = btn.dataset.isUnlimited === '1';

                    // Handle duration population
                    const totalMinutes = parseInt(btn.dataset.durationMinutes);
                    if (totalMinutes % 1440 === 0) {
                        durationValueInput.value = totalMinutes / 1440;
                        durationUnitInput.value = 'days';
                    } else if (totalMinutes % 60 === 0) {
                        durationValueInput.value = totalMinutes / 60;
                        durationUnitInput.value = 'hours';
                    } else {
                        durationValueInput.value = totalMinutes;
                        durationUnitInput.value = 'minutes';
                    }

                    dataLimitInput.disabled = isUnlimitedInput.checked;
                    updateDataLimitDisplay();
                    openModal(bundleModal);
                });
            });

            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById('delete-bundle-id').value = btn.dataset.id;
                    document.getElementById('delete-bundle-name').textContent = btn.dataset.name;
                    openModal(deleteModal);
                });
            });

            closeBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    closeModal(bundleModal);
                    closeModal(deleteModal);
                });
            });

            // --- Form Submission ---
            form.addEventListener('submit', function(e) {
                const duration = parseInt(durationValueInput.value);
                const unit = durationUnitInput.value;
                let totalMinutes = 0;

                if (unit === 'days') {
                    totalMinutes = duration * 1440;
                } else if (unit === 'hours') {
                    totalMinutes = duration * 60;
                } else {
                    totalMinutes = duration;
                }
                durationMinutesInput.value = totalMinutes;
            });

            // --- Slider Value Display ---
            function updateDataLimitDisplay() {
                const mb = parseInt(dataLimitInput.value);
                if (mb >= 1024) {
                    const gb = (mb / 1024).toFixed(1);
                    dataLimitValue.textContent = `${mb} MB / ${gb} GB`;
                } else {
                    dataLimitValue.textContent = `${mb} MB`;
                }
            }

            dataLimitInput.addEventListener('input', updateDataLimitDisplay);

            isUnlimitedInput.addEventListener('change', function() {
                dataLimitInput.disabled = this.checked;
                if (this.checked) {
                    dataLimitValue.textContent = 'Unlimited';
                } else {
                    updateDataLimitDisplay();
                }
            });
        });
    </script>
</body>
</html>
