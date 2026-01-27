#!/bin/bash
# /var/www/html/intranet/agendamento/scripts/executar_rotina.sh

DATA_HORA=$(date +"%Y-%m-%d %H:%M:%S")
SCRIPT_DIR="/var/www/html/intranet/agendamento"
LOG_FILE="$SCRIPT_DIR/logs/rotina_diaria_$(date +%Y%m%d).log"

echo "=== INICIANDO ROTINA DIÁRIA - $DATA_HORA ===" >> "$LOG_FILE"

# Executar o PHP
/usr/bin/php "$SCRIPT_DIR/rotina_diaria.php" >> "$LOG_FILE" 2>&1

EXIT_CODE=$?
DATA_HORA_FIM=$(date +"%Y-%m-%d %H:%M:%S")

echo "=== ROTINA FINALIZADA - $DATA_HORA_FIM - Código de saída: $EXIT_CODE ===" >> "$LOG_FILE"