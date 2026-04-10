interface AvatarProps {
    user: {
        name: string;
        email?: string;
        avatar?: string | null;
    };
    size?: 'sm' | 'md' | 'lg' | 'xl';
    className?: string;
}

export default function Avatar({ user, size = 'md', className = '' }: AvatarProps) {
    const sizeClasses = {
        sm: 'h-8 w-8 text-xs',
        md: 'h-10 w-10 text-sm',
        lg: 'h-12 w-12 text-base',
        xl: 'h-16 w-16 text-lg',
    };

    const getInitials = (name: string) => {
        const names = name.split(' ');
        if (names.length >= 2) {
            return names[0].charAt(0).toUpperCase() + names[1].charAt(0).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    };

    const getColorFromName = (name: string) => {
        const colors = [
            'bg-red-500',
            'bg-orange-500',
            'bg-amber-500',
            'bg-yellow-500',
            'bg-lime-500',
            'bg-green-500',
            'bg-emerald-500',
            'bg-teal-500',
            'bg-cyan-500',
            'bg-sky-500',
            'bg-blue-500',
            'bg-indigo-500',
            'bg-violet-500',
            'bg-purple-500',
            'bg-fuchsia-500',
            'bg-pink-500',
            'bg-rose-500',
        ];
        
        let hash = 0;
        for (let i = 0; i < name.length; i++) {
            hash = name.charCodeAt(i) + ((hash << 5) - hash);
        }
        
        return colors[Math.abs(hash) % colors.length];
    };

    if (user.avatar) {
        return (
            <img
                src={`/storage/${user.avatar}`}
                alt={user.name}
                className={`${sizeClasses[size]} rounded-full object-cover ${className}`}
            />
        );
    }

    return (
        <div
            className={`${sizeClasses[size]} ${getColorFromName(user.name)} rounded-full flex items-center justify-center font-semibold text-white ${className}`}
            title={user.name}
        >
            {getInitials(user.name)}
        </div>
    );
}
