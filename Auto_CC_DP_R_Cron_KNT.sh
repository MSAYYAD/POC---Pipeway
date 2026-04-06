#!/bin/bash

################################################################################
# Script Name: Auto_CC_DP_R_Cron.sh
# Developer: KEANT Technologies
# Description: Automated script to run Court Cost, Direct Pay, and Remittance
#              cron jobs for specified bill dates. Accepts comma-separated dates
#              in MMDD format and converts them to YYYYMMDD format for processing.
#              Supports both "Run ALL" and "Run Selected" modes.
#
# Modification Log:
# Date         Modified By              Description
# ----------   ----------------------   ----------------------------------------
# 2026-01-24   KEANT Technologies       Initial version - Created automated
#                                       script for CC and DP cron execution
# 2026-01-24   KEANT Technologies       Enhanced with menu system, added
#                                       Remittance support, and parameter passing
################################################################################

# Set current year
CURRENT_YEAR="2026"

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to display usage
usage() {
    echo -e "${YELLOW}Usage: $0${NC}"
    echo "This script will prompt you to enter bill dates in MMDD format (comma-separated)"
    echo "Example: 0102,0104,0105"
    exit 1
}

# Function to validate date format (MMDD)
validate_date() {
    local date=$1
    if [[ ! $date =~ ^[0-1][0-9][0-3][0-9]$ ]]; then
        echo -e "${RED}Error: Invalid date format '$date'. Expected MMDD format.${NC}"
        return 1
    fi
    
    local month=${date:0:2}
    local day=${date:2:2}
    
    if [ $month -lt 1 ] || [ $month -gt 12 ]; then
        echo -e "${RED}Error: Invalid month '$month' in date '$date'.${NC}"
        return 1
    fi
    
    if [ $day -lt 1 ] || [ $day -gt 31 ]; then
        echo -e "${RED}Error: Invalid day '$day' in date '$date'.${NC}"
        return 1
    fi
    
    return 0
}

# Function to run Court Cost cron
run_court_cost_cron() {
    local billdate=$1
    echo -e "${YELLOW}Running Court Cost cron for bill date: $billdate${NC}"
    
    # Run the PHP script with billdate as parameter
    /usr/bin/php8.2 /var/www/html/bi/dist/Invoicing/Court_costs_cron_KNT.php "$billdate"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Court Cost cron completed successfully for $billdate${NC}"
        return 0
    else
        echo -e "${RED}✗ Court Cost cron failed for $billdate${NC}"
        return 1
    fi
}

# Function to run Direct Pay cron
run_direct_pay_cron() {
    local billdate=$1
    echo -e "${YELLOW}Running Direct Pay cron for bill date: $billdate${NC}"
    
    # Run the PHP script with billdate as parameter
    /usr/bin/php8.2 /var/www/html/bi/dist/Invoicing/Direct_pay_cron_KNT.php "$billdate"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Direct Pay cron completed successfully for $billdate${NC}"
        return 0
    else
        echo -e "${RED}✗ Direct Pay cron failed for $billdate${NC}"
        return 1
    fi
}

# Function to run Remittance cron
run_remittance_cron() {
    local billdate=$1
    echo -e "${YELLOW}Running Remittance cron for bill date: $billdate${NC}"
    
    # Run the PHP script with billdate as parameter
    /usr/bin/php8.2 /var/www/html/bi/dist/Invoicing/Remittance_cron_KNT.php "$billdate"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Remittance cron completed successfully for $billdate${NC}"
        return 0
    else
        echo -e "${RED}✗ Remittance cron failed for $billdate${NC}"
        return 1
    fi
}

# Main script execution
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Automated Court Cost, Direct Pay, and Remittance Cron Jobs${NC}"
echo -e "${GREEN}========================================${NC}"

# Function to get and validate dates from user
get_dates() {
    echo -e "${YELLOW}Enter bill dates in MMDD format (comma-separated):${NC}"
    echo -e "${YELLOW}Example: 0102,0104,0105${NC}"
    read -p "> " input_dates

    # Check if input is empty
    if [ -z "$input_dates" ]; then
        echo -e "${RED}Error: No dates provided.${NC}"
        return 1
    fi

    # Convert comma-separated string to array
    IFS=',' read -ra DATES <<< "$input_dates"

    # Array to store valid bill dates
    VALID_DATES=()

    # Validate and convert dates
    echo ""
    echo -e "${YELLOW}Validating and converting dates...${NC}"
    for date in "${DATES[@]}"; do
        # Trim whitespace
        date=$(echo "$date" | xargs)
        
        if validate_date "$date"; then
            # Convert to YYYYMMDD format
            billdate="${CURRENT_YEAR}${date}"
            VALID_DATES+=("$billdate")
            echo -e "${GREEN}✓ $date -> $billdate${NC}"
        fi
    done

    # Check if we have any valid dates
    if [ ${#VALID_DATES[@]} -eq 0 ]; then
        echo -e "${RED}Error: No valid dates to process.${NC}"
        return 1
    fi
    
    return 0
}

# Function to run all scripts for given dates
run_all_scripts() {
    if ! get_dates; then
        return 1
    fi
    
    # Confirm before processing
    echo ""
    echo -e "${YELLOW}Ready to process ${#VALID_DATES[@]} bill date(s) for ALL scripts:${NC}"
    for billdate in "${VALID_DATES[@]}"; do
        echo "  - $billdate"
    done
    echo ""
    read -p "Continue? (y/n): " confirm

    if [[ ! $confirm =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Operation cancelled by user.${NC}"
        return 0
    fi

    # Process each bill date
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  Starting Cron Job Processing${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""

    local total_success=0
    local total_failed=0

    for billdate in "${VALID_DATES[@]}"; do
        echo -e "${GREEN}Processing bill date: $billdate${NC}"
        echo "----------------------------------------"
        
        # Run Court Cost cron
        run_court_cost_cron "$billdate"
        if [ $? -eq 0 ]; then ((total_success++)); else ((total_failed++)); fi
        echo ""
        
        # Run Direct Pay cron
        run_direct_pay_cron "$billdate"
        if [ $? -eq 0 ]; then ((total_success++)); else ((total_failed++)); fi
        echo ""
        
        # Run Remittance cron
        run_remittance_cron "$billdate"
        if [ $? -eq 0 ]; then ((total_success++)); else ((total_failed++)); fi
        echo ""
        
        echo "----------------------------------------"
        echo ""
    done

    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  All Cron Jobs Completed${NC}"
    echo -e "${GREEN}  Successful: $total_success | Failed: $total_failed${NC}"
    echo -e "${GREEN}========================================${NC}"
}

# Function to run selected script(s)
run_selected_scripts() {
    if ! get_dates; then
        return 1
    fi
    
    # Confirm dates
    echo ""
    echo -e "${YELLOW}Ready to process ${#VALID_DATES[@]} bill date(s):${NC}"
    for billdate in "${VALID_DATES[@]}"; do
        echo "  - $billdate"
    done
    echo ""
    
    while true; do
        echo ""
        echo -e "${GREEN}========================================${NC}"
        echo -e "${GREEN}  Select Script to Run${NC}"
        echo -e "${GREEN}========================================${NC}"
        echo -e "${YELLOW}1.${NC} Court Cost Cron"
        echo -e "${YELLOW}2.${NC} Direct Pay Cron"
        echo -e "${YELLOW}3.${NC} Remittance Cron"
        echo -e "${YELLOW}exit${NC} - Return to main menu"
        echo ""
        read -p "Enter your choice: " script_choice
        
        # Convert to lowercase for exit check
        script_choice_lower=$(echo "$script_choice" | tr '[:upper:]' '[:lower:]')
        
        if [[ "$script_choice_lower" == "exit" ]]; then
            echo -e "${YELLOW}Returning to main menu...${NC}"
            return 0
        fi
        
        case $script_choice in
            1)
                echo ""
                echo -e "${GREEN}Running Court Cost Cron for all dates...${NC}"
                echo "----------------------------------------"
                for billdate in "${VALID_DATES[@]}"; do
                    run_court_cost_cron "$billdate"
                    echo ""
                done
                echo "----------------------------------------"
                ;;
            2)
                echo ""
                echo -e "${GREEN}Running Direct Pay Cron for all dates...${NC}"
                echo "----------------------------------------"
                for billdate in "${VALID_DATES[@]}"; do
                    run_direct_pay_cron "$billdate"
                    echo ""
                done
                echo "----------------------------------------"
                ;;
            3)
                echo ""
                echo -e "${GREEN}Running Remittance Cron for all dates...${NC}"
                echo "----------------------------------------"
                for billdate in "${VALID_DATES[@]}"; do
                    run_remittance_cron "$billdate"
                    echo ""
                done
                echo "----------------------------------------"
                ;;
            *)
                echo -e "${RED}Invalid choice. Please select 1, 2, 3, or type 'exit'.${NC}"
                ;;
        esac
    done
}

# Main script execution
while true; do
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  Invoice Cron Job Manager${NC}"
    echo -e "${GREEN}  (Court Cost | Direct Pay | Remittance)${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""
    echo -e "${YELLOW}Select Mode:${NC}"
    echo -e "${YELLOW}1.${NC} Run ALL - Execute all scripts sequentially for each date"
    echo -e "${YELLOW}2.${NC} Run Selected - Choose specific script(s) to run"
    echo -e "${YELLOW}exit${NC} - Exit the program"
    echo ""
    read -p "Enter your choice: " mode_choice
    
    # Convert to lowercase for exit check
    mode_choice_lower=$(echo "$mode_choice" | tr '[:upper:]' '[:lower:]')
    
    if [[ "$mode_choice_lower" == "exit" ]]; then
        echo -e "${GREEN}Exiting program. Goodbye!${NC}"
        exit 0
    fi
    
    case $mode_choice in
        1)
            echo ""
            run_all_scripts
            ;;
        2)
            echo ""
            run_selected_scripts
            ;;
        *)
            echo -e "${RED}Invalid choice. Please select 1, 2, or type 'exit'.${NC}"
            ;;
    esac
done