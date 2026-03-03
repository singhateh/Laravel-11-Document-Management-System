import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';

export default function Home({ auth }: PageProps) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Home
                </h2>
            }
        >
            <Head title="Home" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {/* Contacts Module */}
                        <Link
                            href={route('contacts.index')}
                            className="group block"
                        >
                            <div className="overflow-hidden rounded-lg bg-red-500 shadow-lg transition-transform hover:scale-105">
                                <div className="p-8 text-center">
                                    <div className="mb-4 flex justify-center">
                                        <svg
                                            className="h-20 w-20 text-white"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                        </svg>
                                    </div>
                                    <h5 className="text-lg font-semibold text-white">
                                        Contacts
                                    </h5>
                                </div>
                            </div>
                        </Link>

                        {/* Documents Module */}
                        <Link
                            href={route('documents.index')}
                            className="group block"
                        >
                            <div className="overflow-hidden rounded-lg bg-gray-500 shadow-lg transition-transform hover:scale-105">
                                <div className="p-8 text-center">
                                    <div className="mb-4 flex justify-center">
                                        <svg
                                            className="h-20 w-20 text-white"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fillRule="evenodd"
                                                d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                    </div>
                                    <h5 className="text-lg font-semibold text-white">
                                        Documents
                                    </h5>
                                </div>
                            </div>
                        </Link>

                        {/* Projects Module */}
                        <Link
                            href={route('projects.index')}
                            className="group block"
                        >
                            <div className="overflow-hidden rounded-lg bg-blue-500 shadow-lg transition-transform hover:scale-105">
                                <div className="p-8 text-center">
                                    <div className="mb-4 flex justify-center">
                                        <svg
                                            className="h-20 w-20 text-white"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                        </svg>
                                    </div>
                                    <h5 className="text-lg font-semibold text-white">
                                        Projects
                                    </h5>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
