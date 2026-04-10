import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';

interface RecentDocument {
    id: number;
    name: string;
    extension: string;
    size: number;
    created_at: string;
    is_stegoed: boolean;
    tags: Array<{ id: number; name: string }>;
}

interface Stats {
    documents: number;
    folders: number;
    categories: number;
    tags: number;
    stego_docs: number;
}

interface DashboardProps extends PageProps {
    stats: Stats;
    recentDocuments: RecentDocument[];
}

const FILE_ICONS: Record<string, string> = {
    pdf: '📄', doc: '📝', docx: '📝', xls: '📊', xlsx: '📊',
    ppt: '📈', pptx: '📈', jpg: '🖼️', jpeg: '🖼️', png: '🖼️',
    gif: '🖼️', mp4: '🎥', mp3: '🎵', zip: '🗜️',
};

function fileIcon(ext: string) {
    return FILE_ICONS[ext?.toLowerCase()] ?? '📎';
}

function formatBytes(bytes: number) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + sizes[i];
}

interface StatCardProps {
    label: string;
    value: number;
    icon: string;
    href: string;
    color: string;
}

function StatCard({ label, value, icon, href, color }: StatCardProps) {
    return (
        <Link
            href={href}
            className={`group flex items-center justify-between rounded-xl border ${color} bg-white p-5 shadow-sm transition hover:shadow-md`}
        >
            <div>
                <p className="text-sm font-medium text-gray-500">{label}</p>
                <p className="mt-1 text-3xl font-bold text-gray-900">{value.toLocaleString()}</p>
            </div>
            <div className="text-4xl opacity-70 group-hover:opacity-100 transition">{icon}</div>
        </Link>
    );
}

export default function Dashboard({ auth, stats, recentDocuments }: DashboardProps) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
                    <span className="text-sm text-gray-500">
                        Welcome back, <strong>{auth.user.name}</strong>
                    </span>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

                    {/* Stat cards */}
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        <StatCard
                            label="Documents"
                            value={stats.documents}
                            icon="📂"
                            href={route('documents.index')}
                            color="border-blue-100"
                        />
                        <StatCard
                            label="Folders"
                            value={stats.folders}
                            icon="🗂️"
                            href={route('folders.index')}
                            color="border-yellow-100"
                        />
                        <StatCard
                            label="Categories"
                            value={stats.categories}
                            icon="🏷️"
                            href={route('categories.index')}
                            color="border-green-100"
                        />
                        <StatCard
                            label="Tags"
                            value={stats.tags}
                            icon="🔖"
                            href={route('tags.index')}
                            color="border-purple-100"
                        />
                        <StatCard
                            label="Stego Docs"
                            value={stats.stego_docs}
                            icon="🔒"
                            href={route('stego.index')}
                            color="border-indigo-200"
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                        {/* Recent Documents */}
                        <div className="lg:col-span-2">
                            <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                                <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                                    <h3 className="font-semibold text-gray-800">Recent Documents</h3>
                                    <Link
                                        href={route('documents.index')}
                                        className="text-sm text-indigo-600 hover:text-indigo-800"
                                    >
                                        View all →
                                    </Link>
                                </div>

                                {recentDocuments.length === 0 ? (
                                    <div className="py-12 text-center">
                                        <div className="text-4xl">📂</div>
                                        <p className="mt-2 text-sm text-gray-500">No documents yet.</p>
                                        <Link
                                            href={route('documents.index')}
                                            className="mt-3 inline-block text-sm text-indigo-600 hover:underline"
                                        >
                                            Upload your first document
                                        </Link>
                                    </div>
                                ) : (
                                    <ul className="divide-y divide-gray-50">
                                        {recentDocuments.map((doc) => (
                                            <li
                                                key={doc.id}
                                                className="flex items-center gap-3 px-6 py-3 hover:bg-gray-50"
                                            >
                                                <span className="text-xl">{fileIcon(doc.extension)}</span>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="truncate text-sm font-medium text-gray-900">
                                                            {doc.name}
                                                        </span>
                                                        {doc.is_stegoed && (
                                                            <span
                                                                title="Steganographically encoded"
                                                                className="rounded-full bg-indigo-100 px-1.5 py-0.5 text-xs font-medium text-indigo-700"
                                                            >
                                                                🔒
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="mt-0.5 flex flex-wrap gap-1">
                                                        {doc.tags.slice(0, 3).map((t) => (
                                                            <span
                                                                key={t.id}
                                                                className="rounded-full bg-blue-50 px-1.5 py-0.5 text-xs text-blue-700"
                                                            >
                                                                {t.name}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </div>
                                                <div className="text-right text-xs text-gray-400">
                                                    <div>{formatBytes(doc.size)}</div>
                                                    <div>
                                                        {new Date(doc.created_at).toLocaleDateString()}
                                                    </div>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </div>

                        {/* Quick actions */}
                        <div className="space-y-4">
                            <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                                <div className="border-b border-gray-100 px-6 py-4">
                                    <h3 className="font-semibold text-gray-800">Quick Actions</h3>
                                </div>
                                <div className="space-y-2 p-4">
                                    <Link
                                        href={route('documents.index')}
                                        className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                    >
                                        <span className="text-lg">📤</span>
                                        Upload Document
                                    </Link>
                                    <Link
                                        href={route('folders.index')}
                                        className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                    >
                                        <span className="text-lg">📁</span>
                                        Manage Folders
                                    </Link>
                                    <Link
                                        href={route('stego.encode.form')}
                                        className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-indigo-700 hover:bg-indigo-50"
                                    >
                                        <span className="text-lg">🔒</span>
                                        Encode a Document
                                    </Link>
                                    <Link
                                        href={route('stego.decode.form')}
                                        className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-indigo-700 hover:bg-indigo-50"
                                    >
                                        <span className="text-lg">🔓</span>
                                        Decode a Document
                                    </Link>
                                    <Link
                                        href={route('stego.tokens')}
                                        className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                    >
                                        <span className="text-lg">🗝️</span>
                                        API Tokens
                                    </Link>
                                </div>
                            </div>

                            {/* StegoLock panel */}
                            <div className="rounded-xl border border-indigo-100 bg-indigo-50 p-5">
                                <h3 className="font-semibold text-indigo-900">About StegoLock</h3>
                                <p className="mt-2 text-xs leading-relaxed text-indigo-700">
                                    StegoLock hides encrypted documents inside carrier images using
                                    LSB steganography + AES-256-GCM. Only the master key holder can
                                    decode the hidden content.
                                </p>
                                <Link
                                    href={route('stego.index')}
                                    className="mt-3 inline-block text-xs font-medium text-indigo-800 hover:underline"
                                >
                                    View my stego documents →
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
