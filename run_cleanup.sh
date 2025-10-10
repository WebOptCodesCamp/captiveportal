#!/bin/bash

# This script runs the cleanup task in an infinite loop.
# It ensures that expired users are periodically removed from the MikroTik router.

# Get the directory where the script is located, so it can be run from anywhere
echo "script for syncing bundle is started..."
cd "$(dirname "$(/data/data/com.termux/files/home/www/captiveportal)")"

while true; do
  echo "[$(date)] ==> Running cleanup and data sync..."
  
  # Execute the PHP script
  php sync_data_usage.php
  
  echo "[$(date)] ==> Task finished. Waiting for 5 minutes before next run."
  echo "----------------------------------------------------------------"
  
  # Wait for 300 seconds (5 minutes)
  sleep 60
done
