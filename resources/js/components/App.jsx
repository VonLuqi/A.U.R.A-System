import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { AuthProvider } from '../context/AuthContext';
import AbilityRoute from './auth/AbilityRoute';
import GuestRoute from './auth/GuestRoute';
import ProtectedRoute from './auth/ProtectedRoute';
import RootRedirect from './auth/RootRedirect';
import AppShell from './layout/AppShell';
import Toaster from './ui/Toaster';
import { ABILITIES, ROLES } from '../lib/auth';
import AdminUsersPage from '../pages/AdminUsersPage';
import AliasesPage from '../pages/AliasesPage';
import DashboardPage from '../pages/DashboardPage';
import GoalsPage from '../pages/GoalsPage';
import LoginPage from '../pages/LoginPage';
import NotFoundPage from '../pages/NotFoundPage';
import UploadPage from '../pages/UploadPage';

/**
 * Inventário SPA (Etapa D §2.4 / PLAN_EXPANSAO §8.1).
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

                            <Route element={<AbilityRoute ability={ABILITIES.statementsUpload} />}>
                                <Route path="/upload" element={<UploadPage />} />
                            </Route>

                            <Route element={<AbilityRoute ability={ABILITIES.goalsManage} />}>
                                <Route path="/goals" element={<GoalsPage />} />
                            </Route>

                            <Route element={<AbilityRoute ability={ABILITIES.aliasesManage} />}>
                                <Route path="/aliases" element={<AliasesPage />} />
                            </Route>

                            <Route
                                element={(
                                    <AbilityRoute
                                        ability={ABILITIES.usersManage}
                                        roles={[ROLES.admin]}
                                    />
                                )}
                            >
                                <Route path="/admin/users" element={<AdminUsersPage />} />
                            </Route>
                        </Route>
                    </Route>

                    <Route path="/" element={<RootRedirect />} />
                    <Route path="*" element={<NotFoundPage />} />
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    );
}
