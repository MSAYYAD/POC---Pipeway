#!/bin/bash
################################################################################
# Script Name: Batch_R_CC_DP_KNT.sh
# Developer: KEANT Technologies
# Description: Non-interactive batch runner for Court Cost, Remittance, and
#              Direct Pay invoice scripts in 3 stages.
#              Supports per-stage logs and a combined summary log for
#              centralized monitoring.
#
#
# Provisions:
# 1) Accepts optional single bill date parameter in YYYYMMDD format.
# 2) If no date is provided, uses system current date in YYYYMMDD format.
#
# Usage:
#   ./Batch_R_CC_DP_KNT.sh [YYYYMMDD]
#
# Example:
#   ./Batch_R_CC_DP_KNT.sh 20260303
#   ./Batch_R_CC_DP_KNT.sh
#
# Cron Examples:
#   # Mon-Fri, 14:00 EST (fixed EST, no DST shift)
#   0 14 * * 1-5 /bin/bash /var/www/html/bi/dist/Invoicing/Batch_R_CC_DP_KNT.sh >> /home/pipewayweb/log/batch_r_cc_dp_knt.log 2>&1
#
#   # Manual one-time run for a specific bill date
#   /bin/bash /var/www/html/bi/dist/Invoicing/Batch_R_CC_DP_KNT.sh 20260303
#
# Stage execution behavior:
#   Stage 1 -> base date
#   Stage 2 -> base date - 1 day
#   Stage 3 -> base date - 2 days
#   Each stage writes to its own log file under /home/pipewayweb/log
#
# Log files generated:
#   1) Combined summary log:
#      /home/pipewayweb/log/batch_r_cc_dp_knt_summary.log
#   2) Stage-wise detailed logs:
#      /home/pipewayweb/log/batch_r_cc_dp_stage1_YYYYMMDD.log
#      /home/pipewayweb/log/batch_r_cc_dp_stage2_YYYYMMDD.log
#      /home/pipewayweb/log/batch_r_cc_dp_stage3_YYYYMMDD.log
# --------------------------------------------------------------------------------
# Modification Log:
# --------------------------------------------------------------------------------
# Date         Modified By          Description
# ----------   ------------------   -------------------------------------------
# 2026-03-03   KEANT Technologies   Initial batch mode script for cron use
# 2026-03-04   KEANT Technologies   Added 3-stage execution (base, -1, -2)
# 2026-03-04   KEANT Technologies   Added stage-wise log files for traceability
# 2026-03-04   KEANT Technologies   Added combined summary log for easier
#                                    monitoring, troubleshooting, and audit trail
#
################################################################################

set -u

# Runtime dependencies and script locations
PHP_BIN="/usr/bin/php8.2"
BASE_DIR="/var/www/html/bi/dist/Invoicing"

# Individual invoice cron scripts
COURT_SCRIPT="$BASE_DIR/Court_costs_cron_KNT.php"
REMIT_SCRIPT="$BASE_DIR/Remittance_cron_KNT.php"
DIRECT_SCRIPT="$BASE_DIR/Direct_pay_cron_KNT.php"
LOG_DIR="/home/pipewayweb/log"
SUMMARY_LOG_FILE="$LOG_DIR/batch_r_cc_dp_knt_summary.log"

# Summary counters for final batch status
SUCCESS_COUNT=0
FAIL_COUNT=0
FAILED_JOBS=()
LOG_FILE=""

# Ensure log directory exists before any logging attempt
mkdir -p "$LOG_DIR"

# Timestamped logger for standard output + summary log + stage log (if active)
log() {
    local message="$1"
    local line="[$(date '+%Y-%m-%d %H:%M:%S')] $message"
    echo "$line"

    echo "$line" >> "$SUMMARY_LOG_FILE"

    if [ -n "$LOG_FILE" ]; then
        echo "$line" >> "$LOG_FILE"
    fi
}

# Usage helper for invalid arguments
usage() {
    echo "Usage: $0 [YYYYMMDD]"
    echo "If no date is provided, current system date is used in YYYYMMDD format."
    exit 1
}

# Validates that billdate is exactly YYYYMMDD and a real calendar date.
# Supports both Linux date (-d) and macOS/BSD date (-j -f).
validate_billdate() {
    local billdate="$1"

    # Format check: must be 8 digits
    if [[ ! "$billdate" =~ ^[0-9]{8}$ ]]; then
        log "ERROR: Invalid date format '$billdate'. Expected YYYYMMDD."
        return 1
    fi

    # Linux/GNU date validation path
    if date -d "$billdate" "+%Y%m%d" >/dev/null 2>&1; then
        return 0
    fi

    # macOS/BSD date validation path
    if date -j -f "%Y%m%d" "$billdate" "+%Y%m%d" >/dev/null 2>&1; then
        return 0
    fi

    log "ERROR: Invalid calendar date '$billdate'."
    return 1
}

# Shifts YYYYMMDD date by N days and returns YYYYMMDD.
# Supports both Linux/GNU date and macOS/BSD date.
shift_billdate() {
    local billdate="$1"
    local offset="$2"
    local shifted_date=""

    shifted_date=$(date -d "$billdate ${offset} day" +%Y%m%d 2>/dev/null || true)
    if [ -n "$shifted_date" ]; then
        echo "$shifted_date"
        return 0
    fi

    if [ "$offset" -lt 0 ]; then
        local abs_offset=$((offset * -1))
        shifted_date=$(date -j -f "%Y%m%d" "$billdate" -v-${abs_offset}d +%Y%m%d 2>/dev/null || true)
    else
        shifted_date=$(date -j -f "%Y%m%d" "$billdate" -v+${offset}d +%Y%m%d 2>/dev/null || true)
    fi

    if [ -n "$shifted_date" ]; then
        echo "$shifted_date"
        return 0
    fi

    return 1
}

run_job() {
    local job_name="$1"
    local script_path="$2"
    local billdate="$3"

    # Execute one downstream PHP cron script with billdate argument
    log "Running $job_name for bill date: $billdate"

    "$PHP_BIN" "$script_path" "$billdate" >> "$LOG_FILE" 2>&1
    local rc=$?

    # Track success/failure for final exit code and summary
    if [ $rc -eq 0 ]; then
        log "SUCCESS: $job_name completed successfully for $billdate"
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
    else
        log "FAILED: $job_name failed for $billdate (exit code: $rc)"
        FAIL_COUNT=$((FAIL_COUNT + 1))
        FAILED_JOBS+=("$job_name")
    fi

    # Visual spacer in console output/log
    echo ""
}

# Runs one full stage (all three scripts) for a specific bill date.
# Creates a stage-specific log file.
run_stage() {
    local stage_number="$1"
    local stage_billdate="$2"

    LOG_FILE="$LOG_DIR/batch_r_cc_dp_stage${stage_number}_${stage_billdate}.log"
    : > "$LOG_FILE"

    log "========================================"
    log "Stage ${stage_number} started for bill date: $stage_billdate"
    log "Log file: $LOG_FILE"
    log "========================================"
    echo ""

    run_job "Court Cost" "$COURT_SCRIPT" "$stage_billdate"
    run_job "Remittance" "$REMIT_SCRIPT" "$stage_billdate"
    run_job "Direct Pay" "$DIRECT_SCRIPT" "$stage_billdate"

    log "Stage ${stage_number} completed for bill date: $stage_billdate"
    log "========================================"
    echo ""
}

# Accept at most one optional positional argument (billdate)
if [ $# -gt 1 ]; then
    usage
fi

# Resolve bill date from user input or fallback to current system date
if [ $# -eq 1 ]; then
    BILLDATE="$1"
    if ! validate_billdate "$BILLDATE"; then
        exit 1
    fi
    log "Using user-provided bill date: $BILLDATE"
else
    BILLDATE="$(date +%Y%m%d)"
    log "No bill date provided. Using system current date: $BILLDATE"
fi

STAGE1_DATE="$BILLDATE"
STAGE2_DATE="$(shift_billdate "$BILLDATE" -1)"
STAGE3_DATE="$(shift_billdate "$BILLDATE" -2)"

if [ -z "$STAGE2_DATE" ] || [ -z "$STAGE3_DATE" ]; then
    log "ERROR: Unable to calculate stage dates from base date: $BILLDATE"
    exit 1
fi

log "========================================"
log "Starting 3-stage batch execution"
log "Stage 1 date: $STAGE1_DATE"
log "Stage 2 date: $STAGE2_DATE"
log "Stage 3 date: $STAGE3_DATE"
log "Each stage logs to a separate file in $LOG_DIR"
log "Combined summary log file: $SUMMARY_LOG_FILE"
log "========================================"
echo ""

# Fixed execution sequence as requested (for each stage)
run_stage 1 "$STAGE1_DATE"
run_stage 2 "$STAGE2_DATE"
run_stage 3 "$STAGE3_DATE"

log "========================================"
log "Batch execution completed"
log "Successful: $SUCCESS_COUNT | Failed: $FAIL_COUNT"

# Non-zero exit if any child job failed (useful for cron alerting/monitoring)
if [ $FAIL_COUNT -gt 0 ]; then
    log "Failed jobs: ${FAILED_JOBS[*]}"
    log "========================================"
    exit 1
fi

# Zero exit when all jobs complete successfully
log "========================================"
exit 0
