<?php
/**
 * Log Cleanup Script
 * 
 * Cleans up old log files to prevent disk space issues.
 * Can be run manually or via cron job.
 * 
 * Usage:
 *   php scripts/cleanup-logs.php
 *   php scripts/cleanup-logs.php --days=30
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$logsDir = $baseDir . '/logs';

// Parse command line arguments
$options = getopt('', ['days::']);
$daysToKeep = (int) ($options['days'] ?? 7);

if ($daysToKeep < 1) {
    echo "❌ Error: days must be >= 1\n";
    exit(1);
}

echo "🧹 SmartSys Log Cleanup\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Logs directory: $logsDir\n";
echo "Keep logs for:  $daysToKeep days\n\n";

if (!is_dir($logsDir)) {
    echo "❌ Logs directory not found: $logsDir\n";
    exit(1);
}

$cutoffTime = time() - ($daysToKeep * 86400);
$totalSize = 0;
$cleanedSize = 0;
$filesProcessed = 0;
$filesDeleted = 0;

$files = glob($logsDir . '/*.log');

foreach ($files as $file) {
    $fileSize = filesize($file);
    $totalSize += $fileSize;
    $filesProcessed++;

    $mtime = filemtime($file);
    $age = (time() - $mtime) / 86400; // days

    if ($mtime < $cutoffTime) {
        // Delete old log files
        if (unlink($file)) {
            $cleanedSize += $fileSize;
            $filesDeleted++;
            echo "🗑️  Deleted: " . basename($file) . " (" . number_format($age, 1) . " days old, " . formatBytes($fileSize) . ")\n";
        } else {
            echo "❌ Failed to delete: " . basename($file) . "\n";
        }
    } else {
        echo "✅ Keeping: " . basename($file) . " (" . number_format($age, 1) . " days old, " . formatBytes($fileSize) . ")\n";
    }
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 Summary:\n";
echo "  Files processed: $filesProcessed\n";
echo "  Files deleted:   $filesDeleted\n";
echo "  Total size:      " . formatBytes($totalSize) . "\n";
echo "  Cleaned size:    " . formatBytes($cleanedSize) . "\n";
echo "  Space saved:     " . formatBytes($cleanedSize) . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Cleanup complete!\n";

function formatBytes($bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}
