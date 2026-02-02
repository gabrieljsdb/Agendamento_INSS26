import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import NotFound from "@/pages/NotFound";
import { Route, Switch } from "wouter";
import ErrorBoundary from "./components/ErrorBoundary";
import { ThemeProvider } from "./contexts/ThemeContext";
import Home from "./pages/Home";
import Login from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import MyAppointments from "./pages/MyAppointments";
import DailyAppointments from "./pages/admin/DailyAppointments";
import BlockManagement from "./pages/admin/BlockManagement";
import AdminDashboard from "./pages/admin/AdminDashboard";
import Settings from "./pages/admin/Settings";
import AdminCalendar from "./pages/admin/Calendar";
import EmailSettings from "./pages/admin/EmailSettings";

function Router() {
  return (
    <Switch>
      <Route path={"/"} component={Home} />
      <Route path={"/login"} component={Login} />
      <Route path={"/dashboard"} component={Dashboard} />
      <Route path={"/my-appointments"} component={MyAppointments} />
      <Route path={"/admin/daily"} component={DailyAppointments} />
      <Route path={"/admin/blocks"} component={BlockManagement} />
      <Route path={"/admin"} component={AdminDashboard} />
      <Route path={"/admin/settings"} component={Settings} />
      <Route path={"/admin/calendar"} component={AdminCalendar} />
      <Route path={"/admin/email-settings"} component={EmailSettings} />
      <Route path={"/404"} component={NotFound} />
      {/* Final fallback route */}
      <Route component={NotFound} />
    </Switch>
  );
}

function App() {
  return (
    <ErrorBoundary>
      <ThemeProvider defaultTheme="light">
        <TooltipProvider>
          <Toaster />
          <Router />
        </TooltipProvider>
      </ThemeProvider>
    </ErrorBoundary>
  );
}

export default App;
