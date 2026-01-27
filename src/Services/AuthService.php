<?php

namespace App\Services;

use SoapClient;
use SoapFault;
use SimpleXMLElement;
use Psr\Log\LoggerInterface;

/**
 * Classe AuthService
 * 
 * Serviço responsável pela autenticação via SOAP com a OAB/SC
 */
class AuthService
{
    private array $config;
    private LoggerInterface $logger;
    private ?SoapClient $soapClient = null;

    /**
     * Construtor
     *
     * @param array $config Configurações SOAP
     * @param LoggerInterface $logger Logger para registro de eventos
     */
    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Obtém ou cria o cliente SOAP
     *
     * @return SoapClient
     * @throws SoapFault
     */
    private function getSoapClient(): SoapClient
    {
        if ($this->soapClient === null) {
            try {
                $options = [
                    'cache_wsdl' => $this->config['cache_wsdl'],
                    'trace' => $this->config['trace'],
                    'exceptions' => $this->config['exceptions'],
                    'connection_timeout' => $this->config['connection_timeout'],
                ];

                $this->soapClient = new SoapClient($this->config['wsdl_url'], $options);
                
                $this->logger->debug("Cliente SOAP criado com sucesso");

            } catch (SoapFault $e) {
                $this->logger->error("Erro ao criar cliente SOAP", [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);
                throw $e;
            }
        }

        return $this->soapClient;
    }

    /**
     * Autentica um usuário via SOAP
     *
     * @param string $usuario CPF ou OAB do usuário
     * @param string $senha Senha do usuário
     * @return array|null Dados do usuário autenticado ou null em caso de falha
     */
    public function authenticate(string $usuario, string $senha): ?array
    {
        try {
            $client = $this->getSoapClient();
            
            $params = [
                'Usuario' => $usuario,
                'Senha' => $senha,
            ];

            $this->logger->info("Tentativa de autenticação", ['usuario' => $usuario]);

            $result = $client->Autenticar($params);

            if (!isset($result->AutenticarResult) || !is_string($result->AutenticarResult)) {
                $this->logger->warning("Resposta SOAP inválida", ['usuario' => $usuario]);
                return null;
            }

            // Decodifica o XML da resposta
            $xml = simplexml_load_string(html_entity_decode($result->AutenticarResult));

            if ($xml === false) {
                $this->logger->error("Erro ao parsear XML da resposta SOAP", ['usuario' => $usuario]);
                return null;
            }

            // Verifica o status da autenticação
            if (!isset($xml->Status) || trim((string)$xml->Status) !== 'OK') {
                $status = isset($xml->Status) ? (string)$xml->Status : 'Desconhecido';
                $this->logger->warning("Autenticação falhou", [
                    'usuario' => $usuario,
                    'status' => $status,
                ]);
                return null;
            }

            // Extrai os dados do cadastro
            $cadastro = $xml->Cadastro;
            
            $userData = [
                'nome' => (string)($cadastro->Nome ?? ''),
                'email' => (string)($cadastro->EMail ?? ''),
                'oab' => (string)($cadastro->RegistroConselho ?? ''),
                'telefone' => (string)($cadastro->Telefone ?? $cadastro->Celular ?? ''),
                'cpf_oab' => $usuario,
            ];

            $this->logger->info("Autenticação bem-sucedida", [
                'usuario' => $usuario,
                'nome' => $userData['nome'],
            ]);

            return $userData;

        } catch (SoapFault $e) {
            $this->logger->error("Erro SOAP durante autenticação", [
                'usuario' => $usuario,
                'faultcode' => $e->faultcode,
                'faultstring' => $e->faultstring,
            ]);
            return null;

        } catch (\Exception $e) {
            $this->logger->error("Erro inesperado durante autenticação", [
                'usuario' => $usuario,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Valida o formato do CPF
     *
     * @param string $cpf CPF a ser validado
     * @return bool
     */
    public function validarCPF(string $cpf): bool
    {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }

        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Validação dos dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida o formato da OAB
     *
     * @param string $oab OAB a ser validada
     * @return bool
     */
    public function validarOAB(string $oab): bool
    {
        // Remove espaços e converte para maiúsculas
        $oab = strtoupper(trim($oab));

        // Verifica formato básico: números seguidos de /UF
        // Exemplo: 12345/SC
        return (bool)preg_match('/^\d{4,6}\/[A-Z]{2}$/', $oab);
    }
}
