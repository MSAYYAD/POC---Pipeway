# Pipewayweb Server - Invoice Cron Scripts Execution Guide

**Document Version:** 1.1  
**Last Updated:** March 3, 2026  
**Author:** KEANT Technologies  
**Server:** Pipewayweb  

---

## Table of Contents
1. [Overview](#overview)
2. [Script Location](#script-location)
3. [Prerequisites](#prxerequisites)
4. [Script Description](#script-description)
5. [How to Execute](#how-to-execute)
6. [Usage Examples](#usage-examples)
7. [Script Options](#script-options)
8. [Batch Mode (Cron)](#batch-mode-cron)
9. [Troubleshooting](#troubleshooting)
10. [Important Notes](#important-notes)

---

## Overview

This document provides step-by-step instructions for running the automated invoice cron job scripts on the Pipewayweb server. The scripts process Court Cost, Direct Pay, and Remittance invoices for specified bill dates.

---

## Script Location

**Server:** Pipewayweb  
**Interactive Script Path:** `/var/www/html/bi/dist/Invoicing/Auto_CC_DP_R_Cron_KNT.sh`  
**Batch Script Path:** `/var/www/html/bi/dist/Invoicing/Batch_R_CC_DP_KNT.sh`  

**Related PHP Scripts:**
- Court Cost: `/var/www/html/bi/dist/Invoicing/Court_costs_cron_KNT.php`
- Direct Pay: `/var/www/html/bi/dist/Invoicing/Direct_pay_cron_KNT.php`
- Remittance: `/var/www/html/bi/dist/Invoicing/Remittance_cron_KNT.php`

---

## Prerequisites

### 1. **Server Access**
- SSH access to Pipewayweb server
- Appropriate user permissions to execute scripts in `/var/www/html/bi/dist/Invoicing/`

### 2. **Required Software**
- Bash shell (pre-installed on server)
- PHP 8.2 (`/usr/bin/php8.2`)

### 3. **File Permissions**
Ensure the script has execute permissions:
```bash
chmod +x /var/www/html/bi/dist/Invoicing/Auto_CC_DP_R_Cron_KNT.sh
chmod +x /var/www/html/bi/dist/Invoicing/Batch_R_CC_DP_KNT.sh
```

---

## Script Description

### Purpose
The `Auto_CC_DP_R_Cron_KNT.sh` script automates the execution of invoice generation for:
- **Court Cost** invoices
- **Direct Pay** invoices  
- **Remittance** invoices

The `Batch_R_CC_DP_KNT.sh` script provides a non-interactive mode for scheduled/cron execution.

### Key Features
✅ Interactive menu-driven interface  
✅ Date validation (MMDD format)  
✅ Automatic conversion to YYYYMMDD format  
✅ Support for multiple bill dates (comma-separated)  
✅ Two execution modes: **Run ALL** or **Run Selected**  
✅ Color-coded output for easy monitoring  
✅ Success/failure tracking  
✅ Batch mode with optional `YYYYMMDD` argument (cron-friendly)

---

## How to Execute

### Step 1: Connect to Pipewayweb Server
```bash
ssh username@pipewayweb-server-ip
```

### Step 2: Navigate to Script Directory
```bash
cd /var/www/html/bi/dist/Invoicing
```

### Step 3: Run the Script
```bash
./Auto_CC_DP_R_Cron_KNT.sh
```

**Alternative (with full path):**
```bash
bash /var/www/html/bi/dist/Invoicing/Auto_CC_DP_R_Cron_KNT.sh
```

### Step 3 (Alternative): Run Batch Mode Script
```bash
./Batch_R_CC_DP_KNT.sh
```

**Alternative (with full path):**
```bash
bash /var/www/html/bi/dist/Invoicing/Batch_R_CC_DP_KNT.sh
```

**Run with specific bill date (YYYYMMDD):**
```bash
bash /var/www/html/bi/dist/Invoicing/Batch_R_CC_DP_KNT.sh 20260303
```

### Step 4: Follow Interactive Prompts
The script will guide you through:
1. **Mode Selection** (Run ALL or Run Selected)
2. **Date Entry** (MMDD format, comma-separated)
3. **Confirmation** before execution
4. **Script Selection** (if "Run Selected" mode chosen)

---

## Usage Examples

### Example 1: Run All Scripts for Single Date
```
========================================
  Invoice Cron Job Manager
  (Court Cost | Direct Pay | Remittance)
========================================

Select Mode:
1. Run ALL - Execute all scripts sequentially for each date
2. Run Selected - Choose specific script(s) to run
exit - Exit the program

Enter your choice: 1

Enter bill dates in MMDD format (comma-separated):
Example: 0102,0104,0105
> 0115

Validating and converting dates...
✓ 0115 -> 20260115

Ready to process 1 bill date(s) for ALL scripts:
  - 20260115

Continue? (y/n): y

========================================
  Starting Cron Job Processing
========================================

Processing bill date: 20260115
----------------------------------------
Running Court Cost cron for bill date: 20260115
✓ Court Cost cron completed successfully for 20260115

Running Direct Pay cron for bill date: 20260115
✓ Direct Pay cron completed successfully for 20260115

Running Remittance cron for bill date: 20260115
✓ Remittance cron completed successfully for 20260115
```

### Example 2: Run All Scripts for Multiple Dates
```
Enter your choice: 1

Enter bill dates in MMDD format (comma-separated):
Example: 0102,0104,0105
> 0115,0116,0117

Validating and converting dates...
✓ 0115 -> 20260115
✓ 0116 -> 20260116
✓ 0117 -> 20260117

Ready to process 3 bill date(s) for ALL scripts:
  - 20260115
  - 20260116
  - 20260117

Continue? (y/n): y
```

### Example 3: Run Only Court Cost Script
```
Select Mode:
Enter your choice: 2

Enter bill dates in MMDD format (comma-separated):
> 0115

========================================
  Select Script to Run
========================================
1. Court Cost Cron
2. Direct Pay Cron
3. Remittance Cron
exit - Return to main menu

Enter your choice: 1

Running Court Cost Cron for all dates...
----------------------------------------
Running Court Cost cron for bill date: 20260115
✓ Court Cost cron completed successfully for 20260115
```

---

## Script Options

### Mode 1: Run ALL
**Description:** Executes all three scripts (Court Cost, Direct Pay, Remittance) sequentially for each bill date entered.

**Use When:**
- Processing end-of-day invoices
- Running complete invoice cycle for specific dates
- Catching up on missed scheduled runs

**Execution Order per Date:**
1. Court Cost Cron
2. Direct Pay Cron
3. Remittance Cron

### Mode 2: Run Selected
**Description:** Allows you to choose which specific script(s) to run for the entered bill dates.

**Use When:**
- Re-running a specific invoice type
- Testing individual scripts
- Selective invoice processing

**Available Scripts:**
- **Option 1:** Court Cost Cron only
- **Option 2:** Direct Pay Cron only
- **Option 3:** Remittance Cron only

### Mode 3: Batch Mode (Non-Interactive)
**Script:** `Batch_R_CC_DP_KNT.sh`  
**Description:** Executes all three scripts sequentially in fixed order for one bill date.

**Execution Order:**
1. Court Cost Cron
2. Remittance Cron
3. Direct Pay Cron

**Input Handling:**
- If one parameter is passed, it must be `YYYYMMDD`.
- If no parameter is passed, system current date is used (`date +%Y%m%d`).

**Use When:**
- Scheduling from crontab
- Running daily automated processing
- Avoiding interactive prompts

---

## Date Format Requirements

### Input Format: MMDD
- **MM:** Month (01-12)
- **DD:** Day (01-31)

### Examples:
- January 15: `0115`
- February 5: `0205`
- December 31: `1231`

### Multiple Dates:
Separate with commas (no spaces):
```
0115,0116,0117
```

Or with spaces (will be trimmed):
```
0115, 0116, 0117
```

### Automatic Conversion:
The script automatically converts MMDD to YYYYMMDD format using current year (2026):
- `0115` → `20260115`
- `1231` → `20261231`

### Batch Mode Date Format: YYYYMMDD
- Used by `Batch_R_CC_DP_KNT.sh`
- Example valid input: `20260303`
- If omitted, script uses current system date

---

## Batch Mode (Cron)

Use this for fully automated execution without prompts.

### Recommended Cron (Mon-Fri, 14:00 EST)
```bash
CRON_TZ=Etc/GMT+5
0 14 * * 1-5 /bin/bash /var/www/html/bi/dist/Invoicing/Batch_R_CC_DP_KNT.sh >> /home/pipewayweb/log/batch_r_cc_dp_knt.log 2>&1
```

### Notes
- `CRON_TZ=Etc/GMT+5` keeps schedule at fixed EST (no DST shift).
- Output and errors are redirected to: `/home/pipewayweb/log/batch_r_cc_dp_knt.log`
- Exit code is non-zero if any one of the three jobs fails.

---

## Troubleshooting

### Issue 1: Permission Denied
**Error:** `bash: ./Auto_CC_DP_R_Cron_KNT.sh: Permission denied`

**Solution:**
```bash
chmod +x /var/www/html/bi/dist/Invoicing/Auto_CC_DP_R_Cron_KNT.sh
```

### Issue 2: PHP Not Found
**Error:** `/usr/bin/php8.2: No such file or directory`

**Solution:**
Check PHP installation path:
```bash
which php8.2
# or
which php
```

Update script with correct PHP path if needed.

### Issue 3: Invalid Date Format
**Error:** `Error: Invalid date format '115'. Expected MMDD format.`

**Solution:**
Ensure dates are 4 digits with leading zeros:
- ❌ `115` (incorrect)
- ✅ `0115` (correct)

**Batch mode equivalent:**
- ❌ `2026-03-03` (incorrect)
- ✅ `20260303` (correct)

### Issue 4: Script Fails to Execute
**Check:**
1. Current directory:
   ```bash
   pwd
   # Should be: /var/www/html/bi/dist/Invoicing
   ```

2. File exists:
   ```bash
   ls -la Auto_CC_DP_R_Cron_KNT.sh
   ```

3. PHP scripts exist:
   ```bash
   ls -la Court_costs_cron_KNT.php
   ls -la Direct_pay_cron_KNT.php
   ls -la Remittance_cron_KNT.php
   ```

### Issue 5: Cron Job Fails
**Check PHP Logs:**
```bash
tail -f /var/log/php8.2-fpm.log
# or
tail -f /var/log/apache2/error.log
```

**Check Script Output:**
The script shows colored output indicating success (green ✓) or failure (red ✗).

---

## Important Notes

### ⚠️ Data Integrity
- Always verify bill dates before confirming execution
- Review date ranges to ensure correct billing period
- Running scripts multiple times for the same date may create duplicate invoices

### 📅 Year Handling
- Script uses hardcoded year: **2026**
- If running in future years, update `CURRENT_YEAR` variable in script:
  ```bash
  CURRENT_YEAR="2027"  # Update as needed
  ```

### 🔄 Re-running Scripts
If you need to re-run scripts for the same date:
1. Check database for existing records
2. Consider data cleanup before re-execution
3. Use "Run Selected" mode for targeted re-runs

### 📊 Monitoring Execution
**Watch for:**
- Green checkmarks (✓) = Success
- Red X marks (✗) = Failure
- Final summary shows total successful/failed executions

### 🔍 Verification
**After Execution, Verify:**
1. Check database for generated invoices
2. Review output files (if applicable)
3. Confirm counts match expectations
4. Check logs for any warnings/errors

---

## Quick Reference

### Running All Scripts for Today's Date
```bash
cd /var/www/html/bi/dist/Invoicing
./Auto_CC_DP_R_Cron_KNT.sh
# Choose: 1 (Run ALL)
# Enter today's date in MMDD format
# Confirm with 'y'
```

### Running Only Remittance for Multiple Dates
```bash
cd /var/www/html/bi/dist/Invoicing
./Auto_CC_DP_R_Cron_KNT.sh
# Choose: 2 (Run Selected)
# Enter dates: 0115,0116,0117
# Choose: 3 (Remittance Cron)
```

### Running Batch Mode for Current System Date
```bash
cd /var/www/html/bi/dist/Invoicing
./Batch_R_CC_DP_KNT.sh
```

### Running Batch Mode for Specific Date
```bash
cd /var/www/html/bi/dist/Invoicing
./Batch_R_CC_DP_KNT.sh 20260303
```

### Exiting the Script
At any menu, type:
```
exit
```

---

## Support Contact

**For Issues or Questions:**
- Developer: KEANT Technologies
- Script Location: `/var/www/html/bi/dist/Invoicing/`
- Documentation: This file

**Before Contacting Support, Have Ready:**
1. Date(s) attempted to process
2. Error messages (if any)
3. Screenshot of script output
4. Mode selected (Run ALL or Run Selected)

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-28 | KEANT Technologies | Initial documentation created |
| 1.1 | 2026-03-03 | KEANT Technologies | Added Batch mode script usage and cron scheduling guidance |

---

**End of Documentation**
