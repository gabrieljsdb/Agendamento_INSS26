import { useState } from "react";
import { useLocation } from "wouter";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { AlertCircle, Loader2 } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { trpc } from "@/lib/trpc";

export default function Login() {
  const [cpf, setCpf] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [, navigate] = useLocation();

  const loginMutation = trpc.auth.loginWithSOAP.useMutation({
    onSuccess: () => {
      window.location.href = "/dashboard";
    },
    onError: (error) => {
      setError(error.message || "Erro ao fazer login");
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError("");

    // Validação básica
    if (!cpf || !password) {
      setError("CPF e senha são obrigatórios");
      return;
    }

    // ENVIAR CPF FORMATADO: Agora enviamos o valor com pontos e traços
    // O formatCPF já cuida da máscara visual, e o estado 'cpf' terá o valor formatado
    loginMutation.mutate({ cpf, password });
  };

  const formatCPF = (value: string) => {
    // Remove tudo que não é número para aplicar a máscara
    const cleaned = value.replace(/\D/g, "");
    if (cleaned.length <= 3) return cleaned;
    if (cleaned.length <= 6) return `${cleaned.slice(0, 3)}.${cleaned.slice(3)}`;
    if (cleaned.length <= 9) return `${cleaned.slice(0, 3)}.${cleaned.slice(3, 6)}.${cleaned.slice(6)}`;
    return `${cleaned.slice(0, 3)}.${cleaned.slice(3, 6)}.${cleaned.slice(6, 9)}-${cleaned.slice(9, 11)}`;
  };

  const handleCpfChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const formatted = formatCPF(e.target.value);
    setCpf(formatted);
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        {/* Logo e Título */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-indigo-600 rounded-full mb-4">
            <span className="text-3xl">📅</span>
          </div>
          <h1 className="text-3xl font-bold text-gray-900">Agendamento INSS</h1>
          <p className="text-gray-600 mt-2">OAB/SC - Sistema de Agendamento Online</p>
        </div>

        {/* Card de Login */}
        <Card className="shadow-lg">
          <CardHeader>
            <CardTitle>Entrar no Sistema</CardTitle>
            <CardDescription>Utilize suas credenciais da OAB/SC</CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              {/* Erro */}
              {error && (
                <Alert variant="destructive">
                  <AlertCircle className="h-4 w-4" />
                  <AlertDescription>{error}</AlertDescription>
                </Alert>
              )}

              {/* CPF */}
              <div className="space-y-2">
                <label htmlFor="cpf" className="block text-sm font-medium text-gray-700">
                  CPF
                </label>
                <Input
                  id="cpf"
                  type="text"
                  placeholder="000.000.000-00"
                  value={cpf}
                  onChange={handleCpfChange}
                  disabled={loginMutation.isPending}
                  className="text-lg"
                  maxLength={14}
                />
              </div>

              {/* Senha */}
              <div className="space-y-2">
                <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                  Senha
                </label>
                <Input
                  id="password"
                  type="password"
                  placeholder="Digite sua senha"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  disabled={loginMutation.isPending}
                  className="text-lg"
                />
              </div>

              {/* Botão de Login */}
              <Button
                type="submit"
                disabled={loginMutation.isPending}
                className="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 h-auto"
              >
                {loginMutation.isPending ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Entrando...
                  </>
                ) : (
                  "Entrar"
                )}
              </Button>

              {/* Informações */}
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-700">
                <p className="font-semibold mb-2">ℹ️ Informações:</p>
                <ul className="space-y-1 text-xs">
                  <li>• Use seu CPF e senha da OAB/SC</li>
                  <li>• Seus dados são sincronizados automaticamente</li>
                  <li>• Máximo 2 agendamentos por mês</li>
                </ul>
              </div>
            </form>
          </CardContent>
        </Card>

        {/* Rodapé */}
        <div className="text-center mt-6 text-sm text-gray-600">
          <p>&copy; 2026 Sistema de Agendamento INSS - OAB/SC</p>
        </div>
      </div>
    </div>
  );
}
