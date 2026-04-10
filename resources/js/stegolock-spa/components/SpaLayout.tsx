import { ReactNode } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

interface NavItem { to: string; label: string; icon: string }

const repoLinks: NavItem[] = [
    { to: '/dashboard', label: 'Dashboard', icon: '🏠' },
    { to: '/documents', label: 'Documents', icon: '📁' },
];

const stegoLinks: NavItem[] = [
    { to: '/stego', label: 'My Docs', icon: '🔒' },
    { to: '/encode', label: 'Encode', icon: '📥' },
    { to: '/decode', label: 'Decode', icon: '📤' },
    { to: '/tokens', label: 'API Tokens', icon: '🔑' },
];

function NavLink({ to, label, icon }: NavItem) {
    const loc = useLocation();
    const active = loc.pathname === to || (to !== '/dashboard' && loc.pathname.startsWith(to));
    return (
        <Link
            to={to}
            className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                active
                    ? 'bg-indigo-600 text-white'
                    : 'text-gray-700 hover:bg-gray-100'
            }`}
        >
            <span>{icon}</span>
            {label}
        </Link>
    );
}

export default function SpaLayout({ children }: { children: ReactNode }) {
    const { user, logout } = useAuth();

    return (
        <div className="flex h-full min-h-screen bg-gray-100">
            {/* Sidebar */}
            <aside className="flex w-56 flex-col bg-white shadow-sm">
                {/* Logo */}
                <div className="border-b px-5 py-4">
                    <div className="flex items-center gap-2">
                        <span className="text-2xl">🔒</span>
                        <span className="text-lg font-bold text-gray-900">StegoLock</span>
                    </div>
                    <p className="mt-0.5 text-xs text-gray-400">Standalone SPA</p>
                </div>

                {/* Nav */}
                <nav className="flex-1 space-y-1 px-3 py-4">
                    <p className="mb-1 px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">
                        Repository
                    </p>
                    {repoLinks.map((l) => <NavLink key={l.to} {...l} />)}

                    <p className="mb-1 mt-4 px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">
                        StegoLock
                    </p>
                    {stegoLinks.map((l) => <NavLink key={l.to} {...l} />)}
                </nav>

                {/* User footer */}
                <div className="border-t px-4 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold uppercase text-indigo-700">
                            {user?.name?.charAt(0) ?? '?'}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-gray-800">{user?.name}</p>
                            <p className="truncate text-xs text-gray-400">{user?.email}</p>
                        </div>
                        <button onClick={logout} title="Sign out" className="text-gray-400 hover:text-red-500">
                            ⬡
                        </button>
                    </div>
                </div>
            </aside>

            {/* Main */}
            <main className="flex-1 overflow-y-auto">
                <div className="mx-auto max-w-5xl px-6 py-8">
                    {children}
                </div>
            </main>
        </div>
    );
}
