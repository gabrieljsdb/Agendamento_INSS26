import { useAuth } from "@/_core/hooks/useAuth";
import DashboardLayout from "@/components/DashboardLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { trpc } from "@/lib/trpc";
import { Calendar, Clock, Loader2, User, FileText, CheckCircle, XCircle, Clock4, UserMinus, Info, Mail, Phone, MapPin, AlertTriangle, RefreshCw, Send } from "lucide-react";
import { toast } from "sonner";
import { useState } from "react";
import { useLocation } from "wouter";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { Textarea } from "@/components/ui/textarea";

export default function DailyAppointments() {
  const { user, loading } = useAuth();
  const [, navigate] = useLocation();
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [selectedAppointment, setSelectedAppointment] = useState<any>(null);
  const [notificationModalOpen, setNotificationModalOpen] = useState(false);
  const [notificationMessage, setNotificationMessage] = useState("");
  const [isSendingNotification, setIsSendingNotification] = useState(false);
  
  const dailyQuery = trpc.admin.getDailyAppointments.useQuery({ date: selectedDate });
  const sendNotificationMutation = trpc.admin.sendCustomNotification.useMutation({
    onSuccess: () => {
      toast.success("Notificação enviada com sucesso!");
      setNotificationModalOpen(false);
      setNotificationMessage("");
    },
    onError: (error) => {
      toast.error(error.message || "Erro ao enviar notificação");
    },
    onSettled: () => {
      setIsSendingNotification(false);
    }
  });
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
                      <tr 
                        key={apt.id} 
                        className="bg-white hover:bg-gray-50 cursor-pointer transition-colors"
                        onClick={() => setSelectedAppointment(apt)}
                      >
                        <td className="px-4 py-3 font-semibold text-indigo-600">
                          {apt.startTime.substring(0, 5)}
                        </td>
                        <td className="px-4 py-3 font-medium">
                          <div className="flex items-center gap-2">
                            {apt.userName}
                            <Info className="h-3 w-3 text-gray-400" />
                          </div>
                        </td>
                        <td className="px-4 py-3 text-gray-600">
                          <div>{apt.userCpf}</div>
                          <div className="text-xs text-gray-400">{apt.userOab}</div>
                        </td>
                        <td className="px-4 py-3">{apt.reason}</td>
                        <td className="px-4 py-3">{getStatusBadge(apt.status)}</td>
                        <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
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
                            <Button 
                              size="xs" 
                              variant="ghost" 
                              className="h-8 px-2 text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50"
                              onClick={() => {
                                setSelectedAppointment(apt);
                                setNotificationModalOpen(true);
                              }}
                              title="Enviar Notificação"
                            >
                              <Mail className="h-4 w-4" />
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

      {/* Modal de Detalhes do Atendimento */}
      <Dialog open={selectedAppointment !== null} onOpenChange={() => setSelectedAppointment(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-2xl">
              <User className="h-6 w-6 text-indigo-600" />
              Detalhes do Atendimento
            </DialogTitle>
            <DialogDescription>
              Informações completas do agendamento e do usuário.
            </DialogDescription>
          </DialogHeader>

          {selectedAppointment && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 py-4">
              <div className="space-y-4">
                <div>
                  <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Informações do Usuário</h3>
                  <div className="space-y-2">
                    <p className="flex items-center gap-2 text-gray-900 font-medium">
                      <User className="h-4 w-4 text-gray-400" /> {selectedAppointment.userName}
                    </p>
                    <p className="flex items-center gap-2 text-gray-600">
                      <FileText className="h-4 w-4 text-gray-400" /> CPF: {selectedAppointment.userCpf}
                    </p>
                    <p className="flex items-center gap-2 text-gray-600">
                      <Badge variant="outline" className="font-normal">OAB: {selectedAppointment.userOab}</Badge>
                    </p>
                    <p className="flex items-center gap-2 text-gray-600">
                      <Mail className="h-4 w-4 text-gray-400" /> {selectedAppointment.userEmail}
                    </p>
                    {selectedAppointment.userPhone && (
                      <p className="flex items-center gap-2 text-gray-600">
                        <Phone className="h-4 w-4 text-gray-400" /> {selectedAppointment.userPhone}
                      </p>
                    )}
                    {(selectedAppointment.userCidade || selectedAppointment.userEstado) && (
                      <p className="flex items-center gap-2 text-gray-600">
                        <MapPin className="h-4 w-4 text-gray-400" /> {selectedAppointment.userCidade}/{selectedAppointment.userEstado}
                      </p>
                    )}
                  </div>
                </div>
              </div>

              <div className="space-y-4">
                <div>
                  <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Detalhes do Agendamento</h3>
                  <div className="bg-gray-50 p-4 rounded-lg border border-gray-100 space-y-3">
                    <div className="flex justify-between items-center">
                      <span className="text-gray-500 text-sm">Status:</span>
                      {getStatusBadge(selectedAppointment.status)}
                    </div>
                    <div className="flex justify-between items-center">
                      <span className="text-gray-500 text-sm">Horário:</span>
                      <span className="font-semibold text-indigo-600 flex items-center gap-1">
                        <Clock className="h-4 w-4" /> {selectedAppointment.startTime.substring(0, 5)} - {selectedAppointment.endTime.substring(0, 5)}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-500 text-sm block mb-1">Motivo:</span>
                      <p className="text-gray-900 font-medium">{selectedAppointment.reason}</p>
                    </div>
                    {selectedAppointment.notes && (
                      <div>
                        <span className="text-gray-500 text-sm block mb-1">Observações:</span>
                        <p className="text-gray-700 text-sm italic">"{selectedAppointment.notes}"</p>
                      </div>
                    )}
                  </div>
                </div>

                {selectedAppointment.status === "cancelled" && (
                  <div className="bg-red-50 p-3 rounded-md border border-red-100">
                    <h4 className="text-red-800 text-xs font-bold uppercase flex items-center gap-1 mb-1">
                      <AlertTriangle className="h-3 w-3" /> Detalhes do Cancelamento
                    </h4>
                    <p className="text-red-700 text-sm">
                      <strong>Motivo:</strong> {selectedAppointment.cancellationReason || "Não informado"}
                    </p>
                    {selectedAppointment.cancelledAt && (
                      <p className="text-red-600 text-xs mt-1">
                        Cancelado em: {new Date(selectedAppointment.cancelledAt).toLocaleString("pt-BR")}
                      </p>
                    )}
                  </div>
                )}
              </div>
            </div>
          )}

          <DialogFooter className="sm:justify-start gap-2">
            <Button variant="outline" onClick={() => setSelectedAppointment(null)}>Fechar</Button>
            {selectedAppointment && selectedAppointment.status === "confirmed" && (
              <Button 
                className="bg-green-600 hover:bg-green-700 text-white"
                onClick={() => {
                  handleStatusUpdate(selectedAppointment.id, "completed");
                  setSelectedAppointment(null);
                }}
              >
                <CheckCircle className="h-4 w-4 mr-2" /> Marcar como Atendido
              </Button>
            )}
            <Button 
              className="bg-indigo-600 hover:bg-indigo-700 text-white"
              onClick={() => setNotificationModalOpen(true)}
            >
              <Mail className="h-4 w-4 mr-2" /> Notificar Usuário
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Modal de Envio de Notificação */}
      <Dialog open={notificationModalOpen} onOpenChange={(open) => {
        if (!open) {
          setNotificationModalOpen(false);
          setNotificationMessage("");
        }
      }}>
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Send className="h-5 w-5 text-indigo-600" />
              Enviar Notificação por E-mail
            </DialogTitle>
            <DialogDescription>
              A mensagem abaixo será enviada para o e-mail de <strong>{selectedAppointment?.userName}</strong>.
            </DialogDescription>
          </DialogHeader>

          <div className="py-4 space-y-4">
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-700">Mensagem</label>
              <Textarea 
                placeholder="Ex: Por favor, traga a documentação original do INSS..."
                className="min-h-[150px]"
                value={notificationMessage}
                onChange={(e) => setNotificationMessage(e.target.value)}
              />
              <p className="text-xs text-gray-500">
                O usuário receberá esta mensagem formatada em um e-mail oficial do sistema.
              </p>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setNotificationModalOpen(false)}>Cancelar</Button>
            <Button 
              className="bg-indigo-600 hover:bg-indigo-700 text-white"
              disabled={!notificationMessage.trim() || isSendingNotification}
              onClick={() => {
                setIsSendingNotification(true);
                sendNotificationMutation.mutate({
                  appointmentId: selectedAppointment.id,
                  message: notificationMessage
                });
              }}
            >
              {isSendingNotification ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Enviando...
                </>
              ) : (
                <>
                  <Send className="h-4 w-4 mr-2" />
                  Enviar E-mail
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </DashboardLayout>
  );
}
