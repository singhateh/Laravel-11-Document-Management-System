import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';

interface Notification {
    id: number;
    activity_type: string;
    message: string;
    status: 'UNREAD' | 'READ';
    dismiss_status: 'UNDISMISSED' | 'DISMISSED';
    created_at: string;
}

export default function NotificationBell() {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [isOpen, setIsOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    const fetchNotifications = async () => {
        try {
            setLoading(true);
            const response = await axios.get('/api/notifications');
            setNotifications(response.data.notifications || []);
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchNotifications();
        
        // Poll for new notifications every 30 seconds
        const interval = setInterval(fetchNotifications, 30000);
        
        return () => clearInterval(interval);
    }, []);

    const markAsRead = async (id: number) => {
        try {
            await axios.post(`/api/notifications/${id}/read`);
            
            setNotifications(notifications.map(n =>
                n.id === id ? { ...n, status: 'READ' } : n
            ));
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    };

    const dismiss = async (id: number) => {
        try {
            await axios.post(`/api/notifications/${id}/dismiss`);
            
            setNotifications(notifications.filter(n => n.id !== id));
        } catch (error) {
            console.error('Failed to dismiss notification:', error);
        }
    };

    const unreadCount = notifications.filter(n => n.status === 'UNREAD').length;

    const getNotificationIcon = (activityType: string) => {
        switch (activityType) {
            case 'login':
                return '🔐';
            case 'document_shared':
                return '📤';
            default:
                return '🔔';
        }
    };

    const formatTime = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        return `${diffDays}d ago`;
    };

    return (
        <div className="relative">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="relative rounded-full p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                <svg
                    className="h-6 w-6"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                    />
                </svg>
                {unreadCount > 0 && (
                    <span className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-medium text-white">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="absolute right-0 mt-2 w-80 rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5">
                    <div className="border-b border-gray-100 px-4 py-3">
                        <h3 className="text-sm font-semibold text-gray-900">Notifications</h3>
                    </div>
                    
                    <div className="max-h-96 overflow-y-auto">
                        {loading ? (
                            <div className="px-4 py-8 text-center text-sm text-gray-500">
                                Loading...
                            </div>
                        ) : notifications.length === 0 ? (
                            <div className="px-4 py-8 text-center text-sm text-gray-500">
                                No notifications
                            </div>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {notifications.map((notification) => (
                                    <li
                                        key={notification.id}
                                        className={`px-4 py-3 hover:bg-gray-50 ${
                                            notification.status === 'UNREAD' ? 'bg-blue-50' : ''
                                        }`}
                                    >
                                        <div className="flex items-start gap-3">
                                            <span className="text-lg">
                                                {getNotificationIcon(notification.activity_type)}
                                            </span>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm text-gray-900">
                                                    {notification.message}
                                                </p>
                                                <p className="mt-1 text-xs text-gray-500">
                                                    {formatTime(notification.created_at)}
                                                </p>
                                            </div>
                                            <div className="flex gap-1">
                                                {notification.status === 'UNREAD' && (
                                                    <button
                                                        onClick={() => markAsRead(notification.id)}
                                                        className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                        title="Mark as read"
                                                    >
                                                        <svg
                                                            className="h-4 w-4"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                strokeLinecap="round"
                                                                strokeLinejoin="round"
                                                                strokeWidth={2}
                                                                d="M5 13l4 4L19 7"
                                                            />
                                                        </svg>
                                                    </button>
                                                )}
                                                <button
                                                    onClick={() => dismiss(notification.id)}
                                                    className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                    title="Dismiss"
                                                >
                                                    <svg
                                                        className="h-4 w-4"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            strokeWidth={2}
                                                            d="M6 18L18 6M6 6l12 12"
                                                        />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    
                    {notifications.length > 0 && (
                        <div className="border-t border-gray-100 px-4 py-2">
                            <button
                                onClick={() => {
                                    notifications.forEach(n => {
                                        if (n.status === 'UNREAD') {
                                            markAsRead(n.id);
                                        }
                                    });
                                }}
                                className="text-xs text-indigo-600 hover:text-indigo-800"
                            >
                                Mark all as read
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
