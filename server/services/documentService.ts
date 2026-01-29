import PizZip from 'pizzip';
import Docxtemplater from 'docxtemplater';
import fs from 'fs';
import path from 'path';
import { SOAPUserData } from './soapAuthService';

export class DocumentService {
  private templatePath: string;

  constructor() {
    this.templatePath = path.resolve(process.cwd(), 'server/templates/template.docx');
  }

  /**
   * Gera um documento DOCX preenchido com os dados do usuário
   */
  async generateUserDocument(userData: SOAPUserData): Promise<Buffer> {
    try {
      if (!fs.existsSync(this.templatePath)) {
        throw new Error(`Template não encontrado em: ${this.templatePath}`);
      }

      const content = fs.readFileSync(this.templatePath, 'binary');
      const zip = new PizZip(content);
      
      const doc = new Docxtemplater(zip, {
        paragraphLoop: true,
        linebreaks: true,
      });

      // Formata a data de expedição para o padrão brasileiro no documento
      const dataExpedicaoFormatada = this.formatDateToBR(userData.data_expedicao_rg);

      // Preenche os placeholders (mesmos nomes usados no PHP)
      doc.render({
        nome: userData.nome,
        email: userData.email,
        nacionalidade: 'Brasileira',
        oab: userData.oab,
        cpf: userData.cpf,
        identidade: userData.rg,
        data_expedicao: dataExpedicaoFormatada,
        local_expedicao: userData.orgao_rg,
        nome_pai: userData.nome_pai,
        nome_mae: userData.nome_mae,
        cep: userData.cep,
        endereco: userData.endereco,
        bairro: userData.bairro,
        estado: userData.estado.toUpperCase(),
        cidade: userData.cidade,
      });

      const buf = doc.getZip().generate({
        type: 'nodebuffer',
        compression: 'DEFLATE',
      });

      return buf;
    } catch (error) {
      console.error("[DocumentService] Erro ao gerar documento:", error);
      throw error;
    }
  }

  private formatDateToBR(dateStr: string): string {
    if (!dateStr) return '';
    // Se já estiver no formato YYYY-MM-DD, converte para DD/MM/YYYY
    const parts = dateStr.split('-');
    if (parts.length === 3) {
      return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dateStr;
  }
}

export const documentService = new DocumentService();
