import { useAuth } from "@/_core/hooks/useAuth";
import DashboardLayout from "@/components/DashboardLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { trpc } from "@/lib/trpc";
import { ChevronLeft, ChevronRight, Loader2, Calendar as CalendarIcon } from "lucide-react";
import { useState } from "react";
import { useLocation } from "wouter";

export default function AdminDashboard() {
  const { user, loading } = useAuth();
  const [, navigate] = useLocation();
  const [currentDate, setCurrentDate] = useState(new Date());

  const calendarQuery = trpc.admin.getCalendarAppointments.useQuery({
    month: currentDate.getMonth(),
    year: currentDate.getFullYear()
  });

  if (loading) return null;
  if (!user || user.role !== 'admin') {
    navigate("/dashboard");
    return null;
  }

  const daysInMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).getDate();
  const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1).getDay();
  const monthName = currentDate.toLocaleDateString("pt-BR", { month: "long", year: "numeric" });

  const prevMonth = () => setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1));
  const nextMonth = () => setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1));

  const appointmentsByDay = calendarQuery.data?.appointments.reduce((acc, apt) => {
    if (!acc[apt.day]) acc[apt.day] = [];
    acc[apt.day].push(apt);
    return acc;
  }, {} as Record<number, any[]>) || {};

  return (
    <DashboardLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-3xl font-bold text-gray-900">Gerenciar Agenda</h1>
          <div className="flex items-center gap-4">
            <Button variant="outline" size="icon" onClick={prevMonth}><ChevronLeft className="h-4 w-4" /></Button>
            <h2 className="text-lg font-semibold capitalize">{monthName}</h2>
            <Button variant="outline" size="icon" onClick={nextMonth}><ChevronRight className="h-4 w-4" /></Button>
          </div>
        </div>

        <Card className="border-none shadow-md overflow-hidden">
          <CardContent className="p-0">
            <div className="grid grid-cols-7 bg-gray-50 border-b">
              {["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sab"].map(day => (
                <div key={day} className="py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider border-r last:border-r-0">
                  {day}
                </div>
              ))}
            </div>
            <div className="grid grid-cols-7">
              {Array.from({ length: firstDayOfMonth }).map((_, i) => (
                <div key={`empty-${i}`} className="h-32 border-b border-r bg-gray-50/50" />
              ))}
              {Array.from({ length: daysInMonth }).map((_, i) => {
                const day = i + 1;
                const dayAppointments = appointmentsByDay[day] || [];
                const isToday = new Date().toDateString() === new Date(currentDate.getFullYear(), currentDate.getMonth(), day).toDateString();

                return (
                  <div key={day} className={`h-32 border-b border-r p-1 overflow-y-auto hover:bg-gray-50 transition-colors ${isToday ? 'bg-indigo-50/30' : ''}`}>
                    <div className="flex justify-between items-start mb-1">
                      <span className={`text-xs font-bold px-1.5 py-0.5 rounded-full ${isToday ? 'bg-indigo-600 text-white' : 'text-gray-400'}`}>
                        {day}
                      </span>
                      {dayAppointments.length > 0 && (
                        <span className="text-[10px] font-medium text-indigo-600 bg-indigo-50 px-1 rounded">
                          {dayAppointments.length} agend.
                        </span>
                      )}
                    </div>
                    <div className="space-y-1">
                      {dayAppointments.slice(0, 4).map((apt: any) => (
                        <div key={apt.id} className="text-[10px] p-1 bg-white border rounded shadow-sm truncate flex items-center gap-1">
                          <span className="font-bold text-indigo-600">{apt.startTime.substring(0, 5)}</span>
                          <span className="text-gray-700">{apt.userName.split(' ')[0]}</span>
                        </div>
                      ))}
                      {dayAppointments.length > 4 && (
                        <div className="text-[9px] text-center text-gray-400 font-medium">
                          + {dayAppointments.length - 4} mais
                        </div>
                      )}
                    </div>
                  </div>
                );
              })}
              {/* Fill remaining cells */}
              {Array.from({ length: (7 - (firstDayOfMonth + daysInMonth) % 7) % 7 }).map((_, i) => (
                <div key={`empty-end-${i}`} className="h-32 border-b border-r last:border-r-0 bg-gray-50/50" />
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </DashboardLayout>
  );
}
