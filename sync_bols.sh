#!/bin/bash
#
# BOLSearch - PDF Sync Script
# Syncs PDF files from Windows share to local Ubuntu directory
#

# Configuration
SOURCE="/mnt/win_bols/"
DEST="/srv/bols/"
LOG_FILE="/var/log/bolsearch_sync.log"

# Ensure source is mounted
if [ ! -d "$SOURCE" ]; then
    echo "[ERROR] Source directory not found: $SOURCE" | tee -a "$LOG_FILE"
    echo "[ERROR] Please ensure Windows share is mounted" | tee -a "$LOG_FILE"
    exit 1
fi

# Ensure destination exists
if [ ! -d "$DEST" ]; then
    echo "[INFO] Creating destination directory: $DEST" | tee -a "$LOG_FILE"
    sudo mkdir -p "$DEST"
    sudo chown www-data:www-data "$DEST"
fi

# Log start
echo "===================================================" | tee -a "$LOG_FILE"
echo "[INFO] BOL Sync started at $(date)" | tee -a "$LOG_FILE"
echo "[INFO] Source: $SOURCE" | tee -a "$LOG_FILE"
echo "[INFO] Destination: $DEST" | tee -a "$LOG_FILE"

# Perform rsync
rsync -av --delete \
    --exclude='*.tmp' \
    --exclude='*.partial' \
    --exclude='Thumbs.db' \
    --exclude='.DS_Store' \
    "$SOURCE" "$DEST" 2>&1 | tee -a "$LOG_FILE"

RSYNC_EXIT=$?

if [ $RSYNC_EXIT -eq 0 ]; then
    echo "[INFO] Sync completed successfully at $(date)" | tee -a "$LOG_FILE"
else
    echo "[ERROR] Sync failed with exit code $RSYNC_EXIT at $(date)" | tee -a "$LOG_FILE"
fi

echo "===================================================" | tee -a "$LOG_FILE"

exit $RSYNC_EXIT
