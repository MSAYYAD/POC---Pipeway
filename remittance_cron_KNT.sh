#! /bin/bash

################################################################################
# Script Name: remittance_cron_KNT.sh
# Developer: KEANT Technologies
# Description: Remittance invoice cron job execution script
#
# Modification Log:
# Date         Modified By              Description
# ----------   ----------------------   ----------------------------------------
# 2026-01-24   KEANT Technologies       Initial version - Added developer tag
#                                       and modification log
################################################################################

set -x 
      /usr/bin/php8.2 -f  /var/www/html/bi/dist/Invoicing/Remittance-cron_KNT23JAN2026.php
      exit