import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { AuthProvider } from '../context/AuthContext';
import GuestRoute from './auth/GuestRoute';
import ProtectedRoute from './auth/ProtectedRoute';
import RootRedirect from './auth/RootRedirect';
import AppShell from './layout/AppShell';
import Toaster from './ui/Toaster';
import DashboardPage from '../pages/DashboardPage';
import LoginPage from '../pages/LoginPage';
import NotFoundPage from '../pages/NotFoundPage';
import UploadPage from '../pages/UploadPage';

/**
 * Inventário SPA (Etapa D §2.4).
 * BrowserRouter envolve AuthProvider (useNavigate no bootstrap).
 * AuthProvider só monta rotas após CSRF + fetchUser (splash enquanto loading).
 */
export default function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <Toaster />
                <Routes>
                    <Route element={<GuestRoute />}>
                        <Route path="/login" element={<LoginPage />} />
                    </Route>

                    <Route element={<ProtectedRoute />}>
                        <Route element={<AppShell />}>
                            <Route path="/dashboard" element={<DashboardPage />} />
                            <Route path="/upload" element={<UploadPage />} />
                        </Route>
                    </Route>

                    <Route path="/" element={<RootRedirect />} />
                    <Route path="*" element={<NotFoundPage />} />
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    );
}
