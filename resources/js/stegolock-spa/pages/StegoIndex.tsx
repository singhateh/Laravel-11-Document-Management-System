import { useEffect, useState, useRef } from 'react';
import { Link } from 'react-router-dom';
import axios from 'axios';
import SpaLayout from '../components/SpaLayout';

interface StegoDoc {
    id: number;
    document: { id: number; name: string; extension: string } | null;
    status: 'pending' | 'ready' | 'failed';
    failed_reason?: string | null;
    segments_count: number;
    created_at: string;
    stego_hash_sha256: string;
}

export default function StegoIndex() {
    const [docs, setDocs] = useState<StegoDoc[]>([]);
    const [loading, setLoading] = useState(true);

    const fetchDocs = () => {
        axios.get('/api/stego')
            .then((r) => setDocs(r.data.data ?? r.data))
            .catch(console.error)
            .finally(() => setLoading(false));
    };

    useEffect(() => { fetchDocs(); }, []);

    const handleDelete = async (id: number) => {
        if (!confirm('Delete this stego document?')) return;
        await axios.delete(`/api/stego/${id}`);
        setDocs((prev) => prev.filter((d) => d.id !== id));
    };

    return (
        <SpaLayout>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-xl font-bold text-gray-900">My Stego Documents</h1>
                <Link to="/encode" className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    🔒 Encode New
                </Link>
            </div>

            {loading ? (
                <div className="text-center text-gray-400 py-16">Loading…</div>
            ) : docs.length === 0 ? (
                <div className="rounded-xl bg-white py-16 text-center shadow-sm">
                    <div className="text-5xl">🔒</div>
                    <p className="mt-3 text-sm text-gray-500">No encoded documents yet.</p>
                    <Link to="/encode" className="mt-4 inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Encode your first document
                    </Link>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Document</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Segments</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SHA-256</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Encoded</th>
                                <th className="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 bg-white">
                            {docs.map((d) => (
                                <tr key={d.id} className="hover:bg-gray-50">
                                    <td className="px-5 py-4">
                                        <div className="flex items-center gap-2">
                                            <span>🔒</span>
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">{d.document?.name ?? '—'}</p>
                                                <p className="text-xs uppercase text-gray-400">{d.document?.extension}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="whitespace-nowrap px-5 py-4 text-sm text-gray-600">{d.segments_count}</td>
                                    <td className="whitespace-nowrap px-5 py-4 text-sm text-gray-600">
                                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${d.status === 'ready' ? 'bg-green-100 text-green-800' : d.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'}`}>
                                            {d.status}
                                        </span>
                                        {d.status === 'failed' && d.failed_reason && (
                                            <p className="mt-1 max-w-xs truncate text-xs text-red-600" title={d.failed_reason}>{d.failed_reason}</p>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-5 py-4 font-mono text-xs text-gray-400">{d.stego_hash_sha256 ? `${d.stego_hash_sha256.slice(0, 16)}...` : '—'}</td>
                                    <td className="whitespace-nowrap px-5 py-4 text-sm text-gray-500">{new Date(d.created_at).toLocaleDateString()}</td>
                                    <td className="whitespace-nowrap px-5 py-4 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link to="/decode" className="rounded px-2 py-1 text-sm text-indigo-600 hover:bg-indigo-50">🔓 Decode</Link>
                                            <button onClick={() => handleDelete(d.id)} className="rounded px-2 py-1 text-sm text-red-500 hover:bg-red-50">🗑️</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </SpaLayout>
    );
}
