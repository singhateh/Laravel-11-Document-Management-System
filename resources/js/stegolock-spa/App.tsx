import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './hooks/useAuth';
import { ReactNode, Suspense, lazy } from 'react';

const Login     = lazy(() => import('./pages/Login'));
const Dashboard = lazy(() => import('./pages/Dashboard'));
const Encode    = lazy(() => import('./pages/Encode'));
const Decode    = lazy(() => import('./pages/Decode'));
const Tokens    = lazy(() => import('./pages/Tokens'));
const StegoIndex = lazy(() => import('./pages/StegoIndex'));

function RequireAuth({ children }: { children: ReactNode }) {
    const { user, loading } = useAuth();
    if (loading) return <div className="flex h-screen items-center justify-center text-gray-500">Loading…</div>;
    if (!user) return <Navigate to="/login" replace />;
    return <>{children}</>;
}

function AppRoutes() {
    const { user, loading } = useAuth();
    if (loading) return <div className="flex h-screen items-center justify-center text-gray-500">Loading…</div>;

    return (
        <Suspense fallback={<div className="flex h-screen items-center justify-center text-gray-400">Loading page…</div>}>
            <Routes>
                <Route path="/login"   element={user ? <Navigate to="/dashboard" replace /> : <Login />} />
                <Route path="/dashboard" element={<RequireAuth><Dashboard /></RequireAuth>} />
                <Route path="/stego"   element={<RequireAuth><StegoIndex /></RequireAuth>} />
                <Route path="/encode"  element={<RequireAuth><Encode /></RequireAuth>} />
                <Route path="/decode"  element={<RequireAuth><Decode /></RequireAuth>} />
                <Route path="/tokens"  element={<RequireAuth><Tokens /></RequireAuth>} />
                <Route path="*"        element={<Navigate to={user ? '/dashboard' : '/login'} replace />} />
            </Routes>
        </Suspense>
    );
}

export default function App() {
    return (
        <HashRouter>
            <AuthProvider>
                <AppRoutes />
            </AuthProvider>
        </HashRouter>
    );
}
