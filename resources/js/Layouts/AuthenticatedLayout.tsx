import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';
import { PageProps } from '@/types';

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage<PageProps>().props.auth.user;
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="border-b border-gray-100 bg-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        {/* Left: logo + nav links */}
                        <div className="flex items-center">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                                </Link>
                            </div>

                            {/* Desktop nav */}
                            <div className="hidden space-x-1 sm:-my-px sm:ms-6 sm:flex sm:items-center">
                                {/* Repository group */}
                                <span className="px-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    Repository
                                </span>
                                <NavLink href={route('home', undefined, false)} active={route().current('home')}>
                                    Home
                                </NavLink>
                                <NavLink
                                    href={route('documents.index', undefined, false)}
                                    active={route().current('documents.index')}
                                >
                                    Documents
                                </NavLink>
                                <NavLink
                                    href={route('folders.index', undefined, false)}
                                    active={route().current('folders.index')}
                                >
                                    Folders
                                </NavLink>
                                {(user.role === 'admin' || user.role === 'owner') && (
                                    <>
                                        <NavLink
                                            href={route('tags.index', undefined, false)}
                                            active={route().current('tags.index')}
                                        >
                                            Tags
                                        </NavLink>
                                        <NavLink
                                            href={route('categories.index', undefined, false)}
                                            active={route().current('categories.index')}
                                        >
                                            Categories
                                        </NavLink>
                                    </>
                                )}

                                {/* Admin/Owner management */}
                                {(user.role === 'admin' || user.role === 'owner') && (
                                    <>
                                        <div className="mx-2 h-5 w-px bg-gray-200" />
                                        <span className="px-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                            Management
                                        </span>
                                        <NavLink
                                            href={route('users.roles', undefined, false)}
                                            active={route().current('users.roles')}
                                        >
                                            User Roles
                                        </NavLink>
                                    </>
                                )}

                                {/* Divider */}
                                <div className="mx-2 h-5 w-px bg-gray-200" />

                                {/* StegoLock group */}
                                <span className="px-2 text-xs font-semibold uppercase tracking-wide text-indigo-500">
                                    🔒 StegoLock
                                </span>
                                <NavLink
                                    href={route('stego.index', undefined, false)}
                                    active={route().current('stego.*')}
                                >
                                    My Docs
                                </NavLink>
                                <NavLink
                                    href={route('stego.encode.form', undefined, false)}
                                    active={route().current('stego.encode.form')}
                                >
                                    Encode
                                </NavLink>
                                <NavLink
                                    href={route('stego.decode.form', undefined, false)}
                                    active={route().current('stego.decode.form')}
                                >
                                    Decode
                                </NavLink>
                                <NavLink
                                    href={route('stego.tokens', undefined, false)}
                                    active={route().current('stego.tokens')}
                                >
                                    API Tokens
                                </NavLink>
                            </div>
                        </div>

                        {/* Right: user dropdown */}
                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                <span className="mr-2 flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
                                                    {user.name.charAt(0).toUpperCase()}
                                                </span>
                                                {user.name}
                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <div className="border-b border-gray-100 px-4 py-2">
                                            <div className="text-sm font-medium text-gray-800">
                                                {user.name}
                                            </div>
                                            {user.role && (
                                                <span className="mt-1 inline-block rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium capitalize text-indigo-700">
                                                    {user.role}
                                                </span>
                                            )}
                                        </div>
                                        <Dropdown.Link href={route('profile.edit', undefined, false)}>
                                            Profile
                                        </Dropdown.Link>
                                        <Dropdown.Link href={route('stego.tokens', undefined, false)}>
                                            API Tokens
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout', undefined, false)}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        {/* Mobile hamburger */}
                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() => setShowingNavigationDropdown((s) => !s)}
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Mobile menu */}
                        <div className={(showingNavigationDropdown ? 'block' : 'hidden') + ' sm:hidden'}>
                    <div className="space-y-1 pb-3 pt-2">
                        <div className="px-4 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            Repository
                        </div>
                        <ResponsiveNavLink href={route('home', undefined, false)} active={route().current('home')}>
                            Home
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route('documents.index', undefined, false)} active={route().current('documents.index')}>
                            Documents
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route('folders.index', undefined, false)} active={route().current('folders.index')}>
                            Folders
                        </ResponsiveNavLink>
                        {(user.role === 'admin' || user.role === 'owner') && (
                            <>
                                <ResponsiveNavLink href={route('tags.index', undefined, false)} active={route().current('tags.index')}>
                                    Tags
                                </ResponsiveNavLink>
                                <ResponsiveNavLink href={route('categories.index', undefined, false)} active={route().current('categories.index')}>
                                    Categories
                                </ResponsiveNavLink>
                            </>
                        )}

                        {(user.role === 'admin' || user.role === 'owner') && (
                            <>
                                <div className="px-4 pb-1 pt-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    Management
                                </div>
                                <ResponsiveNavLink href={route('users.roles', undefined, false)} active={route().current('users.roles')}>
                                    User Roles
                                </ResponsiveNavLink>
                            </>
                        )}

                        <div className="px-4 pb-1 pt-3 text-xs font-semibold uppercase tracking-wide text-indigo-500">
                            🔒 StegoLock
                        </div>
                        <ResponsiveNavLink href={route('stego.index', undefined, false)} active={route().current('stego.*')}>
                            My Docs
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route('stego.encode.form', undefined, false)} active={route().current('stego.encode.form')}>
                            Encode
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route('stego.decode.form', undefined, false)} active={route().current('stego.decode.form')}>
                            Decode
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route('stego.tokens', undefined, false)} active={route().current('stego.tokens')}>
                            API Tokens
                        </ResponsiveNavLink>
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800">{user.name}</div>
                            <div className="text-sm font-medium text-gray-500">{user.email}</div>
                        </div>
                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit', undefined, false)}>Profile</ResponsiveNavLink>
                            <ResponsiveNavLink method="post" href={route('logout', undefined, false)} as="button">
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="bg-white shadow">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}
