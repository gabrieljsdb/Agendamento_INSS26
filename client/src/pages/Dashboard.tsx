import { useEffect, useState } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import DashboardLayout from "@/components/DashboardLayout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Calendar, Clock, AlertCircle, CheckCircle2, Loader2, FileText, Download } from "lucide-react";
import { trpc } from "@/lib/trpc";
import { useLocation } from "wouter";
import { toast } from "sonner";

interface SelectedSlot {
  date: Date;
  time: string;
}

export default function Dashboard() {
  const { user, loading } = useAuth();
  const [, navigate] = useLocation();
  const [selectedSlot, setSelectedSlot] = useState<SelectedSlot | null>(null);
  const [reason, setReason] = useState("");
  const [phone, setPhone] = useState("");
  const [notes, setNotes] = useState("");
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [availableSlots, setAvailableSlots] = useState<string[]>([]);
  const [isFullDayBlocked, setIsFullDayBlocked] = useState(false);
  const [blockReason, setBlockReason] = useState<string | null>(null);

  const upcomingQuery = trpc.appointments.getUpcoming.useQuery();
  const publicBlocksQuery = trpc.appointments.getPublicBlocks.useQuery({
    month: currentMonth.getMonth(),
    year: currentMonth.getFullYear()
  });
  const availableSlotsQuery = trpc.appointments.getAvailableSlots.useQuery(
    { date: selectedSlot?.date || new Date() },
    { enabled: !!selectedSlot?.date }
  );

  const createAppointmentMutation = trpc.appointments.create.useMutation({
    onSuccess: () => {
      setSelectedSlot(null);
      setReason("");
      setNotes("");
      upcomingQuery.refetch();
      toast.success("Agendamento realizado com sucesso!");
    },
    onError: (error) => {
      toast.error(error.message || "Erro ao realizar agendamento");
    }
  });

  const generateDocumentMutation = trpc.documents.generateMyDocument.useMutation({
    onSuccess: (data) => {
      // Converte base64 para Blob e inicia download
      const byteCharacters = atob(data.content);
      const byteNumbers = new Array(byteCharacters.length);
      for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i);
      }
      const byteArray = new Uint8Array(byteNumbers);
      const blob = new Blob([byteArray], { type: data.contentType });
      
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = data.filename;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      
      toast.success("Documento gerado com sucesso!");
    },
    onError: (error) => {
      toast.error(error.message || "Erro ao gerar documento");
    }
  });

  useEffect(() => {
    if (availableSlotsQuery.data) {
      setAvailableSlots(availableSlotsQuery.data.slots);
      setIsFullDayBlocked(availableSlotsQuery.data.isFullDayBlocked);
      setBlockReason(availableSlotsQuery.data.blockReason || null);
    }
  }, [availableSlotsQuery.data]);

  useEffect(() => {
    if (user?.phone) {
      setPhone(user.phone);
    }
  }, [user]);

  if (loading) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-96">
          <Loader2 className="h-8 w-8 animate-spin text-indigo-600" />
        </div>
      </DashboardLayout>
    );
  }

  if (!user) {
    navigate("/login");
    return null;
  }

  const handleDateClick = (date: Date) => {
    setSelectedSlot({ date, time: "" });
  };

  const handleCreateAppointment = () => {
    if (!selectedSlot || !selectedSlot.time || !reason || !phone) {
      toast.error("Por favor, preencha todos os campos obrigatórios");
      return;
    }

    // Calcula o horário de término (endTime) baseado na duração padrão de 30 min
    const [hour, min] = selectedSlot.time.split(":").map(Number);
    const totalMinutes = hour * 60 + min + 30;
    const endHour = Math.floor(totalMinutes / 60);
    const endMin = totalMinutes % 60;
    const endTime = `${String(endHour).padStart(2, "0")}:${String(endMin).padStart(2, "0")}:00`;

    createAppointmentMutation.mutate({
      appointmentDate: selectedSlot.date,
      startTime: selectedSlot.time,
      endTime,
      reason,
      phone,
      notes: notes || undefined,
    });
  };

  const handleGenerateDocument = () => {
    generateDocumentMutation.mutate();
  };

  const getDaysInMonth = (date: Date) => {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
  };

  const getFirstDayOfMonth = (date: Date) => {
    return new Date(date.getFullYear(), date.getMonth(), 1).getDay();
  };

  const isWeekend = (day: number, date: Date) => {
    const checkDate = new Date(date.getFullYear(), date.getMonth(), day);
    const dayOfWeek = checkDate.getDay();
    return dayOfWeek === 0 || dayOfWeek === 6;
  };

  const isToday = (day: number, date: Date) => {
    const today = new Date();
    return (
      day === today.getDate() &&
      date.getMonth() === today.getMonth() &&
      date.getFullYear() === today.getFullYear()
    );
  };

  const isPastDate = (day: number, date: Date) => {
    const checkDate = new Date(date.getFullYear(), date.getMonth(), day);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return checkDate < today;
  };

  const monthName = currentMonth.toLocaleDateString("pt-BR", { month: "long", year: "numeric" });
  const daysInMonth = getDaysInMonth(currentMonth);
  const firstDay = getFirstDayOfMonth(currentMonth);
  const days = Array.from({ length: daysInMonth }, (_, i) => i + 1);

  return (
    <DashboardLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Painel de Agendamentos</h1>
            <p className="text-gray-600 mt-1">Bem-vindo, {user.name}</p>
          </div>
          

        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Calendário */}
          <div className="lg:col-span-2">
            <Card>
              <CardHeader>
                <CardTitle>Selecione uma Data</CardTitle>
                <CardDescription>Escolha um dia útil para agendar</CardDescription>
              </CardHeader>
              <CardContent>
                {/* Navegação de Mês */}
                <div className="flex items-center justify-between mb-6">
                  <Button
                    variant="outline"
                    onClick={() => setCurrentMonth(new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1))}
                  >
                    ←
                  </Button>
                  <h3 className="text-lg font-semibold capitalize">{monthName}</h3>
                  <Button
                    variant="outline"
                    onClick={() => setCurrentMonth(new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1))}
                  >
                    →
                  </Button>
                </div>

                {/* Calendário Grid */}
                <div className="grid grid-cols-7 gap-2">
                  {["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sab"].map((day) => (
                    <div key={day} className="text-center font-semibold text-sm text-gray-600 py-2">
                      {day}
                    </div>
                  ))}

                  {Array.from({ length: firstDay }).map((_, i) => (
                    <div key={`empty-${i}`} className="aspect-square" />
                  ))}

                  {days.map((day) => {
                    const date = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
                    const isBlocked = publicBlocksQuery.data?.blocks.some(b => b.day === day);
                    const isDisabled = isPastDate(day, currentMonth) || isWeekend(day, currentMonth);
                    const isSelected = selectedSlot?.date.getDate() === day && selectedSlot?.date.getMonth() === currentMonth.getMonth();

                    const blockData = publicBlocksQuery.data?.blocks.find(b => b.day === day);

                    return (
                      <button
                        key={day}
                        onClick={() => handleDateClick(date)}
                        disabled={isDisabled && !isBlocked}
                        className={`aspect-square rounded-lg font-semibold text-sm transition-colors flex flex-col items-center justify-center relative overflow-hidden ${
                          isDisabled && !isBlocked
                            ? "bg-gray-100 text-gray-400 cursor-not-allowed"
                            : isBlocked
                              ? "bg-red-100 text-red-600 border border-red-200 hover:bg-red-200"
                              : isSelected
                                ? "bg-indigo-600 text-white"
                                : isToday(day, currentMonth)
                                  ? "bg-indigo-100 text-indigo-900 border-2 border-indigo-600"
                                  : "bg-white border border-gray-200 hover:border-indigo-600 hover:bg-indigo-50"
                        }`}
                      >
                        <span>{day}</span>
                        {isBlocked && blockData?.reason && (
                          <span className="text-[8px] leading-tight mt-1 px-1 text-center font-normal opacity-80 truncate w-full">
                            {blockData.reason}
                          </span>
                        )}
                      </button>
                    );
                  })}
                </div>

                {/* Legenda */}
                <div className="mt-6 space-y-2 text-sm text-gray-600">
                  <div className="flex items-center gap-2">
                    <div className="w-4 h-4 bg-gray-100 rounded"></div>
                    <span>Fins de semana e datas passadas</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="w-4 h-4 bg-indigo-100 border-2 border-indigo-600 rounded"></div>
                    <span>Hoje</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="w-4 h-4 bg-indigo-600 rounded"></div>
                    <span>Data selecionada</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="w-4 h-4 bg-red-100 border border-red-200 rounded"></div>
                    <span>Dia Bloqueado (Indisponível)</span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Próximos Agendamentos */}
          <div>
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Calendar className="h-5 w-5" />
                  Próximos Agendamentos
                </CardTitle>
              </CardHeader>
              <CardContent>
                {upcomingQuery.isLoading ? (
                  <div className="flex justify-center py-4">
                    <Loader2 className="h-5 w-5 animate-spin text-indigo-600" />
                  </div>
                ) : upcomingQuery.data?.appointments && upcomingQuery.data.appointments.length > 0 ? (
                  <div className="space-y-3">
                    {upcomingQuery.data.appointments.map((apt) => (
                      <div key={apt.id} className="border-l-4 border-indigo-600 pl-3 py-2">
                        <div className="font-semibold text-sm">{apt.date}</div>
                        <div className="flex items-center gap-1 text-xs text-gray-600 mt-1">
                          <Clock className="h-3 w-3" />
                          {apt.time}
                        </div>
                        <div className="text-xs text-gray-700 mt-1">{apt.reason}</div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-6 text-gray-500">
                    <p className="text-sm">Nenhum agendamento futuro</p>
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      {/* Dialog de Agendamento */}
      <Dialog open={!!selectedSlot} onOpenChange={(open) => !open && setSelectedSlot(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Novo Agendamento</DialogTitle>
            <DialogDescription>
              {selectedSlot && `${selectedSlot.date.toLocaleDateString("pt-BR")}`}
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            {isFullDayBlocked && (
              <Alert variant="destructive" className="bg-red-50 border-red-200 text-red-800">
                <AlertCircle className="h-4 w-4 text-red-600" />
                <AlertDescription className="font-medium">
                  Este dia está bloqueado: {blockReason || "Indisponível para agendamento"}
                </AlertDescription>
              </Alert>
            )}

            {/* Horário */}
            <div className="space-y-2">
              <label className="text-sm font-medium">Horário</label>
              <Select value={selectedSlot?.time || ""} onValueChange={(time) => setSelectedSlot(selectedSlot ? { ...selectedSlot, time } : null)}>
                <SelectTrigger>
                  <SelectValue placeholder="Selecione um horário" />
                </SelectTrigger>
                <SelectContent>
                  {availableSlots.map((slot) => (
                    <SelectItem key={slot} value={slot}>
                      {slot.substring(0, 5)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Motivo */}
            <div className="space-y-2">
              <Label htmlFor="reason">Motivo do Agendamento</Label>
              <Select value={reason} onValueChange={setReason}>
                <SelectTrigger id="reason">
                  <SelectValue placeholder="Selecione um motivo" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Atendimento Geral">Atendimento Geral</SelectItem>
                  <SelectItem value="Renovação de Inscrição">Renovação de Inscrição</SelectItem>
                  <SelectItem value="Problemas com Senha">Problemas com Senha</SelectItem>
                  <SelectItem value="Consulta de Dados">Consulta de Dados</SelectItem>
                  <SelectItem value="Outros">Outros</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {/* Telefone */}
            <div className="space-y-2">
              <Label htmlFor="phone">Telefone de Contato</Label>
              <Input 
                id="phone"
                placeholder="(00) 00000-0000" 
                value={phone} 
                onChange={(e) => setPhone(e.target.value)} 
                required
              />
              <p className="text-[10px] text-gray-500 italic">* Obrigatório para confirmação do agendamento</p>
            </div>

            {/* Observações */}
            <div className="space-y-2">
              <label className="text-sm font-medium">Observações (opcional)</label>
              <Textarea placeholder="Adicione observações se necessário" value={notes} onChange={(e) => setNotes(e.target.value)} className="resize-none" rows={3} />
            </div>

            {/* Informações */}
            <Alert>
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>
                <ul className="text-sm space-y-1 mt-2">
                  <li>• Horário de atendimento: 08:00 - 12:00</li>
                  <li>• Máximo 2 agendamentos por mês</li>
                  <li>• Você receberá um email de confirmação</li>
                </ul>
              </AlertDescription>
            </Alert>

            {/* Botões */}
            <div className="flex gap-3">
              <Button variant="outline" onClick={() => setSelectedSlot(null)} className="flex-1">
                Cancelar
              </Button>
              <Button
                onClick={handleCreateAppointment}
                disabled={isFullDayBlocked || !selectedSlot?.time || !reason || !phone || createAppointmentMutation.isPending}
                className="flex-1 bg-indigo-600 hover:bg-indigo-700"
              >
                {createAppointmentMutation.isPending ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Agendando...
                  </>
                ) : (
                  <>
                    <CheckCircle2 className="mr-2 h-4 w-4" />
                    Confirmar Agendamento
                  </>
                )}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </DashboardLayout>
  );
}
