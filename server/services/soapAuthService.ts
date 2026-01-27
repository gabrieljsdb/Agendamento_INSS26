/**
 * Serviço de Autenticação SOAP com OAB/SC
 * 
 * Este serviço integra com o webservice SOAP da OAB/SC para:
 * - Validar credenciais de CPF/OAB
 * - Recuperar dados do usuário
 * - Sincronizar informações
 */

import axios, { AxiosInstance } from "axios";

interface SOAPAuthResponse {
  success: boolean;
  cpf: string;
  oab: string;
  name: string;
  email: string;
  phone?: string;
  message?: string;
}

export class SOAPAuthService {
  private soapClient: AxiosInstance;
  private soapUrl: string;

  constructor(soapUrl: string = process.env.SOAP_AUTH_URL || "") {
    this.soapUrl = soapUrl;
    this.soapClient = axios.create({
      baseURL: soapUrl,
      timeout: 10000,
      headers: {
        "Content-Type": "text/xml; charset=utf-8",
      },
    });
  }

  /**
   * Mock de autenticação para desenvolvimento
   */
  private mockAuthenticate(cpf: string, password: string): SOAPAuthResponse {
    // Simula sucesso com dados de teste
    if (password === "demo123" || password.length > 0) {
      return {
        success: true,
        cpf: cpf,
        oab: "123456",
        name: "Usuário Teste",
        email: `usuario${cpf.slice(-4)}@oab-sc.org.br`,
        phone: "(48) 99999-9999",
      };
    }

    return {
      success: false,
      cpf: "",
      oab: "",
      name: "",
      email: "",
      message: "Credenciais inválidas",
    };
  }

  /**
   * Autentica um usuário contra o serviço SOAP da OAB/SC
   */
  async authenticate(cpf: string, password: string): Promise<SOAPAuthResponse> {
    try {
      // Validação básica
      if (!cpf || !password) {
        return {
          success: false,
          cpf: "",
          oab: "",
          name: "",
          email: "",
          message: "CPF e senha são obrigatórios",
        };
      }

      // Remove formatação do CPF
      const cleanCPF = cpf.replace(/\D/g, "");

      if (cleanCPF.length !== 11) {
        return {
          success: false,
          cpf: "",
          oab: "",
          name: "",
          email: "",
          message: "CPF inválido",
        };
      }

      // Se SOAP_AUTH_URL não está configurada, usa mock
      if (!this.soapUrl || this.soapUrl.trim() === "") {
        console.warn("[SOAPAuth] SOAP_AUTH_URL não configurada. Usando modo mock para desenvolvimento.");
        return this.mockAuthenticate(cleanCPF, password);
      }

      // Monta requisição SOAP
      const soapRequest = this.buildSOAPRequest(cleanCPF, password);

      // Faz chamada SOAP
      const response = await this.soapClient.post("", soapRequest);

      // Parse resposta SOAP
      const result = this.parseSOAPResponse(response.data);

      return result;
    } catch (error) {
      console.error("[SOAPAuth] Erro ao autenticar:", error);
      // Fallback para mock em caso de erro
      console.warn("[SOAPAuth] Usando modo mock como fallback.");
      return this.mockAuthenticate(cpf, password);
    }
  }

  /**
   * Recupera dados de um usuário pelo CPF
   */
  async getUserByCPF(cpf: string): Promise<SOAPAuthResponse> {
    try {
      const cleanCPF = cpf.replace(/\D/g, "");

      if (cleanCPF.length !== 11) {
        return {
          success: false,
          cpf: "",
          oab: "",
          name: "",
          email: "",
          message: "CPF inválido",
        };
      }

      if (!this.soapUrl || this.soapUrl.trim() === "") {
        return this.mockAuthenticate(cleanCPF, "");
      }

      // Monta requisição SOAP para buscar dados
      const soapRequest = this.buildSOAPGetUserRequest(cleanCPF);

      // Faz chamada SOAP
      const response = await this.soapClient.post("", soapRequest);

      // Parse resposta SOAP
      const result = this.parseSOAPResponse(response.data);

      return result;
    } catch (error) {
      console.error("[SOAPAuth] Erro ao buscar usuário:", error);
      return {
        success: false,
        cpf: "",
        oab: "",
        name: "",
        email: "",
        message: "Erro ao buscar dados do usuário",
      };
    }
  }

  /**
   * Valida se um CPF/OAB existe no sistema OAB/SC
   */
  async validateCPFOAB(cpf: string, oab: string): Promise<boolean> {
    try {
      const cleanCPF = cpf.replace(/\D/g, "");
      const cleanOAB = oab.replace(/\D/g, "");

      if (cleanCPF.length !== 11) {
        return false;
      }

      if (!this.soapUrl || this.soapUrl.trim() === "") {
        return true; // Mock aceita tudo
      }

      // Monta requisição SOAP para validação
      const soapRequest = this.buildSOAPValidateRequest(cleanCPF, cleanOAB);

      // Faz chamada SOAP
      const response = await this.soapClient.post("", soapRequest);

      // Parse resposta SOAP
      const result = this.parseSOAPValidateResponse(response.data);

      return result;
    } catch (error) {
      console.error("[SOAPAuth] Erro ao validar CPF/OAB:", error);
      return false;
    }
  }

  /**
   * Monta requisição SOAP para autenticação
   */
  private buildSOAPRequest(cpf: string, password: string): string {
    return `<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:auth="http://oabsc.org.br/auth">
  <soap:Body>
    <auth:Authenticate>
      <auth:cpf>${this.escapeXML(cpf)}</auth:cpf>
      <auth:password>${this.escapeXML(password)}</auth:password>
    </auth:Authenticate>
  </soap:Body>
</soap:Envelope>`;
  }

  /**
   * Monta requisição SOAP para buscar dados do usuário
   */
  private buildSOAPGetUserRequest(cpf: string): string {
    return `<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:auth="http://oabsc.org.br/auth">
  <soap:Body>
    <auth:GetUser>
      <auth:cpf>${this.escapeXML(cpf)}</auth:cpf>
    </auth:GetUser>
  </soap:Body>
</soap:Envelope>`;
  }

  /**
   * Monta requisição SOAP para validação
   */
  private buildSOAPValidateRequest(cpf: string, oab: string): string {
    return `<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:auth="http://oabsc.org.br/auth">
  <soap:Body>
    <auth:Validate>
      <auth:cpf>${this.escapeXML(cpf)}</auth:cpf>
      <auth:oab>${this.escapeXML(oab)}</auth:oab>
    </auth:Validate>
  </soap:Body>
</soap:Envelope>`;
  }

  /**
   * Parse resposta SOAP de autenticação
   */
  private parseSOAPResponse(soapResponse: string): SOAPAuthResponse {
    try {
      // Extrai valores da resposta SOAP (implementação simplificada)
      const successMatch = soapResponse.match(/<auth:success>(.*?)<\/auth:success>/);
      const cpfMatch = soapResponse.match(/<auth:cpf>(.*?)<\/auth:cpf>/);
      const oabMatch = soapResponse.match(/<auth:oab>(.*?)<\/auth:oab>/);
      const nameMatch = soapResponse.match(/<auth:name>(.*?)<\/auth:name>/);
      const emailMatch = soapResponse.match(/<auth:email>(.*?)<\/auth:email>/);
      const phoneMatch = soapResponse.match(/<auth:phone>(.*?)<\/auth:phone>/);
      const messageMatch = soapResponse.match(/<auth:message>(.*?)<\/auth:message>/);

      const success = successMatch ? successMatch[1].toLowerCase() === "true" : false;

      return {
        success,
        cpf: cpfMatch ? this.unescapeXML(cpfMatch[1]) : "",
        oab: oabMatch ? this.unescapeXML(oabMatch[1]) : "",
        name: nameMatch ? this.unescapeXML(nameMatch[1]) : "",
        email: emailMatch ? this.unescapeXML(emailMatch[1]) : "",
        phone: phoneMatch ? this.unescapeXML(phoneMatch[1]) : undefined,
        message: messageMatch ? this.unescapeXML(messageMatch[1]) : undefined,
      };
    } catch (error) {
      console.error("[SOAPAuth] Erro ao fazer parse da resposta SOAP:", error);
      return {
        success: false,
        cpf: "",
        oab: "",
        name: "",
        email: "",
        message: "Erro ao processar resposta do servidor",
      };
    }
  }

  /**
   * Parse resposta SOAP de validação
   */
  private parseSOAPValidateResponse(soapResponse: string): boolean {
    try {
      const validMatch = soapResponse.match(/<auth:valid>(.*?)<\/auth:valid>/);
      return validMatch ? validMatch[1].toLowerCase() === "true" : false;
    } catch (error) {
      console.error("[SOAPAuth] Erro ao fazer parse da validação SOAP:", error);
      return false;
    }
  }

  /**
   * Escapa caracteres especiais para XML
   */
  private escapeXML(str: string): string {
    return str
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&apos;");
  }

  /**
   * Remove escape de caracteres XML
   */
  private unescapeXML(str: string): string {
    return str
      .replace(/&apos;/g, "'")
      .replace(/&quot;/g, '"')
      .replace(/&gt;/g, ">")
      .replace(/&lt;/g, "<")
      .replace(/&amp;/g, "&");
  }
}

// Exporta instância singleton
export const soapAuthService = new SOAPAuthService();
