import { useState, useEffect, useRef } from "react";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Loader2, Send, User, ShieldCheck } from "lucide-react";
import { format } from "date-fns";
import { ptBR } from "date-fns/locale";

interface AppointmentChatProps {
  appointmentId: number;
  isAdmin?: boolean;
}

export function AppointmentChat({ appointmentId, isAdmin = false }: AppointmentChatProps) {
  const [newMessage, setNewMessage] = useState("");
  const scrollRef = useRef<HTMLDivElement>(null);
  
  const messagesQuery = trpc.messages.getMessages.useQuery(
    { appointmentId },
    { 
      refetchInterval: 5000, // Atualiza a cada 5 segundos
    }
  );
  
  const sendMessageMutation = trpc.messages.sendMessage.useMutation({
    onSuccess: () => {
      setNewMessage("");
      messagesQuery.refetch();
    }
  });

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messagesQuery.data]);

  const handleSendMessage = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newMessage.trim() || sendMessageMutation.isPending) return;
    
    sendMessageMutation.mutate({
      appointmentId,
      message: newMessage.trim(),
    });
  };

  return (
    <div className="flex flex-col h-[400px] border rounded-lg bg-white overflow-hidden">
      <div className="bg-gray-50 px-4 py-2 border-b flex items-center justify-between">
        <h3 className="text-sm font-semibold text-gray-700">Mensagens do Agendamento</h3>
        {messagesQuery.isFetching && <Loader2 className="h-3 w-3 animate-spin text-gray-400" />}
      </div>

      <ScrollArea className="flex-1 p-4" ref={scrollRef}>
        {messagesQuery.isLoading ? (
          <div className="flex justify-center items-center h-full">
            <Loader2 className="h-6 w-6 animate-spin text-indigo-600" />
          </div>
        ) : messagesQuery.data && messagesQuery.data.length > 0 ? (
          <div className="space-y-4">
            {messagesQuery.data.map((msg) => {
              const isOwnMessage = isAdmin ? msg.isAdmin : !msg.isAdmin;
              return (
                <div
                  key={msg.id}
                  className={`flex ${isOwnMessage ? "justify-end" : "justify-start"}`}
                >
                  <div
                    className={`max-w-[80%] rounded-lg px-3 py-2 ${
                      isOwnMessage
                        ? "bg-indigo-600 text-white rounded-br-none"
                        : "bg-gray-100 text-gray-800 rounded-bl-none"
                    }`}
                  >
                    <div className="flex items-center gap-1 mb-1">
                      {msg.isAdmin ? (
                        <ShieldCheck className={`h-3 w-3 ${isOwnMessage ? "text-indigo-200" : "text-indigo-600"}`} />
                      ) : (
                        <User className={`h-3 w-3 ${isOwnMessage ? "text-indigo-200" : "text-gray-500"}`} />
                      )}
                      <span className="text-[10px] font-bold uppercase opacity-70">
                        {msg.isAdmin ? "Administrador" : "Usuário"}
                      </span>
                    </div>
                    <p className="text-sm whitespace-pre-wrap break-words">{msg.message}</p>
                    <span className="text-[10px] block mt-1 opacity-70 text-right">
                      {format(new Date(msg.createdAt), "HH:mm", { locale: ptBR })}
                    </span>
                  </div>
                </div>
              );
            })}
          </div>
        ) : (
          <div className="flex flex-col items-center justify-center h-full text-gray-400 text-sm italic">
            <p>Nenhuma mensagem ainda.</p>
            <p>Inicie a conversa abaixo.</p>
          </div>
        )}
      </ScrollArea>

      <form onSubmit={handleSendMessage} className="p-3 border-t bg-gray-50 flex gap-2">
        <Input
          value={newMessage}
          onChange={(e) => setNewMessage(e.target.value)}
          placeholder="Digite sua mensagem..."
          className="flex-1"
          disabled={sendMessageMutation.isPending}
        />
        <Button 
          type="submit" 
          size="icon" 
          disabled={!newMessage.trim() || sendMessageMutation.isPending}
          className="bg-indigo-600 hover:bg-indigo-700"
        >
          {sendMessageMutation.isPending ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Send className="h-4 w-4" />
          )}
        </Button>
      </form>
    </div>
  );
}
