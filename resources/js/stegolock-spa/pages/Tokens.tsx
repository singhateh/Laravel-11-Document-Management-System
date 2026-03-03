import { useEffect, useState } from 'react';
import axios from 'axios';
import SpaLayout from '../components/SpaLayout';

interface TokenInfo {
    id: number;
    name: string;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
}

export default function Tokens() {
    const [tokens, setTokens] = useState<TokenInfo[]>([]);
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [newToken, setNewToken] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [copied, setCopied] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        axios.get('/api/auth/tokens').then((r) => setTokens(r.data)).catch(console.error);
    }, []);

    const createToken = async () => {
        setError('');
        if (!name.trim()) { setError('Token name is required.'); return; }
        setLoading(true);
        try {
            const res = await axios.post('/api/auth/tokens', { name: name.trim() });
            setNewToken(res.data.token);
            if (res.data.token_info) setTokens((p) => [res.data.token_info, ...p]);
            setName('');
            setCreating(false);
        } catch (e: any) {
            setError(e.response?.data?.message ?? 'Failed to create token.');
        } finally {
            setLoading(false);
        }
    };

    const revokeToken = async (id: number) => {
        if (!confirm('Revoke this token?')) return;
        await axios.delete(`/api/auth/tokens/${id}`);
        setTokens((p) => p.filter((t) => t.id !== id));
    };

    const copy = () => {
        if (!newToken) return;
        navigator.clipboard.writeText(newToken).then(() => { setCopied(true); setTimeout(() => setCopied(false), 2000); });
    };

    return (
        <SpaLayout>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-xl font-bold text-gray-900">API Tokens</h1>
                <button onClick={() => { setCreating(true); setNewToken(null); }} className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">+ New Token</button>
            </div>

            <div className="space-y-5">
                {newToken && (
                    <div className="rounded-xl border border-green-200 bg-green-50 p-5">
                        <p className="mb-2 text-sm font-semibold text-green-800">✅ Token created — copy it now (shown once)</p>
                        <div className="flex items-center gap-2">
                            <code className="flex-1 break-all rounded bg-white border border-green-200 px-3 py-2 text-xs font-mono text-green-900">{newToken}</code>
                            <button onClick={copy} className="shrink-0 rounded-md border border-green-300 bg-white px-3 py-2 text-xs font-medium text-green-700 hover:bg-green-100">{copied ? '✓ Copied' : 'Copy'}</button>
                        </div>
                    </div>
                )}

                {creating && (
                    <div className="rounded-xl bg-white p-5 shadow-sm">
                        <h3 className="mb-3 text-sm font-medium text-gray-800">Create new token</h3>
                        {error && <p className="mb-2 text-xs text-red-600">{error}</p>}
                        <div className="flex gap-3">
                            <input type="text" value={name} onChange={(e) => setName(e.target.value)} placeholder="Token name" className="flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" onKeyDown={(e) => e.key === 'Enter' && createToken()} autoFocus />
                            <button onClick={createToken} disabled={loading} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">{loading ? 'Creating…' : 'Create'}</button>
                            <button onClick={() => { setCreating(false); setError(''); }} className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                        </div>
                    </div>
                )}

                {tokens.length === 0 && !creating ? (
                    <div className="rounded-xl bg-white py-14 text-center shadow-sm">
                        <div className="text-4xl">🔑</div>
                        <p className="mt-3 text-sm text-gray-500">No tokens yet. Create one to access the API.</p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Last Used</th>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Expires</th>
                                    <th className="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 bg-white">
                                {tokens.map((t) => {
                                    const expired = t.expires_at && new Date(t.expires_at) < new Date();
                                    return (
                                        <tr key={t.id} className="hover:bg-gray-50">
                                            <td className="px-5 py-4">
                                                <div className="flex items-center gap-2">
                                                    <span>🔑</span>
                                                    <span className="text-sm font-medium text-gray-800">{t.name}</span>
                                                    {expired && <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">Expired</span>}
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4 text-sm text-gray-500">{new Date(t.created_at).toLocaleDateString()}</td>
                                            <td className="whitespace-nowrap px-5 py-4 text-sm text-gray-400">{t.last_used_at ? new Date(t.last_used_at).toLocaleString() : 'Never'}</td>
                                            <td className="whitespace-nowrap px-5 py-4 text-sm text-gray-400">{t.expires_at ? new Date(t.expires_at).toLocaleDateString() : '—'}</td>
                                            <td className="whitespace-nowrap px-5 py-4 text-right">
                                                <button onClick={() => revokeToken(t.id)} className="text-sm text-red-500 hover:text-red-700">Revoke</button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* API reference */}
                <div className="rounded-xl bg-gray-900 p-5 text-sm text-gray-300">
                    <p className="mb-2 font-semibold text-white">Quick API reference</p>
                    <pre className="text-xs leading-relaxed overflow-x-auto whitespace-pre-wrap">{`Authorization: Bearer <token>

POST /api/stego/encode   (multipart: document_id, master_key, carriers[])
POST /api/stego/decode   (json: { stego_document_id, master_key }) → download
GET  /api/stego          → list your encoded documents
GET  /api/auth/tokens    → list tokens`}</pre>
                </div>
            </div>
        </SpaLayout>
    );
}
