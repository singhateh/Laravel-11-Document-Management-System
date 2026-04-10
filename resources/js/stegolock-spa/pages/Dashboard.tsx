import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import axios from 'axios';
import SpaLayout from '../components/SpaLayout';
import { useAuth } from '../hooks/useAuth';

interface Stats {
    documents: number;
    folders: number;
    categories: number;
    tags: number;
    stego_documents: number;
}

interface RecentDoc {
    id: number;
    name: string;
    extension: string;
    size: number;
    created_at: string;
    is_stegoed: boolean;
    tags: { id: number; name: string }[];
}

export default function Dashboard() {
    const { user } = useAuth();
    const [stats, setStats] = useState<Stats | null>(null);
    const [recent, setRecent] = useState<RecentDoc[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.all([
            axios.get('/api/dashboard/stats'),
            axios.get('/api/dashboard/recent'),
        ])
            .then(([s, r]) => {
                setStats(s.data);
                setRecent(r.data);
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    }, []);

    const statCards = stats
        ? [
              { label: 'Documents', value: stats.documents, icon: '📄', to: '/documents' },
              { label: 'Folders', value: stats.folders, icon: '📁', to: '#' },
              { label: 'Categories', value: stats.categories, icon: '🏷️', to: '#' },
              { label: 'Tags', value: stats.tags, icon: '🔖', to: '#' },
              { label: 'Stego Docs', value: stats.stego_documents, icon: '🔒', to: '/stego' },
          ]
        : [];

    return (
        <SpaLayout>
            <h1 className="mb-6 text-2xl font-bold text-gray-900">
                Welcome back, {user?.name?.split(' ')[0] ?? 'User'} 👋
            </h1>

            {loading ? (
                <div className="text-center text-gray-400 py-16">Loading…</div>
            ) : (
                <>
                    {/* Stat cards */}
                    <div className="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-5">
                        {statCards.map((c) => (
                            <Link
                                key={c.label}
                                to={c.to}
                                className="rounded-xl bg-white p-5 shadow-sm hover:shadow-md transition-shadow"
                            >
                                <div className="text-3xl">{c.icon}</div>
                                <div className="mt-2 text-2xl font-bold text-gray-900">{c.value}</div>
                                <div className="text-xs text-gray-500">{c.label}</div>
                            </Link>
                        ))}
                    </div>

                    {/* Quick actions */}
                    <div className="mb-8 flex flex-wrap gap-3">
                        <Link to="/encode" className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            🔒 Encode Document
                        </Link>
                        <Link to="/decode" className="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-white px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                            🔓 Decode Document
                        </Link>
                        <Link to="/tokens" className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            🔑 Manage API Tokens
                        </Link>
                    </div>

                    {/* Recent documents */}
                    <div className="rounded-xl bg-white shadow-sm overflow-hidden">
                        <div className="border-b px-5 py-3 flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-gray-800">Recent Documents</h2>
                        </div>
                        {recent.length === 0 ? (
                            <p className="px-5 py-8 text-center text-sm text-gray-400">No documents yet.</p>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {recent.map((d) => (
                                    <li key={d.id} className="flex items-center gap-3 px-5 py-3 hover:bg-gray-50">
                                        <span className="text-2xl shrink-0">
                                            {d.extension === 'pdf' ? '📕' : d.extension === 'docx' ? '📘' : '📄'}
                                        </span>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="truncate text-sm font-medium text-gray-800">{d.name}</span>
                                                {d.is_stegoed && (
                                                    <span className="inline-flex shrink-0 items-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700">🔒</span>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2 mt-0.5">
                                                <span className="text-xs text-gray-400">
                                                    {(d.size / 1024).toFixed(1)} KB ·{' '}
                                                    {new Date(d.created_at).toLocaleDateString()}
                                                </span>
                                                {d.tags.map((t) => (
                                                    <span key={t.id} className="inline-flex items-center rounded-full bg-blue-100 px-1.5 py-0.5 text-xs text-blue-700">
                                                        {t.name}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </>
            )}
        </SpaLayout>
    );
}
