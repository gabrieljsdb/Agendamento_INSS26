import { useState } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import DashboardLayout from "@/components/DashboardLayout";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { AppointmentChat } from "@/components/AppointmentChat";
import { trpc } from "@/lib/trpc";
import { MessageSquare, Calendar, User, Loader2, AlertCircle } from "lucide-react";
import { Badge } from "@/components/ui/badge";

export default function Messages() {
  const { user, loading } = useAuth();
  const [selectedAppointmentId, setSelectedAppointmentId] = useState<number | null>(null);

  // Se for admin, busca todos os agendamentos do dia para ver as conversas
  // Se for usuário, busca o histórico dele
  const appointmentsQuery = user?.role === 'admin' 
    ? trpc.admin.getDailyAppointments.useQuery({ date: new Date() })
    : trpc.appointments.getHistory.useQuery({ limit: 50 });

  if (loading) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-96">
          <Loader2 className="h-8 w-8 animate-spin text-indigo-600" />
        </div>
      </DashboardLayout>
    );
  }

  const appointments = appointmentsQuery.data?.appointments || [];

  if (appointmentsQuery.isError) {
    console.error("Erro ao carregar agendamentos:", appointmentsQuery.error);
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "confirmed":
        return <Badge className="bg-green-100 text-green-800 border-green-200">Confirmado</Badge>;
      case "pending":
        return <Badge className="bg-yellow-100 text-yellow-800 border-yellow-200">Pendente</Badge>;
      case "completed":
        return <Badge className="bg-blue-100 text-blue-800 border-blue-200">Atendido</Badge>;
      case "cancelled":
        return <Badge className="bg-red-100 text-red-800 border-red-200">Cancelado</Badge>;
      default:
        return <Badge>{status}</Badge>;
    }
  };

  // Função segura para formatar data
  const formatDate = (date: any) => {
    if (!date) return "";
    try {
      const d = new Date(date);
      if (isNaN(d.getTime())) return String(date);
      return d.toLocaleDateString("pt-BR");
    } catch (e) {
      return String(date);
    }
  };

  return (
    <DashboardLayout>
      <div className="space-y-6 h-[calc(100vh-120px)]">
        <div className="flex items-center justify-between">
          <h1 className="text-3xl font-bold text-gray-900">Central de Mensagens</h1>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 h-full overflow-hidden">
          {/* Lista de Conversas */}
          <Card className="md:col-span-1 flex flex-col overflow-hidden">
            <CardHeader className="border-b">
              <CardTitle className="text-lg flex items-center gap-2">
                <MessageSquare className="h-5 w-5 text-indigo-600" />
                Conversas
              </CardTitle>
              <CardDescription>
                {user?.role === 'admin' ? 'Agendamentos de hoje' : 'Seus agendamentos'}
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 flex-1 overflow-y-auto">
              {appointmentsQuery.isLoading ? (
                <div className="flex justify-center py-8">
                  <Loader2 className="h-6 w-6 animate-spin text-indigo-600" />
                </div>
              ) : appointments.length > 0 ? (
                <div className="divide-y">
                  {appointments.map((apt: any) => (
                    <button
                      key={apt.id}
                      onClick={() => setSelectedAppointmentId(apt.id)}
                      className={`w-full text-left p-4 hover:bg-gray-50 transition-colors flex flex-col gap-2 ${
                        selectedAppointmentId === apt.id ? 'bg-indigo-50 border-l-4 border-indigo-600' : ''
                      }`}
                    >
                      <div className="flex justify-between items-start">
                        <span className="font-semibold text-sm text-gray-900">
                          {user?.role === 'admin' ? apt.userName : apt.reason}
                        </span>
                        <span className="text-[10px] text-gray-500 font-medium">
                          {formatDate(apt.appointmentDate || apt.date)}
                        </span>
                      </div>
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-1.5 text-xs text-gray-500">
                          <Calendar className="h-3 w-3" />
                          {apt.startTime?.substring(0, 5) || apt.time}
                        </div>
                        {getStatusBadge(apt.status)}
                      </div>
                    </button>
                  ))}
                </div>
              ) : (
                <div className="text-center py-12 text-gray-500 px-4">
                  <AlertCircle className="h-10 w-10 mx-auto mb-3 text-gray-300" />
                  <p className="text-sm">Nenhuma conversa encontrada.</p>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Área do Chat */}
          <Card className="md:col-span-2 flex flex-col overflow-hidden">
            {selectedAppointmentId ? (
              <div className="flex-1 flex flex-col overflow-hidden">
                <AppointmentChat 
                  appointmentId={selectedAppointmentId} 
                  isAdmin={user?.role === 'admin'} 
                />
              </div>
            ) : (
              <div className="flex-1 flex flex-col items-center justify-center text-gray-500 p-8">
                <div className="bg-gray-50 p-6 rounded-full mb-4">
                  <MessageSquare className="h-12 w-12 text-gray-300" />
                </div>
                <h3 className="text-lg font-medium text-gray-900">Selecione uma conversa</h3>
                <p className="text-sm text-center max-w-xs mt-2">
                  Escolha um agendamento na lista ao lado para visualizar as mensagens e tirar dúvidas.
                </p>
              </div>
            )}
          </Card>
        </div>
      </div>
    </DashboardLayout>
  );
}