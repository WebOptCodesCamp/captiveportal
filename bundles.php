<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// --- Helper Function ---
function formatDuration($minutes) {
    if ($minutes < 60) {
        return $minutes . ' Min';
    } elseif ($minutes < 1440) {
        $hours = floor($minutes / 60);
        return $hours . ' Hour' . ($hours > 1 ? 's' : '');
    } else {
        $days = floor($minutes / 1440);
        return $days . ' Day' . ($days > 1 ? 's' : '');
    }
}

// --- Fetch Bundles ---
// Fetch all available data bundles from the database.
$result = $mysqli->query("SELECT id, name, data_limit_mb, price_kes, duration_minutes, is_unlimited, download_limit_kbps FROM bundles ORDER BY price_kes ASC");
$bundles = $result->fetch_all(MYSQLI_ASSOC);

$payment_status = $_GET['status'] ?? '';
$payment_message = '';

if ($payment_status === 'success') {
    $payment_message = 'Payment request sent successfully! Please check your phone to enter your M-Pesa PIN.';
} elseif ($payment_status === 'error') {
    $payment_message = 'Could not initiate payment. Please try again.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Data Bundle - eBAZZU Hotspot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="./tailwind.com"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Toast Message Styles */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            animation: slideInRight 0.5s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast {
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.1);
            padding: 20px;
            margin-bottom: 16px;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .toast-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.95) 0%, rgba(5, 150, 105, 0.95) 100%);
            color: white;
        }
        
        .toast-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.95) 0%, rgba(220, 38, 38, 0.95) 100%);
            color: white;
        }
        
        @keyframes fadeInUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Enhanced Bundle Card Animations */
        .bundle-hover {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        .bundle-hover:hover {
            transform: translateY(-12px) scale(1.03);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1);
        }
        
        /* Shimmer effect for popular bundle */
        .price-shimmer {
            background: linear-gradient(135deg, #1e40af 0%, #7c3aed 25%, #dc2626 50%, #f59e0b 75%, #1e40af 100%);
            background-size: 400% 400%;
            animation: shimmer 4s ease-in-out infinite;
        }
        @keyframes shimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        /* Floating particles effect */
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            animation: float-particles 8s linear infinite;
        }
        @keyframes float-particles {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100px) rotate(360deg); opacity: 0; }
        }
        
        /* Enhanced glass effect */
        .glass-premium {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }
        
        /* Gradient text animation */
        .gradient-text-animated {
            background: linear-gradient(45deg, #1e40af, #7c3aed, #dc2626, #f59e0b, #1e40af);
            background-size: 300% 300%;
            animation: gradientMove 6s ease infinite;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        @keyframes gradientMove {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        /* Enhanced eBAZZU branding colors */
        .ebazzu-gradient {
            background: linear-gradient(135deg, #1e40af 0%, #7c3aed 40%, #dc2626 80%, #f59e0b 100%);
        }
        
        .ebazzu-accent {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #ef4444 100%);
        }
        
        /* Pulsing glow effect for popular bundle */
        .glow-pulse {
            animation: glowPulse 2s ease-in-out infinite alternate;
        }
        @keyframes glowPulse {
            from { box-shadow: 0 0 20px rgba(99, 102, 241, 0.5), 0 0 40px rgba(99, 102, 241, 0.3); }
            to { box-shadow: 0 0 30px rgba(99, 102, 241, 0.8), 0 0 60px rgba(99, 102, 241, 0.5); }
        }
        
        /* Button hover effects */
        .btn-enhanced {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .btn-enhanced::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        .btn-enhanced:hover::before {
            left: 100%;
        }
        
        /* Navbar enhancement */
        .navbar-enhanced {
            background: rgba(30, 30, 30, 0.95);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Card border animation */
        .border-gradient {
            background: linear-gradient(45deg, #1e40af, #7c3aed, #dc2626, #f59e0b, #1e40af);
            background-size: 400% 400%;
            animation: borderGlow 8s ease infinite;
            padding: 2px;
            border-radius: 1rem;
        }
        @keyframes borderGlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .tier-badge {
            position: absolute;
            top: -1px;
            right: -1px;
            padding: 6px 12px;
            border-top-right-radius: 1rem;
            border-bottom-left-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: white;
            z-index: 20;
            text-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        /* Stagger animation for cards */
        .stagger-fade {
            opacity: 0;
            transform: translateY(30px);
            animation: staggerFadeIn 0.6s ease forwards;
        }
        @keyframes staggerFadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Data usage visualization */
        .data-visual {
            background: linear-gradient(135deg, #1e40af20, #7c3aed20);
            border-radius: 12px;
            padding: 16px;
            margin: 12px 0;
            border: 1px solid rgba(30, 64, 175, 0.2);
            transition: all 0.3s ease;
        }
        .data-visual:hover {
            background: linear-gradient(135deg, #1e40af30, #7c3aed30);
            transform: scale(1.02);
        }
        
        /* Float animation for decorative elements */
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }
        
        /* Pulse animation for popular badge */
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .8; }
        }
        
        /* Enhanced Bundle Card Typography and Layout */
        .bundle-title {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 0.75rem;
            color: #1f2937;
        }
        
        .bundle-price-container {
            margin: 2rem 0;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .bundle-price {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        
        .bundle-currency {
            font-size: 1.125rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .bundle-duration {
            font-size: 0.875rem;
            color: #9ca3af;
            font-weight: 500;
        }
        
        .feature-list {
            margin: 2rem 0;
            padding: 0;
            list-style: none;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
            transition: all 0.3s ease;
        }
        
        .feature-item:last-child {
            border-bottom: none;
        }
        
        .feature-item:hover {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding-left: 0.5rem;
            margin: 0 -0.5rem;
        }
        
        .feature-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .feature-text {
            font-size: 0.95rem;
            color: #374151;
            font-weight: 500;
        }
        
        .purchase-button {
            width: 100%;
            padding: 1rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .purchase-button:hover {
            transform: translateY(-2px);
        }
        
        .bundle-description {
            margin-top: 1rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 8px;
            border-left: 3px solid;
        }
        
        /* Add responsive improvements */
        @media (max-width: 768px) {
            .toast-container {
                top: 70px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
            
            .bundle-price {
                font-size: 2.5rem;
            }
            
            .bundle-title {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 640px) {
            .bundle-hover:hover {
                transform: translateY(-6px) scale(1.01);
            }
            .particles {
                display: none; /* Hide particles on mobile for better performance */
            }
            
            .toast-container {
                top: 60px;
                right: 5px;
                left: 5px;
            }

            .bundle-price {
                font-size: 2rem;
            }
            
            .bundle-title {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body class="relative min-h-screen bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50">
    <!-- Enhanced Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Floating Particles -->
    <div class="particles fixed inset-0 z-0">
        <div class="particle" style="left: 10%; animation-delay: 0s; animation-duration: 8s;"></div>
        <div class="particle" style="left: 20%; animation-delay: 1s; animation-duration: 10s;"></div>
        <div class="particle" style="left: 30%; animation-delay: 2s; animation-duration: 7s;"></div>
        <div class="particle" style="left: 40%; animation-delay: 0.5s; animation-duration: 9s;"></div>
        <div class="particle" style="left: 50%; animation-delay: 3s; animation-duration: 8s;"></div>
        <div class="particle" style="left: 60%; animation-delay: 1.5s; animation-duration: 11s;"></div>
        <div class="particle" style="left: 70%; animation-delay: 4s; animation-duration: 7s;"></div>
        <div class="particle" style="left: 80%; animation-delay: 2.5s; animation-duration: 9s;"></div>
        <div class="particle" style="left: 90%; animation-delay: 5s; animation-duration: 10s;"></div>
    </div>
    
    <!-- Enhanced Navigation Bar -->
    <nav class="navbar-enhanced glass-premium sticky top-0 z-50 px-4 py-4 sm:py-5">
        <div class="container mx-auto flex flex-col sm:flex-row justify-between items-center gap-3 sm:gap-0">
            <div class="flex items-center space-x-3">
                <div class="inline-flex items-center justify-center w-10 h-10 gradient-border rounded-full">
                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-600 to-red-600 rounded-full">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                        </svg>
                    </div>
                </div>
                <span class="text-white font-semibold text-lg">eBAZZU Hotspot</span>
            </div>
            <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-4">
                <div class="flex items-center space-x-2">
                    <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                    <span class="text-gray-300 text-xs sm:text-sm">Device: <?php echo htmlspecialchars($_SESSION['mac_address'] ?? 'Not Detected'); ?></span>
                </div>
                <a href="logout.php" class="btn btn-secondary px-3 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm bg-white bg-opacity-20 hover:bg-opacity-30 border-white border-opacity-30">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span>Disconnect</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 sm:py-12 relative z-10">
        <!-- Enhanced Header with Better Animation -->
        <div class="text-center mb-12 sm:mb-16 fade-in">
            <div class="relative inline-block mb-6">
                <div class="border-gradient">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-full">
                        <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
                <!-- Floating icons around main icon -->
                <div class="absolute -top-2 -right-2 w-6 h-6 bg-gradient-to-br from-pink-400 to-red-500 rounded-full flex items-center justify-center float-animation">
                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                </div>
                <div class="absolute -bottom-1 -left-2 w-5 h-5 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center float-animation" style="animation-delay: 1s;">
                    <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>
            <h1 class="text-3xl sm:text-5xl font-bold gradient-text-animated mb-4">eBAZZU Hotspot Premium Plans</h1>
            <p class="text-gray-600 text-base sm:text-xl max-w-3xl mx-auto leading-relaxed">Experience lightning-fast internet with our premium data bundles. Choose the perfect plan that matches your digital lifestyle and connectivity needs.</p>
            
            <!-- Quick Stats -->
            <div class="flex flex-wrap justify-center gap-4 sm:gap-8 mt-6">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <span class="text-sm text-gray-600">Instant Activation</span>
                </div>
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    <span class="text-sm text-gray-600">Secure Payment</span>
                </div>
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm text-gray-600">24/7 Support</span>
                </div>
            </div>
        </div>

        <!-- Enhanced Toast Notification -->
        <?php if ($payment_message): ?>
            <div class="toast-container">
                <div class="toast <?php echo $payment_status === 'success' ? 'toast-success' : 'toast-error'; ?>" role="alert">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <?php if ($payment_status === 'success'): ?>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    <?php else: ?>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                    <?php endif; ?>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-semibold mb-1">
                                <?php echo $payment_status === 'success' ? 'Payment Initiated!' : 'Payment Failed'; ?>
                            </h4>
                            <p class="text-sm opacity-90">
                                <?php echo htmlspecialchars($payment_message); ?>
                            </p>
                        </div>
                        <button onclick="this.parentElement.parentElement.parentElement.style.display='none'" class="flex-shrink-0 text-white hover:text-gray-200 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Enhanced Bundle Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-8 lg:gap-10 max-w-7xl mx-auto">
            <?php 
            $index = 0;
            $colors = [
                ['gradient' => 'from-blue-600 via-indigo-600 to-purple-700', 'accent' => 'from-blue-500 to-indigo-600', 'bg' => 'from-blue-50 to-indigo-50'],
                ['gradient' => 'from-purple-600 via-pink-600 to-red-600', 'accent' => 'from-purple-500 to-red-500', 'bg' => 'from-purple-50 to-red-50'],
                ['gradient' => 'from-red-600 via-orange-600 to-yellow-600', 'accent' => 'from-red-500 to-orange-500', 'bg' => 'from-red-50 to-orange-50']
            ];
            foreach ($bundles as $bundle): 
                $index++;
                $isPopular = $index == 2;
                $color = $colors[($index - 1) % 3];

                // Find the speed tier for the current bundle
                $current_tier = null;
                foreach (SPEED_TIERS as $tier) {
                    if ($bundle['download_limit_kbps'] == $tier['download_kbps']) {
                        $current_tier = $tier;
                        break;
                    }
                }
            ?>
                <div class="relative">
                    <?php if ($current_tier): ?>
                        <div class="tier-badge" style="background-color: <?php echo $current_tier['color']; ?>">
                            <?php echo htmlspecialchars($current_tier['label']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isPopular): ?>
                        <div class="border-gradient glow-pulse">
                    <?php endif; ?>
                    <div class="glass-premium bundle-card bundle-hover stagger-fade <?php echo $isPopular ? 'popular' : ''; ?> p-4 sm:p-6 md:p-8 flex flex-col rounded-2xl relative overflow-hidden" style="animation-delay: <?php echo $index * 0.15; ?>s;">
                        <!-- Background Pattern -->
                        <div class="absolute inset-0 bg-gradient-to-br <?php echo $color['bg']; ?> opacity-30"></div>
                        <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white to-transparent opacity-5"></div>
                    <?php if ($isPopular): ?>
                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 z-20">
                            <span class="inline-flex items-center px-4 py-2 rounded-full text-xs font-bold bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg pulse">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                                MOST POPULAR
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Bundle Header -->
                    <div class="relative z-10 text-center mb-4">
                        <h2 class="bundle-title"><?php echo htmlspecialchars($bundle['name']); ?></h2>
                        
                        <!-- Data Allowance Display -->
                        <div class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-r <?php echo $color['accent']; ?> rounded-full text-white font-semibold text-sm shadow-lg">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            <?php
                                if ($bundle['is_unlimited']) {
                                    echo 'Unlimited Data';
                                } elseif ($bundle['data_limit_mb'] >= 1024) {
                                    echo ($bundle['data_limit_mb'] / 1024) . ' GB Data';
                                } else {
                                    echo $bundle['data_limit_mb'] . ' MB Data';
                                }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Enhanced Pricing Section -->
                    <div class="bundle-price-container relative z-10">
                        <div class="text-center">
                            <div class="bundle-price bg-gradient-to-r <?php echo $color['gradient']; ?> bg-clip-text text-transparent <?php echo $isPopular ? 'price-shimmer' : ''; ?>">
                                KES <?php echo (int)$bundle['price_kes']; ?>
                            </div>
                            <div class="bundle-duration">
                                Valid for <?php echo formatDuration($bundle['duration_minutes']); ?>
                            </div>
                            <?php if ($isPopular): ?>
                                <div class="absolute -top-1 -right-1 w-6 h-6 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full flex items-center justify-center">
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Enhanced Feature List -->
                    <div class="relative z-10 flex-grow">
                        <ul class="feature-list">
                            <li class="feature-item group">
                                <div class="feature-icon bg-gradient-to-br <?php echo $color['accent']; ?> group-hover:scale-110 transition-transform">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                </div>
                                <div class="feature-text">
                                    <span class="font-bold text-gray-800">
                                    <?php
                                        if ($bundle['is_unlimited']) {
                                            echo 'Unlimited';
                                        } elseif ($bundle['data_limit_mb'] >= 1024) {
                                            echo ($bundle['data_limit_mb'] / 1024) . ' GB';
                                        } else {
                                            echo $bundle['data_limit_mb'] . ' MB';
                                        }
                                    ?>
                                    </span> High-Speed Data
                                </div>
                            </li>
                            <li class="feature-item group">
                                <div class="feature-icon bg-gradient-to-br from-emerald-400 to-teal-500 group-hover:scale-110 transition-transform">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="feature-text">
                                    <span class="font-bold text-gray-800"><?php echo formatDuration($bundle['duration_minutes']); ?></span> Validity
                                </div>
                            </li>
                            <li class="feature-item group">
                                <div class="feature-icon bg-gradient-to-br from-pink-400 to-red-500 group-hover:scale-110 transition-transform">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <div class="feature-text">
                                    Instant Activation
                                </div>
                            </li>
                            <li class="feature-item group">
                                <div class="feature-icon bg-gradient-to-br from-indigo-400 to-purple-500 group-hover:scale-110 transition-transform">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                </div>
                                <div class="feature-text">
                                    Secure M-Pesa Payment
                                </div>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Enhanced Buy Button -->
                    <div class="relative z-10 mt-auto">
                        <form method="POST" action="initiate_payment.php" class="space-y-4">
                            <input type="hidden" name="bundle_id" value="<?php echo $bundle['id']; ?>">
                            <input type="hidden" name="amount" value="<?php echo (int)$bundle['price_kes']; ?>">
                            
                            <!-- Bundle Description -->
                            <div class="bundle-description border-l-blue-500 text-center">
                                <p class="text-sm font-medium text-gray-600">
                                    <?php if ($index == 1): ?>
                                        💡 Perfect for light browsing, social media, and essential apps
                                    <?php elseif ($index == 2): ?>
                                        🔥 Most Popular! Ideal for streaming, video calls, and daily usage
                                    <?php else: ?>
                                        🚀 Premium plan for power users, gaming, and business needs
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <button type="submit" class="purchase-button btn-enhanced <?php echo $isPopular ? 'btn-primary glow-pulse' : 'btn-secondary'; ?> shadow-lg hover:shadow-2xl">
                                <div class="flex items-center justify-center space-x-3">
                                    <div class="w-5 h-5 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                    <span><?php echo $isPopular ? 'Get Popular Bundle' : 'Purchase Bundle'; ?></span>
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                </div>
                            </button>
                            
                            <!-- Payment Security Indicator -->
                            <div class="flex items-center justify-center space-x-2 text-xs text-gray-500">
                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                <span>Secured by M-Pesa</span>
                                <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                                <span>Instant Delivery</span>
                            </div>
                        </form>
                    </div>
                </div>
                <?php if ($isPopular): ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Enhanced Trust Section -->
        <div class="mt-20 text-center fade-in">
            <div class="glass-premium rounded-3xl p-8 sm:p-12 max-w-5xl mx-auto">
                <h3 class="text-xl sm:text-3xl font-bold gradient-text-animated mb-8">Why 10,000+ Users Choose eBAZZU Hotspot</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 sm:gap-12">
                    <div class="group">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-2xl mb-4 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h4 class="text-lg sm:text-xl font-bold text-gray-800 mb-2">⚡ Lightning Fast Speed</h4>
                        <p class="text-gray-600 leading-relaxed">Premium fiber internet with guaranteed high-speed connectivity and 99.9% uptime</p>
                        <div class="mt-3 flex justify-center">
                            <span class="bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-semibold">Up to 100 Mbps</span>
                        </div>
                    </div>
                    <div class="group">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-green-400 to-emerald-500 rounded-2xl mb-4 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h4 class="text-lg sm:text-xl font-bold text-gray-800 mb-2">🔒 Secure & Trusted</h4>
                        <p class="text-gray-600 leading-relaxed">Bank-level security with encrypted M-Pesa payments and protected browsing</p>
                        <div class="mt-3 flex justify-center">
                            <span class="bg-gradient-to-r from-green-100 to-emerald-100 text-green-700 px-3 py-1 rounded-full text-xs font-semibold">SSL Encrypted</span>
                        </div>
                    </div>
                    <div class="group">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-pink-400 to-red-500 rounded-2xl mb-4 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h4 class="text-lg sm:text-xl font-bold text-gray-800 mb-2">🎧 Premium Support</h4>
                        <p class="text-gray-600 leading-relaxed">24/7 professional technical support and customer service excellence</p>
                        <div class="mt-3 flex justify-center">
                            <span class="bg-gradient-to-r from-pink-100 to-red-100 text-pink-700 px-3 py-1 rounded-full text-xs font-semibold">Live Chat Available</span>
                        </div>
                    </div>
                </div>
                
                <!-- Enhanced Trust Indicators -->
                <div class="mt-10 pt-8 border-t border-gray-200">
                    <div class="flex flex-wrap justify-center items-center gap-6 sm:gap-8">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-sm font-medium text-gray-700">10,000+ Active Users</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="flex">
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700">4.9/5 Rating</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm font-medium text-gray-700">Trusted Since 2020</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Phone Number Modal -->
    <div id="phone-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-[100] hidden">
        <div class="glass-enhanced p-8 rounded-2xl shadow-2xl w-full max-w-sm m-4">
            <form id="phone-form" method="POST" action="initiate_payment.php">
                <input type="hidden" name="bundle_id" id="modal-bundle-id">
                <input type="hidden" name="amount" id="modal-amount">

                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Enter Your Phone Number</h2>
                    <p class="text-gray-600 mt-2">Enter your M-Pesa phone number to complete the purchase.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="phone_number" class="text-sm font-semibold text-gray-700">Phone Number</label>
                        <input type="tel" name="phone_number" id="phone_number" class="modern-input mt-1" placeholder="e.g., 0712345678" required pattern="0[7,1]\d{8}">
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-4 mt-8">
                    <button type="button" id="cancel-btn" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Pay Now</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Enhanced JavaScript for Toast and Modal Management -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const phoneModal = document.getElementById('phone-modal');
            const phoneForm = document.getElementById('phone-form');
            const cancelBtn = document.getElementById('cancel-btn');
            const modalBundleId = document.getElementById('modal-bundle-id');
            const modalAmount = document.getElementById('modal-amount');

            document.querySelectorAll('.purchase-button').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const form = this.closest('form');
                    modalBundleId.value = form.querySelector('input[name="bundle_id"]').value;
                    modalAmount.value = form.querySelector('input[name="amount"]').value;
                    phoneModal.classList.remove('hidden');
                });
            });

            cancelBtn.addEventListener('click', () => {
                phoneModal.classList.add('hidden');
            });

            phoneModal.addEventListener('click', (e) => {
                if (e.target === phoneModal) {
                    phoneModal.classList.add('hidden');
                }
            });

            // Auto-hide toast messages
            const toastContainer = document.querySelector('.toast-container');
            if (toastContainer) {
                setTimeout(() => {
                    toastContainer.style.transform = 'translateX(100%)';
                    toastContainer.style.opacity = '0';
                    setTimeout(() => toastContainer.style.display = 'none', 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
