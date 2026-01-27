#!/bin/bash
# ==============================================================================
# SCRIPT ROBUSTO PARA EXECUTAR A ROTINA DIÁRIA DE AGENDAMENTOS
# ==============================================================================

# --- CONFIGURAÇÕES ---
# Diretório raiz onde o script PHP está localizado.
# ****** CAMINHO CORRIGIDO AQUI ******
PROJECT_DIR="/var/www/html/intranet/agendamento"

# Nome do script PHP a ser executado.
PHP_SCRIPT="rotina_diaria.php"

# Caminho completo para o arquivo de log.
LOG_FILE="$PROJECT_DIR/logs/rotina_diaria.log"

# Caminho completo para o executável do PHP que você quer usar.
PHP_EXECUTABLE="/usr/bin/php8.4"
# --- FIM DAS CONFIGURAÇÕES ---


# --- LÓGICA DE EXECUÇÃO ---

# 1. Muda para o diretório do projeto. (ESSENCIAL!)
cd "$PROJECT_DIR" || {
    echo "$(date): [ERRO CRÍTICO] Não foi possível acessar o diretório do projeto: $PROJECT_DIR" >> "$LOG_FILE"
    exit 1
}

# 2. Adiciona um log de início.
echo "--------------------------------------------------------" >> "$LOG_FILE"
echo "$(date): [SHELL] Iniciando a execução de $PHP_SCRIPT" >> "$LOG_FILE"

# 3. Executa o script PHP e redireciona TODA a saída.
$PHP_EXECUTABLE $PHP_SCRIPT >> "$LOG_FILE" 2>&1

# 4. Adiciona um log de fim.
echo "$(date): [SHELL] Execução finalizada." >> "$LOG_FILE"
echo "--------------------------------------------------------" >> "$LOG_FILE"

exit 0