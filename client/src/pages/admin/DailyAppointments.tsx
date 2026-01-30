import { useAuth } from "@/_core/hooks/useAuth";
import DashboardLayout from "@/components/DashboardLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { trpc } from "@/lib/trpc";
import { Calendar, Clock, Loader2, User, FileText, CheckCircle, XCircle, Clock4, UserMinus } from "lucide-react";
import { toast } from "sonner";
import { useState } from "react";
import { useLocation } from "wouter";

export default function DailyAppointments() {
  const { user, loading } = useAuth();
  const [, navigate] = useLocation();
  const [selectedDate, setSelectedDate] = useState(new Date());
  
  const dailyQuery = trpc.admin.getDailyAppointments.useQuery({ date: selectedDate });
  const updateStatusMutation = trpc.admin.updateStatus.useMutation({
    onSuccess: () => {
      toast.success("Status atualizado");
      dailyQuery.refetch();
    },
    onError: (error) => {
      toast.error(error.message || "Erro ao atualizar status");
    }
  });

  if (loading) return null;
  if (!user || user.role !== 'admin') {
    navigate("/dashboard");
    return null;
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "confirmed":
        return <Badge className="bg-green-100 text-green-800 border-green-200">Confirmado</Badge>;
      case "completed":
        return <Badge className="bg-blue-100 text-blue-800 border-blue-200">Atendido</Badge>;
      case "cancelled":
        return <Badge className="bg-red-100 text-red-800 border-red-200">Cancelado</Badge>;
      case "no_show":
        return <Badge className="bg-gray-100 text-gray-800 border-gray-200">Não Compareceu</Badge>;
      case "pending":
        return <Badge className="bg-yellow-100 text-yellow-800 border-yellow-200">Aguardando</Badge>;
      default:
        return <Badge>{status}</Badge>;
    }
  };

  const handleStatusUpdate = (id: number, status: "pending" | "confirmed" | "completed" | "cancelled" | "no_show") => {
    updateStatusMutation.mutate({ appointmentId: id, status });
  };

  return (
    <DashboardLayout>
      <div className="space-y-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Atendimentos do Dia</h1>
            <p className="text-gray-600 mt-1">Gerencie os atendimentos para {selectedDate.toLocaleDateString("pt-BR")}</p>
          </div>
          <div className="flex gap-2">
             <Button variant="outline" onClick={() => setSelectedDate(new Date())}>Hoje</Button>
             <input 
               type="date" 
               className="border rounded-md px-3 py-2 text-sm"
               value={selectedDate.toISOString().split('T')[0]}
               onChange={(e) => setSelectedDate(new Date(e.target.value + 'T12:00:00'))}
             />
          </div>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Lista de Atendimentos</CardTitle>
          </CardHeader>
          <CardContent>
            {dailyQuery.isLoading ? (
              <div className="flex justify-center py-8">
                <Loader2 className="h-8 w-8 animate-spin text-indigo-600" />
              </div>
            ) : dailyQuery.data?.appointments && dailyQuery.data.appointments.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-sm text-left">
                  <thead className="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                      <th className="px-4 py-3">Hora</th>
                      <th className="px-4 py-3">Nome</th>
                      <th className="px-4 py-3">CPF / OAB</th>
                      <th className="px-4 py-3">Motivo</th>
                      <th className="px-4 py-3">Status</th>
                      <th className="px-4 py-3">Ações</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {dailyQuery.data.appointments.map((apt) => (
                      <tr key={apt.id} className="bg-white hover:bg-gray-50">
                        <td className="px-4 py-3 font-semibold text-indigo-600">
                          {apt.startTime.substring(0, 5)}
                        </td>
                        <td className="px-4 py-3 font-medium">
                          {apt.userName}
                        </td>
                        <td className="px-4 py-3 text-gray-600">
                          <div>{apt.userCpf}</div>
                          <div className="text-xs text-gray-400">{apt.userOab}</div>
                        </td>
                        <td className="px-4 py-3">{apt.reason}</td>
                        <td className="px-4 py-3">{getStatusBadge(apt.status)}</td>
                        <td className="px-4 py-3">
                          <div className="flex flex-wrap gap-1">
                            <Button 
                              size="xs" 
                              variant="ghost" 
                              className="h-8 px-2 text-green-600 hover:text-green-700 hover:bg-green-50"
                              onClick={() => handleStatusUpdate(apt.id, "completed")}
                              title="Marcar como Atendido"
                            >
                              <CheckCircle className="h-4 w-4" />
                            </Button>
                            <Button 
                              size="xs" 
                              variant="ghost" 
                              className="h-8 px-2 text-red-600 hover:text-red-700 hover:bg-red-50"
                              onClick={() => handleStatusUpdate(apt.id, "no_show")}
                              title="Não Compareceu"
                            >
                              <UserMinus className="h-4 w-4" />
                            </Button>
                            <Button 
                              size="xs" 
                              variant="ghost" 
                              className="h-8 px-2 text-yellow-600 hover:text-yellow-700 hover:bg-yellow-50"
                              onClick={() => handleStatusUpdate(apt.id, "pending")}
                              title="Aguardando"
                            >
                              <Clock4 className="h-4 w-4" />
                            </Button>
                            <Button 
                              size="xs" 
                              variant="ghost" 
                              className="h-8 px-2 text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                              onClick={() => handleStatusUpdate(apt.id, "confirmed")}
                              title="Confirmado"
                            >
                              <RefreshCw className="h-4 w-4" />
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="text-center py-8 text-gray-500">
                <p>Nenhum agendamento para esta data.</p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </DashboardLayout>
  );
}
