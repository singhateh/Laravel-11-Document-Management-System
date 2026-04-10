import { useState, useEffect, createContext, useContext, ReactNode } from 'react';
import axios from 'axios';

interface AuthUser {
    id: number;
    name: string;
    email: string;
    role?: string;
}

interface AuthContextType {
    user: AuthUser | null;
    token: string | null;
    loading: boolean;
    login: (email: string, password: string) => Promise<void>;
    logout: () => void;
    setToken: (token: string) => void;
}

const AuthContext = createContext<AuthContextType | null>(null);

const STORAGE_KEY = 'stegolock_token';

function setupAxios(token: string | null) {
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
        delete axios.defaults.headers.common['Authorization'];
    }
    // Always send CSRF token from meta tag
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (csrf) axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
    axios.defaults.headers.common['Accept'] = 'application/json';
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<AuthUser | null>(null);
    const [token, setTokenState] = useState<string | null>(() => localStorage.getItem(STORAGE_KEY));
    const [loading, setLoading] = useState(true);

    const setToken = (t: string) => {
        setTokenState(t);
        localStorage.setItem(STORAGE_KEY, t);
        setupAxios(t);
    };

    useEffect(() => {
        setupAxios(token);
        if (token) {
            axios.get('/api/user')
                .then((r) => setUser(r.data))
                .catch(() => {
                    setTokenState(null);
                    localStorage.removeItem(STORAGE_KEY);
                })
                .finally(() => setLoading(false));
        } else {
            setLoading(false);
        }
    }, []); // eslint-disable-line

    const login = async (email: string, password: string) => {
        setupAxios(null);
        // Get Sanctum cookie first then create a token
        const res = await axios.post('/api/auth/login', { email, password });
        const t: string = res.data.token;
        setToken(t);
        const me = await axios.get('/api/user');
        setUser(me.data);
    };

    const logout = () => {
        axios.post('/api/auth/logout').catch(() => {});
        setTokenState(null);
        setUser(null);
        localStorage.removeItem(STORAGE_KEY);
        setupAxios(null);
    };

    return (
        <AuthContext.Provider value={{ user, token, loading, login, logout, setToken }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth must be inside AuthProvider');
    return ctx;
}
