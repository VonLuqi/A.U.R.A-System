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
import CardsPage from '../pages/CardsPage';
import CategoriesPage from '../pages/CategoriesPage';
import DashboardPage from '../pages/DashboardPage';
import GoalsPage from '../pages/GoalsPage';
import LoansPage from '../pages/LoansPage';
import LoginPage from '../pages/LoginPage';
import NotFoundPage from '../pages/NotFoundPage';
import TransactionsPage from '../pages/TransactionsPage';
import UploadPage from '../pages/UploadPage';

/**
 * Inventário SPA (Etapa D §2.4 / PLAN_EXPANSAO §8.1 / PLAN_CARTOES_EMPRESTIMOS §6.1).
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
                            <Route path="/transactions" element={<TransactionsPage />} />

                            <Route element={<AbilityRoute ability={ABILITIES.statementsUpload} />}>
                                <Route path="/upload" element={<UploadPage />} />
                            </Route>

                            <Route
                                element={(
                                    <AbilityRoute
                                        ability={ABILITIES.goalsManage}
                                        feature="goals"
                                    />
                                )}
                            >
                                <Route path="/goals" element={<GoalsPage />} />
                            </Route>

                            <Route
                                element={(
                                    <AbilityRoute
                                        ability={ABILITIES.aliasesManage}
                                        feature="aliases"
                                    />
                                )}
                            >
                                <Route path="/aliases" element={<AliasesPage />} />
                            </Route>

                            <Route path="/categories" element={<CategoriesPage />} />

                            <Route
                                element={(
                                    <AbilityRoute
                                        ability={ABILITIES.creditCardsManage}
                                        feature="credit_cards"
                                    />
                                )}
                            >
                                <Route path="/cards" element={<CardsPage />} />
                            </Route>

                            <Route
                                element={(
                                    <AbilityRoute
                                        ability={ABILITIES.loansManage}
                                        feature="loans"
                                    />
                                )}
                            >
                                <Route path="/loans" element={<LoansPage />} />
                            </Route>

                            <Route
                                element={(
                                    <AbilityRoute
                                        ability={ABILITIES.usersManage}
                                        feature="admin_users"
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
